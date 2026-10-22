<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialPublish;
use App\Models\VideoGeneration;
use App\Services\MuseService;
use Illuminate\Http\Request;
class MuseYoutubeController extends Controller
{
    private function check(Request $request, MuseService $muse): ?\Illuminate\Http\JsonResponse
    {
        if (!$muse->verifyToken($request->header('X-Muse-Token'), $request->bearerToken())) return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        return null;
    }
    /** Apakah ada permintaan sinkronisasi YouTube dari web? */
    public function syncPending(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $at = Setting::get('youtube_sync_requested_at');
        return response()->json(['success' => true, 'requested' => !empty($at), 'requested_at' => $at]);
    }
    /** Semua video yang sudah punya file, beserta status publish YouTube terakhirnya. */
    public function videos(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $gens = VideoGeneration::whereNotNull('video_url')->where('video_url', '!=', '')
            ->orderByDesc('id')->limit(100)->get();
        $pubs = SocialPublish::where('platform', 'youtube')
            ->whereIn('generation_id', $gens->pluck('id'))
            ->orderByDesc('id')->get();
        $latest = [];
        foreach ($pubs as $p) {
            if (!isset($latest[$p->generation_id])) $latest[$p->generation_id] = $p;
        }
        $data = $gens->map(function ($g) use ($latest) {
            $p = $latest[$g->id] ?? null;
            return [
                'id' => $g->id,
                'user_id' => $g->user_id,
                'keyword' => $g->keyword,
                'durasi' => $g->durasi,
                'model' => $g->model,
                'video_url' => $g->video_url,
                'caption' => $g->captionText(),
                'youtube' => $p ? [
                    'publish_id' => $p->id,
                    'status' => $p->status,
                    'post_url' => $p->post_url,
                    'post_id' => $p->post_id,
                ] : null,
            ];
        });
        return response()->json(['success' => true, 'count' => $data->count(), 'data' => $data]);
    }
    /** Buat job publish YouTube (queued) untuk satu generation — idempotent. */
    public function queueUpload(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate(['generation_id' => 'required|integer|exists:video_generations,id']);
        $existing = SocialPublish::where('platform', 'youtube')
            ->where('generation_id', $data['generation_id'])
            ->whereIn('status', ['queued', 'processing', 'completed'])
            ->orderByDesc('id')->first();
        if ($existing) return response()->json(['success' => true, 'exists' => true, 'data' => $existing]);
        $gen = VideoGeneration::findOrFail($data['generation_id']);
        if (!$gen->video_url) return response()->json(['success' => false, 'message' => 'Generation belum punya video_url.'], 422);
        $pub = SocialPublish::create([
            'user_id' => $gen->user_id,
            'generation_id' => $gen->id,
            'platform' => 'youtube',
            'status' => 'queued',
            'caption' => $gen->captionText(),
            'video_url' => $gen->video_url,
            'requested_at' => now(),
        ]);
        return response()->json(['success' => true, 'exists' => false, 'data' => $pub], 201);
    }
    /** Tandai sinkronisasi selesai: bersihkan flag + simpan ringkasan. */
    public function syncDone(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate(['summary' => 'sometimes|nullable|string|max:2000']);
        Setting::set('youtube_sync_requested_at', '');
        Setting::set('youtube_sync_last_at', now()->toIso8601String());
        Setting::set('youtube_sync_summary', $data['summary'] ?? '');
        return response()->json(['success' => true, 'last_at' => Setting::get('youtube_sync_last_at')]);
    }
}
