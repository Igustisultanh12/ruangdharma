<template><div><h1 class="text-2xl font-bold mb-3">🤖 Koneksi Agen Muse</h1>
<div class="card mb-3"><p>Status: <b :class="status?.connected ? 'text-green-600' : 'text-red-600'">{{ status?.connected ? 'Terhubung' : 'Tidak Terhubung' }}</b></p><p>Terakhir aktif: {{ status?.last_active }}</p><p>Latency: {{ status?.latency_ms }} ms • Antrian: {{ status?.queue_pending }} • Video hari ini: {{ status?.daily_generations }}</p><p v-if="err" class="text-red-600">{{ err }}</p><div class="flex gap-2 mt-3"><button @click="check" class="btn-primary">Periksa Koneksi</button><button @click="testGen" class="btn-primary">Test Generate via Muse</button></div></div>
<div class="card text-sm"><h2 class="font-bold mb-1">Cara Integrasi</h2><p>Lihat <code>docs/MUSE_INTEGRATION.md</code>. Endpoint: <code>GET /api/muse/status</code>, <code>POST /api/muse/generate</code> (header <code>X-Muse-Token</code>), <code>GET /api/muse/jobs/{id}</code>, <code>POST /api/muse/webhook</code>.</p><p class="mt-2">Token di <code>.env</code>: <code>MUSE_API_TOKEN</code>. Test via curl ada di README.</p></div>
</div></template>
<script setup>
import { ref, onMounted } from 'vue'; import { checkMuseStatus } from '../../services/muse.js'; import api from '../../services/api.js';
const status=ref(null); const err=ref('');
async function check(){ err.value=''; try{ status.value = await checkMuseStatus(); }catch(e){ err.value = e.response?.data?.message || 'Gagal cek koneksi Muse. Pastikan token di .env benar.'; } }
async function testGen(){ err.value=''; try{ const {data}=await api.post('/muse/generate', {}, {headers:{'X-Muse-Token':'test'}}); }catch(e){} // test via backend lokal: pakai endpoint admin generate contoh
  try{ const {data}=await api.post('/generations', {keyword:'Rwa Bhineda', durasi:60, model:'narasi'}); alert('Test generate lokal berhasil: '+data.result.judul); }catch(e){ alert(e.response?.data?.message||'Test gagal'); }
}
onMounted(check);
</script>
