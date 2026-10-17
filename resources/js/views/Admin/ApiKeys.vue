<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">API Keys</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Kelola kunci API untuk integrasi eksternal. Key hanya ditampilkan sekali saat dibuat — simpan di tempat aman. Disimpan di perangkat ini sampai backend menyediakan endpoint API keys.</p>

    <div v-if="newKey" class="card mb-4 !border-2 !border-amber-400 bg-amber-50 dark:bg-amber-950/40">
      <p class="font-bold text-amber-800 dark:text-amber-200">API Key baru dibuat — salin sekarang, key tidak ditampilkan lagi.</p>
      <div class="flex gap-2 mt-2 items-center flex-wrap">
        <code class="text-xs break-all bg-white dark:bg-slate-800 px-2 py-1.5 rounded border flex-1">{{ newKey }}</code>
        <button @click="copyText(newKey, 'API key')" class="btn-primary btn-sm">Salin</button>
        <button @click="newKey = ''" class="btn-secondary btn-sm">Tutup</button>
      </div>
    </div>

    <div class="card mb-4">
      <div class="flex gap-2 flex-wrap">
        <input v-model="newName" class="input !w-64" placeholder="Nama key (misal: Mobile App)" @keyup.enter="createKey" />
        <button @click="createKey" class="btn-primary">Buat Key Baru</button>
      </div>
    </div>

    <div class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Nama</th><th>Preview</th><th>Terakhir Dipakai</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="k in keys" :key="k.id">
              <td class="font-medium">{{ k.name }}</td>
              <td><code class="text-xs">{{ k.preview }}</code></td>
              <td>{{ k.last_used ? formatDate(k.last_used) : 'Belum pernah' }}</td>
              <td><Badge :variant="k.active ? 'success' : 'slate'">{{ k.active ? 'Aktif' : 'Nonaktif' }}</Badge></td>
              <td class="text-right">
                <div class="flex gap-1.5 justify-end">
                  <button @click="toggleKey(k)" class="btn-secondary btn-sm">{{ k.active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                  <button @click="removeKey(k)" class="btn-danger btn-sm">Hapus</button>
                </div>
              </td>
            </tr>
            <tr v-if="!keys.length"><td colspan="5"><EmptyState title="Belum ada API key" description="Buat key pertama untuk integrasi eksternal." /></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { useAuthStore } from '../../stores/auth.js';
import { getApiKeys, saveApiKeys } from '../../services/preferences.js';
import { logActivity } from '../../services/activity.js';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';

const auth = useAuthStore();
const keys = ref(getApiKeys());
const newName = ref('');
const newKey = ref('');

function randomKey() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let out = 'rd_';
  const buf = new Uint32Array(40);
  crypto.getRandomValues(buf);
  buf.forEach((n) => { out += chars[n % chars.length]; });
  return out;
}
function createKey() {
  if (!newName.value.trim()) {
    Swal.fire({ icon: 'error', title: 'Nama key wajib diisi' });
    return;
  }
  const full = randomKey();
  keys.value.unshift({ id: Date.now(), name: newName.value.trim(), preview: full.slice(0, 7) + '...' + full.slice(-4), last_used: null, active: true });
  saveApiKeys(keys.value);
  newKey.value = full;
  newName.value = '';
  logActivity({ action: 'token', description: 'Membuat API key baru', user: auth.user });
}
function toggleKey(k) {
  k.active = !k.active;
  saveApiKeys(keys.value);
}
async function removeKey(k) {
  const res = await Swal.fire({ title: 'Hapus API key?', text: `${k.name} (${k.preview}) tidak bisa dipakai lagi.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  keys.value = keys.value.filter((x) => x.id !== k.id);
  saveApiKeys(keys.value);
  logActivity({ action: 'delete', description: `Hapus API key ${k.name}`, user: auth.user });
}
async function copyText(text, label) {
  try {
    await navigator.clipboard.writeText(text);
    Swal.fire({ icon: 'success', title: `${label} tersalin`, timer: 1200, showConfirmButton: false });
  } catch { /* abaikan */ }
}
function formatDate(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch { return iso; } }
</script>
