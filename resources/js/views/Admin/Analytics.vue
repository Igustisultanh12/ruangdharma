<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Analitik</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Performa studio 30 hari terakhir.</p>
      </div>
      <button @click="exportCsv" class="btn-secondary btn-sm">Ekspor Analitik CSV</button>
    </div>

    <div class="grid sm:grid-cols-3 gap-4 mb-4">
      <StatCard title="Total Video (30 hari)" :value="items.length" subtitle="Dari data yang dimuat" />
      <StatCard title="Pengguna Aktif" :value="topUsers.length" subtitle="Yang pernah generate" tone="emerald" />
      <StatCard title="Kata Kunci Teratas" :value="topKeywords[0]?.keyword || '-'" :subtitle="topKeywords[0] ? `${topKeywords[0].count} video` : ''" tone="amber" />
    </div>

    <div class="card mb-4">
      <h2 class="font-bold text-slate-900 dark:text-white mb-4">Video per Hari (30 Hari)</h2>
      <BarChart :data="last30" />
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
      <div class="card">
        <h2 class="font-bold text-slate-900 dark:text-white mb-3">Top Kata Kunci</h2>
        <ol class="space-y-2">
          <li v-for="(k, i) in topKeywords" :key="k.keyword" class="flex items-center gap-3 text-sm">
            <span class="w-6 text-slate-400 font-semibold">{{ i + 1 }}.</span>
            <span class="flex-1 font-medium">{{ k.keyword }}</span>
            <span class="badge badge-violet">{{ k.count }} video</span>
          </li>
          <li v-if="!topKeywords.length" class="text-sm text-slate-500">Belum ada data.</li>
        </ol>
      </div>
      <div class="card">
        <h2 class="font-bold text-slate-900 dark:text-white mb-3">Top Pengguna</h2>
        <ol class="space-y-2">
          <li v-for="(u, i) in topUsers" :key="u.name" class="flex items-center gap-3 text-sm">
            <span class="w-6 text-slate-400 font-semibold">{{ i + 1 }}.</span>
            <span class="flex-1 font-medium">{{ u.name }}</span>
            <span class="badge badge-info">{{ u.count }} video</span>
          </li>
          <li v-if="!topUsers.length" class="text-sm text-slate-500">Belum ada data.</li>
        </ol>
      </div>
      <div class="card">
        <h2 class="font-bold text-slate-900 dark:text-white mb-4">Distribusi Model</h2>
        <DonutChart :data="modelDonut" />
      </div>
      <div class="card">
        <h2 class="font-bold text-slate-900 dark:text-white mb-4">Distribusi Durasi</h2>
        <div class="space-y-3">
          <div v-for="d in durationBars" :key="d.label">
            <div class="flex justify-between text-sm mb-1"><span>{{ d.label }}</span><span class="font-semibold">{{ d.value }} video</span></div>
            <div class="progress-track"><div class="progress-bar" :style="{ width: d.pct + '%' }"></div></div>
          </div>
          <p v-if="!durationBars.length" class="text-sm text-slate-500">Belum ada data.</p>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import api from '../../services/api.js';
import StatCard from '../../components/StatCard.vue';
import BarChart from '../../components/BarChart.vue';
import DonutChart from '../../components/DonutChart.vue';

const items = ref([]);
const MODEL_LABELS = { narasi: 'Narasi', ngobrol: 'Ngobrol', gabungan: 'Gabungan' };
const MODEL_COLORS = { narasi: '#7c3aed', ngobrol: '#f59e0b', gabungan: '#0ea5e9' };

const last30 = computed(() => {
  const out = [];
  for (let i = 29; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    out.push({ label: d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }), value: items.value.filter((g) => (g.created_at || '').slice(0, 10) === key).length });
  }
  return out;
});
function groupBy(list, keyFn) {
  const map = {};
  list.forEach((g) => { const k = keyFn(g); if (k) map[k] = (map[k] || 0) + 1; });
  return Object.entries(map).map(([name, count]) => ({ name, count })).sort((a, b) => b.count - a.count);
}
const topKeywords = computed(() => groupBy(items.value, (g) => g.keyword).slice(0, 10).map((x) => ({ keyword: x.name, count: x.count })));
const topUsers = computed(() => groupBy(items.value, (g) => g.user?.name || `User #${g.user_id}`).slice(0, 10).map((x) => ({ name: x.name, count: x.count })));
const modelDonut = computed(() => ['narasi', 'ngobrol', 'gabungan'].map((m) => ({ label: MODEL_LABELS[m], value: items.value.filter((g) => g.model === m).length, color: MODEL_COLORS[m] })));
const durationBars = computed(() => {
  const total = items.value.length || 1;
  return [30, 60, 90].map((d) => {
    const value = items.value.filter((g) => Number(g.durasi) === d).length;
    return { label: `${d} detik`, value, pct: Math.round((value / total) * 100) };
  });
});
function exportCsv() {
  const rows = [['Tanggal', 'Kata Kunci', 'Model', 'Durasi', 'Pengguna']];
  items.value.forEach((g) => rows.push([g.created_at || '', g.keyword, g.model, g.durasi, g.user?.name || '']));
  const csv = rows.map((r) => r.map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'analitik-ruang-dharma.csv';
  a.click();
  URL.revokeObjectURL(a.href);
  Swal.fire({ icon: 'success', title: 'Analitik diekspor', timer: 1200, showConfirmButton: false });
}
onMounted(async () => {
  // Ambil beberapa halaman riwayat admin agar analitik lebih lengkap (maks 3 halaman).
  const all = [];
  for (let p = 1; p <= 3; p++) {
    try {
      const { data } = await api.get('/admin/generations', { params: { page: p } });
      all.push(...(data.data || []));
      if (p >= (data.last_page || 1)) break;
    } catch { break; }
  }
  items.value = all;
});
</script>
