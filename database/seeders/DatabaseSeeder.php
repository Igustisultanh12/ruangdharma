<?php
namespace Database\Seeders;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder {
    public function run(): void {
        User::firstOrCreate(['email'=>'admin@ruangdharma.id'], ['name'=>'Admin Ruang Dharma','password'=>Hash::make('admin123'),'role'=>'admin','is_active'=>true]);
        User::firstOrCreate(['email'=>'user@ruangdharma.id'], ['name'=>'Pengguna Demo','password'=>Hash::make('user123'),'role'=>'pengguna','is_active'=>true]);
        Setting::setValue('daily_limit', '5');
        Setting::setValue('blocked_keywords', json_encode(['politik','judi','pornografi','kekerasan']));
        Setting::setValue('app_name', 'Ruang Dharma Studio');
        Setting::setValue('muse_base_url', '');
        Setting::setValue('muse_timeout', '15');
        // muse_api_token & muse_webhook_secret TIDAK di-seed — admin generate lewat menu Admin > Integrasi Muse.
    }
}
