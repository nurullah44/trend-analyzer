<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A freely assigned tag. Labels decide which Alarms reach the owner; they never restrict collection. */
class Label extends Model
{
    protected $guarded = [];

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class);
    }
}
