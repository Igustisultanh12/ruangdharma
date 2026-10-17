<?php
namespace App\Http\Controllers;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['success' => true, 'data' => $request->user()->webhooks()->withCount('logs')->latest()->paginate(20)]);
    }
    public function store(Request $request)
    {
        $data = $request->validate(['url' => 'required|url|max:255', 'events' => 'required|array|min:1', 'events.*' => 'string|max:100', 'is_active' => 'sometimes|boolean']);
        $wh = Webhook::create(['user_id' => $request->user()->id] + $data);
        return response()->json(['success' => true, 'message' => 'Webhook dibuat.', 'data' => $wh], 201);
    }
    public function update(Request $request, $id)
    {
        $wh = Webhook::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate(['url' => 'sometimes|url|max:255', 'events' => 'sometimes|array|min:1', 'events.*' => 'string|max:100', 'is_active' => 'sometimes|boolean']);
        $wh->update($data);
        return response()->json(['success' => true, 'message' => 'Webhook diperbarui.', 'data' => $wh->fresh()]);
    }
    public function destroy(Request $request, $id)
    {
        $wh = Webhook::where('user_id', $request->user()->id)->findOrFail($id);
        $wh->logs()->delete(); $wh->delete();
        return response()->json(['success' => true, 'message' => 'Webhook dihapus.']);
    }
    public function test(Request $request, $id)
    {
        $wh = Webhook::where('user_id', $request->user()->id)->findOrFail($id);
        $log = WebhookService::test($wh);
        return response()->json(['success' => true, 'message' => 'Tes webhook dikirim.', 'data' => $log]);
    }
    public function logs(Request $request, $id)
    {
        $wh = Webhook::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json(['success' => true, 'data' => $wh->logs()->latest()->paginate(20)]);
    }
}
