<?php
namespace App\Services;
use App\Models\User;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function summary(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $base = VideoGeneration::where('created_at', '>=', $from);
        $perDay = (clone $base)->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))->groupBy('date')->orderBy('date')->get();
        // Isi tanggal kosong dengan 0 agar grafik kontinu.
        $map = $perDay->pluck('total', 'date')->toArray();
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $series[] = ['date' => $d, 'total' => (int) ($map[$d] ?? 0)];
        }
        return [
            'days' => $days,
            'per_day' => $series,
            'total' => (clone $base)->count(),
            'top_keywords' => (clone $base)->select('keyword', DB::raw('count(*) as total'))->groupBy('keyword')->orderByDesc('total')->limit(10)->get(),
            'top_users' => (clone $base)->select('user_id', DB::raw('count(*) as total'))->with('user:id,name,email')->groupBy('user_id')->orderByDesc('total')->limit(10)->get(),
            'avg_durasi' => round((float) ((clone $base)->avg('durasi') ?? 0), 1),
            'by_model' => (clone $base)->select('model', DB::raw('count(*) as total'))->groupBy('model')->pluck('total', 'model'),
            'by_durasi' => (clone $base)->select('durasi', DB::raw('count(*) as total'))->groupBy('durasi')->orderBy('durasi')->pluck('total', 'durasi'),
            'by_workflow_status' => (clone $base)->select('workflow_status', DB::raw('count(*) as total'))->groupBy('workflow_status')->pluck('total', 'workflow_status'),
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
        ];
    }
}
