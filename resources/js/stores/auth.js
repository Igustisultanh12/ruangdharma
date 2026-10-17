import { defineStore } from 'pinia';
import api from '../services/api.js';
export const useAuthStore = defineStore('auth', {
  state: ()=>({ user: null, token: null }),
  actions: {
    loadFromStorage(){ this.token = localStorage.getItem('token'); this.user = JSON.parse(localStorage.getItem('user')||'null'); },
    async login(email, password){
      const { data } = await api.post('/login', { email, password });
      this.user = data.user; this.token = data.token;
      localStorage.setItem('token', data.token); localStorage.setItem('user', JSON.stringify(data.user));
      return data;
    },
    async logout(){ try{ await api.post('/logout'); }catch(e){} this.user=null; this.token=null; localStorage.removeItem('token'); localStorage.removeItem('user'); },
  }
});
