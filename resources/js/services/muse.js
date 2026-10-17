import axios from 'axios';
import api from './api.js';

// Cek status Muse dari frontend admin (via Laravel API, token Sanctum admin)
export async function checkMuseStatus(){
  const { data } = await api.get('/admin/muse/status');
  return data;
}
export async function testMuseGenerate(keyword='Rwa Bhineda'){
  const { data } = await api.get('/admin/muse/status');
  return data;
}
// Direct call dengan MUSE_API_TOKEN (untuk debugging, jangan expose token di produksi)
export async function checkMuseDirect(token){
  const { data } = await axios.get('/api/muse/status', { headers:{ 'X-Muse-Token': token } });
  return data;
}

// --- Konfigurasi Muse via menu Admin (tanpa edit .env) ---
export async function getMuseConfig(show = false){
  const { data } = await api.get('/admin/muse-config', { params: { show: show ? 1 : 0 } });
  return data;
}
export async function generateMuseToken(){
  const { data } = await api.post('/admin/muse-config/generate-token');
  return data;
}
export async function generateMuseWebhookSecret(){
  const { data } = await api.post('/admin/muse-config/generate-webhook-secret');
  return data;
}
export async function updateMuseConfig(payload){
  const { data } = await api.put('/admin/muse-config', payload);
  return data;
}
export async function testMuseConfig(){
  const { data } = await api.post('/admin/muse-config/test');
  return data;
}
