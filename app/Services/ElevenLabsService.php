<?php
namespace App\Services;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integrasi ElevenLabs (text-to-speech) untuk Ruang Dharma Studio.
 *
 * API key diatur admin di Pengaturan → ElevenLabs, disimpan TERENKRIPSI
 * (encrypt(), pola sama seperti token Muse di MuseConfigController) di tabel
 * settings, dan TIDAK PERNAH dikirim keluar server: Muse meminta audio lewat
 * endpoint proxy POST /api/muse/elevenlabs/tts (teks + peran suara masuk,
 * MP3 keluar), jadi key tidak pernah muncul di payload job / log / klien.
 */
class ElevenLabsService
{
    public const KEY_API_KEY = 'elevenlabs_api_key';
    public const KEY_VOICE_IBU = 'elevenlabs_voice_ibu';
    public const KEY_VOICE_ANAK = 'elevenlabs_voice_anak';
    public const KEY_VOICE_NARATOR = 'elevenlabs_voice_narator';
    public const KEY_MODEL_ID = 'elevenlabs_model_id';
    public const KEY_VIDEO_MODEL = 'elevenlabs_video_model';
    public const DEFAULT_MODEL_ID = 'eleven_multilingual_v2';
    public const DEFAULT_VIDEO_MODEL = 'veo-3.1-fast-generate-001';
    /** Model video yang tersedia lewat API Image & Video / Flows (docs ElevenLabs, Okt 2026). */
    public const VIDEO_MODELS = ['veo-3.1-generate-001', 'veo-3.1-fast-generate-001', 'bytedance-seedance-v2', 'bytedance-seedance-v2-fast', 'bytedance-seedance-v2-mini', 'bytedance-seedance-v2.5', 'creatify-aurora'];
    public const ROLES = ['ibu', 'anak', 'narator'];
    private const BASE = 'https://api.elevenlabs.io/v1';

    public function apiKey(): ?string
    {
        $raw = Setting::get(self::KEY_API_KEY);
        if (!$raw) return null;
        try { return decrypt($raw); } catch (\Throwable) { return $raw; } // fallback: nilai lama yang belum terenkripsi
    }

    public function configured(): bool { return !empty($this->apiKey()); }

    public function maskedKey(): ?string
    {
        $k = $this->apiKey();
        if (!$k) return null;
        return '••••••••' . substr($k, -4);
    }

    public function modelId(): string
    {
        return Setting::get(self::KEY_MODEL_ID, self::DEFAULT_MODEL_ID) ?: self::DEFAULT_MODEL_ID;
    }

    public function videoModel(): string
    {
        return Setting::get(self::KEY_VIDEO_MODEL, self::DEFAULT_VIDEO_MODEL) ?: self::DEFAULT_VIDEO_MODEL;
    }

    public function voiceIdForRole(string $role): ?string
    {
        $key = match ($role) {
            'ibu' => self::KEY_VOICE_IBU,
            'anak' => self::KEY_VOICE_ANAK,
            default => self::KEY_VOICE_NARATOR,
        };
        $v = Setting::get($key);
        return $v ?: null;
    }

    /** Konfigurasi NON-rahasia (tanpa API key utuh) — aman untuk UI admin & Muse. */
    public function publicConfig(): array
    {
        return [
            'configured' => $this->configured(),
            'api_key_masked' => $this->maskedKey(),
            'voice_ibu' => $this->voiceIdForRole('ibu'),
            'voice_anak' => $this->voiceIdForRole('anak'),
            'voice_narator' => $this->voiceIdForRole('narator'),
            'model_id' => $this->modelId(),
            'video_model' => $this->videoModel(),
        ];
    }

    /** Daftar suara dari akun ElevenLabs (untuk pilihan di Pengaturan admin). */
    public function voices(): array
    {
        $key = $this->apiKey();
        if (!$key) throw new RuntimeException('API key ElevenLabs belum diatur. Isi dulu di Pengaturan → ElevenLabs.');
        $res = Http::timeout(30)->withHeaders(['xi-api-key' => $key])->get(self::BASE . '/voices');
        if (!$res->successful()) throw new RuntimeException('ElevenLabs menolak permintaan daftar suara (HTTP ' . $res->status() . '). Periksa API key-nya.');
        return collect($res->json('voices') ?? [])->map(fn ($v) => [
            'voice_id' => $v['voice_id'] ?? null,
            'name' => $v['name'] ?? '',
            'category' => $v['category'] ?? '',
            'labels' => $v['labels'] ?? [],
            'preview_url' => $v['preview_url'] ?? null,
        ])->filter(fn ($v) => $v['voice_id'])->values()->all();
    }

