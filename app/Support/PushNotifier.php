<?php

namespace App\Support;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Notifikasi push ke HP Android (Firebase Cloud Messaging HTTP v1) — pola yang sama
 * dengan aplikasi SISINDEN: notifikasi tetap muncul & berbunyi walau aplikasi ditutup.
 *
 * Kredensial (diatur admin di Pengaturan → Push HP, tanpa build ulang APK):
 *  - google-services.json → konfigurasi klien Android (publik), dikirim ke aplikasi lewat
 *    GET /api/mobile/config agar Firebase bisa diinisialisasi di HP.
 *  - Service account (berisi private key) → RAHASIA, disimpan terenkripsi di tabel settings,
 *    hanya dipakai server untuk menandatangani token OAuth Google.
 *
 * Tanpa kredensial pun aplikasi tetap menampilkan notifikasi lewat sinkronisasi berkala
 * (polling saat terbuka + WorkManager di latar belakang), hanya tidak instan.
 */
class PushNotifier
{
    public const ANDROID_PACKAGE = 'id.ruangdharma.studio';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    // ------------------------------------------------------------------ konfigurasi

    private static function setting(string $key): ?string
    {
        try {
            $v = Setting::where('key', $key)->value('value');
            return $v === null || $v === '' ? null : (string) $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Konfigurasi klien Android dari google-services.json (publik). */
    public static function clientConfig(): ?array
    {
        $raw = self::setting('fcm_client_config');
        $cfg = $raw ? json_decode($raw, true) : null;
        if (!is_array($cfg) || empty($cfg['api_key']) || empty($cfg['app_id']) || empty($cfg['project_id']) || empty($cfg['sender_id'])) {
            return null;
        }
        return [
            'api_key' => (string) $cfg['api_key'],
            'app_id' => (string) $cfg['app_id'],
            'project_id' => (string) $cfg['project_id'],
            'sender_id' => (string) $cfg['sender_id'],
        ];
    }

    /**
     * Ambil konfigurasi klien dari isi google-services.json untuk paket aplikasi Ruang Dharma.
     * Paket debug (`id.ruangdharma.studio.debug`) juga diterima.
     * @return array|string array konfigurasi, atau pesan kesalahan.
     */
    public static function parseGoogleServices(string $json)
    {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['project_info']) || empty($data['client'])) {
            return 'Isi google-services.json tidak valid.';
        }
        $found = null;
        foreach ((array) $data['client'] as $client) {
            $pkg = $client['client_info']['android_client_info']['package_name'] ?? null;
            if ($pkg === self::ANDROID_PACKAGE) { $found = $client; break; }
            if ($pkg === self::ANDROID_PACKAGE . '.debug' && !$found) $found = $client;
        }
        if (!$found) {
            return 'google-services.json tidak memuat aplikasi Android dengan package ' . self::ANDROID_PACKAGE . '.';
        }
        $cfg = [
            'api_key' => $found['api_key'][0]['current_key'] ?? null,
            'app_id' => $found['client_info']['mobilesdk_app_id'] ?? null,
            'project_id' => $data['project_info']['project_id'] ?? null,
            'sender_id' => (string) ($data['project_info']['project_number'] ?? ''),
        ];
        if (in_array(null, $cfg, true) || $cfg['sender_id'] === '') {
            return 'google-services.json tidak lengkap (api_key / app_id / project).';
        }
        return $cfg;
    }

    public static function storeClientConfig(array $cfg): void
    {
        Setting::set('fcm_client_config', json_encode($cfg));
    }

    /** Service account (rahasia) yang tersimpan terenkripsi. */
    private static function serviceAccount(): ?array
    {
        $enc = self::setting('fcm_service_account');
        if (!$enc) return null;
        try {
            $sa = json_decode(Crypt::decryptString($enc), true);
        } catch (\Throwable $e) {
            Log::error('[PUSH] Service account FCM tidak dapat didekripsi (APP_KEY berubah?).');
            return null;
        }
        return (is_array($sa) && !empty($sa['client_email']) && !empty($sa['private_key']) && !empty($sa['project_id'])) ? $sa : null;
    }

