<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Pengaturan</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Kelola konfigurasi studio dari satu tempat.</p>

    <div class="flex gap-1 bg-slate-200/70 dark:bg-slate-800 rounded-xl p-1 w-fit mb-4 overflow-x-auto max-w-full">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key" class="px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap" :class="tab === t.key ? 'bg-white dark:bg-slate-700 shadow text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-slate-300'">{{ t.label }}</button>
    </div>

    <!-- UMUM -->
    <div v-if="tab === 'umum'" class="card max-w-2xl space-y-3">
      <h2 class="font-bold text-slate-900 dark:text-white">Umum</h2>
      <div><label class="label">Nama Aplikasi</label><input v-model="form.app_name" class="input" /></div>
      <div><label class="label">Deskripsi Aplikasi</label><textarea v-model="appSettings.description" class="input" rows="2" placeholder="Deskripsi singkat studio..."></textarea></div>
      <div>
        <label class="label">Logo</label>
        <div class="flex items-center gap-3">
          <img v-if="appSettings.logo" :src="appSettings.logo" class="h-14 w-14 rounded-2xl object-cover border" alt="Logo" />
          <label class="btn-secondary btn-sm cursor-pointer">Upload Logo<input type="file" accept="image/*" class="hidden" @change="onLogo" /></label>
        </div>
      </div>
      <button @click="saveGeneral" class="btn-primary">Simpan Umum</button>
    </div>

    <!-- KUOTA & LIMIT -->
    <div v-if="tab === 'kuota'" class="card max-w-2xl space-y-3">
      <h2 class="font-bold text-slate-900 dark:text-white">Kuota & Limit</h2>
      <div><label class="label">Limit Global Harian (video / pengguna / hari)</label><input v-model.number="form.daily_limit" type="number" min="1" max="100" class="input" /></div>
      <p class="text-xs text-slate-500 dark:text-slate-400">Pengguna dengan limit personal akan memakai limit personalnya, bukan limit global ini.</p>
      <button @click="saveSettings" class="btn-primary">Simpan Kuota</button>
    </div>

    <!-- KATA DIBLOKIR -->
    <div v-if="tab === 'blokir'" class="card max-w-2xl space-y-3">
      <h2 class="font-bold text-slate-900 dark:text-white">Kata Diblokir</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400">Kata kunci yang mengandung salah satu kata ini akan ditolak saat generate.</p>
      <div class="flex gap-2">
        <input v-model="newBlocked" class="input" placeholder="Tambah kata diblokir..." @keyup.enter="addBlocked" />
        <button @click="addBlocked" class="btn-primary">Tambah</button>
      </div>
      <div class="flex flex-wrap gap-2">
        <span v-for="w in blocked" :key="w" class="badge badge-danger !text-sm !py-1 !px-3">{{ w }} <button @click="removeBlocked(w)" class="ml-1 font-bold" aria-label="Hapus kata">×</button></span>
        <span v-if="!blocked.length" class="text-sm text-slate-500">Belum ada kata diblokir.</span>
      </div>
      <button @click="saveSettings" class="btn-primary">Simpan Kata Diblokir</button>
    </div>

    <!-- MUSE -->
    <div v-if="tab === 'muse'" class="card max-w-2xl space-y-3">
      <h2 class="font-bold text-slate-900 dark:text-white">Muse</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400">Token, webhook secret, base URL, dan status koneksi Muse dikelola di halaman khusus agar tidak tercampur pengaturan umum.</p>
      <RouterLink to="/admin/muse-config" class="btn-primary w-fit">Buka Integrasi Muse</RouterLink>
    </div>

    <!-- NOTIFIKASI -->
    <div v-if="tab === 'notifikasi'" class="card max-w-2xl space-y-3">
      <h2 class="font-bold text-slate-900 dark:text-white">Template Notifikasi</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400">Placeholder: {keyword}, {remaining}.</p>
      <div><label class="label">Generate berhasil</label><input v-model="notifTemplates.generate_success" class="input" /></div>
      <div><label class="label">Kuota hampir habis</label><input v-model="notifTemplates.quota_warning" class="input" /></div>
      <div><label class="label">Naskah menunggu review</label><input v-model="notifTemplates.review_needed" class="input" /></div>
      <button @click="saveNotifTemplates" class="btn-primary">Simpan Template</button>
    </div>

    <!-- AUTO GENERATE -->
    <div v-if="tab === 'auto'" class="card max-w-2xl space-y-3">
      <h2 class="font-bold text-slate-900 dark:text-white">Auto Generate Harian</h2>
      <label class="flex items-center gap-3 cursor-pointer">
        <input type="checkbox" v-model="auto.enabled" class="h-5 w-5 accent-violet-600" />
        <span class="font-medium">Aktifkan auto-generate harian</span>
      </label>
      <div v-if="auto.enabled" class="space-y-3">
        <div v-for="i in 3" :key="i"><label class="label">Tema default {{ i }}</label><input v-model="auto.themes[i - 1]" class="input" :placeholder="`Tema ${i} (misal: Canang Sari)`" /></div>
        <div><label class="label">Jam jalan (WIB)</label><input v-model="auto.time" type="time" class="input" /></div>
      </div>
      <div class="alert-warning">
        <p class="font-semibold">Keterangan cron aaPanel</p>
        <p class="mt-1">Auto-generate berjalan lewat scheduler Laravel. Tambahkan cron di aaPanel (Website / Cron) agar scheduler aktif setiap menit:</p>
        <code class="block bg-white dark:bg-slate-800 rounded-lg px-3 py-2 mt-2 text-xs break-all">* * * * * cd /www/wwwroot/ruangdharma && php artisan schedule:run >> /dev/null 2>&1</code>
        <p class="text-xs mt-2">Catatan: job scheduler + endpoint backend untuk auto-generate masih perlu ditambahkan agar jadwal ini benar-benar dieksekusi server.</p>
      </div>
      <button @click="saveAuto" class="btn-primary">Simpan Auto Generate</button>
    </div>

    <!-- KEAMANAN -->
    <div v-if="tab === 'keamanan'" class="card max-w-2xl space-y-3">
      <h2 class="font-bold text-slate-900 dark:text-white">Keamanan</h2>
      <div><label class="label">Minimal panjang password</label><input v-model.number="security.password_min" type="number" min="6" max="32" class="input" /></div>
      <div><label class="label">Session timeout (menit)</label><input v-model.number="security.session_timeout" type="number" min="5" max="1440" class="input" /></div>
      <p class="text-xs text-slate-500 dark:text-slate-400">Disimpan di perangkat ini sampai backend menyediakan endpoint pengaturan keamanan.</p>
      <button @click="saveSecurity" class="btn-primary">Simpan Keamanan</button>
    </div>

    <p v-if="msg" class="alert-success mt-4 max-w-2xl">{{ msg }}</p>
  </div>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import api from '../../services/api.js';
