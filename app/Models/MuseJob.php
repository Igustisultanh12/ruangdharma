<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class MuseJob extends Model {
    use HasFactory;
    protected $fillable = ['user_id','keyword','durasi','model','status','result','error','callback_url','source','completed_at'];
    protected $casts = ['result'=>'array','durasi'=>'integer','completed_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function generation() { return $this->hasOne(VideoGeneration::class); }
}
