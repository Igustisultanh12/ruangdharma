<?php
namespace App\Http\Controllers;
use App\Models\Backsound;
use App\Models\BacksoundRequest;
use App\Models\VideoGeneration;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
class BacksoundController extends Controller
{
    public function index(Request $request)
    {
        $q = Backsound::where('user_id', $request->user()->id)->orderByDesc('created_at');
        return response()->json(['success' => true, 'data' => $q->paginate(20)]);
    }
    public function store(Request $request)
    {
        // Validasi berbasis EKSTENSI (bukan deteksi MIME fileinfo saja): file MP3 dari
        // CapCut/WA/HP sering terdeteksi MIME lain sehingga gagal walau .mp3.
        // 'extensions' (Laravel 11) memeriksa ekstensi file. Maks 20MB.
        $data = $request->validate(
            ['name' => 'required|string|max:100', 'file' => 'required|file|extensions:mp3,wav,m4a,ogg|max:20480'],
            ['file.extensions' => 'Format audio harus MP3, WAV, M4A, atau OGG.', 'file.max' => 'Ukuran file maksimal 20MB.']
        );
        $path = $request->file('file')->store('backsounds', 'public');
        // Durasi dihitung via ffprobe di server jika tersedia, fallback null
        $duration = null;
        try {
            $full = storage_path('app/public/' . $path);
            $result = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $full]);
            if ($result->successful() && trim($result->output()) !== '') $duration = round((float) trim($result->output()), 1); // 1 desimal, JANGAN (int) — durasi dipakai sebagai batas atas trim
        } catch (\Throwable $e) {}
        $bs = Backsound::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'file_url' => '/storage/' . $path, 'duration_sec' => $duration]);
        return response()->json(['success' => true, 'message' => 'Backsound diunggah.', 'data' => $bs], 201);
    }
    // ---------- Auto Backsound (Muse carikan, user preview & setujui dulu) ----------
    private function findOwnedGeneration(Request $request, $id): VideoGeneration
    {
        // Samakan dengan VideoGenerationController::findAccessible (dipakai Editor Audio):
        // pemilik boleh, dan reviewer (admin/super_admin/editor) boleh untuk semua video.
        // Cek pemilik-saja di sini sebelumnya membuat admin yang membuka video milik akun
        // lain (mis. dari halaman Media) ditolak 403 saat menekan "Minta Auto Backsound".
        $g = VideoGeneration::findOrFail($id);
        $user = $request->user();
        if ($g->user_id !== $user->id && !$user->hasAnyRole(['admin', 'super_admin', 'editor'])) abort(403);
        return $g;
    }
    private function latestAutoRequest($generationId): ?BacksoundRequest
    {
        return BacksoundRequest::where('generation_id', $generationId)->orderByDesc('id')->first();
    }
    /** Minta Muse mencarikan backsound untuk video ini (satu permintaan terbuka per video). */
    public function autoRequest(Request $request, $id)
    {
        $data = $request->validate(['note' => 'sometimes|nullable|string|max:255']);
        $g = $this->findOwnedGeneration($request, $id);
        $open = BacksoundRequest::where('generation_id', $g->id)->whereIn('status', BacksoundRequest::OPEN_STATUSES)->orderByDesc('id')->first();
        if ($open) return response()->json(['success' => true, 'message' => 'Sudah ada permintaan Auto Backsound yang aktif untuk video ini.', 'data' => $open]);
        $br = BacksoundRequest::create(['user_id' => $request->user()->id, 'generation_id' => $g->id, 'status' => 'queued', 'note' => $data['note'] ?? null]);
        return response()->json(['success' => true, 'message' => 'Permintaan terkirim. Muse akan mencarikan backsound yang cocok, lalu kamu bisa mendengarkan preview-nya di sini.', 'data' => $br], 201);
    }
    /** Permintaan Auto Backsound terakhir untuk video ini (untuk polling status & preview). */
    public function autoShow(Request $request, $id)
    {
        $g = $this->findOwnedGeneration($request, $id);
        $latest = $this->latestAutoRequest($g->id);
        if (!$latest) return response()->json(['success' => true, 'data' => null]);
        $payload = $latest->toArray();
        // Riwayat hasil mix ditampilkan di bawah panel Auto Backsound (Media + Editor Audio).
        $payload['history'] = $this->autoHistory($g);
        // Daftar versi video yang bisa dipilih untuk upload (asli + tiap hasil mix).
        $payload['versions'] = $this->videoVersions($g);
        $payload['active_video_url'] = $g->video_url;
        $payload['original_video_url'] = $this->originalVideoUrlFor($g);
        return response()->json(['success' => true, 'data' => $payload]);
    }
    /** URL video ORIGINAL (tanpa backsound): entri video_files pertama yang bukan -mix-. */
    private function originalVideoUrlFor(VideoGeneration $g): ?string
    {
        foreach ((array) ($g->video_files ?? []) as $f) {
            if (is_string($f) && $f !== '' && ! str_contains($f, '-mix-')) return $f;
        }
        if (is_string($g->video_url) && $g->video_url !== '' && ! str_contains($g->video_url, '-mix-')) return $g->video_url;
        return null;
    }
    /** Samakan bentuk URL video (relatif /storage/... ) agar perbandingan versi tidak gagal karena URL penuh. */
    private function normalizeVideoUrl(?string $url): ?string
    {
        if (! $url) return null;
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = '/' . ltrim((string) $path, '/');
        if (str_contains($path, '/storage/')) return '/storage/' . substr($path, strpos($path, '/storage/') + strlen('/storage/'));
        return $path;
    }
    /**
     * Daftar versi video untuk video ini, urutan: asli dulu, lalu hasil mix terbaru.
     * Tiap versi: key, label, video_url, candidate_name, applied_volume, finished_at, is_active.
     * is_active = versi yang dipakai video_url saat ini — dan karena publish sosmed
     * (Medsos/YouTube) membaca video_url, versi aktif inilah yang ter-upload.
     */
    private function videoVersions(VideoGeneration $g): array
    {
        $active = $this->normalizeVideoUrl($g->video_url);
        $versions = [];
        $seen = [];
        $push = function ($key, $label, $url, $candidate, $volume, $finished) use (&$versions, &$seen, $active) {
            $norm = $this->normalizeVideoUrl($url);
            if (! $norm || isset($seen[$norm])) return;
            $seen[$norm] = true;
            $versions[] = ['key' => $key, 'label' => $label, 'video_url' => $norm, 'candidate_name' => $candidate, 'applied_volume' => $volume, 'finished_at' => $finished, 'is_active' => $norm === $active];
        };
        $original = $this->originalVideoUrlFor($g);
        if ($original) $push('original', 'Asli (tanpa backsound)', $original, null, null, null);
        foreach ($this->autoHistory($g) as $h) {
            if (!empty($h['result_video_url'])) $push('mix-' . $h['id'], $h['candidate_name'] ?: 'Mix backsound', $h['result_video_url'], $h['candidate_name'], $h['applied_volume'], $h['finished_at']);
        }
        // File -mix- lain di video_files (mis. mix manual dari Editor Audio) yang
        // tidak tercatat di riwayat Auto Backsound tetap bisa dipilih.
        foreach ((array) ($g->video_files ?? []) as $f) {
            if (is_string($f) && str_contains($f, '-mix-')) $push('mix-file-' . md5($f), 'Mix backsound', $f, null, null, null);
        }
        // Bila video_url aktif tidak ada di daftar (mis. file diunggah manual),
        // tampilkan juga agar user tahu versi mana yang sedang aktif.
        if ($g->video_url) $push('current', 'Versi aktif saat ini', $g->video_url, null, null, null);
        return $versions;
    }
    /**
     * Pilih versi video yang dipakai untuk upload: asli atau salah satu hasil mix.
     * Hanya mengganti video_url aktif (tanpa mix ulang, tanpa menghapus versi lain).
     * URL wajib salah satu versi yang dikenal untuk video ini — tidak bisa sembarang URL.
     */
    public function useVideoVersion(Request $request, $id)
    {
        $data = $request->validate(['video_url' => 'required|string|max:2048']);
        $g = $this->findOwnedGeneration($request, $id);
        $wanted = $this->normalizeVideoUrl($data['video_url']);
        $allowed = collect($this->videoVersions($g))->pluck('video_url')->all();
        if (! $wanted || ! in_array($wanted, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Versi video ini tidak dikenali untuk video ini.'], 422);
        }
        // Pastikan filenya benar-benar ada di storage sebelum dijadikan versi aktif.
        $rel = ltrim(substr($wanted, strlen('/storage/')), '/');
        if (! Storage::disk('public')->exists($rel)) {
            return response()->json(['success' => false, 'message' => 'File versi ini tidak ditemukan di server.'], 422);
        }
        $g->update(['video_url' => $wanted]);
        ActivityLogger::fromRequest($request, 'video.version', "Versi video aktif diubah: {$g->keyword} -> {$wanted}", ['generation_id' => $g->id]);
        return response()->json(['success' => true, 'message' => 'Versi video untuk upload berhasil diubah.', 'data' => $g->fresh(), 'versions' => $this->videoVersions($g->fresh()), 'active_video_url' => $wanted], 200);
    }
    /**
     * Riwayat mix Auto Backsound yang sudah selesai untuk video ini (terbaru di atas).
     * Baris lama (sebelum kolom result_video_url ada) dipasangkan kronologis dengan
     * file ...-mix-... di video_files yang belum terpakai baris lain.
     */
    private function autoHistory(VideoGeneration $g): array
    {
        $done = BacksoundRequest::where('generation_id', $g->id)->where('status', 'done')->orderBy('id')->get();
        $files = is_array($g->video_files) ? $g->video_files : [];
        $mixFiles = collect($files)->filter(fn ($f) => is_string($f) && str_contains($f, '-mix-'))->values();
        $used = $done->pluck('result_video_url')->filter()->all();
        $pool = $mixFiles->reject(fn ($f) => in_array($f, $used, true))->values();
        $items = [];
        foreach ($done as $r) {
            $url = $r->result_video_url;
            if (!$url && $pool->isNotEmpty()) $url = $pool->shift();
            $items[] = [
                'id' => $r->id,
                'candidate_name' => $r->candidate_name,
                'candidate_source' => $r->candidate_source,
                'applied_volume' => $r->applied_volume,
                'result_video_url' => $url,
                'finished_at' => $r->updated_at,
            ];
        }
        return array_reverse($items);
    }
    /**
     * User menyetujui kandidat dari preview -> gabungkan ke video.
     * Penggabungan memakai alur mix server yang sudah ada (updateBacksound):
     * selalu dari video original, volume dari pilihan user, audio kandidat di-loop penuh.
     */
    public function autoApply(Request $request, $id)
    {
        $data = $request->validate(['volume' => 'sometimes|integer|min:0|max:100']);
        $g = $this->findOwnedGeneration($request, $id);
        $br = $this->latestAutoRequest($g->id);
        if (!$br || $br->status !== 'preview' || empty($br->candidate_url)) {
            return response()->json(['success' => false, 'message' => 'Belum ada kandidat backsound yang siap digabungkan.'], 422);
        }
        $sub = Request::create('/api/generations/' . $g->id . '/backsound', 'PUT', [
            'backsound_url' => $br->candidate_url,
            'backsound_volume' => $data['volume'] ?? 20,
            'backsound_trim_start' => 0,
            'backsound_trim_end' => null,
        ]);
        $sub->setUserResolver(fn () => $request->user());
        $response = app(VideoGenerationController::class)->updateBacksound($sub, $g->id);
        $payload = json_decode($response->getContent(), true);
        if ($response->getStatusCode() === 200 && ($payload['success'] ?? false)) {
            // Simpan hasil mix untuk riwayat di panel: file video hasil mix + volume.
            $br->update(['status' => 'done', 'error' => null, 'applied_volume' => (int) ($data['volume'] ?? 20), 'result_video_url' => $g->fresh()->video_url]);
            $payload['auto_backsound'] = $br->fresh();
        }
        return response()->json($payload, $response->getStatusCode());
    }
    /** User menolak kandidat di preview -> Muse mencarikan yang lain. */
    public function autoReject(Request $request, $id)
    {
        $g = $this->findOwnedGeneration($request, $id);
        $br = $this->latestAutoRequest($g->id);
        if (!$br || $br->status !== 'preview') {
            return response()->json(['success' => false, 'message' => 'Tidak ada kandidat yang bisa ditolak saat ini.'], 422);
        }
        $br->update(['status' => 'rejected', 'error' => null]);
        return response()->json(['success' => true, 'message' => 'Baik, Muse akan mencarikan backsound lain.', 'data' => $br->fresh()]);
    }
    /** Batalkan permintaan Auto Backsound yang masih terbuka. */
    public function autoCancel(Request $request, $id)
    {
        $g = $this->findOwnedGeneration($request, $id);
        $br = $this->latestAutoRequest($g->id);
        if (!$br || !$br->isOpen()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada permintaan Auto Backsound aktif.'], 422);
        }
        $br->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'Permintaan Auto Backsound dibatalkan.', 'data' => $br->fresh()]);
    }
    public function destroy(Request $request, Backsound $backsound)
    {
        if ($backsound->user_id !== $request->user()->id) abort(403);
        $backsound->delete();
        return response()->json(['success' => true, 'message' => 'Backsound dihapus.']);
    }
}
