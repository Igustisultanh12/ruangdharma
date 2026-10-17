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
        if ($r = $this->checkMuseToken($request, $muse)) return $r;
        $data = $request->validate(['job_id' => 'required|integer|exists:muse_jobs,id', 'status' => 'required|in:completed,failed,processing', 'result' => 'sometimes|array', 'error' => 'sometimes|string']);
        $job = MuseJob::findOrFail($data['job_id']);
        if ($data['status'] === 'completed') $muse->completeJob($job, $data['result'] ?? []);
        elseif ($data['status'] === 'failed') $muse->failJob($job, $data['error'] ?? 'Gagal dari Muse');
        else $job->markProcessing();
        return response()->json(['success' => true, 'ok' => true, 'job_id' => $job->id, 'status' => $job->fresh()->status]);
    }
    /**
     * POST /api/muse/upload-video — Auto-upload video dari Muse, langsung bisa dibuka di web.
     * Auth: X-Muse-Token. Body multipart: file (mp4/mov/webm, max 50MB), job_id (opsional, untuk link ke VideoGeneration),
     * generation_id (opsional), keyword (opsional untuk nama file).
     * Return: { success, video_url, file_path, size, job_id, generation_id }
     * File disimpan di storage/app/public/videos/ dan dilayani via /storage/videos/... (butuh php artisan storage:link).
     */
    public function uploadVideo(Request $request, MuseService $muse)
    {
        if ($r = $this->checkMuseToken($request, $muse)) return $r;
        $data = $request->validate([
            'file' => 'required|file|mimes:mp4,mov,webm|max:51200',
            'job_id' => 'sometimes|nullable|integer|exists:muse_jobs,id',
            'generation_id' => 'sometimes|nullable|integer|exists:video_generations,id',
            'keyword' => 'sometimes|nullable|string|max:100',
        ], ['file.max' => 'File video maksimal 50MB.', 'file.mimes' => 'File harus video mp4/mov/webm.']);
        $file = $request->file('file');
        $keyword = $data['keyword'] ?? null;
        $jobId = $data['job_id'] ?? null;
        if ($jobId && !$keyword) { $j = MuseJob::find($jobId); $keyword = $j?->keyword; }
        $slug = $keyword ? preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($keyword)) : 'video';
        $slug = trim($slug, '-') ?: 'video';
        $filename = $slug . '-' . ($jobId ? "job{$jobId}-" : '') . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('videos', $filename, 'public');
        $videoUrl = '/storage/' . $path;
        $fullUrl = rtrim(config('app.url'), '/') . $videoUrl;
        // Link ke VideoGeneration jika ada job_id / generation_id — update video_url agar langsung bisa di-play di Riwayat.
        $generation = null;
        if (!empty($data['generation_id'])) $generation = \App\Models\VideoGeneration::find($data['generation_id']);
        elseif ($jobId) $generation = \App\Models\VideoGeneration::where('muse_job_id', $jobId)->first();
        if ($generation) {
            $generation->update(['video_url' => $fullUrl, 'video_files' => array_values(array_unique(array_merge((array) ($generation->video_files ?? []), [$filename])))]);
        }
        // Simpan juga ke MediaAsset agar muncul di Media Library
        try {
            \App\Models\MediaAsset::create(['title' => ($keyword ? $keyword . ' — Video' : 'Video Muse') . ($jobId ? " (Job #{$jobId})" : ''), 'file_path' => $videoUrl, 'type' => 'video', 'tags' => ['muse', 'auto-upload'], 'user_id' => $generation?->user_id ?? MuseJob::find($jobId)?->user_id ?? 1, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
        } catch (\Throwable $e) { /* MediaAsset opsional, jangan gagalkan upload */ }
        return response()->json(['success' => true, 'message' => 'Video berhasil di-upload ke web.', 'video_url' => $fullUrl, 'video_url_relative' => $videoUrl, 'file_path' => $path, 'filename' => $filename, 'size' => $file->getSize(), 'job_id' => $jobId, 'generation_id' => $generation?->id], 201);
    }
}
