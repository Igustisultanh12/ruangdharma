<?php
namespace App\Http\Controllers;
use App\Models\Backsound;
use App\Models\BacksoundRequest;
use App\Models\VideoGeneration;
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
        return response()->json(['success' => true, 'data' => $payload]);
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
