<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNaskahRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return [
            'naskah' => 'required|array|min:1',
            'naskah.*.teks' => 'sometimes|string',
            'naskah.*.dialog' => 'sometimes|string',
            'naskah.*.speaker' => 'sometimes|string|max:50',
            'caption' => 'sometimes|nullable|string',
        ];
    }
    public function messages(): array { return ['naskah.required' => 'Naskah wajib diisi.', 'naskah.array' => 'Naskah harus berupa array.']; }
}
