<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Notifications\SignUpApproved;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 2026-09-15 redesign: a worker sets their own email and password at
 * sign-up time (no separate invite-link step), the User row is
 * created immediately, but User::email_verified_at stays null until
 * an Admin approves, and AuthController::login blocks any login
 * while it's null. Approving is nothing more than lifting that block,
 * there is no token to generate or link to email. This sidesteps
 * needing a working mailer for the approval step itself, only the
 * "you're approved" notice still goes through mail, and that's
 * informational, not required to actually log in.
 *
 * The older AccountRequest + invite-token flow (AccountRequestApproved,
 * InviteController) still exists untouched and still works, it's just
 * no longer wired to the public sign-up form. Left in place rather
 * than removed, see BUILD-PLAN.md.
 */
class AccountRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'display_name' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ])->validate();

        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'An account with this email already exists.',
            ], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? null,
            'title' => $data['title'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
        ]);

        $this->joinRequestedDepartmentsAsMember($user, $data['department_ids']);

        return response()->json([
            'message' => 'Account created. An admin will review it before you can log in.',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $pending = User::with('departments')
            ->whereNull('email_verified_at')
            ->latest()
            ->get();

        return response()->json(['data' => $pending]);
    }

    public function approve(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($user->email_verified_at) {
            return response()->json(['message' => 'This account has already been reviewed.'], 422);
        }

        // Not mass-fillable on purpose, set explicitly here rather
        // than widen $fillable app-wide, see User::casts().
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->notify(new SignUpApproved($user));

        return response()->json(['data' => $user->fresh()]);
    }

    public function reject(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($user->email_verified_at) {
            return response()->json(['message' => 'This account has already been reviewed.'], 422);
        }

        // Rejected means no account exists, not a disabled one, so the
        // row is actually deleted (department_user rows cascade).
        $user->delete();

        return response()->json(['message' => 'Request rejected.']);
    }

    /**
     * The sign-up form only collects which departments an applicant
     * wants, not a role within each, so every requested department
     * gets the base Member role, an admin or department lead can
     * promote from there. A department that has no Member role
     * available (an admin removed it) is skipped rather than failing
     * the whole sign-up.
     */
    private function joinRequestedDepartmentsAsMember(User $user, array $departmentIds): void
    {
        Department::whereIn('id', $departmentIds)->get()->each(function (Department $department) use ($user) {
            $memberRole = $department->roles()->where('name', 'Member')->first();

            if ($memberRole) {
                $user->joinDepartment($department, $memberRole);
            }
        });
    }
}
