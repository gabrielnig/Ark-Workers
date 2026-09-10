<?php

use App\Http\Controllers\Api\AccountRequestController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetTypeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoutineController;
use App\Http\Controllers\Api\SpaceAccessGrantController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\ChunkedUploadController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/verify-email', [AuthController::class, 'verifyEmailOtp']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/departments', [DepartmentController::class, 'index'])
    ->middleware('throttle:30,1');
Route::post('/account-requests', [AccountRequestController::class, 'store'])
    ->middleware('throttle:5,1');
Route::get('/invites/{token}', [InviteController::class, 'show'])
    ->middleware('throttle:20,1');
Route::post('/invites/{token}/activate', [InviteController::class, 'activate'])
    ->middleware('throttle:10,1');

Route::get('/user', function (Request $request) {
    $user = $request->user();

    // can_manage is computed (hasManagementPermission checks is_admin OR a
    // grants_management department role), not a plain column, so it does
    // not appear in default model serialization. Appended here explicitly
    // since the frontend needs it to decide whether to show management
    // actions (e.g. "Add a Space") rather than showing them to everyone
    // and letting most requests 403.
    return response()->json([
        ...$user->toArray(),
        'can_manage' => $user->hasManagementPermission(),
    ]);
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('spaces', SpaceController::class);
    Route::apiResource('assets', AssetController::class);

    Route::get('/asset-types', [AssetTypeController::class, 'index']);
    Route::post('/asset-types', [AssetTypeController::class, 'store']);
    Route::patch('/asset-types/{assetType}', [AssetTypeController::class, 'update']);

    Route::apiResource('routines', RoutineController::class);

    Route::get('/users', [UserController::class, 'index']);

    Route::get('/spaces/{space}/access-grants', [SpaceAccessGrantController::class, 'index']);
    Route::post('/spaces/{space}/access-grants', [SpaceAccessGrantController::class, 'store']);
    Route::delete('/access-grants/{accessGrant}', [SpaceAccessGrantController::class, 'destroy']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::post('/tasks/{task}/proofs/chunked/start', [ChunkedUploadController::class, 'start']);
    Route::get('/tasks/{task}/proofs/chunked/{session}/status', [ChunkedUploadController::class, 'status']);
    Route::post('/tasks/{task}/proofs/chunked/{session}/chunks/{index}', [ChunkedUploadController::class, 'uploadChunk']);
    Route::post('/tasks/{task}/proofs/chunked/{session}/complete', [ChunkedUploadController::class, 'complete']);

    Route::get('/reports/daily-summary', [ReportController::class, 'dailySummary']);

    Route::get('/account-requests', [AccountRequestController::class, 'index']);
    Route::post('/account-requests/{accountRequest}/approve', [AccountRequestController::class, 'approve']);
    Route::post('/account-requests/{accountRequest}/reject', [AccountRequestController::class, 'reject']);
});
