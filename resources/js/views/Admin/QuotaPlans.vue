<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Paket Kuota</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Kelola paket kuota dan tetapkan paket ke pengguna. Paket disimpan di perangkat ini sampai backend menyediakan endpoint paket.</p>

    <div class="grid lg:grid-cols-5 gap-4">
      <div class="lg:col-span-3">
        <div class="card !p-0 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="table">
              <thead><tr><th>Paket</th><th>Harga</th><th>Limit Harian</th><th>Fitur</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
              <tbody>
                <tr v-for="p in plans" :key="p.id">
                  <td class="font-medium">{{ p.name }}</td>
                  <td>Rp {{ Number(p.price || 0).toLocaleString('id-ID') }}</td>
                  <td>{{ p.daily_limit }} video/hari</td>
                  <td class="max-w-48">{{ p.features }}</td>
                  <td><Badge :variant="p.active ? 'success' : 'slate'">{{ p.active ? 'Aktif' : 'Nonaktif' }}</Badge></td>
                  <td class="text-right">
                    <div class="flex gap-1.5 justify-end">
                      <button @click="openForm(p)" class="btn-secondary btn-sm">Edit</button>
                      <button @click="removePlan(p)" class="btn-danger btn-sm">Hapus</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card mt-4">
          <h2 class="font-bold text-slate-900 dark:text-white mb-3">Assign Paket ke Pengguna</h2>
          <div class="grid sm:grid-cols-3 gap-3">
            <select v-model="assign.userId" class="input"><option value="">Pilih pengguna</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option></select>
            <select v-model="assign.planId" class="input"><option value="">Pilih paket</option><option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }} — {{ p.daily_limit }}/hari</option></select>
            <button @click="assignPlan" class="btn-primary" :disabled="!assign.userId || !assign.planId">Terapkan</button>
          </div>
          <p class="text-xs text-slate-400 mt-2">Assign juga memperbarui limit harian pengguna via API (daily_limit paket).</p>
        </div>
      </div>
      <div class="lg:col-span-2">
        <div class="card">
          <h2 class="font-bold text-slate-900 dark:text-white mb-3">{{ editing ? 'Edit Paket' : 'Paket Baru' }}</h2>
          <div class="space-y-3">
            <div><label class="label">Nama paket</label><input v-model="form.name" class="input" placeholder="misal: Pro" /></div>
            <div><label class="label">Harga (Rp)</label><input v-model.number="form.price" type="number" min="0" class="input" /></div>
            <div><label class="label">Limit harian (video/hari)</label><input v-model.number="form.daily_limit" type="number" min="1" max="1000" class="input" /></div>
            <div><label class="label">Fitur</label><textarea v-model="form.features" class="input" rows="2"></textarea></div>
            <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" v-model="form.active" class="h-4 w-4 accent-violet-600" /> Paket aktif</label>
            <div class="flex gap-2"><button @click="savePlan" class="btn-primary">Simpan Paket</button><button @click="resetForm" class="btn-secondary">Atur Ulang</button></div>
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
import { getQuotaPlans, saveQuotaPlans, getUserPlans, saveUserPlans } from '../../services/preferences.js';
import { logActivity } from '../../services/activity.js';
import Badge from '../../components/Badge.vue';

const auth = useAuthStore();
const plans = ref(getQuotaPlans());
const users = ref([]);
const form = ref({ id: null, name: '', price: 0, daily_limit: 5, features: '', active: true });
const editing = ref(false);
const assign = ref({ userId: '', planId: '' });

function resetForm() {
  form.value = { id: null, name: '', price: 0, daily_limit: 5, features: '', active: true };
  editing.value = false;
}
function openForm(p) {
  form.value = { ...p };
  editing.value = true;
}
function savePlan() {
  if (!form.value.name.trim()) {
    Swal.fire({ icon: 'error', title: 'Nama paket wajib diisi' });
    return;
  }
  if (form.value.id) plans.value = plans.value.map((p) => (p.id === form.value.id ? { ...form.value } : p));
  else plans.value.push({ ...form.value, id: Date.now() });
  saveQuotaPlans(plans.value);
  logActivity({ action: 'update', description: `Simpan paket kuota ${form.value.name}`, user: auth.user });
  Swal.fire({ icon: 'success', title: 'Paket tersimpan', timer: 1200, showConfirmButton: false });
  resetForm();
}
async function removePlan(p) {
  const res = await Swal.fire({ title: 'Hapus paket?', text: p.name, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  plans.value = plans.value.filter((x) => x.id !== p.id);
  saveQuotaPlans(plans.value);
}
async function assignPlan() {
  const plan = plans.value.find((p) => p.id === Number(assign.value.planId));
  if (!plan) return;
  const map = getUserPlans();
  map[assign.value.userId] = plan.id;
  saveUserPlans(map);
  try {
    await api.put(`/admin/users/${assign.value.userId}`, { daily_limit: plan.daily_limit });
  } catch { /* pemetaan lokal tetap tersimpan */ }
  logActivity({ action: 'update', description: `Assign paket ${plan.name} ke pengguna #${assign.value.userId}`, user: auth.user });
  Swal.fire({ icon: 'success', title: `Paket ${plan.name} ditetapkan`, timer: 1400, showConfirmButton: false });
  assign.value = { userId: '', planId: '' };
}
onMounted(async () => {
  try {
    const { data } = await api.get('/admin/users');
    users.value = data || [];
  } catch { /* abaikan */ }
});
</script>
