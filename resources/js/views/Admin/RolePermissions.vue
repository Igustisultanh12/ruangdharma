<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Role & Permission</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Kelola peran, checklist permission, dan penugasan peran ke pengguna. Catatan: backend baru mengenal peran admin/pengguna, jadi peran kustom disimpan sebagai override di perangkat ini sampai backend mendukungnya.</p>

    <div class="grid lg:grid-cols-5 gap-4">
      <div class="lg:col-span-3 space-y-4">
        <div class="card">
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-slate-900 dark:text-white">Daftar Role</h2>
            <button @click="openRoleForm(null)" class="btn-primary btn-sm">Tambah Role</button>
          </div>
          <div class="overflow-x-auto">
            <table class="table">
              <thead><tr><th>Role</th><th>Permission</th><th class="text-right">Aksi</th></tr></thead>
              <tbody>
                <tr v-for="r in roles" :key="r.id">
                  <td><p class="font-medium">{{ r.label }}</p><p class="text-xs text-slate-400">{{ r.name }}</p></td>
                  <td><span class="text-xs">{{ r.permissions.length }} permission</span></td>
                  <td class="text-right">
                    <div class="flex gap-1.5 justify-end">
                      <button @click="openRoleForm(r)" class="btn-secondary btn-sm">Edit</button>
                      <button @click="removeRole(r)" class="btn-danger btn-sm" :disabled="['admin', 'pengguna'].includes(r.name)">Hapus</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <h2 class="font-bold text-slate-900 dark:text-white mb-3">Assign Role ke Pengguna</h2>
          <div class="grid sm:grid-cols-3 gap-3">
            <select v-model="assign.userId" class="input"><option value="">Pilih pengguna</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option></select>
            <select v-model="assign.role" class="input"><option value="">Pilih role</option><option v-for="r in roles" :key="r.id" :value="r.name">{{ r.label }}</option></select>
            <button @click="assignRole" class="btn-primary" :disabled="!assign.userId || !assign.role">Terapkan</button>
          </div>
          <p class="text-xs text-slate-400 mt-2">Role admin/pengguna diteruskan ke API; role lain (mis. editor) disimpan sebagai override lokal.</p>
        </div>
      </div>

      <div class="lg:col-span-2">
        <div class="card">
          <h2 class="font-bold text-slate-900 dark:text-white mb-3">{{ editing ? 'Edit Role' : 'Role Baru' }}</h2>
          <div class="space-y-3">
            <div><label class="label">Nama role (slug)</label><input v-model="form.name" class="input" placeholder="misal: editor" /></div>
            <div><label class="label">Label</label><input v-model="form.label" class="input" placeholder="misal: Editor" /></div>
            <div>
              <label class="label">Permission</label>
              <div class="space-y-1.5 max-h-64 overflow-y-auto">
                <label v-for="p in ALL_PERMISSIONS" :key="p" class="flex items-center gap-2 text-sm cursor-pointer">
                  <input type="checkbox" :value="p" v-model="form.permissions" class="h-4 w-4 accent-violet-600" />
                  <span>{{ p }}</span>
                </label>
              </div>
            </div>
            <div class="flex gap-2">
              <button @click="saveRole" class="btn-primary">Simpan Role</button>
              <button @click="resetForm" class="btn-secondary">Atur Ulang</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import api from '../../services/api.js';
import { useAuthStore } from '../../stores/auth.js';
import { ALL_PERMISSIONS, getRoles, saveRoles, getUserRoleOverrides, saveUserRoleOverrides } from '../../services/preferences.js';
import { logActivity } from '../../services/activity.js';

const auth = useAuthStore();
const roles = ref(getRoles());
const users = ref([]);
const form = ref({ id: null, name: '', label: '', permissions: [] });
const editing = ref(false);
const assign = ref({ userId: '', role: '' });

function resetForm() {
  form.value = { id: null, name: '', label: '', permissions: [] };
  editing.value = false;
}
function openRoleForm(r) {
  if (!r) { resetForm(); return; }
  form.value = { id: r.id, name: r.name, label: r.label, permissions: [...r.permissions] };
  editing.value = true;
}
function saveRole() {
  if (!form.value.name.trim() || !form.value.label.trim()) {
    Swal.fire({ icon: 'error', title: 'Lengkapi nama & label role' });
    return;
  }
  if (form.value.id) {
    roles.value = roles.value.map((r) => (r.id === form.value.id ? { ...form.value } : r));
  } else {
    roles.value.push({ ...form.value, id: Date.now() });
  }
  saveRoles(roles.value);
  logActivity({ action: 'update', description: `Simpan role ${form.value.name}`, user: auth.user });
  Swal.fire({ icon: 'success', title: 'Role tersimpan', timer: 1200, showConfirmButton: false });
  resetForm();
}
async function removeRole(r) {
  const res = await Swal.fire({ title: 'Hapus role?', text: r.label, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  roles.value = roles.value.filter((x) => x.id !== r.id);
  saveRoles(roles.value);
}
async function assignRole() {
  const overrides = getUserRoleOverrides();
  overrides[assign.value.userId] = assign.value.role;
  saveUserRoleOverrides(overrides);
  // Teruskan ke API hanya untuk peran yang dikenali backend.
  if (['admin', 'pengguna'].includes(assign.value.role)) {
    try {
      await api.put(`/admin/users/${assign.value.userId}`, { role: assign.value.role });
    } catch { /* override lokal tetap tersimpan */ }
  }
  logActivity({ action: 'update', description: `Assign role ${assign.value.role} ke pengguna #${assign.value.userId}`, user: auth.user });
  Swal.fire({ icon: 'success', title: 'Role ditetapkan', timer: 1200, showConfirmButton: false });
  assign.value = { userId: '', role: '' };
}
onMounted(async () => {
  try {
    const { data } = await api.get('/admin/users');
    users.value = data || [];
  } catch { /* abaikan */ }
});
</script>
