<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\MuseJob;
use App\Models\VideoGeneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MuseTokenUsageController extends Controller
{
    public function index(Request $request)
    {
        // Estimasi token: 1 token ~ 4 karakter teks (naskah + caption + prompts). Ini estimasi lokal, bukan billing Muse.
        // Total dari semua generasi + job.
        $gens = VideoGeneration::select('id','naskah','caption','prompts','created_at')->get();
        $totalChars = 0; $totalGens = $gens->count();
        $daily = [];
        foreach ($gens as $g) {
            $text = json_encode($g->naskah ?? '') . ' ' . json_encode($g->caption ?? '') . ' ' . json_encode($g->prompts ?? '');
            $chars = mb_strlen($text);
            $totalChars += $chars;
            $day = $g->created_at ? $g->created_at->format('Y-m-d') : 'unknown';
            $daily[$day] = ($daily[$day] ?? 0) + $chars;
        }
        $jobs = MuseJob::count();
        $totalTokens = (int) ceil($totalChars / 4);
        // Kuota: ambil dari settings, default 22.000.000 token / bulan (bisa diubah di Pengaturan)
        $monthlyLimit = (int) (\App\Models\Setting::get('muse_monthly_token_limit', 22000000));
        $monthChars = 0;
        $monthGens = VideoGeneration::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->get();
        foreach ($monthGens as $g) { $monthChars += mb_strlen(json_encode($g->naskah ?? '') . json_encode($g->caption ?? '')); }
        $monthTokens = (int) ceil($monthChars / 4);
        $remaining = max(0, $monthlyLimit - $monthTokens);
        $pct = $monthlyLimit > 0 ? round($monthTokens / $monthlyLimit * 100, 1) : 0;
        // Daily last 7 days
        $dailyList = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $dailyList[] = ['date' => $d, 'tokens' => (int) ceil(($daily[$d] ?? 0) / 4), 'chars' => $daily[$d] ?? 0];
        }
        // Riwayat per generasi (20 terbaru) dihitung di bawah setelah peta profil tersedia (agar ada muse_profile_name).
        $jobHistory = MuseJob::select('id','keyword','model','durasi','status','created_at')->orderByDesc('created_at')->limit(20)->get();
        // Agregasi per profil Muse (job + generasi via MuseJob->muse_profile_id).
        // muse_profile_id null = job/generasi lama (token lama) — tetap ditampilkan sebagai "Tanpa profil".
        // Dibungkus try/catch agar halaman token usage tidak error bila migrasi muse_profiles belum jalan.
        $perProfile = [];
        $jobProfileIdMap = []; // muse_job_id => muse_profile_id
        $profileNames = []; // muse_profile_id => nama
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('muse_profiles') && \Illuminate\Support\Facades\Schema::hasColumn('muse_jobs', 'muse_profile_id')) {
                $profiles = \App\Models\MuseProfile::orderBy('id')->get();
                foreach ($profiles as $p) $profileNames[$p->id] = $p->name;
                $jobProfileIdMap = MuseJob::pluck('muse_profile_id', 'id')->all();
                $jobsByProfile = MuseJob::selectRaw('muse_profile_id, count(*) as total')->groupBy('muse_profile_id')->pluck('total', 'muse_profile_id')->all();
                $genStats = []; // profile_key => ['generations' => n, 'chars' => n]
                $gensForProfile = VideoGeneration::select('id', 'naskah', 'caption', 'prompts', 'muse_job_id')->whereNotNull('muse_job_id')->get();
                foreach ($gensForProfile as $g) {
                    $pid = $jobProfileIdMap[$g->muse_job_id] ?? null;
                    $key = $pid === null ? 'legacy' : (int) $pid;
                    $chars = mb_strlen(json_encode($g->naskah ?? '') . ' ' . json_encode($g->caption ?? '') . ' ' . json_encode($g->prompts ?? ''));
                    $genStats[$key] = $genStats[$key] ?? ['generations' => 0, 'chars' => 0];
                    $genStats[$key]['generations']++;
                    $genStats[$key]['chars'] += $chars;
                }
                // Kunci null dari GROUP BY menjadi '' di array PHP — normalisasi ke 'legacy'.
                $keys = array_unique(array_merge(array_map(fn ($p) => (int) $p->id, $profiles->all()), array_keys($genStats), array_map(fn ($k) => ($k === null || $k === '') ? 'legacy' : (int) $k, array_keys($jobsByProfile))));
                foreach ($keys as $key) {
                    $jobsCount = $key === 'legacy' ? (int) ($jobsByProfile[''] ?? 0) : (int) ($jobsByProfile[$key] ?? 0);
                    $gs = $genStats[$key] ?? ['generations' => 0, 'chars' => 0];
                    $perProfile[] = [
                        'muse_profile_id' => $key === 'legacy' ? null : $key,
                        'muse_profile_name' => $key === 'legacy' ? 'Tanpa profil (token lama)' : ($profileNames[$key] ?? "Profil #{$key}"),
                        'jobs' => $jobsCount,
                        'generations' => $gs['generations'],
                        'chars' => $gs['chars'],
                        'tokens' => (int) ceil($gs['chars'] / 4),
                    ];
                }
            }
        } catch (\Throwable) { $perProfile = []; }
        $history = VideoGeneration::select('id','keyword','model','durasi','naskah','caption','prompts','muse_job_id','created_at')->orderByDesc('created_at')->limit(20)->get()->map(function ($g) use ($jobProfileIdMap, $profileNames) {
            $chars = mb_strlen(json_encode($g->naskah ?? '') . ' ' . json_encode($g->caption ?? '') . ' ' . json_encode($g->prompts ?? ''));
            $pid = $g->muse_job_id !== null ? ($jobProfileIdMap[$g->muse_job_id] ?? null) : null;
            return ['id' => $g->id, 'keyword' => $g->keyword, 'model' => $g->model, 'durasi' => $g->durasi, 'muse_job_id' => $g->muse_job_id, 'muse_profile_id' => $pid, 'muse_profile_name' => $pid !== null ? ($profileNames[$pid] ?? null) : null, 'chars' => $chars, 'tokens' => (int) ceil($chars / 4), 'created_at' => $g->created_at?->toIso8601String()];
        });
        $jobHistory = $jobHistory->map(function ($j) use ($jobProfileIdMap, $profileNames) {
            $pid = $jobProfileIdMap[$j->id] ?? null;
            $arr = $j->toArray();
            $arr['muse_profile_id'] = $pid;
            $arr['muse_profile_name'] = $pid !== null ? ($profileNames[$pid] ?? null) : null;
            return $arr;
        });
        return response()->json(['success' => true, 'data' => [
            'per_profile' => $perProfile,
            'total_tokens' => $totalTokens,
            'total_chars' => $totalChars,
            'total_generations' => $totalGens,
            'total_jobs' => $jobs,
            'month_tokens' => $monthTokens,
            'month_limit' => $monthlyLimit,
            'remaining' => $remaining,
            'percent_used' => $pct,
            'daily' => $dailyList,
            'history' => $history,
            'job_history' => $jobHistory,
            'note' => 'Estimasi: 1 token ~ 4 karakter. Ini pemakaian untuk naskah/caption yang dihasilkan web, bukan billing resmi Muse. Ubah limit di Pengaturan > muse_monthly_token_limit.',
        ]]);
    }
}
