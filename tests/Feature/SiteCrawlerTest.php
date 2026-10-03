<?php

namespace Tests\Feature;

use App\Services\SiteCrawler;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteCrawlerTest extends TestCase
{
    public static function blockedUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/'],
            'private' => ['http://10.0.0.5/'],
            'metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'cgnat' => ['http://100.64.1.1/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'other port' => ['http://93.184.215.14:8080/'],
            'other scheme' => ['ftp://93.184.215.14/'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_refuses_non_public_targets(string $url): void
    {
        Http::fake();

        $out = app(SiteCrawler::class)->summarize($url);

        $this->assertArrayHasKey('error', $out);
        Http::assertNothingSent();
    }

    public function test_refuses_redirect_to_private_address(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);

        $out = app(SiteCrawler::class)->summarize('http://93.184.215.14/');

        $this->assertArrayHasKey('error', $out);
        Http::assertSentCount(1);
    }

    public function test_follows_public_redirect_and_summarizes(): void
    {
        Http::fake(function ($request) {
            return str_starts_with($request->url(), 'http://')
                ? Http::response('', 301, ['Location' => 'https://93.184.215.14/home'])
                : Http::response('<html><head><title>Cafe Roya</title><meta name="viewport" content="x"></head><body><h1>Best coffee</h1></body></html>');
        });

        $out = app(SiteCrawler::class)->summarize('http://93.184.215.14');

        $this->assertArrayNotHasKey('error', $out);
        $this->assertSame('https://93.184.215.14/home', $out['url']);
        $this->assertTrue($out['https']);
        $this->assertSame('Cafe Roya', $out['title']);
        $this->assertSame(['Best coffee'], $out['h1s']);
        $this->assertTrue($out['hasViewport']);
    }
}
