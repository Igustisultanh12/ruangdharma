<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Token FCM perangkat Android per pengguna — untuk notifikasi push walau aplikasi ditutup. */
return new class extends Migration {
    public function up(): void {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->string('platform', 20)->default('android');
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('device_tokens');
    }
};
