<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    private function hasFk(): bool
    {
        foreach (Schema::getForeignKeys('muse_jobs') as $fk) {
            if (($fk['columns'] ?? []) === ['muse_profile_id']) return true;
        }
        return false;
    }
    public function up(): void
    {
        if (! Schema::hasTable('muse_jobs') || ! Schema::hasTable('muse_profiles')) return;
        if (! Schema::hasColumn('muse_jobs', 'muse_profile_id')) {
            Schema::table('muse_jobs', function (Blueprint $table) {
                $table->foreignId('muse_profile_id')->nullable()->after('user_id');
            });
        }
        if (! $this->hasFk()) {
            Schema::table('muse_jobs', function (Blueprint $table) {
                $table->foreign('muse_profile_id')->references('id')->on('muse_profiles')->nullOnDelete();
            });
        }
    }
    public function down(): void
    {
        if (! Schema::hasTable('muse_jobs')) return;
        if ($this->hasFk()) {
            Schema::table('muse_jobs', function (Blueprint $table) {
                $table->dropForeign(['muse_profile_id']);
            });
        }
        if (Schema::hasColumn('muse_jobs', 'muse_profile_id')) {
            Schema::table('muse_jobs', function (Blueprint $table) {
                $table->dropColumn('muse_profile_id');
            });
        }
    }
};
