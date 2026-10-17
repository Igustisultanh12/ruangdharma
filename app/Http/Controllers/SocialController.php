<?php
namespace App\Http\Controllers;
use App\Models\SocialPublish;
use App\Models\SocialStat;
use App\Models\VideoGeneration;
use Illuminate\Http\Request;
class SocialController extends Controller
{
    public function stats(Request $request)
    {
        $platform = $request->input('platform', 'instagram');
        $stat = SocialStat::where('platform', $platform)->first();
        return response()->json(['success' => true, 'platform' => $platform, 'data' => $stat, 'connected' => (bool) $stat, 'hint' => $stat ? null : 'Belum ada data. Klik Refresh agar Muse mengambil statistik terbaru dari Instagram / Facebook.']);
    }
    public function publishes(Request $request)
    {
        $q = SocialPublish::with('generation:id,keyword,video_url,caption')->where('user_id', $request->user()->id)->orderByDesc('created_at');
        if ($request->filled('platform')) $q->where('platform', $request->input('platform'));
        return response()->json(['success' => true, 'data' => $q->paginate(20)]);
    }
    public function publish(Request $request)
    {
        $data = $request->validate([
            'generation_id' => 'required|integer|exists:video_generations,id',
            'platform' => 'required|in:instagram,facebook,tiktok',
        ]);
        $gen = VideoGeneration::where('id', $data['generation_id'])->where('user_id', $request->user()->id)->firstOrFail();
        if ($data['platform'] === 'tiktok') {
            // TikTok manual: tidak auto-post via API, kembalikan instruksi download + salin caption.
            return response()->json(['success' => true, 'manual' => true, 'platform' => 'tiktok', 'message' => 'TikTok masih manual: download video & salin caption, lalu upload di aplikasi TikTok.', 'video_url' => $gen->video_url, 'caption' => $gen->captionText()], 200);
        }
        if (!$gen->video_url) return response()->json(['success' => false, 'message' => 'Video belum ada file-nya. Generate / upload dulu via Muse.'], 422);
        $pub = SocialPublish::create(['user_id' => $request->user()->id, 'generation_id' => $gen->id, 'platform' => $data['platform'], 'status' => 'queued', 'caption' => $gen->captionText(), 'video_url' => $gen->video_url, 'requested_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Job publish dikirim ke Muse. Muse akan upload ke ' . ucfirst($data['platform']) . ' dan kirim hasil balik.', 'data' => $pub], 201);
    }
    public function refresh(Request $request)
    {
        // Tandai perlu refresh: Muse akan melihat via pending + webhook stats. Simpan flag di settings.
        \App\Models\Setting::set('social_refresh_requested_at', now()->toIso8601String());
        return response()->json(['success' => true, 'message' => 'Permintaan refresh statistik dikirim ke Muse. Buka chat Muse dan bilang "refresh statistik sosmed" agar Muse mengambil data terbaru.']);
    }
}
