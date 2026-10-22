<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // Auto Backsound: user meminta dari Editor Audio, Muse mencarikan kandidat
        // backsound (gamelan Bali/Jawa atau yang cocok dengan tema video), kandidat
        // tampil sebagai preview di web, dan hanya setelah user menyetujui barulah
        // server menggabungkannya ke video (lewat alur mix updateBacksound yang ada).
        Schema::create('backsound_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->constrained('video_generations')->cascadeOnDelete();
            // queued = menunggu Muse mencari; searching = Muse sedang mencari;
            // preview = kandidat siap didengar user; rejected = user minta dicarikan lain;
            // done = sudah digabungkan; failed = pencarian gagal; cancelled = dibatalkan user.
            $table->string('status', 20)->default('queued')->index();
            $table->string('note')->nullable(); // petunjuk user, mis. "gamelan bali pelan"
            $table->string('candidate_name')->nullable();
            $table->string('candidate_url', 2048)->nullable();
            $table->string('candidate_source')->nullable(); // sumber + lisensi audio kandidat
            $table->float('candidate_duration_sec')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('backsound_requests');
    }
};
