<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotaPlan extends Model
{
    protected $fillable = ['name', 'daily_limit', 'price', 'description', 'is_active'];
    protected $casts = ['daily_limit' => 'integer', 'price' => 'integer', 'is_active' => 'boolean'];
    public function users(): HasMany { return $this->hasMany(User::class); }
}
