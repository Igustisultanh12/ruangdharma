<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Trim backsound kini desimal (mis. 76.2 / 140.4, step 0.1 dari TrimTimeline).
// Sebelumnya kolom ini INTEGER sehingga 76.2 terpotong jadi 76, dan validasi
// backend 'integer' menolak float dengan "must be an integer".
// Migration ini idempotent: kolom yang sudah float/double/decimal dilewati,
// dan tidak ada data yang dihapus — nilai integer lama (mis. 76) tetap 76.0.
// Tanpa doctrine/dbal, ALTER memakai DB::statement (MySQL).
return new class extends Migration {
    public function up(): void
    {
        $this->convert('video_generations', 'backsound_trim_start', 'FLOAT NOT NULL DEFAULT 0');
        $this->convert('video_generations', 'backsound_trim_end', 'FLOAT NULL DEFAULT NULL');
        // duration_sec dipakai BacksoundPicker sebagai batas atas trim, jadi
        // ikut diubah ke float agar durasi 140.4 tidak terpangkas jadi 140.
        $this->convert('backsounds', 'duration_sec', 'FLOAT NULL DEFAULT NULL');
    }

    protected function convert(string $table, string $column, string $definition): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || DB::getDriverName() === 'sqlite') {
            return;
        }
        $type = DB::selectOne(
            'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        )->DATA_TYPE ?? null;
        if (in_array(strtolower((string) $type), ['float', 'double', 'decimal'], true)) {
            return; // Sudah float — aman diulang.
        }
        DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$definition}");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Kembalikan ke integer (nilai desimal yang ada akan dibulatkan MySQL).
        if (Schema::hasTable('video_generations') && Schema::hasColumn('video_generations', 'backsound_trim_start')) {
            DB::statement('ALTER TABLE `video_generations` MODIFY COLUMN `backsound_trim_start` INT NOT NULL DEFAULT 0');
        }
        if (Schema::hasTable('video_generations') && Schema::hasColumn('video_generations', 'backsound_trim_end')) {
            DB::statement('ALTER TABLE `video_generations` MODIFY COLUMN `backsound_trim_end` INT NULL DEFAULT NULL');
        }
        if (Schema::hasTable('backsounds') && Schema::hasColumn('backsounds', 'duration_sec')) {
            DB::statement('ALTER TABLE `backsounds` MODIFY COLUMN `duration_sec` INT NULL DEFAULT NULL');
        }
    }
};
