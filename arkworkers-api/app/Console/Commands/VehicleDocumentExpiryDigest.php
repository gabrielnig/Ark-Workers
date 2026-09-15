<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleDocumentsNeedAttention;
use Illuminate\Console\Command;

/**
 * PRD.md §5's "document expiry tracking + alerting" (BUILD-PLAN.md
 * Phase 3). The Vehicles screen already computes and shows this live
 * on every page load (Vehicle::expiredDocuments() /
 * expiringSoonDocuments()), this command is the second, independent
 * half: a periodic email digest so an Admin finds out without having
 * to remember to go check the screen. Delivery depends on the mailer
 * actually being finished (BUILD-PLAN.md Phase 0), this command still
 * runs and logs correctly either way, it just won't reach anyone by
 * email until that's done.
 */
class VehicleDocumentExpiryDigest extends Command
{
    protected $signature = 'vehicles:document-expiry-digest';

    protected $description = "Email Admins a digest of any vehicle document that's expired or expiring soon";

    public function handle(): int
    {
        $flagged = Vehicle::all()->filter(
            fn (Vehicle $vehicle) => $vehicle->expiredDocuments() !== [] || $vehicle->expiringSoonDocuments() !== []
        );

        if ($flagged->isEmpty()) {
            $this->info('No vehicle documents expired or expiring soon.');

            return self::SUCCESS;
        }

        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            $admin->notify(new VehicleDocumentsNeedAttention($flagged));
        }

        $this->info("Notified {$admins->count()} admin(s) about {$flagged->count()} vehicle(s) needing attention.");

        return self::SUCCESS;
    }
}
