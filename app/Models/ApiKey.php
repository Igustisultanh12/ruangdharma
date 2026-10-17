<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    protected $fillable = ['user_id', 'name', 'key_hash', 'key_preview', 'last_used_at', 'is_active'];
    protected $hidden = ['key_hash'];
    protected $casts = ['last_used_at' => 'datetime', 'is_active' => 'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function touchUsage(): void { $this->update(['last_used_at' => now()]); }
}
