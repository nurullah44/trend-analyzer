<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Subject, one measurement Source, one query, one ISO week (keyed by its
 * Monday): the series everything else is computed from. A null Volume means the Source was
 * asked and had nothing it could measure, so it is not asked again.
 */
class SubjectWeek extends Model
{
    protected $guarded = [];

    protected $casts = [
        'volume' => 'integer',
    ];

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
