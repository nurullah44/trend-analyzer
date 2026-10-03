<?php

namespace App\Mcp\Tools;

use App\Enums\SubjectState;
use App\Read\Ledger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Which Subjects is the analyzer tracking? Optionally only those in one state (backlog, watching, rising, trending, mainstream, detrending, archived) or with one Label.')]
class ListSubjects extends Tool
{
    public function handle(Request $request, Ledger $ledger): Response
    {
        return Response::json($ledger->subjects($request->get('state'), $request->get('label')));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()->enum(array_column(SubjectState::cases(), 'value')),
            'label' => $schema->string(),
        ];
    }
}
