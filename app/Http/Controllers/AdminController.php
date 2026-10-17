<?php
namespace App\Http\Controllers;
use App\Exports\VideoExport;
use App\Http\Requests\SettingUpdateRequest;
use App\Models\ActivityLog;
use App\Models\LoginHistory;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\ActivityLogger;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function stats()
    {
        $today = now()->toDateString();
        return response()->json(['success' => true,
            'today_generations' => VideoGeneration::whereDate('created_at', $today)->count(),
            'total_generations' => VideoGeneration::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_users' => User::count(),
            'by_model' => VideoGeneration::whereDate('created_at', $today)->select('model', DB::raw('count(*) as total'))->groupBy('model')->pluck('total', 'model'),
            'last_7_days' => $this->dailySeries(7),
            'review_pending' => VideoGeneration::where('workflow_status', 'review')->count(),
            'published_total' => VideoGeneration::where('workflow_status', 'published')->count(),
            'by_workflow' => VideoGeneration::select('workflow_status', DB::raw('count(*) as total'))->groupBy('workflow_status')->pluck('total', 'workflow_status'),
            'recent_generations' => VideoGeneration::with('user:id,name,email,avatar')->latest()->limit(6)->get(['id', 'user_id', 'keyword', 'durasi', 'model', 'workflow_status', 'created_at']),
            'recent_activities' => ActivityLog::with('user:id,name,avatar')->latest('id')->limit(8)->get(),
            'top_users_today' => User::withCount(['videoGenerations as today' => fn ($x) => $x->whereDate('created_at', $today)])->orderByDesc('today')->limit(5)->get(['id', 'name', 'email', 'avatar'])->filter(fn ($u) => $u->today > 0)->values(),
        ]);
    }

    /** Deret harian kontinu (tanggal kosong = 0) agar grafik tidak bolong. */
    protected function dailySeries(int $days): array
    {
        $rows = VideoGeneration::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('count(*) as total'))->groupBy(DB::raw('DATE(created_at)'))->pluck('total', 'd');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $out[] = ['date' => $date, 'total' => (int) ($rows[$date] ?? 0)];
        }
        return $out;
    }

    public function users(Request $request)
    {
        $q = User::withCount([
            'videoGenerations as today_generations' => fn($x) => $x->whereDate('created_at', now()->toDateString()),
            'videoGenerations as total_generations',
        ])->with(['quotaPlan:id,name,daily_limit', 'roles:id,name,display_name']);
        if ($request->filled('search')) $q->where(fn($w) => $w->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"));
        if ($request->filled('role')) $q->where('role', $request->input('role'));
        if ($request->filled('status')) $q->where('is_active', $request->input('status') === 'active');
        return response()->json(['success' => true, 'data' => $q->orderBy('created_at')->paginate((int) $request->input('per_page', 20))]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255', 'role' => 'sometimes|string|exists:roles,name',
            'daily_limit' => 'sometimes|nullable|integer|min:0|max:100', 'is_active' => 'sometimes|boolean',
            'quota_plan_id' => 'sometimes|nullable|integer|exists:quota_plans,id',
            'permissions' => 'sometimes|array', 'permissions.*' => 'string',
        ]);
        if ($request->user()->id === $user->id && ((isset($data['is_active']) && ! $data['is_active']) || (isset($data['role']) && ! in_array($data['role'], ['admin', 'super_admin'], true)))) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa menonaktifkan atau menurunkan role akun sendiri.'], 422);
        }
        $role = $data['role'] ?? null;
        unset($data['role']);
        $user->update($data);
        // Role utama + pivot role_user harus selalu sinkron. Dulu hanya kolom `role` yang
        // berubah, sehingga admin yang diturunkan tetap punya akses admin lewat pivot.
        if ($role) $user->syncRoles([$role]);
        if (array_key_exists('is_active', $data) && ! $data['is_active']) $user->tokens()->delete();
        ActivityLogger::fromRequest($request, 'user.update', "Admin memperbarui user: {$user->email}");
        return response()->json(['success' => true, 'user' => $user->fresh()->load('quotaPlan:id,name,daily_limit'), 'message' => 'Pengguna diperbarui.']);
    }
    /** Alias sesuai spesifikasi: updateUserLimit. */
    public function updateUserLimit(Request $request, $id)
    {
        $data = $request->validate(['daily_limit' => 'required|nullable|integer|min:0|max:100']);
        $user = User::findOrFail($id);
        $user->update(['daily_limit' => $data['daily_limit']]);
        return response()->json(['success' => true, 'user' => $user->fresh(), 'message' => 'Limit user diperbarui.']);
    }
    public function toggleUser(Request $request, $id) { return $this->toggleUserActive($request, $id); }
    public function toggleUserActive(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($request->user()->id === $user->id) return response()->json(['success' => false, 'message' => 'Tidak bisa menonaktifkan akun sendiri.'], 422);
        $user->update(['is_active' => !$user->is_active]);
        if (! $user->is_active) $user->tokens()->delete(); // paksa logout
        ActivityLogger::fromRequest($request, 'user.toggle', ($user->is_active ? 'Aktifkan' : 'Nonaktifkan') . " user: {$user->email}");
        return response()->json(['success' => true, 'user' => $user->fresh(), 'message' => $user->is_active ? 'User diaktifkan.' : 'User dinonaktifkan.']);
    }
    public function deleteUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($request->user()->id === $user->id) return response()->json(['success' => false, 'message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        $email = $user->email;
        $user->videoGenerations()->delete();
        $user->museJobs()->delete();
        $user->activityLogs()->delete();
        $user->loginHistories()->delete();
        $user->notifications()->delete();
        $user->comments()->delete();
        $user->apiKeys()->delete();
        $user->webhooks()->delete();
        $user->mediaAssets()->delete();
        $user->roles()->detach();
        $user->tokens()->delete();
        $user->delete();
        ActivityLogger::fromRequest($request, 'user.delete', "Admin menghapus user: {$email}");
        return response()->json(['success' => true, 'message' => 'Pengguna dan semua datanya berhasil dihapus.']);
    }

    public function allGenerations(Request $request) { return $this->historyAll($request); }
    public function historyAll(Request $request)
    {
        $q = VideoGeneration::with('user:id,name,email');
        $q = VideoGenerationController::applyFilters($q, $request);
        return response()->json(['success' => true, 'data' => $q->paginate((int) $request->input('per_page', 30))]);
    }

    public function exportCsv(Request $request)
    {
        $rows = VideoExport::query($request->only(['user_id', 'model', 'date_from', 'date_to', 'search']));
        return response(VideoExport::toCsv($rows), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="laporan-video-' . now()->format('Ymd-His') . '.csv"']);
    }
    public function exportPdf(Request $request)
    {
        $rows = VideoExport::query($request->only(['user_id', 'model', 'date_from', 'date_to', 'search']));
        return response(VideoExport::toHtml($rows), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function activityLogs(Request $request)
    {
        $q = ActivityLog::with('user:id,name,email')->latest();
        if ($request->filled('user_id')) $q->where('user_id', $request->input('user_id'));
        if ($request->filled('action')) $q->where('action', $request->input('action'));
        if ($request->filled('search')) $q->where('description', 'like', "%{$request->search}%");
        return response()->json(['success' => true, 'data' => $q->paginate((int) $request->input('per_page', 30))]);
    }
    public function loginHistories(Request $request)
    {
        $q = LoginHistory::with('user:id,name,email')->latest();
        if ($request->filled('email')) $q->where('email', 'like', "%{$request->email}%");
        if ($request->has('success')) $q->where('success', filter_var($request->input('success'), FILTER_VALIDATE_BOOLEAN));
        return response()->json(['success' => true, 'data' => $q->paginate((int) $request->input('per_page', 30))]);
    }
    public function analytics(Request $request, AnalyticsService $analytics)
    {
        return response()->json(['success' => true, 'data' => $analytics->summary(max(1, min(365, (int) $request->input('days', 30))))]);
    }
    public function analyticsExportCsv(Request $request, AnalyticsService $analytics)
    {
        $rows = VideoExport::query($request->only(['user_id', 'model', 'date_from', 'date_to', 'search']));
        return response(VideoExport::toCsv($rows), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="analytics-video-' . now()->format('Ymd-His') . '.csv"']);
    }
}
