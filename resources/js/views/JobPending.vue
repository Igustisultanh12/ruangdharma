<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage } from '@/lib/api.js';
import { dateTime, engineShort, MODELS } from '@/lib/format.js';
import { toast, confirmDialog } from '@/lib/toast.js';

const loading = ref(false);
const jobs = ref([]);
const acting = ref(null);
const expanded = ref({});
let timer = null;

function toggleScript(g) {
  expanded.value = { ...expanded.value, [g.id]: !expanded.value[g.id] };
}
function captionText(g) {
  if (!g.caption) return '';
  return typeof g.caption === 'string' ? g.caption : (g.caption.text || '');
}
function isPlaceholderCaption(g) {
  const t = captionText(g);
  return t === '' || t === 'Menunggu konfirmasi admin sebelum video dibuat Muse.';
}
function hasScript(g) {
  return (Array.isArray(g.naskah) && g.naskah.length > 0)
    || (Array.isArray(g.prompts) && g.prompts.length > 0)
    || !isPlaceholderCaption(g);
}
function scriptText(n) {
  return n.teks || n.dialog || '';
}

function modelLabel(v) {
  return MODELS.find((m) => m.value === v)?.label || v;
}
async function load() {
  try {
    const { data } = await api.get('/jobs/pending-confirmation');
    jobs.value = data.data?.data || data.data || [];
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat job pending.'));
  }
}
async function loadFirst() {
  loading.value = true;
  await load();
  loading.value = false;
}
async function confirmJob(g) {
  const ok = await confirmDialog({ title: 'Konfirmasi job ini?', message: `Video "${g.keyword}" (${modelLabel(g.model)}, ${g.durasi} dtk, generate pakai ${engineShort(g.engine)}) akan langsung dibuat oleh Muse sekarang.`, confirmText: 'Ya, Buat Sekarang', tone: 'primary' });
  if (!ok) return;
  acting.value = g.id;
  try {
    const { data } = await api.post(`/generations/${g.id}/confirm-job`);
    toast.success(data.message || 'Job dikonfirmasi, video mulai dibuat.');
    await load();
  } catch (e) { toast.error(errorMessage(e)); } finally { acting.value = null; }
}
async function rejectJob(g) {
  const ok = await confirmDialog({ title: 'Tolak job ini?', message: `Video "${g.keyword}" tidak akan dibuat.`, confirmText: 'Ya, Tolak', tone: 'danger' });
  if (!ok) return;
  acting.value = g.id;
  try {
    const { data } = await api.post(`/generations/${g.id}/reject-job`);
    toast.success(data.message || 'Job ditolak.');
    await load();
  } catch (e) { toast.error(errorMessage(e)); } finally { acting.value = null; }
}
onMounted(() => {
  loadFirst();
  timer = setInterval(load, 10000);
});
onUnmounted(() => { if (timer) clearInterval(timer); });
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-2xl font-bold">Job Pending</h1>
        <p class="mt-1 text-sm text-muted">Setiap hari Muse menyiapkan draft video di sini. Admin tinggal konfirmasi — begitu dikonfirmasi, videonya langsung dibuat. Semua video dibuat <strong>tanpa backsound</strong>.</p>
      </div>
      <button class="btn btn-secondary" @click="loadFirst">
        <Icon name="refresh-cw" class="h-4 w-4" />
        Muat ulang
      </button>
    </div>

    <div v-if="loading" class="card p-8 text-center"><Spinner class="mx-auto h-6 w-6" /><p class="mt-2 text-sm text-muted">Memuat job pending...</p></div>

    <div v-else-if="jobs.length" class="grid gap-3">
      <div v-for="g in jobs" :key="g.id" class="card p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="font-bold">{{ g.keyword }}</p>
            <p class="text-xs text-muted">{{ modelLabel(g.model) }} • {{ g.durasi }} dtk • generate {{ engineShort(g.engine) }} • disiapkan {{ dateTime(g.created_at) }} • tanpa backsound</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <Badge color="amber" icon="hourglass">Menunggu konfirmasi</Badge>
            <button v-if="hasScript(g)" class="btn btn-secondary btn-sm" @click="toggleScript(g)">{{ expanded[g.id] ? 'Sembunyikan naskah' : 'Lihat naskah' }}</button>
            <button class="btn btn-secondary btn-sm" :disabled="acting === g.id" @click="rejectJob(g)">Tolak</button>
            <button class="btn btn-primary btn-sm" :disabled="acting === g.id" @click="confirmJob(g)">
              <Spinner v-if="acting === g.id" class="h-4 w-4" />
              Konfirmasi & Buat
            </button>
          </div>
        </div>
        <div v-if="expanded[g.id]" class="mt-4 space-y-3 border-t pt-4">
          <div v-if="!isPlaceholderCaption(g)">
            <p class="text-xs font-bold uppercase tracking-wide text-muted">Caption</p>
            <p class="mt-1 whitespace-pre-line text-sm">{{ captionText(g) }}</p>
          </div>
          <div v-if="Array.isArray(g.naskah) && g.naskah.length">
            <p class="text-xs font-bold uppercase tracking-wide text-muted">Naskah ({{ g.naskah.length }} bagian)</p>
            <ol class="mt-2 space-y-2">
              <li v-for="(n, i) in g.naskah" :key="i" class="text-sm">
                <p><strong>Bagian {{ n.bagian || i + 1 }}{{ n.judul_bagian ? ' — ' + n.judul_bagian : '' }}</strong><span v-if="n.speaker"> • {{ n.speaker }}</span>: {{ scriptText(n) }}</p>
                <p v-if="n.prompt_visual" class="text-xs text-muted">Prompt visual: {{ n.prompt_visual }}</p>
              </li>
            </ol>
          </div>
          <div v-else-if="Array.isArray(g.prompts) && g.prompts.length">
            <p class="text-xs font-bold uppercase tracking-wide text-muted">Prompt visual</p>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
              <li v-for="(p, i) in g.prompts" :key="i">{{ p }}</li>
            </ul>
          </div>
          <p v-else class="text-sm text-muted">Naskah belum diunggah Muse untuk draft ini.</p>
        </div>
      </div>
    </div>

    <div v-else class="card p-6 text-sm text-muted">Tidak ada job yang menunggu konfirmasi. Draft video harian berikutnya akan muncul di sini setiap pagi.</div>
  </div>
</template>
