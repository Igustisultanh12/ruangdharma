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
            'last_7_days' => VideoGeneration::where('created_at', '>=', now()->subDays(6)->startOfDay())->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))->groupBy('date')->orderBy('date')->get(),
        ]);
    }

    public function users(Request $request)
    {
        $q = User::withCount(['videoGenerations as today_generations' => fn($x) => $x->whereDate('created_at', now()->toDateString())])->with(['quotaPlan:id,name,daily_limit']);
        if ($request->filled('search')) $q->where(fn($w) => $w->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"));
        if ($request->filled('role')) $q->where('role', $request->input('role'));
        return response()->json(['success' => true, 'data' => $q->orderBy('created_at')->paginate((int) $request->input('per_page', 20))]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255', 'role' => 'sometimes|in:super_admin,admin,editor,pengguna,viewer',
            'daily_limit' => 'sometimes|nullable|integer|min:0|max:100', 'is_active' => 'sometimes|boolean',
            'quota_plan_id' => 'sometimes|nullable|integer|exists:quota_plans,id',
            'permissions' => 'sometimes|array', 'permissions.*' => 'string',
        ]);
        $user->update($data);
        ActivityLogger::fromRequest($request, 'user.update', "Admin memperbarui user: {$user->email}");
        return response()->json(['success' => true, 'user' => $user->fresh(), 'message' => 'Pengguna diperbarui.']);
    }
    /** Alias sesuai spesifikasi: updateUserLimit. */
    public function updateUserLimit(Request $request, $id)
    {
        $data = $request->validate(['daily_limit' => 'required|nullable|integer|min:0|max:100']);
        $user = User::findOrFail($id);
        $user->update(['daily_limit' => $data['daily_limit']]);
        return response()->json(['success' => true, 'user' => $user->fresh(), 'message' => 'Limit user diperbarui.']);
    }
    public function toggleUser($id) { return $this->toggleUserActive($id); }
    public function toggleUserActive($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
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
        return response()->json(['success' => true, 'data' => $analytics->summary((int) $request->input('days', 30))]);
    }
    public function analyticsExportCsv(Request $request, AnalyticsService $analytics)
    {
        $rows = VideoExport::query($request->only(['user_id', 'model', 'date_from', 'date_to', 'search']));
        return response(VideoExport::toCsv($rows), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="analytics-video-' . now()->format('Ymd-His') . '.csv"']);
    }
}
