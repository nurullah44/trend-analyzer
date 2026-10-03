<?php

namespace App\Mcp\Tools;

use App\Read\OwnerWrites;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Track a Subject the owner named — a specific thing in five words or fewer — or promote one waiting in Backlog. It is measured from the next weekly run. Only when the owner says so in this conversation.')]
class AddSeed extends Tool
{
    public function handle(Request $request, OwnerWrites $writes): Response
    {
        $input = $request->validate(['name' => 'required|string|max:100', 'query' => 'nullable|string|max:100', 'labels' => 'array', 'labels.*' => 'string|max:50']);

        try {
            $subject = $writes->seed($input['name'], $input['query'] ?? null, $input['labels'] ?? []);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text("Tracking {$subject->name} ({$subject->slug}), state {$subject->state->value}, query \"{$subject->query}\".");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'query' => $schema->string()->description('What to search the Sources for; defaults to the name'),
            'labels' => $schema->array()->items($schema->string()),
        ];
    }
}
