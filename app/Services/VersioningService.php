<?php
namespace App\Services;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Models\VideoGenerationVersion;

class VersioningService
{
    /** Simpan snapshot naskah+caption saat ini sebagai versi baru, lalu terapkan naskah baru. */
    public function snapshot(VideoGeneration $g, ?User $editor = null): VideoGenerationVersion
    {
        $next = ((int) ($g->versions()->max('version_number') ?? 0)) + 1;
        return VideoGenerationVersion::create([
            'video_generation_id' => $g->id, 'version_number' => $next,
            'naskah' => $g->naskah ?? [], 'caption' => $g->captionText(),
            'edited_by' => $editor?->id, 'created_at' => now(),
        ]);
    }
    public function updateNaskah(VideoGeneration $g, array $naskah, ?string $caption, ?User $editor = null): VideoGeneration
    {
        $this->snapshot($g, $editor);
        $g->update(['naskah' => $naskah] + ($caption !== null ? ['caption' => $caption] : []));
        return $g->fresh();
    }
    /** Restore: snapshot kondisi saat ini dulu, lalu kembalikan versi lama. */
    public function restore(VideoGeneration $g, VideoGenerationVersion $version, ?User $editor = null): VideoGeneration
    {
        if ($version->video_generation_id !== $g->id) throw new \InvalidArgumentException('Versi bukan milik video ini.');
        $this->snapshot($g, $editor);
        $g->update(['naskah' => $version->naskah ?? [], 'caption' => $version->caption]);
        return $g->fresh();
    }
}
