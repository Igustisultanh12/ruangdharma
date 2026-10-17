<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Log Aktivitas</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Aktivitas pengguna yang tercatat dari aksi nyata di aplikasi.</p>

    <div class="card mb-4">
      <div class="grid sm:grid-cols-3 gap-3">
        <select v-model="actionFilter" class="input" @change="page = 1"><option value="">Semua Aksi</option><option v-for="a in actions" :key="a" :value="a">{{ actionLabel(a) }}</option></select>
        <input v-model="search" class="input" placeholder="Cari pengguna / deskripsi..." />
        <button @click="page = 1" class="btn-primary">Filter</button>
      </div>
    </div>

    <div class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Deskripsi</th><th>IP</th></tr></thead>
          <tbody>
            <tr v-for="l in paged" :key="l.id">
              <td class="whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
              <td><p class="font-medium">{{ l.user_name }}</p><p class="text-xs text-slate-400">{{ l.user_email }}</p></td>
              <td><Badge :variant="actionVariant(l.action)">{{ actionLabel(l.action) }}</Badge></td>
              <td>{{ l.description }}</td>
              <td>{{ l.ip || '-' }}</td>
            </tr>
            <tr v-if="!paged.length"><td colspan="5"><EmptyState title="Belum ada log" description="Log akan muncul saat pengguna melakukan aktivitas." /></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <Pagination :page="page" :last-page="lastPage" :total="filtered.length" @prev="page--" @next="page++" />
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api.js';
import { getActivities, ACTIVITY_ACTIONS } from '../../services/activity.js';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';
import Pagination from '../../components/Pagination.vue';

const logs = ref([]);
const actionFilter = ref('');
const search = ref('');
const page = ref(1);
const perPage = 20;
const actions = ACTIVITY_ACTIONS;

const ACTION_LABELS = { login: 'Masuk', logout: 'Keluar', generate: 'Generate', update: 'Ubah', delete: 'Hapus', approve: 'Setujui', reject: 'Tolak', publish: 'Publish', settings: 'Pengaturan', token: 'Token', review: 'Review', create: 'Buat' };
function actionLabel(a) { return ACTION_LABELS[a] || a; }
function actionVariant(a) {
  return { login: 'success', logout: 'slate', generate: 'violet', update: 'info', delete: 'danger', approve: 'success', reject: 'danger', publish: 'success', settings: 'warning', token: 'warning', review: 'info', create: 'violet' }[a] || 'slate';
}
function formatDate(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch { return iso; } }

const filtered = computed(() => logs.value.filter((l) =>
  (!actionFilter.value || l.action === actionFilter.value) &&
  (!search.value || `${l.user_name} ${l.user_email} ${l.description}`.toLowerCase().includes(search.value.toLowerCase()))
));
const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));
const paged = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage));

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/activity-logs');
    logs.value = data.data || data || [];
    if (logs.value.length) return;
  } catch { /* fallback localStorage */ }
  logs.value = getActivities();
});
</script>
