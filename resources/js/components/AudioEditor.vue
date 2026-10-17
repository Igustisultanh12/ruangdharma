<script setup>
import { ref, computed } from 'vue';
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

// Durasi acuan trim = durasi video (generation), fallback 60 detik.
const maxDuration = computed(() => Number(props.generation.durasi ?? props.generation.duration ?? 60) || 60);

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
    // Konsistensi: kalau akhir == durasi penuh, kirim null (= habis).
    const endToSend = (trimEnd.value != null && Number(trimEnd.value) >= maxDuration.value) ? null : trimEnd.value;
    const { data } = await api.put(`/generations/${props.generation.id}/backsound`, { backsound_url: backsoundUrl.value || null, backsound_volume: volume.value, backsound_trim_start: trimStart.value, backsound_trim_end: endToSend });
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
      <!-- Trim timeline ala CapCut: tarik handle kiri/kanan -->
      <TrimTimeline
        :duration="maxDuration"
        :start="trimStart"
        :end="trimEnd"
        @update:start="(v) => { trimStart = v; }"
        @update:end="(v) => { trimEnd = v; }"
      />
    </div>
    <div class="flex gap-2">
      <button class="btn btn-primary btn-sm" :disabled="saving" @click="save"><Spinner v-if="saving" class="h-4 w-4" />Simpan & Mix Ulang</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 0">Mute</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 20; trimStart = 0; trimEnd = null">Reset</button>
    </div>
    <p class="text-xs text-muted">Setelah Simpan, Muse akan mix ulang video dengan audio baru (volume {{ (volume/100).toFixed(2) }}, trim {{ trimStart }}s – {{ trimEnd ?? 'habis' }}). Halaman akan refresh otomatis.</p>
  </div>
</template>
