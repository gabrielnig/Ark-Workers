<?php

namespace Tests\Feature;

use App\Models\AccountRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class InviteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('*');
    }

    private function approvedRequestWithToken(array $overrides = []): array
    {
        $token = Str::random(64);

        $accountRequest = AccountRequest::factory()->approved()->create(array_merge([
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => now()->addDays(7),
        ], $overrides));

        return [$accountRequest, $token];
    }

    public function test_show_returns_the_applicants_details_for_a_valid_invite(): void
    {
        $department = Department::factory()->create(['name' => 'Cleaning']);
        [$accountRequest, $token] = $this->approvedRequestWithToken(['name' => 'Chidinma Okafor']);
        $accountRequest->departments()->attach($department);

        $this->getJson("/api/invites/{$token}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Chidinma Okafor',
                    'email' => $accountRequest->email,
                    'departments' => ['Cleaning'],
                ],
            ]);
    }

    public function test_show_404s_for_an_unknown_token(): void
    {
        $this->getJson('/api/invites/'.Str::random(64))->assertStatus(404);
    }

    public function test_show_404s_for_an_expired_invite(): void
    {
        [, $token] = $this->approvedRequestWithToken(['invite_expires_at' => now()->subDay()]);

        $this->getJson("/api/invites/{$token}")->assertStatus(404);
    }

    public function test_show_404s_for_a_pending_request_with_no_approval_yet(): void
    {
        $accountRequest = AccountRequest::factory()->create();

        $this->getJson('/api/invites/'.Str::random(64))->assertStatus(404);
        $this->assertTrue($accountRequest->fresh()->isPending());
    }

    public function test_activating_creates_the_user_and_joins_requested_departments_as_member(): void
    {
        $department = Department::factory()->create();
        $member = Role::factory()->create(['name' => 'Member', 'grants_management' => false]);
        $department->roles()->attach($member);

        [$accountRequest, $token] = $this->approvedRequestWithToken([
            'name' => 'Chidinma Okafor',
            'email' => 'chidinma@example.com',
        ]);
        $accountRequest->departments()->attach($department);

        $this->postJson("/api/invites/{$token}/activate", [
            'password' => 'a-strong-password',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'chidinma@example.com']);
        $user = User::where('email', 'chidinma@example.com')->first();
        $this->assertNotNull($user->email_verified_at);

        $membership = $user->departments()->first();
        $this->assertSame($department->id, $membership->id);
        $this->assertSame($member->id, $membership->pivot->role_id);
    }

    public function test_activation_marks_the_request_consumed_and_links_the_created_user(): void
    {
        $department = Department::factory()->create();
        [$accountRequest, $token] = $this->approvedRequestWithToken();
        $accountRequest->departments()->attach($department);

        $this->postJson("/api/invites/{$token}/activate", [
            'password' => 'a-strong-password',
        ])->assertOk();

        $accountRequest->refresh();
        $this->assertNotNull($accountRequest->consumed_at);
        $this->assertNotNull($accountRequest->created_user_id);
    }

    public function test_the_same_invite_token_cannot_be_used_twice(): void
    {
        $department = Department::factory()->create();
        [$accountRequest, $token] = $this->approvedRequestWithToken();
        $accountRequest->departments()->attach($department);

        $this->postJson("/api/invites/{$token}/activate", ['password' => 'a-strong-password'])
            ->assertOk();

        $this->postJson("/api/invites/{$token}/activate", ['password' => 'a-different-password'])
            ->assertStatus(404);
    }

    public function test_activation_requires_a_password_of_minimum_length(): void
    {
        $department = Department::factory()->create();
        [$accountRequest, $token] = $this->approvedRequestWithToken();
        $accountRequest->departments()->attach($department);

        $this->postJson("/api/invites/{$token}/activate", ['password' => 'short'])
            ->assertStatus(422);
    }

    public function test_a_department_missing_the_member_role_is_skipped_without_failing_activation(): void
    {
        // A department where an admin removed the Member role entirely,
        // activation should still succeed for the account itself.
        $department = Department::factory()->create();
        [$accountRequest, $token] = $this->approvedRequestWithToken();
        $accountRequest->departments()->attach($department);

        $this->postJson("/api/invites/{$token}/activate", ['password' => 'a-strong-password'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['email' => $accountRequest->email]);
    }
}
