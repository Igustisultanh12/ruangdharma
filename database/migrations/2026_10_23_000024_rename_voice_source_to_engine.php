<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        foreach (['video_generations', 'muse_jobs'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'voice_source') && ! Schema::hasColumn($t, 'engine')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->renameColumn('voice_source', 'engine');
                });
            }
        }
    }
    public function down(): void
    {
        foreach (['video_generations', 'muse_jobs'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'engine') && ! Schema::hasColumn($t, 'voice_source')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->renameColumn('engine', 'voice_source');
                });
            }
        }
    }
};
