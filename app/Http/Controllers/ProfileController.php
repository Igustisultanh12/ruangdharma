<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
class ProfileController extends Controller {
    public function show(Request $request) { return response()->json($request->user()); }
    public function update(Request $request) {
        $user = $request->user();
        $data = $request->validate(['name'=>'sometimes|string|max:255','email'=>'sometimes|email|unique:users,email,'.$user->id,'avatar'=>'sometimes|nullable|image|max:2048']);
        if ($request->hasFile('avatar')) { $path = $request->file('avatar')->store('avatars','public'); $data['avatar'] = '/storage/'.$path; }
        $user->update($data);
        return response()->json(['user'=>$user->fresh(),'message'=>'Profil berhasil diperbarui.']);
    }
    public function updatePassword(Request $request) {
        $data = $request->validate(['current_password'=>'required|string','password'=>'required|string|min:6|confirmed']);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) return response()->json(['message'=>'Password lama salah.'], 422);
        $user->update(['password'=>Hash::make($data['password'])]);
        return response()->json(['message'=>'Password berhasil diubah.']);
    }
}
