<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('video_generations', function (Blueprint $table) {
            if (!Schema::hasColumn('video_generations', 'video_url')) $table->string('video_url', 2048)->nullable()->after('muse_job_id');
            if (!Schema::hasColumn('video_generations', 'video_files')) $table->json('video_files')->nullable()->after('video_url');
        });
    }
    public function down(): void {
        Schema::table('video_generations', function (Blueprint $table) {
            if (Schema::hasColumn('video_generations', 'video_url')) $table->dropColumn('video_url');
            if (Schema::hasColumn('video_generations', 'video_files')) $table->dropColumn('video_files');
        });
    }
};
