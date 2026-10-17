<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\MuseService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Kelola konfigurasi Muse dari menu Admin — tanpa edit .env manual.
 * Token & webhook secret disimpan terenkripsi (encrypt()) di tabel settings.
 * Hanya role admin (middleware role:admin di routes/api.php) yang bisa akses.
 */
class MuseConfigController extends Controller {
    protected function decryptValue(?string $raw): ?string {
        if (!$raw) return null;
        try { return decrypt($raw); } catch (\Throwable $e) { return $raw; } // fallback: nilai lama yang belum terenkripsi
    }
    protected function mask(?string $plain): ?string {
        if (!$plain) return null;
        if (strlen($plain) <= 12) return str_repeat('•', strlen($plain));
        return substr($plain, 0, 6) . '••••••••' . substr($plain, -4);
    }
    protected function currentToken(): ?string {
        $raw = Setting::getValue('muse_api_token');
        return $this->decryptValue($raw) ?: MuseService::usableSecret(config('services.muse.token'));
    }
    protected function currentSecret(): ?string {
        $raw = Setting::getValue('muse_webhook_secret');
        return $this->decryptValue($raw) ?: MuseService::usableSecret(config('services.muse.webhook_secret'));
    }

    public function show(Request $request, MuseService $muse) { return $this->get($request, $muse); }
    public function get(Request $request, MuseService $muse) {
        $show = $request->boolean('show');
        $token = $this->currentToken();
        $secret = $this->currentSecret();
        $dbToken = Setting::getValue('muse_api_token');
        $source = $dbToken ? 'database' : (MuseService::usableSecret(config('services.muse.token')) ? 'env' : 'none');
        $baseUrl = Setting::getValue('muse_base_url', config('services.muse.base_url', ''));
        $timeout = (int) (Setting::getValue('muse_timeout', (string) config('services.muse.timeout', 15)) ?: 15);
        return response()->json([
            'has_token' => (bool) $token,
            'has_webhook_secret' => (bool) $secret,
            'source' => $source,
            'muse_api_token' => $show ? $token : null,
            'muse_api_token_masked' => $this->mask($token),
            'muse_webhook_secret' => $show ? $secret : null,
            'muse_webhook_secret_masked' => $this->mask($secret),
            'muse_base_url' => $baseUrl,
            'muse_timeout' => $timeout,
            'status' => $muse->status(),
        ]);
    }

    public function generateToken(Request $request) {
        $plain = bin2hex(random_bytes(32)); // 64 karakter hex
        Setting::setValue('muse_api_token', encrypt($plain));
        Setting::setValue('muse_api_token_updated_at', now()->toIso8601String());
        ActivityLogger::fromRequest($request, 'muse.token', 'Admin generate token Muse baru.');
        NotificationService::notify($request->user(), 'Token Muse dibuat', 'Token Muse baru berhasil dibuat. Token lama sudah tidak berlaku.');
        return response()->json([
            'message' => 'Token Muse baru berhasil dibuat. Token lama langsung tidak berlaku.',
            'muse_api_token' => $plain,
            'muse_api_token_masked' => $this->mask($plain),
        ], 201);
    }

    public function generateWebhookSecret(Request $request) {
        // Pastikan secret selalu beda dari token
        do { $plain = bin2hex(random_bytes(32)); } while ($plain === $this->currentToken());
        Setting::setValue('muse_webhook_secret', encrypt($plain));
        Setting::setValue('muse_webhook_secret_updated_at', now()->toIso8601String());
        ActivityLogger::fromRequest($request, 'muse.webhook_secret', 'Admin generate webhook secret Muse baru.');
        return response()->json([
            'message' => 'Webhook secret baru berhasil dibuat. Secret lama langsung tidak berlaku.',
            'muse_webhook_secret' => $plain,
            'muse_webhook_secret_masked' => $this->mask($plain),
        ], 201);
    }

    public function update(Request $request) {
        $data = $request->validate([
            'muse_base_url' => 'nullable|url|max:255',
            'muse_timeout' => 'nullable|integer|min:5|max:60',
        ]);
        if (array_key_exists('muse_base_url', $data)) Setting::setValue('muse_base_url', (string) ($data['muse_base_url'] ?? ''));
        if (array_key_exists('muse_timeout', $data)) Setting::setValue('muse_timeout', (string) ($data['muse_timeout'] ?? 15));
        return response()->json(['message' => 'Konfigurasi Muse tersimpan.', 'muse_base_url' => Setting::getValue('muse_base_url', ''), 'muse_timeout' => (int) Setting::getValue('muse_timeout', '15')]);
    }

    public function test(Request $request, MuseService $muse) {
        $start = microtime(true);
        $baseUrl = Setting::getValue('muse_base_url', config('services.muse.base_url', ''));
        $timeout = (int) (Setting::getValue('muse_timeout', (string) config('services.muse.timeout', 15)) ?: 15);
        // Jika MUSE_BASE_URL diisi, test ke sana; jika kosong, test internal (DB + status)
        if ($baseUrl) {
            try {
                $res = Http::timeout($timeout)->get(rtrim($baseUrl, '/') . '/health');
                $latency = (int) ((microtime(true) - $start) * 1000);
                return response()->json(['connected' => $res->successful(), 'target' => 'external', 'base_url' => $baseUrl, 'http_status' => $res->status(), 'latency_ms' => $latency, 'body' => $res->json(), 'message' => $res->successful() ? 'Terhubung ke MUSE_BASE_URL.' : 'MUSE_BASE_URL merespons tapi tidak OK.']);
            } catch (\Throwable $e) {
                return response()->json(['connected' => false, 'target' => 'external', 'base_url' => $baseUrl, 'latency_ms' => (int) ((microtime(true) - $start) * 1000), 'message' => 'Gagal menghubungi MUSE_BASE_URL: ' . $e->getMessage()], 200);
            }
        }
        $status = $muse->status();
        $status['target'] = 'internal';
        $status['message'] = 'Tes internal OK. Isi MUSE_BASE_URL jika Muse punya endpoint HTTP sendiri.';
        return response()->json($status);
    }
}
