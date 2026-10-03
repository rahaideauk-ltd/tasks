<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'name', 'site_url', 'description', 'industry', 'goals', 'contact_email', 'locale',
        'google_tokens', 'gsc_site_url', 'ga4_property', 'clarity_token',
        'data', 'analysis', 'data_fetched_at', 'analyzed_at', 'analysis_status',
    ];

    protected $hidden = ['google_tokens', 'clarity_token'];

    protected function casts(): array
    {
        return [
            'google_tokens' => 'encrypted:array',
            'clarity_token' => 'encrypted',
            'data' => 'array',
            'analysis' => 'array',
            'data_fetched_at' => 'datetime',
            'analyzed_at' => 'datetime',
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
