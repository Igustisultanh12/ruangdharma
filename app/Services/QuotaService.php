<?php
namespace App\Services;
use App\Models\Setting;
use App\Models\VideoGeneration;
use App\Models\User;
use Carbon\Carbon;
class QuotaService {
    public function globalLimit(): int { return (int) Setting::getValue('daily_limit', config('app.default_daily_limit', 5)); }
    public function blockedKeywords(): array {
        $raw = Setting::getValue('blocked_keywords', '[]');
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }
    public function isBlocked(string $keyword): ?string {
        $k = mb_strtolower($keyword);
        foreach ($this->blockedKeywords() as $b) {
            if ($b !== '' && str_contains($k, mb_strtolower(trim($b)))) return trim($b);
        }
        return null;
    }
    public function userLimit(User $user): int { return $user->daily_limit ?? $this->globalLimit(); }
    public function usedToday(User $user): int {
        return VideoGeneration::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();
    }
    public function remaining(User $user): int { return max(0, $this->userLimit($user) - $this->usedToday($user)); }
    public function check(User $user, string $keyword): array {
        if (!$user->is_active) return ['ok'=>false,'code'=>403,'message'=>'Akun dinonaktifkan oleh admin.'];
        if ($blocked = $this->isBlocked($keyword)) return ['ok'=>false,'code'=>422,'message'=>"Kata kunci '{$blocked}' diblokir oleh admin. Silakan pakai kata kunci lain."];
        if ($this->remaining($user) <= 0) return ['ok'=>false,'code'=>429,'message'=>'Limit harian tercapai ('.$this->userLimit($user).' video/hari). Coba lagi besok.'];
        return ['ok'=>true];
    }
}
