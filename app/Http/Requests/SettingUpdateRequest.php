<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->isAdmin(); }
    public function rules(): array
    {
        return [
            'daily_limit' => 'sometimes|integer|min:1|max:100',
            'blocked_keywords' => 'sometimes|array',
            'blocked_keywords.*' => 'string|max:100',
            'app_name' => 'sometimes|string|max:100',
            'app_logo' => 'sometimes|nullable|string|max:255',
            'app_description' => 'sometimes|nullable|string|max:500',
            'notification_templates' => 'sometimes|array',
            'password_min_length' => 'sometimes|integer|min:6|max:32',
            'session_timeout' => 'sometimes|integer|min:5|max:1440',
            'auto_generate_enabled' => 'sometimes|boolean',
            'auto_generate_themes' => 'sometimes|array|max:10',
            'auto_generate_themes.*' => 'string|max:100',
            'auto_generate_time' => 'sometimes|date_format:H:i',
            'muse_base_url' => 'sometimes|nullable|url|max:255',
            'muse_timeout' => 'sometimes|integer|min:5|max:60',
        ];
    }
    public function messages(): array
    {
        return [
            'daily_limit.min' => 'Limit global minimal 1.',
            'daily_limit.max' => 'Limit global maksimal 100.',
            'blocked_keywords.array' => 'Kata diblokir harus berupa array.',
            'auto_generate_time.date_format' => 'Format waktu auto-generate harus HH:MM.',
        ];
    }
}
