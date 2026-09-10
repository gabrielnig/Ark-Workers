<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deliberately minimal, not the full staff directory (BUILD-PLAN.md
 * Phase 4, not built yet). This exists specifically to power the
 * restricted-space access grant picker, admin only, name/email only,
 * capped result count. If a broader staff directory need comes up
 * later this can grow into it, not the other way around.
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
