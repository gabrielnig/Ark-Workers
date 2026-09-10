<?php

namespace Tests\Feature;

use App\Models\AccountRequest;
use App\Models\Department;
use App\Models\User;
use App\Notifications\AccountRequestApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_worker_can_submit_a_request_with_multiple_departments(): void
    {
        $cleaning = Department::factory()->create();
        $choir = Department::factory()->create();

        $this->postJson('/api/account-requests', [
            'name' => 'Chidinma Okafor',
            'email' => 'chidinma@example.com',
            'phone' => '+2348035550142',
            'department_ids' => [$cleaning->id, $choir->id],
        ])->assertCreated();

        $accountRequest = AccountRequest::where('email', 'chidinma@example.com')->first();
        $this->assertNotNull($accountRequest);
        $this->assertTrue($accountRequest->isPending());
        $this->assertCount(2, $accountRequest->departments);
    }

    public function test_a_worker_can_submit_a_display_name_and_ministry_office(): void
    {
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', [
            'name' => 'Chidinma Okafor',
            'display_name' => 'Sister Chi',
            'title' => 'Evangelist',
            'email' => 'chidinma@example.com',
            'department_ids' => [$department->id],
        ])->assertCreated();

        $accountRequest = AccountRequest::where('email', 'chidinma@example.com')->first();
        $this->assertSame('Sister Chi', $accountRequest->display_name);
        $this->assertSame('Evangelist', $accountRequest->title);
    }

    public function test_display_name_and_title_are_optional(): void
    {
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', [
            'name' => 'Chidinma Okafor',
            'email' => 'chidinma@example.com',
            'department_ids' => [$department->id],
        ])->assertCreated();

        $accountRequest = AccountRequest::where('email', 'chidinma@example.com')->first();
        $this->assertNull($accountRequest->display_name);
        $this->assertNull($accountRequest->title);
    }

    public function test_a_request_needs_at_least_one_department(): void
    {
        $this->postJson('/api/account-requests', [
            'name' => 'Chidinma Okafor',
            'email' => 'chidinma@example.com',
            'department_ids' => [],
        ])->assertStatus(422);
    }

    public function test_cannot_request_with_an_email_that_already_has_an_account(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $department = Department::factory()->create();

        $this->postJson('/api/account-requests', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'department_ids' => [$department->id],
        ])->assertStatus(422);
    }

    public function test_cannot_submit_a_second_request_while_one_is_still_pending(): void
    {
        $department = Department::factory()->create();
        AccountRequest::factory()->create(['email' => 'pending@example.com']);

        $this->postJson('/api/account-requests', [
            'name' => 'Someone',
            'email' => 'pending@example.com',
            'department_ids' => [$department->id],
        ])->assertStatus(422);
    }

    public function test_can_resubmit_after_a_previous_request_was_rejected(): void
    {
        $department = Department::factory()->create();
        AccountRequest::factory()->rejected()->create(['email' => 'retry@example.com']);

        $this->postJson('/api/account-requests', [
            'name' => 'Someone',
            'email' => 'retry@example.com',
            'department_ids' => [$department->id],
        ])->assertCreated();
    }

    public function test_only_admin_can_list_pending_requests(): void
    {
        AccountRequest::factory()->create();

        $this->actingAs($this->staffUser(), 'sanctum')
            ->getJson('/api/account-requests')
            ->assertForbidden();

        $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/account-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pending_list_excludes_already_reviewed_requests(): void
    {
        AccountRequest::factory()->create();
        AccountRequest::factory()->approved()->create();
        AccountRequest::factory()->rejected()->create();

        $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/account-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_approve_a_request_and_it_sends_an_invite(): void
    {
        Notification::fake();

        $accountRequest = AccountRequest::factory()->create(['email' => 'applicant@example.com']);

        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson("/api/account-requests/{$accountRequest->id}/approve")
            ->assertOk();

        $accountRequest->refresh();
        $this->assertSame(AccountRequest::STATUS_APPROVED, $accountRequest->status);
        $this->assertNotNull($accountRequest->invite_token_hash);
        $this->assertNotNull($accountRequest->invite_expires_at);

        Notification::assertSentOnDemand(
            AccountRequestApproved::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'applicant@example.com'
        );
    }

    public function test_only_admin_can_approve_a_request(): void
    {
        $accountRequest = AccountRequest::factory()->create();

        $this->actingAs($this->managerUser(), 'sanctum')
            ->postJson("/api/account-requests/{$accountRequest->id}/approve")
            ->assertForbidden();
    }

    public function test_cannot_approve_an_already_reviewed_request(): void
    {
        $accountRequest = AccountRequest::factory()->approved()->create();

        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson("/api/account-requests/{$accountRequest->id}/approve")
            ->assertStatus(422);
    }

    public function test_admin_can_reject_a_request_and_no_account_is_ever_created(): void
    {
        $accountRequest = AccountRequest::factory()->create();

        $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson("/api/account-requests/{$accountRequest->id}/reject")
            ->assertOk();

        $accountRequest->refresh();
        $this->assertSame(AccountRequest::STATUS_REJECTED, $accountRequest->status);
        $this->assertNull($accountRequest->invite_token_hash);
        $this->assertDatabaseMissing('users', ['email' => $accountRequest->email]);
    }

    public function test_only_admin_can_reject_a_request(): void
    {
        $accountRequest = AccountRequest::factory()->create();

        $this->actingAs($this->staffUser(), 'sanctum')
            ->postJson("/api/account-requests/{$accountRequest->id}/reject")
            ->assertForbidden();
    }

    public function test_submitting_more_than_five_requests_a_minute_from_one_source_is_throttled(): void
    {
        $department = Department::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/account-requests', [
                'name' => 'Someone',
                'email' => "someone{$i}@example.com",
                'department_ids' => [$department->id],
            ])->assertCreated();
        }

        $this->postJson('/api/account-requests', [
            'name' => 'One Too Many',
            'email' => 'onetoomany@example.com',
            'department_ids' => [$department->id],
        ])->assertStatus(429);
    }
}
