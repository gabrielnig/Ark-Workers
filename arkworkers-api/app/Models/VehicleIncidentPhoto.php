<?php

namespace App\Models;

use Database\Factories\VehicleIncidentPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_incident_id', 'stage', 'file_path', 'file_type', 'uploaded_at'])]
class VehicleIncidentPhoto extends Model
{
    /** @use HasFactory<VehicleIncidentPhotoFactory> */
    use HasFactory;

    public const STAGE_BEFORE = 'before';

    public const STAGE_AFTER = 'after';

    public const STAGES = [self::STAGE_BEFORE, self::STAGE_AFTER];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VehicleIncident, $this>
     */
    public function vehicleIncident(): BelongsTo
    {
        return $this->belongsTo(VehicleIncident::class);
    }
}
