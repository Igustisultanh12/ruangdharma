<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\ElevenLabsService;
use App\Services\MuseService;
use Illuminate\Http\Request;

/**
 * Endpoint ElevenLabs untuk Muse (auth X-Muse-Token).
 * API key TIDAK PERNAH keluar server: Muse mengirim teks + peran suara,
 * server yang memanggil ElevenLabs dan mengembalikan audio MP3-nya.
 * Dipakai saat job video memakai engine = "elevenlabs" (audio dialog/narasinya).
 */
class MuseElevenLabsController extends Controller
{
    private function check(Request $request, MuseService $muse): ?\Illuminate\Http\JsonResponse
    {
        if (!$muse->verifyToken($request->header('X-Muse-Token'), $request->bearerToken())) return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        return null;
    }

    /** Konfigurasi non-rahasia: apakah ElevenLabs siap + pemetaan peran → voice_id + model. */
    public function config(Request $request, MuseService $muse, ElevenLabsService $el)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $c = $el->publicConfig();
        unset($c['api_key_masked']);
        return response()->json(['success' => true, 'data' => $c]);
    }

    /** POST {text, voice: ibu|anak|narator, voice_id?, model_id?} → audio/mpeg (MP3). */
    public function tts(Request $request, MuseService $muse, ElevenLabsService $el)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate([
            'text' => 'required|string|max:1000',
            'voice' => 'sometimes|in:ibu,anak,narator',
            'voice_id' => 'sometimes|nullable|string|max:100',
            'model_id' => 'sometimes|nullable|string|max:100',
        ]);
        if (!$el->configured()) return response()->json(['success' => false, 'message' => 'ElevenLabs belum diatur admin (Pengaturan → ElevenLabs). Job suara ElevenLabs belum bisa diproduksi.'], 422);
        $voiceId = $data['voice_id'] ?? $el->voiceIdForRole($data['voice'] ?? 'narator');
        if (!$voiceId) return response()->json(['success' => false, 'message' => 'Voice ID untuk peran "' . ($data['voice'] ?? 'narator') . '" belum diatur admin (Pengaturan → ElevenLabs).'], 422);
        try {
            $audio = $el->tts($data['text'], $voiceId, $data['model_id'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
        return response($audio, 200)->header('Content-Type', 'audio/mpeg')->header('X-Voice-Id', $voiceId);
    }

    /**
     * Submit generate video ElevenLabs (Image & Video / Flows, asinkron).
     * POST {prompt, model_id?, aspect_ratio?, duration_secs?, resolution?, generate_audio?, negative_prompt?, seed?}
     * → respons API ElevenLabs apa adanya (generation id + status). Polling via videoStatus (maks ±1x/10 detik).
     */
    public function submitVideo(Request $request, MuseService $muse, ElevenLabsService $el)
    {
        if ($r = $this->check($request, $muse)) return $r;
        $data = $request->validate([
            'prompt' => 'sometimes|nullable|string|max:5000',
            'model_id' => 'sometimes|nullable|string|max:100',
            'aspect_ratio' => 'sometimes|nullable|string|max:10',
            'duration_secs' => 'sometimes|nullable|numeric|min:1|max:30',
            'resolution' => 'sometimes|nullable|string|max:10',
            'generate_audio' => 'sometimes|boolean',
            'negative_prompt' => 'sometimes|nullable|string|max:1000',
            'seed' => 'sometimes|nullable|integer',
            'start_frame' => 'sometimes|nullable|string|max:2000',
            'end_frame' => 'sometimes|nullable|string|max:2000',
            'images' => 'sometimes|array|max:9',
            'videos' => 'sometimes|array|max:3',
            'audios' => 'sometimes|array|max:3',
            'image' => 'sometimes|nullable|string|max:2000',
            'audio' => 'sometimes|nullable|string|max:2000',
            'guidance_scale' => 'sometimes|nullable|numeric',
            'audio_guidance_scale' => 'sometimes|nullable|numeric|min:0|max:5',
        ]);
        if (!$el->configured()) return response()->json(['success' => false, 'message' => 'ElevenLabs belum diatur admin (Pengaturan → ElevenLabs). Job generate ElevenLabs belum bisa diproduksi.'], 422);
        try {
            $result = $el->submitVideo($data, $data['model_id'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
        return response()->json(['success' => true, 'data' => $result]);
    }

    /** Status + hasil generate video (selesai → berisi URL output dari ElevenLabs). */
    public function videoStatus(Request $request, string $generationId, MuseService $muse, ElevenLabsService $el)
    {
        if ($r = $this->check($request, $muse)) return $r;
        if (!$el->configured()) return response()->json(['success' => false, 'message' => 'ElevenLabs belum diatur admin (Pengaturan → ElevenLabs).'], 422);
        try {
            $result = $el->videoGeneration($generationId);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
        return response()->json(['success' => true, 'data' => $result]);
    }
}
