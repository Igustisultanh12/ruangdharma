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
use Illuminate\Support\Facades\Process;
use Throwable;

class VideoGenerationController extends Controller
{
    public const REVIEWER_ROLES = ['admin', 'super_admin', 'editor'];

    /** Pesan bila proc_open dimatikan di php.ini (disable_functions) sehingga Process tidak bisa jalan. */
    public const PROC_DISABLED_MESSAGE = 'Server PHP mematikan proc_open/shell_exec (disable_functions). Aktifkan proc_open di aaPanel → PHP → Konfigurasi → hapus proc_open & shell_exec dari disable_functions, lalu restart PHP.';

    /** True bila exception menandakan proc_open / fungsi proses dimatikan di server. */
    protected function isProcDisabled(Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());
        foreach (['proc_open', 'disable_functions', 'undefined function', 'function is disabled', 'unable to launch a new process', 'unable to fork'] as $needle) {
            if (str_contains($msg, $needle)) return true;
        }

        return false;
    }

    /** Respons 500 standar bila Process tidak bisa jalan karena proc_open dimatikan. */
    protected function procDisabledResponse(Request $request, VideoGeneration $g, string $context)
    {
        ActivityLogger::fromRequest($request, 'backsound.mix_failed', "Mix gagal, proc_open dimatikan di server ({$context}): {$g->keyword}", ['generation_id' => $g->id]);

        return response()->json(['success' => false, 'message' => 'Mix gagal: ' . self::PROC_DISABLED_MESSAGE . ' Kolom backsound tetap tersimpan.'], 500);
    }

    /** Riwayat user + search/filter/sort/favorit/arsip + pagination. */
    public function index(Request $request)
    {
        $q = $request->user()->videoGenerations()->with('user:id,name,email', 'museJob.museProfile');
        $q = static::applyFilters($q, $request);

        $page = $q->paginate($this->perPage($request, 20));
        // KEAMANAN: profil Muse bersarang (muse_job.muse_profile) diganti safeArray()
        // agar api_token/webhook_secret tidak pernah ikut ter-serialize ke JSON.
        $page->setCollection($page->getCollection()->map(function ($g) {
            $g->append('muse_profile_name');
            $arr = $g->toArray();
            if (isset($arr['muse_job']['muse_profile']) && $g->museJob?->museProfile) {
                $arr['muse_job']['muse_profile'] = $g->museJob->museProfile->safeArray();
            }
            return $arr;
        }));
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

    /** Job draft harian dari Muse yang menunggu konfirmasi admin (menu Job Pending). */
    public function pendingConfirmation(Request $request)
    {
        $q = $request->user()->videoGenerations()->with('museJob.museProfile')
            ->where('status', 'pending_confirmation')->orderByDesc('created_at');
        $page = $q->paginate($this->perPage($request, 20));
        $page->setCollection($page->getCollection()->map(function ($g) {
            $g->append('muse_profile_name');
            $arr = $g->toArray();
            if (isset($arr['muse_job']['muse_profile']) && $g->museJob?->museProfile) {
                $arr['muse_job']['muse_profile'] = $g->museJob->museProfile->safeArray();
            }
            return $arr;
        }));
        return response()->json(['success' => true, 'data' => $page]);
    }

    /** Konfirmasi job pending: MuseJob menjadi queued sehingga Muse langsung men-generate videonya. */
    public function confirmJob(Request $request, $id)
    {
        $g = $request->user()->videoGenerations()->where('status', 'pending_confirmation')->findOrFail($id);
        if ($g->museJob) $g->museJob->update(['status' => 'queued']);
        $g->update(['status' => 'waiting_muse', 'caption' => 'Menunggu Muse — video sedang dibuat...']);
        ActivityLogger::fromRequest($request, 'generate', "Konfirmasi job pending #{$g->muse_job_id}: {$g->keyword} ({$g->model}, {$g->durasi}s) — mulai digenerate Muse", ['generation_id' => $g->id, 'muse_job_id' => $g->muse_job_id]);
        NotificationService::notify($request->user(), 'Job dikonfirmasi', "Video \"{$g->keyword}\" mulai dibuat oleh Muse.");
        return response()->json(['success' => true, 'message' => 'Job dikonfirmasi. Muse akan langsung membuat videonya.', 'data' => $g->fresh()]);
    }

    /** Tolak job pending: tidak akan digenerate. */
    public function rejectJob(Request $request, $id)
    {
        $g = $request->user()->videoGenerations()->where('status', 'pending_confirmation')->findOrFail($id);
        if ($g->museJob) $g->museJob->update(['status' => 'cancelled', 'error' => 'Ditolak admin dari menu Job Pending.']);
        $g->update(['status' => 'cancelled']);
        ActivityLogger::fromRequest($request, 'generate', "Tolak job pending #{$g->muse_job_id}: {$g->keyword}", ['generation_id' => $g->id, 'muse_job_id' => $g->muse_job_id]);
        return response()->json(['success' => true, 'message' => 'Job ditolak dan tidak akan dibuat.', 'data' => $g->fresh()]);
    }

    public function show(Request $request, $id)
    {
        $g = $this->findAccessible($request, $id)->load(['user:id,name,email,avatar', 'reviewer:id,name', 'museJob.museProfile']);
        $g->append('muse_profile_name');
        // KEAMANAN: profil Muse bersarang diganti safeArray() — tanpa token/secret utuh.
        $arr = $g->toArray();
        if (isset($arr['muse_job']['muse_profile']) && $g->museJob?->museProfile) {
            $arr['muse_job']['muse_profile'] = $g->museJob->museProfile->safeArray();
        }
        return response()->json(['success' => true, 'data' => $arr]);
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
            'backsound_trim_start' => 'sometimes|numeric|min:0',
            'backsound_trim_end' => 'sometimes|nullable|numeric|min:0',
        ]);
        $g = $this->findEditable($request, $id);
        $g->update($data);
        $g = $g->fresh();

        // Belum ada file video -> cukup simpan kolom, mix menyusul saat video dibuat.
        if (empty($g->video_url)) {
            return response()->json(['success' => true, 'message' => 'Backsound disimpan, akan dipakai saat video dibuat.', 'data' => $g]);
        }

        $volume = (int) ($g->backsound_volume ?? 20);
        $backsoundUrl = $g->backsound_url;

        // Backsound dihapus / dibisukan -> kembalikan ke video original (tanpa backsound) bila diketahui.
        if (empty($backsoundUrl) || $volume === 0) {
            $original = $this->originalVideoUrl($g);
            if ($original && $original !== $g->video_url) {
                $g->update(['video_url' => $original]);
                ActivityLogger::fromRequest($request, 'backsound.remove', "Backsound dihapus, video kembali ke audio asli: {$g->keyword}", ['generation_id' => $g->id]);

                return response()->json(['success' => true, 'message' => 'Backsound dihapus, video kembali ke audio asli.', 'data' => $g->fresh()]);
            }

            return response()->json(['success' => true, 'message' => 'Backsound disimpan.', 'data' => $g]);
        }

        // Mix nyata di server dengan ffmpeg (jangan mengandalkan Muse).
        // Sumber video SELALU dari original (bukan dari hasil mix sebelumnya),
        // agar backsound tidak menumpuk / mengeras setiap kali Simpan ditekan.
        $sourceUrl = $this->originalVideoUrl($g) ?? $g->video_url;
        $videoPath = $this->storageLocalPath($sourceUrl);
        $audioPath = $this->storageLocalPath($backsoundUrl);
        if (! $videoPath || ! file_exists($videoPath)) {
            ActivityLogger::fromRequest($request, 'backsound.mix_failed', "Mix gagal, file video asli tidak ditemukan: {$g->keyword}", ['generation_id' => $g->id]);

            return response()->json(['success' => false, 'message' => 'Mix gagal: file video asli tidak ditemukan di server. Kolom backsound tetap tersimpan, silakan coba lagi.'], 500);
        }
        if (! $audioPath || ! file_exists($audioPath)) {
            ActivityLogger::fromRequest($request, 'backsound.mix_failed', "Mix gagal, file backsound tidak ditemukan: {$g->keyword}", ['generation_id' => $g->id]);

            return response()->json(['success' => false, 'message' => 'Mix gagal: file backsound tidak ditemukan di server. Kolom backsound tetap tersimpan, silakan coba lagi.'], 500);
        }
        try {
            $which = Process::run(['which', 'ffmpeg']);
            $ffmpeg = trim($which->output());
        } catch (Throwable $e) {
            if ($this->isProcDisabled($e)) return $this->procDisabledResponse($request, $g, 'which ffmpeg');
            ActivityLogger::fromRequest($request, 'backsound.mix_failed', "Mix gagal, tidak bisa menjalankan which ffmpeg: {$g->keyword}", ['generation_id' => $g->id]);

            return response()->json(['success' => false, 'message' => 'Mix gagal: tidak bisa menjalankan ffmpeg di server. Kolom backsound tetap tersimpan, silakan coba lagi.'], 500);
        }
        if ($ffmpeg === '') {
            ActivityLogger::fromRequest($request, 'backsound.mix_failed', "Mix gagal, ffmpeg tidak tersedia: {$g->keyword}", ['generation_id' => $g->id]);

            return response()->json(['success' => false, 'message' => 'Mix gagal: ffmpeg tidak tersedia di server. Minta admin menginstal ffmpeg (which ffmpeg harus ada). Kolom backsound tetap tersimpan.'], 500);
        }

        $videoDur = $this->probeMediaDuration($videoPath);
        $audioDur = $this->probeMediaDuration($audioPath);
        $start = max(0, (float) ($g->backsound_trim_start ?? 0));
        $end = $g->backsound_trim_end !== null ? (float) $g->backsound_trim_end : null;
        if ($audioDur !== null && $start >= $audioDur) $start = 0;
        if ($end !== null) {
            if ($end <= $start) $end = $start + 0.5;
            if ($audioDur !== null && $end > $audioDur) $end = $audioDur;
        }
        $vol = max(0, min(100, $volume)) / 100;

        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($g->keyword)), '-') ?: 'video';
        $outRel = 'videos/' . $slug . '-mix-' . $g->id . '-' . time() . '.mp4';
        $outFull = storage_path('app/public/' . $outRel);
        if (! is_dir(dirname($outFull))) mkdir(dirname($outFull), 0755, true);

        $hasAudio = $this->videoHasAudio($videoPath);
        $startStr = rtrim(rtrim(number_format($start, 2, '.', ''), '0'), '.') ?: '0';
        $volStr = rtrim(rtrim(number_format($vol, 4, '.', ''), '0'), '.') ?: '0';
        if ($end !== null) {
            $endStr = rtrim(rtrim(number_format($end, 2, '.', ''), '0'), '.') ?: '0';
            $bg = "[1:a]atrim=start={$startStr}:end={$endStr},asetpts=PTS-STARTPTS,volume={$volStr},aloop=loop=-1:size=2e+09[bg]";
        } else {
            $bg = "[1:a]atrim=start={$startStr},asetpts=PTS-STARTPTS,volume={$volStr},aloop=loop=-1:size=2e+09[bg]";
        }
        if ($hasAudio) {
            $filter = "[0:a]aformat=sample_fmts=fltp:channel_layouts=stereo[orig];{$bg};[orig][bg]amix=inputs=2:duration=first:dropout_transition=0:normalize=0[aout]";
            $ffmpegArgs = [$ffmpeg, '-y', '-i', $videoPath, '-i', $audioPath,
                '-filter_complex', $filter,
                '-map', '0:v', '-map', '[aout]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', $outFull];
        } else {
            // Video tanpa track audio: backsound menjadi satu-satunya audio, dibatasi durasi video.
            $filter = "{$bg};[bg]anull[aout]";
            // aloop pada [bg] sudah menghasilkan stream bernama [bg]; anull meneruskannya ke [aout].
            $ffmpegArgs = [$ffmpeg, '-y', '-i', $videoPath, '-i', $audioPath,
                '-filter_complex', $filter,
                '-map', '0:v', '-map', '[aout]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart'];
            if ($videoDur !== null && $videoDur > 0) $ffmpegArgs[] = '-t';
            if ($videoDur !== null && $videoDur > 0) $ffmpegArgs[] = (string) $videoDur;
            $ffmpegArgs[] = $outFull;
        }

        try {
            $ffmpegResult = Process::timeout(120)->run($ffmpegArgs);
            $ffmpegOut = $ffmpegResult->output() . "\n" . $ffmpegResult->errorOutput();
        } catch (Throwable $e) {
            if ($this->isProcDisabled($e)) return $this->procDisabledResponse($request, $g, 'ffmpeg mix');
            ActivityLogger::fromRequest($request, 'backsound.mix_failed', "Mix gagal, ffmpeg tidak bisa dijalankan: {$g->keyword}", ['generation_id' => $g->id]);

            return response()->json(['success' => false, 'message' => 'Mix gagal: ffmpeg tidak bisa memproses file ini. Kolom backsound tetap tersimpan, silakan coba lagi.'], 500);
        }
        if (! file_exists($outFull) || filesize($outFull) <= 0) {
            if (file_exists($outFull)) @unlink($outFull);
            $detail = mb_substr(trim(preg_replace('/\s+/', ' ', $ffmpegOut)), -500);
            ActivityLogger::fromRequest($request, 'backsound.mix_failed', "Mix gagal (ffmpeg): {$g->keyword} — {$detail}", ['generation_id' => $g->id]);

            return response()->json(['success' => false, 'message' => 'Mix gagal: ffmpeg tidak bisa memproses file ini. Kolom backsound tetap tersimpan, silakan coba lagi. Detail: ' . $detail], 500);
        }

        $newUrl = '/storage/' . $outRel;
        $files = array_values(array_unique(array_merge((array) ($g->video_files ?? []), [$sourceUrl, $newUrl])));
        $g->update(['video_url' => $newUrl, 'video_files' => $files]);
        ActivityLogger::fromRequest($request, 'backsound.mixed', "Backsound di-mix ke video: {$g->keyword} (volume {$volume}%, trim {$startStr}s – " . ($end !== null ? $endStr . 's' : 'habis') . ')', ['generation_id' => $g->id]);

        return response()->json(['success' => true, 'message' => 'Audio berhasil di-mix ke video.', 'data' => $g->fresh(), 'video_url' => $newUrl]);
    }

    /**
     * URL video ORIGINAL (tanpa backsound): entri video_files pertama yang
     * bukan hasil mix (-mix-), atau video_url saat ini bila belum pernah di-mix.
     * Return null bila original tidak dapat dipastikan (video_url saat ini sudah mix
     * tapi tidak ada entri non-mix di video_files).
     */
    protected function originalVideoUrl(VideoGeneration $g): ?string
    {
        foreach ((array) ($g->video_files ?? []) as $f) {
            if (is_string($f) && $f !== '' && ! str_contains($f, '-mix-')) return $f;
        }
        if (is_string($g->video_url) && $g->video_url !== '' && ! str_contains($g->video_url, '-mix-')) return $g->video_url;

        return null;
    }

    /**
     * Ubah video_url / backsound_url (/storage/... atau URL penuh) menjadi
     * path lokal storage/app/public/... Return null bila tidak dapat dipetakan
     * atau mengandung path traversal.
     */
    protected function storageLocalPath(?string $url): ?string
    {
        if (! $url) return null;
        if (file_exists($url)) return $url;
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = ltrim((string) $path, '/');
        if (str_starts_with($path, 'storage/')) {
            $rel = substr($path, strlen('storage/'));
        } elseif (str_contains($path, '/storage/')) {
            $rel = substr($path, strpos($path, '/storage/') + strlen('/storage/'));
        } elseif (str_starts_with($path, 'videos/') || str_starts_with($path, 'backsounds/')) {
            $rel = $path;
        } else {
            return null;
        }
        if ($rel === '' || str_contains($rel, '..')) return null;

        return storage_path('app/public/' . $rel);
    }

    /** Durasi media (detik) via ffprobe, null bila gagal. */
    protected function probeMediaDuration(string $path): ?float
    {
        try {
            $result = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $path]);
            if (! $result->successful()) return null;
            $out = $result->output();
        } catch (Throwable $e) {
            // proc_open dimatikan -> durasi tidak diketahui; pemanggil (which/ffmpeg)
            // akan menampilkan pesan actionable. Jangan lempar error mentah ke user.
            return null;
        }
        $v = (float) trim($out);

        return $v > 0 ? $v : null;
    }

    /** Apakah file video punya track audio? */
    protected function videoHasAudio(string $path): bool
    {
        try {
            $result = Process::run(['ffprobe', '-v', 'error', '-select_streams', 'a', '-show_entries', 'stream=index', '-of', 'csv=p=0', $path]);

            return $result->successful() && trim($result->output()) !== '';
        } catch (Throwable $e) {
            return false;
        }
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
