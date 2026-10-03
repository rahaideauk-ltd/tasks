<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Skills: playbooks Claude follows when it suggests tasks. Global, or limited to one project. */
class SkillController extends Controller
{
    public function index()
    {
        return view('admin.skills.index', [
            'skills' => Skill::with('project')->withCount('tasks')->orderByRaw('project_id is not null')->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    protected function validated(Request $request, ?Skill $skill = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['required', 'string', 'max:20000'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'active' => ['nullable', 'boolean'],
        ]);
        $d['slug'] = Str::slug(($d['slug'] ?? null) ?: $d['name']) ?: 'skill-'.Str::lower(Str::random(6));
        $d['project_id'] = $d['project_id'] ?? null;
        $d['active'] = $request->boolean('active', true);

        // slugs are unique within the same scope (global, or one project)
        $taken = Skill::where('slug', $d['slug'])->where('project_id', $d['project_id'])
            ->when($skill, fn ($q) => $q->whereKeyNot($skill->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['slug' => __('This slug is already used.')]);
        }

        return $d;
    }

    public function store(Request $request)
    {
        Skill::create($this->validated($request));

        return back()->with('ok', __('Skill saved.'));
    }

    public function update(Request $request, Skill $skill)
    {
        $skill->update($this->validated($request, $skill));

        return back()->with('ok', __('Skill saved.'));
    }

    public function destroy(Skill $skill)
    {
        $skill->delete();

        return back()->with('ok', __('Skill deleted.'));
    }
}
