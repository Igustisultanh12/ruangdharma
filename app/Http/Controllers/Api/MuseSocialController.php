<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\SocialPublish;
use App\Models\SocialStat;
use App\Services\MuseService;
use Illuminate\Http\Request;
class MuseSocialController extends Controller
{
    private function check(Request $request, MuseService $muse): ?\Illuminate\Http\JsonResponse
    {
        if (!$muse->verifyToken($request->header('X-Muse-Token'), $request->bearerToken())) return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        return null;
    }
    public function pendingPublishes(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $jobs = SocialPublish::whereIn('status', ['queued','processing'])->with('generation:id,keyword,video_url,caption')->orderBy('created_at')->get();
        return response()->json(['success' => true, 'count' => $jobs->count(), 'data' => $jobs]);
    }
    public function publishWebhook(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate(['publish_id' => 'required|integer|exists:social_publishes,id', 'status' => 'required|in:completed,failed,processing', 'post_url' => 'sometimes|nullable|string|max:2048', 'post_id' => 'sometimes|nullable|string|max:255', 'error' => 'sometimes|nullable|string']);
        $pub = SocialPublish::findOrFail($data['publish_id']);
        if ($data['status'] === 'completed') $pub->markCompleted($data['post_url'] ?? null, $data['post_id'] ?? null);
        elseif ($data['status'] === 'failed') $pub->markFailed($data['error'] ?? 'Gagal publish');
        else $pub->markProcessing();
        return response()->json(['success' => true, 'publish_id' => $pub->id, 'status' => $pub->fresh()->status]);
    }
    public function statsWebhook(Request $request, MuseService $muse)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate(['platform' => 'required|in:instagram,facebook', 'account_username' => 'sometimes|nullable|string|max:100', 'followers' => 'sometimes|integer', 'total_likes' => 'sometimes|integer', 'total_comments' => 'sometimes|integer', 'reach' => 'sometimes|integer', 'views' => 'sometimes|integer', 'posts' => 'sometimes|array', 'recent_comments' => 'sometimes|array', 'daily' => 'sometimes|array']);
        $stat = SocialStat::updateOrCreate(['platform' => $data['platform']], array_merge($data, ['fetched_at' => now()]));
        return response()->json(['success' => true, 'platform' => $stat->platform, 'fetched_at' => $stat->fetched_at]);
    }
}
