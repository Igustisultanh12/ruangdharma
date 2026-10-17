<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('video_generation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_generation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('naskah')->nullable();
            $table->longText('caption')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['video_generation_id', 'version_number'], 'vgv_gen_ver_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('video_generation_versions'); }
};
