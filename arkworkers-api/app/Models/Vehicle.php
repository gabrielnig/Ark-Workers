<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'plate_number', 'assigned_driver_id', 'document_expiry'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * The four document types PRD.md §5 requires tracking. Kept as a
     * plain array constant, not a DB-backed lookup, the set is small
     * and fixed, unlike Departments/Roles/AssetTypes which are
     * genuinely admin-extensible.
     */
    public const DOCUMENT_TYPES = ['insurance', 'roadworthiness', 'license', 'registration'];

    /**
     * A document expiring within this many days counts as "expiring
     * soon", not just already-expired. Matches the scheduled digest
     * command's own threshold, see VehicleDocumentExpiryCheck.
     */
    public const EXPIRY_WARNING_DAYS = 30;

    protected function casts(): array
    {
        return [
            'document_expiry' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }

    /**
     * @return HasMany<VehicleLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(VehicleLog::class);
    }

    /**
     * @return HasMany<VehicleIncident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(VehicleIncident::class);
    }

    /**
     * Document types with no expiry date recorded at all don't count
     * as expired or expiring, that's a data-completeness gap, not a
     * compliance one, the UI surfaces those separately.
     */
    public function expiredDocuments(): array
    {
        return $this->documentsMatching(fn (Carbon $date) => $date->isPast());
    }

    public function expiringSoonDocuments(): array
    {
        $cutoff = now()->addDays(self::EXPIRY_WARNING_DAYS);

        return $this->documentsMatching(fn (Carbon $date) => $date->isFuture() && $date->lte($cutoff));
    }

    private function documentsMatching(callable $predicate): array
    {
        $expiry = $this->document_expiry ?? [];
        $matches = [];

        foreach (self::DOCUMENT_TYPES as $type) {
            if (! isset($expiry[$type])) {
                continue;
            }

            $date = Carbon::parse($expiry[$type]);

            if ($predicate($date)) {
                $matches[$type] = $date->toDateString();
            }
        }

        return $matches;
    }
}
