<?php
namespace App\Http\Controllers;
use App\Models\Setting;
use App\Models\SocialPublish;
use App\Models\SocialStat;
use App\Models\VideoGeneration;
use Illuminate\Http\Request;
class YoutubeController extends Controller
{
    /** Ringkasan halaman YouTube: statistik channel + status sinkron tiap video milik user. */
    public function overview(Request $request)
    {
        $stat = SocialStat::where('platform', 'youtube')->first();
        $gens = VideoGeneration::where('user_id', $request->user()->id)
            ->whereNotNull('video_url')->where('video_url', '!=', '')
            ->orderByDesc('id')->limit(50)
            ->get(['id', 'keyword', 'durasi', 'model', 'video_url', 'created_at']);
        $pubs = SocialPublish::where('platform', 'youtube')
            ->whereIn('generation_id', $gens->pluck('id'))
            ->orderByDesc('id')->get();
        $latest = [];
        foreach ($pubs as $p) {
            if (!isset($latest[$p->generation_id])) $latest[$p->generation_id] = $p;
        }
        $videos = $gens->map(function ($g) use ($latest) {
            $p = $latest[$g->id] ?? null;
            return [
                'id' => $g->id,
                'keyword' => $g->keyword,
                'durasi' => $g->durasi,
                'model' => $g->model,
                'video_url' => $g->video_url,
                'created_at' => $g->created_at,
                'caption' => $p ? $p->caption : $g->captionText(),
                'publish_id' => $p ? $p->id : null,
                'youtube_status' => $p ? $p->status : 'none',
                'youtube_post_url' => $p ? $p->post_url : null,
                'youtube_error' => $p ? $p->error : null,
                'youtube_published_at' => $p ? $p->published_at : null,
            ];
        });
        return response()->json([
            'success' => true,
            'channel' => $stat ? [
                'username' => $stat->account_username,
                'subscribers' => $stat->followers,
                'total_views' => $stat->views,
                'total_likes' => $stat->total_likes,
                'total_comments' => $stat->total_comments,
                'watch_hours' => is_array($stat->daily) ? ($stat->daily['watch_hours'] ?? null) : null,
                'posts' => $stat->posts,
                'fetched_at' => $stat->fetched_at,
            ] : null,
            'sync' => [
                'requested_at' => Setting::get('youtube_sync_requested_at'),
                'last_at' => Setting::get('youtube_sync_last_at'),
                'summary' => Setting::get('youtube_sync_summary'),
            ],
            'videos' => $videos,
        ]);
    }
    /**
     * Konfirmasi hasil deteksi: item 'detected' yang dipilih menjadi 'queued' (Muse meng-upload-nya
     * satu paket dengan caption dari web), yang tidak dipilih menjadi 'skipped'.
     */
    public function confirmUpload(Request $request)
    {
        $data = $request->validate([
            'publish_ids' => 'present|array', 'publish_ids.*' => 'integer',
            'skip_ids' => 'sometimes|array', 'skip_ids.*' => 'integer',
        ]);
        $base = SocialPublish::where('user_id', $request->user()->id)->where('platform', 'youtube')->where('status', 'detected');
        $queued = 0; $skipped = 0;
        if (!empty($data['publish_ids'])) {
            $queued = (clone $base)->whereIn('id', $data['publish_ids'])->update(['status' => 'queued', 'requested_at' => now()]);
        }
        if (!empty($data['skip_ids'])) {
            $skipped = (clone $base)->whereIn('id', $data['skip_ids'])->update(['status' => 'skipped']);
        }
        return response()->json(['success' => true, 'queued' => $queued, 'skipped' => $skipped, 'message' => "{$queued} video diantrekan untuk di-upload ke YouTube oleh Muse (satu paket dengan caption-nya)." . ($skipped ? " {$skipped} video dilewati." : '')]);
    }
    /** Minta sinkronisasi: Muse akan membandingkan video web vs channel YouTube lalu meng-upload yang belum ada. */
    public function sync(Request $request)
    {
        Setting::set('youtube_sync_requested_at', now()->toIso8601String());
        return response()->json(['success' => true, 'message' => 'Sinkronisasi YouTube diminta. Muse akan mengecek video yang belum ter-upload dan meng-upload-nya sebagai Shorts. Pantau progresnya di halaman ini.']);
    }
}
