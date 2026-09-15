<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleDocumentsNeedAttention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VehicleDocumentExpiryDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_every_admin_when_a_document_is_expired_or_expiring_soon(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        Vehicle::factory()->create([
            'document_expiry' => ['insurance' => now()->subDay()->toDateString()],
        ]);
        Vehicle::factory()->create([
            'document_expiry' => ['license' => now()->addYear()->toDateString()],
        ]);

        $this->artisan('vehicles:document-expiry-digest')->assertSuccessful();

        Notification::assertSentTo($admin, VehicleDocumentsNeedAttention::class);
    }

    public function test_sends_nothing_when_no_document_is_expired_or_expiring_soon(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        Vehicle::factory()->create([
            'document_expiry' => ['insurance' => now()->addYear()->toDateString()],
        ]);

        $this->artisan('vehicles:document-expiry-digest')->assertSuccessful();

        Notification::assertNothingSentTo($admin);
    }

    public function test_a_manager_who_is_not_admin_is_not_notified(): void
    {
        Notification::fake();

        $manager = $this->managerUser();
        Vehicle::factory()->create([
            'document_expiry' => ['insurance' => now()->subDay()->toDateString()],
        ]);

        $this->artisan('vehicles:document-expiry-digest')->assertSuccessful();

        Notification::assertNothingSentTo($manager);
    }
}
