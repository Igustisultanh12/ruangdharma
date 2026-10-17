<template><div class="max-w-lg"><h1 class="text-2xl font-bold mb-3">Pengaturan</h1><div class="card space-y-3"><label>Limit Global Harian<input v-model.number="form.daily_limit" type="number" class="input" /></label><label>Kata Kunci Diblokir (pisahkan koma)<input v-model="blockedText" class="input" placeholder="politik, judi, pornografi" /></label><label>Nama Aplikasi<input v-model="form.app_name" class="input" /></label><button @click="save" class="btn-primary">Simpan</button><p v-if="msg" class="text-green-600 text-sm">{{ msg }}</p></div></div></template>
<script setup>
import { ref, onMounted } from 'vue'; import api from '../services/api.js';
const form=ref({daily_limit:5,app_name:''}); const blockedText=ref(''); const msg=ref('');
onMounted(async()=>{ const {data}=await api.get('/admin/settings'); form.value={daily_limit:data.daily_limit,app_name:data.app_name}; blockedText.value=(data.blocked_keywords||[]).join(', '); });
async function save(){ const {data}=await api.put('/admin/settings', {...form.value, blocked_keywords: blockedText.value.split(',').map(s=>s.trim()).filter(Boolean)}); msg.value=data.message; }
</script>
