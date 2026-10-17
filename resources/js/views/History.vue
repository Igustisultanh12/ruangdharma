<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import GenerationModal from '@/components/generation/GenerationModal.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import Segmented from '@/components/ui/Segmented.vue';
import api, { errorMessage } from '@/lib/api.js';
import { captionText, DURATIONS, MODELS, modelColor, modelLabel, partText, relative, WORKFLOW, workflowColor, workflowLabel } from '@/lib/format.js';
import { confirmDialog, toast } from '@/lib/toast.js';
import { useList } from '@/composables/useList.js';
import { useRealtime } from '@/composables/useRealtime.js';

const route = useRoute();
const router = useRouter();
const tab = ref(['favorites', 'archived'].includes(route.query.tab) ? route.query.tab : 'all');
const view = ref(localStorage.getItem('rd_history_view') || 'table');
const counts = ref({});
const detailId = ref(null);
const showFilters = ref(false);

const list = useList('/generations', {
    perPage: 12,
    filters: { search: '', model: '', durasi: '', workflow_status: '', date_from: '', date_to: '', sort: 'latest', favorites: tab.value === 'favorites', archived: tab.value === 'archived' ? 1 : tab.value === 'favorites' ? 'all' : 0 },
    realtime: ['generations'],
});
const f = list.filters;
async function loadCounts() {
    try {
        counts.value = (await api.get('/generations/counts')).data.data;
    } catch {
        /* abaikan */
    }
}
useRealtime(['generations'], loadCounts);
onMounted(loadCounts);

watch(tab, (t) => {
    f.favorites = t === 'favorites';
    f.archived = t === 'archived' ? 1 : t === 'favorites' ? 'all' : 0;
    router.replace({ query: t === 'all' ? {} : { tab: t } });
});
watch(view, (v) => localStorage.setItem('rd_history_view', v));

const tabs = computed(() => [
    { value: 'all', label: 'Semua', icon: 'clapperboard', count: counts.value.all },
    { value: 'favorites', label: 'Favorit', icon: 'star', count: counts.value.favorites },
    { value: 'archived', label: 'Arsip', icon: 'archive', count: counts.value.archived },
]);
const activeFilters = computed(() => ['model', 'durasi', 'workflow_status', 'date_from', 'date_to'].filter((k) => f[k]).length);
function resetFilters() {
    Object.assign(f, { search: '', model: '', durasi: '', workflow_status: '', date_from: '', date_to: '', sort: 'latest' });
}

