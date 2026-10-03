<?php

namespace App\Subjects;

/** The Classifier's answer for one Candidate: how likely it is a specific thing, and the Label it chose. */
final readonly class Classification
{
    public function __construct(
        public float $specific,
        public string $label,
    ) {}
}
