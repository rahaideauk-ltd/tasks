<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemoryRevision;
use App\Models\Project;
use App\Models\ProjectFact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Internal project knowledge: facts (dynamic fields), the admin brief and Claude's memory. Never shown to the client. */
class KnowledgeController extends Controller
{
    public function storeFact(Request $request, Project $project)
    {
        $d = $request->validate([
            'kind' => ['required', 'string', 'max:40'], 'value' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! $project->addFact($d['kind'], $d['value'], $d['note'] ?? null)) {
            return back()->with('error', __('This fact already exists.'));
        }

        return back()->with('ok', __('Fact added.'));
    }

    public function updateFact(Request $request, ProjectFact $fact)
    {
        $d = $request->validate([
            'status' => ['nullable', Rule::in(ProjectFact::STATUSES)],
            'value' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $patch = array_filter(['status' => $d['status'] ?? null, 'value' => isset($d['value']) ? trim($d['value']) : null], fn ($v) => $v !== null && $v !== '');
        if ($request->has('note')) {
            $patch['note'] = $d['note'] ?: null;
        }
        $fact->update($patch);

        return back()->with('ok', __('Saved.'));
    }

    public function destroyFact(ProjectFact $fact)
    {
        $fact->delete();

        return back()->with('ok', __('Fact deleted.'));
    }

    public function updateBrief(Request $request, Project $project)
    {
        $d = $request->validate(['brief' => ['nullable', 'string', 'max:20000']]);
        $project->update(['brief' => $d['brief'] ?? null]);

        return back()->with('ok', __('Brief saved.'));
    }

    public function updateMemory(Request $request, Project $project)
    {
        $d = $request->validate(['memory' => ['nullable', 'string', 'max:50000']]);
        $project->saveMemory($d['memory'] ?? null, 'admin');

        return back()->with('ok', __('Memory saved.'));
    }

    public function restoreMemory(Project $project, MemoryRevision $revision)
    {
        abort_unless($revision->project_id === $project->id, 404);
        $project->saveMemory($revision->body, 'admin');

        return back()->with('ok', __('Memory restored.'));
    }
}
