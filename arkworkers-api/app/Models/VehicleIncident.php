<?php

namespace App\Models;

use Database\Factories\VehicleIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reported problem with a vehicle through to its repair, not just a
 * value log like VehicleLog. Mechanic, parts used, cost, and photos
 * both of the broken part and of the completed fix, the detailed
 * repair trail PRD.md §5 implies but doesn't spell out field by
 * field, built out per Unique's explicit request 2026-09-16.
 */
#[Fillable(['vehicle_id', 'reported_by_user_id', 'title', 'description', 'status', 'mechanic_name', 'parts_used', 'cost', 'reported_at', 'resolved_at'])]
class VehicleIncident extends Model
{
    /** @use HasFactory<VehicleIncidentFactory> */
    use HasFactory;

    public const STATUS_REPORTED = 'reported';

    public const STATUS_IN_REPAIR = 'in_repair';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [self::STATUS_REPORTED, self::STATUS_IN_REPAIR, self::STATUS_COMPLETED];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * @return HasMany<VehicleIncidentPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VehicleIncidentPhoto::class);
    }
}
