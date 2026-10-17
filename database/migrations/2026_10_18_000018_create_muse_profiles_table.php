<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('muse_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Token & webhook secret disimpan terenkripsi via cast 'encrypted' di model.
            // TIDAK PERNAH dikembalikan utuh ke frontend — hanya versi masked.
            $table->text('api_token');
            $table->text('webhook_secret')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'is_default']);
        });
    }
    public function down(): void { Schema::dropIfExists('muse_profiles'); }
};
