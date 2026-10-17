<template><div class="max-w-lg"><h1 class="text-2xl font-bold mb-3">Edit Profil</h1>
<div class="card space-y-3"><label>Nama<input v-model="form.name" class="input" /></label><label>Email<input v-model="form.email" class="input" /></label><label>Foto (URL avatar)<input v-model="form.avatar" class="input" placeholder="https://..." /></label><button @click="save" class="btn-primary">Simpan Profil</button><p v-if="msg" class="text-green-600 text-sm">{{ msg }}</p></div>
<div class="card mt-4 space-y-3"><h2 class="font-bold">Ubah Password</h2><input v-model="pw.current_password" type="password" class="input" placeholder="Password lama" /><input v-model="pw.password" type="password" class="input" placeholder="Password baru" /><input v-model="pw.password_confirmation" type="password" class="input" placeholder="Konfirmasi password baru" /><button @click="savePw" class="btn-primary">Ubah Password</button><p v-if="msgPw" class="text-sm">{{ msgPw }}</p></div>
</div></template>
<script setup>
import { ref, onMounted } from 'vue'; import api from '../services/api.js'; import { useAuthStore } from '../stores/auth.js';
const auth=useAuthStore(); const form=ref({name:'',email:'',avatar:''}); const pw=ref({}); const msg=ref(''); const msgPw=ref('');
onMounted(async()=>{ const {data}=await api.get('/profile'); form.value={name:data.name,email:data.email,avatar:data.avatar||''}; });
async function save(){ const {data}=await api.put('/profile', form.value); auth.user=data.user; localStorage.setItem('user', JSON.stringify(data.user)); msg.value=data.message; }
async function savePw(){ try{ const {data}=await api.post('/profile/password', pw.value); msgPw.value=data.message; }catch(e){ msgPw.value=e.response?.data?.message||'Gagal ubah password.'; } }
</script>
