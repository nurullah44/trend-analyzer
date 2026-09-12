<?php

namespace App\Models;

use App\Enums\Magnitude;
use App\Enums\Verdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Published when a Subject reaches Trending. Carries Evidence, never a verdict about what to build. */
class Alarm extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_on' => 'date',
        'closed_on' => 'date',
        'volume' => 'integer',
        'velocity' => 'float',
        'corroboration' => 'integer',
        'score' => 'float',
        'evidence' => 'array',
        'verdict' => Verdict::class,
        'magnitude' => Magnitude::class,
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
