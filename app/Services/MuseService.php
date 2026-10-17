<?php
namespace App\Services;
use App\Models\MuseJob;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MuseService {
    public function status(): array {
        $start = microtime(true);
        $pending = MuseJob::whereIn('status', ['queued','processing'])->count();
        $today = VideoGeneration::whereDate('created_at', now()->toDateString())->count();
        $latency = (int) ((microtime(true)-$start)*1000);
        return ['connected'=>true,'last_active'=>now()->toIso8601String(),'version'=>'1.0.0','latency_ms'=>$latency,'queue_pending'=>$pending,'daily_generations'=>$today];
    }
    public function createJob(?int $userId, string $keyword, int $durasi, string $model, ?string $callbackUrl = null): MuseJob {
        return MuseJob::create(['user_id'=>$userId,'keyword'=>$keyword,'durasi'=>$durasi,'model'=>$model,'status'=>'queued','callback_url'=>$callbackUrl,'source'=>'muse_api']);
    }
    public function completeJob(MuseJob $job, array $result): MuseJob {
        $job->update(['status'=>'completed','result'=>$result,'completed_at'=>now()]);
        // Simpan juga sebagai VideoGeneration jika ada user
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
        $base = config('services.muse.base_url') ?? env('MUSE_BASE_URL');
        $token = env('MUSE_API_TOKEN');
        if (empty($base)) { Log::info('Muse base URL tidak diisi, pakai generator lokal.'); return null; }
        try {
            $res = Http::timeout((int) env('MUSE_TIMEOUT', 15))->withHeaders(['Authorization'=>'Bearer '.$token,'X-Muse-Token'=>$token])->post(rtrim($base,'/').'/generate', ['keyword'=>$keyword,'durasi'=>$durasi,'model'=>$model]);
            if ($res->successful()) return $res->json();
            Log::warning('Muse request gagal', ['status'=>$res->status(),'body'=>$res->body()]);
            return null;
        } catch (\Throwable $e) { Log::error('Muse request exception: '.$e->getMessage()); return null; }
    }
    public function verifyToken(?string $headerToken, ?string $bearer): bool {
        $expected = env('MUSE_API_TOKEN');
        if (!$expected) return false;
        $given = $headerToken ?: $bearer;
        return $given && hash_equals($expected, $given);
    }
}
