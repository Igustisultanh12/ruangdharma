<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VideoGenerationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Api\MuseController;
use App\Http\Controllers\Admin\MuseConfigController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\QuotaPlanController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\RealtimeController;

// ---------- Auth (public, throttle) ----------
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware(['auth:sanctum', 'role:admin,super_admin']);

Route::get('/app-info', [SettingController::class, 'publicInfo']);

// ---------- Templates (public) ----------
Route::get('/templates', [TemplateController::class, 'index']);
Route::get('/templates/{keyword}', [TemplateController::class, 'show']);

// ---------- Muse API (token X-Muse-Token, rate limited) ----------
Route::get('/muse/health', [MuseController::class, 'health']);
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/muse/status', [MuseController::class, 'status']);
    Route::post('/muse/generate', [MuseController::class, 'generate']);
    Route::get('/muse/jobs/pending', [MuseController::class, 'pendingJobs']);
    Route::get('/muse/jobs/{id}', [MuseController::class, 'showJob']);
    Route::post('/muse/webhook', [MuseController::class, 'webhook']);
    // Upload file video hasil Muse (multipart, X-Muse-Token) → langsung tampil di detail video & Media.
    Route::post('/muse/upload-video', [MuseController::class, 'uploadVideo']);
    Route::get('/muse/social/publishes/pending', [\App\Http\Controllers\Api\MuseSocialController::class, 'pendingPublishes']);
    Route::post('/muse/social/publish-webhook', [\App\Http\Controllers\Api\MuseSocialController::class, 'publishWebhook']);
    Route::post('/muse/social/stats-webhook', [\App\Http\Controllers\Api\MuseSocialController::class, 'statsWebhook']);
});

// ---------- Eksternal via X-Api-Key ----------
Route::middleware('api.key')->group(function () {
    Route::get('/external/generations', [VideoGenerationController::class, 'index']);
});

