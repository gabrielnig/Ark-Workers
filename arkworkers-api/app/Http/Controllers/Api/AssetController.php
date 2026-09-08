<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assets = Asset::query()
            ->with('space')
            ->get()
            ->filter(fn (Asset $asset) => $request->user()->can('view', $asset))
            ->values();

        return response()->json(['data' => $assets]);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json(['data' => $asset]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Asset::class);

        $data = Validator::make($request->all(), [
            'asset_type_id' => ['required', 'exists:asset_types,id'],
            'space_id' => ['required', 'exists:spaces,id'],
            'name' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        // AssetPolicy::create() only checks role, since there is no
        // Asset instance yet, this checks the target space itself, so
        // an Asset can never be created into a restricted space the
        // requester cannot see.
        $space = Space::findOrFail($data['space_id']);
        $this->authorize('view', $space);

        $asset = Asset::create($data);

        return response()->json(['data' => $asset], 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $asset->update($data);

        return response()->json(['data' => $asset]);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        // Soft delete, not a hard wipe. Decommissioned assets and their
        // routine/task/proof history stay intact and queryable for 30
        // days before a scheduled prune permanently removes them, so
        // deleting an asset (or a staff member's tasks along with it)
        // never means instantly losing data.
        $asset->decommission();

        return response()->json(status: 204);
    }
}
