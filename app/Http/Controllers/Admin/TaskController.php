<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    protected function rules(): array
    {
        return [
            'title_fa' => ['nullable', 'string', 'max:255'], 'title_en' => ['nullable', 'string', 'max:255'],
            'description_fa' => ['nullable', 'string', 'max:5000'], 'description_en' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'skill_id' => ['nullable', 'integer', Rule::exists('skills', 'id')],
        ];
    }

    protected function fill(Project $project, array $d): array
    {
        $out = [];
        $fa = trim((string) ($d['title_fa'] ?? ''));
        $en = trim((string) ($d['title_en'] ?? ''));
        if ($fa !== '' || $en !== '') {
            $out['title_fa'] = $fa !== '' ? $fa : $en;
            $out['title_en'] = $en !== '' ? $en : $fa;
        }
        foreach (['description_fa', 'description_en', 'priority'] as $k) {
            if (isset($d[$k])) {
                $out[$k] = $d[$k];
            }
        }
        if (array_key_exists('skill_id', $d)) {
            $out['skill_id'] = $d['skill_id'] ?: null;
        }
        if (! empty($d['category'])) {
            $out['category_id'] = $project->categoryFor($d['category'], $d['category'])->id;
        }

        return $out;
    }

    /** Manual task. `draft` keeps it with the drafts; otherwise it joins the current round. */
    public function store(Request $request, Project $project)
    {
        $d = $request->validate($this->rules() + ['draft' => ['nullable', 'boolean']]);
        if (empty($d['title_fa']) && empty($d['title_en'])) {
            return back()->withErrors(['title_fa' => __('A title is required.')]);
        }
        $draft = $request->boolean('draft');
        $project->tasks()->create($this->fill($project, $d) + [
            'status' => $draft ? Task::STATUS_DRAFT : Task::STATUS_TODO,
            'round' => $draft ? 0 : max(1, $project->currentRound()),
            'source' => 'manual', 'priority' => $d['priority'] ?? 'medium',
        ]);

        return back()->with('ok', __('Task added.'));
    }

    public function update(Request $request, Task $task)
    {
        $d = $request->validate($this->rules());
        $task->update($this->fill($task->project, $d));

        return back()->with('ok', __('Task updated.'));
    }

    /** approve | reject (feedback -> client redoes) | reopen (not_done -> todo) | drop | publish (draft -> todo) */
    public function review(Request $request, Task $task)
    {
        $d = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'reopen', 'drop', 'publish'])],
            'feedback' => ['nullable', 'string', 'max:3000', 'required_if:decision,reject'],
        ]);
        $allowedFrom = [
            'approve' => [Task::STATUS_SUBMITTED],
            'reject' => [Task::STATUS_SUBMITTED],
            'reopen' => [Task::STATUS_NOT_DONE, Task::STATUS_REJECTED, Task::STATUS_APPROVED, Task::STATUS_DROPPED],
            'drop' => [Task::STATUS_TODO, Task::STATUS_SUBMITTED, Task::STATUS_NOT_DONE, Task::STATUS_REJECTED],
            'publish' => [Task::STATUS_DRAFT],
        ];
        if (! in_array($task->status, $allowedFrom[$d['decision']], true)) {
            return back()->withErrors(['decision' => __('This action is not allowed for the task\'s current status.')]);
        }
        $patch = match ($d['decision']) {
            'approve' => ['status' => Task::STATUS_APPROVED],
            'reject' => ['status' => Task::STATUS_REJECTED],
            'reopen' => ['status' => Task::STATUS_TODO],
            'drop' => ['status' => Task::STATUS_DROPPED],
            'publish' => ['status' => Task::STATUS_TODO, 'round' => max(1, $task->project->currentRound())],
        };
        $task->update($patch + ['admin_feedback' => $d['feedback'] ?? $task->admin_feedback, 'reviewed_at' => now()]);

        return back()->with('ok', __('Saved.'));
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('ok', __('Task deleted.'));
    }
}
