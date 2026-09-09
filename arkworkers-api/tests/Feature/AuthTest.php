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
        config(['sanctum.stateful' => ['arkworkers.test']]);
    }

    /**
     * Carries the session cookie from a login response into the next
     * request, the way a real browser would. Test calls don't persist
     * cookies across requests by themselves.
     */
    private function withSessionCookieFrom($response): static
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        return $this->withCookie($cookie->getName(), $cookie->getValue());
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

    public function test_a_stateful_login_without_a_valid_csrf_token_is_rejected(): void
    {
        // Laravel's CSRF middleware auto-bypasses itself whenever
        // app.env is "testing", which is always true here, so the
        // real check has to be forced on for this one assertion or
        // this test could never fail for the right reason.
        $this->app['env'] = 'production';

        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        $csrf = $this->withHeader('Origin', 'https://arkworkers.test')->getJson('/sanctum/csrf-cookie');

        $this->withSessionCookieFrom($csrf)
            ->withHeader('Origin', 'https://arkworkers.test')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'a-strong-password',
            ])
            ->assertStatus(419);
    }

    public function test_a_stateful_login_with_the_wrong_csrf_token_is_rejected(): void
    {
        $this->app['env'] = 'production';

        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        $csrf = $this->withHeader('Origin', 'https://arkworkers.test')->getJson('/sanctum/csrf-cookie');

        $this->withSessionCookieFrom($csrf)
            ->withHeader('Origin', 'https://arkworkers.test')
            ->withHeader('X-XSRF-TOKEN', 'not-the-real-token')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'a-strong-password',
            ])
            ->assertStatus(419);
    }

    public function test_a_browser_request_from_the_stateful_domain_gets_a_session_not_a_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        $csrf = $this->withHeader('Origin', 'https://arkworkers.test')
            ->getJson('/sanctum/csrf-cookie');

        $xsrfToken = urldecode(
            collect($csrf->headers->getCookies())
                ->first(fn ($c) => $c->getName() === 'XSRF-TOKEN')
                ->getValue()
        );

        $response = $this->withSessionCookieFrom($csrf)
            ->withHeader('Origin', 'https://arkworkers.test')
            ->withHeader('X-XSRF-TOKEN', $xsrfToken)
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'a-strong-password',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['user'])
            ->assertJsonMissing(['token' => null])
            ->assertJsonMissingPath('token');

        $this->withSessionCookieFrom($response)
            ->withHeader('Origin', 'https://arkworkers.test')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_a_request_with_no_matching_frontend_origin_still_gets_a_bearer_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        // No Origin/Referer header at all, the Android/Capacitor case
        // and any plain API client.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'a-strong-password',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_logout_ends_the_session_for_a_cookie_authenticated_user(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-strong-password')]);

        $csrf = $this->withHeader('Origin', 'https://arkworkers.test')->getJson('/sanctum/csrf-cookie');
        $xsrfToken = urldecode(
            collect($csrf->headers->getCookies())->first(fn ($c) => $c->getName() === 'XSRF-TOKEN')->getValue()
        );

        $login = $this->withSessionCookieFrom($csrf)
            ->withHeader('Origin', 'https://arkworkers.test')
            ->withHeader('X-XSRF-TOKEN', $xsrfToken)
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'a-strong-password']);

        $this->withSessionCookieFrom($login)
            ->withHeader('Origin', 'https://arkworkers.test')
            ->withHeader('X-XSRF-TOKEN', $xsrfToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        // Same guard-instance caching quirk as the token logout test
        // below, forces the session guard to re-resolve against the
        // now-destroyed session on the next call.
        auth()->forgetGuards();

        $this->withSessionCookieFrom($login)
            ->withHeader('Origin', 'https://arkworkers.test')
            ->getJson('/api/user')
            ->assertUnauthorized();
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

    public function test_an_unauthenticated_request_with_no_accept_header_gets_a_clean_401_not_a_crash(): void
    {
        // Deliberately using the raw get() helper, not getJson(),
        // since getJson() always sets Accept: application/json itself
        // and would mask this exact bug. A real client that omits
        // that header must still get a clean 401, this app has no
        // login route to redirect a guest to.
        $this->get('/api/user')->assertUnauthorized();
    }
}
