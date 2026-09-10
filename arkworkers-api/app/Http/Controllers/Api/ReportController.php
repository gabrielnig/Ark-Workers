<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DailySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DESIGN-SYSTEM.md §6.3: a Pastor/Admin-scannable daily summary, not
 * a dense BI dashboard. Gated the same way as other management-only
 * screens (AssetTypeController), Pastor carries zero permission
 * weight so a plain staff user cannot reach this.
 */
class ReportController extends Controller
{
    public function dailySummary(Request $request, DailySummaryService $service): JsonResponse
    {
        abort_unless($request->user()->hasManagementPermission(), 403);

        return response()->json(['data' => $service->generate($request->user())]);
    }
}
