<?php
namespace App\Http\Controllers;
use App\Http\Requests\GenerateVideoRequest;
use App\Http\Requests\UpdateNaskahRequest;
use App\Models\VideoGeneration;
use App\Models\VideoGenerationVersion;
use App\Services\ActivityLogger;
use App\Services\MuseService;
use App\Services\NotificationService;
use App\Services\QuotaService;
use App\Services\ScriptGeneratorService;
use App\Services\VersioningService;
use App\Services\WebhookService;
use Illuminate\Http\Request;

class VideoGenerationController extends Controller
{
    /** Riwayat user + search/filter/sort/favorit/arsip + pagination. */
    public function index(Request $request)
    {
        $q = $request->user()->videoGenerations()->with('user:id,name,email');
        $q = $this->applyFilters($q, $request);
        return response()->json(['success' => true, 'data' => $q->paginate((int) $request->input('per_page', 20))]);
    }

    public function store(GenerateVideoRequest $request, ScriptGeneratorService $gen, QuotaService $quota)
    {
        $data = $request->validated();
        $user = $request->user();
        $check = $quota->check($user, $data['keyword']);
        if (!$check['allowed']) return response()->json(['success' => false, 'message' => $check['reason']], $check['code']);
        // Cara 2 — Generate via Muse (video beneran): buat MuseJob queued + VideoGeneration waiting_muse, jangan langsung completed.
        if ($request->boolean('via_muse') || $request->boolean('async')) {
            $museService = app(\App\Services\MuseService::class);
            $job = $museService->createJob($user->id, $data['keyword'], (int) $data['durasi'], $data['model'], null);
            $g = VideoGeneration::create(['user_id' => $user->id, 'keyword' => $data['keyword'], 'durasi' => (int) $data['durasi'], 'model' => $data['model'], 'naskah' => [], 'caption' => 'Menunggu Muse — video sedang dibuat...', 'prompts' => [], 'status' => 'waiting_muse', 'source' => 'muse', 'muse_job_id' => $job->id, 'workflow_status' => 'draft']);
            ActivityLogger::fromRequest($request, 'generate', "Generate via Muse: {$data['keyword']} ({$data['model']}, {$data['durasi']}s) — job #{$job->id} queued", ['generation_id' => $g->id, 'muse_job_id' => $job->id]);
            return response()->json(['success' => true, 'message' => 'Job dikirim ke Muse. Video akan muncul setelah Muse selesai generate dan kirim webhook.', 'generation' => $g, 'muse_job_id' => $job->id, 'job_status' => 'queued', 'quota_remaining' => $quota->remaining($user)], 201);
        }
        $result = $gen->generate($data['keyword'], (int) $data['durasi'], $data['model']);
        $g = VideoGeneration::create([
            'user_id' => $user->id, 'keyword' => $data['keyword'], 'durasi' => (int) $data['durasi'], 'model' => $data['model'],
            'naskah' => $result['naskah'], 'caption' => $result['caption'], 'prompts' => $result['prompts'],
            'status' => 'completed', 'source' => 'local', 'workflow_status' => 'draft',
        ]);
        ActivityLogger::fromRequest($request, 'generate', "Generate video: {$data['keyword']} ({$data['model']}, {$data['durasi']}s)", ['generation_id' => $g->id]);
        NotificationService::notify($user, 'Video selesai dibuat', "Video \"{$data['keyword']}\" berhasil dibuat.");
        WebhookService::dispatch('generation.completed', ['generation_id' => $g->id, 'keyword' => $g->keyword, 'model' => $g->model, 'durasi' => $g->durasi], $user->id);
        return response()->json(['success' => true, 'message' => 'Video berhasil dibuat.', 'generation' => $g, 'result' => $result, 'quota_remaining' => $quota->remaining($user)], 201);
    }

    public function show(Request $request, $id)
    {
        $g = VideoGeneration::where('user_id', $request->user()->id)->with(['versions', 'comments.user:id,name'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $g]);
    }

    public function destroy(Request $request, $id)
    {
        $g = VideoGeneration::where('user_id', $request->user()->id)->findOrFail($id);
        $g->delete();
        return response()->json(['success' => true, 'message' => 'Video berhasil dihapus.']);
    }

    public function downloadTxt(Request $request, $id)
    {
        $g = VideoGeneration::where('user_id', $request->user()->id)->findOrFail($id);
        $lines = ["RUANG DHARMA STUDIO", "Judul: {$g->keyword}", "Durasi: {$g->durasi} detik | Model: {$g->model} | Workflow: {$g->workflow_status}", '', '=== NASKAH ==='];
        foreach ((array) ($g->naskah ?? []) as $n) {
            $lines[] = '';
            $lines[] = 'Bagian ' . ($n['bagian'] ?? '-') . ' — ' . ($n['speaker'] ?? '-') . ' (' . ($n['suara'] ?? '-') . ')';
            $lines[] = $n['teks'] ?? $n['dialog'] ?? '';
            $lines[] = 'Prompt: ' . ($n['prompt_visual'] ?? '');
        }
        $lines[] = ''; $lines[] = '=== CAPTION ==='; $lines[] = $g->captionText();
        $lines[] = ''; $lines[] = '=== PROMPTS ===';
        foreach ((array) ($g->prompts ?? []) as $p) $lines[] = '- ' . $p;
        $filename = 'ruang-dharma-' . preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($g->keyword)) . '.txt';
        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    public function quota(Request $request, QuotaService $quota)
    {
        $user = $request->user();
        return response()->json(['success' => true, 'limit' => $quota->userLimit($user), 'used' => $quota->usedToday($user), 'remaining' => $quota->remaining($user)]);
    }

