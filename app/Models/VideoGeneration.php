<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoGeneration extends Model
{
    use HasFactory;

    public const MODELS = ['narasi', 'ngobrol', 'gabungan'];
    public const WORKFLOW_STATUSES = ['draft', 'review', 'revisi', 'approved', 'published', 'archived'];

    protected $fillable = [
        'user_id', 'keyword', 'durasi', 'model', 'naskah', 'caption', 'prompts',
        'status', 'source', 'muse_job_id', 'video_url', 'video_files',
        'is_favorite', 'is_archived',
        'workflow_status', 'review_note', 'reviewed_by', 'reviewed_at', 'publish_at',
        'backsound_url', 'backsound_volume', 'backsound_trim_start', 'backsound_trim_end', 'has_subtitle', 'has_watermark',
    ];
    protected $casts = [
        'naskah' => 'array', 'prompts' => 'array', 'durasi' => 'integer',
        'is_favorite' => 'boolean', 'is_archived' => 'boolean', 'video_files' => 'array',
        'reviewed_at' => 'datetime', 'publish_at' => 'datetime',
        'backsound_trim_start' => 'float', 'backsound_trim_end' => 'float',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function museJob(): BelongsTo { return $this->belongsTo(MuseJob::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function versions(): HasMany { return $this->hasMany(VideoGenerationVersion::class)->orderByDesc('version_number'); }
    public function comments(): HasMany { return $this->hasMany(Comment::class)->whereNull('parent_id')->latest(); }

    /** Nama profil Muse yang mengerjakan video ini (via MuseJob). Tidak di-$appends agar tidak N+1 — controller yang membutuhkannya memanggil ->append('muse_profile_name') setelah eager-load museJob.museProfile. */
    public function getMuseProfileNameAttribute(): ?string { return $this->museJob?->museProfile?->name; }

    public function scopeToday(Builder $q): Builder { return $q->whereDate('created_at', now()->toDateString()); }
    public function scopeByModel(Builder $q, string $model): Builder { return $q->where('model', $model); }
    public function scopeFavorites(Builder $q): Builder { return $q->where('is_favorite', true); }
    public function scopeNotArchived(Builder $q): Builder { return $q->where('is_archived', false); }
    public function scopeArchived(Builder $q): Builder { return $q->where('is_archived', true); }
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        return $q->where(fn($w) => $w->where('keyword', 'like', "%{$term}%")->orWhere('caption', 'like', "%{$term}%"));
    }
    public function scopeByMonth(Builder $q, string $month): Builder // YYYY-MM, cocok ke publish_at ATAU created_at
    {
        return $q->where(fn($w) => $w->where('publish_at', 'like', "{$month}%")->orWhere('created_at', 'like', "{$month}%"));
    }

    public function isPublished(): bool { return $this->workflow_status === 'published'; }
    public function captionText(): string { return is_array($this->caption) ? ($this->caption['text'] ?? '') : (string) ($this->caption ?? ''); }
}
