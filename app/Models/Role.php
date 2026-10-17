<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'description'];
    public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class, 'permission_role')->withTimestamps(); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'role_user')->withTimestamps(); }
    public function hasPermission(string $name): bool { return $this->permissions->contains('name', $name); }
}
