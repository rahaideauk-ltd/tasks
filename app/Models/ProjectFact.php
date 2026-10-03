<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dynamic project field (keyword, competitor, feature...). Admin-only. */
class ProjectFact extends Model
{
    public const STATUS_SUGGESTED = 'suggested'; // proposed by Claude, not checked yet

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';   // kept so Claude does not suggest it again

    public const STATUSES = [self::STATUS_SUGGESTED, self::STATUS_CONFIRMED, self::STATUS_REJECTED];

    protected $fillable = ['project_id', 'kind', 'value', 'note', 'source', 'status'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_REJECTED);
    }

    /** Normalise a kind typed by the admin or returned by Claude: "Target Keywords" -> "target_keywords". */
    public static function normalizeKind(string $kind): string
    {
        $kind = trim(preg_replace('/[^\p{L}\p{N}]+/u', '_', mb_strtolower(trim($kind))), '_');

        return mb_substr($kind ?: 'note', 0, 40);
    }

    /** Translated label for a kind; unknown kinds show as typed. */
    public static function kindLabel(string $kind): string
    {
        $key = "fact.$kind";
        $label = __($key);

        return $label === $key ? str_replace('_', ' ', $kind) : $label;
    }
}
