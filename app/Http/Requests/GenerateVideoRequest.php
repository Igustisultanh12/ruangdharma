<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class GenerateVideoRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return [
            'keyword' => 'required|string|max:100',
            'durasi' => 'required|integer|in:30,60,90',
            'model' => 'required|string|in:narasi,ngobrol,gabungan,presenter',
            // Mesin generate untuk video via Muse: flow = video+audio Google Flow; elevenlabs = video+audio ElevenLabs.
            'engine' => 'sometimes|string|in:flow,elevenlabs',
            'voice_source' => 'sometimes|string|in:flow,elevenlabs', // alias lama dari engine
            'via_muse' => 'sometimes|boolean',
            'async' => 'sometimes|boolean',
            // Multi-Muse: profil Muse yang mengerjakan job (opsional; kosong = profil default).
            // Keberadaan & status aktif dicek di controller via MuseService (bukan exists: agar tidak error sebelum migrasi jalan).
            'muse_profile_id' => 'sometimes|nullable|integer',
            'backsound_url' => 'sometimes|nullable|string|max:2048',
            'backsound_volume' => 'sometimes|integer|min:0|max:100',
            'backsound_trim_start' => 'sometimes|numeric|min:0',
            'backsound_trim_end' => 'sometimes|nullable|numeric|min:0',
        ];
    }
    public function messages(): array
    {
        return [
            'keyword.required' => 'Kata kunci wajib diisi.',
            'keyword.max' => 'Kata kunci maksimal 100 karakter.',
            'durasi.required' => 'Durasi wajib dipilih.',
            'durasi.in' => 'Durasi harus 30, 60, atau 90 detik.',
            'model.required' => 'Model video wajib dipilih.',
            'model.in' => 'Model harus narasi, ngobrol, gabungan, atau presenter.',
        ];
    }
}
