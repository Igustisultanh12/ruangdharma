<?php
namespace App\Http\Controllers;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->user()->notifications()->latest();
        if ($request->has('is_read')) $q->where('is_read', filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN));
        return response()->json(['success' => true, 'unread_count' => $request->user()->notifications()->where('is_read', false)->count(), 'data' => $q->paginate((int) $request->input('per_page', 20))]);
    }
    public function read(Request $request, $id)
    {
        $n = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $n->markAsRead();
        return response()->json(['success' => true, 'message' => 'Notifikasi ditandai dibaca.', 'data' => $n]);
    }
    public function readAll(Request $request)
    {
        $count = $request->user()->notifications()->where('is_read', false)->update(['is_read' => true]);
        return response()->json(['success' => true, 'message' => "{$count} notifikasi ditandai dibaca."]);
    }
}
