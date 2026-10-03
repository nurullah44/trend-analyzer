<?php

namespace App\Mcp\Tools;

use App\Read\Ledger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("What did the analyzer find this week? The week's Alarms ranked by Corroboration then Trend Score, each with its Evidence, plus Source health and the analyzer's gaps.")]
class WeeklyReport extends Tool
{
    public function handle(Request $request, Ledger $ledger): Response
    {
        return Response::json($ledger->report($request->get('week')));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['week' => $schema->string()->description('The Monday of the week, YYYY-MM-DD; defaults to the last finished week')];
    }
}
