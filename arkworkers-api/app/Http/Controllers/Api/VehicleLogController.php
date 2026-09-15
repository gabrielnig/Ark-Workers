<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleLogController extends Controller
{
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $logs = $vehicle->logs()->with('loggedByUser')->latest('logged_at')->get();

        return response()->json(['data' => $logs]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('create', [VehicleLog::class, $vehicle]);

        $data = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:'.implode(',', VehicleLog::TYPES)],
            'value' => ['required', 'numeric', 'min:0'],
            'logged_at' => ['nullable', 'date'],
        ])->validate();

        $log = $vehicle->logs()->create([
            ...$data,
            'logged_by_user_id' => $request->user()->id,
            'logged_at' => $data['logged_at'] ?? now(),
        ]);

        return response()->json(['data' => $log->load('loggedByUser')], 201);
    }

    public function destroy(Request $request, VehicleLog $vehicleLog): JsonResponse
    {
        $this->authorize('delete', $vehicleLog);

        $vehicleLog->delete();

        return response()->json(status: 204);
    }
}
