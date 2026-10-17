<script setup>
import { ref, computed, onMounted } from 'vue';
import api, { errorMessage } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import TrimTimeline from '@/components/TrimTimeline.vue';

const props = defineProps({ modelValue: { type: Object, default: () => ({ url: '', volume: 20, trimStart: 0, trimEnd: null }) } });
const emit = defineEmits(['update:modelValue']);
const list = ref([]);
const loading = ref(false);
const uploading = ref(false);
const name = ref('');
const file = ref(null);
const local = ref({ ...props.modelValue });
const selectedFileName = ref('');
const selectedFileSize = ref('');
const ACCEPTED_EXTS = ['mp3', 'wav', 'm4a', 'ogg'];

// Durasi acuan trim: durasi AUDIO PENUH yang dipilih/di-upload kalau
// diketahui (probe metadata / duration_sec server), fallback 60.
// TrimTimeline memakai durasi audio penuh ini, bukan durasi video,
// supaya segmen bisa dipilih dari tengah lagu yang lebih panjang.
const duration = ref(60);
const effEnd = computed(() => local.value.trimEnd ?? duration.value);

function fmt(s) {
  if (s == null || isNaN(s)) return '0:00';
  s = Math.max(0, Math.round(Number(s)));
  return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
}
function clampTrim() {
  if (local.value.trimStart < 0) local.value.trimStart = 0;
  if (local.value.trimStart > duration.value) local.value.trimStart = duration.value;
  if (local.value.trimEnd != null) {
    if (local.value.trimEnd < 0) local.value.trimEnd = 0;
    if (local.value.trimEnd > duration.value) local.value.trimEnd = duration.value;
    if (local.value.trimEnd < local.value.trimStart) local.value.trimEnd = local.value.trimStart;
  }
  if (local.value.trimStart > effEnd.value) local.value.trimStart = effEnd.value;
}
function fileSizeLabel(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
function validExt(fileName) {
  const ext = (fileName.split('.').pop() || '').toLowerCase();
  return ACCEPTED_EXTS.includes(ext);
}
function probeDuration(f) {
  return new Promise((resolve) => {
    try {
      const url = URL.createObjectURL(f);
      const a = new Audio();
      a.preload = 'metadata';
      a.onloadedmetadata = () => { URL.revokeObjectURL(url); resolve(a.duration || null); };
      a.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
      a.src = url;
    } catch { resolve(null); }
  });
}
// Probe durasi audio dari URL file yang dipilih (file_url): baca metadata
// Audio element. Dipakai saat backsound dari daftar tidak punya duration_sec.
function probeUrlDuration(url) {
  return new Promise((resolve) => {
    if (!url) { resolve(null); return; }
    try {
      const a = new Audio();
      a.preload = 'metadata';
      const t = setTimeout(() => resolve(null), 10000);
      a.onloadedmetadata = () => { clearTimeout(t); resolve(a.duration && isFinite(a.duration) ? a.duration : null); };
      a.onerror = () => { clearTimeout(t); resolve(null); };
      a.src = url;
    } catch { resolve(null); }
  });
}

function sync() { emit('update:modelValue', { ...local.value }); }
async function load() {
  loading.value = true;
  try { const { data } = await api.get('/backsounds'); list.value = data.data?.data || data.data || []; } catch {}
  finally { loading.value = false; }
}
async function upload(e) {
  const f = e.target.files?.[0]; if (!f) return;
  if (!name.value.trim()) { toast.error('Isi nama backsound dulu.'); e.target.value = ''; return; }
  if (!validExt(f.name)) {
    toast.error('Format file tidak didukung. Gunakan MP3, WAV, M4A, atau OGG.');
    e.target.value = ''; selectedFileName.value = ''; selectedFileSize.value = '';
    return;
  }
  selectedFileName.value = f.name;
  selectedFileSize.value = fileSizeLabel(f.size);
  const probed = await probeDuration(f);
  if (probed) duration.value = Math.max(1, Math.round(probed));
  const fd = new FormData(); fd.append('name', name.value); fd.append('file', f);
  uploading.value = true;
  try {
    // JANGAN set header Content-Type manual untuk FormData — browser/axios
    // mengisinya lengkap dengan boundary. Header manual tanpa boundary membuat
    // PHP gagal parse multipart sehingga file ditolak walau MP3.
    const { data } = await api.post('/backsounds', fd); toast.success(data.message); name.value = ''; await load();
  } catch (err) { toast.error(errorMessage(err)); } finally { uploading.value = false; e.target.value = ''; }
}
async function pick(bs) {
  local.value.url = bs.file_url; local.value.name = bs.name;
  // Pakai duration_sec server bila ada, kalau tidak probe dari file_url.
  if (bs.duration_sec) {
    duration.value = bs.duration_sec;
  } else {
    const probed = await probeUrlDuration(bs.file_url);
    duration.value = probed ? Math.max(1, Math.round(probed)) : 60;
  }
  clampTrim(); sync(); toast.success(`Backsound "${bs.name}" dipilih.`);
}
onMounted(async () => {
  await load();
  // Audio sudah terpilih sebelumnya (mis. edit): probe durasi dari file_url.
  if (local.value.url) {
    const probed = await probeUrlDuration(local.value.url);
    if (probed) { duration.value = Math.max(1, Math.round(probed)); clampTrim(); sync(); }
  }
});
</script>
<template>
  <div class="card p-4 space-y-3">
    <div class="flex items-center gap-2"><Icon name="music" class="h-4 w-4" /><h3 class="font-bold">Backsound</h3><span class="text-xs text-muted">Opsional — dipotong otomatis sesuai durasi video, volume bisa diatur</span></div>
    <div class="grid gap-2 sm:grid-cols-2">
      <input v-model="name" placeholder="Nama backsound, misal: Gamelan Lembut" class="input" />
      <label class="btn btn-secondary cursor-pointer text-center">Pilih file MP3 / WAV<input type="file" accept=".mp3,.wav,.m4a,.ogg,audio/*" class="hidden" @change="upload" /></label>
    </div>
    <p class="text-xs text-muted">Format diterima: MP3, WAV, M4A, OGG — maksimal 20 MB.</p>
    <div v-if="selectedFileName" class="text-xs text-muted break-all">File dipilih: {{ selectedFileName }} ({{ selectedFileSize }})</div>
    <div v-if="uploading" class="flex items-center gap-2 text-sm text-muted"><Spinner class="h-4 w-4" />Mengunggah...</div>
    <div v-if="list.length" class="flex flex-wrap gap-2">
      <button v-for="b in list" :key="b.id" type="button" :class="local.url === b.file_url ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm'" @click="pick(b)">{{ b.name }} <span v-if="b.duration_sec">({{ b.duration_sec }} dtk)</span></button>
    </div>
    <div class="space-y-2">
      <label class="text-sm">Volume: {{ local.volume }}%<input v-model.number="local.volume" type="range" min="0" max="100" class="w-full" @input="sync" /><span class="text-xs text-muted">20% = pelan di bawah narasi, 0 = tanpa backsound</span></label>
      <!-- Trim timeline ala CapCut: tarik handle kiri/kanan.
           :duration = durasi AUDIO penuh (probe / duration_sec).
           :audio-src + :volume = untuk Play Preview segmen terpilih. -->
      <TrimTimeline
        :duration="duration"
        :start="local.trimStart ?? 0"
        :end="local.trimEnd"
        :audio-src="local.url"
        :volume="local.volume"
        @update:start="(v) => { local.trimStart = v; clampTrim(); sync(); }"
        @update:end="(v) => { local.trimEnd = v; clampTrim(); sync(); }"
      />
    </div>
    <p class="text-xs text-muted">Trim di atas memakai durasi AUDIO penuh ({{ duration }} dtk). Di server, backsound dipotong / di-loop agar pas durasi video, lalu dicampur di bawah suara narasi/dialog dengan ffmpeg: <code>volume={{ (local.volume/100).toFixed(2) }}</code>.</p>
  </div>
</template>
