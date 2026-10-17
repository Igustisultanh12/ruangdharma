<?php
namespace App\Http\Controllers;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index() { return response()->json(['success' => true, 'data' => Role::with('permissions')->get()]); }
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:50|unique:roles,name', 'display_name' => 'nullable|string|max:100', 'description' => 'nullable|string|max:500', 'permissions' => 'sometimes|array', 'permissions.*' => 'string|exists:permissions,name']);
        $role = Role::create(['name' => $data['name'], 'display_name' => $data['display_name'] ?? ucfirst($data['name']), 'description' => $data['description'] ?? null]);
        if (!empty($data['permissions'])) $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));
        return response()->json(['success' => true, 'message' => 'Role dibuat.', 'data' => $role->load('permissions')], 201);
    }
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate(['display_name' => 'sometimes|string|max:100', 'description' => 'sometimes|nullable|string|max:500', 'permissions' => 'sometimes|array', 'permissions.*' => 'string|exists:permissions,name']);
        $role->update($data);
        if (isset($data['permissions'])) $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));
        return response()->json(['success' => true, 'message' => 'Role diperbarui.', 'data' => $role->load('permissions')]);
    }
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        if (in_array($role->name, ['super_admin', 'admin', 'pengguna'], true)) return response()->json(['success' => false, 'message' => 'Role bawaan tidak bisa dihapus.'], 422);
        $role->permissions()->detach(); $role->users()->detach(); $role->delete();
        return response()->json(['success' => true, 'message' => 'Role dihapus.']);
    }
    /** Assign permission ke role. */
    public function assignPermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate(['permissions' => 'required|array', 'permissions.*' => 'string|exists:permissions,name']);
        $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));
        return response()->json(['success' => true, 'message' => 'Permission role diperbarui.', 'data' => $role->load('permissions')]);
    }
    /** Assign role ke user (mengganti role utama + pivot). */
    public function assignRoleToUser(Request $request, $userId)
    {
        $data = $request->validate(['roles' => 'required|array|min:1', 'roles.*' => 'string|exists:roles,name']);
        $user = User::findOrFail($userId);
        $user->syncRoles($data['roles']);
        return response()->json(['success' => true, 'message' => 'Role user diperbarui.', 'user' => AuthController::userPayload($user->fresh())]);
    }
    public function permissions() { return response()->json(['success' => true, 'data' => Permission::orderBy('group')->orderBy('name')->get()]); }
}
