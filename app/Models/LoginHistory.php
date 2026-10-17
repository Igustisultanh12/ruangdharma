<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'email', 'ip', 'user_agent', 'success'];
    protected $casts = ['success' => 'boolean', 'created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
