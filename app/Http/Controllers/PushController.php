<?php
namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Services\ActivityLogger;
use App\Support\PushNotifier;
use Illuminate\Http\Request;

/** Endpoint notifikasi push untuk aplikasi Android + pengaturan FCM oleh admin. */
class PushController extends Controller
{
    /** GET /api/mobile/config — publik: konfigurasi klien Firebase (bukan rahasia). */
    public function mobileConfig()
    {
        return response()->json([
            'success' => true,
            'push' => PushNotifier::clientConfig(),
            'push_enabled' => PushNotifier::enabled(),
            'poll_seconds' => 30,
        ]);
    }

    /** POST /api/push-token — daftarkan token FCM HP untuk akun yang sedang masuk. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|min:20|max:4096',
            'platform' => 'nullable|string|max:20',
            'device_name' => 'nullable|string|max:120',
            'app_version' => 'nullable|string|max:40',
        ]);
        $hash = DeviceToken::hashOf($data['token']);
        DeviceToken::updateOrCreate(['token_hash' => $hash], [
            'user_id' => $request->user()->id,
            'token' => trim($data['token']),
            'platform' => $data['platform'] ?? 'android',
            'device_name' => $data['device_name'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'last_seen_at' => now(),
        ]);
        return response()->json(['success' => true, 'push_enabled' => PushNotifier::enabled()]);
    }

    /** DELETE /api/push-token — hapus token saat logout agar HP tidak menerima notifikasi akun lama. */
    public function unregister(Request $request)
    {
        $token = (string) $request->input('token', '');
        if ($token !== '') {
            DeviceToken::where('user_id', $request->user()->id)->where('token_hash', DeviceToken::hashOf($token))->delete();
        }
        return response()->json(['success' => true]);
    }

    /** POST /api/push-test — kirim notifikasi uji ke HP milik pengguna sendiri. */
    public function test(Request $request)
    {
        if (!PushNotifier::enabled()) {
            return response()->json(['success' => false, 'message' => 'Push belum aktif. Admin perlu mengunggah google-services.json & service account di Pengaturan → Push HP.'], 422);
        }
        $sent = PushNotifier::sendNow([$request->user()->id], [
            'type' => 'notification',
            'id' => (string) (time()),
            'title' => 'Tes notifikasi Ruang Dharma',
            'body' => 'Notifikasi push berhasil. Om Swastyastu 🙏',
            'test' => '1',
        ]);
        return $sent > 0
            ? response()->json(['success' => true, 'message' => "Notifikasi uji terkirim ke {$sent} perangkat."])
            : response()->json(['success' => false, 'message' => 'Tidak ada perangkat terdaftar untuk akun ini. Buka aplikasi Android lalu login dulu.'], 422);
    }

    /** GET /api/admin/push-config — status FCM untuk halaman Pengaturan. */
    public function adminShow()
    {
        return response()->json(['success' => true, 'data' => PushNotifier::status()]);
    }

    /** POST /api/admin/push-config — simpan google-services.json / service account / aktif-nonaktif / hapus. */
    public function adminUpdate(Request $request)
    {
        $request->validate([
            'enabled' => 'nullable|boolean',
            'google_services' => 'nullable|string|max:20000',
            'service_account' => 'nullable|string|max:20000',
            'clear' => 'nullable|boolean',
        ]);
        if ($request->boolean('clear')) {
            PushNotifier::clear();
            ActivityLogger::fromRequest($request, 'setting.update', 'Admin menghapus kredensial push FCM');
            return response()->json(['success' => true, 'message' => 'Kredensial push dihapus.', 'data' => PushNotifier::status()]);
        }
        if (filled($request->input('google_services'))) {
            $cfg = PushNotifier::parseGoogleServices((string) $request->input('google_services'));
            if (is_string($cfg)) return response()->json(['success' => false, 'message' => $cfg, 'errors' => ['google_services' => [$cfg]]], 422);
            PushNotifier::storeClientConfig($cfg);
        }
        if (filled($request->input('service_account'))) {
            $err = PushNotifier::storeServiceAccount((string) $request->input('service_account'));
            if ($err) return response()->json(['success' => false, 'message' => $err, 'errors' => ['service_account' => [$err]]], 422);
        }
        if ($request->has('enabled')) {
            \App\Models\Setting::set('fcm_enabled', $request->boolean('enabled') ? '1' : '0');
        }
        ActivityLogger::fromRequest($request, 'setting.update', 'Admin memperbarui pengaturan push FCM');
        $status = PushNotifier::status();
        $warn = ($status['client_project'] && $status['service_account_project'] && $status['client_project'] !== $status['service_account_project'])
            ? ' Perhatian: project google-services.json dan service account berbeda.' : '';
        return response()->json(['success' => true, 'message' => 'Pengaturan push tersimpan.' . $warn, 'data' => $status]);
    }
}
