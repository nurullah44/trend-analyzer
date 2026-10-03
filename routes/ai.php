<?php

use App\Mcp\TrendServer;
use Laravel\Mcp\Facades\Mcp;

// Claude Code starts this over stdio (see .mcp.json): php artisan mcp:start trends
Mcp::local('trends', TrendServer::class);
