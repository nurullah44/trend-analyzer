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
        $trending = $score->corroboration() >= $config['trending_corroboration'] && $score->trendScore >= $config['trending_score'];
        $rising = $score->corroboration() >= 1 && $score->trendScore >= $config['rising_score'];

        return match ($state) {
            SubjectState::Watching => match (true) {
                $trending => [SubjectState::Trending, 'corroborated rise'],
                $rising => [SubjectState::Rising, 'accelerating'],
                $daysWatching >= $config['archive_after_days'] => [SubjectState::Archived, "quiet for {$daysWatching} days"],
                default => null,
            },
            SubjectState::Rising, SubjectState::Detrending => match (true) {
                $trending => [SubjectState::Trending, 'corroborated rise'],
                $state === SubjectState::Detrending && $rising => [SubjectState::Rising, 'accelerating again'],
                $state === SubjectState::Rising && ! $rising => [SubjectState::Detrending, 'the rise failed'],
                default => null,
            },
            SubjectState::Trending => $rising ? null : [SubjectState::Detrending, 'falling after trending'],
            default => null,
        };
    }
}
