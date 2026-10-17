<?php
namespace App\Services;
use App\Models\MuseJob;
use App\Models\Setting;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MuseService {
    /** Ambil nilai settings, decrypt jika terenkripsi (token/secret). */
    protected function settingDecrypted(string $key, $envFallback = null): ?string {
        $raw = Setting::getValue($key);
        if ($raw !== null && $raw !== '') {
            try { return decrypt($raw); } catch (\Throwable $e) { return $raw; }
        }
        return $envFallback ?: null;
    }
    public function getApiToken(): ?string {
        return $this->settingDecrypted('muse_api_token', env('MUSE_API_TOKEN'));
    }
    public function getWebhookSecret(): ?string {
        return $this->settingDecrypted('muse_webhook_secret', env('MUSE_WEBHOOK_SECRET'));
    }
    public function getBaseUrl(): string {
        return (string) (Setting::getValue('muse_base_url', env('MUSE_BASE_URL', '')) ?: '');
    }
    public function getTimeout(): int {
        return (int) (Setting::getValue('muse_timeout', (string) env('MUSE_TIMEOUT', 15)) ?: 15);
    }
    public function getConfigSource(): string {
        if (Setting::getValue('muse_api_token')) return 'database';
        if (env('MUSE_API_TOKEN')) return 'env';
        return 'none';
    }
    public function status(): array {
        $start = microtime(true);
        $pending = MuseJob::whereIn('status', ['queued','processing'])->count();
        $today = VideoGeneration::whereDate('created_at', now()->toDateString())->count();
        $latency = (int) ((microtime(true)-$start)*1000);
        return ['connected'=>true,'last_active'=>now()->toIso8601String(),'version'=>'1.0.0','latency_ms'=>$latency,'queue_pending'=>$pending,'daily_generations'=>$today,'config_source'=>$this->getConfigSource(),'has_token'=>(bool) $this->getApiToken(),'has_webhook_secret'=>(bool) $this->getWebhookSecret(),'muse_base_url'=>$this->getBaseUrl(),'muse_timeout'=>$this->getTimeout()];
    }
    public function createJob(?int $userId, string $keyword, int $durasi, string $model, ?string $callbackUrl = null): MuseJob {
        return MuseJob::create(['user_id'=>$userId,'keyword'=>$keyword,'durasi'=>$durasi,'model'=>$model,'status'=>'queued','callback_url'=>$callbackUrl,'source'=>'muse_api']);
    }
    public function completeJob(MuseJob $job, array $result): MuseJob {
        $job->update(['status'=>'completed','result'=>$result,'completed_at'=>now()]);
        if ($job->user_id) {
            VideoGeneration::create(['user_id'=>$job->user_id,'keyword'=>$job->keyword,'durasi'=>$job->durasi,'model'=>$job->model,'naskah'=>$result['naskah'] ?? [],'caption'=>$result['caption'] ?? null,'prompts'=>$result['prompts'] ?? [],'status'=>'completed','source'=>'muse','muse_job_id'=>$job->id]);
        }
        return $job->fresh();
    }
    public function failJob(MuseJob $job, string $error): MuseJob {
        $job->update(['status'=>'failed','error'=>$error,'completed_at'=>now()]);
        return $job->fresh();
    }
    /** Laravel -> Muse: panggil endpoint Muse jika MUSE_BASE_URL diisi. Return null jika tidak dikonfigurasi. */
    public function requestGenerationFromMuse(string $keyword, int $durasi, string $model): ?array {
        $base = $this->getBaseUrl();
        $token = $this->getApiToken();
        if (empty($base)) { Log::info('Muse base URL tidak diisi, pakai generator lokal.'); return null; }
        try {
            $res = Http::timeout($this->getTimeout())->withHeaders(['Authorization'=>'Bearer '.$token,'X-Muse-Token'=>$token])->post(rtrim($base,'/').'/generate', ['keyword'=>$keyword,'durasi'=>$durasi,'model'=>$model]);
            if ($res->successful()) return $res->json();
            Log::warning('Muse request gagal', ['status'=>$res->status(),'body'=>$res->body()]);
            return null;
        } catch (\Throwable $e) { Log::error('Muse request exception: '.$e->getMessage()); return null; }
    }
    public function verifyToken(?string $headerToken, ?string $bearer): bool {
        $given = $headerToken ?: $bearer;
        if (!$given) return false;
        // Terima token dari DB (hasil generate di menu Admin) ATAU dari .env (fallback lama)
        foreach ([$this->getApiToken(), env('MUSE_API_TOKEN')] as $expected) {
            if ($expected && hash_equals($expected, $given)) return true;
        }
        return false;
    }
    public function verifyWebhookSecret(?string $given): bool {
        if (!$given) return false;
        foreach ([$this->getWebhookSecret(), env('MUSE_WEBHOOK_SECRET')] as $expected) {
            if ($expected && hash_equals($expected, $given)) return true;
        }
        return false;
    }
}
