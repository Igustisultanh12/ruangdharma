<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return ['body' => 'required|string|max:2000', 'parent_id' => 'sometimes|nullable|integer|exists:comments,id'];
    }
    public function messages(): array { return ['body.required' => 'Komentar wajib diisi.', 'body.max' => 'Komentar maksimal 2000 karakter.']; }
}
