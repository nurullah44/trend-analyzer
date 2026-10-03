<?php

namespace App\Subjects;

use App\Collection\SourceHttp;
use Illuminate\Http\Client\Factory;
use UnexpectedValueException;

/**
 * The Classifier (ADR-0006): TypeSafe's Jev answers two typed questions about a
 * Candidate — is it a specific, nameable thing, and which Label fits. It never
 * measures, never scores, and its probabilities never reach the Trend Score.
 */
final class Jev
{
    public function __construct(private readonly Factory $http) {}

    public function configured(): bool
    {
        return filled(config('trend.classifier.key'));
    }

    /** @param list<string> $seenIn titles the Candidate appeared in, as context */
    public function classify(string $candidate, array $seenIn): Classification
    {
        $answers = SourceHttp::client($this->http, config('trend.classifier.url'))
            ->withToken(config('trend.classifier.key'))
            ->post('/v1/systemone', [
                'model' => config('trend.classifier.model'),
                'state' => ['candidate' => $candidate, 'seen_in' => $seenIn],
                'questions' => [
                    'specific' => [
                        'type' => 'noul',
                        'instructions' => 'Is the candidate a specific, nameable thing — a product, tool, library, technology, company or named practice — rather than a broad field (like "AI" or "programming"), a generic word, or a fragment of a sentence?',
                    ],
                    'label' => [
                        'type' => 'choice',
                        'instructions' => 'Which kind of thing is the candidate?',
                        'criteria' => config('trend.classifier.labels'),
                    ],
                ],
            ])
            ->throw()
            ->json('answers');

        if (! is_numeric($answers['specific']['noul'] ?? null) || ! is_string($answers['label']['choice'] ?? null)) {
            throw new UnexpectedValueException("Jev answered without both answers for [{$candidate}].");
        }

        return new Classification((float) $answers['specific']['noul'], $answers['label']['choice']);
    }
}
