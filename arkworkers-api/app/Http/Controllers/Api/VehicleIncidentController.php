<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleIncidentController extends Controller
{
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $incidents = $vehicle->incidents()
            ->with(['reportedByUser', 'photos'])
            ->latest('reported_at')
            ->get();

        return response()->json(['data' => $incidents]);
    }

    public function show(Request $request, VehicleIncident $vehicleIncident): JsonResponse
    {
        $this->authorize('view', $vehicleIncident);

        $vehicleIncident->load(['reportedByUser', 'photos']);

        return response()->json(['data' => $vehicleIncident]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('create', [VehicleIncident::class, $vehicle]);

        $data = Validator::make($request->all(), [
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ])->validate();

        $incident = $vehicle->incidents()->create([
            ...$data,
            'reported_by_user_id' => $request->user()->id,
            'status' => VehicleIncident::STATUS_REPORTED,
            'reported_at' => now(),
        ]);

        return response()->json(['data' => $incident->load('reportedByUser')], 201);
    }

    /**
     * Manager-level: mechanic, parts, cost, and status. A status of
     * "completed" stamps resolved_at automatically, callers never set
     * that timestamp directly.
     */
    public function update(Request $request, VehicleIncident $vehicleIncident): JsonResponse
    {
        $this->authorize('update', $vehicleIncident);

        $data = Validator::make($request->all(), [
            'status' => ['sometimes', 'string', 'in:'.implode(',', VehicleIncident::STATUSES)],
            'mechanic_name' => ['sometimes', 'nullable', 'string'],
            'parts_used' => ['sometimes', 'nullable', 'string'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ])->validate();

        if (($data['status'] ?? null) === VehicleIncident::STATUS_COMPLETED) {
            $data['resolved_at'] = now();
        }

        $vehicleIncident->update($data);

        return response()->json(['data' => $vehicleIncident->fresh(['reportedByUser', 'photos'])]);
    }
}
