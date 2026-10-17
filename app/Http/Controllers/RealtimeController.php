<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\LoginHistory;
use App\Models\MediaAsset;
use App\Models\MuseJob;
use App\Models\QuotaPlan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Models\WebhookLog;
use App\Services\QuotaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Realtime tanpa WebSocket: frontend memanggil GET /api/realtime/pulse tiap beberapa detik.
 * Respons berisi "stamp" (sidik jari) per resource. Bila stamp berubah, halaman yang
 * memakai resource itu memuat ulang datanya otomatis — tanpa refresh browser.
 * Cocok untuk shared hosting / aaPanel karena tidak butuh server Reverb/Pusher.
 */
class RealtimeController extends Controller
{
    public function pulse(Request $request, QuotaService $quota)
    {
        $user = $request->user();
        $isReviewer = $user->hasAnyRole(VideoGenerationController::REVIEWER_ROLES);
        $isAdmin = $user->isAdmin();

        $stamps = [
            'me' => md5(implode('|', [$user->updated_at, $user->role, (int) $user->is_active, $user->quota_plan_id, $user->roles()->pluck('name')->join(','), QuotaPlan::max('updated_at'), Setting::where('key', 'daily_limit')->value('updated_at')])),
            'generations' => $this->stamp($user->videoGenerations()),
            'notifications' => $this->stamp($user->notifications(), 'id', 'is_read'),
            'comments' => $this->stamp(Comment::query()),
            'media' => $this->stamp(MediaAsset::query()),
            'api_keys' => $this->stamp($user->apiKeys()),
            'webhooks' => md5($this->stamp($user->webhooks()).WebhookLog::whereIn('webhook_id', $user->webhooks()->select('id'))->max('id')),
            'settings' => $this->stamp(Setting::query()),
        ];
        if ($isReviewer) {
            $stamps['review'] = $this->stamp(VideoGeneration::query());
        }
        if ($isAdmin) {
            $stamps['all_generations'] = $stamps['review'] ?? $this->stamp(VideoGeneration::query());
            $stamps['users'] = $this->stamp(User::query());
            $stamps['activity'] = $this->stamp(ActivityLog::query(), 'id');
            $stamps['logins'] = $this->stamp(LoginHistory::query(), 'id');
            $stamps['plans'] = $this->stamp(QuotaPlan::query());
            $stamps['roles'] = md5($this->stamp(Role::query()).DB::table('permission_role')->count().'|'.DB::table('role_user')->count());
            $stamps['muse'] = $this->stamp(MuseJob::query());
        }

        $latest = $user->notifications()->where('is_read', false)->latest('id')->first(['id', 'title', 'message', 'created_at']);

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'active' => (bool) $user->is_active,
            'stamps' => $stamps,
            'unread_notifications' => $user->notifications()->where('is_read', false)->count(),
            'latest_notification' => $latest,
            'quota' => ['limit' => $quota->userLimit($user), 'used' => $quota->usedToday($user), 'remaining' => $quota->remaining($user)],
            'session_timeout' => Setting::getInt('session_timeout', 120),
            'review_pending' => $isReviewer ? VideoGeneration::where('workflow_status', 'review')->count() : null,
        ]);
    }

    /** Sidik jari murah: jumlah baris + id terbesar + waktu ubah terakhir (+ kolom opsional). */
    protected function stamp(Builder|Relation $q, string $timeColumn = 'updated_at', ?string $sumColumn = null): string
    {
        $parts = [(clone $q)->count(), (clone $q)->max('id'), (clone $q)->max($timeColumn)];
        if ($sumColumn) $parts[] = (clone $q)->where($sumColumn, true)->count();

        return md5(implode('|', array_map(fn ($v) => (string) $v, $parts)));
    }
}
