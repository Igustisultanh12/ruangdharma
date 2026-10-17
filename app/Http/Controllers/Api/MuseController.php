<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\MuseJob;
use App\Models\User;
use App\Services\MuseService;
use App\Services\QuotaService;
use App\Services\ScriptGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MuseController extends Controller
{
    private function checkMuseToken(Request $request, MuseService $muse): ?\Illuminate\Http\JsonResponse
    {
        if (!$muse->verifyToken($request->header('X-Muse-Token'), $request->bearerToken())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Header X-Muse-Token tidak valid.'], 401);
        }
        return null;
    }
    public function health()
    {
        try { DB::connection()->getPdo(); $db = 'ok'; } catch (\Throwable) { $db = 'error'; }
        return response()->json(['success' => true, 'status' => 'ok', 'service' => 'ruang-dharma-studio', 'time' => now()->toIso8601String(), 'db' => $db, 'version' => '1.0.0']);
    }
    public function status(Request $request, MuseService $muse)
    {
        if ($r = $this->checkMuseToken($request, $muse)) return $r;
        return response()->json(['success' => true] + $muse->status());
    }
    public function generate(Request $request, MuseService $muse, QuotaService $quota, ScriptGeneratorService $gen)
    {
        if ($r = $this->checkMuseToken($request, $muse)) return $r;
        $data = $request->validate([
            'keyword' => 'required|string|max:100', 'durasi' => 'sometimes|in:30,60,90',
            'model' => 'sometimes|in:narasi,ngobrol,gabungan', 'user_id' => 'sometimes|nullable|integer|exists:users,id',
            'callback_url' => 'sometimes|nullable|url',
            'async' => 'sometimes|boolean', 'via_muse' => 'sometimes|boolean',
        ]);
        $durasi = (int) ($data['durasi'] ?? 60); $model = $data['model'] ?? 'narasi';
        $async = !empty($data['async']) || !empty($data['via_muse']);
        if ($blocked = $quota->isBlocked($data['keyword'])) return response()->json(['success' => false, 'message' => "Kata kunci '{$blocked}' diblokir."], 422);
        $user = isset($data['user_id']) ? User::find($data['user_id']) : null;
        if ($user) { $check = $quota->check($user, $data['keyword']); if (!$check['allowed']) return response()->json(['success' => false, 'message' => $check['reason']], $check['code']); }
        $job = $muse->createJob($user?->id, $data['keyword'], $durasi, $model, $data['callback_url'] ?? null);
        // Cara 2 — Generate via Muse (video beneran): job dibuat status queued, TIDAK langsung completed.
        // Muse di chat akan melihat job ini (GET /api/muse/jobs/pending), generate video, lalu kirim hasil via POST /api/muse/webhook.
        if ($async) {
            if ($user) {
                \App\Models\VideoGeneration::create(['user_id' => $user->id, 'keyword' => $data['keyword'], 'durasi' => $durasi, 'model' => $model, 'naskah' => [], 'caption' => 'Menunggu Muse — video sedang dibuat...', 'prompts' => [], 'status' => 'waiting_muse', 'source' => 'muse', 'muse_job_id' => $job->id, 'workflow_status' => 'draft']);
            }
            return response()->json(['success' => true, 'job_id' => $job->id, 'status' => 'queued', 'keyword' => $job->keyword, 'durasi' => $durasi, 'model' => $model, 'message' => 'Job dibuat, menunggu Muse generate video. Muse akan kirim hasil via webhook.', 'next_step' => 'Di chat Muse, jalankan: python3 ~/workspace/custom-connectors/custom.ruangdharma/bin/ruangdharma.py pending'], 201);
        }
        try {
            $result = $gen->generate($data['keyword'], $durasi, $model);
            $muse->completeJob($job, $result);
            return response()->json(['success' => true, 'job_id' => $job->id, 'status' => 'completed', 'keyword' => $job->keyword, 'durasi' => $durasi, 'model' => $model, 'result' => $result, 'estimated_seconds' => 45], 201);
        } catch (\Throwable $e) {
            $muse->failJob($job, $e->getMessage());
            return response()->json(['success' => false, 'job_id' => $job->id, 'status' => 'failed', 'message' => $e->getMessage()], 500);
        }
    }
    public function pendingJobs(Request $request, MuseService $muse)
    {
        if ($r = $this->checkMuseToken($request, $muse)) return $r;
        $jobs = MuseJob::whereIn('status', ['queued', 'processing'])->with('user:id,name,email')->orderBy('created_at')->get();
        return response()->json(['success' => true, 'count' => $jobs->count(), 'data' => $jobs]);
    }
    public function showJob(Request $request, $id, MuseService $muse)
    {
        if ($r = $this->checkMuseToken($request, $muse)) return $r;
        return response()->json(['success' => true, 'data' => MuseJob::with('generation')->findOrFail($id)]);
    }
    public function webhook(Request $request, MuseService $muse)
    {
        // Sesuai docs/MUSE_INTEGRATION.md: X-Muse-Signature (HMAC-SHA256 body dgn MUSE_WEBHOOK_SECRET),
        // header X-Muse-Webhook-Secret, ATAU X-Muse-Token (MUSE_API_TOKEN).
        $authorized = $muse->verifyWebhookSignature($request->header('X-Muse-Signature'), $request->getContent())
            || $muse->verifyWebhookSecret($request->header('X-Muse-Webhook-Secret'))
            || $muse->verifyToken($request->header('X-Muse-Token'), $request->bearerToken());
        if (!$authorized) return response()->json(['success' => false, 'message' => 'Unauthorized. Signature / token webhook tidak valid.'], 401);
        $data = $request->validate(['job_id' => 'required|integer|exists:muse_jobs,id', 'status' => 'required|in:completed,failed,processing', 'result' => 'sometimes|array', 'error' => 'sometimes|string']);
        $job = MuseJob::findOrFail($data['job_id']);
        if ($data['status'] === 'completed') $muse->completeJob($job, $data['result'] ?? []);
        elseif ($data['status'] === 'failed') $muse->failJob($job, $data['error'] ?? 'Gagal dari Muse');
        else $muse->markProcessing($job);
        return response()->json(['success' => true, 'ok' => true, 'job_id' => $job->id, 'status' => $job->fresh()->status]);
    }
}
