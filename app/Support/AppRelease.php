<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pembaruan aplikasi Android Ruang Dharma Studio.
 *
 * Mendukung dua sumber:
 * 1. Berkas APK yang diunggah Admin di Pengaturan web (disimpan di disk privat storage/app/app-updates).
 * 2. Rilis resmi GitHub (Igustisultanh12/ruangdharma/releases/latest) secara otomatis sebagai cadangan,
 *    sehingga setiap rilis baru yang di-build CI langsung dapat di-update oleh aplikasi HP tanpa
 *    harus diunggah manual ke server.
 */
class AppRelease
{
    public const PACKAGE = 'id.ruangdharma.studio';
    public const DISK = 'local';
    public const DIR = 'app-updates';
    public const KEY = 'app_update_meta';
    public const GITHUB_REPO = 'Igustisultanh12/ruangdharma';

    /**
     * Data pembaruan saat ini (manual unggahan admin diutamakan; bila belum ada, ambil dari GitHub).
     *
     * @return array<string, mixed>|null
     */
    public static function current(): ?array
    {
        $raw = Setting::get(self::KEY);
        $meta = $raw ? json_decode((string) $raw, true) : null;
        if (is_array($meta) && !empty($meta['version']) && !empty($meta['sha256'])) {
            if (!empty($meta['path'])) {
                if (Storage::disk(self::DISK)->exists($meta['path'])) {
                    $meta['source'] = 'manual';
                    return $meta;
                }
            } elseif (!empty($meta['download_url'])) {
                $meta['source'] = $meta['source'] ?? 'configured';
                return $meta;
            }
        }

        // Jika tidak ada berkas lokal, cek rilis terbaru GitHub (cache 10 menit)
        return self::latestGitHubRelease();
    }

    /** Data publik untuk aplikasi Android (tanpa info sensitif server). */
    public static function publicPayload(): array
    {
        $m = self::current();
        if (!$m || empty($m['version']) || empty($m['sha256'])) {
            return ['status' => 'success', 'available' => false];
        }
        return [
            'status' => 'success',
            'available' => true,
            'version' => (string) $m['version'],
            'version_code' => (int) ($m['version_code'] ?? 0),
            'notes' => (string) ($m['notes'] ?? ''),
            'size' => (int) ($m['size'] ?? 0),
            'sha256' => (string) ($m['sha256'] ?? ''),
            'file_name' => 'RUANGDHARMA-' . $m['version'] . '.apk',
            'uploaded_at' => (string) ($m['uploaded_at'] ?? ''),
            'download_url' => url('/api/mobile/app-update/download') . '?v=' . rawurlencode((string) $m['version']),
        ];
    }

    /** Ringkasan untuk halaman Pengaturan web admin. */
    public static function adminSummary(): ?array
    {
        $m = self::current();
        if (!$m) {
            return null;
        }
        return [
            'source' => $m['source'] ?? 'manual',
            'version' => $m['version'],
            'version_code' => (int) ($m['version_code'] ?? 0),
            'notes' => (string) ($m['notes'] ?? ''),
            'size' => (int) ($m['size'] ?? 0),
            'sha256' => (string) ($m['sha256'] ?? ''),
            'uploaded_at' => (string) ($m['uploaded_at'] ?? ''),
            'uploaded_by' => (string) ($m['uploaded_by'] ?? ($m['source'] === 'github' ? 'GitHub Actions CI' : 'Admin')),
            'notified' => (int) ($m['notified'] ?? 0),
            'download_url' => $m['download_url'] ?? url('/api/mobile/app-update/download') . '?v=' . rawurlencode((string) $m['version']),
        ];
    }

