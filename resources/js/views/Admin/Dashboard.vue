<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Dashboard Admin</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Ringkasan seluruh studio.</p>

    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
      <StatCard title="Video Hari Ini" :value="s.today_generations ?? 0" subtitle="Dibuat hari ini" tone="violet" />
      <StatCard title="Total Video" :value="s.total_generations ?? 0" subtitle="Semua pengguna" tone="sky" />
      <StatCard title="Pengguna Aktif" :value="`${s.active_users ?? 0} / ${s.total_users ?? 0}`" subtitle="Aktif dari total pengguna" tone="emerald" />
      <StatCard title="Kuota Tersisa (Global)" :value="quotaLabel" subtitle="Ilustrasi limit global harian" tone="amber">
        <template #footer>
          <div class="progress-track"><div class="progress-bar" :style="{ width: quotaPct + '%' }"></div></div>
        </template>
      </StatCard>
    </div>

    <div class="grid lg:grid-cols-5 gap-4 mb-4">
      <div class="card lg:col-span-3">
        <h2 class="font-bold text-slate-900 dark:text-white mb-4">Video 7 Hari Terakhir</h2>
        <BarChart :data="last7" />
      </div>
      <div class="card lg:col-span-2">
        <h2 class="font-bold text-slate-900 dark:text-white mb-4">Distribusi Model (Hari Ini)</h2>
        <DonutChart :data="modelDonut" />
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
      <div class="card">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-bold text-slate-900 dark:text-white">Pengguna Teratas (Hari Ini)</h2>
          <RouterLink to="/admin/users" class="text-sm text-violet-600 hover:underline">Semua pengguna</RouterLink>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead><tr><th>Pengguna</th><th>Peran</th><th>Video Hari Ini</th></tr></thead>
            <tbody>
              <tr v-for="u in topUsers" :key="u.id">
                <td><p class="font-medium">{{ u.name }}</p><p class="text-xs text-slate-400">{{ u.email }}</p></td>
                <td><Badge :variant="u.role === 'admin' ? 'violet' : 'slate'">{{ u.role }}</Badge></td>
                <td class="font-semibold">{{ u.today_generations ?? 0 }}</td>
              </tr>
              <tr v-if="!topUsers.length"><td colspan="3"><EmptyState title="Belum ada pengguna" /></td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <h2 class="font-bold text-slate-900 dark:text-white mb-3">Jalan Pintas Admin</h2>
        <div class="grid sm:grid-cols-2 gap-2">
          <RouterLink to="/admin/review" class="btn-secondary justify-start">Review Naskah</RouterLink>
          <RouterLink to="/admin/analytics" class="btn-secondary justify-start">Analitik</RouterLink>
          <RouterLink to="/admin/activity-logs" class="btn-secondary justify-start">Log Aktivitas</RouterLink>
          <RouterLink to="/admin/muse-config" class="btn-secondary justify-start">Integrasi Muse</RouterLink>
          <RouterLink to="/admin/settings" class="btn-secondary justify-start">Pengaturan</RouterLink>
          <RouterLink to="/admin/quota-plans" class="btn-secondary justify-start">Paket Kuota</RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api.js';
import StatCard from '../../components/StatCard.vue';
import Badge from '../../components/Badge.vue';
import EmptyState from '../../components/EmptyState.vue';
import BarChart from '../../components/BarChart.vue';
import DonutChart from '../../components/DonutChart.vue';

const s = ref({});
const users = ref([]);
const MODEL_LABELS = { narasi: 'Narasi', ngobrol: 'Ngobrol', gabungan: 'Gabungan' };
const MODEL_COLORS = { narasi: '#7c3aed', ngobrol: '#f59e0b', gabungan: '#0ea5e9' };
const quotaLabel = computed(() => {
  const today = s.value.today_generations ?? 0;
  const limit = s.value.global_daily_limit ?? null;
  return limit ? `${Math.max(0, limit - today)} / ${limit}` : `${today} video`;
});
const quotaPct = computed(() => {
  const limit = s.value.global_daily_limit;
  if (!limit) return 100;
  return Math.round((Math.max(0, limit - (s.value.today_generations || 0)) / limit) * 100);
});
const last7 = computed(() => {
  const map = {};
  (s.value.last_7_days || []).forEach((d) => { map[d.date] = d.total; });
  const out = [];
  for (let i = 6; i >= 0; i--) {
    const dt = new Date();
    dt.setDate(dt.getDate() - i);
    const key = dt.toISOString().slice(0, 10);
    out.push({ label: dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }), value: map[key] || 0 });
  }
  return out;
});
const modelDonut = computed(() => {
  const by = s.value.by_model || {};
  return Object.keys(MODEL_LABELS).map((m) => ({ label: MODEL_LABELS[m], value: by[m] || 0, color: MODEL_COLORS[m] }));
});
const topUsers = computed(() => [...users.value].sort((a, b) => (b.today_generations || 0) - (a.today_generations || 0)).slice(0, 5));

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/stats');
    s.value = data;
  } catch { /* abaikan */ }
  try {
    const { data } = await api.get('/admin/users');
    users.value = data || [];
  } catch { /* abaikan */ }
});
</script>
