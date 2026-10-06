<?php

namespace App\Subjects;

/**
 * The Classifier's answer for one Candidate: how likely it is what the analyzer
 * tracks, and the Label it chose. For a Hacker News or Stack Exchange name that is
 * whether it is a specific thing; for an App Store search term, whether it looks
 * for a kind of app or a task rather than one brand, title or event (ADR-0012).
 */
final readonly class Classification
{
    /** @param 'specific'|'need' $question */
    public function __construct(
        public float $probability,
        public string $label,
        public string $question = 'specific',
    ) {}
}
