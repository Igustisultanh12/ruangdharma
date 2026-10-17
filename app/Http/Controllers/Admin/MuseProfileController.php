<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\MuseProfile;
use App\Services\ActivityLogger;
use App\Services\MuseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kelola profil Muse (multi-Muse) dari menu Admin.
 * KEAMANAN: semua respons hanya membawa versi masked (**** + 4 karakter terakhir),
 * KECUALI endpoint reveal() yang sengaja mengembalikan token/secret utuh untuk SATU
 * profil atas permintaan admin — endpoint itu dibatasi throttle + hanya admin +
 * dicatat ke ActivityLogger TANPA nilai token/secret, dan frontend auto-hide 30 detik.
 * Token & secret baru dibuat dengan bin2hex(random_bytes(32)) dan langsung
 * disimpan terenkripsi (cast 'encrypted' di MuseProfile).
 * Token lama di settings tetap dipertahankan sebagai fallback (lihat MuseService).
 */
class MuseProfileController extends Controller
{
    /** Daftar lengkap untuk Admin (masked). Otomatis migrasi token lama jadi profil "Muse Utama" bila tabel masih kosong. */
    public function index(Request $request, MuseService $muse)
    {
        $muse->ensureLegacyProfile();
        $profiles = MuseProfile::withCount('jobs')->orderByDesc('is_default')->orderBy('id')->get()
            ->map(fn (MuseProfile $p) => $p->safeArray() + ['jobs_count' => $p->jobs_count]);
        return response()->json(['success' => true, 'data' => $profiles]);
    }

    /** Daftar ringkas untuk dropdown Generate (user biasa): TANPA token, hanya profil aktif. */
    public function options(Request $request, MuseService $muse)
    {
        $muse->ensureLegacyProfile();
        $profiles = MuseProfile::where('is_active', true)->orderByDesc('is_default')->orderBy('id')->get()
            ->map(fn (MuseProfile $p) => ['id' => $p->id, 'name' => $p->name, 'is_default' => (bool) $p->is_default, 'is_active' => (bool) $p->is_active]);
        return response()->json(['success' => true, 'data' => $profiles]);
    }

