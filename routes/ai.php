<?php

use Laravel\Mcp\Facades\Mcp;
use App\Mcp\Servers\TaskServer;

Mcp::local('tasks', TaskServer::class);
Mcp::web('/mcp/tasks', TaskServer::class);
