<script setup>
import { ref, watch } from 'vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Segmented from '@/components/ui/Segmented.vue';
import api from '@/lib/api.js';
import { dateTime, relative } from '@/lib/format.js';
import { toast } from '@/lib/toast.js';
import { useList } from '@/composables/useList.js';
import { useRealtimeStore } from '@/stores/realtime.js';

const rt = useRealtimeStore();
const tab = ref('all');
const list = useList('/notifications', { perPage: 20, filters: { is_read: '' }, realtime: ['notifications'] });
watch(tab, (t) => (list.filters.is_read = t === 'unread' ? 0 : ''));

function icon(n) {
    const t = `${n.title} ${n.message}`.toLowerCase();
    if (t.includes('revisi')) return 'rotate-ccw';
    if (t.includes('setuju')) return 'badge-check';
    if (t.includes('terbit') || t.includes('publik')) return 'send';
    if (t.includes('review')) return 'list-checks';
    if (t.includes('komentar')) return 'message-circle';
    if (t.includes('token')) return 'key-round';
    if (t.includes('video')) return 'clapperboard';
    return 'bell-ring';
}
async function read(n) {
    if (n.is_read) return;
    n.is_read = true;
    await api.post(`/notifications/${n.id}/read`).catch(() => {});
    rt.pulseNow();
}
async function readAll() {
    const { data } = await api.post('/notifications/read-all');
    toast.success(data.message);
    rt.pulseNow();
    list.load({ silent: true });
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Notifikasi" description="Kabar terbaru tentang video, review, dan akunmu — masuk otomatis tanpa refresh.">
            <template #actions>
                <button type="button" class="btn btn-secondary" :disabled="!rt.unread" @click="readAll"><Icon name="check-check" class="h-4 w-4" /> Tandai semua dibaca</button>
            </template>
        </PageHeader>
        <Segmented v-model="tab" :options="[{ value: 'all', label: 'Semua' }, { value: 'unread', label: 'Belum dibaca', count: rt.unread }]" class="mb-4" />

        <div class="card overflow-hidden">
            <div v-if="list.loading.value && !list.loaded.value" class="space-y-3 p-6"><div v-for="i in 5" :key="i" class="skeleton h-14"></div></div>
            <EmptyState v-else-if="!list.items.value.length" icon="bell" :title="tab === 'unread' ? 'Semua sudah dibaca' : 'Belum ada notifikasi'" description="Notifikasi muncul saat video selesai dibuat, direview, atau dikomentari." />
            <ul v-else class="divide-y divide-line">
                <li v-for="n in list.items.value" :key="n.id">
                    <button type="button" :class="!n.is_read ? 'bg-violet-50/60 dark:bg-violet-500/10' : ''" class="flex w-full gap-4 px-5 py-4 text-left transition hover:bg-surface-2/60" @click="read(n)">
                        <span :class="!n.is_read ? 'bg-yellow-300 border-ink text-zinc-900' : 'bg-surface-2 border-line text-muted'" class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl border-2"><Icon :name="icon(n)" class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2"><span class="font-bold">{{ n.title }}</span><span v-if="!n.is_read" class="h-2 w-2 rounded-full bg-violet-500"></span></span>
                            <span class="block text-sm text-muted">{{ n.message }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-muted" :title="dateTime(n.created_at)">{{ relative(n.created_at) }}</span>
                    </button>
                </li>
            </ul>
            <Pagination :meta="list.meta" label="notifikasi" @go="list.go" />
        </div>
    </div>
</template>