    public function store(Request $request, MuseService $muse)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);
        $muse->ensureLegacyProfile();
        $slug = Str::slug($data['name']) ?: 'muse';
        $base = $slug; $i = 2;
        while (MuseProfile::where('slug', $slug)->exists()) $slug = $base . '-' . ($i++);
        $isFirst = MuseProfile::count() === 0;
        $profile = MuseProfile::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            // Token & secret langsung dibuat di sini (tidak di-paste dari chat).
            'api_token' => bin2hex(random_bytes(32)),
            'webhook_secret' => bin2hex(random_bytes(32)),
            'is_active' => true,
            'is_default' => $isFirst,
        ]);
        ActivityLogger::fromRequest($request, 'muse.profile', "Admin tambah profil Muse: {$profile->name}.");
        return response()->json(['success' => true, 'message' => 'Profil Muse ditambahkan.', 'data' => $profile->safeArray()], 201);
    }

    public function update(Request $request, $id)
    {
        $profile = MuseProfile::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
        ]);
        $profile->update($data);
        return response()->json(['success' => true, 'message' => 'Profil Muse diperbarui.', 'data' => $profile->fresh()->safeArray()]);
    }

    public function generateToken(Request $request, $id)
    {
        $profile = MuseProfile::findOrFail($id);
        $profile->update(['api_token' => bin2hex(random_bytes(32))]);
        ActivityLogger::fromRequest($request, 'muse.profile.token', "Admin generate token baru untuk profil Muse: {$profile->name}.");
        return response()->json(['success' => true, 'message' => 'Token baru dibuat untuk profil ini. Token lama langsung tidak berlaku.', 'data' => $profile->fresh()->safeArray()], 201);
    }

    public function generateWebhookSecret(Request $request, $id)
    {
        $profile = MuseProfile::findOrFail($id);
        do { $secret = bin2hex(random_bytes(32)); } while ($secret === $profile->api_token);
        $profile->update(['webhook_secret' => $secret]);
        ActivityLogger::fromRequest($request, 'muse.profile.webhook_secret', "Admin generate webhook secret baru untuk profil Muse: {$profile->name}.");
        return response()->json(['success' => true, 'message' => 'Webhook secret baru dibuat untuk profil ini.', 'data' => $profile->fresh()->safeArray()], 201);
    }

    public function setDefault(Request $request, $id)
    {
        $profile = MuseProfile::findOrFail($id);
        DB::transaction(function () use ($profile) {
            MuseProfile::where('is_default', true)->update(['is_default' => false]);
            // Profil default harus aktif agar bisa dipakai job baru.
            $profile->update(['is_default' => true, 'is_active' => true]);
        });
        ActivityLogger::fromRequest($request, 'muse.profile.default', "Admin jadikan profil Muse default: {$profile->name}.");
        return response()->json(['success' => true, 'message' => "\"{$profile->name}\" kini profil Muse default.", 'data' => $profile->fresh()->safeArray()]);
    }

    public function toggleActive(Request $request, $id)
    {
        $profile = MuseProfile::findOrFail($id);
        if ($profile->is_default && $profile->is_active) {
            return response()->json(['success' => false, 'message' => 'Profil default tidak bisa dinonaktifkan. Jadikan profil lain sebagai default dulu.'], 422);
        }
        $profile->update(['is_active' => ! $profile->is_active]);
        return response()->json(['success' => true, 'message' => $profile->is_active ? 'Profil diaktifkan.' : 'Profil dinonaktifkan.', 'data' => $profile->fresh()->safeArray()]);
    }

    public function destroy(Request $request, $id)
    {
        $profile = MuseProfile::findOrFail($id);
        $wasDefault = (bool) $profile->is_default;
        $name = $profile->name;
        DB::transaction(function () use ($profile, $wasDefault) {
            $profile->delete(); // FK muse_jobs.muse_profile_id = SET NULL, job lama tetap ada.
            if ($wasDefault) {
                // Profil aktif tertua menjadi default baru.
                $next = MuseProfile::where('is_active', true)->orderBy('id')->first();
                if ($next) $next->update(['is_default' => true]);
            }
        });
        ActivityLogger::fromRequest($request, 'muse.profile.delete', "Admin hapus profil Muse: {$name}.");
        return response()->json(['success' => true, 'message' => "Profil \"{$name}\" dihapus."]);
    }

    /** Tes profil: hanya status tersimpan + format token. TIDAK memanggil endpoint eksternal apa pun. */
    public function test(Request $request, $id)
    {
        $profile = MuseProfile::withCount('jobs')->findOrFail($id);
        $token = $profile->api_token;
        return response()->json(['success' => true, 'data' => [
            'id' => $profile->id,
            'name' => $profile->name,
            'is_active' => (bool) $profile->is_active,
            'is_default' => (bool) $profile->is_default,
            'has_token' => (bool) $token,
            'api_token_masked' => $profile->maskedToken(),
            'api_token_length' => $token ? strlen($token) : 0,
            'api_token_format' => $token && ctype_xdigit($token) ? 'hex' : ($token ? 'custom' : null),
            'has_webhook_secret' => (bool) $profile->webhook_secret,
            'webhook_secret_masked' => $profile->maskedSecret(),
            'jobs_count' => $profile->jobs_count,
            'last_used_at' => $profile->last_used_at?->toIso8601String(),
            'message' => 'Profil tersimpan dengan format token valid. Tes koneksi jaringan tidak dilakukan di sini.',
        ]]);
    }

    /**
     * Reveal token + webhook_secret UTUH untuk SATU profil (atas permintaan admin).
     * Disengaja atas permintaan user (tombol mata di Admin -> Integrasi Muse).
     * Pengaman: (a) hanya admin (route group role:admin,super_admin),
     * (b) throttle 10x/menit per user (di route), (c) dicatat ke ActivityLogger
     * TANPA nilai token/secret, (d) hanya profil ini yang dikembalikan — tidak
     * pernah profil lain. Frontend wajib auto-hide 30 detik & tidak menyimpan
     * nilai ini ke localStorage/sessionStorage.
     */
    public function reveal(Request $request, $id)
    {
        $profile = MuseProfile::findOrFail($id);
        ActivityLogger::fromRequest($request, 'muse.profile.reveal', "Reveal token profil Muse {$profile->name}.");
        return response()->json(['success' => true, 'data' => [
            'id' => $profile->id,
            'name' => $profile->name,
            'api_token' => $profile->api_token,
            'webhook_secret' => $profile->webhook_secret,
        ]]);
    }
}
