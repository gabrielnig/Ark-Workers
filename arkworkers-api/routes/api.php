<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetTypeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmailOtp']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('spaces', SpaceController::class);
    Route::apiResource('assets', AssetController::class);

    Route::get('/asset-types', [AssetTypeController::class, 'index']);
    Route::post('/asset-types', [AssetTypeController::class, 'store']);
    Route::patch('/asset-types/{assetType}', [AssetTypeController::class, 'update']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::post('/tasks/{task}/proofs', [TaskController::class, 'uploadProof']);
});
