<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Backsound extends Model
{
    protected $fillable = ['user_id','name','file_url','duration_sec'];
    protected $casts = ['duration_sec' => 'float'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
