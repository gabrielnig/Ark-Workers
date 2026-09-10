<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Grant management is admin-only, not just anyone with management
 * permission, matching User::bypassesSpaceRestrictions(). A manager
 * without a grant can't see a restricted space at all, so letting
 * them grant access to it would mean granting access to something
 * they themselves can't confirm exists correctly. Only Admin, the
 * same tier that already sees every restricted space unconditionally,
 * manages the exception mechanism.
 */
class SpaceAccessGrantController extends Controller
{
    public function index(Request $request, Space $space): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        // Built explicitly rather than relying on default Eloquent
        // serialization: SpaceAccessGrant::grantedBy() snake-cases to
        // "granted_by", identical to the actual granted_by FK column,
        // so a raw ->load('grantedBy') silently overwrites the real
        // column value with the nested user object in the JSON output.
        $grants = $space->accessGrants()
            ->with(['user:id,name,email', 'grantedBy:id,name'])
            ->get()
            ->map(fn ($grant) => $this->formatGrant($grant));

        return response()->json(['data' => $grants]);
    }

    public function store(Request $request, Space $space): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $data = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        // The unique(user_id, space_id) DB constraint is the real
        // guard against a duplicate grant, this check exists only to
        // return a clean 409 instead of a raw constraint-violation
        // 500 if someone double-submits.
        if ($space->hasGrantFor(User::find($data['user_id']))) {
            return response()->json(['message' => 'This person already has access to this space.'], 409);
        }

        $grant = SpaceAccessGrant::create([
            'user_id' => $data['user_id'],
            'space_id' => $space->id,
            'granted_by' => $request->user()->id,
        ]);

        $grant->load(['user:id,name,email', 'grantedBy:id,name']);

        return response()->json(['data' => $this->formatGrant($grant)], 201);
    }

    public function destroy(Request $request, SpaceAccessGrant $accessGrant): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $accessGrant->delete();

        return response()->json(status: 204);
    }

    private function formatGrant(SpaceAccessGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'user_id' => $grant->user_id,
            'space_id' => $grant->space_id,
            'granted_by' => $grant->granted_by,
            'granted_at' => $grant->granted_at,
            'user' => $grant->user ? [
                'id' => $grant->user->id,
                'name' => $grant->user->name,
                'email' => $grant->user->email,
            ] : null,
            'granted_by_name' => $grant->grantedBy?->name,
        ];
    }
}
