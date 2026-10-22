<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import api, { errorMessage } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';

// Panel Auto Backsound: dipakai di Editor Audio (Riwayat) dan di preview halaman Media.
// Alur: user minta -> Muse carikan kandidat -> status preview (user dengarkan dulu)
// -> user tekan Gabungkan (server yang me-mix) / Cari Lainnya / Batalkan.
const props = defineProps({ generationId: { type: Number, required: true } });
const emit = defineEmits(['updated']);

const autoReq = ref(null);
const history = ref([]);
const autoBusy = ref(false);
const volume = ref(20);
let autoTimer = null;
const AUTO_OPEN = ['queued', 'searching', 'rejected'];
const AUTO_LABELS = {
  queued: 'Menunggu Muse mencarikan backsound…',
  searching: 'Muse sedang mencari backsound yang cocok…',
  preview: 'Kandidat siap — dengarkan dulu, lalu pilih Gabungkan atau Cari Lainnya.',
  rejected: 'Kandidat ditolak — Muse sedang mencarikan yang lain…',
  done: 'Backsound sudah digabungkan ke video.',
  failed: 'Pencarian backsound gagal.',
  cancelled: 'Permintaan dibatalkan.',
};
function scheduleAutoPoll() {
  clearTimeout(autoTimer);
  if (autoReq.value && AUTO_OPEN.includes(autoReq.value.status)) {
    autoTimer = setTimeout(loadAuto, 3000);
  }
}
async function loadAuto() {
  try {
    const { data } = await api.get(`/generations/${props.generationId}/auto-backsound`);
    autoReq.value = data.data || null;
    if (Array.isArray(autoReq.value?.history)) history.value = autoReq.value.history;
  } catch { /* diamkan: panel auto bersifat tambahan */ }
  scheduleAutoPoll();
}
function fmtDate(s) {
  if (!s) return '';
  try { return new Date(s).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); } catch { return ''; }
}
async function requestAuto() {
  autoBusy.value = true;
  try {
    const { data } = await api.post(`/generations/${props.generationId}/auto-backsound`, {});
    autoReq.value = data.data;
    toast.success(data.message || 'Permintaan Auto Backsound terkirim.');
    scheduleAutoPoll();
  } catch (err) { toast.error(errorMessage(err)); } finally { autoBusy.value = false; }
}
async function applyAuto() {
  autoBusy.value = true;
  try {
    const { data } = await api.post(`/generations/${props.generationId}/auto-backsound/apply`, { volume: volume.value });
    toast.success(data.message || 'Backsound digabungkan ke video.');
    await loadAuto();
    emit('updated');
  } catch (err) { toast.error(errorMessage(err)); } finally { autoBusy.value = false; }
}
async function rejectAuto() {
  autoBusy.value = true;
  try {
    const { data } = await api.post(`/generations/${props.generationId}/auto-backsound/reject`, {});
    autoReq.value = data.data;
    toast.success(data.message || 'Baik, Muse akan mencarikan yang lain.');
    scheduleAutoPoll();
  } catch (err) { toast.error(errorMessage(err)); } finally { autoBusy.value = false; }
}
async function cancelAuto() {
  autoBusy.value = true;
  try {
    const { data } = await api.post(`/generations/${props.generationId}/auto-backsound/cancel`, {});
    autoReq.value = data.data;
    toast.success(data.message || 'Permintaan dibatalkan.');
  } catch (err) { toast.error(errorMessage(err)); } finally { autoBusy.value = false; }
}
onMounted(() => { loadAuto(); });
onUnmounted(() => { clearTimeout(autoTimer); });
</script>
<template>
  <div class="card p-4 space-y-3">
    <div class="flex items-center gap-2"><Icon name="sparkles" class="h-4 w-4" /><h3 class="font-bold">Auto Backsound</h3><span class="text-xs text-muted">Muse carikan gamelan Bali/Jawa atau yang cocok — preview dulu, oke baru digabungkan</span></div>
    <template v-if="!autoReq || ['done', 'failed', 'cancelled'].includes(autoReq.status)">
      <p v-if="autoReq" class="text-sm" :class="autoReq.status === 'failed' ? 'text-red-500' : 'text-muted'">{{ AUTO_LABELS[autoReq.status] }}<span v-if="autoReq.status === 'failed' && autoReq.error"> {{ autoReq.error }}</span></p>
      <div>
        <button class="btn btn-primary btn-sm" :disabled="autoBusy" @click="requestAuto"><Spinner v-if="autoBusy" class="h-4 w-4" />Minta Auto Backsound</button>
      </div>
      <p class="text-xs text-muted">Muse akan mencari backsound yang cocok dengan tema video ini. Hasilnya muncul di sini sebagai preview — tidak langsung digabungkan.</p>
    </template>
    <template v-else>
      <div class="flex items-center gap-2 text-sm text-muted">
        <Spinner v-if="AUTO_OPEN.includes(autoReq.status)" class="h-4 w-4" />
        <span>{{ AUTO_LABELS[autoReq.status] || autoReq.status }}</span>
      </div>
      <div v-if="autoReq.status === 'preview'" class="space-y-2">
        <p class="text-sm font-medium">{{ autoReq.candidate_name }}</p>
        <p v-if="autoReq.candidate_source" class="text-xs text-muted">Sumber: {{ autoReq.candidate_source }}</p>
        <audio controls :src="autoReq.candidate_url" class="w-full"></audio>
        <label class="block text-sm">Volume gabung: {{ volume }}%<input v-model.number="volume" type="range" min="0" max="100" class="w-full" /><span class="text-xs text-muted">20 = pelan di bawah suara dialog/narasi</span></label>
        <div class="flex flex-wrap gap-2">
          <button class="btn btn-primary btn-sm" :disabled="autoBusy" @click="applyAuto"><Spinner v-if="autoBusy" class="h-4 w-4" />Gabungkan ke Video</button>
          <button class="btn btn-secondary btn-sm" :disabled="autoBusy" @click="rejectAuto">Cari Lainnya</button>
          <button class="btn btn-ghost btn-sm" :disabled="autoBusy" @click="cancelAuto">Batalkan</button>
        </div>
      </div>
      <div v-else class="flex gap-2">
        <button class="btn btn-ghost btn-sm" :disabled="autoBusy" @click="cancelAuto">Batalkan</button>
      </div>
    </template>
    <div v-if="history.length" class="border-t border-slate-100 pt-3">
      <p class="text-xs font-bold text-slate-600">🎧 Riwayat mix backsound</p>
      <ul class="mt-2 space-y-2">
        <li v-for="h in history" :key="h.id" class="flex items-center justify-between gap-2 rounded-lg bg-purple-50 px-3 py-2">
          <div class="min-w-0">
            <p class="truncate text-xs font-semibold">{{ h.candidate_name || 'Backsound' }}</p>
            <p class="text-[11px] text-muted">{{ fmtDate(h.finished_at) }}<span v-if="h.applied_volume != null"> • volume {{ h.applied_volume }}%</span></p>
          </div>
          <a v-if="h.result_video_url" :href="h.result_video_url" target="_blank" rel="noopener" class="btn btn-primary btn-sm shrink-0">▶ Putar</a>
        </li>
      </ul>
    </div>
  </div>
</template>