    /**
     * Validasi & simpan service account (terenkripsi).
     * @return string|null pesan kesalahan, null bila berhasil.
     */
    public static function storeServiceAccount(string $json): ?string
    {
        $sa = json_decode($json, true);
        if (!is_array($sa) || ($sa['type'] ?? null) !== 'service_account' || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['project_id'])) {
            return 'Berkas service account tidak valid (harus JSON dengan "type": "service_account").';
        }
        if (!function_exists('openssl_pkey_get_private') || !openssl_pkey_get_private($sa['private_key'])) {
            return 'Private key pada service account tidak dapat dibaca.';
        }
        Setting::set('fcm_service_account', Crypt::encryptString(json_encode([
            'type' => 'service_account',
            'project_id' => $sa['project_id'],
            'client_email' => $sa['client_email'],
            'private_key' => $sa['private_key'],
            'token_uri' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ])));
        Cache::forget('fcm:access_token');
        return null;
    }

    public static function clear(): void
    {
        Setting::whereIn('key', ['fcm_service_account', 'fcm_client_config'])->delete();
        Cache::forget('fcm:access_token');
    }

    public static function serviceAccountProject(): ?string
    {
        return self::serviceAccount()['project_id'] ?? null;
    }

    public static function enabled(): bool
    {
        return self::setting('fcm_enabled') !== '0' && self::serviceAccount() !== null && self::clientConfig() !== null;
    }

    /** Ringkasan status untuk halaman admin (tanpa data rahasia). */
    public static function status(): array
    {
        $devices = Schema::hasTable('device_tokens') ? DeviceToken::count() : 0;
        return [
            'enabled_flag' => self::setting('fcm_enabled') !== '0',
            'ready' => self::enabled(),
            'client_project' => self::clientConfig()['project_id'] ?? null,
            'service_account_project' => self::serviceAccountProject(),
            'devices' => $devices,
            'package' => self::ANDROID_PACKAGE,
        ];
    }

    // ------------------------------------------------------------------ pemicu

    /** Notifikasi in-app baru → push ke semua HP pemiliknya. */
    public static function notification(Notification $n): void
    {
        if (!$n->user_id) return;
        $unread = 0;
        try { $unread = Notification::where('user_id', $n->user_id)->where('is_read', false)->count(); } catch (\Throwable $e) { }
        self::queue([(int) $n->user_id], [
            'type' => 'notification',
            'id' => (string) $n->id,
            'title' => (string) ($n->title ?: 'Ruang Dharma Studio'),
            'body' => mb_substr((string) ($n->message ?? ''), 0, 240),
            'unread' => (string) $unread,
        ]);
    }

    // ------------------------------------------------------------------ pengiriman

    /** Kirim setelah respons HTTP selesai agar tidak memperlambat web/aplikasi. Data FCM wajib string. */
    private static function queue(array $userIds, array $data, int $ttl = 86400): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds || !self::enabled()) return;
        $job = function () use ($userIds, $data, $ttl) {
            try {
                self::sendNow($userIds, $data, $ttl);
            } catch (\Throwable $e) {
                Log::warning('[PUSH] Gagal mengirim: ' . $e->getMessage());
            }
        };
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            $job(); // scheduler / artisan: kirim langsung
        } elseif (function_exists('defer')) {
            defer($job);
        } else {
            app()->terminating($job);
        }
    }

    /** @return int jumlah perangkat yang berhasil dikirimi. */
    public static function sendNow(array $userIds, array $data, int $ttl = 86400): int
    {
        if (!Schema::hasTable('device_tokens')) return 0;
        $sa = self::serviceAccount();
        $access = $sa ? self::accessToken($sa) : null;
        if (!$access) return 0;
        $url = "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send";
        $sent = 0;
        foreach (DeviceToken::whereIn('user_id', $userIds)->get() as $device) {
            $res = Http::withToken($access)->timeout(10)->post($url, ['message' => [
                'token' => $device->token,
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'high', 'ttl' => $ttl . 's'],
            ]]);
            if ($res->successful()) { $sent++; continue; }
            $status = (string) ($res->json('error.status') ?? '');
            $detail = json_encode($res->json('error.details') ?? []);
            if ($res->status() === 404 || $status === 'NOT_FOUND' || str_contains((string) $detail, 'UNREGISTERED')) {
                $device->delete(); // aplikasi di-uninstall / token kedaluwarsa
            } else {
                Log::warning("[PUSH] FCM {$res->status()} {$status}");
            }
        }
        return $sent;
    }

    /** Token OAuth2 dari service account (JWT RS256), di-cache ±55 menit. */
    private static function accessToken(array $sa): ?string
    {
        $token = Cache::remember('fcm:access_token', now()->addMinutes(55), function () use ($sa) {
            $now = time();
            $b64 = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $b64(json_encode([
                'iss' => $sa['client_email'],
                'scope' => self::SCOPE,
                'aud' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $signature = '';
            if (!openssl_sign("{$header}.{$claims}", $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                Log::error('[PUSH] Gagal menandatangani JWT service account.');
                return null;
            }
            $res = Http::asForm()->timeout(10)->post($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$header}.{$claims}." . $b64($signature),
            ]);
            if (!$res->successful()) {
                Log::error('[PUSH] Token OAuth FCM ditolak: HTTP ' . $res->status());
                return null;
            }
            return $res->json('access_token');
        });
        if (!$token) Cache::forget('fcm:access_token');
        return $token ?: null;
    }
}
