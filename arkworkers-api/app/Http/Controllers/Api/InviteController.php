<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InviteController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $accountRequest = $this->findUsableRequest($token);

        if (! $accountRequest) {
            return response()->json(['message' => 'This invite link is invalid or has expired.'], 404);
        }

        return response()->json([
            'data' => [
                'name' => $accountRequest->name,
                'display_name' => $accountRequest->display_name,
                'title' => $accountRequest->title,
                'email' => $accountRequest->email,
                'departments' => $accountRequest->departments->pluck('name'),
            ],
        ]);
    }

    public function activate(Request $request, string $token): JsonResponse
    {
        $accountRequest = $this->findUsableRequest($token);

        if (! $accountRequest) {
            return response()->json(['message' => 'This invite link is invalid or has expired.'], 404);
        }

        $data = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:12'],
        ])->validate();

        $user = User::create([
            'name' => $accountRequest->name,
            'display_name' => $accountRequest->display_name,
            'title' => $accountRequest->title,
            'email' => $accountRequest->email,
            'phone' => $accountRequest->phone,
            'password' => $data['password'],
        ]);

        // The invite link itself is the verification, per this
        // session's decision to replace the OTP step for this flow.
        // email_verified_at is deliberately not mass-fillable, set it
        // explicitly here rather than widen $fillable app-wide.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->joinRequestedDepartmentsAsMember($user, $accountRequest);

        $accountRequest->forceFill([
            'consumed_at' => now(),
            'created_user_id' => $user->id,
        ])->save();

        return response()->json(['message' => 'Account activated. You can now log in.']);
    }

    private function findUsableRequest(string $token): ?AccountRequest
    {
        $accountRequest = AccountRequest::where('invite_token_hash', hash('sha256', $token))->first();

        if (! $accountRequest || ! $accountRequest->inviteIsUsable()) {
            return null;
        }

        return $accountRequest;
    }

    /**
     * The sign-up form only collects which departments an applicant
     * wants, not a role within each, so every requested department
     * gets the base Member role, an admin or department lead can
     * promote from there. A department that has no Member role
     * available (an admin removed it) is skipped rather than failing
     * the whole activation.
     */
    private function joinRequestedDepartmentsAsMember(User $user, AccountRequest $accountRequest): void
    {
        $accountRequest->departments->each(function (Department $department) use ($user) {
            $memberRole = $department->roles()->where('name', 'Member')->first();

            if ($memberRole) {
                $user->joinDepartment($department, $memberRole);
            }
        });
    }
}
