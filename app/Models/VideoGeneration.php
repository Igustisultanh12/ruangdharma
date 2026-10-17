<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class VideoGeneration extends Model {
    use HasFactory;
    protected $fillable = ['user_id','keyword','durasi','model','naskah','caption','prompts','status','source','muse_job_id'];
    protected $casts = ['naskah'=>'array','prompts'=>'array','durasi'=>'integer'];
    public function user() { return $this->belongsTo(User::class); }
    public function museJob() { return $this->belongsTo(MuseJob::class); }
}
