<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'avatar', 'daily_limit', 'is_active',
        'default_durasi', 'default_model', 'permissions', 'quota_plan_id',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'daily_limit' => 'integer',
        'default_durasi' => 'integer',
        'permissions' => 'array',
    ];
    protected $appends = ['avatar_url'];

    // ---------- Relasi ----------
    public function videoGenerations(): HasMany { return $this->hasMany(VideoGeneration::class); }
    /** Alias lama — dipakai controller & withCount lama. */
    public function generations(): HasMany { return $this->videoGenerations(); }
    public function museJobs(): HasMany { return $this->hasMany(MuseJob::class); }
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class, 'role_user')->withTimestamps(); }
    public function quotaPlan(): BelongsTo { return $this->belongsTo(QuotaPlan::class); }
    public function apiKeys(): HasMany { return $this->hasMany(ApiKey::class); }
    public function webhooks(): HasMany { return $this->hasMany(Webhook::class); }
    public function notifications(): HasMany { return $this->hasMany(Notification::class); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }
    public function loginHistories(): HasMany { return $this->hasMany(LoginHistory::class); }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
    public function mediaAssets(): HasMany { return $this->hasMany(MediaAsset::class); }

    // ---------- Role & Permission ----------
    public function isAdmin(): bool { return in_array($this->role, ['admin', 'super_admin'], true) || $this->hasRole('admin') || $this->hasRole('super_admin'); }
    public function isSuperAdmin(): bool { return $this->role === 'super_admin' || $this->hasRole('super_admin'); }
    public function isPengguna(): bool { return $this->role === 'pengguna' || $this->hasRole('pengguna'); }
    public function hasRole(string $role): bool
    {
        if ($this->role === $role) return true;
        if (!$this->relationLoaded('roles')) { try { return $this->roles()->where('name', $role)->exists(); } catch (\Throwable) { return false; } }
        return $this->roles->contains('name', $role);
    }
    public function hasAnyRole(array $roles): bool { foreach ($roles as $r) if ($this->hasRole(trim($r))) return true; return false; }
    /** Cek permission: kolom permissions JSON (fallback) lalu role -> permissions. */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) return true;
        $direct = $this->permissions ?? [];
        if (in_array($permission, $direct, true) || in_array('*', $direct, true)) return true;
        try {
            $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->with('permissions')->get();
            foreach ($roles as $role) {
                $perms = $role->permissions ?? collect();
                if ($perms->contains('name', $permission) || $perms->contains('name', '*')) return true;
            }
        } catch (\Throwable) { /* tabel roles belum migrate -> fallback ke kolom JSON */ }
        return false;
    }
    public function assignRole(string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst(str_replace('_', ' ', $roleName))]);
        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->update(['role' => $roleName]);
    }
    public function syncRoles(array $roleNames): void
    {
        $ids = [];
        foreach ($roleNames as $name) {
            $ids[] = Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst(str_replace('_', ' ', $name))])->id;
        }
        $this->roles()->sync($ids);
        if (!empty($roleNames)) $this->update(['role' => $roleNames[0]]);
    }
    public function allPermissions(): array
    {
        $out = $this->permissions ?? [];
        try {
            foreach (($this->relationLoaded('roles') ? $this->roles : $this->roles()->with('permissions')->get()) as $role) {
                foreach (($role->permissions ?? []) as $p) $out[] = $p->name;
            }
        } catch (\Throwable) { }
        return array_values(array_unique($out));
    }

    // ---------- Kuota ----------
    public function effectiveDailyLimit(): int
    {
        if ($this->quotaPlan) return (int) $this->quotaPlan->daily_limit;
        if ($this->daily_limit !== null) return (int) $this->daily_limit;
        return (int) Setting::get('daily_limit', config('app.default_daily_limit', 5));
    }
    public function usedToday(): int { return $this->videoGenerations()->today()->count(); }
    public function remainingQuotaToday(): int { return max(0, $this->effectiveDailyLimit() - $this->usedToday()); }

    // ---------- Accessor ----------
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) return null;
        if (str_starts_with($this->avatar, 'http')) return $this->avatar;
        return asset(ltrim($this->avatar, '/'));
    }
}
