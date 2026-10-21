<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ExternalApiController;
use App\Http\Controllers\Api\FaceAuthController;
use App\Http\Controllers\SecureBiometricPhotoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==============================================================================
// 1. Mobile & Citizen Biometric Face Auth
// ==============================================================================
Route::middleware(['app.key', 'throttle:face-recognize'])->post('/recognize', [FaceAuthController::class, 'recognize']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/me', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'person' => $request->user(),
        ]);
    });

    Route::get('/user/photo', [SecureBiometricPhotoController::class, 'citizenPhoto']);
    Route::post('/user/logout', [FaceAuthController::class, 'logout']);
});

// ==============================================================================
// 2. eGov Passport & Biometric Face Match (Davlat Pasporti orqali Face ID Kirish)
// ==============================================================================
Route::prefix('egov')->group(function () {
    Route::post('/passport-verify', [\App\Http\Controllers\Api\EgovPassportController::class, 'fetchPassport']);
    Route::post('/face-match', [\App\Http\Controllers\Api\EgovPassportController::class, 'verifyFaceAndLogin']);
});

// ==============================================================================
// 3. B2B API Management Autentifikatsiya (Basic Auth -> Bearer Token)
// ==============================================================================
Route::prefix('v1/auth')->group(function () {
    Route::post('/token', [ApiAuthController::class, 'issueToken']);
    Route::middleware(['external.key'])->group(function () {
        Route::get('/me', [ApiAuthController::class, 'me']);
        Route::post('/revoke', [ApiAuthController::class, 'revokeToken']);
    });
});

// ==============================================================================
// 4. External B2B API (Yagona 1:1 Pasport va Jonli Yuzni Solishtirish Xizmati)
// ==============================================================================
Route::prefix('v1/external')
    ->middleware(['external.key', 'throttle:external-api'])
    ->group(function () {
        Route::post('/verify', [ExternalApiController::class, 'verifyFaceWithPassport']);
    });

// ==============================================================================
// 5. Tizim holati (Health Check)
// ==============================================================================
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'framework' => 'Laravel 13',
        'database' => 'PostgreSQL pgvector',
        'timestamp' => now()->toIso8601String(),
    ]);
});