    /**
     * Simpan APK yang diunggah Admin.
     *
     * @return array<string, mixed>|string
     */
    public static function store(UploadedFile $file, string $notes, string $uploader, ?string $manualVersion = null)
    {
        $tmp = $file->getRealPath();
        if (!$tmp || !is_file($tmp)) {
            return 'Berkas APK tidak terbaca. Coba unggah ulang.';
        }
        $info = ApkInspector::inspect($tmp);
        if ($info === null) {
            return 'Berkas bukan APK Android yang valid.';
        }
        if ($info['package'] !== self::PACKAGE) {
            return 'APK ini bukan aplikasi Ruang Dharma Studio (paket ' . $info['package'] . ').';
        }
        $version = $info['version_name'] !== '' ? $info['version_name'] : trim((string) $manualVersion);
        if (!preg_match('/^\d+(\.\d+){1,3}$/', $version)) {
            return 'Nomor versi APK tidak terbaca. Isi kolom versi (contoh 1.3.0).';
        }

        $sha = hash_file('sha256', $tmp);
        $size = (int) filesize($tmp);
        $path = self::DIR . '/RUANGDHARMA-' . $version . '-' . substr($sha, 0, 8) . '.apk';
        $stored = Storage::disk(self::DISK)->putFileAs(self::DIR, $file, basename($path));
        if (!$stored) {
            return 'APK gagal disimpan di server (periksa izin folder storage).';
        }

        $prev = self::localMeta();
        if ($prev && !empty($prev['path']) && $prev['path'] !== $stored) {
            Storage::disk(self::DISK)->delete($prev['path']);
        }

        $meta = [
            'version' => $version,
            'version_code' => (int) $info['version_code'],
            'notes' => mb_substr(trim($notes), 0, 2000),
            'size' => $size,
            'sha256' => $sha,
            'path' => $stored,
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $uploader,
            'notified' => 0,
            'source' => 'manual',
        ];
        Setting::set(self::KEY, $meta);
        return $meta;
    }

    public static function markNotified(int $count): void
    {
        $m = self::localMeta();
        if ($m) {
            $m['notified'] = $count;
            Setting::set(self::KEY, $m);
        }
    }

    public static function clear(): void
    {
        $m = self::localMeta();
        if ($m && !empty($m['path'])) {
            Storage::disk(self::DISK)->delete($m['path']);
        }
        Setting::where('key', self::KEY)->delete();
        Cache::forget('rd:github_latest_release');
    }

    private static function localMeta(): ?array
    {
        $raw = Setting::get(self::KEY);
        $meta = $raw ? json_decode((string) $raw, true) : null;
        return is_array($meta) ? $meta : null;
    }

    /** Ambil info rilis terbaru dari GitHub Releases. */
    public static function latestGitHubRelease(bool $forceRefresh = false): ?array
    {
        $cacheKey = 'rd:github_latest_release';
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 600, function () {
            try {
                $res = Http::withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'RuangDharma-Server/1.0',
                ])->timeout(8)->get('https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest');

                if (!$res->successful()) {
                    return null;
                }

                $json = $res->json();
                $tag = (string) ($json['tag_name'] ?? '');
                $version = ltrim($tag, 'v');
                if (!preg_match('/^\d+(\.\d+){1,3}$/', $version)) {
                    return null;
                }

                $assets = $json['assets'] ?? [];
                $apkAsset = null;
                $shaAsset = null;
                foreach ($assets as $asset) {
                    $name = $asset['name'] ?? '';
                    if (str_ends_with($name, '.apk')) {
                        $apkAsset = $asset;
                    } elseif ($name === 'SHA256SUMS.txt') {
                        $shaAsset = $asset;
                    }
                }

                if (!$apkAsset) {
                    return null;
                }

                $sha = '';
                if ($shaAsset && !empty($shaAsset['browser_download_url'])) {
                    $shaRes = Http::timeout(6)->get($shaAsset['browser_download_url']);
                    if ($shaRes->successful()) {
                        $lines = explode("\n", $shaRes->body());
                        foreach ($lines as $line) {
                            if (str_contains($line, $apkAsset['name'])) {
                                $parts = preg_split('/\s+/', trim($line));
                                if (!empty($parts[0]) && preg_match('/^[0-9a-f]{64}$/i', $parts[0])) {
                                    $sha = strtolower($parts[0]);
                                    break;
                                }
                            }
                        }
                    }
                }

                // Jika SHA256SUMS tidak ada, sha kosong → aplikasi tidak bisa memverifikasi
                if (empty($sha)) {
                    return null;
                }

                // Ekstrak versionCode dari catatan rilis (mis. "versionCode 5")
                $body = (string) ($json['body'] ?? '');
                $versionCode = 0;
                if (preg_match('/versionCode\s+(\d+)/i', $body, $vm)) {
                    $versionCode = (int) $vm[1];
                }

                return [
                    'source' => 'github',
                    'version' => $version,
                    'version_code' => $versionCode,
                    'notes' => mb_substr(trim($body), 0, 2000),
                    'size' => (int) ($apkAsset['size'] ?? 0),
                    'sha256' => $sha,
                    'path' => null,
                    'download_url' => (string) ($apkAsset['browser_download_url'] ?? ''),
                    'uploaded_at' => (string) ($json['published_at'] ?? now()->toIso8601String()),
                    'uploaded_by' => 'GitHub Releases',
                    'notified' => 0,
                ];
            } catch (\Throwable $e) {
                Log::warning('[AppRelease] Gagal mengambil GitHub release: ' . $e->getMessage());
                return null;
            }
        });
    }
}
