<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Find tasks owned by or assigned to a specific user (GPM or designer).')]
class TasksByUserTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['gpm', 'designer', 'any'])],
            'status' => ['nullable', Rule::in(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! isset($validated['user_id']) && ! isset($validated['user_name'])) {
            return Response::error('You must provide either user_id or user_name.');
        }

        $user = isset($validated['user_id'])
            ? User::query()->find($validated['user_id'])
            : User::query()
                ->where('name', 'like', '%'.$validated['user_name'].'%')
                ->orderBy('name')
                ->first();

        if (! $user) {
            return Response::error('User not found for the provided input.');
        }

        $limit = (int) ($validated['limit'] ?? 25);
        $role = $validated['role'] ?? 'any';

        $query = Task::query()
            ->with(['gpm:id,name,email', 'designers:id,name,email'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('project_status', $status))
            ->latest()
            ->limit($limit);

        if ($role === 'gpm') {
            $query->where('gpm_user_id', $user->id);
        } elseif ($role === 'designer') {
            $query->whereHas('designers', fn ($dq) => $dq->where('users.id', $user->id));
        } else {
            $query->where(function ($scoped) use ($user): void {
                $scoped
                    ->where('gpm_user_id', $user->id)
                    ->orWhereHas('designers', fn ($dq) => $dq->where('users.id', $user->id));
            });
        }

        $tasks = $query->get();

        return Response::json([
            'generated_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'requested_role' => $role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'count' => $tasks->count(),
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'sku' => $task->sku,
                'phase_task' => $task->phase_task,
                'project_status' => $task->project_status,
                'due_date' => $task->due_date,
                'job_number' => $task->job_number,
                'gpm' => $task->gpm?->name,
                'designers' => $task->designers->pluck('name')->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('Exact user ID to query tasks for.'),
            'user_name' => $schema->string()->description('Fallback partial name lookup if user_id is not provided.'),
            'role' => $schema->string()->enum(['gpm', 'designer', 'any'])->description('How to match the user against tasks. Defaults to any.'),
            'status' => $schema->string()->enum(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])->description('Optional status filter.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Max number of tasks to return. Defaults to 25.'),
        ];
    }
}
