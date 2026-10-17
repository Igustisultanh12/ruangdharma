<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\VideoGeneration;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /** Komentar hanya untuk pemilik video atau admin/editor (dulu siapa pun bisa membaca & menulis). */
    protected function accessible(Request $request, $generationId): VideoGeneration
    {
        $q = VideoGeneration::query();
        if (! $request->user()->hasAnyRole(VideoGenerationController::REVIEWER_ROLES)) $q->where('user_id', $request->user()->id);
        return $q->findOrFail($generationId);
    }

    public function index(Request $request, $generationId)
    {
        $g = $this->accessible($request, $generationId);
        $q = Comment::where('video_generation_id', $g->id)->whereNull('parent_id')->with(['user:id,name,avatar', 'replies.user:id,name,avatar'])->latest();
        return response()->json(['success' => true, 'data' => $q->paginate((int) $request->input('per_page', 20))]);
    }
    public function store(StoreCommentRequest $request, $generationId)
    {
        $g = $this->accessible($request, $generationId);
        $comment = Comment::create([
            'video_generation_id' => $g->id, 'user_id' => $request->user()->id,
            'parent_id' => $request->input('parent_id'), 'body' => $request->validated()['body'],
        ]);
        if ($g->user_id !== $request->user()->id) {
            NotificationService::notify($g->user, 'Komentar baru', "{$request->user()->name} berkomentar pada video \"{$g->keyword}\".");
        }
        return response()->json(['success' => true, 'message' => 'Komentar ditambahkan.', 'data' => $comment->load('user:id,name,avatar')], 201);
    }
    public function destroy(Request $request, $generationId, $commentId)
    {
        $comment = Comment::where('video_generation_id', $generationId)->findOrFail($commentId);
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Hanya pemilik komentar atau admin yang bisa menghapus.'], 403);
        }
        $comment->replies()->delete();
        $comment->delete();
        return response()->json(['success' => true, 'message' => 'Komentar dihapus.']);
    }
}
