<?php

namespace App\Models;

use App\Enums\Magnitude;
use App\Enums\SubjectState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A specific thing the analyzer can name in five words or fewer and re-find with a query. */
class Subject extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => SubjectState::class,
        'magnitude' => Magnitude::class,
        'seasonal' => 'boolean',
        'first_seen_on' => 'date',
        'mainstream_on' => 'date',
        'lead_time_days' => 'integer',
    ];

    public function days(): HasMany
    {
        return $this->hasMany(SubjectDay::class);
    }

    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }
}
