<?php

namespace App\Http;

use Carbon\CarbonImmutable;

/** The geometry of one Source's weekly series as an inline SVG line: one series, one axis, from zero. */
final readonly class SeriesChart
{
    public const WIDTH = 320;

    public const HEIGHT = 120;

    private const LEFT = 8;

    private const RIGHT = 44;

    private const TOP = 12;

    private const BOTTOM = 22;

    /** @var list<array{week: string, volume: ?int, x: float, y: float}> */
    public array $points;

    public int $max;

    /** @param array<string, ?int> $weeks week => Volume; weeks never measured show as gaps */
    public function __construct(array $weeks)
    {
        $weeks = self::everyWeek($weeks);
        $this->max = self::niceCeiling(max([1, ...array_filter($weeks, 'is_int')]));
        $step = (self::WIDTH - self::LEFT - self::RIGHT) / max(1, count($weeks) - 1);
        $points = [];

        foreach (array_keys($weeks) as $index => $week) {
            $volume = $weeks[$week];
            $points[] = [
                'week' => $week,
                'volume' => $volume,
                'x' => round(self::LEFT + $index * $step, 1),
                'y' => round($this->y($volume ?? 0), 1),
            ];
        }

        $this->points = $points;
    }

    /** The line through the measured weeks; an unmeasurable week breaks it. */
    public function path(): string
    {
        $path = '';
        $pen = 'M';

        foreach ($this->points as $point) {
            if ($point['volume'] === null) {
                $pen = 'M';

                continue;
            }

            $path .= "{$pen}{$point['x']} {$point['y']} ";
            $pen = 'L';
        }

        return trim($path);
    }

    /** The wash under each unbroken run of measured weeks, closed on the baseline. */
    public function area(): string
    {
        $runs = [[]];

        foreach ($this->points as $point) {
            if ($point['volume'] === null) {
                $runs[] = [];
            } else {
                $runs[array_key_last($runs)][] = $point;
            }
        }

        return implode(' ', array_map(fn (array $run) => 'M'.$run[0]['x'].' '.$this->baseline()
            .' L'.implode(' L', array_map(fn (array $point) => "{$point['x']} {$point['y']}", $run))
            .' L'.end($run)['x'].' '.$this->baseline().' Z', array_filter($runs, fn (array $run) => count($run) > 1)));
    }

    /** @return array{week: string, volume: ?int, x: float, y: float}|null */
    public function last(): ?array
    {
        $measured = array_filter($this->points, fn (array $point) => $point['volume'] !== null);

        return $measured === [] ? null : end($measured);
    }

    public function baseline(): float
    {
        return self::HEIGHT - self::BOTTOM;
    }

    public function top(): float
    {
        return self::TOP;
    }

    private function y(int $volume): float
    {
        return $this->baseline() - ($volume / $this->max) * ($this->baseline() - self::TOP);
    }

    /**
     * Every Monday from the first week to the last, so elapsed time sets the spacing
     * and a week that was never measured shows as a gap.
     *
     * @param  array<string, ?int>  $weeks
     * @return array<string, ?int>
     */
    private static function everyWeek(array $weeks): array
    {
        if ($weeks === []) {
            return [];
        }

        ksort($weeks);
        $all = [];

        for ($week = CarbonImmutable::parse(array_key_first($weeks)); $week->toDateString() <= array_key_last($weeks); $week = $week->addWeek()) {
            $all[$week->toDateString()] = $weeks[$week->toDateString()] ?? null;
        }

        return $all;
    }

    /** Round an axis maximum up to 1, 2 or 5 times a power of ten. */
    private static function niceCeiling(int $value): int
    {
        $power = 10 ** (int) floor(log10($value));

        foreach ([1, 2, 5, 10] as $factor) {
            if ($factor * $power >= $value) {
                return $factor * $power;
            }
        }

        return 10 * $power;
    }
}
