<template>
  <div class="max-w-2xl space-y-4">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Profil Saya</h1>

    <div class="card">
      <h2 class="font-bold text-slate-900 dark:text-white mb-3">Data Profil</h2>
      <div class="flex items-center gap-4 mb-4">
        <img v-if="avatarPreview" :src="avatarPreview" class="h-20 w-20 rounded-full object-cover border border-slate-200 dark:border-slate-600" alt="Pratinjau avatar" />
        <span v-else class="flex h-20 w-20 items-center justify-center rounded-full bg-violet-600 text-white text-2xl font-bold">{{ initials }}</span>
        <div>
          <label class="btn-secondary btn-sm cursor-pointer">
            Pilih Foto
            <input type="file" accept="image/*" class="hidden" @change="onAvatarFile" />
          </label>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Format gambar, maks 2MB. Foto tersimpan saat Simpan Profil.</p>
        </div>
      </div>
      <div class="space-y-3">
        <div><label class="label" for="profile-name">Nama</label><input id="profile-name" v-model="form.name" class="input" /></div>
        <div><label class="label" for="profile-email">Email</label><input id="profile-email" v-model="form.email" type="email" class="input" /></div>
        <button @click="save" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan Profil' }}</button>
        <p v-if="msg" class="alert-success">{{ msg }}</p>
        <p v-if="err" class="alert-error">{{ err }}</p>
      </div>
    </div>

    <div class="card">
      <h2 class="font-bold text-slate-900 dark:text-white mb-1">Default Generate</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">Dipakai otomatis saat membuka halaman Generate (tersimpan di perangkat ini).</p>
      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="label" for="def-durasi">Default Durasi</label>
          <select id="def-durasi" v-model.number="defaults.durasi" class="input">
            <option :value="30">30 detik</option>
            <option :value="60">60 detik</option>
            <option :value="90">90 detik</option>
          </select>
        </div>
        <div>
          <label class="label" for="def-model">Default Model</label>
          <select id="def-model" v-model="defaults.model" class="input">
            <option value="narasi">Narasi</option>
            <option value="ngobrol">Ngobrol</option>
            <option value="gabungan">Gabungan</option>
          </select>
        </div>
      </div>
      <button @click="saveDefaults" class="btn-primary mt-3">Simpan Default</button>
      <p v-if="msgDefaults" class="alert-success mt-3">{{ msgDefaults }}</p>
    </div>

    <div class="card">
      <h2 class="font-bold text-slate-900 dark:text-white mb-3">Ubah Password</h2>
      <div class="space-y-3">
        <input v-model="pw.current_password" type="password" class="input" placeholder="Password lama" autocomplete="current-password" />
        <input v-model="pw.password" type="password" class="input" placeholder="Password baru (min 6 karakter)" autocomplete="new-password" />
        <input v-model="pw.password_confirmation" type="password" class="input" placeholder="Konfirmasi password baru" autocomplete="new-password" />
        <button @click="savePw" class="btn-primary" :disabled="savingPw">{{ savingPw ? 'Menyimpan...' : 'Ubah Password' }}</button>
        <p v-if="msgPw" class="text-sm" :class="pwError ? 'text-red-600' : 'text-emerald-600'">{{ msgPw }}</p>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import api from '../services/api.js';
import { useAuthStore } from '../stores/auth.js';
import { getGenerateDefaults, saveGenerateDefaults } from '../services/preferences.js';
import { logActivity } from '../services/activity.js';

const auth = useAuthStore();
const form = ref({ name: '', email: '' });
const avatarFile = ref(null);
const avatarPreview = ref('');
const pw = ref({ current_password: '', password: '', password_confirmation: '' });
const msg = ref('');
const err = ref('');
const msgPw = ref('');
const pwError = ref(false);
const saving = ref(false);
const savingPw = ref(false);
const defaults = ref(getGenerateDefaults());
const msgDefaults = ref('');

const initials = computed(() => {
  const name = (form.value.name || auth.user?.name || '?').trim();
  return name.split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
});

function onAvatarFile(e) {
  const f = e.target.files?.[0];
  if (!f) return;
  if (f.size > 2 * 1024 * 1024) {
    Swal.fire({ icon: 'error', title: 'File terlalu besar', text: 'Maksimal 2MB.' });
    return;
  }
  avatarFile.value = f;
  avatarPreview.value = URL.createObjectURL(f);
}

onMounted(async () => {
  try {
    const { data } = await api.get('/profile');
    form.value = { name: data.name, email: data.email };
    if (data.avatar) avatarPreview.value = data.avatar;
  } catch {
    form.value = { name: auth.user?.name || '', email: auth.user?.email || '' };
  }
});

async function save() {
  msg.value = '';
  err.value = '';
  saving.value = true;
  try {
    // Avatar dikirim sebagai file (backend memvalidasi image). Pakai POST +
    // _method=PUT agar multipart terbaca (PHP tidak mem-parsing body PUT multipart).
    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('name', form.value.name);
    fd.append('email', form.value.email);
    if (avatarFile.value) fd.append('avatar', avatarFile.value);
    const { data } = await api.post('/profile', fd);
    auth.user = data.user;
    auth.persist();
    msg.value = data.message || 'Profil berhasil diperbarui.';
    logActivity({ action: 'update', description: 'Memperbarui profil', user: auth.user });
  } catch (e) {
    err.value = e.response?.data?.message || e.response?.data?.errors?.email?.[0] || 'Gagal menyimpan profil.';
  } finally {
    saving.value = false;
  }
}
function saveDefaults() {
  saveGenerateDefaults(defaults.value);
  msgDefaults.value = 'Default generate tersimpan. Halaman Generate akan memakai durasi & model ini.';
}
async function savePw() {
  msgPw.value = '';
  pwError.value = false;
  if (pw.value.password !== pw.value.password_confirmation) {
    pwError.value = true;
    msgPw.value = 'Konfirmasi password tidak sama.';
    return;
  }
  savingPw.value = true;
  try {
    const { data } = await api.post('/profile/password', pw.value);
    msgPw.value = data.message || 'Password berhasil diubah.';
    pw.value = { current_password: '', password: '', password_confirmation: '' };
  } catch (e) {
    pwError.value = true;
    msgPw.value = e.response?.data?.message || 'Gagal ubah password.';
  } finally {
    savingPw.value = false;
  }
}
</script>
