<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Support\WfPlanWeek;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
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
            'group_by' => ['required', Rule::in(['status', 'gpm', 'designer', 'priority', 'assets_status', 'wf_plan_week'])],
            'status' => ['nullable', Rule::in(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])],
            'gpm_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'designer_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_before' => ['nullable', 'date'],
            'due_after' => ['nullable', 'date'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:53'],
            'current_week' => ['nullable', 'boolean'],
        ]);

        $weekNumber = isset($validated['week_number'])
            ? (int) $validated['week_number']
            : ((bool) ($validated['current_week'] ?? false) ? now()->isoWeek() : null);

        $tasks = Task::query()
            ->with(['gpm:id,name', 'designers:id,name'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('project_status', $status))
            ->when($validated['gpm_user_id'] ?? null, fn ($q, $gpmId) => $q->where('gpm_user_id', $gpmId))
            ->when($validated['designer_id'] ?? null, fn ($q, $designerId) => $q->whereHas('designers', fn ($dq) => $dq->where('users.id', $designerId)))
            ->when($validated['due_before'] ?? null, fn ($q, $dueBefore) => $q->whereDate('due_date', '<=', $dueBefore))
            ->when($validated['due_after'] ?? null, fn ($q, $dueAfter) => $q->whereDate('due_date', '>=', $dueAfter))
            ->get();

        if ($weekNumber !== null) {
            $tasks = $tasks->filter(fn (Task $task) => WfPlanWeek::matchesWeekNumber($task->wf_plan_week, $weekNumber))->values();
        }

        $groupBy = $validated['group_by'];

        $summary = match ($groupBy) {
            'status' => $this->toGroupedCounts($tasks->map(fn (Task $task) => $task->project_status ?: 'Unspecified')),
            'gpm' => $this->toGroupedCounts($tasks->map(fn (Task $task) => $task->gpm?->name ?: 'Unassigned')),
            'priority' => $this->toGroupedCounts($tasks->map(fn (Task $task) => $task->priority ?: 'Unspecified')),
            'assets_status' => $this->toGroupedCounts($tasks->map(fn (Task $task) => $task->assets_status ?: 'Unspecified')),
            'wf_plan_week' => $this->toGroupedCounts($tasks->map(fn (Task $task) => $task->wf_plan_week ?: 'Unspecified')),
            'designer' => $this->toGroupedCounts(
                $tasks->flatMap(
                    fn (Task $task) => $task->designers->isEmpty() ? ['Unassigned'] : $task->designers->pluck('name')->all()
                )
            ),
            default => collect(),
        };

        return Response::json([
            'generated_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'group_by' => $groupBy,
            'week_number' => $weekNumber,
            'total_tasks_considered' => $tasks->count(),
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
            'group_by' => $schema->string()->enum(['status', 'gpm', 'designer', 'priority', 'assets_status', 'wf_plan_week'])->required(),
            'status' => $schema->string()->enum(['DONE', 'in progress', 'Upcoming', 'Wait for FBs'])->description('Optional status pre-filter.'),
            'gpm_user_id' => $schema->integer()->description('Optional GPM user filter.'),
            'designer_id' => $schema->integer()->description('Optional designer filter.'),
            'due_before' => $schema->string()->format('date')->description('Optional due date upper bound (YYYY-MM-DD).'),
            'due_after' => $schema->string()->format('date')->description('Optional due date lower bound (YYYY-MM-DD).'),
            'week_number' => $schema->integer()->min(1)->max(53)->description('Optional WF plan week number filter (1-53).'),
            'current_week' => $schema->boolean()->description('If true, filters by current ISO week number.'),
        ];
    }

    private function toGroupedCounts(Collection $groups): Collection
    {
        return $groups
            ->countBy()
            ->map(fn (int $count, string $group) => (object) ['grouping' => $group, 'total' => $count])
            ->values()
            ->sortByDesc(fn (object $row) => $row->total)
            ->values();
    }
}
