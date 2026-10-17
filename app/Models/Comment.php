<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = ['video_generation_id', 'user_id', 'parent_id', 'body'];
    public function generation(): BelongsTo { return $this->belongsTo(VideoGeneration::class, 'video_generation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parent(): BelongsTo { return $this->belongsTo(Comment::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(Comment::class, 'parent_id')->latest(); }
}
