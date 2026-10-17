<?php
namespace App\Console\Commands;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\ActivityLogger;
use App\Services\ScriptGeneratorService;
use Illuminate\Console\Command;

class GenerateDailyVideos extends Command
{
    protected $signature = 'app:generate-daily {--themes=* : Tema manual (ganti tema dari settings)} {--dry-run : Tampilkan rencana tanpa menyimpan}';
    protected $description = 'Auto-generate harian: 3 video dari tema default untuk user admin pertama / system.';

    public function handle(ScriptGeneratorService $gen): int
    {
        if (!Setting::getBool('auto_generate_enabled', false) && empty($this->option('themes'))) {
            $this->info('Auto-generate nonaktif (settings auto_generate_enabled=false). Lewati.');
            return self::SUCCESS;
        }
        $themes = $this->option('themes') ?: Setting::getArray('auto_generate_themes', ['Rwa Bhineda', 'Canang Sari', 'Tri Sandhya']);
        $themes = array_values(array_slice($themes, 0, 3));
        $models = ['narasi', 'ngobrol', 'gabungan']; // rotasi 3 style
        $owner = User::whereIn('role', ['super_admin', 'admin'])->where('is_active', true)->orderBy('id')->first();
        if (!$owner) { $this->error('Tidak ada user admin aktif untuk pemilik video auto-generate.'); return self::FAILURE; }

        foreach ($themes as $i => $keyword) {
            $model = $models[$i % 3];
            $result = $gen->generate($keyword, 60, $model);
            if ($this->option('dry-run')) { $this->line("[DRY] {$keyword} ({$model}) untuk {$owner->email}"); continue; }
            $g = VideoGeneration::create([
                'user_id' => $owner->id, 'keyword' => $keyword, 'durasi' => 60, 'model' => $model,
                'naskah' => $result['naskah'], 'caption' => $result['caption'], 'prompts' => $result['prompts'],
                'status' => 'completed', 'source' => 'auto_daily', 'workflow_status' => 'draft',
            ]);
            ActivityLogger::log($owner, 'generate.auto', "Auto-generate harian: {$keyword} ({$model})", null, ['generation_id' => $g->id]);
            $this->info("OK: {$keyword} ({$model}) -> generation #{$g->id}");
        }
        return self::SUCCESS;
    }
}
