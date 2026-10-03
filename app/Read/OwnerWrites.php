<?php

namespace App\Read;

use App\Enums\Magnitude;
use App\Enums\Verdict;
use App\Models\Alarm;
use App\Models\Event;
use App\Models\Label;
use App\Models\Subject;
use App\Subjects\Intake;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only three writes the doors allow (ADR-0007), made on the owner's word:
 * a Verdict on an Alarm, a Label on a Subject, and a Seed. Nothing else in the
 * analyzer is writable from outside.
 */
final class OwnerWrites
{
    public function __construct(private readonly Intake $intake) {}

    /** Record the owner's Verdict on an Alarm, and optionally the Subject's Magnitude. */
    public function verdict(int $alarmId, string $verdict, ?string $note = null, ?string $magnitude = null): Alarm
    {
        $alarm = Alarm::find($alarmId) ?? throw new InvalidArgumentException("No Alarm has id {$alarmId}.");
        $ruling = Verdict::tryFrom($verdict) ?? throw new InvalidArgumentException('A Verdict is one of: '.implode(', ', array_column(Verdict::cases(), 'value')).'.');
        $size = $magnitude === null ? null : (Magnitude::tryFrom($magnitude) ?? throw new InvalidArgumentException('A Magnitude is one of: '.implode(', ', array_column(Magnitude::cases(), 'value')).'.'));

        DB::transaction(function () use ($alarm, $ruling, $note, $size) {
            $alarm->update(['verdict' => $ruling, 'verdict_note' => $note]);

            if ($size !== null) {
                $alarm->subject->update(['magnitude' => $size]);
            }

            Event::create(['subject_id' => $alarm->subject_id, 'alarm_id' => $alarm->id, 'type' => 'verdict', 'reason' => $note, 'payload' => ['verdict' => $ruling->value, 'magnitude' => $size?->value], 'happened_at' => now()]);
        });

        return $alarm->fresh();
    }

    /** Attach a Label to a Subject, or take it off. */
    public function label(string $slug, string $label, bool $remove = false): Subject
    {
        $subject = Subject::where('slug', $slug)->first() ?? throw new InvalidArgumentException("No Subject has slug [{$slug}].");
        $label = trim($label) !== '' ? trim($label) : throw new InvalidArgumentException('A Label needs a name.');

        if ($remove) {
            $subject->labels()->detach(Label::where('name', $label)->pluck('id')->all() ?: [0]);
        } else {
            $subject->labels()->syncWithoutDetaching([Label::firstOrCreate(['name' => $label])->id]);
        }

        return $subject->load('labels');
    }

    /**
     * Add a Seed — or promote a Backlog or Archived Subject — with optional Labels.
     *
     * @param  list<string>  $labels
     */
    public function seed(string $name, ?string $query = null, array $labels = []): Subject
    {
        if (in_array('', array_map('trim', $labels), true)) {
            throw new InvalidArgumentException('A Label needs a name.');
        }

        return DB::transaction(function () use ($name, $query, $labels) {
            $subject = $this->intake->seed($name, $query);

            foreach ($labels as $label) {
                $this->label($subject->slug, $label);
            }

            return $subject->load('labels');
        });
    }
}
