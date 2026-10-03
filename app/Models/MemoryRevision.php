<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryRevision extends Model
{
    protected $fillable = ['project_id', 'body', 'source'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
