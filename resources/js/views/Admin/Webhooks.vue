<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Webhooks</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Kirim event ke URL eksternal. Disimpan di perangkat ini sampai backend menyediakan endpoint webhooks. Tombol Test mencatat pengiriman simulasi ke Log Pengiriman.</p>

    <div class="flex gap-1 bg-slate-200/70 dark:bg-slate-800 rounded-xl p-1 w-fit mb-4">
      <button @click="tab = 'list'" class="px-4 py-2 rounded-lg text-sm font-medium" :class="tab === 'list' ? 'bg-white dark:bg-slate-700 shadow text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-slate-300'">Daftar Webhook</button>
      <button @click="tab = 'logs'" class="px-4 py-2 rounded-lg text-sm font-medium" :class="tab === 'logs' ? 'bg-white dark:bg-slate-700 shadow text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-slate-300'">Log Pengiriman</button>
    </div>

    <div v-if="tab === 'list'">
      <div class="card mb-4">
        <h2 class="font-bold text-slate-900 dark:text-white mb-3">{{ editing ? 'Edit Webhook' : 'Tambah Webhook' }}</h2>
        <div class="grid md:grid-cols-2 gap-3">
          <div><label class="label">URL Endpoint</label><input v-model="form.url" class="input" placeholder="https://contoh.com/webhook" /></div>
          <div><label class="label">Nama</label><input v-model="form.name" class="input" placeholder="misal: Notifikasi Discord" /></div>
        </div>
        <div class="mt-3">
          <label class="label">Events</label>
          <div class="flex gap-4 flex-wrap">
            <label v-for="e in EVENTS" :key="e" class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" :value="e" v-model="form.events" class="h-4 w-4 accent-violet-600" /> {{ e }}</label>
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm cursor-pointer mt-3"><input type="checkbox" v-model="form.active" class="h-4 w-4 accent-violet-600" /> Webhook aktif</label>
        <div class="flex gap-2 mt-3">
          <button @click="saveWebhook" class="btn-primary">Simpan Webhook</button>
          <button @click="resetForm" class="btn-secondary">Atur Ulang</button>
        </div>
      </div>

      <div class="card !p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="table">
            <thead><tr><th>Nama / URL</th><th>Events</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
              <tr v-for="w in hooks" :key="w.id">
                <td><p class="font-medium">{{ w.name }}</p><p class="text-xs text-slate-400 break-all">{{ w.url }}</p></td>
                <td><div class="flex gap-1 flex-wrap"><Badge v-for="e in w.events" :key="e" variant="violet">{{ e }}</Badge></div></td>
                <td><Badge :variant="w.active ? 'success' : 'slate'">{{ w.active ? 'Aktif' : 'Nonaktif' }}</Badge></td>
                <td class="text-right">
                  <div class="flex gap-1.5 justify-end">
                    <button @click="testWebhook(w)" class="btn-secondary btn-sm">Test</button>
                    <button @click="openForm(w)" class="btn-secondary btn-sm">Edit</button>
                    <button @click="removeHook(w)" class="btn-danger btn-sm">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!hooks.length"><td colspan="4"><EmptyState title="Belum ada webhook" description="Tambahkan endpoint untuk menerima event studio." /></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="tab === 'logs'" class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Waktu</th><th>Webhook</th><th>Event</th><th>Status</th><th>Respons</th></tr></thead>
          <tbody>
            <tr v-for="l in logs" :key="l.id">
              <td class="whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
              <td class="max-w-56 truncate" :title="l.url">{{ l.name }}<p class="text-xs text-slate-400 truncate">{{ l.url }}</p></td>
              <td><Badge variant="violet">{{ l.event }}</Badge></td>
              <td><Badge :variant="l.status === 'success' ? 'success' : 'danger'">{{ l.status }}</Badge></td>
              <td>{{ l.response }}</td>
            </tr>
            <tr v-if="!logs.length"><td colspan="5"><EmptyState title="Belum ada log pengiriman" description="Tekan Test pada webhook untuk mencatat pengiriman simulasi." /></td></tr>
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
import { getWebhooks, saveWebhooks, getWebhookLogs, addWebhookLog } from '../../services/preferences.js';
import { logActivity } from '../../services/activity.js';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';

const auth = useAuthStore();
const EVENTS = ['generate.created', 'generate.completed', 'review.approved', 'user.created'];
const hooks = ref(getWebhooks());
const logs = ref(getWebhookLogs());
const tab = ref('list');
const form = ref({ id: null, name: '', url: '', events: [], active: true });
const editing = ref(false);

function resetForm() {
  form.value = { id: null, name: '', url: '', events: [], active: true };
  editing.value = false;
}
function openForm(w) {
  form.value = { ...w, events: [...w.events] };
  editing.value = true;
}
function saveWebhook() {
  if (!form.value.url.trim() || !/^https?:\/\//.test(form.value.url.trim())) {
    Swal.fire({ icon: 'error', title: 'URL tidak valid', text: 'Gunakan URL http(s) yang lengkap.' });
    return;
  }
  if (!form.value.events.length) {
    Swal.fire({ icon: 'error', title: 'Pilih minimal satu event' });
    return;
  }
  if (form.value.id) hooks.value = hooks.value.map((h) => (h.id === form.value.id ? { ...form.value } : h));
  else hooks.value.push({ ...form.value, id: Date.now() });
  saveWebhooks(hooks.value);
  logActivity({ action: 'update', description: `Simpan webhook ${form.value.name || form.value.url}`, user: auth.user });
  Swal.fire({ icon: 'success', title: 'Webhook tersimpan', timer: 1200, showConfirmButton: false });
  resetForm();
}
async function removeHook(w) {
  const res = await Swal.fire({ title: 'Hapus webhook?', text: w.url, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  hooks.value = hooks.value.filter((x) => x.id !== w.id);
  saveWebhooks(hooks.value);
}
function testWebhook(w) {
  // Simulasi: tidak melakukan fetch lintas domain (akan gagal CORS).
  // Backend nantinya mengirim POST sungguhan ke URL webhook.
  const event = w.events[0] || 'generate.created';
  addWebhookLog({ name: w.name, url: w.url, event, status: 'success', response: 'Simulasi: payload contoh dicatat (pengiriman nyata menunggu endpoint backend).' });
  logs.value = getWebhookLogs();
  Swal.fire({ icon: 'success', title: 'Test dicatat', text: 'Pengiriman simulasi tersimpan di Log Pengiriman.', timer: 1600, showConfirmButton: false });
}
function formatDate(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch { return iso; } }
</script>
