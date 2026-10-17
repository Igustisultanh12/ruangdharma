<?php
namespace App\Http\Controllers;
use App\Models\Backsound;
use Illuminate\Http\Request;
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
        $data = $request->validate(['name' => 'required|string|max:100', 'file' => 'required|file|mimes:mp3,wav,m4a,ogg|max:20480']);
        $path = $request->file('file')->store('backsounds', 'public');
        // Durasi dihitung via ffprobe di server jika tersedia, fallback null
        $duration = null;
        try {
            $full = storage_path('app/public/' . $path);
            $out = shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($full) . ' 2>/dev/null');
            if ($out) $duration = (int) round((float) trim($out));
        } catch (\Throwable $e) {}
        $bs = Backsound::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'file_url' => '/storage/' . $path, 'duration_sec' => $duration]);
        return response()->json(['success' => true, 'message' => 'Backsound diunggah.', 'data' => $bs], 201);
    }
    public function destroy(Request $request, Backsound $backsound)
    {
        if ($backsound->user_id !== $request->user()->id) abort(403);
        $backsound->delete();
        return response()->json(['success' => true, 'message' => 'Backsound dihapus.']);
    }
}
