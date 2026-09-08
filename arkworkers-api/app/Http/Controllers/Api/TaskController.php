<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Routine;
use App\Models\Task;
use App\Models\TaskProof;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = Task::query()
            ->with('routine.asset.space')
            ->get()
            ->filter(fn (Task $task) => $request->user()->can('view', $task))
            ->values();

        return response()->json(['data' => $tasks]);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json(['data' => $task]);
    }

    /**
     * Manual, ad-hoc task assignment. The automatic scheduler that
     * generates tasks from a routine's calendar/meter trigger is
     * Phase 4 scope (ARCHITECTURE.md §3); this endpoint is the
     * standalone "assign this now" path Facility Manager also needs
     * for one-off work outside the routine schedule.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $data = Validator::make($request->all(), [
            'routine_id' => ['required', 'exists:routines,id'],
            'assigned_user_id' => ['required', 'exists:users,id'],
            'due_at' => ['required', 'date'],
        ])->validate();

        $routine = Routine::findOrFail($data['routine_id']);

        // The routine must actually resolve to a space the requester
        // can see, same reasoning as AssetController::store().
        if (! $routine->isAssetBound() || ! $request->user()->can('view', $routine->asset->space)) {
            abort(403);
        }

        $task = Task::create([
            ...$data,
            'status' => Task::STATUS_PENDING,
        ]);

        return response()->json(['data' => $task], 201);
    }

    /**
     * Marks a task complete. Only the assigned user or a privileged
     * role, and only with space access, per TaskPolicy::update().
     */
    public function complete(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task->update([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return response()->json(['data' => $task]);
    }

    /**
     * Non-resumable proof upload, happy path first per RESEARCH.md's
     * Phase 2 guidance, resumable/chunked upload is Phase 3 scope.
     * Validated by actual file content (mimes rules read the file
     * signature, not just the extension), stored on the private disk
     * outside the web root, per ARCHITECTURE.md §6.
     */
    public function uploadProof(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'],
        ])->validate();

        $path = $request->file('file')->store('task-proofs', 'local');

        $proof = TaskProof::create([
            'task_id' => $task->id,
            'file_path' => $path,
            'file_type' => $request->file('file')->getMimeType(),
        ]);

        return response()->json(['data' => $proof], 201);
    }
}
