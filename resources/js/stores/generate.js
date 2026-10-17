import { defineStore } from 'pinia';
import api from '../services/api.js';

export const GENERATE_STEPS = [
  'Menyiapkan naskah',
  'Menulis dialog/narasi',
  'Membuat prompt visual',
  'Menyusun caption',
  'Menyiapkan video',
];

export const useGenerateStore = defineStore('generate', {
  state: () => ({
    loading: false,
    step: 0,
    steps: GENERATE_STEPS,
    result: null,
    error: null,
    errorKind: null, // 'quota' | 'blocked' | 'validation' | 'forbidden' | 'server' | null
    quota: { limit: 5, used: 0, remaining: 5 },
  }),
  actions: {
    async fetchQuota() {
      const { data } = await api.get('/quota');
      this.quota = data;
    },
    reset() {
      this.result = null;
      this.error = null;
      this.errorKind = null;
      this.step = 0;
    },
    async generate(payload) {
      this.loading = true;
      this.error = null;
      this.errorKind = null;
      this.step = 0;
      const iv = setInterval(() => {
        if (this.step < this.steps.length - 1) this.step++;
      }, 900);
      try {
        const { data } = await api.post('/generations', payload);
        this.result = data;
        this.step = this.steps.length - 1;
        await this.fetchQuota();
        return data;
      } catch (e) {
        const status = e.response?.status;
        const msg = e.response?.data?.message || 'Gagal generate.';
        this.error = msg;
        if (status === 429) this.errorKind = 'quota';
        else if (status === 422 && /diblokir/i.test(msg)) this.errorKind = 'blocked';
        else if (status === 422) this.errorKind = 'validation';
        else if (status === 403) this.errorKind = 'forbidden';
        else this.errorKind = 'server';
        throw e;
      } finally {
        clearInterval(iv);
        this.loading = false;
      }
    },
  },
});
