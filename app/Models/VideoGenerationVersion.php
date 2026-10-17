<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoGenerationVersion extends Model
{
    public $timestamps = false;
    protected $fillable = ['video_generation_id', 'version_number', 'naskah', 'caption', 'edited_by'];
    protected $casts = ['naskah' => 'array', 'created_at' => 'datetime'];
    public function generation(): BelongsTo { return $this->belongsTo(VideoGeneration::class, 'video_generation_id'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'edited_by'); }
}
