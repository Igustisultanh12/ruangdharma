<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Integrasi Muse</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Generate & kelola token Muse dari sini — <b>tanpa edit file .env</b>. Token disimpan terenkripsi di database. Hanya admin yang bisa melihat halaman ini.</p>

    <div v-if="banner" class="card mb-4 !border-2 !border-amber-400 bg-amber-50 dark:bg-amber-950/40">
      <p class="font-bold text-amber-800 dark:text-amber-200">{{ banner.title }}</p>
      <p class="text-sm mt-1">{{ banner.text }}</p>
      <div class="flex gap-2 mt-2 items-center">
        <code class="text-xs break-all bg-white dark:bg-slate-800 px-2 py-1 rounded border flex-1">{{ banner.token }}</code>
        <button @click="copyText(banner.token, 'Token baru')" class="btn-primary btn-sm">Salin</button>
      </div>
      <p class="text-xs mt-2 text-slate-500">Token ini tetap bisa dilihat lagi lewat tombol <b>Tampilkan</b> di bawah (karena tersimpan di database), tapi tetap jangan dibagikan ke sembarang orang.</p>
    </div>
    <p v-if="err" class="alert-error mb-3">{{ err }}</p>
    <p v-if="msg" class="alert-success mb-3">{{ msg }}</p>

    <!-- MUSE_API_TOKEN -->
    <div class="card mb-4">
      <div class="flex items-center justify-between gap-2">
        <h2 class="font-bold text-slate-900 dark:text-white">MUSE_API_TOKEN</h2>
        <span class="badge" :class="config.has_token ? 'badge-success' : 'badge-danger'">{{ config.has_token ? 'Terhubung' : 'Tidak Terhubung' }}</span>
      </div>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Password rahasia agar Muse bisa memanggil API web ini (<code>X-Muse-Token</code>). 64 karakter hex.</p>
      <div class="flex gap-2 mt-3 items-center flex-wrap">
        <code class="text-sm break-all bg-slate-100 dark:bg-slate-700 px-2 py-1.5 rounded border border-slate-200 dark:border-slate-600 flex-1 min-w-[220px]">{{ tokenShown ? (config.muse_api_token || '— belum ada —') : (config.muse_api_token_masked || '— belum ada —') }}</code>
        <button @click="toggleToken" class="btn-secondary btn-sm">{{ tokenShown ? 'Sembunyikan' : 'Tampilkan' }}</button>
        <button @click="copyText(tokenShown ? config.muse_api_token : null, 'Token')" class="btn-secondary btn-sm">Salin</button>
        <button @click="onGenerateToken" class="btn-gold btn-sm">Generate Token Baru</button>
      </div>
      <p class="text-xs mt-2 text-slate-500">Sumber: {{ config.source || '—' }} • Status token: {{ config.has_token ? 'ada' : 'belum ada' }}</p>
    </div>

    <!-- MUSE_WEBHOOK_SECRET -->
    <div class="card mb-4">
      <div class="flex items-center justify-between gap-2">
        <h2 class="font-bold text-slate-900 dark:text-white">MUSE_WEBHOOK_SECRET</h2>
        <span class="badge" :class="config.has_webhook_secret ? 'badge-success' : 'badge-danger'">{{ config.has_webhook_secret ? 'Terhubung' : 'Tidak Terhubung' }}</span>
      </div>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Secret untuk webhook (Muse kirim hasil balik). Harus <b>beda</b> dari MUSE_API_TOKEN.</p>
      <div class="flex gap-2 mt-3 items-center flex-wrap">
        <code class="text-sm break-all bg-slate-100 dark:bg-slate-700 px-2 py-1.5 rounded border border-slate-200 dark:border-slate-600 flex-1 min-w-[220px]">{{ secretShown ? (config.muse_webhook_secret || '— belum ada —') : (config.muse_webhook_secret_masked || '— belum ada —') }}</code>
        <button @click="toggleSecret" class="btn-secondary btn-sm">{{ secretShown ? 'Sembunyikan' : 'Tampilkan' }}</button>
        <button @click="copyText(secretShown ? config.muse_webhook_secret : null, 'Webhook secret')" class="btn-secondary btn-sm">Salin</button>
        <button @click="onGenerateSecret" class="btn-gold btn-sm">Generate Secret Baru</button>
      </div>
      <p class="text-xs mt-2 text-slate-500">Status: {{ config.has_webhook_secret ? 'ada' : 'belum ada' }}</p>
    </div>

    <!-- BASE URL + TIMEOUT -->
    <div class="card mb-4">
      <h2 class="font-bold text-slate-900 dark:text-white mb-3">Pengaturan Koneksi</h2>
      <label class="label">MUSE_BASE_URL <span class="text-slate-400 font-normal">(opsional — isi hanya jika Muse punya endpoint HTTP sendiri, kosongkan untuk generator lokal)</span></label>
      <input v-model="form.base_url" placeholder="https://..." class="input mb-3" />
      <label class="label">MUSE_TIMEOUT (detik, 5–60)</label>
      <input v-model.number="form.timeout" type="number" min="5" max="60" class="input !w-32 mb-3" />
      <div><button @click="onSave" class="btn-primary">Simpan Pengaturan</button></div>
    </div>

    <!-- STATUS -->
    <div class="card mb-4">
      <h2 class="font-bold text-slate-900 dark:text-white mb-2">Status Koneksi</h2>
      <p>Status: <b :class="status?.connected ? 'text-emerald-600' : 'text-red-600'">{{ status?.connected ? 'Terhubung' : 'Tidak Terhubung' }}</b></p>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Terakhir aktif: {{ status?.last_active || '—' }} • Latency: {{ status?.latency_ms }} ms • Antrian: {{ status?.queue_pending }} • Video hari ini: {{ status?.daily_generations }}</p>
      <p class="text-sm text-slate-500 dark:text-slate-400">Sumber token: {{ status?.config_source || config.source || '—' }}</p>
      <p v-if="testResult" class="text-sm mt-2">Hasil tes: {{ testResult.message || JSON.stringify(testResult) }}</p>
      <div class="flex gap-2 mt-3 flex-wrap">
        <button @click="check" class="btn-secondary btn-sm">Periksa Koneksi</button>
        <button @click="onTest" class="btn-secondary btn-sm">Test Koneksi Muse</button>
        <button @click="testGen" class="btn-primary btn-sm">Test Generate via Muse</button>
      </div>
    </div>

    <!-- CURL -->
    <div class="card text-sm">
      <h2 class="font-bold text-slate-900 dark:text-white mb-2">Contoh curl (token ter-mask sebagian)</h2>
      <pre class="bg-slate-100 dark:bg-slate-700 p-3 rounded-xl overflow-auto text-xs">{{ curlExample }}</pre>
      <button @click="copyText(curlFull, 'Perintah curl')" class="btn-secondary btn-sm mt-2">Salin curl</button>
      <p class="mt-2 text-slate-500 dark:text-slate-400">Catatan: untuk menyalin curl lengkap dengan token asli, klik <b>Tampilkan</b> pada MUSE_API_TOKEN dulu. Jangan bagikan curl berisi token asli ke orang lain.</p>
    </div>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';
