<?php

namespace App\Subjects;

use App\Collection\SourceHttp;
use Illuminate\Http\Client\Factory;
use UnexpectedValueException;

/**
 * The Classifier (ADR-0006): TypeSafe's Jev answers two typed questions about a
 * Candidate — is it a specific, nameable thing (for an App Store search term:
 * is it a search for a kind of app or task, ADR-0012), and which Label fits. It never
 * measures, never scores, and its probabilities never reach the Trend Score.
 */
final class Jev
{
    public function __construct(private readonly Factory $http) {}

    public function configured(): bool
    {
        return filled(config('trend.classifier.key'));
    }

    private const SPECIFIC = 'Is the candidate a specific, nameable thing — a product, tool, library, technology, company or named practice — rather than a broad field (like "AI" or "programming"), a generic word, or a fragment of a sentence? A search for a specific kind of app (like "pdf scanner" or "plant identifier") counts as specific.';

    private const NEED = 'The candidate is a search term people type into the App Store. Is it a search for a kind of app or a task people want done — like "pdf scanner", "ai photo editor", "plant identifier" or "marathon training plan" — rather than a search for one named brand, app, game, TV channel, publication, sports team, person, or a dated event — like "ufc fight pass", "game informer", "official whitehouse app" or "chicago marathon"?';

    /**
     * @param  list<string>  $seenIn  titles the Candidate appeared in, as context
     * @param  bool  $appStore  an App Store search term, asked whether it is a need rather than whether it is specific
     */
    public function classify(string $candidate, array $seenIn, bool $appStore = false): Classification
    {
        $question = $appStore ? 'need' : 'specific';
        $answers = SourceHttp::client($this->http, config('trend.classifier.url'))
            ->withToken(config('trend.classifier.key'))
            ->post('/v1/systemone', [
                'model' => config('trend.classifier.model'),
                'state' => ['candidate' => $candidate, 'seen_in' => $seenIn],
                'questions' => [
                    $question => [
                        'type' => 'noul',
                        'instructions' => $appStore ? self::NEED : self::SPECIFIC,
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

        if (! is_numeric($answers[$question]['noul'] ?? null) || ! is_string($answers['label']['choice'] ?? null)) {
            throw new UnexpectedValueException("Jev answered without both answers for [{$candidate}].");
        }

        return new Classification((float) $answers[$question]['noul'], $answers['label']['choice'], $question);
    }
}
