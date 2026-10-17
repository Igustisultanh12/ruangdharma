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
        $user->update($data);
        return response()->json(['success' => true, 'user' => AuthController::userPayload($user->fresh()), 'message' => 'Profil berhasil diperbarui.']);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], ['current_password.required' => 'Password lama wajib diisi.', 'password.min' => 'Password baru minimal 6 karakter.', 'password.confirmed' => 'Konfirmasi password tidak cocok.']);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) return response()->json(['success' => false, 'message' => 'Password lama salah.'], 422);
        $user->update(['password' => Hash::make($data['password'])]);
        return response()->json(['success' => true, 'message' => 'Password berhasil diubah.']);
    }
}
