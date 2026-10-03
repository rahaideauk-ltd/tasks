<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A playbook Claude follows when it suggests a certain kind of task. Global when project_id is null. */
class Skill extends Model
{
    protected $fillable = ['project_id', 'slug', 'name', 'description', 'instructions', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Active global skills plus the project's own. A project skill overrides a global one with the same slug. */
    public function scopeAvailableFor(Builder $q, Project $project): Builder
    {
        return $q->where('active', true)
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $project->id))
            ->orderByRaw('project_id is null')
            ->orderBy('name');
    }
}
