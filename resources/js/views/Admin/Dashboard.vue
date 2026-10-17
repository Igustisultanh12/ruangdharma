<template><div><h1 class="text-2xl font-bold mb-3">Dashboard Admin</h1>
<div class="grid md:grid-cols-4 gap-3 mb-4"><div class="card"><p class="text-sm">Video Hari Ini</p><p class="text-2xl font-bold">{{ s.today_generations }}</p></div><div class="card"><p class="text-sm">Total Video</p><p class="text-2xl font-bold">{{ s.total_generations }}</p></div><div class="card"><p class="text-sm">Pengguna Aktif</p><p class="text-2xl font-bold">{{ s.active_users }} / {{ s.total_users }}</p></div><div class="card"><RouterLink to="/admin/muse" class="underline">🤖 Status Muse →</RouterLink></div></div>
<div class="flex gap-3 text-sm mb-4"><RouterLink to="/admin/users" class="underline">Pengguna</RouterLink><RouterLink to="/admin/history" class="underline">Riwayat Semua</RouterLink><RouterLink to="/admin/settings" class="underline">Pengaturan</RouterLink><RouterLink to="/admin/muse" class="underline">Muse</RouterLink></div>
<div class="card"><h2 class="font-bold mb-2">Per Model (Hari Ini)</h2><pre>{{ s.by_model }}</pre><h2 class="font-bold mt-3 mb-2">7 Hari Terakhir</h2><pre>{{ s.last_7_days }}</pre></div>
</div></template>
<script setup>
import { ref, onMounted } from 'vue'; import api from '../services/api.js';
const s=ref({}); onMounted(async()=>{ const {data}=await api.get('/admin/stats'); s.value=data; });
</script>
