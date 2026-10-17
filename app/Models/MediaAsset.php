<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    protected $fillable = ['title', 'file_path', 'type', 'tags', 'user_id', 'mime', 'size'];
    protected $casts = ['tags' => 'array', 'size' => 'integer'];
    protected $appends = ['url'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getUrlAttribute(): ?string { return $this->file_path ? asset(ltrim($this->file_path, '/')) : null; }
    public function scopeSearchByTag($q, ?string $tag)
    {
        if (!$tag) return $q;
        return $q->where('tags', 'like', "%{$tag}%")->orWhere('title', 'like', "%{$tag}%");
    }
}
