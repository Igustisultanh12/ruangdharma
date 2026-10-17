<template>
  <div class="card !p-0 overflow-hidden shadow-xl">
    <div class="bg-gradient-to-r from-violet-700 to-amber-500 px-6 py-6 text-white">
      <div class="flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/20 font-bold text-lg">RD</span>
        <div>
          <h1 class="text-xl font-bold leading-tight">Ruang Dharma Studio</h1>
          <p class="text-xs text-white/80">Generate Naskah, Video, Caption</p>
        </div>
      </div>
    </div>
    <div class="p-6">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">Masuk ke akun</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Demo: admin@ruangdharma.id / admin123 — user@ruangdharma.id / user123</p>
      <form @submit.prevent="submit" class="space-y-3">
        <div>
          <label class="label" for="login-email">Email</label>
          <input id="login-email" v-model="email" class="input" placeholder="nama@email.com" type="email" required autocomplete="email" />
        </div>
        <div>
          <label class="label" for="login-password">Password</label>
          <input id="login-password" v-model="password" class="input" placeholder="Password" type="password" required autocomplete="current-password" />
        </div>
        <p v-if="err" class="alert-error">{{ err }}</p>
        <button class="btn-primary w-full" :disabled="loading">
          <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 3a9 9 0 109 9" /></svg>
          {{ loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>
      <div class="flex gap-2 mt-4">
        <button @click="fill('admin@ruangdharma.id','admin123')" class="btn-secondary btn-sm flex-1">Isi Admin</button>
        <button @click="fill('user@ruangdharma.id','user123')" class="btn-secondary btn-sm flex-1">Isi Pengguna</button>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const email = ref('');
const password = ref('');
const err = ref('');
const loading = ref(false);
const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

function fill(e, p) {
  email.value = e;
  password.value = p;
}
async function submit() {
  err.value = '';
  loading.value = true;
  try {
    await auth.login(email.value, password.value);
    router.push(route.query.redirect || '/');
  } catch (e) {
    err.value = e.response?.data?.message || e.response?.data?.errors?.email?.[0] || 'Login gagal. Periksa email & password.';
  } finally {
    loading.value = false;
  }
}
</script>
