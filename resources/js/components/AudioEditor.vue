<script setup>
import { ref, computed } from 'vue';
import api, { errorMessage } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';

const props = defineProps({ generation: { type: Object, required: true } });
const emit = defineEmits(['updated']);
const volume = ref(props.generation.backsound_volume ?? 20);
const trimStart = ref(props.generation.backsound_trim_start ?? 0);
const trimEnd = ref(props.generation.backsound_trim_end ?? null);
const backsoundUrl = ref(props.generation.backsound_url || '');
const uploading = ref(false);
const saving = ref(false);
const file = ref(null);
const name = ref('');
const selectedFileName = ref('');
const selectedFileSize = ref('');
const ACCEPTED_EXTS = ['mp3', 'wav', 'm4a', 'ogg'];

// Durasi acuan trim = durasi video (generation), fallback 60 detik.
const maxDuration = computed(() => Number(props.generation.durasi ?? props.generation.duration ?? 60) || 60);
const effEnd = computed(() => trimEnd.value ?? maxDuration.value);

function fmt(s) {
  if (s == null || isNaN(s)) return '0:00';
  s = Math.max(0, Math.round(Number(s)));
  return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
}
function clampTrim() {
  if (trimStart.value < 0) trimStart.value = 0;
  if (trimStart.value > maxDuration.value) trimStart.value = maxDuration.value;
  if (trimEnd.value != null) {
    if (trimEnd.value < 0) trimEnd.value = 0;
    if (trimEnd.value > maxDuration.value) trimEnd.value = maxDuration.value;
    if (trimEnd.value < trimStart.value) trimEnd.value = trimStart.value;
  }
  if (trimStart.value > effEnd.value) trimStart.value = effEnd.value;
}
function onTrimStartInput() { clampTrim(); }
function onTrimEndInput() {
  // Slider akhir: kalau digeser sampai durasi penuh, anggap habis (null).
  if (trimEnd.value != null && Number(trimEnd.value) >= maxDuration.value) trimEnd.value = null;
  clampTrim();
}
function onTrimEndSlider(e) {
  const v = Number(e.target.value);
  trimEnd.value = v >= maxDuration.value ? null : v;
  clampTrim();
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

async function uploadAudio(e) {
  const f = e.target.files?.[0]; if (!f) return;
  if (!validExt(f.name)) {
    toast.error('Format file tidak didukung. Gunakan MP3, WAV, M4A, atau OGG.');
    e.target.value = '';
    selectedFileName.value = ''; selectedFileSize.value = '';
    return;
  }
  selectedFileName.value = f.name;
  selectedFileSize.value = fileSizeLabel(f.size);
  const fd = new FormData(); fd.append('name', name.value || f.name); fd.append('file', f);
  uploading.value = true;
  try {
    // JANGAN set header Content-Type manual untuk FormData — browser/axios
    // mengisinya lengkap dengan boundary. Header manual tanpa boundary membuat
    // PHP gagal parse multipart sehingga file ditolak walau MP3.
    const { data } = await api.post('/backsounds', fd);
    backsoundUrl.value = data.data.file_url;
    toast.success('Audio diunggah, sekarang atur volume & potong di bawah lalu Simpan.');
  } catch (err) { toast.error(errorMessage(err)); } finally { uploading.value = false; e.target.value = ''; }
}
async function save() {
  saving.value = true;
  try {
    const { data } = await api.put(`/generations/${props.generation.id}/backsound`, { backsound_url: backsoundUrl.value || null, backsound_volume: volume.value, backsound_trim_start: trimStart.value, backsound_trim_end: trimEnd.value });
    toast.success(data.message || 'Audio disimpan. Video akan di-mix ulang oleh Muse.');
    emit('updated');
  } catch (err) { toast.error(errorMessage(err)); } finally { saving.value = false; }
}
</script>
<template>
  <div class="card p-4 space-y-3">
    <div class="flex items-center gap-2"><Icon name="music" class="h-4 w-4" /><h3 class="font-bold">Editor Audio</h3><span class="text-xs text-muted">Upload audio, atur keras & potong — seperti editor online</span></div>
    <div class="grid gap-2 sm:grid-cols-2">
      <input v-model="name" placeholder="Nama audio (opsional)" class="input" />
      <label class="btn btn-secondary cursor-pointer text-center">Upload Audio (MP3/WAV)<input type="file" accept=".mp3,.wav,.m4a,.ogg,audio/*" class="hidden" @change="uploadAudio" /></label>
    </div>
    <p class="text-xs text-muted">Format diterima: MP3, WAV, M4A, OGG — maksimal 20 MB.</p>
    <div v-if="selectedFileName" class="text-xs text-muted break-all">File dipilih: {{ selectedFileName }} ({{ selectedFileSize }})</div>
    <div v-if="uploading" class="flex items-center gap-2 text-sm text-muted"><Spinner class="h-4 w-4" />Mengunggah...</div>
    <div v-if="backsoundUrl" class="text-xs text-muted break-all">Audio terpilih: {{ backsoundUrl }}</div>
    <div class="space-y-2">
      <label class="text-sm">Volume: {{ volume }}%<input v-model.number="volume" type="range" min="0" max="100" class="w-full" /><span class="text-xs text-muted">0 = bisu, 20 = pelan, 100 = keras</span></label>
      <!-- Timeline trim: geser slider, area terpilih di-highlight -->
      <div>
        <div class="flex justify-between text-xs text-muted"><span>Trim: {{ fmt(trimStart) }} — {{ fmt(effEnd) }} / {{ fmt(maxDuration) }}</span><span>Geser slider untuk potong</span></div>
        <div class="relative h-2 mt-1 rounded bg-black/10 overflow-hidden">
          <div class="absolute top-0 bottom-0 bg-violet-500" :style="{ left: (trimStart / maxDuration * 100) + '%', width: ((effEnd - trimStart) / maxDuration * 100) + '%' }"></div>
        </div>
        <label class="text-sm block mt-2">Mulai (geser)<input :value="trimStart" type="range" min="0" :max="maxDuration" step="0.5" class="w-full" @input="e => { trimStart = Number(e.target.value); onTrimStartInput(); }" /></label>
        <label class="text-sm block mt-1">Sampai (geser, ujung = habis)<input :value="effEnd" type="range" min="0" :max="maxDuration" step="0.5" class="w-full" @input="onTrimEndSlider" /></label>
        <div class="grid grid-cols-2 gap-2 mt-1">
          <label class="text-xs text-muted">Mulai (detik)<input v-model.number="trimStart" type="number" min="0" :max="maxDuration" step="0.5" class="input mt-1" @change="onTrimStartInput" /></label>
          <label class="text-xs text-muted">Sampai (detik, kosong = habis)<input v-model.number="trimEnd" type="number" min="0" :max="maxDuration" step="0.5" placeholder="Kosong = habis" class="input mt-1" @change="onTrimEndInput" /></label>
        </div>
      </div>
    </div>
    <div class="flex gap-2">
      <button class="btn btn-primary btn-sm" :disabled="saving" @click="save"><Spinner v-if="saving" class="h-4 w-4" />Simpan & Mix Ulang</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 0">Mute</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 20; trimStart = 0; trimEnd = null">Reset</button>
    </div>
    <p class="text-xs text-muted">Setelah Simpan, Muse akan mix ulang video dengan audio baru (volume {{ (volume/100).toFixed(2) }}, trim {{ trimStart }}s – {{ trimEnd ?? 'habis' }}). Halaman akan refresh otomatis.</p>
  </div>
</template>
