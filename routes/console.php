<?php
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Setting;

Artisan::command('inspire', function () { $this->comment(Inspiring::quote()); })->purpose('Display an inspiring quote');

// Auto-generate harian: waktu dari settings auto_generate_time (default 07:00),
// fallback daily() jika settings belum tersedia (mis. tabel belum migrate).
Schedule::command('app:generate-daily')->dailyAt((function () {
    try { return Setting::get('auto_generate_time', '07:00') ?: '07:00'; } catch (\Throwable) { return '07:00'; }
})())->name('generate-daily-videos')->withoutOverlapping();
