<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('backsounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_url', 2048);
            $table->integer('duration_sec')->nullable();
            $table->timestamps();
        });
        Schema::table('video_generations', function (Blueprint $table) {
            $table->string('backsound_url', 2048)->nullable()->after('video_url');
            $table->integer('backsound_volume')->default(20)->after('backsound_url'); // 0-100, 20 = pelan di bawah narasi
            $table->integer('backsound_trim_start')->default(0)->after('backsound_volume'); // detik
            $table->integer('backsound_trim_end')->nullable()->after('backsound_trim_start'); // detik, null = sampai habis video
            $table->boolean('has_subtitle')->default(false)->after('backsound_trim_end');
            $table->boolean('has_watermark')->default(false)->after('has_subtitle');
        });
    }
    public function down(): void {
        Schema::dropIfExists('backsounds');
        Schema::table('video_generations', function (Blueprint $table) {
            $table->dropColumn(['backsound_url','backsound_volume','backsound_trim_start','backsound_trim_end','has_subtitle','has_watermark']);
        });
    }
};
