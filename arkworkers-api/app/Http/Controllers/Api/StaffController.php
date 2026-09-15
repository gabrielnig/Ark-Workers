<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * BUILD-PLAN.md Phase 4's staff directory, the "view every active
 * worker" screen UserController's own docblock said would eventually
 * need to exist separately from the capped 20-result assignee/grant
 * picker. Only approved workers show here (email_verified_at not
 * null), someone still pending admin approval belongs on the Admin
 * requests screen, not this one.
 *
 * Every write here (Admin toggle, department/role assignment) is
 * Admin-only, not the broader hasManagementPermission(), same
 * reasoning as AccountRequestController: who has company-wide access
 * isn't something a single department's manager should be able to
 * grant, even to themselves.
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

    /**
     * Every department with its admin-toggled available roles, for
     * the "add to department" picker. Admin-only, not the public
     * /api/departments the sign-up form uses, that one deliberately
     * never exposes roles.
     */
    public function departmentOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $departments = Department::with('roles')->orderBy('name')->get();

        return response()->json([
            'data' => $departments->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'roles' => $d->roles->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name]),
            ]),
        ]);
    }

    public function updateAdmin(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => "You can't change your own Admin status."], 422);
        }

        $data = Validator::make($request->all(), [
            'is_admin' => ['required', 'boolean'],
        ])->validate();

        $user->forceFill(['is_admin' => $data['is_admin']])->save();

        return response()->json(['data' => $this->present($user->fresh('departments'))]);
    }

    public function joinDepartment(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $data = Validator::make($request->all(), [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ])->validate();

        $department = Department::findOrFail($data['department_id']);
        $role = Role::findOrFail($data['role_id']);

        $user->joinDepartment($department, $role);

        return response()->json(['data' => $this->present($user->fresh('departments'))]);
    }

    public function leaveDepartment(Request $request, User $user, Department $department): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $user->departments()->detach($department->id);

        return response()->json(['data' => $this->present($user->fresh('departments'))]);
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
                'role_id' => $department->pivot->role_id,
            ]),
        ];
    }
}
