<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('*');
    }

    public function test_user_can_log_in_with_a_correct_pin(): void
    {
        $user = User::factory()->create(['pin_hash' => Hash::make('123456')]);

        $response = $this->postJson('/api/auth/login-pin', [
            'phone' => $user->phone,
            'pin' => '123456',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_an_incorrect_pin(): void
    {
        $user = User::factory()->create(['pin_hash' => Hash::make('123456')]);

        $response = $this->postJson('/api/auth/login-pin', [
            'phone' => $user->phone,
            'pin' => '000000',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_for_an_unknown_phone_without_revealing_that(): void
    {
        $response = $this->postJson('/api/auth/login-pin', [
            'phone' => '+2340000000000',
            'pin' => '123456',
        ]);

        // Same 401 shape as a wrong PIN, so the endpoint cannot be used
        // to enumerate which numbers are registered.
        $response->assertStatus(401)->assertJson(['message' => 'Invalid phone or PIN.']);
    }

    public function test_login_fails_for_an_otp_only_user_with_no_pin_set(): void
    {
        $user = User::factory()->create(['pin_hash' => null]);

        $response = $this->postJson('/api/auth/login-pin', [
            'phone' => $user->phone,
            'pin' => '123456',
        ]);

        $response->assertStatus(401);
    }

    public function test_otp_request_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/otp/request', ['phone' => $user->phone])->assertOk();
        }

        $this->postJson('/api/auth/otp/request', ['phone' => $user->phone])
            ->assertStatus(429);
    }

    public function test_pin_login_locks_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['pin_hash' => Hash::make('123456')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login-pin', [
                'phone' => $user->phone,
                'pin' => 'wrong',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login-pin', [
            'phone' => $user->phone,
            'pin' => '123456',
        ])->assertStatus(429);
    }

    public function test_a_successful_login_clears_the_rate_limiter(): void
    {
        $user = User::factory()->create(['pin_hash' => Hash::make('123456')]);

        $this->postJson('/api/auth/login-pin', [
            'phone' => $user->phone,
            'pin' => 'wrong',
        ]);

        $this->postJson('/api/auth/login-pin', [
            'phone' => $user->phone,
            'pin' => '123456',
        ])->assertOk();

        // Rate limiter cleared, so a subsequent wrong attempt is not
        // immediately locked out from the earlier failure.
        $this->postJson('/api/auth/login-pin', [
            'phone' => $user->phone,
            'pin' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_otp_request_always_returns_the_same_message_whether_or_not_the_phone_is_registered(): void
    {
        $user = User::factory()->create();

        $known = $this->postJson('/api/auth/otp/request', ['phone' => $user->phone]);
        $unknown = $this->postJson('/api/auth/otp/request', ['phone' => '+2340000000001']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_otp_request_creates_a_usable_hashed_code(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/otp/request', ['phone' => $user->phone]);

        $otp = OtpCode::where('user_id', $user->id)->first();

        $this->assertNotNull($otp);
        $this->assertTrue($otp->isUsable());
        $this->assertNotEquals($otp->code_hash, '123456');
    }

    public function test_user_can_verify_a_valid_otp_and_receive_a_token(): void
    {
        $user = User::factory()->create();
        $otp = OtpCode::factory()->for($user)->create([
            'code_hash' => Hash::make('654321'),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone' => $user->phone,
            'code' => '654321',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
        $this->assertNotNull($otp->fresh()->consumed_at);
    }

    public function test_an_already_consumed_otp_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        OtpCode::factory()->for($user)->create([
            'code_hash' => Hash::make('654321'),
            'consumed_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone' => $user->phone,
            'code' => '654321',
        ]);

        $response->assertStatus(401);
    }

    public function test_an_expired_otp_cannot_be_used(): void
    {
        $user = User::factory()->create();
        OtpCode::factory()->for($user)->create([
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone' => $user->phone,
            'code' => '654321',
        ]);

        $response->assertStatus(401);
    }
}
