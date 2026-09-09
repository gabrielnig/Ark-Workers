<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\TransientToken;

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

        /**
         * The PWA and any other browser-based frontend on a stateful
         * domain get a session cookie, not a token in the response
         * body, per SECURITY.md §6.3 (no bearer credential accessible
         * to JS). Capacitor's Android WebView runs on a different
         * origin than the stateful domain list, so it never matches
         * here and falls through to the token path below without
         * needing an explicit "is this mobile" flag.
         */
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return response()->json(['user' => $user]);
        }

        return response()->json([
            'token' => $user->createToken('login')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof TransientToken) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    private function throttleKey(string $prefix, string $identifier, ?string $ip): string
    {
        return "{$prefix}:{$identifier}:{$ip}";
    }
}
