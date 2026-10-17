<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        // Tabel settings sudah key-value (key, value). Migrasi ini hanya
        // memastikan key konfigurasi Muse tersedia dengan nilai default.
        // Token & webhook secret TIDAK diisi di sini — admin generate lewat menu Admin.
        $defaults = [
            ['key' => 'muse_base_url', 'value' => ''],
            ['key' => 'muse_timeout', 'value' => '15'],
        ];
        foreach ($defaults as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                ['value' => $row['value'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
    public function down(): void {
        DB::table('settings')->whereIn('key', ['muse_base_url','muse_timeout'])->delete();
    }
};
