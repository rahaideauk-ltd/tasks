<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Fetch the homepage and reduce it to facts + plain text for the AI prompt. */
class SiteCrawler
{
    protected const MAX_REDIRECTS = 3;

    public function summarize(?string $siteUrl, int $maxChars = 6000): ?array
    {
        if (! $siteUrl) {
            return null;
        }
        $url = preg_match('#^https?://#i', $siteUrl) ? $siteUrl : "https://{$siteUrl}";

        try {
            [$res, $finalUrl] = $this->fetch($url);
            $html = $res->body();
            $pick = fn (string $re) => preg_match($re, $html, $m) ? trim(html_entity_decode($m[1])) : '';
            preg_match_all('#<h1[^>]*>(.*?)</h1>#is', $html, $h1);

            return [
                'url' => $finalUrl,
                'status' => $res->status(),
                'title' => $pick('#<title[^>]*>(.*?)</title>#is'),
                'metaDesc' => $pick('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']#i')
                    ?: $pick('#<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']#i'),
                'h1s' => array_slice(array_values(array_filter(array_map(fn ($h) => $this->strip($h), $h1[1]))), 0, 5),
                'https' => str_starts_with(strtolower($finalUrl), 'https://'),
                'hasViewport' => (bool) preg_match('#<meta[^>]+name=["\']viewport["\']#i', $html),
                'hasGa' => (bool) preg_match('#gtag\(|googletagmanager\.com|google-analytics\.com#i', $html),
                'hasClarity' => (bool) preg_match('#clarity\.ms#i', $html),
                'text' => mb_substr($this->strip($html), 0, $maxChars),
                'fetchedAt' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return ['url' => $url, 'error' => $e->getMessage(), 'fetchedAt' => now()->toIso8601String()];
        }
    }

    /**
     * GET a public URL. The URL comes from an anonymous form, so every hop (including redirects)
     * must resolve to a public IP, and the connection is pinned to that IP (no DNS rebinding).
     *
     * @return array{0: Response, 1: string} response and final URL
     */
    protected function fetch(string $url): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->resolvePublic($url);
            $res = Http::withUserAgent('Mozilla/5.0 (compatible; ClientTasksBot/1.0)')->timeout(15)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:".(str_contains($ip, ':') ? "[{$ip}]" : $ip)]],
                ])
                ->get($url);

            $location = $res->header('Location');
            if (! $res->redirect() || $location === '') {
                return [$res, $url];
            }
            $url = $this->absolute($url, $location);
        }

        throw new \RuntimeException('Too many redirects');
    }

    /** @return array{0: string, 1: int, 2: string} host, port, public IP */
    protected function resolvePublic(string $url): array
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower(trim($p['host'] ?? '', '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Only http(s) URLs are allowed');
        }
        $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new \RuntimeException('Only ports 80 and 443 are allowed');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            throw new \RuntimeException("Could not resolve {$host}");
        }
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new \RuntimeException("{$host} points to a private or reserved address");
            }
        }

        return [$host, $port, $ips[0]];
    }

    protected function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // carrier-grade NAT 100.64.0.0/10 is not covered by the PHP flags
        $long = ip2long($ip);

        return $long === false || ($long & 0xFFC00000) !== ip2long('100.64.0.0');
    }

    protected function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($location, '//')) {
            return $b['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');

        return $origin.($dir ?: '/').$location;
    }

    protected function strip(string $html): string
    {
        $t = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html);
        $t = preg_replace('#<!--.*?-->#s', ' ', $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $t));
    }
}
