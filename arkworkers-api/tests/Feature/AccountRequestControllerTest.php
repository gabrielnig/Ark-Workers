<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SignUpApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AccountRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('*');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Chidinma Okafor',
            'email' => 'chidinma@example.com',
            'phone' => '+2348035550142',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ], $overrides);
    }

    public function test_a_worker_can_sign_up_with_a_password_and_multiple_departments(): void
    {
        $cleaning = Department::factory()->create();
        $choir = Department::factory()->create();
        $member = Role::factory()->create(['name' => 'Member', 'grants_management' => false]);
        $cleaning->roles()->attach($member);
        $choir->roles()->attach($member);

        $this->postJson('/api/account-requests', $this->validPayload([
            'department_ids' => [$cleaning->id, $choir->id],
        ]))->assertCreated();

        $user = User::where('email', 'chidinma@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertCount(2, $user->departments);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
    }

    public function test_a_worker_can_submit_a_display_name_and_ministry_office(): void
    {
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', $this->validPayload([
            'display_name' => 'Sister Chi',
            'title' => 'Evangelist',
            'department_ids' => [$department->id],
        ]))->assertCreated();

        $user = User::where('email', 'chidinma@example.com')->first();
        $this->assertSame('Sister Chi', $user->display_name);
        $this->assertSame('Evangelist', $user->title);
    }

    public function test_display_name_and_title_are_optional_but_phone_and_password_are_not(): void
    {
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', $this->validPayload([
            'department_ids' => [$department->id],
        ]))->assertCreated();

        $user = User::where('email', 'chidinma@example.com')->first();
        $this->assertNull($user->display_name);
        $this->assertNull($user->title);
    }

    public function test_a_request_needs_a_phone_number(): void
    {
        $department = Department::factory()->create();
        $payload = $this->validPayload(['department_ids' => [$department->id]]);
        unset($payload['phone']);

        $this->postJson('/api/account-requests', $payload)->assertStatus(422);
    }

    public function test_signup_needs_a_password_of_at_least_twelve_characters(): void
    {
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', $this->validPayload([
            'department_ids' => [$department->id],
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertStatus(422);
    }

    public function test_signup_needs_password_confirmation_to_match(): void
    {
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', $this->validPayload([
            'department_ids' => [$department->id],
            'password_confirmation' => 'a-different-password',
        ]))->assertStatus(422);
    }

    public function test_a_request_needs_at_least_one_department(): void
    {
        $this->postJson('/api/account-requests', $this->validPayload([
            'department_ids' => [],
        ]))->assertStatus(422);
    }

    public function test_cannot_sign_up_with_an_email_that_already_has_an_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', $this->validPayload([
            'email' => 'taken@example.com',
            'department_ids' => [$department->id],
        ]))->assertStatus(422);
    }

    public function test_a_newly_signed_up_user_cannot_log_in_before_approval(): void
    {
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', $this->validPayload([
            'department_ids' => [$department->id],
        ]))->assertCreated();

        $this->postJson('/api/auth/login', [
            'email' => 'chidinma@example.com',
            'password' => 'a-strong-password',
        ])->assertStatus(403);
    }

    public function test_only_admin_can_list_pending_signups(): void
    {
        User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($this->staffUser(), 'sanctum')
            ->getJson('/api/account-requests')
            ->assertForbidden();

        $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/account-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pending_list_excludes_already_approved_users(): void
    {
        User::factory()->create(['email_verified_at' => null]);
        User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/account-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_approve_a_pending_signup_and_it_can_then_log_in(): void
    {
        Notification::fake();

        $pending = User::factory()->create([
            'email' => 'applicant@example.com',
            'email_verified_at' => null,
        ]);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson("/api/account-requests/{$pending->id}/approve")
            ->assertOk();

        $pending->refresh();
        $this->assertNotNull($pending->email_verified_at);

        Notification::assertSentTo($pending, SignUpApproved::class);
    }

    public function test_only_admin_can_approve_a_signup(): void
    {
        $pending = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($this->managerUser(), 'sanctum')
            ->postJson("/api/account-requests/{$pending->id}/approve")
            ->assertForbidden();
    }

    public function test_cannot_approve_an_already_approved_user(): void
    {
        $alreadyApproved = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson("/api/account-requests/{$alreadyApproved->id}/approve")
            ->assertStatus(422);
    }

    public function test_admin_can_reject_a_pending_signup_and_the_account_is_deleted(): void
    {
        $pending = User::factory()->create([
            'email' => 'applicant@example.com',
            'email_verified_at' => null,
        ]);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson("/api/account-requests/{$pending->id}/reject")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'applicant@example.com']);
    }

    public function test_only_admin_can_reject_a_signup(): void
    {
        $pending = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($this->staffUser(), 'sanctum')
            ->postJson("/api/account-requests/{$pending->id}/reject")
            ->assertForbidden();
    }

    public function test_submitting_more_than_five_signups_a_minute_from_one_source_is_throttled(): void
    {
        $department = Department::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/account-requests', $this->validPayload([
                'email' => "someone{$i}@example.com",
                'department_ids' => [$department->id],
            ]))->assertCreated();
        }

        $this->postJson('/api/account-requests', $this->validPayload([
            'email' => 'onetoomany@example.com',
            'department_ids' => [$department->id],
        ]))->assertStatus(429);
    }
}
