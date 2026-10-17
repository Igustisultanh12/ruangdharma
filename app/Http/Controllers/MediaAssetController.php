<?php
namespace App\Http\Controllers;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaAssetController extends Controller
{
    public function index(Request $request)
    {
        $q = MediaAsset::with('user:id,name')->latest();
        if ($request->filled('tag')) $q->searchByTag($request->input('tag'));
        if ($request->filled('search')) $q->where('title', 'like', "%{$request->search}%");
        if ($request->filled('type')) $q->where('type', $request->input('type'));
        return response()->json(['success' => true, 'data' => $q->paginate((int) $request->input('per_page', 20))]);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm|max:10240',
            'tags' => 'sometimes|array', 'tags.*' => 'string|max:50',
        ], ['file.max' => 'File maksimal 10MB.', 'file.mimes' => 'File harus gambar (jpg/png/webp/gif) atau video (mp4/mov/webm).']);
        $file = $request->file('file');
        $path = $file->store('media', 'public');
        $isVideo = str_starts_with($file->getMimeType() ?: '', 'video/');
        $asset = MediaAsset::create([
            'title' => $data['title'], 'file_path' => '/storage/' . $path,
            'type' => $isVideo ? 'video' : 'image', 'tags' => $data['tags'] ?? [],
            'user_id' => $request->user()->id, 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);
        return response()->json(['success' => true, 'message' => 'Media berhasil diunggah.', 'data' => $asset], 201);
    }
    public function destroy(Request $request, $id)
    {
        $asset = MediaAsset::findOrFail($id);
        if ($asset->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Hanya pemilik atau admin yang bisa menghapus media ini.'], 403);
        }
        $rel = ltrim(str_replace('/storage/', '', $asset->file_path), '/');
        if (Storage::disk('public')->exists($rel)) Storage::disk('public')->delete($rel);
        $asset->delete();
        return response()->json(['success' => true, 'message' => 'Media dihapus.']);
    }
}
