<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Backsound;
use App\Models\BacksoundRequest;
use App\Services\MuseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
class MuseBacksoundController extends Controller
{
    private function check(Request $request, MuseService $muse): ?\Illuminate\Http\JsonResponse
    {
        if (!$muse->verifyToken($request->header('X-Muse-Token'), $request->bearerToken())) return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        return null;
    }
    /**
     * Permintaan Auto Backsound yang menunggu dicarikan: status queued (baru)
     * atau rejected (user menolak kandidat sebelumnya, minta dicarikan yang lain).
     */
    public function pending(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $reqs = BacksoundRequest::whereIn('status', ['queued', 'rejected'])
            ->orderBy('id')->limit(20)->get();
        $data = $reqs->map(function ($br) {
            $g = $br->generation;
            return [
                'id' => $br->id,
                'generation_id' => $br->generation_id,
                'status' => $br->status,
                'note' => $br->note,
                'previous_candidate_name' => $br->status === 'rejected' ? $br->candidate_name : null,
                'keyword' => $g?->keyword,
                'caption' => $g?->captionText(),
                'durasi' => $g?->durasi,
                'model' => $g?->model,
                'video_url' => $g?->video_url,
                'created_at' => $br->created_at,
            ];
        });
        return response()->json(['success' => true, 'count' => $data->count(), 'data' => $data]);
    }
    /** Muse menandai progres: searching (mulai mencari) atau failed (gagal, dengan pesan). */
    public function status(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate([
            'request_id' => 'required|integer|exists:backsound_requests,id',
            'status' => 'required|in:searching,failed',
            'error' => 'sometimes|nullable|string|max:1000',
        ]);
        $br = BacksoundRequest::findOrFail($data['request_id']);
        if (!in_array($br->status, ['queued', 'rejected', 'searching'], true)) {
            return response()->json(['success' => false, 'message' => 'Status permintaan sudah tidak bisa diubah: ' . $br->status], 422);
        }
        $br->update(['status' => $data['status'], 'error' => $data['status'] === 'failed' ? ($data['error'] ?? 'Pencarian backsound gagal.') : null]);
        return response()->json(['success' => true, 'data' => $br->fresh()]);
    }
    /**
     * Muse mengunggah kandidat backsound hasil pencarian (multipart).
     * Kandidat disimpan sebagai file audio + baris Backsound milik pemilik video,
     * lalu permintaan menjadi 'preview' — menunggu user mendengarkan & menyetujui
     * di web sebelum digabungkan. TIDAK ada penggabungan otomatis di langkah ini.
     */
    public function candidate(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate(
            [
                'request_id' => 'required|integer|exists:backsound_requests,id',
                'name' => 'required|string|max:150',
                'source' => 'sometimes|nullable|string|max:255',
                'file' => 'required|file|extensions:mp3,wav,m4a,ogg|max:20480',
            ],
            ['file.extensions' => 'Format audio harus MP3, WAV, M4A, atau OGG.', 'file.max' => 'Ukuran file maksimal 20MB.']
        );
        $br = BacksoundRequest::findOrFail($data['request_id']);
        if (!in_array($br->status, ['queued', 'rejected', 'searching'], true)) {
            return response()->json(['success' => false, 'message' => 'Permintaan sudah tidak menunggu kandidat: ' . $br->status], 422);
        }
        $path = $request->file('file')->store('backsounds', 'public');
        $duration = null;
        try {
            $full = storage_path('app/public/' . $path);
            $result = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $full]);
            if ($result->successful() && trim($result->output()) !== '') $duration = round((float) trim($result->output()), 1);
        } catch (\Throwable $e) {}
        $fileUrl = '/storage/' . $path;
        Backsound::create(['user_id' => $br->user_id, 'name' => $data['name'], 'file_url' => $fileUrl, 'duration_sec' => $duration]);
        $br->update([
            'status' => 'preview',
            'candidate_name' => $data['name'],
            'candidate_url' => $fileUrl,
            'candidate_source' => $data['source'] ?? null,
            'candidate_duration_sec' => $duration,
            'error' => null,
        ]);
        return response()->json(['success' => true, 'message' => 'Kandidat tersimpan, menunggu persetujuan user.', 'data' => $br->fresh()], 201);
    }
}
