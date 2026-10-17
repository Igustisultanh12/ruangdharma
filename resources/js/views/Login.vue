<template>
  <div class="max-w-md mx-auto card mt-10">
    <h1 class="text-2xl font-bold mb-1">Masuk Ruang Dharma Studio</h1>
    <p class="text-sm text-slate-500 mb-4">Demo: admin@ruangdharma.id / admin123 • user@ruangdharma.id / user123</p>
    <form @submit.prevent="submit" class="space-y-3">
      <input v-model="email" class="input" placeholder="Email" type="email" required />
      <input v-model="password" class="input" placeholder="Password" type="password" required />
      <p v-if="err" class="text-red-600 text-sm">{{ err }}</p>
      <button class="btn-primary w-full">Masuk</button>
    </form>
    <div class="flex gap-2 mt-3 text-sm">
      <button @click="fill('admin@ruangdharma.id','admin123')" class="underline">Isi Admin</button>
      <button @click="fill('user@ruangdharma.id','user123')" class="underline">Isi Pengguna</button>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue'; import { useRouter } from 'vue-router'; import { useAuthStore } from '../stores/auth.js';
const email=ref(''); const password=ref(''); const err=ref(''); const router=useRouter(); const auth=useAuthStore();
function fill(e,p){ email.value=e; password.value=p; }
async function submit(){ err.value=''; try{ await auth.login(email.value, password.value); router.push('/'); }catch(e){ err.value = e.response?.data?.message || 'Login gagal.'; } }
</script>
