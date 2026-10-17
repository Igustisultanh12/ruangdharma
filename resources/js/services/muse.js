import axios from 'axios';
// Cek status Muse dari frontend admin (via Laravel API, token Sanctum admin)
import api from './api.js';
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
