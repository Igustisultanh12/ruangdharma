<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = ['name','email','password','role','avatar','daily_limit','is_active'];
    protected $hidden = ['password','remember_token'];
    protected $casts = ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean','daily_limit'=>'integer'];
    public function generations() { return $this->hasMany(VideoGeneration::class); }
    public function museJobs() { return $this->hasMany(MuseJob::class); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
}
