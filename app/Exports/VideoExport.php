<?php
namespace App\Exports;
use App\Models\VideoGeneration;
use Illuminate\Support\Collection;

/** Ekspor CSV ringan tanpa package berat. Dipakai AdminController@exportCsv + Analytics. */
class VideoExport
{
    public static function query(array $filters = []): Collection
    {
        $q = VideoGeneration::with('user:id,name,email')->latest();
        if (!empty($filters['user_id'])) $q->where('user_id', $filters['user_id']);
        if (!empty($filters['model'])) $q->where('model', $filters['model']);
        if (!empty($filters['date_from'])) $q->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $q->whereDate('created_at', '<=', $filters['date_to']);
        if (!empty($filters['search'])) $q->search($filters['search']);
        return $q->get();
    }
    public static function toCsv(Collection $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['tanggal', 'user', 'email', 'keyword', 'durasi', 'model', 'status', 'workflow_status']);
        foreach ($rows as $g) {
            fputcsv($out, [$g->created_at?->toDateTimeString(), $g->user?->name, $g->user?->email, $g->keyword, $g->durasi, $g->model, $g->status, $g->workflow_status]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }
    /** HTML siap print -> PDF via browser (tanpa package PDF berat). */
    public static function toHtml(Collection $rows, string $title = 'Laporan Video Ruang Dharma'): string
    {
        $body = '';
        foreach ($rows as $g) {
            $body .= '<tr><td>' . e($g->created_at?->toDateTimeString()) . '</td><td>' . e($g->user?->name ?? '-') . '</td><td>' . e($g->keyword) . '</td><td>' . e($g->durasi) . '</td><td>' . e($g->model) . '</td><td>' . e($g->status) . '</td><td>' . e($g->workflow_status) . '</td></tr>';
        }
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>'
            . '<style>body{font-family:sans-serif}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px;text-align:left}h1{font-size:20px}</style></head>'
            . '<body><h1>' . e($title) . '</h1><p>Total: ' . count($rows) . ' video • Dicetak: ' . now()->toDateTimeString() . '</p>'
            . '<table><thead><tr><th>Tanggal</th><th>User</th><th>Keyword</th><th>Durasi</th><th>Model</th><th>Status</th><th>Workflow</th></tr></thead><tbody>' . $body . '</tbody></table>'
            . '<script>window.onload=function(){window.print();}</script></body></html>';
    }
}
