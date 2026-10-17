<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Token FCM satu perangkat. Disimpan dengan hash agar unik & mudah dicari tanpa membandingkan teks panjang. */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'token_hash', 'platform', 'device_name', 'app_version', 'last_seen_at'];
    protected $hidden = ['token'];
    protected $casts = ['last_seen_at' => 'datetime'];

    public static function hashOf(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
