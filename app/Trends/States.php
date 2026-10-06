<?php

namespace App\Trends;

use App\Enums\SubjectState;

/**
 * Where a scored Subject goes next, as a pure function: the state it is in, its
 * Score, and how many days it has been in Watching. Null means it stays.
 */
final class States
{
    /** @return array{0: SubjectState, 1: string}|null the next state and the reason */
    public function next(SubjectState $state, Score $score, int $daysWatching): ?array
    {
        $config = config('trend.scoring');
        // Sustained growth beyond last year's is an Alarm on its own (ADR-0012): it held for weeks, so it is no spike.
        $sustained = $score->sustained !== [];
        $corroborated = $score->corroboration() >= $config['trending_corroboration'] && $score->trendScore >= $config['trending_score'];
        $trending = $sustained || $corroborated;
        $rising = $sustained || ($score->corroboration() >= 1 && $score->trendScore >= $config['rising_score']);
        $why = $corroborated ? 'corroborated rise' : 'sustained growth';

        return match ($state) {
            SubjectState::Watching => match (true) {
                $trending => [SubjectState::Trending, $why],
                $rising => [SubjectState::Rising, 'accelerating'],
                $daysWatching >= $config['archive_after_days'] => [SubjectState::Archived, "quiet for {$daysWatching} days"],
                default => null,
            },
            SubjectState::Rising, SubjectState::Detrending => match (true) {
                $trending => [SubjectState::Trending, $why],
                $state === SubjectState::Detrending && $rising => [SubjectState::Rising, 'accelerating again'],
                $state === SubjectState::Rising && ! $rising => [SubjectState::Detrending, 'the rise failed'],
                default => null,
            },
            SubjectState::Trending => $rising ? null : [SubjectState::Detrending, 'falling after trending'],
            default => null,
        };
    }
}
