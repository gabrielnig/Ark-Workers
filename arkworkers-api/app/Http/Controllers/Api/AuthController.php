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
 * PIN and OTP login, per SECURITY.md §3.1. No password auth: staff are
 * low-tech-literacy, so a PIN (device-bound session) or phone+OTP is
 * the login path, not a complex password.
 */
class AuthController extends Controller
{
    /**
     * Max login attempts before the account/IP pair is locked out, per
     * SECURITY.md §3.1.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Lockout window in seconds (15 minutes), per SECURITY.md §3.1.
     */
    private const DECAY_SECONDS = 900;

    public function loginWithPin(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'pin' => ['required', 'string'],
        ])->validate();

        $key = $this->throttleKey('pin', $data['phone'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json([
                'message' => 'Too many attempts. Try again later.',
            ], 429);
        }

        $user = User::where('phone', $data['phone'])->first();

        if (! $user || ! $user->pin_hash || ! Hash::check($data['pin'], $user->pin_hash)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'Invalid phone or PIN.'], 401);
        }

        RateLimiter::clear($key);

        return response()->json([
            'token' => $user->createToken('pin-login')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
        ])->validate();

        $key = $this->throttleKey('otp-request', $data['phone'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json([
                'message' => 'Too many attempts. Try again later.',
            ], 429);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        $user = User::where('phone', $data['phone'])->first();

        // Same response whether or not the phone exists, so the
        // endpoint cannot be used to enumerate registered numbers.
        if ($user) {
            $code = (string) random_int(100000, 999999);

            OtpCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(5),
            ]);

            // Sending the code (SMS gateway) is deferred to Phase 2
            // infra setup; logged here for local/sandbox testing only.
            logger()->info("OTP for {$user->phone}: {$code}");
        }

        return response()->json(['message' => 'If that number is registered, a code has been sent.']);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ])->validate();

        $key = $this->throttleKey('otp', $data['phone'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json([
                'message' => 'Too many attempts. Try again later.',
            ], 429);
        }

        $user = User::where('phone', $data['phone'])->first();
        $otp = $user
            ? $user->otpCodes()->latest()->first()
            : null;

        if (! $user || ! $otp || ! $otp->isUsable() || ! Hash::check($data['code'], $otp->code_hash)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        RateLimiter::clear($key);
        $otp->update(['consumed_at' => now()]);
        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now()])->save();

        return response()->json([
            'token' => $user->createToken('otp-login')->plainTextToken,
            'user' => $user,
        ]);
    }

    private function throttleKey(string $prefix, string $phone, ?string $ip): string
    {
        return "{$prefix}:{$phone}:{$ip}";
    }
}