async function toggleFav(g) {
    g.is_favorite = !g.is_favorite;
    try {
        await api.post(`/generations/${g.id}/toggle-favorite`);
        loadCounts();
    } catch (e) {
        g.is_favorite = !g.is_favorite;
        toast.error(errorMessage(e));
    }
}
async function remove(g) {
    if (!(await confirmDialog({ title: `Hapus "${g.keyword}"?`, message: 'Video beserta versi & komentarnya dihapus permanen.', tone: 'danger', confirmText: 'Hapus' }))) return;
    try {
        await api.delete(`/generations/${g.id}`);
        toast.success('Video dihapus.');
        list.load({ silent: true });
        loadCounts();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
const regenerate = (g) => router.push({ path: '/generate', query: { keyword: g.keyword, durasi: g.durasi, model: g.model } });
</script>

<template>
    <div>
        <PageHeader title="Riwayat saya" description="Semua video yang pernah kamu buat. Perubahan dari reviewer muncul otomatis.">
            <template #actions>
                <RouterLink to="/generate" class="btn btn-primary"><Icon name="plus" class="h-4 w-4" /> Video baru</RouterLink>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Segmented v-model="tab" :options="tabs" />
            <div class="segment">
                <button type="button" :class="view === 'table' ? 'segment-item-active' : ''" class="segment-item" aria-label="Tampilan tabel" @click="view = 'table'"><Icon name="list" class="h-4 w-4" /></button>
                <button type="button" :class="view === 'grid' ? 'segment-item-active' : ''" class="segment-item" aria-label="Tampilan kartu" @click="view = 'grid'"><Icon name="layout-grid" class="h-4 w-4" /></button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-line bg-surface-2/40 p-4">
                <SearchInput v-model="f.search" placeholder="Cari kata kunci atau caption…" class="min-w-[220px] flex-1" />
                <select v-model="f.sort" class="input input-sm w-auto" aria-label="Urutkan">
                    <option value="latest">Terbaru</option>
                    <option value="oldest">Terlama</option>
                </select>
                <button type="button" :class="showFilters || activeFilters ? 'border-violet-400 text-violet-600 dark:text-violet-300' : ''" class="btn btn-secondary btn-sm" @click="showFilters = !showFilters">
                    <Icon name="sliders-horizontal" class="h-4 w-4" /> Filter <span v-if="activeFilters" class="rounded-full bg-violet-600 px-1.5 text-[10px] text-white">{{ activeFilters }}</span>
                </button>
            </div>
            <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <div v-if="showFilters" class="grid grid-cols-1 gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div><label class="label text-xs" for="f-model">Gaya</label><select id="f-model" v-model="f.model" class="input input-sm"><option value="">Semua</option><option v-for="m in MODELS" :key="m.value" :value="m.value">{{ m.label }}</option></select></div>
                    <div><label class="label text-xs" for="f-durasi">Durasi</label><select id="f-durasi" v-model="f.durasi" class="input input-sm"><option value="">Semua</option><option v-for="d in DURATIONS" :key="d" :value="d">{{ d }} detik</option></select></div>
                    <div><label class="label text-xs" for="f-wf">Status</label><select id="f-wf" v-model="f.workflow_status" class="input input-sm"><option value="">Semua</option><option v-for="(w, k) in WORKFLOW" :key="k" :value="k">{{ w.label }}</option></select></div>
                    <div><label class="label text-xs" for="f-from">Dari tanggal</label><input id="f-from" v-model="f.date_from" type="date" class="input input-sm" /></div>
                    <div><label class="label text-xs" for="f-to">Sampai tanggal</label><input id="f-to" v-model="f.date_to" type="date" class="input input-sm" /></div>
                    <div class="sm:col-span-2 lg:col-span-5"><button type="button" class="btn btn-ghost btn-sm" @click="resetFilters"><Icon name="rotate-ccw" class="h-4 w-4" /> Atur ulang filter</button></div>
                </div>
            </Transition>

            <div v-if="list.loading.value && !list.loaded.value" class="space-y-3 p-6"><div v-for="i in 5" :key="i" class="skeleton h-12"></div></div>
            <p v-else-if="list.error.value" class="p-6 text-sm text-rose-600">{{ list.error.value }}</p>
            <EmptyState v-else-if="!list.items.value.length" :icon="tab === 'favorites' ? 'star' : tab === 'archived' ? 'archive' : 'clapperboard'" :title="f.search || activeFilters ? 'Tidak ada yang cocok' : tab === 'favorites' ? 'Belum ada favorit' : tab === 'archived' ? 'Arsip kosong' : 'Belum ada video'" :description="f.search || activeFilters ? 'Ubah kata pencarian atau atur ulang filter.' : 'Video yang kamu buat akan muncul di sini.'">
                <button v-if="f.search || activeFilters" type="button" class="btn btn-secondary btn-sm" @click="resetFilters">Atur ulang filter</button>
                <RouterLink v-else to="/generate" class="btn btn-primary btn-sm"><Icon name="wand-sparkles" class="h-4 w-4" /> Buat video</RouterLink>
            </EmptyState>

            <div v-else-if="view === 'table'" class="table-wrap">
                <table class="table">
                    <thead><tr><th class="w-10"></th><th>Kata kunci</th><th>Gaya</th><th>Durasi</th><th>Status</th><th>Dibuat</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="g in list.items.value" :key="g.id" class="cursor-pointer" @click="detailId = g.id">
                            <td @click.stop><button type="button" class="rounded-lg p-1.5 transition hover:bg-yellow-100 dark:hover:bg-yellow-400/10" :aria-label="g.is_favorite ? 'Hapus dari favorit' : 'Jadikan favorit'" @click="toggleFav(g)"><Icon name="star" :class="g.is_favorite ? 'fill-yellow-400 text-yellow-500' : 'text-muted'" class="h-4 w-4" /></button></td>
                            <td>
                                <p class="font-bold">{{ g.keyword }}</p>
                                <p class="max-w-xs truncate text-xs text-muted">{{ partText((g.naskah || [])[0]) }}</p>
                            </td>
                            <td><Badge :color="modelColor(g.model)" size="xs">{{ modelLabel(g.model) }}</Badge></td>
                            <td class="tabular-nums">{{ g.durasi }} dtk</td>
                            <td><Badge :color="workflowColor(g.workflow_status)" dot>{{ workflowLabel(g.workflow_status) }}</Badge></td>
                            <td class="whitespace-nowrap text-muted">{{ relative(g.created_at) }}</td>
                            <td @click.stop>
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Buat ulang" aria-label="Buat ulang" @click="regenerate(g)"><Icon name="refresh-cw" class="h-4 w-4" /></button>
                                    <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Detail" aria-label="Buka detail" @click="detailId = g.id"><Icon name="eye" class="h-4 w-4" /></button>
                                    <button type="button" class="btn btn-soft-danger btn-icon btn-sm" title="Hapus" aria-label="Hapus" @click="remove(g)"><Icon name="trash-2" class="h-4 w-4" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="g in list.items.value" :key="g.id" class="group card card-lift relative flex cursor-pointer flex-col p-5" @click="detailId = g.id">
                    <div class="flex items-start justify-between gap-2">
                        <Badge :color="workflowColor(g.workflow_status)" dot size="xs">{{ workflowLabel(g.workflow_status) }}</Badge>
                        <button type="button" class="-m-1 rounded-lg p-1" :aria-label="g.is_favorite ? 'Hapus dari favorit' : 'Jadikan favorit'" @click.stop="toggleFav(g)"><Icon name="star" :class="g.is_favorite ? 'fill-yellow-400 text-yellow-500' : 'text-muted'" class="h-4 w-4" /></button>
                    </div>
                    <h3 class="mt-3 font-display text-lg font-bold">{{ g.keyword }}</h3>
                    <p class="mt-1 line-clamp-3 flex-1 text-sm text-muted">{{ captionText(g.caption).split('\n')[0] }}</p>
                    <div class="mt-4 flex items-center gap-2 text-xs">
                        <Badge :color="modelColor(g.model)" size="xs">{{ modelLabel(g.model) }}</Badge>
                        <span class="font-semibold">{{ g.durasi }} dtk</span>
                        <span class="ml-auto text-muted">{{ relative(g.created_at) }}</span>
                    </div>
                </article>
            </div>

            <Pagination :meta="list.meta" label="video" @go="list.go" />
        </div>

        <GenerationModal :id="detailId" @close="detailId = null" @changed="loadCounts" @deleted="list.load({ silent: true }); loadCounts()" />
    </div>
</template>
