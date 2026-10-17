<template>
  <div class="min-h-screen">
    <header class="bg-violet-700 text-white px-4 py-3 flex justify-between items-center">
      <RouterLink to="/" class="font-bold text-lg">🛕 Ruang Dharma Studio</RouterLink>
      <nav class="flex gap-3 text-sm items-center">
        <template v-if="auth.user">
          <RouterLink to="/generate">Generate</RouterLink>
          <RouterLink to="/history">Riwayat</RouterLink>
          <RouterLink v-if="auth.user.role==='admin'" to="/admin">Admin</RouterLink>
          <RouterLink to="/profile">{{ auth.user.name }}</RouterLink>
          <button @click="logout" class="bg-white text-violet-700 px-3 py-1 rounded">Logout</button>
        </template>
        <RouterLink v-else to="/login">Login</RouterLink>
      </nav>
    </header>
    <main class="max-w-6xl mx-auto p-4"><RouterView /></main>
  </div>
</template>
<script setup>
import { useAuthStore } from './stores/auth.js';
import { useRouter } from 'vue-router';
const auth = useAuthStore(); const router = useRouter();
auth.loadFromStorage();
async function logout(){ await auth.logout(); router.push('/login'); }
</script>
