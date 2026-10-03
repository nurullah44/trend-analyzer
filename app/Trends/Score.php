<?php

namespace App\Trends;

/**
 * One Subject's numbers for one week, every one of them explainable from the
 * stored series. The Trend Score is never a probability.
 */
final readonly class Score
{
    /**
     * @param  array<string, int>  $volumes  this week's Volume per Source
     * @param  array<string, float>  $velocities  per Source with enough baseline to judge
     * @param  list<string>  $rising  the Sources that count towards Corroboration
     */
    public function __construct(
        public array $volumes,
        public array $velocities,
        public array $rising,
        public float $trendScore,
    ) {}

    public function corroboration(): int
    {
        return count($this->rising);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'volumes' => $this->volumes,
            'velocities' => array_map(fn (float $velocity) => round($velocity, 2), $this->velocities),
            'rising' => $this->rising,
            'corroboration' => $this->corroboration(),
            'trend_score' => round($this->trendScore, 2),
        ];
    }
}
