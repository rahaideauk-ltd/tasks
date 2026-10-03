<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Project;
use App\Models\ProjectFact;
use App\Models\Skill;

/**
 * Claude reads the business profile, homepage, analytics snapshot and task history,
 * then decides the categories and the next round of tasks. Needs ANTHROPIC_API_KEY.
 */
class AiTaskGenerator
{
    public static function configured(): bool
    {
        return (bool) config('services.anthropic.key');
    }

    protected const SYSTEM = <<<'TXT'
You are a senior growth consultant (SEO, conversion, UX, content, local marketing) for small businesses.
You receive a business profile, the text of its homepage (when reachable), analytics data when connected
(Google Search Console, GA4, Microsoft Clarity) and the history of tasks the owner already did, declined or had rejected.

Produce the next round of 4 to 8 concrete tasks the business owner (non-technical, or with a freelance developer) can
complete within a week or two. Rules:
- Group tasks into categories that fit THIS business; you decide the categories. Reuse category names from existing tasks when they fit.
- Every task must be specific: name the page, query, metric, section or asset. No generic advice.
- Every task must be verifiable by us afterwards (we will ask for a link or screenshot).
- Do not repeat tasks that already exist in any status. Build on approved tasks; if a task was declined with a reason, respect the reason.
- When analytics are not connected, base the tasks on the homepage and business profile; connecting analytics may be one task, not more.
  A source listed under DATA.errors is already connected but failed to load this time: do not ask to connect it.
- Write everything in both Persian and English. Persian must be natural, not a word-for-word translation.

You also receive our internal knowledge about the project. The owner never sees any of it:
- INTERNAL BRIEF: written by our team. Treat it as ground truth and follow it. You never change it.
- PROJECT MEMORY: your own long-term notes from earlier rounds. Use it, then return an updated version in `memory`:
  keep what is still true, add what you learned this time (what the owner can or cannot do, what worked, their
  preferences and constraints, decisions taken, patterns in the data), remove what is outdated or repeated.
  Do not copy the task list into it. Concise Markdown with short headings. Never contradict the brief.
- FACTS: structured fields such as target keywords, competitors and distinctive features. Status "confirmed" was checked
  by our team; "suggested" was proposed earlier and not checked yet. Return in `facts` only NEW facts you found in the
  data (homepage text, queries, pages), never one that already exists or is listed under REJECTED FACTS.
- SKILLS: our playbooks. When a task falls within a skill, follow its instructions and set `skill` to its slug;
  otherwise set `skill` to an empty string.
TXT;

