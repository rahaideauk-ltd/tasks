<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'name', 'site_url', 'description', 'industry', 'goals', 'contact_email', 'locale', 'brief',
        'google_tokens', 'gsc_site_url', 'ga4_property', 'clarity_token',
        'data', 'analysis', 'data_fetched_at', 'analyzed_at', 'analysis_status',
    ];

    // brief and memory are internal notes about the client: never serialise them.
    protected $hidden = ['google_tokens', 'clarity_token', 'brief', 'memory'];

    protected function casts(): array
    {
        return [
            'google_tokens' => 'encrypted:array',
            'clarity_token' => 'encrypted',
            'data' => 'array',
            'analysis' => 'array',
            'data_fetched_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'memory_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->token ??= Str::random(40);
        });
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(ProjectFact::class)->orderBy('kind')->orderBy('id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function memoryRevisions(): HasMany
    {
        return $this->hasMany(MemoryRevision::class)->latest('id');
    }

    /** Replace the memory and keep the new version as a revision. No-op when nothing changed. */
    public function saveMemory(?string $body, string $source): bool
    {
        $body = trim((string) $body);
        if ($body === trim((string) $this->memory)) {
            return false;
        }
        $this->forceFill(['memory' => $body, 'memory_updated_at' => now()])->save();
        $this->memoryRevisions()->create(['body' => $body, 'source' => $source]);

        return true;
    }

    /**
     * Add a fact unless it already exists (case-insensitive). An existing rejected fact
     * stays rejected, so Claude cannot bring it back. Returns the new fact or null.
     */
    public function addFact(string $kind, string $value, ?string $note = null, string $source = 'manual', string $status = ProjectFact::STATUS_CONFIRMED): ?ProjectFact
    {
        $kind = ProjectFact::normalizeKind($kind);
        $value = mb_substr(trim($value), 0, 255);
        if ($value === '') {
            return null;
        }
        $exists = $this->facts()->where('kind', $kind)->get()
            ->contains(fn (ProjectFact $f) => mb_strtolower($f->value) === mb_strtolower($value));
        if ($exists) {
            return null;
        }

        return $this->facts()->create(['kind' => $kind, 'value' => $value, 'note' => $note ?: null, 'source' => $source, 'status' => $status]);
    }

    public function portalUrl(): string
    {
        return route('portal.show', $this->token);
    }

    public function hasGoogle(): bool
    {
        return ! empty($this->google_tokens);
    }

    public function hasClarity(): bool
    {
        return ! empty($this->clarity_token);
    }

    /**
     * True while an analysis is in progress. A run with no progress for 15 minutes
     * (e.g. the PHP process was killed) counts as stuck, so the UI unlocks again.
     */
    public function isAnalysing(): bool
    {
        return $this->analysis_status === 'running' && $this->updated_at?->gt(now()->subMinutes(15));
    }

    /** Highest published round (drafts live in round 0). */
    public function currentRound(): int
    {
        return (int) $this->tasks()->where('status', '!=', Task::STATUS_DRAFT)->max('round');
    }

    /** Find or create a category by its English name. */
    public function categoryFor(string $nameEn, ?string $nameFa = null): Category
    {
        $nameEn = trim($nameEn) ?: 'General';

        return $this->categories()->firstOrCreate(
            ['name_en' => $nameEn],
            ['name_fa' => trim((string) $nameFa) ?: $nameEn, 'sort' => $this->categories()->count()],
        );
    }

    /** Publish every draft task as the next round. Returns number published. */
    public function publishDrafts(): int
    {
        $drafts = $this->tasks()->where('status', Task::STATUS_DRAFT);
        if (! $drafts->exists()) {
            return 0;
        }

        return $drafts->update(['status' => Task::STATUS_TODO, 'round' => $this->currentRound() + 1]);
    }
}
