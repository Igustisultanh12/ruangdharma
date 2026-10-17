<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membuat tabel personal_access_tokens persis seperti skeleton
     * Sanctum Laravel 11. Dibutuhkan karena login memakai
     * $user->createToken('web') dan tabel ini tidak pernah dibuat
     * di migration awal (sama seperti kasus tabel cache kemarin).
     * Tanpa tabel ini login gagal dengan:
     * SQLSTATE[42S02]: Table 'ruangdharma.personal_access_tokens' doesn't exist
     */
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
