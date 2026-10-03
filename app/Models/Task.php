<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    public const STATUS_DRAFT = 'draft';         // generated, waiting for admin to publish

    public const STATUS_TODO = 'todo';           // open for the client

    public const STATUS_SUBMITTED = 'submitted'; // client: "done"

    public const STATUS_NOT_DONE = 'not_done';   // client: "could not / did not do it" + reason

    public const STATUS_APPROVED = 'approved';   // admin accepted

    public const STATUS_REJECTED = 'rejected';   // admin sent back with feedback

    public const STATUS_DROPPED = 'dropped';     // admin dropped the task

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_TODO, self::STATUS_SUBMITTED, self::STATUS_NOT_DONE,
        self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_DROPPED,
    ];

    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = [
        'project_id', 'category_id', 'skill_id', 'round', 'title_fa', 'title_en', 'description_fa', 'description_en',
        'priority', 'source', 'reason', 'evidence', 'status', 'client_note', 'admin_feedback', 'responded_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'responded_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function scopeVisibleToClient(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_DRAFT);
    }

    public function scopeNeedsReview(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_NOT_DONE]);
    }

    public function title(?string $locale = null): string
    {
        $l = $locale ?? app()->getLocale();

        return ($l === 'fa' ? $this->title_fa : $this->title_en) ?: ($this->title_en ?: $this->title_fa);
    }

    public function description(?string $locale = null): string
    {
        $l = $locale ?? app()->getLocale();

        return (string) (($l === 'fa' ? $this->description_fa : $this->description_en) ?: ($this->description_en ?: $this->description_fa));
    }

    public function isOpenForClient(): bool
    {
        return in_array($this->status, [self::STATUS_TODO, self::STATUS_REJECTED], true);
    }
}
