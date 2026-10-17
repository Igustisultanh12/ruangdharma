<?php
namespace App\Services;
use App\Models\MuseJob;
use App\Models\Setting;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MuseService
{
    protected function settingDecrypted(string $key, $envFallback = null): ?string
    {
        $raw = Setting::get($key);
        if ($raw !== null && $raw !== '') {
            try { return decrypt($raw); } catch (\Throwable) { return $raw; }
        }
        return static::usableSecret($envFallback);
    }
    /** Abaikan nilai kosong / placeholder contoh (mis. "change-me-...") agar tidak bisa dipakai sebagai token. */
    public static function usableSecret(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with(strtolower($value), 'change-me') || strlen($value) < 16) return null;
        return $value;
    }
    public function getApiToken(): ?string { return $this->settingDecrypted('muse_api_token', config('services.muse.token')); }
    public function getWebhookSecret(): ?string { return $this->settingDecrypted('muse_webhook_secret', config('services.muse.webhook_secret')); }
    public function getBaseUrl(): string { return (string) (Setting::get('muse_base_url', config('services.muse.base_url', '')) ?: ''); }
    public function getTimeout(): int { return (int) (Setting::get('muse_timeout', (string) config('services.muse.timeout', 15)) ?: 15); }
    public function getConfigSource(): string
    {
        if (Setting::get('muse_api_token')) return 'database';
        if (static::usableSecret(config('services.muse.token'))) return 'env';
        return 'none';
    }
    public function testConnection(): array
    {
        $start = microtime(true);
        $base = $this->getBaseUrl();
        if (!$base) return ['connected' => true, 'target' => 'internal', 'latency_ms' => (int) ((microtime(true) - $start) * 1000), 'message' => 'Tes internal OK.'];
        try {
            $res = Http::timeout($this->getTimeout())->get(rtrim($base, '/') . '/health');
            return ['connected' => $res->successful(), 'target' => 'external', 'base_url' => $base, 'http_status' => $res->status(), 'latency_ms' => (int) ((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) { return ['connected' => false, 'target' => 'external', 'base_url' => $base, 'message' => $e->getMessage()]; }
    }
    public function status(): array
    {
        $start = microtime(true);
        return ['connected' => true, 'last_active' => now()->toIso8601String(), 'version' => '1.0.0', 'latency_ms' => (int) ((microtime(true) - $start) * 1000), 'queue_pending' => MuseJob::whereIn('status', ['queued', 'processing'])->count(), 'daily_generations' => VideoGeneration::whereDate('created_at', now()->toDateString())->count(), 'config_source' => $this->getConfigSource(), 'has_token' => (bool) $this->getApiToken(), 'has_webhook_secret' => (bool) $this->getWebhookSecret(), 'muse_base_url' => $this->getBaseUrl(), 'muse_timeout' => $this->getTimeout()];
    }
    public function createJob(?int $userId, string $keyword, int $durasi, string $model, ?string $callbackUrl = null): MuseJob
    {
        return MuseJob::create(['user_id' => $userId, 'keyword' => $keyword, 'durasi' => $durasi, 'model' => $model, 'status' => 'queued', 'callback_url' => $callbackUrl, 'source' => 'muse_api']);
    }
    public static function captionText($caption): ?string
    {
        if (is_array($caption)) return $caption['text'] ?? ($caption['caption'] ?? json_encode($caption, JSON_UNESCAPED_UNICODE));
        return $caption;
    }
    public function completeJob(MuseJob $job, array $result): MuseJob
    {
        $job = $job->markCompleted($result);
        if ($job->user_id) {
            VideoGeneration::create(['user_id' => $job->user_id, 'keyword' => $job->keyword, 'durasi' => $job->durasi, 'model' => $job->model, 'naskah' => $result['naskah'] ?? [], 'caption' => static::captionText($result['caption'] ?? null), 'prompts' => $result['prompts'] ?? [], 'status' => 'completed', 'source' => 'muse', 'muse_job_id' => $job->id, 'workflow_status' => 'draft']);
        }
        return $job;
    }
    public function failJob(MuseJob $job, string $error): MuseJob { return $job->markFailed($error); }
    public function requestGenerationFromMuse(string $keyword, int $durasi, string $model): ?array
    {
        $base = $this->getBaseUrl(); $token = $this->getApiToken();
        if (empty($base)) { Log::info('Muse base URL tidak diisi, pakai generator lokal.'); return null; }
        try {
            $res = Http::timeout($this->getTimeout())->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Muse-Token' => $token])->post(rtrim($base, '/') . '/generate', ['keyword' => $keyword, 'durasi' => $durasi, 'model' => $model]);
            if ($res->successful()) return $res->json();
            Log::warning('Muse request gagal', ['status' => $res->status(), 'body' => $res->body()]);
            return null;
        } catch (\Throwable $e) { Log::error('Muse request exception: ' . $e->getMessage()); return null; }
    }
    public function verifyToken(?string $headerToken, ?string $bearer): bool
    {
        $given = $headerToken ?: $bearer;
        if (!$given) return false;
        foreach ([$this->getApiToken(), static::usableSecret(config('services.muse.token'))] as $expected) {
            if ($expected && hash_equals($expected, $given)) return true;
        }
        return false;
    }
    /** Verifikasi header X-Muse-Signature = HMAC-SHA256(raw body, MUSE_WEBHOOK_SECRET) (boleh berawalan "sha256="). */
    public function verifyWebhookSignature(?string $signature, string $rawBody): bool
    {
        if (!$signature) return false;
        $signature = preg_replace('/^sha256=/i', '', trim($signature));
        foreach ([$this->getWebhookSecret(), static::usableSecret(config('services.muse.webhook_secret'))] as $secret) {
            if ($secret && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) return true;
        }
        return false;
    }
    public function verifyWebhookSecret(?string $given): bool
    {
        if (!$given) return false;
        foreach ([$this->getWebhookSecret(), static::usableSecret(config('services.muse.webhook_secret'))] as $expected) {
            if ($expected && hash_equals($expected, $given)) return true;
        }
        return false;
    }
}
