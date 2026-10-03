<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectFact;
use App\Models\Skill;
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
        // Start from scratch: a disconnected or failing source must not leave its old snapshot behind.
        $data = ['errors' => [], 'site' => $this->crawler->summarize($project->site_url)];

        $sources = [
            'gsc' => [$project->hasGoogle() && $project->gsc_site_url, fn () => $this->google->fetchSearchConsole($project)],
            'ga4' => [$project->hasGoogle() && $project->ga4_property, fn () => $this->google->fetchGa4($project)],
            'clarity' => [$project->hasClarity(), fn () => $this->clarity->fetch($project->clarity_token)],
        ];
        foreach ($sources as $key => [$connected, $fetch]) {
            if (! $connected) {
                continue;
            }
            try {
                $data[$key] = $fetch();
            } catch (\Throwable $e) {
                $data['errors'][$key] = $e->getMessage();
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
        $factsAdded = 0;
        $memoryUpdated = false;
        // slug -> id; a project skill wins over a global one with the same slug
        $skillIds = Skill::query()->availableFor($project)->get()->unique('slug')->pluck('id', 'slug')->all();

        $add = function (array $s) use ($project, $skillIds, &$seen, &$created) {
            $key = mb_strtolower($s['title']['en'] ?: $s['title']['fa']);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $cat = $project->categoryFor($s['category']['en'] ?? 'General', $s['category']['fa'] ?? null);
            $project->tasks()->create([
                'category_id' => $cat->id, 'skill_id' => $skillIds[$s['skill'] ?? ''] ?? null, 'round' => 0, 'status' => Task::STATUS_DRAFT,
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
                    foreach ($out['facts'] as $f) {
                        $factsAdded += (int) (bool) $project->addFact($f['kind'] ?? '', $f['value'] ?? '', $f['note'] ?? null, 'ai', ProjectFact::STATUS_SUGGESTED);
                    }
                    if (trim($out['memory']) !== '') {
                        $memoryUpdated = $project->saveMemory($out['memory'], 'ai');
                    }
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
            'analysis' => ['summary' => $summary, 'created' => $created, 'published' => $published, 'facts' => $factsAdded, 'memory_updated' => $memoryUpdated, 'errors' => $errors],
            'analyzed_at' => now(),
            'analysis_status' => $failed ? 'failed' : 'done',
        ])->save();

        return ['created' => $created, 'published' => $published, 'errors' => $errors];
    }
}
