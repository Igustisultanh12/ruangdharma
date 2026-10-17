<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MuseProfile extends Model
{
    protected $fillable = ['name', 'slug', 'api_token', 'webhook_secret', 'description', 'is_active', 'is_default', 'last_used_at'];
    // Cast 'encrypted': nilai yang disimpan terenkripsi di DB, otomatis didekripsi saat dibaca.
    // Assign nilai PLAINTEXT ke atribut ini — JANGAN assign hasil encrypt() manual (akan terenkripsi ganda).
    protected $casts = ['api_token' => 'encrypted', 'webhook_secret' => 'encrypted', 'is_active' => 'boolean', 'is_default' => 'boolean', 'last_used_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $profile) {
            if (empty($profile->slug) && !empty($profile->name)) $profile->slug = Str::slug($profile->name);
        });
    }

    public function jobs(): HasMany { return $this->hasMany(MuseJob::class); }

    /** Masked: hanya 4 karakter terakhir, mis. ****abcd. Token utuh TIDAK PERNAH dikirim ke frontend. */
    public static function mask(?string $plain): ?string
    {
        if (!$plain) return null;
        if (strlen($plain) <= 8) return '****';
        return '****' . substr($plain, -4);
    }

    public function maskedToken(): ?string { return static::mask($this->api_token); }
    public function maskedSecret(): ?string { return static::mask($this->webhook_secret); }

    /** Representasi aman untuk frontend: tanpa token/secret utuh. */
    public function safeArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'api_token_masked' => $this->maskedToken(),
            'webhook_secret_masked' => $this->maskedSecret(),
            'has_token' => (bool) $this->api_token,
            'has_webhook_secret' => (bool) $this->webhook_secret,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
