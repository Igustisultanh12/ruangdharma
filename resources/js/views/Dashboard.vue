<script setup>
import { computed, onMounted, ref } from 'vue';
import CanangMark from '@/components/brand/CanangMark.vue';
import GenerationModal from '@/components/generation/GenerationModal.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import StatCard from '@/components/ui/StatCard.vue';
import api from '@/lib/api.js';
import { date, greeting, isoDate, modelColor, modelLabel, relative, time, workflowColor, workflowLabel, GEN_STATUS } from '@/lib/format.js';
import { useRealtime } from '@/composables/useRealtime.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const counts = ref(null);
const recent = ref([]);
const upcoming = ref([]);
const templates = ref([]);
const loading = ref(true);
const detailId = ref(null);

const rt = useRealtime(['generations', 'review'], () => load(true));
const quota = computed(() => rt.quota);
const needsRevision = computed(() => recent.value.filter((g) => g.workflow_status === 'revisi'));

async function load(silent = false) {
    if (!silent) loading.value = true;
    const month = isoDate().slice(0, 7);
    try {
        const [c, r, cal] = await Promise.all([
            api.get('/generations/counts'),
            api.get('/generations', { params: { per_page: 6, archived: 0 } }),
            api.get('/generations/calendar', { params: { month } }),
        ]);
        counts.value = c.data.data;
        recent.value = r.data.data.data;
        const now = Date.now();
        upcoming.value = (cal.data.data.data || []).filter((g) => g.publish_at && new Date(g.publish_at).getTime() >= now - 3600e3).slice(0, 4);
    } finally {
        loading.value = false;
    }
}
onMounted(async () => {
    load();
    try {
        const all = (await api.get('/templates')).data.data || [];
        templates.value = all.sort(() => Math.random() - 0.5).slice(0, 4);
    } catch {
        templates.value = [];
    }
});
</script>

