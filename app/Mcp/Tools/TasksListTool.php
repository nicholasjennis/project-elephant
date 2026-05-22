<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List tasks with optional filters (status, SKU, job number, GPM, designer, due date range, and text search).')]
class TasksListTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])],
            'sku' => ['nullable', 'string', 'max:255'],
            'job_number' => ['nullable', 'string', 'max:255'],
            'gpm_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'designer_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_before' => ['nullable', 'date'],
            'due_after' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 25);

        $query = Task::query()
            ->with(['gpm:id,name,email', 'designers:id,name,email'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('project_status', $status))
            ->when($validated['sku'] ?? null, fn ($q, $sku) => $q->where('sku', 'like', "%{$sku}%"))
            ->when($validated['job_number'] ?? null, fn ($q, $jobNumber) => $q->where('job_number', 'like', "%{$jobNumber}%"))
            ->when($validated['gpm_user_id'] ?? null, fn ($q, $gpmId) => $q->where('gpm_user_id', $gpmId))
            ->when($validated['designer_id'] ?? null, fn ($q, $designerId) => $q->whereHas('designers', fn ($dq) => $dq->where('users.id', $designerId)))
            ->when($validated['due_before'] ?? null, fn ($q, $dueBefore) => $q->whereDate('due_date', '<=', $dueBefore))
            ->when($validated['due_after'] ?? null, fn ($q, $dueAfter) => $q->whereDate('due_date', '>=', $dueAfter))
            ->when($validated['search'] ?? null, function ($q, $search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('phase_task', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('gpm_note', 'like', "%{$search}%")
                        ->orWhere('gd_notes', 'like', "%{$search}%")
                        ->orWhere('job_number', 'like', "%{$search}%");
                });
            })
            ->latest();

        $tasks = $query->limit($limit)->get();

        return Response::json([
            'generated_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'count' => $tasks->count(),
            'limit' => $limit,
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'sku' => $task->sku,
                'phase_task' => $task->phase_task,
                'project_status' => $task->project_status,
                'job_number' => $task->job_number,
                'due_date' => $task->due_date,
                'gpm' => $task->gpm ? [
                    'id' => $task->gpm->id,
                    'name' => $task->gpm->name,
                    'email' => $task->gpm->email,
                ] : null,
                'designers' => $task->designers->map(fn ($designer) => [
                    'id' => $designer->id,
                    'name' => $designer->name,
                    'email' => $designer->email,
                ])->values()->all(),
                'priority' => $task->priority,
                'assets_status' => $task->assets_status,
                'gpm_note' => $task->gpm_note,
                'gd_notes' => $task->gd_notes,
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
            'status' => $schema->string()->enum(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])->description('Filter by project status.'),
            'sku' => $schema->string()->description('Filter by SKU (partial match).'),
            'job_number' => $schema->string()->description('Filter by job number (partial match).'),
            'gpm_user_id' => $schema->integer()->description('Filter by GPM user ID.'),
            'designer_id' => $schema->integer()->description('Filter by designer user ID.'),
            'due_before' => $schema->string()->format('date')->description('Include tasks due on or before this date (YYYY-MM-DD).'),
            'due_after' => $schema->string()->format('date')->description('Include tasks due on or after this date (YYYY-MM-DD).'),
            'search' => $schema->string()->description('Free-text search across SKU, phase task, description, notes, and job number.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Max number of tasks to return. Defaults to 25.'),
        ];
    }
}
