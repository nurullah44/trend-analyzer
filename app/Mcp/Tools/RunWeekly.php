<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Start the weekly run the scheduler would start: measure every tracked Subject, score the last finished week, move Subjects and publish Alarms. Only when the owner asks. Afterwards, read weekly-report and tell the owner what it found.')]
class RunWeekly extends Tool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate(['week' => 'nullable|date_format:Y-m-d']);
        $status = Artisan::call('trends:weekly', array_filter(['--week' => $input['week'] ?? null]));

        return $status === 0 ? Response::text(Artisan::output()) : Response::error(Artisan::output());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['week' => $schema->string()->description('The Monday of a finished week, YYYY-MM-DD; defaults to the last finished week')];
    }
}
