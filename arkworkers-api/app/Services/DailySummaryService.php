<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * PRD.md §7: completion rates, overdue tasks, and asset issues,
 * reportable daily. Restricted-space data is excluded from these
 * aggregates for any user without Admin status or a grant, per
 * SECURITY.md §4.2, using the same TaskPolicy::view() check the
 * Tasks list already goes through, not a reimplemented rule.
 *
 * "Asset issues" has no dedicated field or model anywhere in the
 * codebase, PRD.md §7 names it but doesn't define it further. The
 * only concrete signal currently available is an asset carrying one
 * or more overdue tasks, so that's what this reports, derived purely
 * from existing Task/Routine/Asset data rather than inventing a new
 * "issue" concept that was never actually specified.
 */
class DailySummaryService
{
    public function generate(User $user): array
    {
        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();

        $tasks = Task::query()
            ->with('routine.asset.space', 'assignedUser')
            ->get()
            ->filter(fn (Task $task) => $user->can('view', $task))
            ->values();

        $dueToday = $tasks->filter(
            fn (Task $task) => $task->due_at->gte($today) && $task->due_at->lt($tomorrow)
        );
        $completedToday = $dueToday->filter(
            fn (Task $task) => $task->status === Task::STATUS_COMPLETED
        );

        $overdue = $tasks->filter(
            fn (Task $task) => $task->status !== Task::STATUS_COMPLETED
                && $task->due_at->lt(Carbon::now())
        )->sortBy('due_at')->values();

        return [
            'date' => $today->toDateString(),
            'completion_rate' => $this->rate($completedToday->count(), $dueToday->count()),
            'tasks_due_today' => $dueToday->count(),
            'tasks_completed_today' => $completedToday->count(),
            'overdue_count' => $overdue->count(),
            'overdue_tasks' => $overdue->map(fn (Task $task) => $this->overdueTaskSummary($task))->all(),
            'asset_issues' => $this->assetIssues($overdue),
        ];
    }

    private function rate(int $completed, int $due): float
    {
        if ($due === 0) {
            return 0.0;
        }

        return round(($completed / $due) * 100, 1);
    }

    private function overdueTaskSummary(Task $task): array
    {
        $asset = $task->routine?->asset;

        return [
            'task_id' => $task->id,
            'asset_name' => $asset?->name,
            'space_name' => $asset?->space?->name,
            'assigned_user_name' => $task->assignedUser?->name,
            'due_at' => $task->due_at->toIso8601String(),
            'days_overdue' => (int) $task->due_at->diffInDays(Carbon::now()),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Task>  $overdueTasks
     */
    private function assetIssues($overdueTasks): array
    {
        return $overdueTasks
            ->filter(fn (Task $task) => $task->routine?->asset !== null)
            ->groupBy(fn (Task $task) => $task->routine->asset_id)
            ->map(function ($tasksForAsset) {
                $asset = $tasksForAsset->first()->routine->asset;

                return [
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->name,
                    'space_name' => $asset->space?->name,
                    'overdue_task_count' => $tasksForAsset->count(),
                ];
            })
            ->sortByDesc('overdue_task_count')
            ->values()
            ->all();
    }
}
