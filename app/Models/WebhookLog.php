<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['webhook_id', 'event', 'payload', 'status_code', 'response'];
    protected $casts = ['payload' => 'array', 'status_code' => 'integer', 'created_at' => 'datetime'];
    public function webhook(): BelongsTo { return $this->belongsTo(Webhook::class); }
}
