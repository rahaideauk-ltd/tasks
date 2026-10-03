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

    /**
     * Mark the project as running and dispatch. With a real queue the worker picks it up;
     * with QUEUE_CONNECTION=sync it runs after the HTTP response so the request is not blocked.
     */
    public static function start(Project $project, bool $sync = true, bool $useRules = true, bool $useAi = true): void
    {
        $project->forceFill(['analysis_status' => 'running'])->save();
        $pending = static::dispatch($project, $sync, $useRules, $useAi);
        if (config('queue.default') === 'sync') {
            $pending->afterResponse();
        }
    }

    public function handle(ProjectAnalyzer $analyzer): void
    {
        // Outside a worker (after-response run) the queue timeout does not apply; raise PHP's limit instead.
        @set_time_limit($this->timeout);

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
