<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountRequest;
use App\Models\User;
use App\Notifications\AccountRequestApproved;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The admin-approval sign-up pipeline: a worker submits a request with
 * no password, an Admin approves or rejects it, and approval sends a
 * single-use invite link. Nothing here creates a login-capable User,
 * that only happens in InviteController::activate.
 */
class AccountRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ])->validate();

        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'An account with this email already exists.',
            ], 422);
        }

        if (AccountRequest::where('email', $data['email'])->where('status', AccountRequest::STATUS_PENDING)->exists()) {
            return response()->json([
                'message' => 'A request for this email is already pending review.',
            ], 422);
        }

        $accountRequest = AccountRequest::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        $accountRequest->departments()->attach($data['department_ids']);

        return response()->json([
            'message' => 'Request submitted. An admin will review it.',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountRequest::class);

        $requests = AccountRequest::with('departments')
            ->where('status', AccountRequest::STATUS_PENDING)
            ->latest()
            ->get();

        return response()->json(['data' => $requests]);
    }

    public function approve(Request $request, AccountRequest $accountRequest): JsonResponse
    {
        $this->authorize('review', $accountRequest);

        if (! $accountRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $plaintextToken = Str::random(64);

        $accountRequest->forceFill([
            'status' => AccountRequest::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'invite_token_hash' => hash('sha256', $plaintextToken),
            'invite_expires_at' => now()->addDays(7),
        ])->save();

        Notification::route('mail', $accountRequest->email)
            ->notify(new AccountRequestApproved($accountRequest, $plaintextToken));

        return response()->json(['data' => $accountRequest->fresh()]);
    }

    public function reject(Request $request, AccountRequest $accountRequest): JsonResponse
    {
        $this->authorize('review', $accountRequest);

        if (! $accountRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $accountRequest->forceFill([
            'status' => AccountRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json(['data' => $accountRequest->fresh()]);
    }
}
