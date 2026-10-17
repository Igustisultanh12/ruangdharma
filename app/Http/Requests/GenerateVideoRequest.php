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
            'model' => 'required|string|in:narasi,ngobrol,gabungan',
            'via_muse' => 'sometimes|boolean',
            'async' => 'sometimes|boolean',
            'backsound_url' => 'sometimes|nullable|string|max:2048',
            'backsound_volume' => 'sometimes|integer|min:0|max:100',
            'backsound_trim_start' => 'sometimes|integer|min:0',
            'backsound_trim_end' => 'sometimes|nullable|integer|min:0',
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
            'model.in' => 'Model harus narasi, ngobrol, atau gabungan.',
        ];
    }
}
