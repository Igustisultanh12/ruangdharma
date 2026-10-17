<?php
namespace App\Http\Controllers;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->loadMissing(['roles', 'quotaPlan']);
        return response()->json(['success' => true, 'user' => AuthController::userPayload($user)]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        if ($request->hasFile('avatar')) {
            if ($user->avatar) { // hapus avatar lama
                $old = ltrim(str_replace('/storage/', '', $user->avatar), '/');
                if (Storage::disk('public')->exists($old)) Storage::disk('public')->delete($old);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = '/storage/' . $path;
        }
        // Password di-update lewat field ini juga (dengan current_password) atau via updatePassword.
        if (!empty($data['password'])) {
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $user->password)) {
                return response()->json(['success' => false, 'message' => 'Password lama salah.'], 422);
            }
            $data['password'] = Hash::make($data['password']);
        } else { unset($data['password']); }
        unset($data['current_password'], $data['password_confirmation']);
        if (array_key_exists('avatar', $data) && $data['avatar'] === null && ! $request->hasFile('avatar')) unset($data['avatar']);
        if ($request->boolean('remove_avatar') && $user->avatar) {
            $old = ltrim(str_replace('/storage/', '', $user->avatar), '/');
            if (Storage::disk('public')->exists($old)) Storage::disk('public')->delete($old);
            $data['avatar'] = null;
        }
        $user->update($data);
        \App\Services\ActivityLogger::fromRequest($request, 'profile.update', 'Memperbarui profil');
        return response()->json(['success' => true, 'user' => AuthController::userPayload($user->fresh()->load(['roles', 'quotaPlan'])), 'message' => 'Profil berhasil diperbarui.']);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:' . \App\Models\Setting::getInt('password_min_length', 6) . '|confirmed',
        ], ['current_password.required' => 'Password lama wajib diisi.', 'password.min' => 'Password baru minimal :min karakter.', 'password.confirmed' => 'Konfirmasi password tidak cocok.']);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) return response()->json(['success' => false, 'message' => 'Password lama salah.'], 422);
        $user->update(['password' => Hash::make($data['password'])]);
        // Cabut sesi lain, pertahankan token yang sedang dipakai.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current && isset($current->id), fn ($q) => $q->where('id', '!=', $current->id))->delete();
        \App\Services\ActivityLogger::fromRequest($request, 'profile.password', 'Mengubah password');
        return response()->json(['success' => true, 'message' => 'Password berhasil diubah.']);
    }
}
