import { defineStore } from 'pinia';
import api from '../services/api.js';
import { logActivity, recordLogin } from '../services/activity.js';

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null, token: null }),
  getters: {
    isAuthenticated: (s) => !!s.token && !!s.user,
    isAdmin: (s) => s.user?.role === 'admin',
    initials: (s) => {
      const name = (s.user?.name || s.user?.email || '?').trim();
      return name.split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    },
  },
  actions: {
    loadFromStorage() {
      this.token = localStorage.getItem('token');
      try {
        this.user = JSON.parse(localStorage.getItem('user') || 'null');
      } catch {
        this.user = null;
      }
    },
    persist() {
      if (this.token) localStorage.setItem('token', this.token);
      else localStorage.removeItem('token');
      if (this.user) localStorage.setItem('user', JSON.stringify(this.user));
      else localStorage.removeItem('user');
    },
    async login(email, password) {
      try {
        const { data } = await api.post('/login', { email, password });
        this.user = data.user;
        this.token = data.token;
        this.persist();
        recordLogin({ email, success: true });
        logActivity({ action: 'login', description: `Masuk ke aplikasi (${email})`, user: data.user });
        return data;
      } catch (e) {
        recordLogin({ email, success: false });
        throw e;
      }
    },
    async fetchUser() {
      const { data } = await api.get('/me');
      this.user = data;
      this.persist();
      return data;
    },
    async logout() {
      const user = this.user;
      try {
        await api.post('/logout');
      } catch (e) {
        // Token mungkin sudah tidak valid; tetap bersihkan sesi lokal.
      }
      this.user = null;
      this.token = null;
      this.persist();
      if (user) logActivity({ action: 'logout', description: `Keluar dari aplikasi (${user.email})`, user });
    },
  },
});
