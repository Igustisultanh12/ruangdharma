<script setup>
import { computed, ref, watch } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import HBarList from '@/components/charts/HBarList.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Segmented from '@/components/ui/Segmented.vue';
import StatCard from '@/components/ui/StatCard.vue';
import api, { errorMessage } from '@/lib/api.js';
import { modelHex, workflowHex } from '@/lib/colors.js';
import { downloadFromApi } from '@/lib/download.js';
import { date, isoDate, modelLabel, WORKFLOW } from '@/lib/format.js';
import { toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const days = ref(30);
const d = ref(null);
const showTable = ref(false);
async function load() {
    try {
        d.value = (await api.get('/admin/analytics', { params: { days: days.value } })).data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
watch(days, load, { immediate: true });
useRealtime(['all_generations', 'users'], load);

const series = computed(() => (d.value?.per_day || []).map((r) => ({ label: date(r.date, { day: 'numeric', month: 'short' }), title: date(r.date, { weekday: 'long', day: 'numeric', month: 'long' }), value: r.total, date: r.date })));
const labelEvery = computed(() => (days.value <= 14 ? 1 : days.value <= 30 ? 5 : 15));
const models = computed(() => ['narasi', 'ngobrol', 'gabungan'].map((k) => ({ label: modelLabel(k), value: Number(d.value?.by_model?.[k] || 0), color: modelHex[k] })));
const workflow = computed(() => Object.keys(WORKFLOW).map((k) => ({ label: WORKFLOW[k].label, value: Number(d.value?.by_workflow_status?.[k] || 0), color: workflowHex[k] })).filter((r) => r.value));
const durasi = computed(() => [30, 60, 90].map((k) => ({ label: `${k} detik`, value: Number(d.value?.by_durasi?.[k] || 0) })));
const keywords = computed(() => (d.value?.top_keywords || []).map((k) => ({ label: k.keyword, value: k.total })));
const users = computed(() => (d.value?.top_users || []).map((u) => ({ label: u.user?.name || 'Pengguna terhapus', sub: u.user?.email, value: u.total })));
const perDayAvg = computed(() => (d.value ? (d.value.total / d.value.days).toFixed(1) : '0'));
const exportCsv = () => downloadFromApi('/admin/analytics/export-csv', 'analitik-video.csv', { date_from: isoDate(new Date(Date.now() - (days.value - 1) * 864e5)), date_to: isoDate() });
</script>

<template>
    <div>
        <PageHeader title="Analitik" description="Tren produksi video, gaya yang paling sering dipakai, dan tema favorit tim.">
            <template #actions>
                <Segmented v-model="days" :options="[{ value: 7, label: '7 hari' }, { value: 14, label: '14 hari' }, { value: 30, label: '30 hari' }, { value: 90, label: '90 hari' }]" />
                <button type="button" class="btn btn-secondary" @click="exportCsv"><Icon name="file-spreadsheet" class="h-4 w-4" /> Ekspor CSV</button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard :label="`Video ${days} hari`" :value="d?.total ?? 0" icon="clapperboard" color="violet" :hint="`rata-rata ${perDayAvg} per hari`" :loading="!d" />
            <StatCard label="Rata-rata durasi" :value="d ? `${d.avg_durasi} dtk` : '—'" icon="timer" color="orange" :loading="!d" />
            <StatCard label="Pengguna aktif" :value="d?.active_users ?? 0" icon="users" color="emerald" :hint="`dari ${d?.total_users ?? 0} akun`" :loading="!d" />
            <StatCard label="Tema berbeda" :value="keywords.length" icon="lightbulb" color="amber" hint="10 teratas ditampilkan" :loading="!d" />
        </div>

        <div class="card mt-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-display text-lg font-bold">Video per hari</h2>
                    <p class="text-sm text-muted">{{ days }} hari terakhir</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" @click="showTable = !showTable"><Icon :name="showTable ? 'chart-column' : 'table'" class="h-4 w-4" /> {{ showTable ? 'Lihat grafik' : 'Lihat tabel' }}</button>
            </div>
            <div v-if="!showTable" class="mt-4"><BarChart :rows="series" :label-every="labelEvery" :height="250" /></div>
            <div v-else class="table-wrap mt-4 max-h-80 overflow-y-auto rounded-2xl border border-line">
                <table class="table"><thead><tr><th>Tanggal</th><th class="text-right">Video</th></tr></thead><tbody><tr v-for="r in [...series].reverse()" :key="r.date"><td>{{ r.title }}</td><td class="text-right font-bold tabular-nums">{{ r.value }}</td></tr></tbody></table>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="card p-6"><h2 class="mb-4 font-display text-lg font-bold">Gaya penyampaian</h2><DonutChart :rows="models" center-label="Video" :size="140" /></div>
            <div class="card p-6"><h2 class="mb-4 font-display text-lg font-bold">Tahap review</h2><DonutChart :rows="workflow" center-label="Video" :size="140" /></div>
            <div class="card p-6"><h2 class="mb-4 font-display text-lg font-bold">Durasi</h2><HBarList :rows="durasi" color="#f97316" unit="video" /></div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="card p-6"><h2 class="mb-4 font-display text-lg font-bold">Tema terpopuler</h2><HBarList :rows="keywords" unit="video" /></div>
            <div class="card p-6"><h2 class="mb-4 font-display text-lg font-bold">Pembuat paling aktif</h2><HBarList :rows="users" color="#0ea5e9" unit="video" /></div>
        </div>
    </div>
</template>
