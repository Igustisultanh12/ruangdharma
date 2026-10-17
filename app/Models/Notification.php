<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notifikasi in-app sederhana (tabel `notifications` milik aplikasi, bukan Laravel database notifications). */
class Notification extends Model
{
    /** Hanya punya kolom created_at: biarkan Eloquent mengisinya otomatis (dulu selalu NULL karena tidak fillable). */
    public const UPDATED_AT = null;
    protected $fillable = ['user_id', 'title', 'message', 'is_read'];
    protected $casts = ['is_read' => 'boolean', 'created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Setiap notifikasi baru juga dikirim sebagai push ke HP pemiliknya (bila FCM sudah diatur admin). */
    protected static function booted(): void
    {
        static::created(fn (self $n) => \App\Support\PushNotifier::notification($n));
    }
    public function markAsRead(): static { $this->update(['is_read' => true]); return $this->fresh(); }
}
