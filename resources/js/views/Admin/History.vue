<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Riwayat Generate Semua Pengguna</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Filter, telusuri, dan ekspor laporan.</p>
      </div>
      <div class="flex gap-2">
        <button @click="exportCsv" class="btn-secondary btn-sm">Ekspor CSV</button>
        <button @click="exportPdf" class="btn-secondary btn-sm">Ekspor PDF</button>
      </div>
    </div>

    <div class="card mb-4">
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div><label class="label">Pengguna</label><select v-model="userFilter" class="input" @change="load(1)"><option value="">Semua Pengguna</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option></select></div>
        <div><label class="label">Model</label><select v-model="modelFilter" class="input" @change="load(1)"><option value="">Semua Model</option><option value="narasi">Narasi</option><option value="ngobrol">Ngobrol</option><option value="gabungan">Gabungan</option></select></div>
        <div><label class="label">Tanggal</label><input v-model="dateFilter" type="date" class="input" @change="load(1)" /></div>
        <div><label class="label">Cari kata kunci</label><input v-model="search" class="input" placeholder="Cari..." @keyup.enter="load(1)" /></div>
      </div>
      <button @click="resetFilters" class="btn-secondary btn-sm mt-3">Atur Ulang Filter</button>
    </div>

    <div class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Kata Kunci</th><th>Model</th><th>Durasi</th><th>Pengguna</th><th>Tanggal</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="g in items" :key="g.id">
              <td class="font-medium">{{ g.keyword }}</td>
              <td><Badge :variant="modelVariant(g.model)">{{ modelLabel(g.model) }}</Badge></td>
              <td>{{ g.durasi }} dtk</td>
              <td>{{ g.user?.name || '-' }}<p class="text-xs text-slate-400">{{ g.user?.email || '' }}</p></td>
              <td>{{ formatDate(g.created_at) }}</td>
              <td><Badge variant="success">{{ g.status || 'completed' }}</Badge></td>
            </tr>
            <tr v-if="!items.length"><td colspan="6"><EmptyState title="Tidak ada riwayat" description="Ubah filter atau tunggu generate baru." /></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <Pagination :page="page" :last-page="lastPage" :total="total" @prev="load(page - 1)" @next="load(page + 1)" />
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import api from '../../services/api.js';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';
import Pagination from '../../components/Pagination.vue';

const items = ref([]);
const users = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const userFilter = ref('');
const modelFilter = ref('');
const dateFilter = ref('');
const search = ref('');

const MODEL_LABELS = { narasi: 'Narasi', ngobrol: 'Ngobrol', gabungan: 'Gabungan' };
function modelLabel(m) { return MODEL_LABELS[m] || m; }
function modelVariant(m) { return m === 'narasi' ? 'violet' : m === 'ngobrol' ? 'warning' : 'info'; }
function formatDate(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch { return iso; } }

const filtered = computed(() => {
  let rows = items.value;
  if (userFilter.value) rows = rows.filter((g) => g.user_id === Number(userFilter.value) || g.user?.id === Number(userFilter.value));
  if (modelFilter.value) rows = rows.filter((g) => g.model === modelFilter.value);
  if (dateFilter.value) rows = rows.filter((g) => (g.created_at || '').slice(0, 10) === dateFilter.value);
  if (search.value) rows = rows.filter((g) => (g.keyword || '').toLowerCase().includes(search.value.toLowerCase()));
  return rows;
});

async function load(p = 1) {
  page.value = p;
  const { data } = await api.get('/admin/generations', { params: { page: p } });
  items.value = data.data || [];
  lastPage.value = data.last_page || 1;
  total.value = data.total ?? items.value.length;
  // Tampilkan hasil filter klien pada halaman yang dimuat.
  const f = filtered.value;
  if (f.length !== items.value.length) items.value = f;
}
function resetFilters() {
  userFilter.value = '';
  modelFilter.value = '';
  dateFilter.value = '';
  search.value = '';
  load(1);
}
function csvEscape(v) {
  const s = String(v ?? '').replace(/"/g, '""');
  return `"${s}"`;
}
function buildCsvRows() {
  const rows = [['Kata Kunci', 'Model', 'Durasi', 'Pengguna', 'Email', 'Tanggal', 'Status']];
  filtered.value.forEach((g) => rows.push([g.keyword, g.model, g.durasi, g.user?.name || '', g.user?.email || '', g.created_at || '', g.status || 'completed']));
  return rows.map((r) => r.map(csvEscape).join(',')).join('\n');
}
function downloadBlob(content, filename, type) {
  const blob = new Blob([content], { type });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  a.click();
  URL.revokeObjectURL(a.href);
}
async function exportCsv() {
  try {
    // Coba endpoint backend dulu; jika belum tersedia, buat CSV dari data yang tampil.
    const { data } = await api.get('/admin/generations/export', { params: { format: 'csv', user_id: userFilter.value || undefined, model: modelFilter.value || undefined, date: dateFilter.value || undefined }, responseType: 'blob' });
    downloadBlob(data, 'laporan-generate.csv', 'text/csv');
  } catch {
    downloadBlob(buildCsvRows(), 'laporan-generate.csv', 'text/csv');
  }
  Swal.fire({ icon: 'success', title: 'CSV diekspor', timer: 1200, showConfirmButton: false });
}
async function exportPdf() {
  try {
    const { data } = await api.get('/admin/generations/export', { params: { format: 'pdf', user_id: userFilter.value || undefined, model: modelFilter.value || undefined, date: dateFilter.value || undefined }, responseType: 'blob' });
    downloadBlob(data, 'laporan-generate.pdf', 'application/pdf');
    Swal.fire({ icon: 'success', title: 'PDF diekspor', timer: 1200, showConfirmButton: false });
    return;
  } catch { /* fallback: jendela cetak */ }
  const rows = filtered.value.map((g) => `<tr><td>${g.keyword}</td><td>${g.model}</td><td>${g.durasi}</td><td>${g.user?.name || ''}</td><td>${formatDate(g.created_at)}</td></tr>`).join('');
  const win = window.open('', '_blank');
  if (!win) return;
  win.document.write(`<html><head><title>Laporan Generate</title><style>table{border-collapse:collapse;width:100%;font-family:sans-serif;font-size:13px}th,td{border:1px solid #ccc;padding:6px;text-align:left}</style></head><body><h2>Laporan Generate — Ruang Dharma Studio</h2><table><thead><tr><th>Kata Kunci</th><th>Model</th><th>Durasi</th><th>Pengguna</th><th>Tanggal</th></tr></thead><tbody>${rows}</tbody></table><script>window.print()<\/script></body></html>`);
  win.document.close();
}
onMounted(async () => {
  try {
    const { data } = await api.get('/admin/users');
    users.value = data || [];
  } catch { /* abaikan */ }
  load(1);
});
</script>
