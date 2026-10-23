<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MuseJob extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'keyword', 'durasi', 'model', 'voice_source', 'status', 'result', 'error', 'callback_url', 'source', 'completed_at', 'muse_profile_id'];
    protected $casts = ['result' => 'array', 'durasi' => 'integer', 'completed_at' => 'datetime', 'muse_profile_id' => 'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function generation(): HasOne { return $this->hasOne(VideoGeneration::class); }
    public function museProfile(): BelongsTo { return $this->belongsTo(MuseProfile::class); }

    /** Nama profil Muse yang mengerjakan job ini (null = job lama / token lama). */
    public function getMuseProfileNameAttribute(): ?string { return $this->museProfile?->name; }

    public function markProcessing(): static { $this->update(['status' => 'processing']); return $this->fresh(); }
    public function markCompleted(array $result = []): static
    {
        $this->update(['status' => 'completed', 'result' => $result, 'error' => null, 'completed_at' => now()]);
        return $this->fresh();
    }
    public function markFailed(string $error): static
    {
        $this->update(['status' => 'failed', 'error' => $error, 'completed_at' => now()]);
        return $this->fresh();
    }
    public function isCompleted(): bool { return $this->status === 'completed'; }
}
