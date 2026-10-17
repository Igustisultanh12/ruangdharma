<template><div><h1 class="text-2xl font-bold mb-3">Riwayat Saya</h1><div v-for="g in items" :key="g.id" class="card mb-3"><b>{{ g.keyword }}</b> — {{ g.durasi }} detik • {{ g.model }} • {{ new Date(g.created_at).toLocaleString('id-ID') }}<p class="text-sm line-clamp-2">{{ g.caption }}</p><RouterLink :to="'/generate'" class="text-violet-600 text-sm underline">Generate lagi</RouterLink></div><p v-if="!items.length">Belum ada riwayat.</p></div></template>
<script setup>
import { ref, onMounted } from 'vue'; import api from '../services/api.js';
const items=ref([]); onMounted(async()=>{ const {data}=await api.get('/generations'); items.value=data.data||data; });
</script>
