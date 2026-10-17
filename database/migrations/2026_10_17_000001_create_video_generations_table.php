<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('video_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->integer('durasi')->default(60);
            $table->string('model')->default('narasi');
            $table->json('naskah')->nullable();
            $table->longText('caption')->nullable();
            $table->json('prompts')->nullable();
            $table->string('status')->default('completed');
            $table->string('source')->default('local'); // local | muse
            $table->foreignId('muse_job_id')->nullable()->constrained('muse_jobs')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('video_generations'); }
};
