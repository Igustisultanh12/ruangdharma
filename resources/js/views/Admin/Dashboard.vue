<script setup>
import { computed, onMounted, ref } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import GenerationModal from '@/components/generation/GenerationModal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import StatCard from '@/components/ui/StatCard.vue';
import api from '@/lib/api.js';
import { modelHex, workflowHex } from '@/lib/colors.js';
import { date, greeting, modelColor, modelLabel, relative, workflowColor, workflowLabel, WORKFLOW, GEN_STATUS } from '@/lib/format.js';
import { useRealtime } from '@/composables/useRealtime.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const s = ref(null);
const detailId = ref(null);
async function load() {
    try {
        s.value = (await api.get('/admin/stats')).data;
    } catch {
        /* biarkan kosong */
    }
}
onMounted(load);
useRealtime(['all_generations', 'users', 'activity'], load);

const bars = computed(() => (s.value?.last_7_days || []).map((d) => ({ label: date(d.date, { weekday: 'short' }), title: date(d.date, { weekday: 'long', day: 'numeric', month: 'long' }), value: d.total })));
const workflowRows = computed(() => Object.keys(WORKFLOW).map((k) => ({ label: WORKFLOW[k].label, value: Number(s.value?.by_workflow?.[k] || 0), color: workflowHex[k] })).filter((r) => r.value > 0));
const modelRows = computed(() => ['narasi', 'ngobrol', 'gabungan'].map((k) => ({ label: modelLabel(k), value: Number(s.value?.by_model?.[k] || 0), color: modelHex[k] })));
const actionIcon = (a = '') => (a.startsWith('login') ? 'log-in' : a.startsWith('logout') ? 'log-out' : a.startsWith('generate') ? 'wand-sparkles' : a.startsWith('review') ? 'list-checks' : a.startsWith('user') ? 'users' : a.startsWith('setting') ? 'settings' : a.startsWith('muse') ? 'plug-zap' : 'activity');
</script>

