<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing a Source published: what the Evidence is built from. */
class Item extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'observed_on' => 'date',
        'signal' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
