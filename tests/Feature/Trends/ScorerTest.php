<?php

namespace Tests\Feature\Trends;

use App\Enums\SubjectState;
use App\Trends\Scorer;
use App\Trends\States;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScorerTest extends TestCase
{
    private const WEEK = '2026-09-21';

    /** Eight baseline weeks then the scored week, oldest first. @return array<string, ?int> */
    private static function weeks(int ...$volumes): array
    {
        $weeks = [];

        foreach (array_reverse($volumes) as $back => $volume) {
            $weeks[date('Y-m-d', strtotime(self::WEEK." -{$back} weeks"))] = $volume;
        }

        return array_reverse($weeks, true);
    }

    /** @return array<string, array{0: array<string, array<string, ?int>>, 1: SubjectState, 2: ?SubjectState}> */
    public static function cases(): array
    {
        $flat = self::weeks(5, 6, 5, 4, 6, 5, 5, 6, 5);
        $rise = self::weeks(5, 6, 5, 4, 6, 5, 5, 6, 30);

        return [
            'a cold start has no Velocity and stays' => [['hacker_news' => self::weeks(1, 40)], SubjectState::Watching, null],
            'a flat series stays' => [['hacker_news' => $flat, 'stack_exchange' => $flat], SubjectState::Watching, null],
            'a single loud Source only rises' => [['hacker_news' => $rise, 'stack_exchange' => $flat], SubjectState::Watching, SubjectState::Rising],
            'a corroborated rise trends' => [['hacker_news' => $rise, 'stack_exchange' => $rise], SubjectState::Watching, SubjectState::Trending],
            'a rise from one or two mentions does not count' => [['hacker_news' => self::weeks(0, 0, 0, 0, 0, 0, 0, 0, 2), 'stack_exchange' => self::weeks(0, 0, 0, 0, 0, 0, 0, 0, 2)], SubjectState::Watching, null],
            'a rising Subject that fades fails' => [['hacker_news' => $flat], SubjectState::Rising, SubjectState::Detrending],
            'a trending Subject that fades detrends' => [['hacker_news' => $flat, 'stack_exchange' => $flat], SubjectState::Trending, SubjectState::Detrending],
            'a trending Subject still rising stays' => [['hacker_news' => $rise, 'stack_exchange' => $flat], SubjectState::Trending, null],
            'a detrending Subject can rise again' => [['hacker_news' => $rise], SubjectState::Detrending, SubjectState::Rising],
        ];
    }

    /** @param array<string, array<string, ?int>> $series */
    #[DataProvider('cases')]
    public function test_the_series_moves_the_subject(array $series, SubjectState $from, ?SubjectState $to): void
    {
        $score = (new Scorer)->score($series, self::WEEK);

        $this->assertSame($to, (new States)->next($from, $score, 7)[0] ?? null);
    }

    public function test_the_score_is_explainable_from_the_series(): void
    {
        $score = (new Scorer)->score(['hacker_news' => self::weeks(4, 6, 4, 6, 4, 6, 4, 6, 14)], self::WEEK);

        // Baseline median 5, MAD 1 → spread max(1.48, √5 = 2.24, 1) = 2.24; (14 − 5) / 2.24 = 4.02.
        $this->assertSame(['hacker_news' => 14], $score->volumes);
        $this->assertEqualsWithDelta(4.02, $score->velocities['hacker_news'], 0.01);
        $this->assertSame(['hacker_news'], $score->rising);
        $this->assertEqualsWithDelta(4.02, $score->trendScore, 0.01);
    }

    public function test_missing_recent_weeks_are_a_cold_start_not_an_older_baseline(): void
    {
        $series = ['hacker_news' => ['2026-05-04' => 1, '2026-05-11' => 1, '2026-05-18' => 1, '2026-05-25' => 1, '2026-06-01' => 1, self::WEEK => 40]];

        $this->assertSame([], (new Scorer)->score($series, self::WEEK)->velocities);
    }

    public function test_one_source_adds_at_most_the_cap(): void
    {
        $score = (new Scorer)->score(['hacker_news' => self::weeks(5, 5, 5, 5, 5, 5, 5, 5, 500)], self::WEEK);

        $this->assertSame(5.0, $score->trendScore);
    }

    public function test_a_quiet_watching_subject_is_archived_after_thirty_days(): void
    {
        $score = (new Scorer)->score([], self::WEEK);

        $this->assertSame(SubjectState::Archived, (new States)->next(SubjectState::Watching, $score, 30)[0]);
        $this->assertNull((new States)->next(SubjectState::Watching, $score, 29));
    }
}
