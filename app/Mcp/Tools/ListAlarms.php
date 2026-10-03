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
#[Description('Which Alarms are open, or which were published in a given week? Each carries its Evidence and any Verdict already recorded.')]
class ListAlarms extends Tool
{
    public function handle(Request $request, Ledger $ledger): Response
    {
        return Response::json($ledger->alarms($request->get('week')));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['week' => $schema->string()->description('The Monday of a week, YYYY-MM-DD; without it, every open Alarm')];
    }
}
