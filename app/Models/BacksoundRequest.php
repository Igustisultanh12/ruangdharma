<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BacksoundRequest extends Model
{
    protected $fillable = ['user_id', 'generation_id', 'status', 'note', 'candidate_name', 'candidate_url', 'candidate_source', 'candidate_duration_sec', 'error'];
    protected $casts = ['candidate_duration_sec' => 'float'];
    /** Status yang masih "terbuka" (belum selesai/dibatalkan). */
    public const OPEN_STATUSES = ['queued', 'searching', 'preview', 'rejected'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function generation(): BelongsTo { return $this->belongsTo(VideoGeneration::class, 'generation_id'); }
    public function isOpen(): bool { return in_array($this->status, self::OPEN_STATUSES, true); }
}
