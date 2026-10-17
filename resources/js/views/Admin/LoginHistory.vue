<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Riwayat Login</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Percobaan masuk yang tercatat (berhasil & gagal).</p>

    <div class="card mb-4">
      <div class="grid sm:grid-cols-3 gap-3">
        <select v-model="statusFilter" class="input" @change="page = 1"><option value="">Semua Status</option><option value="success">Sukses</option><option value="failed">Gagal</option></select>
        <input v-model="search" class="input" placeholder="Cari email / pengguna..." />
        <button @click="page = 1" class="btn-primary">Filter</button>
      </div>
    </div>

    <div class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>IP</th><th>Perangkat</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="l in paged" :key="l.id">
              <td class="whitespace-nowrap">{{ formatDate(l.created_at) }}</td>
              <td><p class="font-medium">{{ l.user_name }}</p><p class="text-xs text-slate-400">{{ l.email }}</p></td>
              <td>{{ l.ip || '-' }}</td>
              <td class="max-w-xs truncate" :title="l.device">{{ l.device || '-' }}</td>
              <td><Badge :variant="l.success ? 'success' : 'danger'">{{ l.success ? 'Sukses' : 'Gagal' }}</Badge></td>
            </tr>
            <tr v-if="!paged.length"><td colspan="5"><EmptyState title="Belum ada riwayat login" description="Riwayat akan tercatat mulai dari login berikutnya." /></td></tr>
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
import { getLoginHistory } from '../../services/activity.js';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';
import Pagination from '../../components/Pagination.vue';

const logs = ref([]);
const statusFilter = ref('');
const search = ref('');
const page = ref(1);
const perPage = 20;

function formatDate(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch { return iso; } }
const filtered = computed(() => logs.value.filter((l) =>
  (!statusFilter.value || (statusFilter.value === 'success') === !!l.success) &&
  (!search.value || `${l.user_name} ${l.email}`.toLowerCase().includes(search.value.toLowerCase()))
));
const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));
const paged = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage));

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/login-history');
    logs.value = data.data || data || [];
    if (logs.value.length) return;
  } catch { /* fallback localStorage */ }
  logs.value = getLoginHistory();
});
</script>
