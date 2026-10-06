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

    /**
     * App Store popularity for the 57 weeks a Sustained growth score reads, every
     * week below Apple's list unless given: weeks back from the scored one => popularity.
     *
     * @param  array<int, int>  $ranked
     * @return array<string, ?int>
     */
    private static function apple(array $ranked): array
    {
        $weeks = [];

        foreach (range(56, 0) as $back) {
            $weeks[date('Y-m-d', strtotime(self::WEEK." -{$back} weeks"))] = $ranked[$back] ?? null;
        }

        return $weeks;
    }

    /** @return array<string, array{0: array<int, int>, 1: ?string}> */
    public static function appStore(): array
    {
        return [
            'new demand that held for weeks grows' => [[0 => 56, 1 => 54, 2 => 51, 3 => 49], 'sustained'],
            'one week below the list still holds' => [[0 => 56, 1 => 54, 3 => 50], 'sustained'],
            'a one-week jump is no growth' => [[0 => 66], null],
            'two weeks are not yet held' => [[0 => 63, 1 => 61], null],
            'flat demand is not growth' => [array_fill(0, 12, 60), null],
            'a term that rose the same weeks a year ago is Seasonal' => [[0 => 56, 1 => 54, 2 => 51, 3 => 49, 50 => 55, 51 => 64, 54 => 50], 'seasonal'],
            'a level below the ranked weeks before is no growth, however many weeks were below the list' => [[0 => 57, 1 => 57, 2 => 57, 3 => 57, 8 => 60, 9 => 60, 10 => 60, 11 => 60], null],
            'beating last year by enough is growth again' => [[0 => 63, 1 => 61, 2 => 62, 3 => 60, 51 => 55, 52 => 53, 53 => 48], 'sustained'],
        ];
    }

    /** @param array<int, int> $ranked */
    #[DataProvider('appStore')]
    public function test_app_store_search_counts_only_sustained_growth(array $ranked, ?string $growth): void
    {
        $score = (new Scorer)->score(['apple_ads' => self::apple($ranked)], self::WEEK);

        $this->assertSame($growth === null ? [] : ['apple_ads'], $score->rising);
        $this->assertSame($growth === 'sustained' ? ['apple_ads'] : [], $score->sustained);
        $this->assertSame($growth === 'seasonal' ? ['apple_ads'] : [], $score->seasonal);
    }

    public function test_sustained_growth_beyond_last_years_alarms_on_its_own_and_seasonal_growth_does_not(): void
    {
        $growing = (new Scorer)->score(['apple_ads' => self::apple([0 => 56, 1 => 54, 2 => 51, 3 => 49])], self::WEEK);
        $seasonal = (new Scorer)->score(['apple_ads' => self::apple([0 => 56, 1 => 54, 2 => 51, 3 => 49, 52 => 60])], self::WEEK);

        $this->assertSame([SubjectState::Trending, 'sustained growth'], (new States)->next(SubjectState::Watching, $growing, 7));
        $this->assertNotSame(SubjectState::Trending, (new States)->next(SubjectState::Watching, $seasonal, 7)[0] ?? null);
        $this->assertNull((new States)->next(SubjectState::Trending, $growing, 7), 'it stays Trending while the growth holds');
    }

    public function test_weeks_apple_no_longer_keeps_are_unknown_not_below_the_list(): void
    {
        $weeks = array_slice(self::apple(array_fill(0, 4, 60)), -11, null, true);

        $this->assertSame([], (new Scorer)->score(['apple_ads' => $weeks], self::WEEK)->rising, 'seven of the eight weeks before are too few to call it growth');
    }

    public function test_a_year_ago_window_never_measured_is_not_called_new(): void
    {
        $weeks = array_slice(self::apple([0 => 56, 1 => 54, 2 => 51, 3 => 49]), -12, null, true);

        $this->assertSame(['apple_ads'], (new Scorer)->score(['apple_ads' => $weeks], self::WEEK)->seasonal);
    }
}