    /** Sintesis satu teks menjadi MP3 (string binary). */
    public function tts(string $text, string $voiceId, ?string $modelId = null): string
    {
        $key = $this->apiKey();
        if (!$key) throw new RuntimeException('API key ElevenLabs belum diatur admin (Pengaturan → ElevenLabs).');
        $res = Http::timeout(120)->withHeaders(['xi-api-key' => $key, 'Accept' => 'audio/mpeg'])
            ->post(self::BASE . '/text-to-speech/' . $voiceId . '?output_format=mp3_44100_128', [
                'text' => $text,
                'model_id' => $modelId ?: $this->modelId(),
                // Sesuai docs: language_code (ISO 639-1) dipakai model yang mendukungnya
                // (flash/turbo); pada eleven_multilingual_v2 parameter ini diabaikan.
                'language_code' => 'id',
                'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75, 'use_speaker_boost' => true],
            ]);
        if (!$res->successful()) {
            $msg = $res->json('detail.message') ?? $res->json('detail') ?? ('HTTP ' . $res->status());
            if (is_array($msg)) $msg = json_encode($msg);
            throw new RuntimeException('ElevenLabs gagal membuat suara: ' . $msg);
        }
        return $res->body();
    }

    /**
     * Submit generate video ke Image & Video API (Flows) — ASINKRON.
     * POST /v1/flows/video dengan body satu objek berkunci nama model
     * (satu request = satu model), mis. {"veo-3.1-fast-generate-001": {prompt, aspect_ratio, ...}}.
     * Mengembalikan respons API apa adanya (berisi generation id + status awal).
     * Syarat dari docs: plan Pro ke atas + API key berizin Image & Video / Flows,
     * jika tidak API membalas 402 paid_plan_required / 403.
     */
    public function submitVideo(array $params, ?string $modelId = null): array
    {
        $key = $this->apiKey();
        if (!$key) throw new RuntimeException('API key ElevenLabs belum diatur admin (Pengaturan → ElevenLabs).');
        $model = $modelId ?: $this->videoModel();
        // PENTING (docs): tiap model punya request class sendiri dan field yang tidak
        // dikenal model itu DITOLAK (bukan diabaikan) — jadi parameter disaring per model.
        $allowed = match (true) {
            str_starts_with($model, 'veo-') => ['prompt', 'aspect_ratio', 'duration_secs', 'resolution', 'generate_audio', 'negative_prompt', 'seed', 'enhance_prompt', 'start_frame', 'end_frame', 'images', 'webhook'],
            str_starts_with($model, 'bytedance-seedance') => ['prompt', 'aspect_ratio', 'duration_secs', 'resolution', 'generate_audio', 'seed', 'start_frame', 'end_frame', 'images', 'videos', 'audios', 'webhook'],
            $model === 'creatify-aurora' => ['image', 'audio', 'resolution', 'guidance_scale', 'audio_guidance_scale', 'webhook'],
            default => ['prompt', 'aspect_ratio', 'duration_secs', 'resolution', 'generate_audio', 'seed', 'webhook'],
        };
        $clean = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $params) && $params[$k] !== null && $params[$k] !== '') $clean[$k] = $params[$k];
        }
        if (empty($clean['prompt']) && $model !== 'creatify-aurora') throw new RuntimeException('Prompt video wajib diisi.');
        $res = Http::timeout(120)->withHeaders(['xi-api-key' => $key])->post(self::BASE . '/flows/video', [$model => $clean]);
        if (!$res->successful()) {
            $msg = $res->json('detail.message') ?? $res->json('detail') ?? ('HTTP ' . $res->status());
            if (is_array($msg)) $msg = json_encode($msg);
            throw new RuntimeException('ElevenLabs gagal submit video (HTTP ' . $res->status() . '): ' . $msg);
        }
        $json = $res->json();
        return is_array($json) ? $json : [];
    }

    /** Status + hasil satu generate video (GET /v1/flows/video/{id}) — respons API apa adanya. */
    public function videoGeneration(string $generationId): array
    {
        $key = $this->apiKey();
        if (!$key) throw new RuntimeException('API key ElevenLabs belum diatur admin (Pengaturan → ElevenLabs).');
        $res = Http::timeout(60)->withHeaders(['xi-api-key' => $key])->get(self::BASE . '/flows/video/' . $generationId);
        if (!$res->successful()) {
            $msg = $res->json('detail.message') ?? $res->json('detail') ?? ('HTTP ' . $res->status());
            if (is_array($msg)) $msg = json_encode($msg);
            throw new RuntimeException('ElevenLabs gagal membaca status video (HTTP ' . $res->status() . '): ' . $msg);
        }
        $json = $res->json();
        return is_array($json) ? $json : [];
    }
}
