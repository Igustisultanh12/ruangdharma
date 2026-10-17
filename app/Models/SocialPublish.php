<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SocialPublish extends Model
{
    protected $fillable = ['user_id','generation_id','platform','status','caption','video_url','post_url','post_id','error','published_at','requested_at'];
    protected $casts = ['published_at' => 'datetime', 'requested_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function generation(): BelongsTo { return $this->belongsTo(VideoGeneration::class); }
    public function markProcessing(): static { $this->update(['status' => 'processing']); return $this->fresh(); }
    public function markCompleted(?string $postUrl = null, ?string $postId = null): static { $this->update(['status' => 'completed', 'post_url' => $postUrl, 'post_id' => $postId, 'error' => null, 'published_at' => now()]); return $this->fresh(); }
    public function markFailed(string $error): static { $this->update(['status' => 'failed', 'error' => $error]); return $this->fresh(); }
}
