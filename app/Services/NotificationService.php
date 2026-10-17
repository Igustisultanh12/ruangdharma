<?php
namespace App\Services;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public static function notify(?User $user, string $title, string $message): ?Notification
    {
        if (!$user) return null;
        return Notification::create(['user_id' => $user->id, 'title' => $title, 'message' => $message, 'is_read' => false, 'created_at' => now()]);
    }
    public static function notifyAdmins(string $title, string $message): void
    {
        foreach (User::whereIn('role', ['admin', 'super_admin'])->where('is_active', true)->get() as $admin) static::notify($admin, $title, $message);
    }
}
