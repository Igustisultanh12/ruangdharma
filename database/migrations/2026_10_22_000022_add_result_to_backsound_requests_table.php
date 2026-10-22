<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // Riwayat mix: saat user menekan "Gabungkan", simpan file video hasil mix
        // + volume yang dipakai, supaya panel Auto Backsound bisa menampilkan
        // daftar hasil mix sebelumnya (nama backsound, waktu, tombol putar).
        Schema::table('backsound_requests', function (Blueprint $table) {
            $table->string('result_video_url', 2048)->nullable()->after('candidate_duration_sec');
            $table->unsignedSmallInteger('applied_volume')->nullable()->after('result_video_url');
        });
    }
    public function down(): void {
        Schema::table('backsound_requests', function (Blueprint $table) {
            $table->dropColumn(['result_video_url', 'applied_volume']);
        });
    }
};
