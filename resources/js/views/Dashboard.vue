<template>
  <div>
    <h1 class="text-2xl font-bold mb-2">Halo, {{ auth.user?.name }} 🙏</h1>
    <p class="mb-4">Sisa kuota hari ini: <b>{{ quota.remaining }} / {{ quota.limit }}</b> video</p>
    <div class="grid md:grid-cols-3 gap-4">
      <RouterLink to="/generate" class="card">🎬 <b>Generate Video Baru</b><p class="text-sm">Kata kunci + durasi + model → naskah + caption</p></RouterLink>
      <RouterLink to="/history" class="card">📜 <b>Riwayat Saya</b><p class="text-sm">Lihat hasil generate sebelumnya</p></RouterLink>
      <RouterLink to="/profile" class="card">👤 <b>Edit Profil</b><p class="text-sm">Nama, foto, email, password</p></RouterLink>
    </div>
  </div>
</template>
<script setup>
import { onMounted, ref } from 'vue'; import { useAuthStore } from '../stores/auth.js'; import api from '../services/api.js';
const auth = useAuthStore(); const quota = ref({remaining:0,limit:5});
onMounted(async()=>{ const {data}=await api.get('/quota'); quota.value=data; });
</script>
