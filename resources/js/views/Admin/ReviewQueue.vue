<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Review Naskah</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Antrian naskah menunggu review. Status review disimpan di perangkat ini sampai backend menyediakan endpoint review.</p>

    <div class="flex gap-1 bg-slate-200/70 dark:bg-slate-800 rounded-xl p-1 w-fit mb-4 overflow-x-auto max-w-full">
      <button v-for="t in statusTabs" :key="t.key" @click="statusFilter = t.key" class="px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap" :class="statusFilter === t.key ? 'bg-white dark:bg-slate-700 shadow text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-slate-300'">{{ t.label }}</button>
    </div>

    <div class="card !p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Kata Kunci</th><th>Pengguna</th><th>Model</th><th>Status Review</th><th>Catatan</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="g in filtered" :key="g.id">
              <td class="font-medium">{{ g.keyword }}</td>
              <td>{{ g.user?.name || '-' }}</td>
              <td><Badge variant="slate">{{ g.model }}</Badge></td>
              <td><Badge :variant="statusVariant(reviewOf(g.id).status)">{{ statusLabel(reviewOf(g.id).status) }}</Badge></td>
              <td class="max-w-56 truncate" :title="reviewOf(g.id).note">{{ reviewOf(g.id).note || '-' }}</td>
              <td class="text-right">
                <div class="flex gap-1.5 justify-end flex-wrap">
                  <button @click="setStatus(g, 'approved')" class="btn-primary btn-sm">Approve</button>
                  <button @click="openReject(g)" class="btn-danger btn-sm">Reject</button>
                  <button @click="setStatus(g, 'published')" class="btn-gold btn-sm">Publish</button>
                  <button @click="setStatus(g, 'archived')" class="btn-secondary btn-sm">Archive</button>
                  <button @click="openReject(g, true)" class="btn-secondary btn-sm">Minta Revisi</button>
                </div>
              </td>
            </tr>
            <tr v-if="!filtered.length"><td colspan="6"><EmptyState title="Tidak ada naskah" description="Ubah filter status atau tunggu generate baru." /></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <Pagination :page="page" :last-page="lastPage" :total="total" @prev="load(page - 1)" @next="load(page + 1)" />

    <Modal :show="!!rejectTarget" title="Catatan Review" @close="rejectTarget = null">
      <template v-if="rejectTarget">
        <p class="text-sm mb-2">Naskah: <b>{{ rejectTarget.keyword }}</b> — {{ rejectMode === 'revisi' ? 'Minta revisi' : 'Reject' }}</p>
        <textarea v-model="rejectNote" class="input" rows="4" placeholder="Tulis catatan untuk penulis..."></textarea>
      </template>
      <template #footer v-if="rejectTarget">
        <button @click="rejectTarget = null" class="btn-secondary">Batal</button>
        <button @click="confirmReject" class="btn-primary">Simpan</button>
      </template>
    </Modal>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import api from '../../services/api.js';
import { useAuthStore } from '../../stores/auth.js';
import { getReviewOverrides, saveReviewOverrides } from '../../services/preferences.js';
import { logActivity } from '../../services/activity.js';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';
import Pagination from '../../components/Pagination.vue';
import Modal from '../../components/Modal.vue';

const auth = useAuthStore();
const items = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const statusFilter = ref('all');
const overrides = ref(getReviewOverrides());
const rejectTarget = ref(null);
const rejectNote = ref('');
const rejectMode = ref('reject');

const statusTabs = [
  { key: 'all', label: 'Semua' },
  { key: 'review', label: 'Menunggu Review' },
  { key: 'revisi', label: 'Revisi' },
  { key: 'approved', label: 'Disetujui' },
  { key: 'rejected', label: 'Ditolak' },
  { key: 'published', label: 'Terbit' },
  { key: 'archived', label: 'Arsip' },
];
function reviewOf(id) {
  return overrides.value[id] || { status: 'review', note: '' };
}
function statusLabel(s) {
  return { review: 'Menunggu Review', revisi: 'Revisi', approved: 'Disetujui', rejected: 'Ditolak', published: 'Terbit', archived: 'Arsip' }[s] || s;
}
function statusVariant(s) {
  return { review: 'warning', revisi: 'info', approved: 'success', rejected: 'danger', published: 'violet', archived: 'slate' }[s] || 'slate';
}
const filtered = computed(() => items.value.filter((g) => statusFilter.value === 'all' || reviewOf(g.id).status === statusFilter.value));

async function load(p = 1) {
  page.value = p;
  const { data } = await api.get('/admin/generations', { params: { page: p } });
  items.value = data.data || [];
  lastPage.value = data.last_page || 1;
  total.value = data.total ?? items.value.length;
}
function setStatus(g, status) {
  overrides.value[g.id] = { status, note: overrides.value[g.id]?.note || '' };
  saveReviewOverrides(overrides.value);
  const action = { approved: 'approve', rejected: 'reject', published: 'publish', archived: 'update' }[status] || 'review';
  logActivity({ action, description: `${statusLabel(status)} naskah "${g.keyword}"`, user: auth.user });
  Swal.fire({ icon: 'success', title: `Naskah ${statusLabel(status).toLowerCase()}`, timer: 1200, showConfirmButton: false });
}
function openReject(g, isRevisi = false) {
  rejectTarget.value = g;
  rejectMode.value = isRevisi ? 'revisi' : 'reject';
  rejectNote.value = overrides.value[g.id]?.note || '';
}
function confirmReject() {
  const g = rejectTarget.value;
  overrides.value[g.id] = { status: rejectMode.value === 'revisi' ? 'revisi' : 'rejected', note: rejectNote.value };
  saveReviewOverrides(overrides.value);
  logActivity({ action: rejectMode.value === 'revisi' ? 'review' : 'reject', description: `${rejectMode.value === 'revisi' ? 'Minta revisi' : 'Tolak'} naskah "${g.keyword}": ${rejectNote.value}`, user: auth.user });
  rejectTarget.value = null;
  Swal.fire({ icon: 'success', title: 'Catatan tersimpan', timer: 1200, showConfirmButton: false });
}
onMounted(() => load(1));
</script>
