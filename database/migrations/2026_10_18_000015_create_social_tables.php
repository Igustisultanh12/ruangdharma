<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('social_publishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('video_generations')->nullOnDelete();
            $table->string('platform', 20); // instagram, facebook, tiktok
            $table->string('status', 20)->default('queued'); // queued, processing, completed, failed
            $table->text('caption')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->string('post_url', 2048)->nullable();
            $table->string('post_id', 255)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'platform']);
            $table->index(['status']);
        });
        Schema::create('social_stats', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('account_username')->nullable();
            $table->integer('followers')->default(0);
            $table->integer('total_likes')->default(0);
            $table->integer('total_comments')->default(0);
            $table->integer('reach')->default(0);
            $table->integer('views')->default(0);
            $table->json('posts')->nullable();
            $table->json('recent_comments')->nullable();
            $table->json('daily')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['platform']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('social_publishes');
        Schema::dropIfExists('social_stats');
    }
};
