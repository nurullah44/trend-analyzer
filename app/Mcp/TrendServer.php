<?php

namespace App\Mcp;

use App\Mcp\Tools\AddSeed;
use App\Mcp\Tools\ListAlarms;
use App\Mcp\Tools\ListSubjects;
use App\Mcp\Tools\RecordVerdict;
use App\Mcp\Tools\SetLabel;
use App\Mcp\Tools\ShowSubject;
use App\Mcp\Tools\SourceHealth;
use App\Mcp\Tools\WeeklyReport;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('trend-analyzer')]
#[Version('0.1.0')]
#[Instructions(<<<'TEXT'
    The owner's trend analyzer: it detects Subjects gaining speed on public Sources before they reach the mainstream, and publishes Alarms that carry Evidence — numbers, series, Items and links — never a verdict. Statistics detect; you read and talk the results over with the owner.
    Start with weekly-report. Quote the Evidence; never invent a number or decide on the owner's behalf what is worth building.
    The three write tools (record-verdict, set-label, add-seed) are for when the owner says so in the conversation, and never otherwise. You cannot collect, run the analyzer, or change an Alarm, Evidence or a series.
    TEXT)]
class TrendServer extends Server
{
    protected array $tools = [
        WeeklyReport::class,
        ListAlarms::class,
        ListSubjects::class,
        ShowSubject::class,
        SourceHealth::class,
        RecordVerdict::class,
        SetLabel::class,
        AddSeed::class,
    ];
}
