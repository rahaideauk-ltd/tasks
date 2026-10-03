<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeProject;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_redirects_to_portal_and_starts_analysis(): void
    {
        Bus::fake();

        $res = $this->post('/projects', ['name' => 'Cafe Roya', 'site_url' => 'example.com']);

        $project = Project::sole();
        $res->assertRedirect(route('portal.show', $project->token));
        $this->assertSame('running', $project->analysis_status);
        Bus::assertDispatched(AnalyzeProject::class, fn ($job) => $job->project->is($project));
    }

    public function test_sign_ups_are_rate_limited_per_ip(): void
    {
        Bus::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/projects', ['name' => "P{$i}"])->assertRedirect();
        }
        $this->post('/projects', ['name' => 'one too many'])->assertStatus(429);
        $this->assertSame(5, Project::count());
    }
}
