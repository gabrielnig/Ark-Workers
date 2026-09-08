<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login-pin', [AuthController::class, 'loginWithPin']);
Route::post('/auth/otp/request', [AuthController::class, 'requestOtp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
