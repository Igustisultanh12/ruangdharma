import { defineStore } from 'pinia';
import api from '../services/api.js';
export const useGenerateStore = defineStore('generate', {
  state: ()=>({ loading:false, step:0, result:null, error:null, quota:{limit:5,used:0,remaining:5} }),
  actions: {
    async fetchQuota(){ const {data}=await api.get('/quota'); this.quota=data; },
    async generate(payload){
      this.loading=true; this.error=null; this.step=0;
      const steps = ['Menyiapkan naskah','Menulis dialog/narasi','Membuat prompt visual','Menyusun caption','Menyiapkan video'];
      const iv = setInterval(()=>{ if(this.step < steps.length-1) this.step++; }, 900);
      try{
        const {data}=await api.post('/generations', payload);
        this.result=data; await this.fetchQuota();
        return data;
      }catch(e){ this.error = e.response?.data?.message || 'Gagal generate.'; throw e; }
      finally{ clearInterval(iv); this.loading=false; this.step=4; }
    }
  }
});
