<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something that happened to a Subject: a state change, a Spike, a mainstream arrival, a prune. */
class Event extends Model
{
    protected $guarded = [];

    protected $casts = [
        'happened_at' => 'datetime',
        'payload' => 'array',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function alarm(): BelongsTo
    {
        return $this->belongsTo(Alarm::class);
    }
}
