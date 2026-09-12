<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An approved place to collect from: official API, RSS feed, dataset, or licensed provider. */
class Source extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