import { useAuthStore } from '../../stores/auth.js';
import { getAutoGenerate, saveAutoGenerate, getNotificationTemplates, saveNotificationTemplates, getSecuritySettings, saveSecuritySettings, getAppSettings, saveAppSettings } from '../../services/preferences.js';
import { logActivity } from '../../services/activity.js';

const auth = useAuthStore();
const tab = ref('umum');
const tabs = [
  { key: 'umum', label: 'Umum' },
  { key: 'kuota', label: 'Kuota & Limit' },
  { key: 'blokir', label: 'Kata Diblokir' },
  { key: 'muse', label: 'Muse' },
  { key: 'notifikasi', label: 'Notifikasi' },
  { key: 'auto', label: 'Auto Generate' },
  { key: 'keamanan', label: 'Keamanan' },
];
const form = ref({ daily_limit: 5, app_name: '' });
const blocked = ref([]);
const newBlocked = ref('');
const msg = ref('');
const auto = ref(getAutoGenerate());
const notifTemplates = ref(getNotificationTemplates());
const security = ref(getSecuritySettings());
const appSettings = ref(getAppSettings());

function toast(title) {
  msg.value = title;
  Swal.fire({ icon: 'success', title, timer: 1400, showConfirmButton: false });
}
async function saveSettings() {
  try {
    const { data } = await api.put('/admin/settings', { ...form.value, blocked_keywords: blocked.value });
    logActivity({ action: 'settings', description: 'Memperbarui pengaturan (kuota / kata diblokir)', user: auth.user });
    toast(data.message || 'Pengaturan tersimpan.');
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal menyimpan', text: e.response?.data?.message || 'Terjadi kesalahan.' });
  }
}
async function saveGeneral() {
  form.value.app_name = appSettings.value.app_name;
  saveAppSettings(appSettings.value);
  await saveSettings();
}
function addBlocked() {
  const w = newBlocked.value.trim();
  if (w && !blocked.value.includes(w)) blocked.value.push(w);
  newBlocked.value = '';
}
function removeBlocked(w) {
  blocked.value = blocked.value.filter((x) => x !== w);
}
function saveNotifTemplates() {
  saveNotificationTemplates(notifTemplates.value);
  logActivity({ action: 'settings', description: 'Memperbarui template notifikasi', user: auth.user });
  toast('Template notifikasi tersimpan.');
}
function saveAuto() {
  saveAutoGenerate(auto.value);
  logActivity({ action: 'settings', description: `Memperbarui auto-generate (aktif: ${auto.value.enabled ? 'ya' : 'tidak'})`, user: auth.user });
  toast('Auto generate tersimpan.');
}
function saveSecurity() {
  saveSecuritySettings(security.value);
  logActivity({ action: 'settings', description: 'Memperbarui pengaturan keamanan', user: auth.user });
  toast('Pengaturan keamanan tersimpan.');
}
function onLogo(e) {
  const f = e.target.files?.[0];
  if (!f) return;
  if (f.size > 2 * 1024 * 1024) {
    Swal.fire({ icon: 'error', title: 'File terlalu besar', text: 'Maksimal 2MB.' });
    return;
  }
  const reader = new FileReader();
  reader.onload = () => { appSettings.value.logo = reader.result; };
  reader.readAsDataURL(f);
}
onMounted(async () => {
  try {
    const { data } = await api.get('/admin/settings');
    form.value = { daily_limit: data.daily_limit, app_name: data.app_name };
    blocked.value = data.blocked_keywords || [];
    if (!appSettings.value.app_name) appSettings.value.app_name = data.app_name || '';
  } catch { /* abaikan */ }
});
</script>
