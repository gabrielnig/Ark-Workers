<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Asset types are not a fixed list, this is how a new kind of asset
 * (a pool, a fan, a generator) gets added at any time, with no code
 * change. Not space-scoped: a type definition is not restricted data,
 * only the concrete Assets built from it are. So the only check here
 * is role, the same privileged roles that manage Spaces and Assets.
 */
class AssetTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => AssetType::all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManages($request);

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'category' => ['nullable', 'string'],
            'default_routine_template' => ['nullable', 'array'],
        ])->validate();

        $assetType = AssetType::create($data);

        return response()->json(['data' => $assetType], 201);
    }

    public function update(Request $request, AssetType $assetType): JsonResponse
    {
        $this->authorizeManages($request);

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string'],
            'category' => ['nullable', 'string'],
            'default_routine_template' => ['nullable', 'array'],
        ])->validate();

        $assetType->update($data);

        return response()->json(['data' => $assetType]);
    }

    private function authorizeManages(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole([User::ROLE_ADMIN, User::ROLE_PASTOR, User::ROLE_FACILITY_MANAGER]),
            403
        );
    }
}