    /** @param  string[]  $skillSlugs */
    protected function schema(array $skillSlugs): array
    {
        $str = ['type' => 'string'];
        $kinds = implode(', ', config('tasks.fact_kinds', []));

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary_fa', 'summary_en', 'memory', 'facts', 'tasks'],
            'properties' => [
                'summary_fa' => $str + ['description' => 'Assessment of the business/site for the admin, Persian, 2-4 sentences.'],
                'summary_en' => $str + ['description' => 'Same assessment in English.'],
                'memory' => $str + ['description' => 'The full updated PROJECT MEMORY in Markdown, under '.config('tasks.memory_max_words', 800).' words. Internal, never shown to the owner.'],
                'facts' => [
                    'type' => 'array',
                    'description' => 'New facts discovered this time. Empty when there is nothing new.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['kind', 'value', 'note'],
                        'properties' => [
                            'kind' => $str + ['description' => "One of: $kinds. Use another snake_case kind only when none fits."],
                            'value' => $str + ['description' => 'Short value, e.g. the keyword, the competitor domain or name, the feature.'],
                            'note' => $str + ['description' => 'Where it was found or why it matters, one sentence, English.'],
                        ],
                    ],
                ],
                'tasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['category_fa', 'category_en', 'title_fa', 'title_en', 'description_fa', 'description_en', 'priority', 'reason', 'skill'],
                        'properties' => [
                            'category_fa' => $str + ['description' => 'Category in Persian, e.g. سئو, بهبود فروش, تجربه کاربری, محتوا, فنی, شبکه‌های اجتماعی. Choose whatever fits.'],
                            'category_en' => $str + ['description' => 'Same category in English. Reuse an existing category name when one fits.'],
                            'title_fa' => $str,
                            'title_en' => $str,
                            'description_fa' => $str + ['description' => '2-4 sentences with concrete steps, Persian.'],
                            'description_en' => $str + ['description' => 'Same in English.'],
                            'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                            'reason' => $str + ['description' => 'Which observation or data point motivated this task. English, one sentence. Shown only to the admin.'],
                            'skill' => ['type' => 'string', 'enum' => array_values(array_unique(['', ...$skillSlugs])), 'description' => 'Slug of the skill this task follows, or empty.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function compact(?array $data): ?array
    {
        if (! $data) {
            return null;
        }
        $take = fn ($arr, $n) => array_slice($arr ?? [], 0, $n);
        $s = $data['site'] ?? null;

        return [
            'site' => $s ? array_intersect_key($s, array_flip(['url', 'status', 'title', 'metaDesc', 'h1s', 'https', 'hasViewport', 'hasGa', 'hasClarity', 'error', 'text'])) : null,
            'gsc' => isset($data['gsc']) ? ['range' => $data['gsc']['range'], 'totals' => $data['gsc']['totals'], 'pages' => $take($data['gsc']['pages'], 25), 'queries' => $take($data['gsc']['queries'], 25), 'devices' => $data['gsc']['devices']] : null,
            'ga4' => isset($data['ga4']) ? ['totals' => $data['ga4']['totals'], 'pages' => $take($data['ga4']['pages'], 25), 'devices' => $data['ga4']['devices'], 'channels' => $data['ga4']['channels']] : null,
            // a source listed here is connected but failed to load; do not ask the owner to connect it again
            'errors' => $data['errors'] ?? [],
            'clarity' => isset($data['clarity']) ? ['numOfDays' => $data['clarity']['numOfDays'], 'totals' => $data['clarity']['totals'], 'rageClicks' => $take($data['clarity']['rageClicks'], 10), 'deadClicks' => $take($data['clarity']['deadClicks'], 10), 'quickBacks' => $take($data['clarity']['quickBacks'], 10), 'jsErrors' => $take($data['clarity']['jsErrors'], 10)] : null,
        ];
    }

    /**
     * @return array{summary: array{fa:string,en:string}, memory: string, facts: array<int, array{kind:string,value:string,note:string}>, tasks: array<int, array>}
     */
    public function generate(Project $project): array
    {
        $history = $project->tasks()->with(['category', 'skill'])->get()->map(fn ($t) => array_filter([
            'category' => $t->category?->name_en, 'title' => $t->title_en, 'status' => $t->status, 'skill' => $t->skill?->slug,
            'clientNote' => $t->client_note, 'adminFeedback' => $t->admin_feedback,
        ]))->values()->all();

        $facts = $project->facts()->get();
        $active = $facts->where('status', '!=', ProjectFact::STATUS_REJECTED)
            ->map(fn ($f) => array_filter(['kind' => $f->kind, 'value' => $f->value, 'note' => $f->note, 'status' => $f->status]))->values()->all();
        $rejected = $facts->where('status', ProjectFact::STATUS_REJECTED)->map(fn ($f) => "{$f->kind}: {$f->value}")->values()->all();

        // A project skill overrides a global one with the same slug.
        $skills = Skill::query()->availableFor($project)->get()->unique('slug');
        $skillList = $skills->map(fn ($s) => ['slug' => $s->slug, 'name' => $s->name, 'whenToUse' => $s->description, 'instructions' => $s->instructions])->values()->all();

        $profile = $project->only(['name', 'site_url', 'industry', 'description', 'goals']);
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $user = "BUSINESS PROFILE:\n".json_encode($profile, $flags)
            ."\n\nINTERNAL BRIEF:\n".(trim((string) $project->brief) ?: '(empty)')
            ."\n\nPROJECT MEMORY:\n".(trim((string) $project->memory) ?: '(empty: this is the first analysis, start the memory)')
            ."\n\nFACTS:\n".json_encode($active, $flags)
            ."\n\nREJECTED FACTS (do not suggest again):\n".json_encode($rejected, $flags)
            ."\n\nSKILLS:\n".json_encode($skillList, $flags)
            ."\n\nDATA (null = not connected / not fetched):\n".json_encode($this->compact($project->data), $flags)
            ."\n\nEXISTING TASKS:\n".json_encode($history, $flags);

        $client = new Client(apiKey: config('services.anthropic.key'));
        $message = $client->messages->create(
            model: config('services.anthropic.model', 'claude-opus-5-5'),
            maxTokens: 16000,
            system: self::SYSTEM,
            messages: [['role' => 'user', 'content' => $user]],
            outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $this->schema($skills->pluck('slug')->all())]],
        );

        if ($message->stopReason === 'refusal') {
            throw new \RuntimeException('Model declined the request'.($message->stopDetails?->explanation ? ': '.$message->stopDetails->explanation : ''));
        }
        if ($message->stopReason === 'max_tokens') {
            throw new \RuntimeException('Model output was cut off');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        $parsed = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        return [
            'summary' => ['fa' => $parsed['summary_fa'] ?? '', 'en' => $parsed['summary_en'] ?? ''],
            'memory' => (string) ($parsed['memory'] ?? ''),
            'facts' => $parsed['facts'] ?? [],
            'tasks' => array_map(fn ($t) => [
                'source' => 'ai', 'priority' => $t['priority'], 'reason' => $t['reason'], 'skill' => $t['skill'] ?? '',
                'category' => ['fa' => $t['category_fa'], 'en' => $t['category_en']],
                'title' => ['fa' => $t['title_fa'], 'en' => $t['title_en']],
                'description' => ['fa' => $t['description_fa'], 'en' => $t['description_en']],
            ], $parsed['tasks'] ?? []),
        ];
    }
}
