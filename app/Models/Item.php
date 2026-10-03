<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing a Source published: what the Evidence is built from. */
class Item extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'measured_quantity' => 'integer',
    ];

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Items whose title or text mentions the given words, case-insensitively.
     *
     * @param  Builder<Item>  $query
     */
    public function scopeMentioning(Builder $query, string $words): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $words).'%';

        $query->where(fn (Builder $query) => $query
            ->whereRaw("title like ? escape '\\'", [$like])
            ->orWhereRaw("excerpt like ? escape '\\'", [$like]));
    }

    /**
     * The collection day is a date, never a moment: the contract upserts per Source per day.
     *
     * @return Attribute<CarbonImmutable|null, mixed>
     */
    protected function observedOn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : CarbonImmutable::parse($value),
            set: fn ($value) => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : CarbonImmutable::parse($value)->format('Y-m-d'),
        );
    }
}
