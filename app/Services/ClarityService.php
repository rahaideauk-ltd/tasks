<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Microsoft Clarity Data Export API.
 * Token: Clarity project -> Settings -> Data Export -> Generate new API token.
 * Limits: numOfDays 1..3, max 10 requests per project per day.
 */
class ClarityService
{
    protected const ENDPOINT = 'https://www.clarity.ms/export-data/api/v1/project-live-insights';

    public function fetch(string $token, int $numOfDays = 3): array
    {
        $res = Http::withToken($token)->acceptJson()->timeout(30)
            ->get(self::ENDPOINT, ['numOfDays' => $numOfDays, 'dimension1' => 'URL']);

        if (! $res->successful()) {
            throw new \RuntimeException('Clarity API '.$res->status().': '.mb_substr($res->body(), 0, 200));
        }

        return $this->normalize($res->json() ?? [], $numOfDays);
    }

    /** Raw shape: [{metricName, information: [{URL, totalSessionCount | sessionsWithMetricPercentage, ...}]}, ...] */
    protected function normalize(array $raw, int $numOfDays): array
    {
        $by = [];
        foreach ($raw as $m) {
            $by[$m['metricName'] ?? ''] = $m['information'] ?? [];
        }
        $num = fn ($v) => (float) ($v ?? 0);
        $rows = function (string $name, string $field) use ($by, $num) {
            $out = [];
            foreach ($by[$name] ?? [] as $r) {
                $url = $r['URL'] ?? $r['Url'] ?? null;
                if ($url) {
                    $out[] = ['url' => $url, 'value' => $num($r[$field] ?? 0)];
                }
            }
            usort($out, fn ($a, $b) => $b['value'] <=> $a['value']);

            return $out;
        };
        $total = fn (string $name, string $field) => array_sum(array_map(fn ($r) => $num($r[$field] ?? 0), $by[$name] ?? []));

        return [
            'numOfDays' => $numOfDays,
            'totals' => [
                'sessions' => (int) $total('Traffic', 'totalSessionCount'),
                'rageClicks' => $total('Rage Clicks', 'sessionsWithMetricPercentage'),
                'deadClicks' => $total('Dead Clicks', 'sessionsWithMetricPercentage'),
                'quickBacks' => $total('Quick backs', 'sessionsWithMetricPercentage'),
                'jsErrors' => $total('Script Errors', 'sessionsWithMetricPercentage'),
                'avgScrollDepth' => $num($by['Scroll Depth'][0]['averageScrollDepth'] ?? 0),
                'avgEngagementSec' => $num($by['Engagement Time'][0]['activeTime'] ?? 0),
            ],
            'traffic' => $rows('Traffic', 'totalSessionCount'),
            'rageClicks' => $rows('Rage Clicks', 'sessionsWithMetricPercentage'),
            'deadClicks' => $rows('Dead Clicks', 'sessionsWithMetricPercentage'),
            'quickBacks' => $rows('Quick backs', 'sessionsWithMetricPercentage'),
            'jsErrors' => $rows('Script Errors', 'sessionsWithMetricPercentage'),
        ];
    }
}
