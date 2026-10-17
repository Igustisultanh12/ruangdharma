<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    protected $fillable = ['user_id', 'url', 'events', 'is_active'];
    protected $casts = ['events' => 'array', 'is_active' => 'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function logs(): HasMany { return $this->hasMany(WebhookLog::class); }
    public function listensTo(string $event): bool { return in_array($event, $this->events ?? [], true) || in_array('*', $this->events ?? [], true); }
}
