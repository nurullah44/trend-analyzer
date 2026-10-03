<?php

namespace App\Models;

use App\Enums\Magnitude;
use App\Enums\SubjectState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    /**
     * A Subject's identity: its name lower-cased and dashed, in any script, with
     * "+" and "#" kept apart so C, C++ and C# stay three Subjects.
     */
    public static function slugFor(string $name): string
    {
        return Str::slug(strtr($name, ['+' => ' plus', '#' => ' sharp']), language: null);
    }

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

    /**
     * Move to another state and record why, so every transition can be explained later.
     *
     * @param  array<string, mixed>  $numbers  the stored numbers the move was decided on
     */
    public function moveTo(SubjectState $to, string $reason, array $numbers = []): void
    {
        $from = $this->state;
        $this->update(['state' => $to]);

        $this->events()->create([
            'type' => 'state_changed',
            'from_state' => $from->value,
            'to_state' => $to->value,
            'reason' => $reason,
            'payload' => $numbers === [] ? null : $numbers,
            'happened_at' => now(),
        ]);
    }
}
