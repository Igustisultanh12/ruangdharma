<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('quota_plans', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique();
            $table->unsignedInteger('daily_limit');
            $table->unsignedBigInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        // FK users.quota_plan_id -> quota_plans.id (kolom dibuat di 000004 tanpa FK karena urutan).
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'quota_plan_id')) {
            $exists = DB::selectOne("SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'users_quota_plan_id_foreign'");
            if (!$exists || (int) $exists->cnt === 0) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('quota_plan_id')->references('id')->on('quota_plans')->nullOnDelete();
                });
            }
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'quota_plan_id')) {
            try { Schema::table('users', fn(Blueprint $t) => $t->dropForeign('users_quota_plan_id_foreign')); } catch (\Throwable) { }
        }
        Schema::dropIfExists('quota_plans');
    }
};
