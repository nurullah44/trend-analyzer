<?php

namespace App\Mcp\Tools;

use App\Read\Ledger;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Is collection healthy? Every registered Source with its roles, last success, last Item count and last error, plus what the analyzer could not see.')]
class SourceHealth extends Tool
{
    public function handle(Request $request, Ledger $ledger): Response
    {
        return Response::json(['sources' => $ledger->sources(), 'gaps' => $ledger->gaps()]);
    }
}
