<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Summarize tasks grouped by status, GPM, designer, priority, or assets status.')]
class TasksSummaryTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'group_by' => ['required', Rule::in(['status', 'gpm', 'designer', 'priority', 'assets_status'])],
            'status' => ['nullable', Rule::in(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])],
            'gpm_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'designer_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_before' => ['nullable', 'date'],
            'due_after' => ['nullable', 'date'],
        ]);

        $baseQuery = Task::query()
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('project_status', $status))
            ->when($validated['gpm_user_id'] ?? null, fn ($q, $gpmId) => $q->where('gpm_user_id', $gpmId))
            ->when($validated['designer_id'] ?? null, fn ($q, $designerId) => $q->whereHas('designers', fn ($dq) => $dq->where('users.id', $designerId)))
            ->when($validated['due_before'] ?? null, fn ($q, $dueBefore) => $q->whereDate('due_date', '<=', $dueBefore))
            ->when($validated['due_after'] ?? null, fn ($q, $dueAfter) => $q->whereDate('due_date', '>=', $dueAfter));

        $groupBy = $validated['group_by'];

        $summary = match ($groupBy) {
            'status' => (clone $baseQuery)
                ->selectRaw("COALESCE(project_status, 'Unspecified') as grouping, COUNT(*) as total")
                ->groupBy('grouping')
                ->orderByDesc('total')
                ->get(),
            'gpm' => (clone $baseQuery)
                ->leftJoin('users as gpms', 'gpms.id', '=', 'tasks.gpm_user_id')
                ->selectRaw("COALESCE(gpms.name, 'Unassigned') as grouping, COUNT(*) as total")
                ->groupBy('grouping')
                ->orderByDesc('total')
                ->get(),
            'priority' => (clone $baseQuery)
                ->selectRaw("COALESCE(priority, 'Unspecified') as grouping, COUNT(*) as total")
                ->groupBy('grouping')
                ->orderByDesc('total')
                ->get(),
            'assets_status' => (clone $baseQuery)
                ->selectRaw("COALESCE(assets_status, 'Unspecified') as grouping, COUNT(*) as total")
                ->groupBy('grouping')
                ->orderByDesc('total')
                ->get(),
            'designer' => DB::table('tasks')
                ->join('task_designer', 'task_designer.task_id', '=', 'tasks.id')
                ->join('users as designers', 'designers.id', '=', 'task_designer.user_id')
                ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('tasks.project_status', $status))
                ->when($validated['gpm_user_id'] ?? null, fn ($q, $gpmId) => $q->where('tasks.gpm_user_id', $gpmId))
                ->when($validated['designer_id'] ?? null, fn ($q, $designerId) => $q->where('designers.id', $designerId))
                ->when($validated['due_before'] ?? null, fn ($q, $dueBefore) => $q->whereDate('tasks.due_date', '<=', $dueBefore))
                ->when($validated['due_after'] ?? null, fn ($q, $dueAfter) => $q->whereDate('tasks.due_date', '>=', $dueAfter))
                ->selectRaw('designers.name as grouping, COUNT(DISTINCT tasks.id) as total')
                ->groupBy('designers.name')
                ->orderByDesc('total')
                ->get(),
            default => collect(),
        };

        return Response::json([
            'generated_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'group_by' => $groupBy,
            'total_tasks_considered' => (clone $baseQuery)->count(),
            'rows' => $summary->map(fn ($row) => [
                'group' => $row->grouping,
                'count' => (int) $row->total,
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
            'group_by' => $schema->string()->enum(['status', 'gpm', 'designer', 'priority', 'assets_status'])->required(),
            'status' => $schema->string()->enum(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])->description('Optional status pre-filter.'),
            'gpm_user_id' => $schema->integer()->description('Optional GPM user filter.'),
            'designer_id' => $schema->integer()->description('Optional designer filter.'),
            'due_before' => $schema->string()->format('date')->description('Optional due date upper bound (YYYY-MM-DD).'),
            'due_after' => $schema->string()->format('date')->description('Optional due date lower bound (YYYY-MM-DD).'),
        ];
    }
}