// ---------- User (auth:sanctum) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    // POST (multipart) untuk upload avatar — PHP tidak mem-parsing body multipart pada PUT.
    Route::post('/profile', [ProfileController::class, 'update']);

    // Realtime pulse (polling ringan, lihat RealtimeController)
    Route::get('/realtime/pulse', [RealtimeController::class, 'pulse']);
    Route::post('/profile/password', [ProfileController::class, 'updatePassword']);

    // Generate / riwayat
    Route::get('/generations', [VideoGenerationController::class, 'index']);
    Route::post('/generations', [VideoGenerationController::class, 'store']);
    Route::get('/generations/calendar', [VideoGenerationController::class, 'calendar']);
    Route::get('/generations/counts', [VideoGenerationController::class, 'counts']);
    Route::get('/generations/{id}', [VideoGenerationController::class, 'show']);
    Route::get('/generations/{id}/download-txt', [VideoGenerationController::class, 'downloadTxt']);
    Route::delete('/generations/{id}', [VideoGenerationController::class, 'destroy']);
    Route::post('/generations/{id}/toggle-favorite', [VideoGenerationController::class, 'toggleFavorite']);
    Route::post('/generations/{id}/toggle-archive', [VideoGenerationController::class, 'toggleArchive']);
    Route::post('/generations/{id}/submit-review', [VideoGenerationController::class, 'submitReview']);
    Route::post('/generations/{id}/archive', [VideoGenerationController::class, 'archiveWorkflow']);
    Route::post('/generations/{id}/schedule-publish', [VideoGenerationController::class, 'schedulePublish']);
    Route::get('/generations/{id}/versions', [VideoGenerationController::class, 'versions']);
    Route::put('/generations/{id}/naskah', [VideoGenerationController::class, 'updateNaskah']);
    Route::post('/generations/{id}/versions/{versionId}/restore', [VideoGenerationController::class, 'restoreVersion']);
    Route::get('/quota', [VideoGenerationController::class, 'quota']);

    // Medsos — statistik & publish via Muse (IG/FB auto, TikTok manual)
    Route::get('/social/stats', [\App\Http\Controllers\SocialController::class, 'stats']);
    Route::get('/social/publishes', [\App\Http\Controllers\SocialController::class, 'publishes']);
    Route::post('/social/publish', [\App\Http\Controllers\SocialController::class, 'publish']);
    Route::post('/social/refresh', [\App\Http\Controllers\SocialController::class, 'refresh']);

    // Komentar
    Route::get('/generations/{generationId}/comments', [CommentController::class, 'index']);
    Route::post('/generations/{generationId}/comments', [CommentController::class, 'store']);
    Route::delete('/generations/{generationId}/comments/{commentId}', [CommentController::class, 'destroy']);

    // Notifikasi
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

    // Media Library
    Route::get('/media', [MediaAssetController::class, 'index']);
    Route::post('/media', [MediaAssetController::class, 'store']);
    Route::delete('/media/{id}', [MediaAssetController::class, 'destroy']);

    // API Keys & Webhooks (milik sendiri)
    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::post('/api-keys/{id}/toggle', [ApiKeyController::class, 'toggle']);
    Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy']);
    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::put('/webhooks/{id}', [WebhookController::class, 'update']);
    Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
    Route::post('/webhooks/{id}/test', [WebhookController::class, 'test']);
    Route::get('/webhooks/{id}/logs', [WebhookController::class, 'logs']);

    // ---------- Admin ----------
    Route::middleware('role:admin,super_admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::put('/users/{id}/limit', [AdminController::class, 'updateUserLimit']);
        Route::post('/users/{id}/toggle', [AdminController::class, 'toggleUser']);
        Route::post('/users/{id}/toggle-active', [AdminController::class, 'toggleUserActive']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::get('/generations', [AdminController::class, 'allGenerations']);
        Route::get('/history', [AdminController::class, 'historyAll']);
        Route::get('/export/csv', [AdminController::class, 'exportCsv']);
        Route::get('/export/pdf', [AdminController::class, 'exportPdf']);
        Route::get('/analytics', [AdminController::class, 'analytics']);
        Route::get('/analytics/export-csv', [AdminController::class, 'analyticsExportCsv']);
        Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
        Route::get('/login-histories', [AdminController::class, 'loginHistories']);
        Route::get('/muse/status', [MuseController::class, 'status']);

        // Settings (umum + per tab)
        Route::get('/settings', [SettingController::class, 'index']);
        Route::get('/settings/{tab}', [SettingController::class, 'getByTab']);
        Route::put('/settings', [SettingController::class, 'update']);
        Route::put('/settings/{tab}', [SettingController::class, 'updateByTab']);

        // Konfigurasi Muse
        Route::get('/muse-config', [MuseConfigController::class, 'show']);
        Route::post('/muse-config/generate-token', [MuseConfigController::class, 'generateToken']);
        Route::post('/muse-config/generate-webhook-secret', [MuseConfigController::class, 'generateWebhookSecret']);
        Route::put('/muse-config', [MuseConfigController::class, 'update']);
        Route::post('/muse-config/test', [MuseConfigController::class, 'test']);

        // Roles & Permissions
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::post('/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);
        Route::post('/users/{userId}/roles', [RoleController::class, 'assignRoleToUser']);
        Route::get('/permissions', [RoleController::class, 'permissions']);

        // Quota Plans
        Route::get('/quota-plans', [QuotaPlanController::class, 'index']);
        Route::post('/quota-plans', [QuotaPlanController::class, 'store']);
        Route::put('/quota-plans/{id}', [QuotaPlanController::class, 'update']);
        Route::delete('/quota-plans/{id}', [QuotaPlanController::class, 'destroy']);
        Route::post('/users/{userId}/quota-plan', [QuotaPlanController::class, 'assign']);
    });

    // ---------- Workflow approval (editor ke atas) ----------
    Route::middleware('role:admin,super_admin,editor')->group(function () {
        Route::get('/review-queue', [VideoGenerationController::class, 'reviewQueue']);
        Route::post('/generations/{id}/approve', [VideoGenerationController::class, 'approve']);
        Route::post('/generations/{id}/reject', [VideoGenerationController::class, 'reject']);
        Route::post('/generations/{id}/publish', [VideoGenerationController::class, 'publish']);
    });
});
