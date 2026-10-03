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
#[Description("Start the daily run the scheduler would start: collect every missing day of the last week and route that day's Candidates through the Classifier. Only when the owner asks. Returns what it collected and discovered.")]
class RunDaily extends Tool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate(['days' => 'nullable|integer|min:1|max:30']);
        $status = Artisan::call('trends:daily', ['--days' => $input['days'] ?? 7]);

        return $status === 0 ? Response::text(Artisan::output()) : Response::error(Artisan::output());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['days' => $schema->integer()->description('How many days back to heal; defaults to 7')];
    }
}
