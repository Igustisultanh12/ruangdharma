<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tambahkan FK muse_job_id -> muse_jobs.id SETELAH kedua tabel ada.
        // Migration ini harus jalan terakhir (000005), setelah:
        // - 000001_create_video_generations_table (membuat kolom muse_job_id tanpa FK)
        // - 000003_create_muse_jobs_table (membuat tabel muse_jobs)
        // Guard hasTable/hasColumn + cek FK yang sudah ada agar aman di-rerun
        // dan tidak gagal jika FK sudah terpasang / kolom belum ada.
        if (! Schema::hasTable('video_generations') || ! Schema::hasTable('muse_jobs')) {
            return;
        }
        if (! Schema::hasColumn('video_generations', 'muse_job_id')) {
            return;
        }

        // Cek apakah FK sudah ada (MySQL: information_schema)
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'video_generations'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = 'video_generations_muse_job_id_foreign'"
        );
        if ($exists && (int) $exists->cnt > 0) {
            return;
        }

        Schema::table('video_generations', function (Blueprint $table) {
            $table->foreign('muse_job_id')
                ->references('id')
                ->on('muse_jobs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_generations')) {
            return;
        }
        if (! Schema::hasColumn('video_generations', 'muse_job_id')) {
            return;
        }

        // Hanya drop jika FK benar-benar ada (cek information_schema dulu,
        // karena dropForeign pada FK yang tidak ada akan throw).
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'video_generations'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = 'video_generations_muse_job_id_foreign'"
        );
        if (! $exists || (int) $exists->cnt === 0) {
            return;
        }

        Schema::table('video_generations', function (Blueprint $table) {
            $table->dropForeign('video_generations_muse_job_id_foreign');
        });
    }
};
