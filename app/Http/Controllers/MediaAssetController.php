<?php
namespace App\Http\Controllers;
use App\Models\MediaAsset;
use App\Models\VideoGeneration;
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
        $page = $q->paginate((int) $request->input('per_page', 20));
        $this->attachGenerationIds($page->items());
        return response()->json(['success' => true, 'data' => $page]);
    }
    /**
     * Cocokkan aset video ke VideoGeneration pemilik yang sama (dari video_url saat ini,
     * lalu dari daftar video_files) dan tempelkan generation_id-nya. Dipakai halaman Media
     * untuk menampilkan panel Auto Backsound pada video hasil generate; aset unggahan
     * manual yang tidak terhubung ke generation mana pun mendapat null.
     */
    private function attachGenerationIds(array $assets): void
    {
        $videos = array_values(array_filter($assets, fn ($a) => $a->type === 'video'));
        if (!$videos) return;
        $norm = function ($u) {
            if (!is_string($u) || $u === '') return null;
            $p = parse_url($u, PHP_URL_PATH) ?: $u;
            return '/' . ltrim($p, '/');
        };
        $userIds = array_unique(array_map(fn ($a) => $a->user_id, $videos));
        $gens = VideoGeneration::whereIn('user_id', $userIds)->whereNotNull('video_url')->where('video_url', '!=', '')
            ->get(['id', 'user_id', 'video_url', 'video_files']);
        $map = [];
        foreach ($gens as $g) {
            $key = $g->user_id . '|' . $norm($g->video_url);
            if (!isset($map[$key])) $map[$key] = $g->id;
        }
        foreach ($gens as $g) {
            foreach ((array) ($g->video_files ?? []) as $f) {
                $key = $g->user_id . '|' . $norm($f);
                if (!isset($map[$key])) $map[$key] = $g->id;
            }
        }
        foreach ($videos as $a) {
            $a->setAttribute('generation_id', $map[$a->user_id . '|' . $norm($a->file_path)] ?? null);
        }
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
