<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Riwayat Saya</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Semua hasil generate, favorit, dan arsip.</p>
      </div>
      <RouterLink to="/generate" class="btn-primary">Generate Baru</RouterLink>
    </div>

    <!-- Tab Semua / Favorit / Arsip -->
    <div class="flex gap-1 bg-slate-200/70 dark:bg-slate-800 rounded-xl p-1 w-fit mb-4">
      <button v-for="t in tabOptions" :key="t.key" @click="setTab(t.key)" class="px-4 py-2 rounded-lg text-sm font-medium" :class="tab === t.key ? 'bg-white dark:bg-slate-700 shadow text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-slate-300'">{{ t.label }} ({{ tabCounts[t.key] }})</button>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div>
          <label class="label" for="hist-search">Cari kata kunci</label>
          <input id="hist-search" v-model="search" class="input" placeholder="misal: Canang Sari" @keyup.enter="load(1)" />
        </div>
        <div>
          <label class="label" for="hist-model">Model</label>
          <select id="hist-model" v-model="modelFilter" class="input" @change="load(1)">
            <option value="">Semua Model</option>
            <option value="narasi">Narasi</option>
            <option value="ngobrol">Ngobrol</option>
            <option value="gabungan">Gabungan</option>
          </select>
        </div>
        <div>
          <label class="label" for="hist-from">Dari tanggal</label>
          <input id="hist-from" v-model="dateFrom" type="date" class="input" @change="load(1)" />
        </div>
        <div>
          <label class="label" for="hist-to">Sampai tanggal</label>
          <input id="hist-to" v-model="dateTo" type="date" class="input" @change="load(1)" />
        </div>
        <div>
          <label class="label" for="hist-sort">Urutkan</label>
          <select id="hist-sort" v-model="sort" class="input" @change="load(1)">
            <option value="desc">Terbaru</option>
            <option value="asc">Terlama</option>
          </select>
        </div>
      </div>
      <div class="flex gap-2 mt-3">
        <button @click="load(1)" class="btn-primary btn-sm">Terapkan Filter</button>
        <button @click="resetFilters" class="btn-secondary btn-sm">Atur Ulang</button>
      </div>
    </div>

    <!-- Tabel -->
    <div class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Kata Kunci</th><th>Durasi</th><th>Model</th><th>Tanggal</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="g in items" :key="g.id">
              <td>
                <button @click="openDetail(g)" class="font-medium text-violet-700 dark:text-violet-300 hover:underline text-left">{{ g.keyword }}</button>
                <span v-if="isFavorite(g.id)" class="ml-2"><Badge variant="warning">Favorit</Badge></span>
                <span v-if="isArchived(g.id)" class="ml-1"><Badge variant="slate">Arsip</Badge></span>
              </td>
              <td>{{ g.durasi }} dtk</td>
              <td><Badge :variant="modelVariant(g.model)">{{ modelLabel(g.model) }}</Badge></td>
              <td>{{ formatDate(g.created_at) }}</td>
              <td><Badge variant="success">{{ g.status || 'completed' }}</Badge></td>
              <td>
                <div class="flex gap-1.5 justify-end">
                  <button @click="toggleFav(g)" :title="isFavorite(g.id) ? 'Hapus dari favorit' : 'Tambah favorit'" class="p-2 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/40">
                    <svg class="h-5 w-5" :class="isFavorite(g.id) ? 'text-amber-500 fill-amber-500' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                  </button>
                  <button @click="toggleArch(g)" :title="isArchived(g.id) ? 'Keluarkan dari arsip' : 'Arsipkan'" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10.5 11.25h3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                  </button>
                  <button @click="regenerate(g)" class="btn-secondary btn-sm">Generate Ulang</button>
                  <button @click="openDetail(g)" class="btn-secondary btn-sm">Detail</button>
                  <button @click="removeItem(g)" class="btn-danger btn-sm">Hapus</button>
                </div>
              </td>
            </tr>
            <tr v-if="!items.length"><td colspan="6"><EmptyState title="Tidak ada riwayat" description="Coba ubah filter, atau buat video pertama Anda." /></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <Pagination :page="page" :last-page="lastPage" :total="total" @prev="load(page - 1)" @next="load(page + 1)" />

    <!-- Modal Detail: Detail / Versi / Komentar -->
    <Modal :show="!!detail" :title="detail ? `Detail — ${detail.keyword}` : ''" size="lg" @close="detail = null">
      <template v-if="detail">
        <div class="flex gap-1 bg-slate-100 dark:bg-slate-700 rounded-xl p-1 w-fit mb-4">
          <button v-for="t in detailTabs" :key="t.key" @click="detailTab = t.key" class="px-3 py-1.5 rounded-lg text-sm font-medium" :class="detailTab === t.key ? 'bg-white dark:bg-slate-800 shadow text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-slate-300'">{{ t.label }}</button>
        </div>

        <div v-if="detailTab === 'detail'">
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">{{ detail.durasi }} detik • {{ modelLabel(detail.model) }} • {{ formatDate(detail.created_at) }}</p>
          <h4 class="font-semibold mb-1">Naskah (versi aktif)</h4>
          <div v-for="n in activeNaskah" :key="n.bagian" class="border-b border-slate-100 dark:border-slate-700 py-2 last:border-0">
            <p class="text-sm font-medium">Bagian {{ n.bagian }} — {{ n.speaker }}</p>
            <p class="text-sm">{{ n.teks }}</p>
          </div>
          <h4 class="font-semibold mt-4 mb-1">Caption</h4>
          <pre class="whitespace-pre-wrap text-sm bg-slate-50 dark:bg-slate-700/50 rounded-xl p-3">{{ activeCaption }}</pre>
          <div class="flex gap-2 mt-4">
            <button @click="regenerate(detail)" class="btn-primary btn-sm">Generate Ulang</button>
            <button @click="copyText(activeCaption, 'Caption')" class="btn-secondary btn-sm">Salin Caption</button>
          </div>
        </div>

        <div v-if="detailTab === 'versions'">
          <div class="flex justify-between items-center mb-3">
            <p class="text-sm text-slate-500 dark:text-slate-400">Snapshot naskah + caption. Restore membuat versi baru dari versi lama.</p>
            <button @click="createVersion" class="btn-primary btn-sm">Buat Versi Baru</button>
          </div>
          <div class="space-y-2">
            <div v-for="v in versions" :key="v.id" class="flex items-center justify-between border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5">
              <div>
                <p class="font-medium text-sm">{{ v.label }} <span v-if="v.id === activeVersionId" class="ml-1"><Badge variant="success">Aktif</Badge></span></p>
                <p class="text-xs text-slate-500">{{ formatDate(v.created_at) }} • {{ (v.naskah || []).length }} bagian naskah</p>
              </div>
              <button @click="restoreVersion(v)" class="btn-secondary btn-sm" :disabled="v.id === activeVersionId">Restore</button>
            </div>
            <p v-if="!versions.length" class="text-sm text-slate-500">Belum ada versi.</p>
          </div>
        </div>

        <div v-if="detailTab === 'comments'">
          <div class="space-y-3 mb-4">
            <div v-for="c in comments" :key="c.id" class="border border-slate-200 dark:border-slate-700 rounded-xl p-3">
              <p class="text-sm font-medium">{{ c.author }} <span class="text-xs text-slate-400 font-normal">{{ formatDate(c.created_at) }}</span></p>
              <p class="text-sm mt-1">{{ c.text }}</p>
              <button @click="replyTo = replyTo === c.id ? null : c.id" class="text-xs text-violet-600 hover:underline mt-1">Balas</button>
              <div v-if="replyTo === c.id" class="flex gap-2 mt-2">
                <input v-model="replyText" class="input input-sm" placeholder="Tulis balasan..." @keyup.enter="addComment(c.id)" />
                <button @click="addComment(c.id)" class="btn-primary btn-sm">Kirim</button>
              </div>
              <div v-for="r in comments.filter((x) => x.parent_id === c.id)" :key="r.id" class="ml-4 mt-2 border-l-2 border-violet-200 dark:border-violet-800 pl-3">
                <p class="text-sm font-medium">{{ r.author }} <span class="text-xs text-slate-400 font-normal">{{ formatDate(r.created_at) }}</span></p>
                <p class="text-sm mt-0.5">{{ r.text }}</p>
              </div>
            </div>
            <p v-if="!topComments.length" class="text-sm text-slate-500">Belum ada komentar.</p>
          </div>
          <div class="flex gap-2">
            <input v-model="newComment" class="input" placeholder="Tulis komentar..." @keyup.enter="addComment(null)" />
            <button @click="addComment(null)" class="btn-primary">Kirim</button>
          </div>
        </div>
      </template>
    </Modal>
  </div>
