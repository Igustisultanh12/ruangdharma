<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Selamat datang, {{ auth.user?.name }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Ringkasan aktivitas studio hari ini.</p>
      </div>
      <RouterLink to="/generate" class="btn-primary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Generate Video
      </RouterLink>
    </div>

    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
      <StatCard title="Video Hari Ini" :value="todayCount" subtitle="Dibuat hari ini" tone="violet">
        <template #icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg></template>
      </StatCard>
      <StatCard title="Total Video" :value="totalCount" subtitle="Semua riwayat saya" tone="sky">
        <template #icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg></template>
      </StatCard>
      <StatCard title="Kuota Tersisa" :value="`${quota.remaining} / ${quota.limit}`" subtitle="Kuota harian" tone="amber">
        <template #icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg></template>
        <template #footer>
          <div class="progress-track"><div class="progress-bar" :style="{ width: quotaPct + '%' }"></div></div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Terpakai {{ quota.used }} video hari ini</p>
        </template>
      </StatCard>
      <StatCard title="Limit Harian" :value="quota.limit" subtitle="Video per hari" tone="emerald">
        <template #icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg></template>
      </StatCard>
    </div>

    <div class="grid lg:grid-cols-5 gap-4 mb-4">
      <div class="card lg:col-span-3">
        <h2 class="font-bold text-slate-900 dark:text-white mb-1">Aktivitas 7 Hari Terakhir</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Dihitung dari 20 riwayat terbaru.</p>
        <BarChart :data="last7" />
      </div>
      <div class="card lg:col-span-2">
        <h2 class="font-bold text-slate-900 dark:text-white mb-4">Distribusi Model</h2>
        <DonutChart :data="modelDonut" />
      </div>
    </div>

    <div class="card">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-slate-900 dark:text-white">Riwayat Terbaru</h2>
        <RouterLink to="/history" class="text-sm text-violet-600 hover:underline">Lihat semua</RouterLink>
      </div>
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>Kata Kunci</th><th>Durasi</th><th>Model</th><th>Tanggal</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="g in recent" :key="g.id">
              <td class="font-medium">{{ g.keyword }}</td>
              <td>{{ g.durasi }} dtk</td>
              <td><Badge :variant="modelVariant(g.model)">{{ modelLabel(g.model) }}</Badge></td>
              <td>{{ formatDate(g.created_at) }}</td>
              <td><Badge variant="success">{{ g.status || 'completed' }}</Badge></td>
            </tr>
            <tr v-if="!recent.length"><td colspan="5"><EmptyState title="Belum ada riwayat" description="Mulai dengan Generate Video pertama Anda." /></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { useNotificationStore } from '../stores/notifications.js';
import api from '../services/api.js';
import StatCard from '../components/StatCard.vue';
import Badge from '../components/Badge.vue';
import EmptyState from '../components/EmptyState.vue';
import BarChart from '../components/BarChart.vue';
import DonutChart from '../components/DonutChart.vue';

const auth = useAuthStore();
const notif = useNotificationStore();
const quota = ref({ remaining: 0, limit: 5, used: 0 });
const items = ref([]);
const totalCount = ref(0);

const MODEL_LABELS = { narasi: 'Narasi', ngobrol: 'Ngobrol', gabungan: 'Gabungan' };
const MODEL_COLORS = { narasi: '#7c3aed', ngobrol: '#f59e0b', gabungan: '#0ea5e9' };
function modelLabel(m) { return MODEL_LABELS[m] || m; }
function modelVariant(m) { return m === 'narasi' ? 'violet' : m === 'ngobrol' ? 'warning' : 'info'; }
function formatDate(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch { return iso; } }

const todayStr = new Date().toISOString().slice(0, 10);
const todayCount = computed(() => items.value.filter((g) => (g.created_at || '').slice(0, 10) === todayStr).length);
const recent = computed(() => items.value.slice(0, 5));
const quotaPct = computed(() => (quota.value.limit ? Math.round((quota.value.remaining / quota.value.limit) * 100) : 0));
const last7 = computed(() => {
  const days = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    days.push({ label: d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }), value: items.value.filter((g) => (g.created_at || '').slice(0, 10) === key).length });
  }
  return days;
});
const modelDonut = computed(() => ['narasi', 'ngobrol', 'gabungan'].map((m) => ({ label: MODEL_LABELS[m], value: items.value.filter((g) => g.model === m).length, color: MODEL_COLORS[m] })));

onMounted(async () => {
  try {
    const { data } = await api.get('/quota');
    quota.value = data;
    notif.maybeQuotaWarning(data.remaining, data.limit);
  } catch { /* abaikan */ }
  try {
    const { data } = await api.get('/generations', { params: { page: 1 } });
    items.value = data.data || data || [];
    totalCount.value = data.total ?? items.value.length;
  } catch { /* abaikan */ }
});
</script>
