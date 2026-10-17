<script setup>
import { ref } from 'vue';
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

async function uploadAudio(e) {
  const f = e.target.files?.[0]; if (!f) return;
  const fd = new FormData(); fd.append('name', name.value || f.name); fd.append('file', f);
  uploading.value = true;
  try {
    const { data } = await api.post('/backsounds', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    backsoundUrl.value = data.data.file_url;
    toast.success('Audio diunggah, sekarang atur volume & potong di bawah lalu Simpan.');
  } catch (err) { toast.error(errorMessage(err)); } finally { uploading.value = false; }
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
      <label class="btn btn-secondary cursor-pointer text-center">Upload Audio (MP3/WAV)<input type="file" accept="audio/*" class="hidden" @change="uploadAudio" /></label>
    </div>
    <div v-if="uploading" class="flex items-center gap-2 text-sm text-muted"><Spinner class="h-4 w-4" />Mengunggah...</div>
    <div v-if="backsoundUrl" class="text-xs text-muted break-all">Audio terpilih: {{ backsoundUrl }}</div>
    <div class="grid gap-3 sm:grid-cols-3">
      <label class="text-sm">Volume: {{ volume }}%<input v-model.number="volume" type="range" min="0" max="100" class="w-full" /><span class="text-xs text-muted">0 = bisu, 20 = pelan, 100 = keras</span></label>
      <label class="text-sm">Mulai (detik)<input v-model.number="trimStart" type="number" min="0" class="input mt-1" /></label>
      <label class="text-sm">Sampai (detik)<input v-model.number="trimEnd" type="number" min="0" placeholder="Kosong = habis" class="input mt-1" /></label>
    </div>
    <div class="flex gap-2">
      <button class="btn btn-primary btn-sm" :disabled="saving" @click="save"><Spinner v-if="saving" class="h-4 w-4" />Simpan & Mix Ulang</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 0">Mute</button>
      <button class="btn btn-ghost btn-sm" @click="volume = 20; trimStart = 0; trimEnd = null">Reset</button>
    </div>
    <p class="text-xs text-muted">Setelah Simpan, Muse akan mix ulang video dengan audio baru (volume {{ (volume/100).toFixed(2) }}, trim {{ trimStart }}s – {{ trimEnd ?? 'habis' }}). Halaman akan refresh otomatis.</p>
  </div>
</template>