import api from '../../services/api.js';
import { useAuthStore } from '../../stores/auth.js';
import { getMuseConfig, generateMuseToken, generateMuseWebhookSecret, updateMuseConfig, testMuseConfig, checkMuseStatus } from '../../services/muse.js';
import { logActivity } from '../../services/activity.js';

const auth = useAuthStore();
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
  try {
    await navigator.clipboard.writeText(text);
    msg.value = (label || 'Teks') + ' tersalin.';
  } catch (e) { err.value = 'Gagal menyalin: ' + e.message; }
}
async function load(show = false) {
  err.value = ''; msg.value = '';
  try {
    const data = await getMuseConfig(show);
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
  const res = await Swal.fire({ title: 'Generate token baru?', text: 'Token lama akan langsung TIDAK berlaku.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Generate', cancelButtonText: 'Batal', confirmButtonColor: '#d97706' });
  if (!res.isConfirmed) return;
  err.value = ''; msg.value = ''; banner.value = null;
  try {
    const data = await generateMuseToken();
    banner.value = { title: 'Token baru dibuat!', text: 'Simpan token ini dan berikan ke Muse lewat jalur aman. Token lama sudah tidak berlaku.', token: data.muse_api_token };
    logActivity({ action: 'token', description: 'Generate MUSE_API_TOKEN baru', user: auth.user });
    await load(true); tokenShown.value = false;
  } catch (e) { err.value = e.response?.data?.message || 'Gagal generate token.'; }
}
async function onGenerateSecret() {
  const res = await Swal.fire({ title: 'Generate webhook secret baru?', text: 'Secret lama akan langsung TIDAK berlaku.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Generate', cancelButtonText: 'Batal', confirmButtonColor: '#d97706' });
  if (!res.isConfirmed) return;
  err.value = ''; msg.value = ''; banner.value = null;
  try {
    const data = await generateMuseWebhookSecret();
    banner.value = { title: 'Webhook secret baru dibuat!', text: 'Secret lama sudah tidak berlaku.', token: data.muse_webhook_secret };
    logActivity({ action: 'token', description: 'Generate MUSE_WEBHOOK_SECRET baru', user: auth.user });
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
    Swal.fire({ icon: 'success', title: 'Test generate lokal berhasil', text: data?.result?.judul || data?.keyword || 'OK' });
  } catch (e) { Swal.fire({ icon: 'error', title: 'Test gagal', text: e.response?.data?.message || 'Terjadi kesalahan.' }); }
}
onMounted(() => load(false));
</script>
