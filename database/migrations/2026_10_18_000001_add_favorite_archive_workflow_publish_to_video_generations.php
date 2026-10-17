<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('muse_job_id');
            $table->boolean('is_archived')->default(false)->after('is_favorite');
            $table->string('workflow_status')->default('draft')->after('is_archived'); // draft|review|revisi|approved|published|archived
            $table->text('review_note')->nullable()->after('workflow_status');
            $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->timestamp('publish_at')->nullable()->after('reviewed_at');
            $table->index(['workflow_status', 'created_at']);
            $table->index(['publish_at']);
        });
    }
    public function down(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->dropColumn(['is_favorite', 'is_archived', 'workflow_status', 'review_note', 'reviewed_by', 'reviewed_at', 'publish_at']);
        });
    }
};
