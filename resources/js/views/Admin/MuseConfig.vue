<template>
<div>
  <h1 class="text-2xl font-bold mb-1">🤖 Integrasi Muse</h1>
  <p class="text-sm text-gray-600 mb-4">Generate & kelola token Muse dari sini — <b>tanpa edit file .env</b>. Token disimpan terenkripsi di database. Hanya admin yang bisa melihat halaman ini.</p>

  <div v-if="banner" class="card mb-3 border-2 border-amber-400 bg-amber-50">
    <p class="font-bold">⚠️ {{ banner.title }}</p>
    <p class="text-sm mt-1">{{ banner.text }}</p>
    <div class="flex gap-2 mt-2 items-center">
      <code class="text-xs break-all bg-white px-2 py-1 rounded border flex-1">{{ banner.token }}</code>
      <button @click="copyText(banner.token, 'Token baru')" class="btn-primary">Salin</button>
    </div>
    <p class="text-xs mt-2 text-gray-600">Token ini tetap bisa dilihat lagi lewat tombol <b>Tampilkan</b> di bawah (karena tersimpan di database), tapi tetap jangan dibagikan ke sembarang orang.</p>
  </div>
  <p v-if="err" class="text-red-600 text-sm mb-3">{{ err }}</p>
  <p v-if="msg" class="text-green-700 text-sm mb-3">{{ msg }}</p>

  <!-- MUSE_API_TOKEN -->
  <div class="card mb-3">
    <h2 class="font-bold">MUSE_API_TOKEN</h2>
    <p class="text-xs text-gray-600">Password rahasia agar Muse bisa memanggil API web ini (<code>X-Muse-Token</code>). 64 karakter hex.</p>
    <div class="flex gap-2 mt-2 items-center flex-wrap">
      <code class="text-sm break-all bg-gray-100 px-2 py-1 rounded border flex-1 min-w-[220px]">{{ tokenShown ? (config.muse_api_token || '— belum ada —') : (config.muse_api_token_masked || '— belum ada —') }}</code>
      <button @click="toggleToken" class="btn-primary">{{ tokenShown ? 'Sembunyikan' : 'Tampilkan' }}</button>
      <button @click="copyText(tokenShown ? config.muse_api_token : null, 'Token')" class="btn-primary">Salin</button>
      <button @click="onGenerateToken" class="btn-primary bg-amber-600">Generate Token Baru</button>
    </div>
    <p class="text-xs mt-1 text-gray-500">Sumber: {{ config.source || '—' }} • Status: {{ config.has_token ? '✅ ada' : '❌ belum ada' }}</p>
  </div>

  <!-- MUSE_WEBHOOK_SECRET -->
  <div class="card mb-3">
    <h2 class="font-bold">MUSE_WEBHOOK_SECRET</h2>
    <p class="text-xs text-gray-600">Secret untuk webhook (Muse kirim hasil balik). Harus <b>beda</b> dari MUSE_API_TOKEN.</p>
    <div class="flex gap-2 mt-2 items-center flex-wrap">
      <code class="text-sm break-all bg-gray-100 px-2 py-1 rounded border flex-1 min-w-[220px]">{{ secretShown ? (config.muse_webhook_secret || '— belum ada —') : (config.muse_webhook_secret_masked || '— belum ada —') }}</code>
      <button @click="toggleSecret" class="btn-primary">{{ secretShown ? 'Sembunyikan' : 'Tampilkan' }}</button>
      <button @click="copyText(secretShown ? config.muse_webhook_secret : null, 'Webhook secret')" class="btn-primary">Salin</button>
      <button @click="onGenerateSecret" class="btn-primary bg-amber-600">Generate Secret Baru</button>
    </div>
    <p class="text-xs mt-1 text-gray-500">Status: {{ config.has_webhook_secret ? '✅ ada' : '❌ belum ada' }}</p>
  </div>

  <!-- BASE URL + TIMEOUT -->
  <div class="card mb-3">
    <h2 class="font-bold mb-2">Pengaturan Koneksi</h2>
    <label class="text-sm block mb-1">MUSE_BASE_URL <span class="text-gray-500">(opsional — isi hanya jika Muse punya endpoint HTTP sendiri, kosongkan untuk generator lokal)</span></label>
    <input v-model="form.base_url" placeholder="https://..." class="border rounded px-2 py-1 w-full mb-2" />
    <label class="text-sm block mb-1">MUSE_TIMEOUT (detik, 5–60)</label>
    <input v-model.number="form.timeout" type="number" min="5" max="60" class="border rounded px-2 py-1 w-32 mb-3" />
    <div><button @click="onSave" class="btn-primary">Simpan Pengaturan</button></div>
  </div>

  <!-- STATUS -->
  <div class="card mb-3">
    <h2 class="font-bold mb-1">Status Koneksi</h2>
    <p>Status: <b :class="status?.connected ? 'text-green-600' : 'text-red-600'">{{ status?.connected ? 'Terhubung' : 'Tidak Terhubung' }}</b></p>
    <p class="text-sm">Terakhir aktif: {{ status?.last_active || '—' }} • Latency: {{ status?.latency_ms }} ms • Antrian: {{ status?.queue_pending }} • Video hari ini: {{ status?.daily_generations }}</p>
    <p class="text-sm">Sumber token: {{ status?.config_source || config.source || '—' }}</p>
    <p v-if="testResult" class="text-sm mt-2">Hasil tes: {{ testResult.message || JSON.stringify(testResult) }}</p>
    <div class="flex gap-2 mt-3 flex-wrap">
      <button @click="check" class="btn-primary">Periksa Koneksi</button>
      <button @click="onTest" class="btn-primary">Test Koneksi Muse</button>
      <button @click="testGen" class="btn-primary">Test Generate via Muse</button>
    </div>
  </div>

  <!-- CURL -->
  <div class="card text-sm">
    <h2 class="font-bold mb-1">Contoh curl (token ter-mask sebagian)</h2>
    <pre class="bg-gray-100 p-2 rounded overflow-auto text-xs">{{ curlExample }}</pre>
    <button @click="copyText(curlFull, 'Perintah curl')" class="btn-primary mt-2">Salin curl</button>
    <p class="mt-2 text-gray-600">Catatan: untuk menyalin curl lengkap dengan token asli, klik <b>Tampilkan</b> pada MUSE_API_TOKEN dulu. Jangan bagikan curl berisi token asli ke orang lain.</p>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../services/api.js';
