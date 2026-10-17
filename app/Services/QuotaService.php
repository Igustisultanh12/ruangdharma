<?php
namespace App\Services;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoGeneration;
use Carbon\Carbon;

class QuotaService
{
    public function globalLimit(): int { return (int) Setting::get('daily_limit', config('app.default_daily_limit', 5)); }
    public function blockedKeywords(): array { return Setting::getArray('blocked_keywords', []); }
    public function isBlocked(string $keyword): ?string
    {
        $k = mb_strtolower($keyword);
        foreach ($this->blockedKeywords() as $b) {
            $b = trim((string) $b);
            if ($b !== '' && str_contains($k, mb_strtolower($b))) return $b;
        }
        return null;
    }
    public function userLimit(User $user): int { return $user->effectiveDailyLimit(); }
    public function usedToday(User $user): int { return VideoGeneration::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count(); }
    public function remaining(User $user): int { return max(0, $this->userLimit($user) - $this->usedToday($user)); }

    /**
     * Bentuk kanonis: [allowed, reason, remaining].
     * Kunci [ok, code, message] dipertahankan untuk controller lama.
     */
    public function check(User $user, string $keyword): array
    {
        if (!$user->is_active) return ['allowed' => false, 'ok' => false, 'code' => 403, 'reason' => 'Akun dinonaktifkan oleh admin.', 'message' => 'Akun dinonaktifkan oleh admin.', 'remaining' => 0];
        if ($blocked = $this->isBlocked($keyword)) {
            $msg = "Kata kunci '{$blocked}' diblokir oleh admin. Silakan pakai kata kunci lain.";
            return ['allowed' => false, 'ok' => false, 'code' => 422, 'reason' => $msg, 'message' => $msg, 'remaining' => $this->remaining($user)];
        }
        $remaining = $this->remaining($user);
        if ($remaining <= 0) {
            $msg = 'Limit harian tercapai (' . $this->userLimit($user) . ' video/hari). Coba lagi besok.';
            return ['allowed' => false, 'ok' => false, 'code' => 429, 'reason' => $msg, 'message' => $msg, 'remaining' => 0];
        }
        return ['allowed' => true, 'ok' => true, 'code' => 200, 'reason' => null, 'message' => null, 'remaining' => $remaining];
    }
}
