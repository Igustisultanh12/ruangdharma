<template><div><h1 class="text-2xl font-bold mb-3">Riwayat Generate Semua Pengguna</h1><div v-for="g in items" :key="g.id" class="card mb-2"><b>{{ g.keyword }}</b> — {{ g.model }} • {{ g.durasi }}s • oleh {{ g.user?.name }} ({{ g.user?.email }}) • {{ new Date(g.created_at).toLocaleString('id-ID') }}<p class="text-sm">{{ g.caption?.slice(0,200) }}</p></div></div></template>
<script setup>
import { ref, onMounted } from 'vue'; import api from '../../services/api.js';
const items=ref([]); onMounted(async()=>{ const {data}=await api.get('/admin/generations'); items.value=data.data||[]; });
</script>
