<?php
namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoGenerationFactory extends Factory
{
    public function definition(): array
    {
        $keyword = fake()->randomElement(['Rwa Bhineda', 'Canang Sari', 'Tri Sandhya', 'Karma', 'Bhatara Sri', 'Tri Hita Karana']);
        return [
            'user_id' => User::factory(),
            'keyword' => $keyword,
            'durasi' => fake()->randomElement([30, 60, 90]),
            'model' => fake()->randomElement(['narasi', 'ngobrol', 'gabungan']),
            'naskah' => [['bagian' => 1, 'speaker' => 'Narator', 'teks' => 'Om Swastyastu! Contoh naskah.', 'suara' => 'Narator Aria']],
            'caption' => "Contoh caption {$keyword} #RuangDharma\n\nOm Santi Santi Santi Om 🙏",
            'prompts' => ['3D Disney/Pixar cartoon, vertical 9:16, contoh prompt visual.'],
            'status' => 'completed', 'source' => 'local', 'workflow_status' => 'draft',
            'is_favorite' => false, 'is_archived' => false,
        ];
    }
    public function favorite(): static { return $this->state(fn() => ['is_favorite' => true]); }
    public function archived(): static { return $this->state(fn() => ['is_archived' => true, 'workflow_status' => 'archived']); }
    public function published(): static { return $this->state(fn() => ['workflow_status' => 'published', 'publish_at' => now()]); }
}
