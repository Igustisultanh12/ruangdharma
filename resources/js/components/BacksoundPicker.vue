<script setup>
import { ref, onMounted } from 'vue';
import api, { errorMessage } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';

const props = defineProps({ modelValue: { type: Object, default: () => ({ url: '', volume: 20, trimStart: 0, trimEnd: null }) } });
const emit = defineEmits(['update:modelValue']);
const list = ref([]);
const loading = ref(false);
const uploading = ref(false);
const name = ref('');
const file = ref(null);
const local = ref({ ...props.modelValue });

function sync() { emit('update:modelValue', { ...local.value }); }
async function load() {
  loading.value = true;
  try { const { data } = await api.get('/backsounds'); list.value = data.data?.data || data.data || []; } catch {}
  finally { loading.value = false; }
}
async function upload(e) {
  const f = e.target.files?.[0]; if (!f) return;
  if (!name.value.trim()) { toast.error('Isi nama backsound dulu.'); return; }
  const fd = new FormData(); fd.append('name', name.value); fd.append('file', f);
  uploading.value = true;
  try { const { data } = await api.post('/backsounds', fd, { headers: { 'Content-Type': 'multipart/form-data' } }); toast.success(data.message); name.value = ''; await load(); } catch (err) { toast.error(errorMessage(err)); } finally { uploading.value = false; }
}
function pick(bs) { local.value.url = bs.file_url; local.value.name = bs.name; sync(); toast.success(`Backsound "${bs.name}" dipilih.`); }
onMounted(load);
</script>
<template>
  <div class="card p-4 space-y-3">
    <div class="flex items-center gap-2"><Icon name="music" class="h-4 w-4" /><h3 class="font-bold">Backsound</h3><span class="text-xs text-muted">Opsional — dipotong otomatis sesuai durasi video, volume bisa diatur</span></div>
    <div class="grid gap-2 sm:grid-cols-2">
      <input v-model="name" placeholder="Nama backsound, misal: Gamelan Lembut" class="input" />
      <label class="btn btn-secondary cursor-pointer text-center">Pilih file MP3 / WAV<input type="file" accept="audio/*" class="hidden" @change="upload" /></label>
    </div>
    <div v-if="uploading" class="flex items-center gap-2 text-sm text-muted"><Spinner class="h-4 w-4" />Mengunggah...</div>
    <div v-if="list.length" class="flex flex-wrap gap-2">
      <button v-for="b in list" :key="b.id" type="button" :class="local.url === b.file_url ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm'" @click="pick(b)">{{ b.name }} <span v-if="b.duration_sec">({{ b.duration_sec }} dtk)</span></button>
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
      <label class="text-sm">Volume: {{ local.volume }}%<input v-model.number="local.volume" type="range" min="0" max="100" class="w-full" @input="sync" /><span class="text-xs text-muted">20% = pelan di bawah narasi, 0 = tanpa backsound</span></label>
      <label class="text-sm">Mulai dari (detik)<input v-model.number="local.trimStart" type="number" min="0" class="input mt-1" @input="sync" /></label>
      <label class="text-sm">Sampai (detik, kosong = habis video)<input v-model.number="local.trimEnd" type="number" min="0" placeholder="Kosong = habis" class="input mt-1" @input="sync" /></label>
    </div>
    <p class="text-xs text-muted">Di server, backsound dipotong / di-loop agar pas durasi video ({{ '60' }} dtk), lalu dicampur di bawah suara narasi/dialog dengan ffmpeg: <code>volume={{ (local.volume/100).toFixed(2) }}</code>.</p>
  </div>
</template>
