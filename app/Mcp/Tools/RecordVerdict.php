<?php

namespace App\Mcp\Tools;

use App\Enums\Magnitude;
use App\Enums\Verdict;
use App\Read\OwnerWrites;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description("Record the owner's Verdict on an Alarm — worth_considering or noise — with their one-line reason and, if they gave one, the Magnitude. Only when the owner says so in this conversation.")]
class RecordVerdict extends Tool
{
    public function handle(Request $request, OwnerWrites $writes): Response
    {
        $input = $request->validate(['alarm_id' => 'required|integer|min:1', 'verdict' => 'required|string', 'note' => 'nullable|string|max:500', 'magnitude' => 'nullable|string']);

        try {
            $alarm = $writes->verdict($input['alarm_id'], $input['verdict'], $input['note'] ?? null, $input['magnitude'] ?? null);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text("Recorded: Alarm {$alarm->id} is {$alarm->verdict->value}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'alarm_id' => $schema->integer()->required(),
            'verdict' => $schema->string()->enum(array_column(Verdict::cases(), 'value'))->required(),
            'note' => $schema->string()->description("The owner's one-line reason, in their words"),
            'magnitude' => $schema->string()->enum(array_column(Magnitude::cases(), 'value')),
        ];
    }
}
