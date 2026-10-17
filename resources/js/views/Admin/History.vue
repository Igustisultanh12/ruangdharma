<script setup>
import { onMounted, ref } from 'vue';
import GenerationModal from '@/components/generation/GenerationModal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import api from '@/lib/api.js';
import { downloadFromApi, openHtmlFromApi } from '@/lib/download.js';
import { dateTime, MODELS, modelColor, modelLabel, WORKFLOW, workflowColor, workflowLabel, GEN_STATUS } from '@/lib/format.js';
import { useList } from '@/composables/useList.js';

const list = useList('/admin/history', { perPage: 20, filters: { search: '', user_id: '', model: '', workflow_status: '', date_from: '', date_to: '', sort: 'latest' }, realtime: ['all_generations'] });
const f = list.filters;
const users = ref([]);
const detailId = ref(null);
onMounted(async () => {
    try {
        users.value = (await api.get('/admin/users', { params: { per_page: 100 } })).data.data.data;
    } catch {
        users.value = [];
    }
});
const exportParams = () => Object.fromEntries(Object.entries({ user_id: f.user_id, model: f.model, date_from: f.date_from, date_to: f.date_to, search: f.search }).filter(([, v]) => v));
</script>

<template>
    <div>
        <PageHeader title="Semua video" description="Seluruh hasil generate dari semua pengguna, lengkap dengan filter & ekspor laporan.">
            <template #actions>
                <button type="button" class="btn btn-secondary" @click="downloadFromApi('/admin/export/csv', 'laporan-video.csv', exportParams())"><Icon name="file-spreadsheet" class="h-4 w-4" /> Ekspor CSV</button>
                <button type="button" class="btn btn-secondary" @click="openHtmlFromApi('/admin/export/pdf', exportParams())"><Icon name="printer" class="h-4 w-4" /> Cetak / PDF</button>
            </template>
        </PageHeader>

        <div class="card overflow-hidden">
            <div class="grid grid-cols-1 gap-3 border-b border-line bg-surface-2/40 p-4 md:grid-cols-3 xl:grid-cols-7">
                <SearchInput v-model="f.search" placeholder="Cari kata kunci…" class="md:col-span-3 xl:col-span-2" />
                <select v-model="f.user_id" class="input input-sm" aria-label="Pengguna"><option value="">Semua pengguna</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option></select>
                <select v-model="f.model" class="input input-sm" aria-label="Gaya"><option value="">Semua gaya</option><option v-for="m in MODELS" :key="m.value" :value="m.value">{{ m.label }}</option></select>
                <select v-model="f.workflow_status" class="input input-sm" aria-label="Status"><option value="">Semua status</option><option v-for="(w, k) in WORKFLOW" :key="k" :value="k">{{ w.label }}</option></select>
                <input v-model="f.date_from" type="date" class="input input-sm" aria-label="Dari tanggal" />
                <input v-model="f.date_to" type="date" class="input input-sm" aria-label="Sampai tanggal" />
            </div>
            <div v-if="list.loading.value && !list.loaded.value" class="space-y-3 p-6"><div v-for="i in 6" :key="i" class="skeleton h-11"></div></div>
            <EmptyState v-else-if="!list.items.value.length" icon="library" title="Tidak ada video" description="Belum ada video atau tidak ada yang cocok dengan filter." />
            <div v-else class="table-wrap">
                <table class="table">
                    <thead><tr><th>Pembuat</th><th>Kata kunci</th><th>Gaya</th><th>Durasi</th><th>Sumber</th><th>Status</th><th>Dibuat</th></tr></thead>
                    <tbody>
                        <tr v-for="g in list.items.value" :key="g.id" class="cursor-pointer" @click="detailId = g.id">
                            <td><div class="flex items-center gap-2.5"><Avatar :user="g.user" size="sm" /><div class="min-w-0"><p class="max-w-36 truncate font-semibold">{{ g.user?.name }}</p><p class="max-w-36 truncate text-xs text-muted">{{ g.user?.email }}</p></div></div></td>
                            <td class="font-bold">{{ g.keyword }}</td>
                            <td><Badge :color="modelColor(g.model)" size="xs">{{ modelLabel(g.model) }}</Badge></td>
                            <td class="tabular-nums">{{ g.durasi }} dtk</td>
                            <td><Badge :color="g.source === 'muse' ? 'sky' : g.source === 'auto_daily' ? 'lime' : 'slate'" size="xs">{{ g.source === 'muse' ? 'Muse' : g.source === 'auto_daily' ? 'Otomatis' : 'Studio' }}</Badge></td>
                            <td><Badge v-if="GEN_STATUS[g.status]" :color="GEN_STATUS[g.status].color" :icon="GEN_STATUS[g.status].icon" size="xs">{{ GEN_STATUS[g.status].label }}</Badge><Badge v-else :color="workflowColor(g.workflow_status)" dot size="xs">{{ workflowLabel(g.workflow_status) }}</Badge></td>
                            <td class="whitespace-nowrap text-muted">{{ dateTime(g.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="list.meta" label="video" @go="list.go" />
        </div>
        <GenerationModal :id="detailId" @close="detailId = null" @changed="list.load({ silent: true })" />
    </div>
</template>
