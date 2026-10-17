<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tambahkan FK muse_job_id -> muse_jobs.id SETELAH kedua tabel ada.
     * Cek FK memakai Schema::getForeignKeys() (portabel: MySQL, MariaDB, SQLite, PostgreSQL)
     * — sebelumnya memakai information_schema yang tidak ada di SQLite sehingga
     * `php artisan migrate` gagal pada instalasi lokal default.
     */
    private function hasFk(): bool
    {
        foreach (Schema::getForeignKeys('video_generations') as $fk) {
            if (($fk['columns'] ?? []) === ['muse_job_id']) return true;
        }
        return false;
    }

    public function up(): void
    {
        if (! Schema::hasTable('video_generations') || ! Schema::hasTable('muse_jobs')) return;
        if (! Schema::hasColumn('video_generations', 'muse_job_id') || $this->hasFk()) return;

        Schema::table('video_generations', function (Blueprint $table) {
            $table->foreign('muse_job_id')->references('id')->on('muse_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_generations') || ! $this->hasFk()) return;
        Schema::table('video_generations', function (Blueprint $table) {
            $table->dropForeign(['muse_job_id']);
        });
    }
};
