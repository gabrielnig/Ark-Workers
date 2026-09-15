<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BUILD-PLAN.md Phase 4's staff directory, the "view every active
 * worker" screen UserController's own docblock said would eventually
 * need to exist separately from the capped 20-result assignee/grant
 * picker. Only approved workers show here (email_verified_at not
 * null), someone still pending admin approval belongs on the Admin
 * requests screen, not this one.
 */
class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasManagementPermission(), 403);

        $users = User::query()
            ->whereNotNull('email_verified_at')
            ->with('departments')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users->map($this->present(...))]);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'display_name' => $user->display_name,
            'title' => $user->title,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_admin' => $user->is_admin,
            'departments' => $user->departments->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'role' => $department->pivot->role?->name,
            ]),
        ];
    }
}
