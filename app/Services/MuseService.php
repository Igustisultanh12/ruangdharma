<?php
namespace App\Services;
use App\Models\MuseJob;
use App\Models\MuseProfile;
use App\Models\Setting;
use App\Models\VideoGeneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    // ---------- Multi-profil Muse ----------
    public static function profilesTableExists(): bool
    {
        try { return Schema::hasTable('muse_profiles'); } catch (\Throwable) { return false; }
    }

    /**
     * Fallback token lama: jika tabel muse_profiles masih kosong tapi settings/.env
     * punya token lama, buat profil "Muse Utama" dari token tersebut (didekripsi dulu,
     * lalu disimpan terenkripsi lewat cast 'encrypted'). Konektor lama tetap jalan
     * karena tokennya sama. Dipanggil saat daftar profil / job baru diminta —
     * bukan lewat artisan tinker. Jika sudah ada profil, pastikan satu default.
     */
    public function ensureLegacyProfile(): ?MuseProfile
    {
        try {
            if (! static::profilesTableExists()) return null;
            $existing = MuseProfile::query()->first();
            if ($existing) {
                if (! MuseProfile::where('is_default', true)->exists()) {
                    $oldest = MuseProfile::where('is_active', true)->orderBy('id')->first() ?? $existing;
                    $oldest->update(['is_default' => true]);
                }
                return MuseProfile::where('is_default', true)->first();
            }
            $token = $this->getApiToken();
            if (! $token) return null;
            return MuseProfile::create([
                'name' => 'Muse Utama',
                'slug' => 'muse-utama',
                'api_token' => $token,
                'webhook_secret' => $this->getWebhookSecret(),
                'description' => 'Profil otomatis dari token Muse lama (settings).',
                'is_active' => true,
                'is_default' => true,
            ]);
        } catch (\Throwable) { return null; }
    }

    public function defaultProfile(): ?MuseProfile
    {
        $this->ensureLegacyProfile();
        try {
            if (! static::profilesTableExists()) return null;
            return MuseProfile::where('is_default', true)->first();
        } catch (\Throwable) { return null; }
    }

    /** Cari profil AKTIF berdasarkan token (perbandingan hash_equals, tanpa log token). */
    public function findProfileByToken(?string $given): ?MuseProfile
    {
        if (! $given || ! static::profilesTableExists()) return null;
        try {
            foreach (MuseProfile::where('is_active', true)->get() as $profile) {
                try { $expected = $profile->api_token; } catch (\Throwable) { continue; }
                if ($expected && hash_equals($expected, $given)) return $profile;
            }
        } catch (\Throwable) { return null; }
        return null;
    }

    /** Cari profil AKTIF berdasarkan webhook secret. */
    public function findProfileByWebhookSecret(?string $given): ?MuseProfile
    {
        if (! $given || ! static::profilesTableExists()) return null;
        try {
            foreach (MuseProfile::where('is_active', true)->get() as $profile) {
                try { $expected = $profile->webhook_secret; } catch (\Throwable) { continue; }
                if ($expected && hash_equals($expected, $given)) return $profile;
            }
        } catch (\Throwable) { return null; }
        return null;
    }

    /** Cari profil AKTIF berdasarkan HMAC signature (X-Muse-Signature). */
    public function findProfileBySignature(?string $signature, string $rawBody): ?MuseProfile
    {
        if (! $signature || ! static::profilesTableExists()) return null;
        $signature = preg_replace('/^sha256=/i', '', trim($signature));
        try {
            foreach (MuseProfile::where('is_active', true)->get() as $profile) {
                try { $secret = $profile->webhook_secret; } catch (\Throwable) { continue; }
                if ($secret && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) return $profile;
            }
        } catch (\Throwable) { return null; }
        return null;
    }

    protected function legacyTokenMatches(?string $given): bool
    {
        if (! $given) return false;
        foreach ([$this->getApiToken(), static::usableSecret(config('services.muse.token'))] as $expected) {
            if ($expected && hash_equals($expected, $given)) return true;
        }
        return false;
    }

    protected function legacySecretMatches(?string $given): bool
    {
        if (! $given) return false;
        foreach ([$this->getWebhookSecret(), static::usableSecret(config('services.muse.webhook_secret'))] as $expected) {
            if ($expected && hash_equals($expected, $given)) return true;
        }
        return false;
    }

    protected function legacySignatureMatches(?string $signature, string $rawBody): bool
    {
        if (! $signature) return false;
        $signature = preg_replace('/^sha256=/i', '', trim($signature));
        foreach ([$this->getWebhookSecret(), static::usableSecret(config('services.muse.webhook_secret'))] as $secret) {
            if ($secret && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) return true;
        }
        return false;
    }

    /**
     * Resolusi kredensial token: profil yang cocok (diutamakan) atau token lama (legacy).
     * @return array{authorized: bool, profile: ?MuseProfile, legacy: bool}
     */
    public function resolveTokenAuth(?string $headerToken, ?string $bearer): array
    {
        $given = $headerToken ?: $bearer;
        if (! $given) return ['authorized' => false, 'profile' => null, 'legacy' => false];
        if ($profile = $this->findProfileByToken($given)) return ['authorized' => true, 'profile' => $profile, 'legacy' => false];
        if ($this->legacyTokenMatches($given)) return ['authorized' => true, 'profile' => null, 'legacy' => true];
        return ['authorized' => false, 'profile' => null, 'legacy' => false];
    }

    /**
     * Resolusi kredensial webhook: signature -> webhook secret -> token (OR, seperti sebelumnya).
     * @return array{authorized: bool, profile: ?MuseProfile, legacy: bool}
     */
    public function resolveWebhookAuth(Request $request): array
    {
        $rawBody = $request->getContent();
        if ($profile = $this->findProfileBySignature($request->header('X-Muse-Signature'), $rawBody)) return ['authorized' => true, 'profile' => $profile, 'legacy' => false];
        if ($this->legacySignatureMatches($request->header('X-Muse-Signature'), $rawBody)) return ['authorized' => true, 'profile' => null, 'legacy' => true];
        if ($profile = $this->findProfileByWebhookSecret($request->header('X-Muse-Webhook-Secret'))) return ['authorized' => true, 'profile' => $profile, 'legacy' => false];
        if ($this->legacySecretMatches($request->header('X-Muse-Webhook-Secret'))) return ['authorized' => true, 'profile' => null, 'legacy' => true];
        return $this->resolveTokenAuth($request->header('X-Muse-Token'), $request->bearerToken());
    }

    /**
     * Kepemilikan job: job tanpa profil (job lama) boleh diselesaikan kredensial valid apa pun.
     * Job berprofil hanya boleh diselesaikan token profil pemiliknya — KECUALI token lama
     * (settings/.env) yang bertindak sebagai kredensial admin fallback agar konektor
     * yang belum migrasi tetap bisa menyelesaikan job berprofil apa pun.
     * Token profil lain DITOLAK.
     */
    public function profileMatchesJob(?MuseProfile $profile, bool $legacy, MuseJob $job): bool
    {
        if (empty($job->muse_profile_id)) return true;
        if ($profile && (int) $profile->id === (int) $job->muse_profile_id) return true;
        if ($legacy) return true;
        return false;
    }

    /**
     * Resolusi profil untuk job baru: id eksplisit (harus aktif) atau profil default.
     * @return array{profile: ?MuseProfile, error: ?string}
     */
    public function resolveProfileForAssignment(?int $profileId): array
    {
        if ($profileId !== null) {
            if (! static::profilesTableExists()) return ['profile' => null, 'error' => 'Profil Muse belum tersedia (migrasi muse_profiles belum dijalankan).'];
            $profile = MuseProfile::find($profileId);
            if (! $profile) return ['profile' => null, 'error' => 'Profil Muse tidak ditemukan.'];
            if (! $profile->is_active) return ['profile' => null, 'error' => "Profil Muse \"{$profile->name}\" tidak aktif."];
            return ['profile' => $profile, 'error' => null];
        }
        return ['profile' => $this->defaultProfile(), 'error' => null];
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
        $profilesTotal = 0; $profilesActive = 0;
        try {
            if (static::profilesTableExists()) {
                $profilesTotal = MuseProfile::count();
                $profilesActive = MuseProfile::where('is_active', true)->count();
            }
        } catch (\Throwable) { /* abaikan bila migrasi belum jalan */ }
        return ['connected' => true, 'last_active' => now()->toIso8601String(), 'version' => '1.0.0', 'latency_ms' => (int) ((microtime(true) - $start) * 1000), 'queue_pending' => MuseJob::whereIn('status', ['queued', 'processing'])->count(), 'daily_generations' => VideoGeneration::whereDate('created_at', now()->toDateString())->count(), 'config_source' => $this->getConfigSource(), 'has_token' => (bool) $this->getApiToken(), 'has_webhook_secret' => (bool) $this->getWebhookSecret(), 'muse_base_url' => $this->getBaseUrl(), 'muse_timeout' => $this->getTimeout(), 'muse_profiles_total' => $profilesTotal, 'muse_profiles_active' => $profilesActive];
    }
    public function createJob(?int $userId, string $keyword, int $durasi, string $model, ?string $callbackUrl = null, ?int $museProfileId = null): MuseJob
    {
        $attrs = ['user_id' => $userId, 'keyword' => $keyword, 'durasi' => $durasi, 'model' => $model, 'status' => 'queued', 'callback_url' => $callbackUrl, 'source' => 'muse_api'];
        // Tanpa muse_profile_id bila kolom belum ada (migrasi belum jalan) — job tetap dibuat.
        $profileId = $museProfileId ?? $this->defaultProfile()?->id;
        try {
            if (Schema::hasColumn('muse_jobs', 'muse_profile_id')) $attrs['muse_profile_id'] = $profileId;
        } catch (\Throwable) { /* abaikan */ }
        $job = MuseJob::create($attrs);
        if ($profileId) {
            try { MuseProfile::where('id', $profileId)->update(['last_used_at' => now()]); } catch (\Throwable) { /* abaikan */ }
        }
        return $job;
    }
    public static function captionText($caption): ?string
    {
        if (is_array($caption)) return $caption['text'] ?? ($caption['caption'] ?? json_encode($caption, JSON_UNESCAPED_UNICODE));
        return $caption;
    }
    public function completeJob(MuseJob $job, array $result): MuseJob
    {
        $job = $job->markCompleted($result);
        $videoUrl = $result['video_url'] ?? $result['videoUrl'] ?? null;
        $videoFiles = $result['video_files'] ?? $result['videoFiles'] ?? null;
        if ($job->user_id) {
            // Jika Muse hanya mengirim video tanpa naskah, lengkapi naskah/caption/prompt dari generator lokal
            // agar detail video di web tidak kosong.
            if (empty($result['naskah'])) {
                $local = app(ScriptGeneratorService::class)->generate($job->keyword, (int) $job->durasi, $job->model);
                $result += ['naskah' => $local['naskah'], 'caption' => $local['caption'], 'prompts' => $local['prompts']];
                $result['naskah'] = $result['naskah'] ?: $local['naskah'];
            }
            // Jika VideoGeneration draft sudah ada (dari generate async via web), update itu. Jika belum ada, buat baru.
            $existing = VideoGeneration::where('muse_job_id', $job->id)->first();
            $attrs = ['user_id' => $job->user_id, 'keyword' => $job->keyword, 'durasi' => $job->durasi, 'model' => $job->model, 'naskah' => $result['naskah'] ?? [], 'caption' => static::captionText($result['caption'] ?? null), 'prompts' => $result['prompts'] ?? [], 'status' => 'completed', 'source' => 'muse', 'muse_job_id' => $job->id, 'video_url' => $videoUrl, 'video_files' => $videoFiles];
            if ($existing) $existing->update($attrs);
            else VideoGeneration::create($attrs + ['workflow_status' => 'draft']);
            NotificationService::notify($job->user, 'Video via Muse selesai', "Video \"{$job->keyword}\" dari Muse sudah jadi" . ($videoUrl ? ' lengkap dengan file videonya.' : '.'));
        }
        return $job;
    }
    public function failJob(MuseJob $job, string $error): MuseJob
    {
        $job = $job->markFailed($error);
        VideoGeneration::where('muse_job_id', $job->id)->where('status', '!=', 'completed')->update(['status' => 'failed', 'caption' => 'Muse gagal membuat video: ' . mb_substr($error, 0, 500)]);
        if ($job->user_id) NotificationService::notify($job->user, 'Video via Muse gagal', "Muse gagal membuat \"{$job->keyword}\": " . mb_substr($error, 0, 200));
        return $job;
    }
    public function markProcessing(MuseJob $job): MuseJob
    {
        $job = $job->markProcessing();
        VideoGeneration::where('muse_job_id', $job->id)->where('status', 'waiting_muse')->update(['status' => 'processing']);
        return $job;
    }
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
        return $this->resolveTokenAuth($headerToken, $bearer)['authorized'];
    }
    /** Verifikasi header X-Muse-Signature = HMAC-SHA256(raw body, MUSE_WEBHOOK_SECRET) (boleh berawalan "sha256="). */
    public function verifyWebhookSignature(?string $signature, string $rawBody): bool
    {
        if ($this->findProfileBySignature($signature, $rawBody)) return true;
        return $this->legacySignatureMatches($signature, $rawBody);
    }
    public function verifyWebhookSecret(?string $given): bool
    {
        if ($this->findProfileByWebhookSecret($given)) return true;
        return $this->legacySecretMatches($given);
    }
}
