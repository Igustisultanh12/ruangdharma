<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage } from '@/lib/api.js';
import { dateTime, number, relative } from '@/lib/format.js';
import { toast, confirmDialog } from '@/lib/toast.js';

const loading = ref(false);
const syncing = ref(false);
const channel = ref(null);
const sync = ref({ requested_at: null, last_at: null, summary: null });
const videos = ref([]);
let timer = null;

const counts = computed(() => {
  const c = { total: videos.value.length, done: 0, progress: 0, none: 0, failed: 0, detected: 0 };
  for (const v of videos.value) {
    if (v.youtube_status === 'completed') c.done++;
    else if (v.youtube_status === 'queued' || v.youtube_status === 'processing') c.progress++;
    else if (v.youtube_status === 'failed') c.failed++;
    else if (v.youtube_status === 'detected') c.detected++;
    else if (v.youtube_status !== 'skipped') c.none++;
  }
  return c;
});
const detected = computed(() => videos.value.filter((v) => v.youtube_status === 'detected'));
const checkOverrides = ref({});
function isChecked(v) {
  return checkOverrides.value[v.publish_id] ?? true;
}
function setChecked(v, val) {
  checkOverrides.value = { ...checkOverrides.value, [v.publish_id]: val };
}
const confirming = ref(false);
async function confirmSelected() {
  const selectedIds = detected.value.filter((v) => isChecked(v)).map((v) => v.publish_id);
  const skipIds = detected.value.filter((v) => !isChecked(v)).map((v) => v.publish_id);
  if (!selectedIds.length && !skipIds.length) return;
  const ok = await confirmDialog({ title: 'Upload yang dipilih?', message: `${selectedIds.length} video akan di-upload ke YouTube satu paket dengan caption-nya.${skipIds.length ? ` ${skipIds.length} video yang tidak dicentang akan dilewati.` : ''}`, confirmText: 'Ya, Upload', tone: 'primary' });
  if (!ok) return;
  confirming.value = true;
  try {
    const { data } = await api.post('/youtube/confirm-upload', { publish_ids: selectedIds, skip_ids: skipIds });
    toast.success(data.message || 'Konfirmasi tersimpan.');
    checkOverrides.value = {};
    await load();
    ensurePolling();
  } catch (e) { toast.error(errorMessage(e)); } finally { confirming.value = false; }
}
const progressPct = computed(() => (counts.value.total ? Math.round((counts.value.done / counts.value.total) * 100) : 0));
const busy = computed(() => !!sync.value.requested_at || counts.value.progress > 0);

const statCards = computed(() => {
  const s = channel.value;
  if (!s) return [];
  const cards = [
    { label: 'Subscriber', value: s.subscribers || 0, icon: 'users', color: 'violet' },
    { label: 'Total Views', value: s.total_views || 0, icon: 'eye', color: 'emerald' },
    { label: 'Total Like', value: s.total_likes || 0, icon: 'heart', color: 'rose' },
    { label: 'Total Komen', value: s.total_comments || 0, icon: 'messages-square', color: 'sky' },
  ];
  if (s.watch_hours !== null && s.watch_hours !== undefined) {
    cards.push({ label: 'Jam Tonton', value: s.watch_hours, icon: 'play', color: 'amber' });
  }
  return cards;
});

function statusBadge(v) {
  switch (v.youtube_status) {
    case 'completed': return { color: 'emerald', label: 'Terbit di YouTube' };
    case 'processing': return { color: 'sky', label: 'Sedang di-upload...' };
    case 'queued': return { color: 'sky', label: 'Antri upload' };
    case 'detected': return { color: 'violet', label: 'Menunggu konfirmasi' };
    case 'skipped': return { color: 'gray', label: 'Dilewati' };
    case 'failed': return { color: 'rose', label: 'Gagal' };
    default: return { color: 'amber', label: 'Belum di-upload' };
  }
}

