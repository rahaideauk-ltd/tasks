<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Project;

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
- Write everything in both Persian and English. Persian must be natural, not a word-for-word translation.
TXT;

    protected function schema(): array
    {
        $str = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary_fa', 'summary_en', 'tasks'],
            'properties' => [
                'summary_fa' => $str + ['description' => 'Assessment of the business/site for the admin, Persian, 2-4 sentences.'],
                'summary_en' => $str + ['description' => 'Same assessment in English.'],
                'tasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['category_fa', 'category_en', 'title_fa', 'title_en', 'description_fa', 'description_en', 'priority', 'reason'],
                        'properties' => [
                            'category_fa' => $str + ['description' => 'Category in Persian, e.g. سئو, بهبود فروش, تجربه کاربری, محتوا, فنی, شبکه‌های اجتماعی. Choose whatever fits.'],
                            'category_en' => $str + ['description' => 'Same category in English. Reuse an existing category name when one fits.'],
                            'title_fa' => $str,
                            'title_en' => $str,
                            'description_fa' => $str + ['description' => '2-4 sentences with concrete steps, Persian.'],
                            'description_en' => $str + ['description' => 'Same in English.'],
                            'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                            'reason' => $str + ['description' => 'Which observation or data point motivated this task. English, one sentence. Shown only to the admin.'],
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
            'clarity' => isset($data['clarity']) ? ['numOfDays' => $data['clarity']['numOfDays'], 'totals' => $data['clarity']['totals'], 'rageClicks' => $take($data['clarity']['rageClicks'], 10), 'deadClicks' => $take($data['clarity']['deadClicks'], 10), 'quickBacks' => $take($data['clarity']['quickBacks'], 10), 'jsErrors' => $take($data['clarity']['jsErrors'], 10)] : null,
        ];
    }

    /** @return array{summary: array{fa:string,en:string}, tasks: array<int, array>} */
    public function generate(Project $project): array
    {
        $history = $project->tasks()->with('category')->get()->map(fn ($t) => array_filter([
            'category' => $t->category?->name_en, 'title' => $t->title_en, 'status' => $t->status,
            'clientNote' => $t->client_note, 'adminFeedback' => $t->admin_feedback,
        ]))->values()->all();

        $profile = $project->only(['name', 'site_url', 'industry', 'description', 'goals']);
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $user = "BUSINESS PROFILE:\n".json_encode($profile, $flags)
            ."\n\nDATA (null = not connected / not fetched):\n".json_encode($this->compact($project->data), $flags)
            ."\n\nEXISTING TASKS:\n".json_encode($history, $flags);

        $client = new Client(apiKey: config('services.anthropic.key'));
        $message = $client->messages->create(
            model: config('services.anthropic.model', 'claude-opus-5-5'),
            maxTokens: 16000,
            system: self::SYSTEM,
            messages: [['role' => 'user', 'content' => $user]],
            outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
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
            'tasks' => array_map(fn ($t) => [
                'source' => 'ai', 'priority' => $t['priority'], 'reason' => $t['reason'],
                'category' => ['fa' => $t['category_fa'], 'en' => $t['category_en']],
                'title' => ['fa' => $t['title_fa'], 'en' => $t['title_en']],
                'description' => ['fa' => $t['description_fa'], 'en' => $t['description_en']],
            ], $parsed['tasks'] ?? []),
        ];
    }
}
