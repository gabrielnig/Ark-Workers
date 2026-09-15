<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleIncident;
use App\Models\VehicleIncidentPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Deliberately a plain synchronous upload, not the chunked/resumable
 * session ChunkedUploadController uses for Task proofs. That
 * machinery exists for large video proofs over a flaky connection in
 * the field, a handful of part/repair photos don't need it, adding
 * it here would be unrequested complexity for a real difference in
 * use case, not a shortcut.
 */
class VehicleIncidentPhotoController extends Controller
{
    private const MAX_SIZE_KB = 15 * 1024;

    private const ALLOWED_REAL_MIME_TYPES = ['image/jpeg', 'image/png'];

    public function store(Request $request, VehicleIncident $vehicleIncident): JsonResponse
    {
        $this->authorize('view', $vehicleIncident);

        $data = Validator::make($request->all(), [
            'stage' => ['required', 'string', 'in:'.implode(',', VehicleIncidentPhoto::STAGES)],
            'photo' => ['required', 'file', 'max:'.self::MAX_SIZE_KB],
        ])->validate();

        $file = $request->file('photo');
        $actualMimeType = $file->getMimeType();

        if (! in_array($actualMimeType, self::ALLOWED_REAL_MIME_TYPES, true)) {
            return response()->json(['message' => 'Only JPEG or PNG photos are allowed.'], 422);
        }

        $path = $file->store('vehicle-incident-photos', 'local');

        $photo = $vehicleIncident->photos()->create([
            'stage' => $data['stage'],
            'file_path' => $path,
            'file_type' => $actualMimeType,
            'uploaded_at' => now(),
        ]);

        return response()->json(['data' => $photo], 201);
    }

    public function destroy(Request $request, VehicleIncidentPhoto $vehicleIncidentPhoto): JsonResponse
    {
        $this->authorize('update', $vehicleIncidentPhoto->vehicleIncident);

        Storage::disk('local')->delete($vehicleIncidentPhoto->file_path);
        $vehicleIncidentPhoto->delete();

        return response()->json(status: 204);
    }
}
