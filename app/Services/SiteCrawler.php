<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Fetch the homepage and reduce it to facts + plain text for the AI prompt. */
class SiteCrawler
{
    public function summarize(?string $siteUrl, int $maxChars = 6000): ?array
    {
        if (! $siteUrl) {
            return null;
        }
        $url = preg_match('#^https?://#i', $siteUrl) ? $siteUrl : "https://{$siteUrl}";

        try {
            $res = Http::withUserAgent('Mozilla/5.0 (compatible; ClientTasksBot/1.0)')->timeout(15)->get($url);
            $html = $res->body();
            $pick = fn (string $re) => preg_match($re, $html, $m) ? trim(html_entity_decode($m[1])) : '';
            preg_match_all('#<h1[^>]*>(.*?)</h1>#is', $html, $h1);

            return [
                'url' => (string) $res->effectiveUri() ?: $url,
                'status' => $res->status(),
                'title' => $pick('#<title[^>]*>(.*?)</title>#is'),
                'metaDesc' => $pick('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']#i')
                    ?: $pick('#<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']#i'),
                'h1s' => array_slice(array_values(array_filter(array_map(fn ($h) => $this->strip($h), $h1[1]))), 0, 5),
                'https' => str_starts_with((string) $res->effectiveUri() ?: $url, 'https://'),
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

    protected function strip(string $html): string
    {
        $t = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html);
        $t = preg_replace('#<!--.*?-->#s', ' ', $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $t));
    }
}
