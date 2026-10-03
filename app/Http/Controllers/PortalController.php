<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\GoogleService;
use Illuminate\Http\Request;

/** Client portal: reached only through the secret token link. */
class PortalController extends Controller
{
    public function show(Project $project)
    {
        $tasks = $project->tasks()->visibleToClient()->with('category')
            ->orderByDesc('round')->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")->get();

        return view('portal.show', [
            'project' => $project,
            'tasks' => $tasks,
            'rounds' => $tasks->groupBy('round')->sortKeysDesc(),
            'googleConfigured' => GoogleService::configured(),
        ]);
    }

    /** Client answers a task: done (optional note/link) or not done (reason required). */
    public function respond(Request $request, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id && $task->status !== Task::STATUS_DRAFT, 404);
        abort_unless($task->isOpenForClient(), 400, 'Task is not open');

        $data = $request->validate([
            'done' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:3000', 'required_if:done,0'],
        ]);

        $task->update([
            'status' => $data['done'] ? Task::STATUS_SUBMITTED : Task::STATUS_NOT_DONE,
            'client_note' => $data['note'] ?? '',
            'responded_at' => now(),
        ]);

        return back()->with('ok', __('Thanks, your answer was recorded.'));
    }

    public function clarity(Request $request, Project $project)
    {
        $data = $request->validate(['api_token' => ['nullable', 'string', 'max:2000']]);
        $project->forceFill(['clarity_token' => $data['api_token'] ?: null])->save();

        return back()->with('ok', __('Clarity settings saved.'));
    }

    /** After Google OAuth: choose which Search Console site and GA4 property belong to this project. */
    public function googleOptions(Project $project, GoogleService $google)
    {
        abort_unless($project->hasGoogle(), 400);
        $out = ['sites' => [], 'properties' => [], 'googleErrors' => []];
        try {
            $out['sites'] = $google->listSites($project);
        } catch (\Throwable $e) {
            $out['googleErrors']['sites'] = $e->getMessage();
        }
        try {
            $out['properties'] = $google->listGa4Properties($project);
        } catch (\Throwable $e) {
            $out['googleErrors']['properties'] = $e->getMessage();
        }

        return view('portal.google', ['project' => $project] + $out);
    }

    public function googleSelect(Request $request, Project $project)
    {
        abort_unless($project->hasGoogle(), 400);
        $data = $request->validate(['gsc_site_url' => ['nullable', 'string', 'max:300'], 'ga4_property' => ['nullable', 'string', 'max:100']]);
        $project->forceFill(['gsc_site_url' => $data['gsc_site_url'] ?: null, 'ga4_property' => $data['ga4_property'] ?: null])->save();

        return redirect()->route('portal.show', $project->token)->with('ok', __('Google settings saved.'));
    }

    public function googleDisconnect(Project $project)
    {
        $project->forceFill(['google_tokens' => null, 'gsc_site_url' => null, 'ga4_property' => null])->save();

        return back()->with('ok', __('Google disconnected.'));
    }
}