async function load() {
  try {
    const { data } = await api.get('/youtube/overview');
    channel.value = data.channel;
    sync.value = data.sync || { requested_at: null, last_at: null, summary: null };
    videos.value = data.videos || [];
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat data YouTube.'));
  }
}
async function loadFirst() {
  loading.value = true;
  await load();
  loading.value = false;
  ensurePolling();
}
function ensurePolling() {
  if (busy.value && !timer) {
    timer = setInterval(async () => { await load(); if (!busy.value) stopPolling(); }, 3000);
  }
}
function stopPolling() {
  if (timer) { clearInterval(timer); timer = null; }
}
async function syncNow() {
  const ok = await confirmDialog({ title: 'Sinkronkan ke YouTube?', message: 'Muse akan mengecek semua video yang sudah jadi dan membandingkannya dengan isi channel. Video yang belum ada di YouTube TIDAK langsung di-upload — akan ditampilkan di sini untuk kamu konfirmasi dulu (centang yang mau di-upload, uncek yang tidak).', confirmText: 'Ya, Sinkronkan', tone: 'primary' });
  if (!ok) return;
  syncing.value = true;
  try {
    const { data } = await api.post('/youtube/sync');
    toast.success(data.message || 'Sinkronisasi diminta.');
    await load();
    ensurePolling();
  } catch (e) { toast.error(errorMessage(e)); } finally { syncing.value = false; }
}
async function uploadOne(v) {
  const ok = await confirmDialog({ title: 'Upload ke YouTube?', message: `Video "${v.keyword}" akan di-upload ke YouTube sebagai Shorts dengan caption dari Riwayat.`, confirmText: 'Ya, Upload', tone: 'primary' });
  if (!ok) return;
  try {
    const { data } = await api.post('/social/publish', { generation_id: v.id, platform: 'youtube' });
    toast.success(data.message || 'Job upload dikirim ke Muse.');
    await load();
    ensurePolling();
  } catch (e) { toast.error(errorMessage(e)); }
}
onMounted(loadFirst);
onUnmounted(stopPolling);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-2xl font-bold">YouTube</h1>
        <p class="mt-1 text-sm text-muted">Channel <strong>Ruang Dharmaa</strong> (@RuangDharmaa) — sinkronkan video dari web ke YouTube Shorts dan pantau insight-nya di sini.</p>
      </div>
      <button class="btn btn-primary" :disabled="syncing || busy" @click="syncNow">
        <Spinner v-if="syncing || busy" class="h-4 w-4" />
        <Icon v-else name="refresh-cw" class="h-4 w-4" />
        {{ busy ? 'Sinkronisasi berjalan...' : 'Sinkronkan sekarang' }}
      </button>
    </div>

    <div v-if="loading" class="card p-8 text-center"><Spinner class="mx-auto h-6 w-6" /><p class="mt-2 text-sm text-muted">Memuat data YouTube...</p></div>

    <template v-else>
      <!-- Sync summary -->
      <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-display text-lg font-bold">Status sinkronisasi</h2>
          <Badge v-if="sync.requested_at" color="sky" icon="refresh-cw">Sinkronisasi diminta {{ relative(sync.requested_at) }} — Muse sedang bekerja</Badge>
          <Badge v-else-if="sync.last_at" color="emerald" icon="check">Terakhir sinkron {{ relative(sync.last_at) }}</Badge>
          <Badge v-else color="amber">Belum pernah sinkron</Badge>
        </div>
        <p v-if="sync.summary" class="mt-2 text-sm text-muted">{{ sync.summary }}</p>
        <div class="mt-4">
          <div class="flex justify-between text-xs text-muted">
            <span>{{ counts.done }} dari {{ counts.total }} video sudah terbit di YouTube</span>
            <span>{{ progressPct }}%</span>
          </div>
          <div class="mt-1 h-3 overflow-hidden rounded-full bg-line">
            <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: progressPct + '%' }"></div>
          </div>
          <p class="mt-2 text-xs text-muted">{{ counts.progress }} sedang proses • {{ counts.detected }} menunggu konfirmasi • {{ counts.none }} belum di-upload • {{ counts.failed }} gagal</p>
        </div>
      </div>

      <!-- Detected: needs confirmation -->
      <div v-if="detected.length" class="card border-violet-300 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-display text-lg font-bold">Perlu konfirmasi upload</h2>
          <Badge color="violet" icon="list-checks">{{ detected.length }} video terdeteksi belum ada di YouTube</Badge>
        </div>
        <p class="mt-1 text-sm text-muted">Hasil deteksi sinkronisasi. Centang video yang mau di-upload (satu paket dengan caption dari web), hilangkan centang yang tidak. Yang tidak dicentang akan dilewati.</p>
        <div class="mt-4 grid gap-3">
          <label v-for="v in detected" :key="v.publish_id" class="flex cursor-pointer gap-3 rounded-2xl border-2 border-line p-3">
            <input type="checkbox" class="checkbox mt-1" :checked="isChecked(v)" @change="setChecked(v, $event.target.checked)" />
            <span class="min-w-0 flex-1">
              <span class="block font-bold">{{ v.keyword }}</span>
              <span class="block text-xs text-muted">{{ v.durasi }} dtk • {{ v.model }} • dibuat {{ dateTime(v.created_at) }}</span>
              <span v-if="v.caption" class="mt-1 block whitespace-pre-line text-xs text-muted line-clamp-3">Caption: {{ v.caption }}</span>
            </span>
          </label>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
          <button class="btn btn-primary" :disabled="confirming" @click="confirmSelected">
            <Spinner v-if="confirming" class="h-4 w-4" />
            Upload yang dipilih ({{ detected.filter((v) => isChecked(v)).length }})
          </button>
          <span class="text-xs text-muted">{{ detected.filter((v) => !isChecked(v)).length }} tidak dicentang akan dilewati</span>
        </div>
      </div>

      <!-- Channel stats -->
      <div v-if="channel" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="c in statCards" :key="c.label" class="card p-4">
          <div class="flex items-center gap-2 text-sm text-muted"><Icon :name="c.icon" class="h-4 w-4" />{{ c.label }}</div>
          <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ number(c.value) }}</p>
          <p class="text-xs text-muted">@{{ channel.username || 'RuangDharmaa' }} • {{ channel.fetched_at ? relative(channel.fetched_at) : 'belum pernah diambil' }}</p>
        </div>
      </div>
      <div v-else class="card p-6 text-sm text-muted">Belum ada data statistik channel. Klik "Sinkronkan sekarang" agar Muse mengambil statistik terbaru dari YouTube Studio.</div>

      <!-- Videos sync list -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-bold">Video web vs YouTube</h2>
        <p class="mt-1 text-sm text-muted">Daftar video yang sudah jadi di web dan statusnya di YouTube. Halaman ini memperbarui otomatis saat ada upload yang berjalan.</p>
        <div class="mt-4 grid gap-3">
          <div v-for="v in videos" :key="v.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-line p-3">
            <div class="min-w-0">
              <p class="font-bold">{{ v.keyword }}</p>
              <p class="text-xs text-muted">{{ v.durasi }} dtk • {{ v.model }} • {{ dateTime(v.created_at) }}</p>
              <p v-if="v.youtube_status === 'failed' && v.youtube_error" class="mt-1 text-xs text-rose-600">{{ v.youtube_error }}</p>
              <p v-if="v.youtube_status === 'completed' && v.youtube_published_at" class="mt-1 text-xs text-muted">Terbit {{ relative(v.youtube_published_at) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <Badge :color="statusBadge(v).color">{{ statusBadge(v).label }}</Badge>
              <a v-if="v.youtube_post_url" :href="v.youtube_post_url" target="_blank" class="btn btn-secondary btn-sm">Tonton</a>
              <button v-else-if="v.youtube_status === 'none' || v.youtube_status === 'failed'" class="btn btn-primary btn-sm" @click="uploadOne(v)">Upload</button>
              <Spinner v-else-if="v.youtube_status === 'processing'" class="h-4 w-4" />
            </div>
          </div>
          <p v-if="!videos.length" class="text-sm text-muted">Belum ada video yang jadi. Buat dulu di menu Buat video.</p>
        </div>
      </div>

      <!-- Insights per video -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-bold">Insight video di YouTube</h2>
        <div v-if="channel?.posts?.length" class="mt-3 space-y-3">
          <div v-for="p in channel.posts" :key="p.id || p.url" class="flex gap-3 rounded-2xl border-2 border-line p-3">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold line-clamp-2">{{ p.caption || p.title || '(tanpa judul)' }}</p>
              <p class="mt-1 text-xs text-muted">{{ p.created_at ? dateTime(p.created_at) : '' }}</p>
            </div>
            <div class="shrink-0 text-right text-sm">
              <p>👀 {{ number(p.views || 0) }}</p>
              <p>❤️ {{ number(p.likes || p.like_count || 0) }} • 💬 {{ number(p.comments || p.comment_count || 0) }}</p>
              <a v-if="p.url" :href="p.url" target="_blank" class="text-xs text-violet-600 hover:underline">Lihat video</a>
            </div>
          </div>
        </div>
        <p v-else class="mt-2 text-sm text-muted">Belum ada data insight. Sinkronkan dulu agar Muse mengambil data dari YouTube Studio.</p>
      </div>
    </template>
  </div>
</template>
