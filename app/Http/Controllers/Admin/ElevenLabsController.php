<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\ElevenLabsService;
use Illuminate\Http\Request;

/**
 * Pengaturan ElevenLabs — menu Pengaturan → tab ElevenLabs (khusus admin).
 * API key disimpan terenkripsi dan TIDAK PERNAH dikembalikan utuh ke klien:
 * GET hanya memberi status "sudah diatur" + masked. Saat simpan, field API key
 * yang dikosongkan berarti key lama dipertahankan; centang remove_api_key untuk menghapus.
 */
class ElevenLabsController extends Controller
{
    public function show(ElevenLabsService $el)
    {
        return response()->json(['success' => true, 'data' => $el->publicConfig()]);
    }

    public function update(Request $request, ElevenLabsService $el)
    {
        $data = $request->validate([
            'api_key' => 'sometimes|nullable|string|max:300',
            'remove_api_key' => 'sometimes|boolean',
            'voice_ibu' => 'sometimes|nullable|string|max:100',
            'voice_anak' => 'sometimes|nullable|string|max:100',
            'voice_narator' => 'sometimes|nullable|string|max:100',
            'model_id' => 'sometimes|nullable|string|max:100',
        ]);
        $changed = [];
        if (!empty($data['remove_api_key'])) {
            Setting::set(ElevenLabsService::KEY_API_KEY, '');
            $changed[] = 'api_key (dihapus)';
        } elseif (!empty($data['api_key'])) {
            Setting::set(ElevenLabsService::KEY_API_KEY, encrypt($data['api_key']));
            $changed[] = 'api_key';
        }
        foreach (['voice_ibu' => ElevenLabsService::KEY_VOICE_IBU, 'voice_anak' => ElevenLabsService::KEY_VOICE_ANAK, 'voice_narator' => ElevenLabsService::KEY_VOICE_NARATOR, 'model_id' => ElevenLabsService::KEY_MODEL_ID] as $field => $key) {
            if (array_key_exists($field, $data)) {
                Setting::set($key, $data[$field] ?? '');
                $changed[] = $field;
            }
        }
        // Log hanya NAMA field yang berubah — nilai API key tidak pernah masuk log.
        ActivityLogger::fromRequest($request, 'setting.update', 'Admin memperbarui pengaturan ElevenLabs: ' . implode(', ', $changed));
        return response()->json(['success' => true, 'message' => 'Pengaturan ElevenLabs tersimpan.', 'data' => $el->publicConfig()]);
    }

    /** Daftar suara dari akun ElevenLabs untuk dipilih admin (sekaligus tes API key). */
    public function voices(ElevenLabsService $el)
    {
        try {
            return response()->json(['success' => true, 'data' => $el->voices()]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }
}