</template>
<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import api from '../services/api.js';
import { useAuthStore } from '../stores/auth.js';
import { getFavorites, toggleFavorite, getArchived, toggleArchived, isFavorite as favCheck, isArchived as archCheck, getVersions, saveVersions, getComments, saveComments } from '../services/preferences.js';
import { logActivity } from '../services/activity.js';
import Badge from '../components/Badge.vue';
import EmptyState from '../components/EmptyState.vue';
import Pagination from '../components/Pagination.vue';
import Modal from '../components/Modal.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const items = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const search = ref('');
const modelFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const sort = ref('desc');
const tab = ref('semua');
const tabOptions = [
  { key: 'semua', label: 'Semua' },
  { key: 'favorit', label: 'Favorit' },
  { key: 'arsip', label: 'Arsip' },
];
const detail = ref(null);
const detailTab = ref('detail');
const detailTabs = [
  { key: 'detail', label: 'Detail' },
  { key: 'versions', label: 'Versi' },
  { key: 'comments', label: 'Komentar' },
];
const versions = ref([]);
const activeVersionId = ref(null);
const comments = ref([]);
const newComment = ref('');
const replyTo = ref(null);
const replyText = ref('');

const MODEL_LABELS = { narasi: 'Narasi', ngobrol: 'Ngobrol', gabungan: 'Gabungan' };
function modelLabel(m) { return MODEL_LABELS[m] || m; }
function modelVariant(m) { return m === 'narasi' ? 'violet' : m === 'ngobrol' ? 'warning' : 'info'; }
function formatDate(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch { return iso; } }
function isFavorite(id) { return favCheck(id); }
function isArchived(id) { return archCheck(id); }
const tabCounts = computed(() => ({ semua: total.value, favorit: getFavorites().length, arsip: getArchived().length }));
const topComments = computed(() => comments.value.filter((c) => !c.parent_id));
const activeVersion = computed(() => versions.value.find((v) => v.id === activeVersionId.value) || versions.value[0]);
const activeNaskah = computed(() => { const n = activeVersion.value?.naskah ?? detail.value?.naskah ?? []; return Array.isArray(n) ? n : []; });
const activeCaption = computed(() => { const c = activeVersion.value?.caption ?? detail.value?.caption ?? ''; return typeof c === 'string' ? c : (c?.text || ''); });

