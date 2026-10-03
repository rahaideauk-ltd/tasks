<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ProjectAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sync data then generate tasks. Dispatched after onboarding and from the admin panel. */
class AnalyzeProject implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public Project $project, public bool $sync = true, public bool $useRules = true, public bool $useAi = true) {}

    public function handle(ProjectAnalyzer $analyzer): void
    {
        $project = $this->project->fresh();
        if ($this->sync) {
            $analyzer->sync($project);
        }
        $analyzer->analyze($project, $this->useRules, $this->useAi);
    }

    public function failed(\Throwable $e): void
    {
        $this->project->fresh()?->forceFill([
            'analysis_status' => 'failed',
            'analysis' => ['summary' => null, 'created' => 0, 'published' => 0, 'errors' => ['job' => $e->getMessage()]],
        ])->save();
    }
}
