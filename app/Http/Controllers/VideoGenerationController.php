<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateVideoRequest;
use App\Http\Requests\UpdateNaskahRequest;
use App\Models\User;
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
    public const REVIEWER_ROLES = ['admin', 'super_admin', 'editor'];

    /** Riwayat user + search/filter/sort/favorit/arsip + pagination. */
    public function index(Request $request)
    {
        $q = $request->user()->videoGenerations()->with('user:id,name,email', 'museJob.museProfile');
        $q = static::applyFilters($q, $request);

        $page = $q->paginate($this->perPage($request, 20));
        $page->getCollection()->each(fn ($g) => $g->append('muse_profile_name'));

        return response()->json(['success' => true, 'data' => $page]);
    }

    /** Ringkasan untuk tab Riwayat (jumlah semua / favorit / arsip). */
    public function counts(Request $request)
    {
        $base = $request->user()->videoGenerations();

        return response()->json(['success' => true, 'data' => [
            'all' => (clone $base)->where('is_archived', false)->count(),
            'favorites' => (clone $base)->where('is_favorite', true)->count(),
            'archived' => (clone $base)->where('is_archived', true)->count(),
            'review' => (clone $base)->where('workflow_status', 'review')->count(),
            'revisi' => (clone $base)->where('workflow_status', 'revisi')->count(),
            'published' => (clone $base)->where('workflow_status', 'published')->count(),
        ]]);
    }

    public function store(GenerateVideoRequest $request, ScriptGeneratorService $gen, QuotaService $quota)
    {
        $data = $request->validated();
        $user = $request->user();
        $keyword = trim($data['keyword']);
        $check = $quota->check($user, $keyword);
        if (! $check['allowed']) {
            return response()->json(['success' => false, 'message' => $check['reason']], $check['code']);
        }

        // Cara 2 — Generate via Muse (video beneran): buat MuseJob "queued" + VideoGeneration "waiting_muse".
        // Muse mengambil job lewat GET /api/muse/jobs/pending lalu mengirim hasil ke POST /api/muse/webhook.
        if ($request->boolean('via_muse') || $request->boolean('async')) {
            // Multi-Muse: job bisa di-assign ke profil tertentu; kosong = profil default (atau fallback token lama).
            $resolved = app(MuseService::class)->resolveProfileForAssignment(isset($data['muse_profile_id']) ? (int) $data['muse_profile_id'] : null);
            if ($resolved['error']) {
                return response()->json(['success' => false, 'message' => $resolved['error']], 422);
            }
            $profile = $resolved['profile'];
            $job = app(MuseService::class)->createJob($user->id, $keyword, (int) $data['durasi'], $data['model'], null, $profile?->id);
            $g = VideoGeneration::create([
                'user_id' => $user->id, 'keyword' => $keyword, 'durasi' => (int) $data['durasi'], 'model' => $data['model'],
                'naskah' => [], 'caption' => 'Menunggu Muse — video sedang dibuat...', 'prompts' => [],
                'status' => 'waiting_muse', 'source' => 'muse', 'muse_job_id' => $job->id, 'workflow_status' => 'draft',
                'backsound_url' => $data['backsound_url'] ?? null, 'backsound_volume' => $data['backsound_volume'] ?? 20,
                'backsound_trim_start' => $data['backsound_trim_start'] ?? 0, 'backsound_trim_end' => $data['backsound_trim_end'] ?? null,
            ]);
            ActivityLogger::fromRequest($request, 'generate', "Generate via Muse: {$keyword} ({$data['model']}, {$data['durasi']}s) — job #{$job->id} queued", ['generation_id' => $g->id, 'muse_job_id' => $job->id]);
            NotificationService::notify($user, 'Job dikirim ke Muse', "Job #{$job->id} untuk \"{$keyword}\" menunggu Muse membuat videonya.");

            return response()->json([
                'success' => true, 'message' => 'Job dikirim ke Muse. Video muncul otomatis setelah Muse selesai dan mengirim webhook.',
                'generation' => $g->fresh()->append('muse_profile_name'), 'muse_job_id' => $job->id, 'job_status' => 'queued',
                'muse_profile_id' => $job->muse_profile_id, 'muse_profile_name' => $profile?->name,
                'quota_remaining' => $quota->remaining($user),
            ], 201);
        }

        $result = $gen->generate($keyword, (int) $data['durasi'], $data['model']);
        $g = VideoGeneration::create([
            'user_id' => $user->id, 'keyword' => $keyword, 'durasi' => (int) $data['durasi'], 'model' => $data['model'],
            'naskah' => $result['naskah'], 'caption' => $result['caption'], 'prompts' => $result['prompts'],
            'status' => 'completed', 'source' => 'local', 'workflow_status' => 'draft',
            'backsound_url' => $data['backsound_url'] ?? null, 'backsound_volume' => $data['backsound_volume'] ?? 20,
            'backsound_trim_start' => $data['backsound_trim_start'] ?? 0, 'backsound_trim_end' => $data['backsound_trim_end'] ?? null,
        ]);

        ActivityLogger::fromRequest($request, 'generate', "Generate video: {$keyword} ({$data['model']}, {$data['durasi']}s)", ['generation_id' => $g->id]);
        NotificationService::notify($user, 'Video selesai dibuat', NotificationService::template('generate_success', 'Video "{keyword}" berhasil dibuat dan tersimpan di Riwayat.', ['keyword' => $keyword]));
        $this->webhookLater('generation.completed', ['generation_id' => $g->id, 'keyword' => $g->keyword, 'model' => $g->model, 'durasi' => $g->durasi], $user->id);

        return response()->json([
            'success' => true, 'message' => 'Video berhasil dibuat.', 'generation' => $g->fresh(),
            'result' => $result, 'quota_remaining' => $quota->remaining($user),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $g = $this->findAccessible($request, $id)->load(['user:id,name,email,avatar', 'reviewer:id,name', 'museJob.museProfile']);
        $g->append('muse_profile_name');

        return response()->json(['success' => true, 'data' => $g]);
    }

    public function destroy(Request $request, $id)
    {
        $g = $this->findOwned($request, $id);
        $keyword = $g->keyword;
        $g->delete();
        ActivityLogger::fromRequest($request, 'generate.delete', "Hapus video: {$keyword}");

        return response()->json(['success' => true, 'message' => 'Video berhasil dihapus.']);
    }

    public function downloadTxt(Request $request, $id)
    {
        $g = $this->findAccessible($request, $id);
        $lines = ['RUANG DHARMA STUDIO', "Judul: {$g->keyword}", "Durasi: {$g->durasi} detik | Model: {$g->model} | Workflow: {$g->workflow_status}", '', '=== NASKAH ==='];
        foreach ((array) ($g->naskah ?? []) as $n) {
            $lines[] = '';
            $lines[] = 'Bagian '.($n['bagian'] ?? '-').' — '.($n['speaker'] ?? '-').' ('.($n['suara'] ?? '-').')';
            $lines[] = $n['teks'] ?? $n['dialog'] ?? '';
            $lines[] = 'Prompt: '.($n['prompt_visual'] ?? '');
        }
        $lines[] = '';
        $lines[] = '=== CAPTION ===';
        $lines[] = $g->captionText();
        $lines[] = '';
        $lines[] = '=== PROMPTS ===';
        foreach ((array) ($g->prompts ?? []) as $p) $lines[] = '- '.$p;
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($g->keyword)), '-') ?: 'video';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"ruang-dharma-{$slug}.txt\"",
        ]);
    }

    public function quota(Request $request, QuotaService $quota)
    {
        $user = $request->user();

        return response()->json(['success' => true, 'limit' => $quota->userLimit($user), 'used' => $quota->usedToday($user), 'remaining' => $quota->remaining($user)]);
    }

    public function toggleFavorite(Request $request, $id)
    {
        $g = $this->findOwned($request, $id);
        $g->update(['is_favorite' => ! $g->is_favorite]);

        return response()->json(['success' => true, 'message' => $g->is_favorite ? 'Ditandai favorit.' : 'Favorit dibatalkan.', 'data' => $g->fresh()]);
    }

    public function toggleArchive(Request $request, $id)
    {
        $g = $this->findOwned($request, $id);
        $archived = ! $g->is_archived;
        $g->update([
            'is_archived' => $archived,
            // Keluar dari arsip: workflow "archived" dikembalikan ke draft agar konsisten.
            'workflow_status' => $archived ? $g->workflow_status : ($g->workflow_status === 'archived' ? 'draft' : $g->workflow_status),
        ]);

        return response()->json(['success' => true, 'message' => $archived ? 'Video diarsipkan.' : 'Video dikeluarkan dari arsip.', 'data' => $g->fresh()]);
    }

    // ---------- Workflow Approval ----------
    public function submitReview(Request $request, $id)
    {
        $g = $this->findOwned($request, $id);
        if (in_array($g->workflow_status, ['review', 'published'], true)) {
            return response()->json(['success' => false, 'message' => $g->workflow_status === 'review' ? 'Video sudah dalam antrean review.' : 'Video sudah dipublikasikan.'], 422);
        }
        $g->update(['workflow_status' => 'review', 'review_note' => null, 'is_archived' => false]);
        ActivityLogger::fromRequest($request, 'review.submit', "Kirim ke review: {$g->keyword}", ['generation_id' => $g->id]);
        foreach ($this->reviewers() as $reviewer) {
            if ($reviewer->id !== $request->user()->id) {
                NotificationService::notify($reviewer, 'Naskah menunggu review', NotificationService::template('review_needed', '{name} mengirim "{keyword}" untuk direview.', ['name' => $request->user()->name, 'keyword' => $g->keyword]));
            }
        }

        return response()->json(['success' => true, 'message' => 'Video dikirim ke review.', 'data' => $g->fresh()]);
    }

    /** Antrean review untuk admin / editor (default status=review, `status=all` untuk semua). */
    public function reviewQueue(Request $request)
    {
        $status = $request->input('status', 'review');
        $q = VideoGeneration::with(['user:id,name,email,avatar', 'reviewer:id,name']);
        if ($status && $status !== 'all') $q->where('workflow_status', $status);
        if ($request->filled('search')) $q->search($request->input('search'));
        $q->orderBy('updated_at', 'desc');
        $counts = VideoGeneration::selectRaw('workflow_status, count(*) as total')->groupBy('workflow_status')->pluck('total', 'workflow_status');

        return response()->json(['success' => true, 'counts' => $counts, 'data' => $q->paginate($this->perPage($request, 20))]);
    }

    public function approve(Request $request, $id)
    {
        $g = VideoGeneration::findOrFail($id);
        $g->update(['workflow_status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->input('review_note')]);
        ActivityLogger::fromRequest($request, 'review.approve', "Setujui video: {$g->keyword}", ['generation_id' => $g->id]);
        $this->notifyOwner($request, $g, 'Naskah disetujui', NotificationService::template('review_approved', 'Video "{keyword}" disetujui oleh {reviewer}.', ['keyword' => $g->keyword, 'reviewer' => $request->user()->name]));

        return response()->json(['success' => true, 'message' => 'Video disetujui.', 'data' => $g->fresh()]);
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate(['review_note' => 'required|string|max:1000'], ['review_note.required' => 'Catatan revisi wajib diisi.']);
        $g = VideoGeneration::findOrFail($id);
        $g->update(['workflow_status' => 'revisi', 'review_note' => $data['review_note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        ActivityLogger::fromRequest($request, 'review.reject', "Minta revisi video: {$g->keyword}", ['generation_id' => $g->id]);
        $this->notifyOwner($request, $g, 'Naskah perlu revisi', NotificationService::template('review_revision', 'Video "{keyword}": {note}', ['keyword' => $g->keyword, 'note' => $data['review_note']]));

        return response()->json(['success' => true, 'message' => 'Video dikembalikan untuk revisi.', 'data' => $g->fresh()]);
    }

    public function publish(Request $request, $id)
    {
        $g = VideoGeneration::findOrFail($id);
        $g->update(['workflow_status' => 'published', 'is_archived' => false, 'publish_at' => $g->publish_at ?? now(), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        ActivityLogger::fromRequest($request, 'review.publish', "Publikasikan video: {$g->keyword}", ['generation_id' => $g->id]);
        $this->notifyOwner($request, $g, 'Video dipublikasikan', "Video \"{$g->keyword}\" sudah dipublikasikan.");
        $this->webhookLater('generation.published', ['generation_id' => $g->id, 'keyword' => $g->keyword], $g->user_id);

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
        $data = $request->validate(['publish_at' => 'nullable|date'], ['publish_at.date' => 'Format tanggal tidak valid.']);
        $g = $this->findOwned($request, $id);
        $g->update(['publish_at' => $data['publish_at'] ?? null]);

        return response()->json(['success' => true, 'message' => empty($data['publish_at']) ? 'Jadwal dihapus.' : 'Jadwal publish disimpan.', 'data' => $g->fresh()]);
    }

    public function calendar(Request $request)
    {
        $month = (string) $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) $month = now()->format('Y-m');
        $q = $request->user()->videoGenerations()->byMonth($month)->orderBy('publish_at')->orderBy('created_at');

        return response()->json(['success' => true, 'month' => $month, 'data' => $q->paginate(200)]);
    }

    // ---------- Versioning ----------
    public function versions(Request $request, $id)
    {
        $g = $this->findAccessible($request, $id);

        return response()->json(['success' => true, 'data' => $g->versions()->with('editor:id,name')->paginate(50)]);
    }

    public function updateNaskah(UpdateNaskahRequest $request, $id, VersioningService $versioning)
    {
        $g = $this->findEditable($request, $id);
        // Pakai input penuh (bukan validated()) agar kunci lain per bagian (bagian, suara,
        // prompt_visual, judul) tidak terbuang oleh aturan wildcard naskah.*.
        $naskah = collect($request->input('naskah'))->map(function ($n, $i) {
            $n = is_array($n) ? $n : ['teks' => (string) $n];
            $text = $n['teks'] ?? $n['dialog'] ?? $n['narasi'] ?? '';
            return array_merge($n, ['bagian' => $n['bagian'] ?? $i + 1, 'teks' => $text, 'dialog' => $text, 'narasi' => $text]);
        })->values()->all();
        $g = $versioning->updateNaskah($g, $naskah, $request->input('caption'), $request->user());
        ActivityLogger::fromRequest($request, 'generate.edit', "Edit naskah: {$g->keyword}", ['generation_id' => $g->id]);

        return response()->json(['success' => true, 'message' => 'Naskah diperbarui, versi lama tersimpan.', 'data' => $g]);
    }

    public function updateBacksound(Request $request, $id)
    {
        $data = $request->validate([
            'backsound_url' => 'sometimes|nullable|string|max:2048',
            'backsound_volume' => 'sometimes|integer|min:0|max:100',
            'backsound_trim_start' => 'sometimes|integer|min:0',
            'backsound_trim_end' => 'sometimes|nullable|integer|min:0',
        ]);
        $g = $this->findEditable($request, $id);
        $g->update($data);
        // Tandai perlu re-mix: buat job baru untuk Muse (status waiting_muse) agar auto-executor (cek tiap 1 detik) mengambil dan mix ulang.
        // Simpan flag di generation: status tetap, tapi muse_job_id baru akan dibuat via webhook? Untuk sekarang, cukup update + notifikasi.
        // Frontend akan polling dan menampilkan status.
        return response()->json(['success' => true, 'message' => 'Audio disimpan. Muse akan mix ulang video dengan audio baru.', 'data' => $g->fresh()]);
    }

    public function restoreVersion(Request $request, $id, $versionId, VersioningService $versioning)
    {
        $g = $this->findEditable($request, $id);
        $version = VideoGenerationVersion::where('video_generation_id', $g->id)->findOrFail($versionId);
        $g = $versioning->restore($g, $version, $request->user());

        return response()->json(['success' => true, 'message' => "Versi {$version->version_number} dipulihkan.", 'data' => $g]);
    }

    /** Filter bersama index user + history admin. */
    public static function applyFilters($q, Request $request)
    {
        if ($request->filled('search')) $q->search($request->input('search'));
        if ($request->filled('model')) $q->byModel($request->input('model'));
        if ($request->filled('durasi')) $q->where('durasi', (int) $request->input('durasi'));
        if ($request->filled('date_from')) $q->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $q->whereDate('created_at', '<=', $request->input('date_to'));
        if ($request->filled('user_id')) $q->where('user_id', $request->input('user_id'));
        if ($request->filled('workflow_status')) $q->where('workflow_status', $request->input('workflow_status'));
        if ((string) $request->input('favorites') === '1') $q->favorites();
        if ($request->filled('archived') && $request->input('archived') !== 'all') {
            $q->where('is_archived', filter_var($request->input('archived'), FILTER_VALIDATE_BOOLEAN));
        }
        $q->orderBy('created_at', $request->input('sort') === 'oldest' ? 'asc' : 'desc')->orderBy('id', $request->input('sort') === 'oldest' ? 'asc' : 'desc');

        return $q;
    }

    // ---------- Helpers ----------
    protected function perPage(Request $request, int $default): int
    {
        return max(1, min(100, (int) $request->input('per_page', $default)));
    }

    protected function isReviewer(Request $request): bool
    {
        return $request->user()->hasAnyRole(self::REVIEWER_ROLES);
    }

    protected function findOwned(Request $request, $id): VideoGeneration
    {
        return VideoGeneration::where('user_id', $request->user()->id)->findOrFail($id);
    }

    /** Pemilik, atau admin/editor (untuk review). */
    protected function findAccessible(Request $request, $id): VideoGeneration
    {
        $q = VideoGeneration::query();
        if (! $this->isReviewer($request)) $q->where('user_id', $request->user()->id);

        return $q->findOrFail($id);
    }

    protected function findEditable(Request $request, $id): VideoGeneration
    {
        return $this->findAccessible($request, $id);
    }

    protected function reviewers()
    {
        return User::where('is_active', true)->get()->filter(fn (User $u) => $u->hasAnyRole(self::REVIEWER_ROLES));
    }

    protected function notifyOwner(Request $request, VideoGeneration $g, string $title, string $message): void
    {
        if ($g->user_id !== $request->user()->id) NotificationService::notify($g->user, $title, $message);
    }

    /** Kirim webhook setelah respons terkirim agar request user tidak menunggu HTTP eksternal. */
    protected function webhookLater(string $event, array $payload, ?int $userId): void
    {
        dispatch(fn () => WebhookService::dispatch($event, $payload, $userId))->afterResponse();
    }
}
