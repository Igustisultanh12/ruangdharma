<script setup>
import { computed, ref, watch } from 'vue';
import GenerationModal from '@/components/generation/GenerationModal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import Segmented from '@/components/ui/Segmented.vue';
import api, { errorMessage } from '@/lib/api.js';
import { modelColor, modelLabel, partText, relative, workflowColor, workflowLabel } from '@/lib/format.js';
import { confirmDialog, toast } from '@/lib/toast.js';
import { useList } from '@/composables/useList.js';
import { useRealtimeStore } from '@/stores/realtime.js';

const rt = useRealtimeStore();
const status = ref('review');
const list = useList('/review-queue', { perPage: 10, filters: { status: 'review', search: '' }, realtime: ['review'] });
watch(status, (v) => (list.filters.status = v));
const detailId = ref(null);
const busy = ref(null);
const counts = computed(() => list.extra.value.counts || {});
const tabs = computed(() => [
    { value: 'review', label: 'Menunggu', icon: 'hourglass', count: counts.value.review || 0 },
    { value: 'revisi', label: 'Perlu revisi', icon: 'rotate-ccw', count: counts.value.revisi || 0 },
    { value: 'approved', label: 'Disetujui', icon: 'badge-check', count: counts.value.approved || 0 },
    { value: 'published', label: 'Terbit', icon: 'send', count: counts.value.published || 0 },
    { value: 'all', label: 'Semua', icon: 'layers' },
]);

async function act(g, kind) {
    let payload = {};
    if (kind === 'reject') {
        const note = await confirmDialog({ title: `Minta revisi "${g.keyword}"`, message: 'Catatan ini dikirim ke pembuat video sebagai notifikasi.', confirmText: 'Kirim catatan', icon: 'rotate-ccw', input: { label: 'Catatan revisi', required: true, multiline: true, placeholder: 'Apa yang perlu diperbaiki?' } });
        if (!note) return;
        payload = { review_note: note };
    }
    if (kind === 'publish' && !(await confirmDialog({ title: `Terbitkan "${g.keyword}"?`, confirmText: 'Terbitkan', icon: 'send' }))) return;
    busy.value = `${g.id}-${kind}`;
    try {
        const { data } = await api.post(`/generations/${g.id}/${kind}`, payload);
        toast.success(data.message);
        list.load({ silent: true });
        rt.pulseNow();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        busy.value = null;
    }
}
</script>

<template>
    <div>
        <PageHeader title="Antrean review" description="Tinjau naskah sebelum tayang. Antrean bertambah otomatis saat ada kiriman baru." />
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Segmented v-model="status" :options="tabs" />
            <SearchInput v-model="list.filters.search" placeholder="Cari kata kunci…" class="w-full sm:ml-auto sm:w-64" />
        </div>

        <div v-if="list.loading.value && !list.loaded.value" class="space-y-4"><div v-for="i in 3" :key="i" class="skeleton h-36 rounded-3xl"></div></div>
        <div v-else-if="!list.items.value.length" class="card">
            <EmptyState :icon="status === 'review' ? 'party-popper' : 'inbox'" :title="status === 'review' ? 'Antrean bersih' : 'Tidak ada video di tahap ini'" :description="status === 'review' ? 'Semua naskah sudah ditinjau. Kiriman baru akan muncul di sini otomatis.' : 'Pilih tahap lain di atas.'" />
        </div>
        <div v-else class="space-y-4">
            <article v-for="g in list.items.value" :key="g.id" class="card card-lift p-5">
                <div class="flex flex-wrap items-start gap-4">
                    <Avatar :user="g.user" size="md" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="font-display text-lg font-bold hover:text-violet-600 dark:hover:text-violet-300" @click="detailId = g.id">{{ g.keyword }}</button>
                            <Badge :color="workflowColor(g.workflow_status)" dot size="xs">{{ workflowLabel(g.workflow_status) }}</Badge>
                            <Badge :color="modelColor(g.model)" size="xs">{{ modelLabel(g.model) }}</Badge>
                            <Badge size="xs">{{ g.durasi }} dtk</Badge>
                        </div>
                        <p class="mt-0.5 text-xs text-muted">{{ g.user?.name }} · diperbarui {{ relative(g.updated_at) }}</p>
                        <ol class="mt-3 space-y-1 text-sm">
                            <li v-for="n in (g.naskah || []).slice(0, 2)" :key="n.bagian" class="line-clamp-1"><b>{{ n.speaker }}:</b> {{ partText(n) }}</li>
                        </ol>
                        <p v-if="g.review_note" class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"><b>Catatan:</b> {{ g.review_note }}</p>
                    </div>
                    <div class="flex w-full flex-wrap gap-2 sm:w-auto sm:flex-col">
                        <button v-if="g.workflow_status !== 'approved' && g.workflow_status !== 'published'" type="button" class="btn btn-success btn-sm" :disabled="busy === `${g.id}-approve`" @click="act(g, 'approve')"><Icon name="badge-check" class="h-4 w-4" /> Setujui</button>
                        <button v-if="g.workflow_status !== 'revisi' && g.workflow_status !== 'published'" type="button" class="btn btn-secondary btn-sm" :disabled="busy === `${g.id}-reject`" @click="act(g, 'reject')"><Icon name="rotate-ccw" class="h-4 w-4" /> Minta revisi</button>
                        <button v-if="g.workflow_status === 'approved'" type="button" class="btn btn-primary btn-sm" :disabled="busy === `${g.id}-publish`" @click="act(g, 'publish')"><Icon name="send" class="h-4 w-4" /> Terbitkan</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="detailId = g.id"><Icon name="eye" class="h-4 w-4" /> Buka</button>
                    </div>
                </div>
            </article>
            <div class="card overflow-hidden"><Pagination :meta="list.meta" label="video" @go="list.go" /></div>
        </div>

        <GenerationModal :id="detailId" @close="detailId = null" @changed="list.load({ silent: true })" />
    </div>
</template>
