<template><div><h1 class="text-2xl font-bold mb-3">Daftar Pengguna</h1><div v-for="u in users" :key="u.id" class="card mb-2 flex justify-between items-center"><div><b>{{ u.name }}</b> ({{ u.email }}) — {{ u.role }} • Hari ini: {{ u.today_generations }} • Limit: {{ u.daily_limit ?? 'global' }} • {{ u.is_active ? 'Aktif' : 'Nonaktif' }}</div><div class="flex gap-2"><select :value="u.role" @change="upd(u, {role:$event.target.value})" class="input !w-32"><option value="admin">admin</option><option value="pengguna">pengguna</option></select><input type="number" :value="u.daily_limit" @change="upd(u, {daily_limit: $event.target.value ? parseInt($event.target.value) : null})" class="input !w-24" placeholder="Limit" /><button @click="toggle(u)" class="btn-primary !px-2">{{ u.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></div></div></div></template>
<script setup>
import { ref, onMounted } from 'vue'; import api from '../../services/api.js';
const users=ref([]); async function load(){ const {data}=await api.get('/admin/users'); users.value=data; } onMounted(load);
async function upd(u, patch){ await api.put(`/admin/users/${u.id}`, patch); await load(); }
async function toggle(u){ await api.post(`/admin/users/${u.id}/toggle`); await load(); }
</script>
