<?php
namespace App\Http\Controllers;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['success' => true, 'data' => $request->user()->apiKeys()->latest()->get(['id', 'name', 'key_preview', 'last_used_at', 'is_active', 'created_at'])]);
    }
    /** Key mentah hanya ditampilkan SEKALI di respons ini — tidak disimpan. */
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $plain = 'rd_' . Str::random(48);
        $key = ApiKey::create([
            'user_id' => $request->user()->id, 'name' => $data['name'],
            'key_hash' => hash('sha256', $plain), 'key_preview' => substr($plain, 0, 6) . '••••' . substr($plain, -4),
            'is_active' => true,
        ]);
        return response()->json(['success' => true, 'message' => 'API key dibuat. Simpan key ini — tidak akan ditampilkan lagi.', 'data' => ['id' => $key->id, 'name' => $key->name, 'key' => $plain, 'key_preview' => $key->key_preview]], 201);
    }
    public function toggle(Request $request, $id)
    {
        $key = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);
        $key->update(['is_active' => !$key->is_active]);
        return response()->json(['success' => true, 'message' => $key->is_active ? 'API key diaktifkan.' : 'API key dinonaktifkan.', 'data' => $key->fresh()]);
    }
    public function destroy(Request $request, $id)
    {
        ApiKey::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'API key dihapus.']);
    }
}
