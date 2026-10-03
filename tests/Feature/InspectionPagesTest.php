<?php

namespace Tests\Feature;

use App\Http\SeriesChart;
use App\Models\Alarm;
use App\Models\Source;
use App\Models\SubjectWeek;
use App\Read\OwnerWrites;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionPagesTest extends TestCase
{
    use RefreshDatabase;

    private Alarm $alarm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-09-29 08:00', 'UTC'));
        $subject = $this->app->make(OwnerWrites::class)->seed('Tidewave', null, ['dev-tool']);

        foreach (['2026-09-07' => 4, '2026-09-14' => null, '2026-09-21' => 30] as $week => $volume) {
            SubjectWeek::create(['subject_id' => $subject->id, 'source_id' => Source::where('key', 'hacker_news')->value('id'), 'query' => 'Tidewave', 'week' => $week, 'volume' => $volume]);
        }

        $this->alarm = Alarm::create(['subject_id' => $subject->id, 'week' => '2026-09-21', 'published_on' => '2026-09-29', 'state_at_publication' => 'trending', 'corroboration' => 2, 'score' => 7.5, 'evidence' => ['volumes' => ['hacker_news' => 30], 'rising' => ['hacker_news'], 'items' => [['source' => 'hacker_news', 'title' => 'Show HN: Tidewave', 'url' => 'https://example.com', 'measured_quantity' => 80]]]]);
    }

    public function test_the_alarms_page_shows_the_weeks_alarms_and_their_evidence(): void
    {
        $this->get('/')->assertOk()->assertSee('Tidewave')->assertSee('Show HN: Tidewave')->assertSee('Trend Score')->assertSee('clay.css');
    }

    public function test_the_subject_page_shows_its_series_and_story(): void
    {
        $this->get('/subjects/tidewave')->assertOk()->assertSee('Weekly series')->assertSee('<svg', false)->assertSee('Series as a table')->assertSee('watching')->assertSee('seeded');
        $this->get('/subjects/nothing')->assertNotFound();
    }

    public function test_the_sources_page_shows_collection_health(): void
    {
        $this->get('/sources')->assertOk()->assertSee('hacker_news')->assertSee('discovery, measurement');
    }

    public function test_the_verdict_form_records_through_the_owner_writes(): void
    {
        $this->from('/')->post("/alarms/{$this->alarm->id}/verdict", ['verdict' => 'noise', 'note' => 'old news'])->assertRedirect('/');
        $this->from('/')->post("/alarms/{$this->alarm->id}/verdict", ['verdict' => 'great'])->assertSessionHasErrors('verdict');

        $this->assertSame('noise', $this->alarm->fresh()->verdict->value);
    }

    public function test_an_unmeasurable_week_breaks_the_line_and_the_axis_starts_at_zero(): void
    {
        $chart = new SeriesChart(['2026-09-07' => 4, '2026-09-14' => null, '2026-09-21' => 30]);

        $this->assertSame(50, $chart->max);
        $this->assertSame(2, substr_count($chart->path(), 'M'), 'the gap starts a new segment');
        $this->assertSame(30, $chart->last()['volume']);
        $this->assertSame('', $chart->area(), 'no wash joins two measured weeks across a gap');

        $outage = new SeriesChart(['2026-08-03' => 5, '2026-08-10' => 6, '2026-09-21' => 30]);
        $this->assertCount(8, $outage->points, 'weeks never measured still take their place on the axis');
        $this->assertNull($outage->points[3]['volume']);
    }
}
