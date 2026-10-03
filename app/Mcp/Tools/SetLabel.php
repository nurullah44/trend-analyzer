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
#[Description('Attach a Label to a Subject, or take it off. Labels decide which Alarms reach the owner; they never change what is collected. Only when the owner says so in this conversation.')]
class SetLabel extends Tool
{
    public function handle(Request $request, OwnerWrites $writes): Response
    {
        $input = $request->validate(['slug' => 'required|string', 'label' => 'required|string|max:50', 'remove' => 'boolean']);

        try {
            $subject = $writes->label($input['slug'], $input['label'], (bool) ($input['remove'] ?? false));
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text("{$subject->slug} now has: ".($subject->labels->pluck('name')->join(', ') ?: 'no Labels'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->required(),
            'label' => $schema->string()->required(),
            'remove' => $schema->boolean()->description('Take the Label off instead'),
        ];
    }
}
