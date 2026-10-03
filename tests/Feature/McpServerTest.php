<?php

namespace Tests\Feature;

use App\Mcp\Tools\AddSeed;
use App\Mcp\Tools\ListAlarms;
use App\Mcp\Tools\ListSubjects;
use App\Mcp\Tools\RecordVerdict;
use App\Mcp\Tools\SetLabel;
use App\Mcp\Tools\ShowSubject;
use App\Mcp\Tools\SourceHealth;
use App\Mcp\Tools\WeeklyReport;
use App\Mcp\TrendServer;
use App\Models\Alarm;
use App\Models\Subject;
use App\Read\Ledger;
use App\Read\OwnerWrites;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->app->make(OwnerWrites::class)->seed('Tidewave', null, ['dev-tool']);
    }

    public function test_the_server_offers_the_reads_and_the_three_writes(): void
    {
        TrendServer::tools()->assertRegistered([WeeklyReport::class, ListAlarms::class, ListSubjects::class, ShowSubject::class, SourceHealth::class, RecordVerdict::class, SetLabel::class, AddSeed::class]);
    }

    public function test_reads_return_what_the_ledger_returns(): void
    {
        $subject = $this->app->make(Ledger::class)->subject('tidewave');

        TrendServer::tool(ShowSubject::class, ['slug' => 'tidewave'])->assertOk()->assertSee(['tidewave', $subject['first_seen_on'], 'dev-tool']);
        TrendServer::tool(ListSubjects::class, ['state' => 'watching'])->assertOk()->assertSee('tidewave');
        TrendServer::tool(WeeklyReport::class)->assertOk()->assertSee('"gaps"');
        TrendServer::tool(SourceHealth::class)->assertOk()->assertSee('hacker_news');
        TrendServer::tool(ShowSubject::class, ['slug' => 'nothing'])->assertHasErrors(['No Subject has slug [nothing]']);
    }

    public function test_writes_go_through_the_owner_writes_and_refuse_unknowns(): void
    {
        $alarm = Alarm::create(['subject_id' => Subject::value('id'), 'week' => '2026-09-21', 'published_on' => '2026-09-29', 'state_at_publication' => 'trending', 'evidence' => []]);

        TrendServer::tool(RecordVerdict::class, ['alarm_id' => $alarm->id, 'verdict' => 'noise', 'note' => 'old news'])->assertOk()->assertSee('noise');
        TrendServer::tool(RecordVerdict::class, ['alarm_id' => 999, 'verdict' => 'noise'])->assertHasErrors(['No Alarm has id 999']);
        TrendServer::tool(SetLabel::class, ['slug' => 'tidewave', 'label' => 'ai'])->assertOk()->assertSee('ai');
        TrendServer::tool(SetLabel::class, ['slug' => 'nothing', 'label' => 'ai'])->assertHasErrors();
        TrendServer::tool(AddSeed::class, ['name' => 'Model Context Protocol', 'query' => 'MCP'])->assertOk()->assertSee('watching');

        $this->assertSame('noise', $alarm->fresh()->verdict->value);
        $this->assertTrue(Subject::where('slug', 'model-context-protocol')->exists());
    }
}
