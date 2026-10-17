<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Daftar Pengguna</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Kelola peran, limit, paket, dan status pengguna.</p>
    <div class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Pengguna</th><th>Peran</th><th>Paket</th><th>Pemakaian / Limit</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td><p class="font-medium">{{ u.name }}</p><p class="text-xs text-slate-400">{{ u.email }}</p></td>
              <td>
                <select :value="effectiveRole(u)" @change="upd(u, { role: $event.target.value })" class="input input-sm !w-36">
                  <option value="admin">Admin</option>
                  <option value="pengguna">Pengguna</option>
                </select>
              </td>
              <td><Badge variant="info">{{ userPlanName(u.id) }}</Badge></td>
              <td>
                <div class="flex items-center gap-2">
                  <input type="number" min="0" :value="u.daily_limit" @change="upd(u, { daily_limit: $event.target.value ? parseInt($event.target.value) : null })" class="input input-sm !w-20" placeholder="Global" />
                  <span class="text-xs text-slate-500">hari ini: {{ u.today_generations ?? 0 }}</span>
                </div>
              </td>
              <td>
                <button @click="toggle(u)" role="switch" :aria-checked="!!u.is_active" class="relative inline-flex h-6 w-11 items-center rounded-full transition" :class="u.is_active ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'" :title="u.is_active ? 'Nonaktifkan' : 'Aktifkan'">
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" :class="u.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
                </button>
                <span class="ml-2 text-xs">{{ u.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="text-right"><button @click="removeUser(u)" class="btn-danger btn-sm">Hapus</button></td>
            </tr>
            <tr v-if="!users.length"><td colspan="6"><EmptyState title="Belum ada pengguna" /></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <p class="text-xs text-slate-400 mt-2">Peran kustom (mis. Editor) dari modul Role &amp; Permission disimpan sebagai override di perangkat ini sampai backend mendukungnya.</p>
  </div>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import api from '../../services/api.js';
import { useAuthStore } from '../../stores/auth.js';
import { getUserRoleOverrides, getUserPlans, getQuotaPlans } from '../../services/preferences.js';
import { logActivity } from '../../services/activity.js';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';

const auth = useAuthStore();
const users = ref([]);

function effectiveRole(u) {
  return getUserRoleOverrides()[u.id] || u.role;
}
function userPlanName(userId) {
  const planId = getUserPlans()[userId];
  if (!planId) return 'Free (default)';
  return getQuotaPlans().find((p) => p.id === Number(planId))?.name || 'Kustom';
}
async function load() {
  const { data } = await api.get('/admin/users');
  users.value = data || [];
}
async function upd(u, patch) {
  try {
    await api.put(`/admin/users/${u.id}`, patch);
    logActivity({ action: 'update', description: `Perbarui pengguna ${u.email}: ${JSON.stringify(patch)}`, user: auth.user });
    await load();
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal memperbarui', text: e.response?.data?.message || 'Terjadi kesalahan.' });
  }
}
async function toggle(u) {
  await api.post(`/admin/users/${u.id}/toggle`);
  logActivity({ action: 'update', description: `${u.is_active ? 'Nonaktifkan' : 'Aktifkan'} pengguna ${u.email}`, user: auth.user });
  await load();
}
async function removeUser(u) {
  const res = await Swal.fire({ title: 'Hapus pengguna?', text: `${u.name} (${u.email}) akan dihapus.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  try {
    await api.delete(`/admin/users/${u.id}`);
    logActivity({ action: 'delete', description: `Hapus pengguna ${u.email}`, user: auth.user });
    await load();
  } catch {
    // Backend belum menyediakan endpoint DELETE /admin/users/{id}.
    Swal.fire({ icon: 'error', title: 'Belum bisa dihapus', text: 'Endpoint hapus pengguna belum tersedia di backend (DELETE /admin/users/{id}).' });
  }
}
onMounted(load);
</script>
