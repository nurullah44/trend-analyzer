<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Subject, one Source, one day: the raw series everything else is computed from. */
class SubjectDay extends Model
{
    protected $guarded = [];

    protected $casts = [
        'day' => 'date',
        'volume' => 'integer',
        'strength' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
