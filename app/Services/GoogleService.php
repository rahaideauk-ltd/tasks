<?php

namespace App\Services;

use App\Models\Project;
use Google\Client as GoogleClient;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\GoogleAnalyticsAdmin;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

/**
 * Google OAuth (the client authorises from their portal) + Search Console + GA4 pulls.
 * Needs services.google.client_id / client_secret (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).
 */
class GoogleService
{
    public const SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    public static function configured(): bool
    {
        return (bool) (config('services.google.client_id') && config('services.google.client_secret'));
    }

    protected function client(): GoogleClient
    {
        $c = new GoogleClient;
        $c->setClientId(config('services.google.client_id'));
        $c->setClientSecret(config('services.google.client_secret'));
        $c->setRedirectUri(route('google.callback'));
        $c->setScopes(self::SCOPES);
        $c->setAccessType('offline');
        $c->setPrompt('consent');

        return $c;
    }

    public function authUrl(Project $project): string
    {
        return $this->client()->createAuthUrl(null, ['state' => $project->token]);
    }

    /** Exchange the OAuth code and store tokens on the project. */
    public function connect(Project $project, string $code): void
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth: '.($token['error_description'] ?? $token['error']));
        }
        $project->forceFill(['google_tokens' => $token])->save();
    }

    /** Authorised client; refreshes the access token when expired and persists it. */
    protected function authed(Project $project): GoogleClient
    {
        $client = $this->client();
        $client->setAccessToken($project->google_tokens);
        if ($client->isAccessTokenExpired()) {
            $refresh = $client->getRefreshToken() ?: ($project->google_tokens['refresh_token'] ?? null);
            if (! $refresh) {
                throw new \RuntimeException('Google token expired and no refresh token stored; reconnect Google.');
            }
            $new = $client->fetchAccessTokenWithRefreshToken($refresh);
            if (isset($new['error'])) {
                throw new \RuntimeException('Google token refresh failed: '.$new['error']);
            }
            $project->forceFill(['google_tokens' => $client->getAccessToken()])->save();
        }

        return $client;
    }

    /** @return array<int, array{siteUrl:string, permission:string}> */
    public function listSites(Project $project): array
    {
        $sc = new SearchConsole($this->authed($project));
        $out = [];
        foreach ($sc->sites->listSites()->getSiteEntry() ?? [] as $s) {
            $out[] = ['siteUrl' => $s->getSiteUrl(), 'permission' => $s->getPermissionLevel()];
        }

        return $out;
    }

    /** @return array<int, array{property:string, displayName:string}> */
    public function listGa4Properties(Project $project): array
    {
        $admin = new GoogleAnalyticsAdmin($this->authed($project));
        $out = [];
        foreach ($admin->accountSummaries->listAccountSummaries(['pageSize' => 200])->getAccountSummaries() ?? [] as $acc) {
            foreach ($acc->getPropertySummaries() ?? [] as $p) {
                $out[] = ['property' => $p->getProperty(), 'displayName' => $acc->getDisplayName().' / '.$p->getDisplayName()];
            }
        }

        return $out;
    }

    /** Search Console: totals, top pages, top queries, devices for the last N days. */
    public function fetchSearchConsole(Project $project, int $days = 28): array
    {
        $sc = new SearchConsole($this->authed($project));
        $site = $project->gsc_site_url;
        $end = now()->subDays(2);  // GSC data lags ~2 days
        $range = ['startDate' => $end->copy()->subDays($days)->toDateString(), 'endDate' => $end->toDateString()];

        $query = function (array $dimensions, int $limit) use ($sc, $site, $range) {
            $req = new SearchAnalyticsQueryRequest($range + ['dimensions' => $dimensions, 'rowLimit' => $limit]);
            $rows = [];
            foreach ($sc->searchanalytics->query($site, $req)->getRows() ?? [] as $r) {
                $rows[] = [
                    'key' => $r->getKeys()[0], 'clicks' => (int) $r->getClicks(), 'impressions' => (int) $r->getImpressions(),
                    'ctr' => round($r->getCtr() * 100, 2), 'position' => round($r->getPosition(), 1),
                ];
            }

            return $rows;
        };

        $totalsRow = $sc->searchanalytics->query($site, new SearchAnalyticsQueryRequest($range))->getRows()[0] ?? null;

        return [
            'range' => $range,
            'totals' => [
                'clicks' => (int) ($totalsRow?->getClicks() ?? 0), 'impressions' => (int) ($totalsRow?->getImpressions() ?? 0),
                'ctr' => round(($totalsRow?->getCtr() ?? 0) * 100, 2), 'position' => round($totalsRow?->getPosition() ?? 0, 1),
            ],
            'pages' => $query(['page'], 50),
            'queries' => $query(['query'], 50),
            'devices' => $query(['device'], 3),
        ];
    }

    /** GA4: totals, top pages, devices, channels for the last N days. */
    public function fetchGa4(Project $project, int $days = 28): array
    {
        $ga = new AnalyticsData($this->authed($project));
        $property = $project->ga4_property;
        $dateRanges = [['startDate' => "{$days}daysAgo", 'endDate' => 'yesterday']];
        $metric = fn (string $n) => ['name' => $n];
        $dim = fn (string $n) => ['name' => $n];

        $run = fn (array $body) => $ga->properties->runReport($property, new RunReportRequest(['dateRanges' => $dateRanges] + $body));
        $pct = fn ($v) => round(((float) $v) * 100, 1);

        $table = function ($report, string $key, array $metrics, array $pctCols = []) use ($pct) {
            $rows = [];
            foreach ($report->getRows() ?? [] as $r) {
                $row = [$key => $r->getDimensionValues()[0]->getValue()];
                foreach ($metrics as $i => $name) {
                    $v = (float) $r->getMetricValues()[$i]->getValue();
                    $row[$name] = in_array($name, $pctCols, true) ? $pct($v) : round($v, 1);
                }
                $rows[] = $row;
            }

            return $rows;
        };

        $totals = $run(['metrics' => array_map($metric, ['sessions', 'totalUsers', 'engagementRate', 'bounceRate', 'averageSessionDuration', 'conversions'])]);
        $t = $totals->getRows()[0] ?? null;
        $m = fn (int $i) => $t ? (float) $t->getMetricValues()[$i]->getValue() : 0.0;

        $pages = $run(['dimensions' => [$dim('pagePath')], 'metrics' => array_map($metric, ['screenPageViews', 'engagementRate', 'bounceRate']),
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 50]);
        $devices = $run(['dimensions' => [$dim('deviceCategory')], 'metrics' => array_map($metric, ['sessions', 'engagementRate', 'bounceRate'])]);
        $channels = $run(['dimensions' => [$dim('sessionDefaultChannelGroup')], 'metrics' => array_map($metric, ['sessions', 'engagementRate']),
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 10]);

        return [
            'totals' => [
                'sessions' => (int) $m(0), 'users' => (int) $m(1), 'engagementRate' => $pct($m(2)), 'bounceRate' => $pct($m(3)),
                'avgSessionSec' => (int) round($m(4)), 'conversions' => (int) $m(5),
            ],
            'pages' => $table($pages, 'path', ['views', 'engagementRate', 'bounceRate'], ['engagementRate', 'bounceRate']),
            'devices' => $table($devices, 'device', ['sessions', 'engagementRate', 'bounceRate'], ['engagementRate', 'bounceRate']),
            'channels' => $table($channels, 'channel', ['sessions', 'engagementRate'], ['engagementRate']),
        ];
    }
}
