<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;

/** Orchestrates: sync data from every connected source -> rules + AI -> draft tasks (or publish directly). */
class ProjectAnalyzer
{
    public function __construct(
        protected GoogleService $google,
        protected ClarityService $clarity,
        protected SiteCrawler $crawler,
        protected RulesEngine $rules,
        protected AiTaskGenerator $ai,
    ) {}

    /** Pull fresh data. Per-source errors are stored in data.errors, not thrown. */
    public function sync(Project $project): Project
    {
        $data = $project->data ?? [];
        $data['errors'] = [];
        $data['site'] = $this->crawler->summarize($project->site_url);

        if ($project->hasGoogle() && $project->gsc_site_url) {
            try {
                $data['gsc'] = $this->google->fetchSearchConsole($project);
            } catch (\Throwable $e) {
                $data['errors']['gsc'] = $e->getMessage();
            }
        }
        if ($project->hasGoogle() && $project->ga4_property) {
            try {
                $data['ga4'] = $this->google->fetchGa4($project);
            } catch (\Throwable $e) {
                $data['errors']['ga4'] = $e->getMessage();
            }
        }
        if ($project->hasClarity()) {
            try {
                $data['clarity'] = $this->clarity->fetch($project->clarity_token);
            } catch (\Throwable $e) {
                $data['errors']['clarity'] = $e->getMessage();
            }
        }

        $project->forceFill(['data' => $data, 'data_fetched_at' => now()])->save();

        return $project;
    }

    /**
     * Generate suggestions and store them as draft tasks.
     *
     * @return array{created:int, published:int, errors:array}
     */
    public function analyze(Project $project, bool $useRules = true, bool $useAi = true): array
    {
        $project->forceFill(['analysis_status' => 'running'])->save();
        $existing = $project->tasks()->get();
        $seen = $existing->map(fn ($t) => mb_strtolower($t->title_en ?: $t->title_fa))->flip()->all();
        $created = 0;
        $errors = [];
        $failed = false;
        $summary = $project->analysis['summary'] ?? null;

        $add = function (array $s) use ($project, &$seen, &$created) {
            $key = mb_strtolower($s['title']['en'] ?: $s['title']['fa']);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $cat = $project->categoryFor($s['category']['en'] ?? 'General', $s['category']['fa'] ?? null);
            $project->tasks()->create([
                'category_id' => $cat->id, 'round' => 0, 'status' => Task::STATUS_DRAFT,
                'title_fa' => $s['title']['fa'] ?: $s['title']['en'], 'title_en' => $s['title']['en'] ?: $s['title']['fa'],
                'description_fa' => $s['description']['fa'] ?? '', 'description_en' => $s['description']['en'] ?? '',
                'priority' => $s['priority'] ?? 'medium', 'source' => $s['source'] ?? 'rule',
                'reason' => $s['reason'] ?? null, 'evidence' => $s['evidence'] ?? null,
            ]);
            $created++;
        };

        if ($useRules) {
            foreach ($this->rules->run($project->data ?? []) as $s) {
                $add($s);
            }
        }
        if ($useAi) {
            if (AiTaskGenerator::configured()) {
                try {
                    $out = $this->ai->generate($project);
                    foreach ($out['tasks'] as $s) {
                        $add($s);
                    }
                    $summary = $out['summary'];
                } catch (\Throwable $e) {
                    report($e);
                    $errors['ai'] = $e->getMessage();
                    $failed = true;
                }
            } else {
                $errors['ai'] = 'ANTHROPIC_API_KEY is not set';
            }
        }

        $published = config('tasks.auto_publish') ? $project->publishDrafts() : 0;

        $project->forceFill([
            'analysis' => ['summary' => $summary, 'created' => $created, 'published' => $published, 'errors' => $errors],
            'analyzed_at' => now(),
            'analysis_status' => $failed ? 'failed' : 'done',
        ])->save();

        return ['created' => $created, 'published' => $published, 'errors' => $errors];
    }
}
