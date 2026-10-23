<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        foreach (['video_generations', 'muse_jobs'] as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'voice_source')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->string('voice_source', 20)->default('flow');
                });
            }
        }
    }
    public function down(): void
    {
        foreach (['video_generations', 'muse_jobs'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'voice_source')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropColumn('voice_source');
                });
            }
        }
    }
};
