<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        // Cek portabel via Schema::getForeignKeys() (information_schema tidak ada di SQLite).
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'quota_plan_id')) {
            $exists = collect(Schema::getForeignKeys('users'))->contains(fn ($fk) => ($fk['columns'] ?? []) === ['quota_plan_id']);
            if (! $exists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('quota_plan_id')->references('id')->on('quota_plans')->nullOnDelete();
                });
            }
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'quota_plan_id')) {
            try { Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['quota_plan_id'])); } catch (\Throwable) { }
        }
        Schema::dropIfExists('quota_plans');
    }
};
