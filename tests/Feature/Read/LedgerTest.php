<?php

namespace Tests\Feature\Read;

use App\Collection\SourceRegistry;
use App\Enums\SubjectState;
use App\Models\Alarm;
use App\Models\Item;
use App\Models\Source;
use App\Models\Subject;
use App\Models\SubjectWeek;
use App\Read\Ledger;
use App\Read\OwnerWrites;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\FakeWeeklyCollector;
use Tests\TestCase;

/** The read layer and the three writes, tested once at the boundary the CLI, MCP server and pages share. */
class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private Alarm $alarm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-09-29 08:00', 'UTC'));
        config(['trend.classifier.key' => 'jv_test']);

        $subject = $this->app->make(OwnerWrites::class)->seed('Tidewave', null, ['dev-tool']);
        $hn = Source::where('key', 'hacker_news')->value('id');

        foreach (range(8, 0) as $back) {
            SubjectWeek::create(['subject_id' => $subject->id, 'source_id' => $hn, 'query' => 'Tidewave', 'week' => CarbonImmutable::parse('2026-09-21')->subWeeks($back)->toDateString(), 'volume' => $back === 0 ? 30 : 5]);
        }

        $subject->update(['scored_week' => '2026-09-21']);
        $subject->moveTo(SubjectState::Trending, 'corroborated rise');
        $this->alarm = Alarm::create(['subject_id' => $subject->id, 'week' => '2026-09-21', 'published_on' => '2026-09-29', 'state_at_publication' => 'trending', 'corroboration' => 2, 'score' => 7.5, 'evidence' => ['volumes' => ['hacker_news' => 30], 'rising' => ['hacker_news']]]);
        Item::create(['source_id' => $hn, 'external_id' => '1', 'title' => 'Show HN: Tidewave – agents', 'url' => 'https://example.com', 'observed_on' => '2026-09-27', 'measured_quantity' => 80]);
    }

    public function test_one_subject_carries_everything_behind_it(): void
    {
        $subject = $this->app->make(Ledger::class)->subject('tidewave');

        $this->assertSame('trending', $subject['state']);
        $this->assertSame(['dev-tool'], $subject['labels']);
        $this->assertSame(30, $subject['series']['hacker_news']['2026-09-21']);
        $this->assertSame(['hacker_news'], $subject['score']['rising'], 'the score is worked out from the stored series');
        $this->assertSame(['seeded', 'state_changed'], array_column($subject['events'], 'type'));
        $this->assertSame([$this->alarm->id], array_column($subject['alarms'], 'id'));
        $this->assertSame('https://example.com', $subject['items'][0]['url']);
        $this->assertNull($this->app->make(Ledger::class)->subject('nothing'));
    }

    public function test_subjects_alarms_and_the_report_list_what_is_there(): void
    {
        $ledger = $this->app->make(Ledger::class);

        $this->assertSame(['tidewave'], array_column($ledger->subjects('trending'), 'slug'));
        $this->assertSame([], $ledger->subjects('watching'));
        $this->assertSame(['tidewave'], array_column($ledger->subjects(label: 'dev-tool'), 'slug'));
        $this->assertSame([$this->alarm->id], array_column($ledger->alarms(), 'id'), 'open Alarms by default');
        $this->assertSame([$this->alarm->id], array_column($ledger->report()['alarms'], 'id'));
        $this->assertContains('hacker_news', array_column($ledger->sources(), 'key'));
    }

    public function test_the_gaps_name_a_source_without_credentials_and_a_weekly_publication_not_collected(): void
    {
        $this->assertContains('apple_ads has no credentials yet: it is left out of every run', $this->app->make(Ledger::class)->gaps());

        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'UTC'));
        $this->app->instance(SourceRegistry::class, new SourceRegistry(['apple_ads' => new FakeWeeklyCollector('apple_ads')]));
        Item::create(['source_id' => Source::where('key', 'apple_ads')->value('id'), 'external_id' => 'HEALTH_FITNESS:step counter', 'title' => 'step counter', 'observed_on' => '2026-10-05']);

        $gaps = $this->app->make(Ledger::class)->gaps();

        $this->assertContains('apple_ads has not collected its publication of 2026-10-12', $gaps, "last week's Items do not hide this Monday's");
        $this->assertNotContains('apple_ads has no credentials yet: it is left out of every run', $gaps);
    }

    public function test_the_owner_records_a_verdict_and_a_magnitude(): void
    {
        $alarm = $this->app->make(OwnerWrites::class)->verdict($this->alarm->id, 'worth_considering', 'a real gap', 'big');

        $this->assertSame('worth_considering', $alarm->verdict->value);
        $this->assertSame('a real gap', $alarm->verdict_note);
        $this->assertSame('big', $alarm->subject->magnitude->value);
        $this->assertSame('worth_considering', $this->app->make(Ledger::class)->alarms()[0]['verdict']);

        $this->app->make(OwnerWrites::class)->verdict($this->alarm->id, 'worth_considering', 'a real gap', 'big');
        $this->assertSame(1, $alarm->subject->events()->where('type', 'verdict')->count(), 'the same ruling twice is recorded once');
    }

    public function test_writes_refuse_what_does_not_exist_or_is_not_allowed(): void
    {
        $writes = $this->app->make(OwnerWrites::class);

        foreach ([
            fn () => $writes->verdict(999, 'noise'),
            fn () => $writes->verdict($this->alarm->id, 'great'),
            fn () => $writes->verdict($this->alarm->id, 'noise', null, 'huge'),
            fn () => $writes->label('nothing', 'ai'),
            fn () => $writes->seed('a name that is far too long to be a subject'),
            fn () => $writes->seed('Blobby', null, ['ai', ' ']),
        ] as $write) {
            try {
                $write();
                $this->fail('the write should have been refused');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertFalse(Subject::where('slug', 'blobby')->exists(), 'a refused Seed leaves nothing behind');
    }

    public function test_labels_come_and_go(): void
    {
        $writes = $this->app->make(OwnerWrites::class);

        $this->assertSame(['dev-tool', 'ai'], $writes->label('tidewave', 'ai')->labels->pluck('name')->all());
        $this->assertSame(['ai'], $writes->label('tidewave', 'dev-tool', remove: true)->labels->pluck('name')->all());
        $this->assertSame(['ai'], $writes->label('tidewave', 'typo', remove: true)->labels->pluck('name')->all(), 'removing an unknown Label removes nothing');
    }

    public function test_the_cli_reads_as_json_and_writes_only_the_three_writes(): void
    {
        $this->artisan('trends:show', ['what' => 'subject', 'slug' => 'tidewave'])->expectsOutputToContain('"slug": "tidewave"')->assertSuccessful();
        $this->artisan('trends:show', ['what' => 'subject', 'slug' => 'nothing'])->assertFailed();
        $this->artisan('trends:verdict', ['alarm' => $this->alarm->id, 'verdict' => 'noise', '--note' => 'old news'])->assertSuccessful();
        $this->artisan('trends:label', ['subject' => 'tidewave', 'label' => 'ai'])->expectsOutputToContain('ai')->assertSuccessful();
        $this->artisan('trends:seed', ['name' => 'Model Context Protocol', '--query' => 'MCP', '--label' => ['ai']])->expectsOutputToContain('watching')->assertSuccessful();

        $this->artisan('trends:verdict', ['alarm' => $this->alarm->id.'oops', 'verdict' => 'worth_considering'])->assertFailed();

        $this->assertSame('noise', $this->alarm->fresh()->verdict->value);
        $this->assertFalse(Subject::where('slug', 'blobby')->exists());
    }
}
