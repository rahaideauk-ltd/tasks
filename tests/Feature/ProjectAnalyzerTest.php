<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Services\GoogleService;
use App\Services\ProjectAnalyzer;
use App\Services\RulesEngine;
use App\Services\SiteCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SiteCrawler::class, fn ($m) => $m->shouldReceive('summarize')->andReturn(null));
    }

    public function test_sync_drops_data_of_disconnected_sources(): void
    {
        $project = Project::create(['name' => 'Shop']);
        $project->forceFill(['data' => ['gsc' => ['totals' => ['clicks' => 9]], 'ga4' => ['totals' => []], 'clarity' => ['totals' => []]]])->save();

        app(ProjectAnalyzer::class)->sync($project);

        $data = $project->fresh()->data;
        $this->assertArrayNotHasKey('gsc', $data);
        $this->assertArrayNotHasKey('ga4', $data);
        $this->assertArrayNotHasKey('clarity', $data);
    }

    public function test_failing_source_records_error_and_is_not_reported_as_missing(): void
    {
        $this->mock(GoogleService::class, fn ($m) => $m->shouldReceive('fetchSearchConsole')->andThrow(new \RuntimeException('quota')));
        $project = Project::create(['name' => 'Shop']);
        $project->forceFill(['google_tokens' => ['access_token' => 'x'], 'gsc_site_url' => 'sc-domain:example.com', 'data' => ['gsc' => ['old' => true]]])->save();

        app(ProjectAnalyzer::class)->sync($project);
        $data = $project->fresh()->data;

        $this->assertSame('quota', $data['errors']['gsc']);
        $this->assertArrayNotHasKey('gsc', $data);

        $ids = array_column(app(RulesEngine::class)->run($data), 'rule_id');
        $this->assertNotContains('gsc-missing', $ids);
        $this->assertContains('ga4-missing', $ids);
    }

    public function test_analyze_creates_rule_drafts_without_duplicates(): void
    {
        config(['services.anthropic.key' => null, 'tasks.auto_publish' => false]);
        $project = Project::create(['name' => 'Shop']);
        $analyzer = app(ProjectAnalyzer::class);

        $first = $analyzer->analyze($project, useRules: true, useAi: false);
        $second = $analyzer->analyze($project, useRules: true, useAi: false);

        $this->assertSame(3, $first['created']); // connect GSC, GA4, Clarity
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $project->tasks()->where('status', Task::STATUS_DRAFT)->count());
        $this->assertSame('done', $project->fresh()->analysis_status);
    }
}
