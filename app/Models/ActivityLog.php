<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /** Hanya punya kolom created_at: biarkan Eloquent mengisinya otomatis (dulu selalu NULL karena tidak fillable). */
    public const UPDATED_AT = null;
    protected $fillable = ['user_id', 'action', 'description', 'ip', 'meta'];
    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
