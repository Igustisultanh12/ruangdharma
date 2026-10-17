<?php
namespace App\Http\Controllers;
use App\Http\Requests\RegisterUserRequest;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected function throttleKey(Request $request): string
    {
        return 'login:' . mb_strtolower((string) $request->input('email')) . '|' . $request->ip();
    }

    public function login(Request $request)
    {
        // Blokir IP sementara: 5x gagal -> blokir 5 menit (Cache).
        $blockKey = 'login_blocked:' . $request->ip();
        if (Cache::has($blockKey)) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak percobaan gagal dari IP ini. Coba lagi dalam 5 menit.'], 429);
        }
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string',
        ], ['email.required' => 'Email wajib diisi.', 'email.email' => 'Format email tidak valid.', 'password.required' => 'Password wajib diisi.']);

        $key = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json(['success' => false, 'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik."], 429);
        }

        $user = User::where('email', $data['email'])->first();
        $ok = $user && Hash::check($data['password'], $user->password);
        LoginHistory::create(['user_id' => $user?->id, 'email' => $data['email'], 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'success' => $ok, 'created_at' => now()]);

        if (!$ok) {
            RateLimiter::hit($key, 60);
            $fails = Cache::increment('login_fails_ip:' . $request->ip());
            if ($fails === 1) Cache::put('login_fails_ip:' . $request->ip(), 1, 300);
            if ($fails >= 5) { Cache::put($blockKey, true, 300); Cache::forget('login_fails_ip:' . $request->ip()); }
            throw ValidationException::withMessages(['email' => ['Email atau password salah.']]);
        }
        if (!$user->is_active) return response()->json(['success' => false, 'message' => 'Akun dinonaktifkan. Hubungi admin.'], 403);
        $user->loadMissing(['roles', 'quotaPlan']);

        RateLimiter::clear($key);
        Cache::forget('login_fails_ip:' . $request->ip());
        $token = $user->createToken('web')->plainTextToken;
        $payload = $this->userPayload($user);
        ActivityLogger::log($user, 'login', "Login berhasil: {$user->email}", $request);
        // "data" ditambahkan untuk klien yang mengharapkan wrapper (mis. app Android lama),
        // TANPA menghapus field token/user level atas yang dipakai web.
        return response()->json(['success' => true, 'message' => 'Login berhasil.', 'user' => $payload, 'token' => $token, 'data' => ['user' => $payload, 'token' => $token]]);
    }

    /** Hanya admin yang bisa membuat user (route dilindungi auth + role:admin). */
    public function register(RegisterUserRequest $request)
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'pengguna', 'is_active' => true,
            'daily_limit' => $data['daily_limit'] ?? null, 'quota_plan_id' => $data['quota_plan_id'] ?? null,
        ]);
        if (!empty($data['role'])) { try { $user->assignRole($data['role']); } catch (\Throwable) { } }
        ActivityLogger::fromRequest($request, 'user.create', "Admin membuat user baru: {$user->email} (role {$user->role})");
        NotificationService::notify($user, 'Akun dibuat', 'Selamat datang di Ruang Dharma Studio! Akun Anda sudah aktif.');
        return response()->json(['success' => true, 'message' => 'User berhasil dibuat.', 'user' => $this->userPayload($user)], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()?->delete();
        if ($user) ActivityLogger::log($user, 'logout', "Logout: {$user->email}", $request);
        return response()->json(['success' => true, 'message' => 'Berhasil logout.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->loadMissing(['roles', 'quotaPlan']);
        return response()->json(['success' => true, 'user' => $this->userPayload($user)]);
    }

    public static function userPayload(User $user): array
    {
        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role,
            'avatar' => $user->avatar, 'avatar_url' => $user->avatar_url, 'is_active' => (bool) $user->is_active,
            'daily_limit' => $user->daily_limit, 'default_durasi' => $user->default_durasi, 'default_model' => $user->default_model,
            'roles' => $user->roles?->pluck('name')->all() ?? [$user->role],
            'permissions' => $user->allPermissions(),
            'quota_plan' => $user->quotaPlan ? ['id' => $user->quotaPlan->id, 'name' => $user->quotaPlan->name, 'daily_limit' => $user->quotaPlan->daily_limit] : null,
            'quota' => ['limit' => $user->effectiveDailyLimit(), 'used_today' => $user->usedToday(), 'remaining' => $user->remainingQuotaToday()],
            'is_admin' => $user->isAdmin(),
            'is_reviewer' => $user->hasAnyRole(VideoGenerationController::REVIEWER_ROLES),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