import { getMuseConfig, generateMuseToken, generateMuseWebhookSecret, updateMuseConfig, testMuseConfig, checkMuseStatus } from '../../services/muse.js';

const config = ref({});
const status = ref(null);
const err = ref('');
const msg = ref('');
const banner = ref(null);
const tokenShown = ref(false);
const secretShown = ref(false);
const form = ref({ base_url: '', timeout: 15 });
const testResult = ref(null);

const curlExample = computed(() => {
  const masked = config.value.muse_api_token_masked || '••••••••';
  const base = window.location.origin;
  return `curl -H "X-Muse-Token: ${masked}" ${base}/api/muse/status`;
});
const curlFull = computed(() => {
  const token = config.value.muse_api_token || config.value.muse_api_token_masked || 'TOKEN';
  const base = window.location.origin;
  return `curl -H "X-Muse-Token: ${token}" ${base}/api/muse/status`;
});

async function copyText(text, label) {
  if (!text) { err.value = 'Tidak ada nilai untuk disalin. Klik Tampilkan dulu.'; return; }
  try { await navigator.clipboard.writeText(text); msg.value = (label || 'Teks') + ' tersalin.'; } catch (e) { err.value = 'Gagal menyalin: ' + e.message; }
}
async function load(show = false) {
  err.value = ''; msg.value = '';
  try {
    const data = await getMuseConfig(show);
    // Pertahankan token full jika sudah pernah dimuat & show=false tidak menimpanya dengan null
    if (show || !config.value.muse_api_token) {
      config.value = { ...config.value, ...data };
    } else {
      const { muse_api_token, muse_webhook_secret, ...rest } = data;
      config.value = { ...config.value, ...rest };
    }
    form.value = { base_url: data.muse_base_url || '', timeout: data.muse_timeout || 15 };
    status.value = data.status || status.value;
  } catch (e) { err.value = e.response?.data?.message || 'Gagal memuat konfigurasi Muse.'; }
}
async function toggleToken() {
  if (!tokenShown.value) { await load(true); tokenShown.value = true; }
  else tokenShown.value = false;
}
async function toggleSecret() {
  if (!secretShown.value) { await load(true); secretShown.value = true; }
  else secretShown.value = false;
}
async function onGenerateToken() {
  if (!confirm('Generate token baru? Token lama akan langsung TIDAK berlaku. Lanjut?')) return;
  err.value = ''; msg.value = ''; banner.value = null;
  try {
    const data = await generateMuseToken();
    banner.value = { title: 'Token baru dibuat!', text: 'Simpan token ini dan berikan ke Muse lewat jalur aman. Token lama sudah tidak berlaku.', token: data.muse_api_token };
    await load(true); tokenShown.value = false;
  } catch (e) { err.value = e.response?.data?.message || 'Gagal generate token.'; }
}
async function onGenerateSecret() {
  if (!confirm('Generate webhook secret baru? Secret lama akan langsung TIDAK berlaku. Lanjut?')) return;
  err.value = ''; msg.value = ''; banner.value = null;
  try {
    const data = await generateMuseWebhookSecret();
    banner.value = { title: 'Webhook secret baru dibuat!', text: 'Secret lama sudah tidak berlaku.', token: data.muse_webhook_secret };
    await load(true); secretShown.value = false;
  } catch (e) { err.value = e.response?.data?.message || 'Gagal generate webhook secret.'; }
}
async function onSave() {
  err.value = ''; msg.value = '';
  try {
    await updateMuseConfig({ muse_base_url: form.value.base_url || null, muse_timeout: form.value.timeout });
    msg.value = 'Pengaturan tersimpan.';
    await load(false);
  } catch (e) { err.value = e.response?.data?.errors ? JSON.stringify(e.response.data.errors) : (e.response?.data?.message || 'Gagal menyimpan.'); }
}
async function check() {
  err.value = '';
  try { status.value = await checkMuseStatus(); } catch (e) { err.value = e.response?.data?.message || 'Gagal cek koneksi Muse.'; }
}
async function onTest() {
  err.value = '';
  try { testResult.value = await testMuseConfig(); } catch (e) { err.value = e.response?.data?.message || 'Gagal test koneksi.'; }
}
async function testGen() {
  err.value = '';
  try {
    const { data } = await api.post('/generations', { keyword: 'Rwa Bhineda', durasi: 60, model: 'narasi' });
    alert('Test generate lokal berhasil: ' + (data?.result?.judul || data?.keyword || 'OK'));
  } catch (e) { alert(e.response?.data?.message || 'Test gagal'); }
}
onMounted(() => load(false));
</script>
