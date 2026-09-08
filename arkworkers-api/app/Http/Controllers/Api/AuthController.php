<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Email + password auth, with an email-OTP step at signup to verify
 * the address. No SMS anywhere, per the decision to avoid that cost
 * entirely. Phone is an optional profile field only, never used for
 * login or verification.
 */
class AuthController extends Controller
{
    /**
     * Max attempts before the account/IP pair is locked out, per
     * SECURITY.md §3.1.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Lockout window in seconds (15 minutes), per SECURITY.md §3.1.
     */
    private const DECAY_SECONDS = 900;

    public function register(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'phone' => ['nullable', 'string'],
            'role' => ['required', 'string', 'in:'.implode(',', User::ROLES)],
        ])->validate();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
        ]);

        $this->issueEmailOtp($user);

        return response()->json([
            'message' => 'Registered. Check your email for a verification code.',
        ], 201);
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ])->validate();

        $key = $this->throttleKey('verify-email', $data['email'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $user = User::where('email', $data['email'])->first();
        $otp = $user ? $user->otpCodes()->latest()->first() : null;

        if (! $user || ! $otp || ! $otp->isUsable() || ! Hash::check($data['code'], $otp->code_hash)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        RateLimiter::clear($key);
        $otp->update(['consumed_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json(['message' => 'Email verified. You can now log in.']);
    }

    public function login(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ])->validate();

        $key = $this->throttleKey('login', $data['email'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (! $user->email_verified_at) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'Please verify your email before logging in.'], 403);
        }

        RateLimiter::clear($key);

        return response()->json([
            'token' => $user->createToken('login')->plainTextToken,
            'user' => $user,
        ]);
    }

    private function issueEmailOtp(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Sending the actual email is deferred to Phase 2 mail setup,
        // logged here for local/sandbox testing only.
        logger()->info("Email verification code for {$user->email}: {$code}");
    }

    private function throttleKey(string $prefix, string $identifier, ?string $ip): string
    {
        return "{$prefix}:{$identifier}:{$ip}";
    }
}
