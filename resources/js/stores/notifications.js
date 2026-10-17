import { defineStore } from 'pinia';
import { localGet, localSet } from '../services/preferences.js';

// Notifikasi in-app (localStorage). Backend belum menyediakan endpoint
// notifikasi, jadi notifikasi dibuat dari kejadian nyata di frontend
// (generate berhasil, kuota menipis, dsb.).
export const useNotificationStore = defineStore('notifications', {
  state: () => ({ items: localGet('rd_notifications', []) }),
  getters: {
    unreadCount: (s) => s.items.filter((i) => !i.read).length,
    latest: (s) => [...s.items].slice(0, 20),
  },
  actions: {
    persist() {
      localSet('rd_notifications', this.items.slice(0, 100));
    },
    push({ title, message, type = 'info', link = null }) {
      this.items.unshift({
        id: Date.now() + Math.floor(Math.random() * 1000),
        title,
        message,
        type,
        link,
        read: false,
        created_at: new Date().toISOString(),
      });
      this.items = this.items.slice(0, 100);
      this.persist();
    },
    markRead(id) {
      const n = this.items.find((i) => i.id === id);
      if (n) n.read = true;
      this.persist();
    },
    markAllRead() {
      this.items.forEach((i) => (i.read = true));
      this.persist();
    },
    maybeQuotaWarning(remaining, limit) {
      const today = new Date().toISOString().slice(0, 10);
      const key = `rd_quota_warned_${today}`;
      if (remaining <= 1 && !localStorage.getItem(key)) {
        localStorage.setItem(key, '1');
        this.push({
          title: 'Kuota hampir habis',
          message: `Kuota hari ini tersisa ${remaining} dari ${limit} video.`,
          type: 'warning',
          link: '/generate',
        });
      }
    },
  },
});