<template>
    <div>
        <section class="animate-gradient bg-brand-gradient relative mb-8 overflow-hidden rounded-[2rem] border-2 border-ink p-6 text-white shadow-brutal sm:p-8">
            <div class="dot-bg absolute inset-0 opacity-20"></div>
            <Icon name="crown" class="animate-bounce-soft absolute top-6 right-6 hidden h-14 w-14 text-yellow-300 sm:block" />
            <div class="relative">
                <p class="text-sm font-bold text-white/80">{{ greeting() }}, {{ auth.firstName }} 👋</p>
                <h1 class="mt-1 font-display text-3xl font-bold sm:text-4xl">Ringkasan studio hari ini</h1>
                <p class="mt-2 max-w-2xl text-white/85">
                    <b>{{ s?.today_generations ?? '…' }}</b> video dibuat hari ini, <b>{{ s?.review_pending ?? '…' }}</b> naskah menunggu review, dan <b>{{ s?.active_users ?? '…' }}</b> pengguna aktif.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <RouterLink to="/admin/review" class="btn btn-brutal btn-sm"><Icon name="list-checks" class="h-4 w-4" /> Tinjau naskah</RouterLink>
                    <RouterLink to="/admin/users" class="btn btn-sm border-2 border-white/60 bg-white/15 text-white hover:bg-white/25"><Icon name="user-plus" class="h-4 w-4" /> Tambah pengguna</RouterLink>
                    <RouterLink to="/admin/analytics" class="btn btn-sm border-2 border-white/60 bg-white/15 text-white hover:bg-white/25"><Icon name="chart-column" class="h-4 w-4" /> Analitik</RouterLink>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Video hari ini" :value="s?.today_generations ?? 0" icon="zap" color="orange" :loading="!s" to="/admin/history" />
            <StatCard label="Total video" :value="s?.total_generations ?? 0" icon="clapperboard" color="violet" :hint="`${s?.published_total ?? 0} sudah terbit`" :loading="!s" to="/admin/history" />
            <StatCard label="Menunggu review" :value="s?.review_pending ?? 0" icon="hourglass" color="amber" :loading="!s" to="/admin/review" />
            <StatCard label="Pengguna aktif" :value="s?.active_users ?? 0" icon="users" color="emerald" :hint="`dari ${s?.total_users ?? 0} akun`" :loading="!s" to="/admin/users" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card p-6 xl:col-span-2">
                <h2 class="font-display text-lg font-bold">Video dibuat 7 hari terakhir</h2>
                <p class="text-sm text-muted">Arahkan kursor ke batang untuk melihat jumlah per hari.</p>
                <div class="mt-4"><BarChart :rows="bars" :height="230" /></div>
            </div>
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold">Status alur</h2>
                <p class="mb-4 text-sm text-muted">Semua video berdasarkan tahap review</p>
                <DonutChart :rows="workflowRows" center-label="Video" />
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card overflow-hidden xl:col-span-2">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-bold">Video terbaru</h2>
                    <RouterLink to="/admin/history" class="text-sm font-bold text-violet-600 hover:underline dark:text-violet-300">Lihat semua</RouterLink>
                </div>
                <div v-if="s?.recent_generations?.length" class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Pembuat</th><th>Kata kunci</th><th>Gaya</th><th>Status</th><th>Waktu</th></tr></thead>
                        <tbody>
                            <tr v-for="g in s.recent_generations" :key="g.id" class="cursor-pointer" @click="detailId = g.id">
                                <td><div class="flex items-center gap-2"><Avatar :user="g.user" size="xs" /><span class="max-w-28 truncate text-sm font-semibold">{{ g.user?.name }}</span></div></td>
                                <td class="font-bold">{{ g.keyword }}</td>
                                <td><Badge :color="modelColor(g.model)" size="xs">{{ modelLabel(g.model) }}</Badge></td>
                                <td><Badge v-if="GEN_STATUS[g.status]" :color="GEN_STATUS[g.status].color" :icon="GEN_STATUS[g.status].icon" size="xs">{{ GEN_STATUS[g.status].label }}</Badge><Badge v-else :color="workflowColor(g.workflow_status)" dot size="xs">{{ workflowLabel(g.workflow_status) }}</Badge></td>
                                <td class="whitespace-nowrap text-muted">{{ relative(g.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EmptyState v-else compact icon="clapperboard" title="Belum ada video" />
            </div>
            <div class="space-y-6">
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Gaya hari ini</h2>
                    <div class="mt-4"><DonutChart :rows="modelRows" center-label="Hari ini" :size="132" /></div>
                </div>
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Paling produktif hari ini</h2>
                    <ul v-if="s?.top_users_today?.length" class="mt-4 space-y-3">
                        <li v-for="(u, i) in s.top_users_today" :key="u.id" class="flex items-center gap-3">
                            <span class="w-4 text-xs font-bold text-muted">{{ i + 1 }}</span>
                            <Avatar :user="u" size="sm" />
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ u.name }}</span>
                            <span class="text-sm font-bold tabular-nums">{{ u.today }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">Belum ada yang membuat video hari ini.</p>
                </div>
            </div>
        </div>

        <div class="card mt-6 p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-bold">Aktivitas terbaru</h2>
                <RouterLink to="/admin/activity-logs" class="text-sm font-bold text-violet-600 hover:underline dark:text-violet-300">Log lengkap</RouterLink>
            </div>
            <ol class="mt-4 grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                <li v-for="a in s?.recent_activities || []" :key="a.id" class="flex gap-3">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-surface-2 text-muted"><Icon :name="actionIcon(a.action)" class="h-4 w-4" /></span>
                    <span class="min-w-0"><span class="line-clamp-2 block text-sm"><b>{{ a.user?.name || 'Sistem' }}</b> — {{ a.description }}</span><span class="text-xs text-muted">{{ relative(a.created_at) }}</span></span>
                </li>
            </ol>
        </div>

        <GenerationModal :id="detailId" @close="detailId = null" />
    </div>
</template>
