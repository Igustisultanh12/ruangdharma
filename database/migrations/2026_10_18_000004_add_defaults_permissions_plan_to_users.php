<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('default_durasi')->default(60)->after('daily_limit');
            $table->string('default_model')->default('narasi')->after('default_durasi');
            $table->json('permissions')->nullable()->after('default_model'); // fallback permission per-user
            // Tanpa FK di sini: tabel quota_plans baru dibuat di migration 000009.
            // Kolom hanya unsignedBigInteger + index; FK ditambahkan di 000009.
            $table->unsignedBigInteger('quota_plan_id')->nullable()->after('permissions')->index();
        });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['default_durasi', 'default_model', 'permissions', 'quota_plan_id']);
        });
    }
};
