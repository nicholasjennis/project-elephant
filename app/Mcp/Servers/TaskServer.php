<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\TasksByUserTool;
use App\Mcp\Tools\TasksListTool;
use App\Mcp\Tools\TasksSummaryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Task Server')]
#[Version('0.0.1')]
#[Instructions('Use this server to query task data by status, assignee, and key task fields.')]
class TaskServer extends Server
{
    protected array $tools = [
        TasksListTool::class,
        TasksSummaryTool::class,
        TasksByUserTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