    public function toggleFavorite(Request $request, $id)
    {
        $g = VideoGeneration::where('user_id', $request->user()->id)->findOrFail($id);
        $g->update(['is_favorite' => !$g->is_favorite]);
        return response()->json(['success' => true, 'message' => $g->is_favorite ? 'Ditandai favorit.' : 'Favorit dibatalkan.', 'data' => $g->fresh()]);
    }
    public function toggleArchive(Request $request, $id)
    {
        $g = VideoGeneration::where('user_id', $request->user()->id)->findOrFail($id);
        $g->update(['is_archived' => !$g->is_archived]);
        return response()->json(['success' => true, 'message' => $g->is_archived ? 'Video diarsipkan.' : 'Video dikeluarkan dari arsip.', 'data' => $g->fresh()]);
    }

    // ---------- Workflow Approval ----------
    protected function findOwned(Request $request, $id): VideoGeneration
    {
        return VideoGeneration::where('user_id', $request->user()->id)->findOrFail($id);
    }
    public function submitReview(Request $request, $id)
    {
        $g = $this->findOwned($request, $id);
        $g->update(['workflow_status' => 'review', 'review_note' => null]);
        return response()->json(['success' => true, 'message' => 'Video dikirim ke review.', 'data' => $g->fresh()]);
    }
    public function approve(Request $request, $id)
    {
        $g = VideoGeneration::findOrFail($id); // admin/editor (route dibatasi)
        $g->update(['workflow_status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => null]);
        return response()->json(['success' => true, 'message' => 'Video disetujui.', 'data' => $g->fresh()]);
    }
    public function reject(Request $request, $id)
    {
        $data = $request->validate(['review_note' => 'required|string|max:1000'], ['review_note.required' => 'Catatan revisi wajib diisi.']);
        $g = VideoGeneration::findOrFail($id);
        $g->update(['workflow_status' => 'revisi', 'review_note' => $data['review_note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Video dikembalikan untuk revisi.', 'data' => $g->fresh()]);
    }
    public function publish(Request $request, $id)
    {
        $g = VideoGeneration::findOrFail($id);
        $g->update(['workflow_status' => 'published', 'publish_at' => $g->publish_at ?? now(), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        WebhookService::dispatch('generation.published', ['generation_id' => $g->id, 'keyword' => $g->keyword], $g->user_id);
        return response()->json(['success' => true, 'message' => 'Video dipublikasikan.', 'data' => $g->fresh()]);
    }
    public function archiveWorkflow(Request $request, $id)
    {
        $g = $this->findOwned($request, $id);
        $g->update(['workflow_status' => 'archived', 'is_archived' => true]);
        return response()->json(['success' => true, 'message' => 'Video diarsipkan (workflow).', 'data' => $g->fresh()]);
    }

    // ---------- Kalender Konten ----------
    public function schedulePublish(Request $request, $id)
    {
        $data = $request->validate(['publish_at' => 'required|date']);
        $g = $this->findOwned($request, $id);
        $g->update(['publish_at' => $data['publish_at']]);
        return response()->json(['success' => true, 'message' => 'Jadwal publish disimpan.', 'data' => $g->fresh()]);
    }
    public function calendar(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $q = $request->user()->videoGenerations()->byMonth($month)->orderBy('publish_at')->orderBy('created_at');
        return response()->json(['success' => true, 'month' => $month, 'data' => $q->paginate(50)]);
    }

    // ---------- Versioning ----------
    public function versions(Request $request, $id)
    {
        $g = $this->findOwned($request, $id);
        return response()->json(['success' => true, 'data' => $g->versions()->with('editor:id,name')->paginate(20)]);
    }
    public function updateNaskah(UpdateNaskahRequest $request, $id, VersioningService $versioning)
    {
        $g = $this->findOwned($request, $id);
        $g = $versioning->updateNaskah($g, $request->validated()['naskah'], $request->input('caption'), $request->user());
        return response()->json(['success' => true, 'message' => 'Naskah diperbarui, versi lama tersimpan.', 'data' => $g]);
    }
    public function restoreVersion(Request $request, $id, $versionId, VersioningService $versioning)
    {
        $g = $this->findOwned($request, $id);
        $version = VideoGenerationVersion::where('video_generation_id', $g->id)->findOrFail($versionId);
        $g = $versioning->restore($g, $version, $request->user());
        return response()->json(['success' => true, 'message' => "Versi {$version->version_number} dipulihkan.", 'data' => $g]);
    }

    /** Filter bersama index user + history admin. */
    public static function applyFilters($q, Request $request)
    {
        if ($request->filled('search')) $q->search($request->input('search'));
        if ($request->filled('model')) $q->byModel($request->input('model'));
        if ($request->filled('date_from')) $q->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $q->whereDate('created_at', '<=', $request->input('date_to'));
        if ($request->filled('user_id')) $q->where('user_id', $request->input('user_id'));
        if ($request->filled('workflow_status')) $q->where('workflow_status', $request->input('workflow_status'));
        if ($request->input('favorites') == 1 || $request->input('favorites') === '1') $q->favorites();
        if ($request->has('archived')) $q->where('is_archived', filter_var($request->input('archived'), FILTER_VALIDATE_BOOLEAN));
        $sort = $request->input('sort', 'latest');
        $q->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc');
        return $q;
    }
}