<template>
    <div>
        <section class="animate-gradient bg-brand-gradient relative mb-8 overflow-hidden rounded-[2rem] border-2 border-ink p-6 text-white shadow-brutal sm:p-8">
            <div class="dot-bg absolute inset-0 opacity-20"></div>
            <div class="absolute -right-6 -bottom-8 hidden rotate-12 opacity-95 sm:block"><CanangMark :size="170" /></div>
            <div class="relative max-w-2xl">
                <p class="text-sm font-bold text-white/80">{{ greeting() }}, {{ auth.firstName }} 👋</p>
                <h1 class="mt-1 font-display text-3xl font-bold sm:text-4xl">Cerita dharma apa yang kita buat hari ini?</h1>
                <p class="mt-2 text-white/85">
                    <template v-if="quota">Kamu masih bisa membuat <b>{{ quota.remaining }}</b> video hari ini.</template>
                    <template v-if="counts?.revisi"> Ada <b>{{ counts.revisi }}</b> naskah yang perlu direvisi.</template>
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <RouterLink to="/generate" class="btn btn-brutal btn-sm"><Icon name="wand-sparkles" class="h-4 w-4" /> Buat video</RouterLink>
                    <RouterLink to="/templates" class="btn btn-sm border-2 border-white/60 bg-white/15 text-white hover:bg-white/25"><Icon name="lightbulb" class="h-4 w-4" /> Cari ide</RouterLink>
                    <RouterLink to="/calendar" class="btn btn-sm border-2 border-white/60 bg-white/15 text-white hover:bg-white/25"><Icon name="calendar-days" class="h-4 w-4" /> Kalender</RouterLink>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Dibuat hari ini" :value="quota?.used ?? 0" icon="zap" color="orange" :hint="quota ? `dari kuota ${quota.limit} video` : ''" to="/generate" :loading="!quota" />
            <StatCard label="Video aktif" :value="counts?.all ?? 0" icon="clapperboard" color="violet" :hint="`${counts?.archived ?? 0} di arsip`" to="/history" :loading="loading" />
            <StatCard label="Favorit" :value="counts?.favorites ?? 0" icon="star" color="amber" hint="Siap dipakai lagi" :to="{ path: '/history', query: { tab: 'favorites' } }" :loading="loading" />
            <StatCard label="Terbit" :value="counts?.published ?? 0" icon="send" color="fuchsia" :hint="`${counts?.review ?? 0} menunggu review`" :loading="loading" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card overflow-hidden xl:col-span-2">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-bold">Terakhir dibuat</h2>
                    <RouterLink to="/history" class="text-sm font-bold text-violet-600 hover:underline dark:text-violet-300">Lihat semua</RouterLink>
                </div>
                <div v-if="loading" class="space-y-3 p-6"><div v-for="i in 4" :key="i" class="skeleton h-12"></div></div>
                <ul v-else-if="recent.length" class="divide-y divide-line">
                    <li v-for="g in recent" :key="g.id">
                        <button type="button" class="flex w-full items-center gap-4 px-6 py-3.5 text-left transition hover:bg-surface-2/60" @click="detailId = g.id">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-surface-2 font-display text-sm font-bold">{{ g.durasi }}s</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2"><span class="truncate font-bold">{{ g.keyword }}</span><Icon v-if="g.is_favorite" name="star" class="h-3.5 w-3.5 fill-yellow-400 text-yellow-500" /></span>
                                <span class="text-xs text-muted">{{ relative(g.created_at) }}</span>
                            </span>
                            <Badge :color="modelColor(g.model)" size="xs" class="hidden sm:inline-flex">{{ modelLabel(g.model) }}</Badge>
                            <Badge v-if="GEN_STATUS[g.status]" :color="GEN_STATUS[g.status].color" :icon="GEN_STATUS[g.status].icon">{{ GEN_STATUS[g.status].label }}</Badge><Badge v-else :color="workflowColor(g.workflow_status)" dot>{{ workflowLabel(g.workflow_status) }}</Badge>
                        </button>
                    </li>
                </ul>
                <EmptyState v-else icon="clapperboard" title="Belum ada video" description="Video pertamamu tinggal satu klik lagi.">
                    <RouterLink to="/generate" class="btn btn-primary btn-sm"><Icon name="wand-sparkles" class="h-4 w-4" /> Buat video pertama</RouterLink>
                </EmptyState>
            </div>

            <div class="space-y-6">
                <div v-if="needsRevision.length" class="card-brutal p-5">
                    <h2 class="flex items-center gap-2 font-display text-lg font-bold"><Icon name="rotate-ccw" class="h-5 w-5 text-rose-500" /> Perlu revisi</h2>
                    <ul class="mt-3 space-y-2">
                        <li v-for="g in needsRevision" :key="g.id">
                            <button type="button" class="w-full rounded-2xl bg-rose-50 p-3 text-left transition hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20" @click="detailId = g.id">
                                <p class="text-sm font-bold">{{ g.keyword }}</p>
                                <p class="line-clamp-2 text-xs text-rose-700 dark:text-rose-300">{{ g.review_note }}</p>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-lg font-bold">Jadwal tayang</h2>
                        <RouterLink to="/calendar" class="text-sm font-bold text-violet-600 hover:underline dark:text-violet-300">Kalender</RouterLink>
                    </div>
                    <ul v-if="upcoming.length" class="mt-4 space-y-3">
                        <li v-for="g in upcoming" :key="g.id" class="flex items-center gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border-2 border-ink bg-yellow-300 text-center leading-none text-zinc-900">
                                <span><span class="block font-display text-lg font-bold">{{ new Date(g.publish_at).getDate() }}</span><span class="text-[10px] font-bold">{{ date(g.publish_at, { month: 'short' }) }}</span></span>
                            </span>
                            <button type="button" class="min-w-0 flex-1 text-left" @click="detailId = g.id">
                                <span class="block truncate text-sm font-bold">{{ g.keyword }}</span>
                                <span class="text-xs text-muted">pukul {{ time(g.publish_at) }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">Belum ada jadwal bulan ini. Atur jadwal dari detail video.</p>
                </div>

                <div class="card p-5">
                    <h2 class="font-display text-lg font-bold">Ide untuk dicoba</h2>
                    <ul class="mt-3 space-y-2">
                        <li v-for="t in templates" :key="t.keyword">
                            <RouterLink :to="{ path: '/generate', query: { keyword: t.keyword, durasi: t.default_durasi, model: t.default_model } }" class="group flex items-center gap-3 rounded-2xl p-2 transition hover:bg-surface-2">
                                <span class="grid h-9 w-9 place-items-center rounded-xl bg-surface-2 group-hover:bg-brand-gradient group-hover:text-white"><Icon name="lightbulb" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1"><span class="block text-sm font-bold">{{ t.judul }}</span><span class="block truncate text-xs text-muted">{{ t.deskripsi }}</span></span>
                                <Icon name="arrow-up-right" class="h-4 w-4 text-muted opacity-0 transition group-hover:opacity-100" />
                            </RouterLink>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <GenerationModal :id="detailId" @close="detailId = null" @changed="load(true)" @deleted="load(true)" />
    </div>
</template>
