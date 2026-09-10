<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Routine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoutineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Routine::query()->with('asset.space');

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->integer('asset_id'));
        }

        if ($request->filled('asset_type_id')) {
            $query->where('asset_type_id', $request->integer('asset_type_id'));
        }

        $routines = $query->get()
            ->filter(fn (Routine $routine) => $request->user()->can('view', $routine))
            ->values();

        return response()->json(['data' => $routines]);
    }

    public function show(Request $request, Routine $routine): JsonResponse
    {
        $this->authorize('view', $routine);

        return response()->json(['data' => $routine]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Routine::class);

        $data = Validator::make($request->all(), [
            'asset_id' => ['nullable', 'required_without:asset_type_id', 'prohibits:asset_type_id', 'exists:assets,id'],
            'asset_type_id' => ['nullable', 'required_without:asset_id', 'exists:asset_types,id'],
            'name' => ['required', 'string'],
            'calendar_interval_days' => ['nullable', 'required_without:meter_threshold', 'integer', 'min:1'],
            'meter_threshold' => ['nullable', 'required_without:calendar_interval_days', 'integer', 'min:1'],
            'requires_proof' => ['sometimes', 'boolean'],
        ])->validate();

        // Same asymmetry as AssetController::store(): RoutinePolicy::create()
        // only checks role, not space, since there is no Routine instance
        // yet to check a space against. An asset-bound routine must
        // separately confirm the requester can see the asset's space, so a
        // manager without a grant can't create routines into a restricted
        // space's asset.
        if (isset($data['asset_id'])) {
            $asset = Asset::with('space')->findOrFail($data['asset_id']);
            $this->authorize('view', $asset->space);
        }

        $routine = Routine::create($data);

        return response()->json(['data' => $routine], 201);
    }

    public function update(Request $request, Routine $routine): JsonResponse
    {
        $this->authorize('update', $routine);

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string'],
            'calendar_interval_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'meter_threshold' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'requires_proof' => ['sometimes', 'boolean'],
        ])->validate();

        // Reassigning asset_id/asset_type_id isn't supported here, on
        // purpose, it would silently change which space's restriction
        // rules apply to a routine's existing task history. Moving a
        // routine to a different asset is a delete-and-recreate, not an
        // update, until there's a real need to support it.
        $routine->update($data);

        return response()->json(['data' => $routine]);
    }

    public function destroy(Request $request, Routine $routine): JsonResponse
    {
        $this->authorize('delete', $routine);

        // Soft delete only, Routine uses SoftDeletes, not Prunable, see
        // the model. This routine stops appearing in active listings
        // and can no longer generate new tasks, but every task and proof
        // it already produced stays fully intact and permanently
        // reachable, not just for a grace period the way a decommissioned
        // Asset works. Task::routine() explicitly loads withTrashed() so
        // a task's history still shows which routine it came from.
        $routine->delete();

        return response()->json(status: 204);
    }
}
