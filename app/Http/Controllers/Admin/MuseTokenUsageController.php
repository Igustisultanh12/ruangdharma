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
        // Kuota: ambil dari settings, default 1.000.000 token / bulan (bisa diubah di Pengaturan)
        $monthlyLimit = (int) (\App\Models\Setting::get('muse_monthly_token_limit', 1000000));
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
        return response()->json(['success' => true, 'data' => [
            'total_tokens' => $totalTokens,
            'total_chars' => $totalChars,
            'total_generations' => $totalGens,
            'total_jobs' => $jobs,
            'month_tokens' => $monthTokens,
            'month_limit' => $monthlyLimit,
            'remaining' => $remaining,
            'percent_used' => $pct,
            'daily' => $dailyList,
            'note' => 'Estimasi: 1 token ~ 4 karakter. Ini pemakaian untuk naskah/caption yang dihasilkan web, bukan billing resmi Muse. Ubah limit di Pengaturan > muse_monthly_token_limit.',
        ]]);
    }
}
