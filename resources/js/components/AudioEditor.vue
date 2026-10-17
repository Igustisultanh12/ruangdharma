<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import api, { errorMessage } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import TrimTimeline from '@/components/TrimTimeline.vue';

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

// Durasi video (acuan server untuk memotong/loop), fallback 60 detik.
const videoDuration = computed(() => Number(props.generation.durasi ?? props.generation.duration ?? 60) || 60);
// Durasi AUDIO PENUH (dari probe metadata) — ini yang dipakai TrimTimeline,
// supaya segmen bisa dipilih dari tengah lagu yang jauh lebih panjang
// dari video. Selama belum ter-probe, fallback ke durasi video.
const audioDuration = ref(null);
const trimDuration = computed(() => {
  const a = Number(audioDuration.value);
  return (audioDuration.value != null && isFinite(a) && a > 0) ? a : videoDuration.value;
});

function fmt(s) {
  if (s == null || isNaN(s)) return '0:00';
  s = Math.max(0, Math.round(Number(s)));
  return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
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

// Probe durasi audio dari URL (backsoundUrl): baca metadata Audio element.
// Mengembalikan durasi detik (number) atau null kalau gagal.
function probeAudioDuration(url) {
  return new Promise((resolve) => {
    if (!url) { resolve(null); return; }
    try {
      const a = new Audio();
      a.preload = 'metadata';
      const done = (v) => { resolve(v); };
      const t = setTimeout(() => done(null), 10000);
      a.onloadedmetadata = () => { clearTimeout(t); done(a.duration && isFinite(a.duration) ? a.duration : null); };
      a.onerror = () => { clearTimeout(t); done(null); };
      a.src = url;
    } catch { resolve(null); }
  });
}
// Probe durasi audio dari File yang baru dipilih (object URL lokal).
function probeFileDuration(f) {
  return new Promise((resolve) => {
    try {
      const url = URL.createObjectURL(f);
      const a = new Audio();
      a.preload = 'metadata';
      a.onloadedmetadata = () => { URL.revokeObjectURL(url); resolve(a.duration && isFinite(a.duration) ? a.duration : null); };
      a.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
      a.src = url;
    } catch { resolve(null); }
  });
}
function clampTrim() {
  const total = trimDuration.value;
  if (trimStart.value < 0) trimStart.value = 0;
  if (trimStart.value > Math.max(0, total - 0.5)) trimStart.value = Math.max(0, total - 0.5);
  if (trimEnd.value != null) {
    if (trimEnd.value > total) trimEnd.value = null; // = habis (ujung audio)
    else if (trimEnd.value < trimStart.value + 0.5) trimEnd.value = Math.min(total, trimStart.value + 0.5);
  }
}
async function applyProbedDuration(probed) {
  if (probed && isFinite(probed) && probed > 0) {
    audioDuration.value = probed;
    clampTrim();
  }
}

// backsoundUrl berubah (audio lama dari server / selesai upload) ->
// probe durasi audio asli dari URL tersebut.
watch(backsoundUrl, async (url) => {
  if (!url) { audioDuration.value = null; return; }
  await applyProbedDuration(await probeAudioDuration(url));
});
onMounted(async () => {
  // Durasi dari server bila ada (field backsound), kalau tidak probe URL.
  const serverDur = Number(props.generation.backsound_duration_sec ?? props.generation.backsound_duration ?? NaN);
  if (serverDur && isFinite(serverDur) && serverDur > 0) {
    audioDuration.value = serverDur;
    clampTrim();
  } else if (backsoundUrl.value) {
    await applyProbedDuration(await probeAudioDuration(backsoundUrl.value));
  }
});

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
  // Probe durasi file baru yang di-upload (bukan hanya dari server).
  const probedFile = await probeFileDuration(f);
  if (probedFile) { audioDuration.value = probedFile; clampTrim(); }
  const fd = new FormData(); fd.append('name', name.value || f.name); fd.append('file', f);
  uploading.value = true;
  try {
    // JANGAN set header Content-Type manual untuk FormData — browser/axios
    // mengisinya lengkap dengan boundary. Header manual tanpa boundary membuat
    // PHP gagal parse multipart sehingga file ditolak walau MP3.
    const { data } = await api.post('/backsounds', fd);
    backsoundUrl.value = data.data.file_url;
    // Watch backsoundUrl di atas juga akan mem-probe URL server ini.
    toast.success('Audio diunggah, sekarang atur volume & potong di bawah lalu Simpan.');
  } catch (err) { toast.error(errorMessage(err)); } finally { uploading.value = false; e.target.value = ''; }
}
async function save() {
  saving.value = true;
  try {
    // Konsistensi: kalau akhir == durasi audio penuh, kirim null (= habis).
    // Payload API TIDAK berubah: trimStart/trimEnd = offset ke dalam file audio.
    const endToSend = (trimEnd.value != null && Number(trimEnd.value) >= trimDuration.value) ? null : trimEnd.value;
    const { data } = await api.put(`/generations/${props.generation.id}/backsound`, { backsound_url: backsoundUrl.value || null, backsound_volume: volume.value, backsound_trim_start: trimStart.value, backsound_trim_end: endToSend });
    toast.success(data.message || 'Audio disimpan. Server langsung mix audio ke video.');
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
      <!-- Trim timeline ala CapCut: tarik handle kiri/kanan.
           :duration = durasi AUDIO penuh (probe), bukan durasi video.
           :audio-src + :volume = untuk Play Preview segmen terpilih. -->
      <TrimTimeline
        :duration="trimDuration"
        :start="trimStart"
        :end="trimEnd"
        :audio-src="backsoundUrl"
        :volume="volume"
        @update:start="(v) => { trimStart = v; }"
        @update:end="(v) => { trimEnd = v; }"
      />
    </div>
    <div class="flex gap-2">
      <button class="btn btn-primary btn-sm" :disabled="saving" @click="save"><Spinner v-if="saving" class="h-4 w-4" />Simpan & Mix Ulang</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 0">Mute</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 20; trimStart = 0; trimEnd = null">Reset</button>
    </div>
    <p class="text-xs text-muted">Setelah Simpan, server langsung mix audio ke video (volume {{ (volume/100).toFixed(2) }}, trim {{ trimStart }}s – {{ trimEnd ?? 'habis' }} — offset dalam file audio; server memotong/loop sesuai durasi video {{ fmt(videoDuration) }}). Halaman akan refresh otomatis.</p>
  </div>
</template>
