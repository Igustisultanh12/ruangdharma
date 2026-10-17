<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VideoGenerationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Api\MuseController;
use App\Http\Controllers\Admin\MuseConfigController;

// Auth (public)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Muse API (token X-Muse-Token, rate limited) - public health, rest via middleware in controller
Route::get('/muse/health', [MuseController::class, 'health']);
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/muse/status', [MuseController::class, 'status']);
    Route::post('/muse/generate', [MuseController::class, 'generate']);
    Route::get('/muse/jobs/{id}', [MuseController::class, 'showJob']);
    Route::post('/muse/webhook', [MuseController::class, 'webhook']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Profil
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/password', [ProfileController::class, 'updatePassword']);
    // Generate
    Route::get('/generations', [VideoGenerationController::class, 'index']);
    Route::post('/generations', [VideoGenerationController::class, 'store']);
    Route::get('/generations/{id}', [VideoGenerationController::class, 'show']);
    Route::get('/quota', [VideoGenerationController::class, 'quota']);
    // Admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::post('/users/{id}/toggle', [AdminController::class, 'toggleUser']);
        Route::get('/generations', [AdminController::class, 'allGenerations']);
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
        Route::get('/muse/status', [MuseController::class, 'status']);
        // Konfigurasi Muse via menu Admin (generate & tampilkan token, tanpa edit .env)
        Route::get('/muse-config', [MuseConfigController::class, 'show']);
        Route::post('/muse-config/generate-token', [MuseConfigController::class, 'generateToken']);
        Route::post('/muse-config/generate-webhook-secret', [MuseConfigController::class, 'generateWebhookSecret']);
        Route::put('/muse-config', [MuseConfigController::class, 'update']);
        Route::post('/muse-config/test', [MuseConfigController::class, 'test']);
    });
});
