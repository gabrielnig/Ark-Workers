<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Every action here goes through SpacePolicy, never a raw query
 * filtered only by role, per SECURITY.md §4.
 */
class SpaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Space::class);

        // Restricted spaces without a grant are excluded from the list
        // entirely, not just hidden in detail view, per SECURITY.md
        // §4.2's requirement that they not surface their existence.
        $spaces = Space::query()
            ->get()
            ->filter(fn (Space $space) => $request->user()->can('view', $space))
            ->values();

        return response()->json(['data' => $spaces]);
    }

    public function show(Request $request, Space $space): JsonResponse
    {
        $this->authorize('view', $space);

        return response()->json(['data' => $space]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Space::class);

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'parent_space_id' => ['nullable', 'exists:spaces,id'],
            'is_restricted' => ['boolean'],
        ])->validate();

        $space = Space::create($data);

        return response()->json(['data' => $space], 201);
    }

    public function update(Request $request, Space $space): JsonResponse
    {
        $this->authorize('update', $space);

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string'],
            'parent_space_id' => ['nullable', 'exists:spaces,id'],
            'is_restricted' => ['sometimes', 'boolean'],
        ])->validate();

        $space->update($data);

        return response()->json(['data' => $space]);
    }

    public function destroy(Request $request, Space $space): JsonResponse
    {
        $this->authorize('delete', $space);

        try {
            $space->delete();
        } catch (QueryException) {
            // restrictOnDelete on assets.space_id means this is a real
            // "not allowed", not a server error.
            return response()->json([
                'message' => 'This space still has assets in it. Move or remove those first.',
            ], 409);
        }

        return response()->json(status: 204);
    }
}
