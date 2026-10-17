<?php
namespace App\Services;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /** Teks notifikasi dari Pengaturan > Template notifikasi (fallback ke teks bawaan), dengan variabel {nama}. */
    public static function template(string $key, string $default, array $vars = []): string
    {
        $templates = \App\Models\Setting::getArray('notification_templates', []);
        $text = is_string($templates[$key] ?? null) && trim($templates[$key]) !== '' ? $templates[$key] : $default;
        return strtr($text, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

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
