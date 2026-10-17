<?php
namespace App\Services;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogger
{
    public static function log(?User $user, string $action, string $description, ?Request $request = null, array $meta = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user?->id, 'action' => $action, 'description' => $description,
            'ip' => $request?->ip(), 'meta' => $meta ?: null, 'created_at' => now(),
        ]);
    }
    public static function fromRequest(Request $request, string $action, string $description, array $meta = []): ActivityLog
    {
        return static::log($request->user(), $action, $description, $request, $meta);
    }
}
