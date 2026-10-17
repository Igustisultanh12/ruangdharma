<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('muse_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword');
            $table->integer('durasi')->default(60);
            $table->string('model')->default('narasi');
            $table->string('status')->default('queued'); // queued|processing|completed|failed
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->string('callback_url')->nullable();
            $table->string('source')->default('muse_api');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('muse_jobs'); }
};
