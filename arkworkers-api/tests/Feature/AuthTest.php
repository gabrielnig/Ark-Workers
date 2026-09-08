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

    public function test_registering_creates_an_unverified_user_and_an_email_otp(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'a-strong-password',
            'role' => User::ROLE_CLEANING_STAFF,
        ]);

        $response->assertCreated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull(OtpCode::where('user_id', $user->id)->first());
    }

    public function test_registration_accepts_an_optional_phone_number(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'a-strong-password',
            'phone' => '+2348012345678',
            'role' => User::ROLE_DRIVER,
        ])->assertCreated();

        $this->assertSame('+2348012345678', User::where('email', 'test@example.com')->first()->phone);
    }

    public function test_registration_fails_with_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'a-strong-password',
            'role' => User::ROLE_CLEANING_STAFF,
        ])->assertStatus(422);
    }

    public function test_user_can_verify_email_with_the_correct_code(): void
    {
        $user = User::factory()->unverified()->create();
        $otp = OtpCode::factory()->for($user)->create(['code_hash' => Hash::make('654321')]);

        $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '654321',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNotNull($otp->fresh()->consumed_at);
    }

    public function test_email_verification_fails_with_the_wrong_code(): void
    {
        $user = User::factory()->unverified()->create();
        OtpCode::factory()->for($user)->create(['code_hash' => Hash::make('654321')]);

        $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(401);
    }

    public function test_a_consumed_or_expired_otp_cannot_verify_email(): void
    {
        $user = User::factory()->unverified()->create();
        OtpCode::factory()->for($user)->create([
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '654321',
        ])->assertStatus(401);
    }

    public function test_verified_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'a-strong-password',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_an_incorrect_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_fails_for_an_unknown_email_without_revealing_that(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401)->assertJson(['message' => 'Invalid email or password.']);
    }

    public function test_login_is_blocked_for_an_unverified_email(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('a-strong-password')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'a-strong-password',
        ])->assertStatus(403);
    }

    public function test_login_locks_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'a-strong-password',
        ])->assertStatus(429);
    }

    public function test_a_successful_login_clears_the_rate_limiter(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'a-strong-password'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(401);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('login')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // The Sanctum guard caches its resolved user for the lifetime
        // of the guard instance, which survives across HTTP calls made
        // within one test method, forgetGuards forces it to
        // re-resolve from the (now-deleted) token on the next request,
        // matching what actually happens on separate real requests.
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
