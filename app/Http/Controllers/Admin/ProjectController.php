<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeProject;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Task;
use App\Services\AiTaskGenerator;
use App\Services\GoogleService;
use App\Services\ProjectAnalyzer;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::withCount([
            'tasks', 'tasks as draft_count' => fn ($q) => $q->where('status', Task::STATUS_DRAFT),
            'tasks as review_count' => fn ($q) => $q->needsReview(),
            'tasks as todo_count' => fn ($q) => $q->where('status', Task::STATUS_TODO),
            'tasks as approved_count' => fn ($q) => $q->where('status', Task::STATUS_APPROVED),
        ])->latest()->get();

        return view('admin.projects.index', ['projects' => $projects, 'aiConfigured' => AiTaskGenerator::configured(), 'googleConfigured' => GoogleService::configured()]);
    }

    public function show(Project $project)
    {
        $tasks = $project->tasks()->with(['category', 'skill'])->orderBy('round')->orderBy('created_at')->get();

        return view('admin.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'drafts' => $tasks->where('status', Task::STATUS_DRAFT),
            'review' => $tasks->whereIn('status', [Task::STATUS_SUBMITTED, Task::STATUS_NOT_DONE]),
            'rounds' => $tasks->where('status', '!=', Task::STATUS_DRAFT)->groupBy('round')->sortKeysDesc(),
            'categories' => $project->categories()->get(),
            'facts' => $project->facts()->get(),
            'factKinds' => config('tasks.fact_kinds', []),
            'revisions' => $project->memoryRevisions()->limit(10)->get(),
            'skills' => Skill::query()->availableFor($project)->get(),
            'aiConfigured' => AiTaskGenerator::configured(),
            'autoPublish' => config('tasks.auto_publish'),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'site_url' => ['nullable', 'string', 'max:300'],
            'industry' => ['nullable', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'], 'contact_email' => ['nullable', 'email', 'max:200'],
        ]);
        $project->update($data);

        return back()->with('ok', __('Project updated.'));
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('admin.projects.index')->with('ok', __('Project deleted.'));
    }

    /** Pull fresh data only. */
    public function sync(Project $project, ProjectAnalyzer $analyzer)
    {
        $analyzer->sync($project);

        return back()->with('ok', __('Data refreshed.'));
    }

    /** Sync + rules + AI, in the background. The page shows status while it runs. */
    public function analyze(Request $request, Project $project)
    {
        if ($project->isAnalysing()) {
            return back()->with('error', __('An analysis is already running.'));
        }
        AnalyzeProject::start($project, sync: true, useRules: $request->boolean('rules', true), useAi: $request->boolean('ai', true));

        return back()->with('ok', __('Analysis started. Refresh the page in a minute.'));
    }

    /** Publish all drafts as the next round. */
    public function publish(Project $project)
    {
        $n = $project->publishDrafts();
        if ($n === 0) {
            return back()->with('error', __('There are no drafts to publish.'));
        }

        return back()->with('ok', __(':n tasks published as round :r.', ['n' => $n, 'r' => $project->currentRound()]));
    }
}
