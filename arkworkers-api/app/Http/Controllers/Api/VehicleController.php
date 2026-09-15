<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::with('assignedDriver')->orderBy('name')->get();

        return response()->json(['data' => $vehicles->map($this->present(...))]);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $vehicle->load('assignedDriver');

        return response()->json(['data' => $this->present($vehicle)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $data = $this->validated($request);

        $vehicle = Vehicle::create($data);

        return response()->json(['data' => $this->present($vehicle)], 201);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $data = $this->validated($request, sometimes: true);

        $vehicle->update($data);

        return response()->json(['data' => $this->present($vehicle->fresh())]);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $rule = fn (array $rules) => $sometimes ? array_merge(['sometimes'], $rules) : $rules;

        return Validator::make($request->all(), [
            'name' => $rule(['nullable', 'string']),
            'plate_number' => $rule(['required', 'string', 'unique:vehicles,plate_number,'.$request->route('vehicle')?->id]),
            'assigned_driver_id' => ['nullable', 'exists:users,id'],
            'document_expiry' => ['nullable', 'array'],
            'document_expiry.*' => ['nullable', 'date'],
        ])->validate();
    }

    /**
     * Adds the computed expired/expiring-soon breakdown to every
     * response, so the frontend never has to reimplement
     * Vehicle::expiredDocuments()'s date logic itself.
     */
    private function present(Vehicle $vehicle): array
    {
        return [
            ...$vehicle->toArray(),
            'expired_documents' => $vehicle->expiredDocuments(),
            'expiring_soon_documents' => $vehicle->expiringSoonDocuments(),
        ];
    }
}
