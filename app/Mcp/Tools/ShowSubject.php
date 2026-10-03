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
#[Description('Why is this Subject where it is? Its weekly series per Source, the latest score worked out from it, every state change with the numbers behind it, its Alarms, Google Ads figures and the recent Items that mention it.')]
class ShowSubject extends Tool
{
    public function handle(Request $request, Ledger $ledger): Response
    {
        $slug = (string) $request->get('slug');

        return ($subject = $ledger->subject($slug)) === null
            ? Response::error("No Subject has slug [{$slug}]. list-subjects shows the slugs.")
            : Response::json($subject);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['slug' => $schema->string()->description('The Subject slug, as list-subjects shows it')->required()];
    }
}
