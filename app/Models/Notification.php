<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notifikasi in-app sederhana (tabel `notifications` milik aplikasi, bukan Laravel database notifications). */
class Notification extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'title', 'message', 'is_read'];
    protected $casts = ['is_read' => 'boolean', 'created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function markAsRead(): static { $this->update(['is_read' => true]); return $this->fresh(); }
}