async function load(p = 1) {
  page.value = p;
  // Query dikirim ke API (page + filter). Backend saat ini baru memakai page;
  // filter tanggal/model/sort juga diterapkan sisi klien pada halaman yang dimuat.
  const params = { page: p, search: search.value || undefined, model: modelFilter.value || undefined, date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined, sort: sort.value };
  const { data } = await api.get('/generations', { params });
  // Backend: { success:true, data: paginator{ data:[...], last_page, total } } — jangan pakai paginator sebagai array.
  const paginator = data?.data && typeof data.data === 'object' && Array.isArray(data.data.data) ? data.data : null;
  let rows = paginator ? (paginator.data || []) : (Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []));
  if (search.value) rows = rows.filter((g) => (g.keyword || '').toLowerCase().includes(search.value.toLowerCase()));
  if (modelFilter.value) rows = rows.filter((g) => g.model === modelFilter.value);
  if (dateFrom.value) rows = rows.filter((g) => (g.created_at || '').slice(0, 10) >= dateFrom.value);
  if (dateTo.value) rows = rows.filter((g) => (g.created_at || '').slice(0, 10) <= dateTo.value);
  if (sort.value === 'asc') rows = [...rows].reverse();
  if (tab.value === 'favorit') rows = rows.filter((g) => isFavorite(g.id));
  if (tab.value === 'arsip') rows = rows.filter((g) => isArchived(g.id));
  items.value = rows;
  lastPage.value = paginator?.last_page || data?.last_page || 1;
  total.value = paginator?.total ?? data?.total ?? rows.length;
}
function setTab(t) {
  tab.value = t;
  router.replace({ query: { ...route.query, tab: t } });
  load(1);
}
function resetFilters() {
  search.value = '';
  modelFilter.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  sort.value = 'desc';
  load(1);
}
function toggleFav(g) {
  toggleFavorite(g.id);
  load(page.value);
}
function toggleArch(g) {
  toggleArchived(g.id);
  load(page.value);
}
function regenerate(g) {
  router.push({ name: 'generate', query: { keyword: g.keyword, durasi: g.durasi, model: g.model } });
}
async function removeItem(g) {
  const res = await Swal.fire({ title: 'Hapus riwayat?', text: `"${g.keyword}" akan dihapus.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  try {
    await api.delete(`/generations/${g.id}`);
    logActivity({ action: 'delete', description: `Hapus riwayat "${g.keyword}"`, user: auth.user });
    Swal.fire({ icon: 'success', title: 'Terhapus', timer: 1200, showConfirmButton: false });
    load(page.value);
  } catch (e) {
    // Backend belum menyediakan endpoint DELETE /generations/{id}.
    Swal.fire({ icon: 'error', title: 'Belum bisa dihapus', text: 'Endpoint hapus belum tersedia di backend (DELETE /generations/{id}). Item tetap ditampilkan.' });
  }
}
async function copyText(text, label) {
  try {
    await navigator.clipboard.writeText(text || '');
    Swal.fire({ icon: 'success', title: `${label} tersalin`, timer: 1200, showConfirmButton: false });
  } catch { /* abaikan */ }
}
async function openDetail(g) {
  detailTab.value = 'detail';
  try {
    const { data } = await api.get(`/generations/${g.id}`);
    // Backend: { success:true, data: generation } — unwrap agar detail.value adalah generation, bukan wrapper.
    detail.value = data?.data || data;
  } catch {
    detail.value = g;
  }
  versions.value = getVersions(g.id);
  if (!versions.value.length) {
    versions.value = [{ id: Date.now(), label: 'Versi 1 (Asli)', created_at: detail.value.created_at || new Date().toISOString(), naskah: detail.value.naskah || [], caption: detail.value.caption || '' }];
    saveVersions(g.id, versions.value);
  }
  activeVersionId.value = versions.value[0].id;
  comments.value = getComments(g.id);
}
function createVersion() {
  const n = versions.value.length + 1;
  versions.value.unshift({ id: Date.now(), label: `Versi ${n}`, created_at: new Date().toISOString(), naskah: activeNaskah.value, caption: activeCaption.value });
  saveVersions(detail.value.id, versions.value);
  activeVersionId.value = versions.value[0].id;
  Swal.fire({ icon: 'success', title: 'Versi baru dibuat', timer: 1200, showConfirmButton: false });
}
function restoreVersion(v) {
  const n = versions.value.length + 1;
  versions.value.unshift({ id: Date.now(), label: `Versi ${n} (Restore dari ${v.label})`, created_at: new Date().toISOString(), naskah: v.naskah, caption: v.caption });
  saveVersions(detail.value.id, versions.value);
  activeVersionId.value = versions.value[0].id;
  Swal.fire({ icon: 'success', title: 'Versi dipulihkan', text: 'Disimpan sebagai versi baru dan dijadikan versi aktif.', timer: 1600, showConfirmButton: false });
}
function addComment(parentId) {
  const text = parentId ? replyText.value.trim() : newComment.value.trim();
  if (!text) return;
  comments.value.push({ id: Date.now(), parent_id: parentId || null, author: auth.user?.name || 'Saya', text, created_at: new Date().toISOString() });
  saveComments(detail.value.id, comments.value);
  newComment.value = '';
  replyText.value = '';
  replyTo.value = null;
}
watch(() => route.query.q, (q) => {
  if (q !== undefined) {
    search.value = String(q || '');
    load(1);
  }
});
watch(() => route.query.tab, (t) => {
  if (t && ['semua', 'favorit', 'arsip'].includes(t)) {
    tab.value = t;
    load(1);
  }
});
onMounted(() => {
  if (route.query.q) search.value = String(route.query.q);
  if (route.query.tab && ['semua', 'favorit', 'arsip'].includes(route.query.tab)) tab.value = route.query.tab;
  load(1);
});
</script>
