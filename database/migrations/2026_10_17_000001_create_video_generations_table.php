<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('video_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->integer('durasi')->default(60);
            $table->string('model')->default('narasi');
            $table->json('naskah')->nullable();
            $table->longText('caption')->nullable();
            $table->json('prompts')->nullable();
            $table->string('status')->default('completed');
            $table->string('source')->default('local'); // local | muse
            // NOTE: FK ke muse_jobs TIDAK dibuat di sini. Migration ini jalan
            // SEBELUM 000003_create_muse_jobs_table, jadi tabel muse_jobs
            // belum ada dan MySQL gagal dengan error 1215
            // "Cannot add foreign key constraint". Kolom hanya dibuat
            // sebagai unsignedBigInteger + index; FK ditambahkan di
            // migration 000005_add_muse_job_fk_to_video_generations
            // setelah kedua tabel ada.
            $table->unsignedBigInteger('muse_job_id')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('video_generations'); }
};
