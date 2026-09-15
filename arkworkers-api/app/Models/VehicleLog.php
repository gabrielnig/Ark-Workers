<?php

namespace App\Models;

use Database\Factories\VehicleLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_id', 'type', 'value', 'logged_by_user_id', 'logged_at'])]
class VehicleLog extends Model
{
    /** @use HasFactory<VehicleLogFactory> */
    use HasFactory;

    public const TYPE_FUEL = 'fuel';

    public const TYPE_MILEAGE = 'mileage';

    public const TYPE_SERVICE = 'service';

    public const TYPES = [self::TYPE_FUEL, self::TYPE_MILEAGE, self::TYPE_SERVICE];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'logged_at' => 'datetime',
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
    public function loggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}
