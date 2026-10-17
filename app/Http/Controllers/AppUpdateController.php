<?php

namespace App\Http\Controllers;

use App\Support\ActivityLogger;
use App\Support\AppRelease;
use App\Support\PushNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pembaruan aplikasi Android Ruang Dharma Studio (seperti di SISINDEN):
 * Admin dapat mengunggah APK di web atau otomatis menyinkronkan dengan rilis GitHub Actions.
 * Aplikasi di HP memeriksa pembaruan, mengunduh APK, memverifikasi SHA-256 dan tanda tangan,
 * lalu memasangnya langsung lewat PackageInstaller.
 */
class AppUpdateController extends Controller
{
    /** Aplikasi Android: periksa info versi terbaru. */
    public function latest()
    {
        return response()->json(AppRelease::publicPayload())->header('Cache-Control', 'no-store');
    }

    /** Aplikasi Android: unduh berkas APK terbaru. */
    public function download(Request $request)
    {
        $m = AppRelease::current();
        if (!$m) {
            return response()->json(['status' => 'error', 'message' => 'Belum ada berkas pembaruan aplikasi.'], 404);
        }

        // Jika berkas tersimpan di disk lokal server
        if (!empty($m['path']) && Storage::disk(AppRelease::DISK)->exists($m['path'])) {
            $path = Storage::disk(AppRelease::DISK)->path($m['path']);
            return response()->download($path, 'RUANGDHARMA-' . $m['version'] . '.apk', [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Cache-Control' => 'no-store',
                'X-Content-SHA256' => (string) ($m['sha256'] ?? ''),
            ]);
        }

        // Jika bersumber dari GitHub Releases, alihkan (redirect 302) langsung ke asset rilis resmi
        if (!empty($m['download_url'])) {
            return redirect()->away($m['download_url']);
        }

        return response()->json(['status' => 'error', 'message' => 'Tautan unduhan tidak tersedia.'], 404);
    }

    /** Web Admin: ringkasan status pembaruan aplikasi untuk halaman Pengaturan. */
    public function adminInfo()
    {
        $summary = AppRelease::adminSummary();
        $limit = self::uploadLimitBytes();

        return response()->json([
            'success' => true,
            'data' => [
                'current' => $summary,
                'upload_limit_bytes' => $limit,
                'upload_limit_mb' => round($limit / 1048576, 1),
                'push_ready' => PushNotifier::enabled(),
                'github_repo' => AppRelease::GITHUB_REPO,
            ],
        ]);
    }

    /** Web Admin: unggah berkas APK baru. */
    public function store(Request $request)
    {
        if (!$request->hasFile('apk')) {
            $limit = ini_get('upload_max_filesize') ?: '?';
            $post = ini_get('post_max_size') ?: '?';
            $f = $request->files->get('apk');
            $tooBig = $f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile
                && in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            return response()->json([
                'success' => false,
                'message' => $tooBig
                    ? "APK gagal diterima server: ukuran melebihi batas unggah PHP (upload_max_filesize {$limit}, post_max_size {$post})."
                    : 'Pilih berkas APK yang akan diunggah.',
            ], 422);
        }

        $request->validate([
            'apk' => 'required|file|max:204800',
            'notes' => 'nullable|string|max:2000',
            'version' => 'nullable|string|max:20',
            'notify' => 'nullable|boolean',
        ], [
            'apk.uploaded' => 'APK gagal diunggah (melebihi batas upload_max_filesize server).',
            'apk.max' => 'Ukuran APK maksimal 200 MB.',
        ]);

        $file = $request->file('apk');
        if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
            return response()->json(['success' => false, 'message' => 'Berkas harus berekstensi .apk.'], 422);
        }

        $result = AppRelease::store(
            $file,
            (string) $request->input('notes', ''),
            (string) $request->user()->name,
            $request->input('version')
        );

        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result], 422);
        }

        $notified = 0;
        if ($request->boolean('notify', true) && PushNotifier::enabled()) {
            $notified = PushNotifier::appUpdate($result['version'], $result['notes']);
            AppRelease::markNotified($notified);
        }

        ActivityLogger::fromRequest(
            $request,
            'app_update.upload',
            "Admin mengunggah APK v{$result['version']} (" . round($result['size'] / 1048576, 1) . ' MB)'
        );

        $msg = "APK Ruang Dharma versi {$result['version']} berhasil disimpan.";
        if ($notified > 0) {
            $msg .= " Notifikasi push pembaruan dikirim ke {$notified} perangkat.";
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => AppRelease::adminSummary(),
        ]);
    }

    /** Web Admin: hapus APK yang diunggah secara manual. */
    public function destroy(Request $request)
    {
        AppRelease::clear();
        ActivityLogger::fromRequest($request, 'app_update.delete', 'Admin menghapus berkas APK pembaruan dari server');

        return response()->json([
            'success' => true,
            'message' => 'Berkas APK pembaruan di server berhasil dihapus.',
            'data' => AppRelease::adminSummary(),
        ]);
    }

    /** Web Admin: sinkronkan data pembaruan dengan rilis terbaru GitHub. */
    public function syncGitHub(Request $request)
    {
        $info = AppRelease::latestGitHubRelease(forceRefresh: true);
        if (!$info) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengambil rilis terbaru dari GitHub. Pastikan rilis GitHub memiliki file .apk dan SHA256SUMS.txt.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyinkronkan dengan rilis GitHub v{$info['version']}.",
            'data' => AppRelease::adminSummary(),
        ]);
    }

    /** Web Admin: kirim notifikasi push ke seluruh pengguna bahwa pembaruan tersedia. */
    public function notify(Request $request)
    {
        $current = AppRelease::current();
        if (!$current) {
            return response()->json(['success' => false, 'message' => 'Belum ada versi pembaruan yang siap diumumkan.'], 422);
        }

        if (!PushNotifier::enabled()) {
            return response()->json(['success' => false, 'message' => 'Layanan push FCM belum aktif. Atur kredensial di tab Push HP terlebih dahulu.'], 422);
        }

        $sent = PushNotifier::appUpdate($current['version'], (string) ($current['notes'] ?? ''));
        AppRelease::markNotified($sent);

        return response()->json([
            'success' => true,
            'message' => "Notifikasi pembaruan v{$current['version']} dikirim ke {$sent} perangkat.",
        ]);
    }

    /** Batas unggah efektif PHP (byte). */
    public static function uploadLimitBytes(): int
    {
        $toBytes = function ($v): int {
            $v = trim((string) $v);
            if ($v === '' || $v === '-1' || $v === '0') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1073741824,
                'm' => $n * 1048576,
                'k' => $n * 1024,
                default => $n,
            };
        };
        return min($toBytes(ini_get('upload_max_filesize')), $toBytes(ini_get('post_max_size')));
    }
}
