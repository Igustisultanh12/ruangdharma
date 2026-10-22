<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'avatar' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'password' => 'sometimes|nullable|string|min:' . \App\Models\Setting::getInt('password_min_length', 6) . '|confirmed',
            'current_password' => 'sometimes|required_with:password|string',
            'default_durasi' => 'sometimes|nullable|integer|in:30,60,90',
            'default_model' => 'sometimes|nullable|string|in:narasi,ngobrol,gabungan,presenter',
        ];
    }
    public function messages(): array
    {
        return [
            'name.max' => 'Nama maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'avatar.image' => 'Avatar harus berupa gambar.',
            'avatar.max' => 'Avatar maksimal 2MB.',
            'password.min' => 'Password baru minimal :min karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'default_durasi.in' => 'Durasi default harus 30, 60, atau 90.',
            'default_model.in' => 'Model default harus narasi, ngobrol, gabungan, atau presenter.',
        ];
    }
}
