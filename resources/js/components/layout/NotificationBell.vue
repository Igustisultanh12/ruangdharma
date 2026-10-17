<script setup>
import { ref } from 'vue';
import Dropdown from '@/components/ui/Dropdown.vue';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api from '@/lib/api.js';
import { relative } from '@/lib/format.js';
import { useRealtime } from '@/composables/useRealtime.js';

const items = ref([]);
const loading = ref(false);
const rt = useRealtime(['notifications'], () => load(true));

async function load(silent = false) {
    if (!silent) loading.value = true;
    try {
        const { data } = await api.get('/notifications', { params: { per_page: 8 } });
        items.value = data.data?.data || [];
    } catch {
        /* diam */
    } finally {
        loading.value = false;
    }
}
async function read(n) {
    if (n.is_read) return;
    n.is_read = true;
    await api.post(`/notifications/${n.id}/read`).catch(() => {});
    rt.pulseNow();
}
async function readAll() {
    items.value.forEach((n) => (n.is_read = true));
    await api.post('/notifications/read-all').catch(() => {});
    rt.pulseNow();
}
</script>

<template>
    <Dropdown width="w-[22rem] max-w-[calc(100vw-2rem)]">
        <template #trigger="{ open }">
            <button type="button" :class="open ? 'bg-surface-2 text-ink' : ''" class="btn btn-ghost btn-icon relative" aria-label="Notifikasi" @click="!open && load()">
                <Icon name="bell" :class="rt.unread ? 'animate-wiggle' : ''" class="h-5 w-5" />
                <span v-if="rt.unread" class="absolute top-1.5 right-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[9px] font-extrabold text-white ring-2 ring-card">{{ rt.unread > 9 ? '9+' : rt.unread }}</span>
            </button>
        </template>
        <template #default="{ close }">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <p class="font-display font-bold">Notifikasi</p>
                <button v-if="rt.unread" type="button" class="text-xs font-bold text-violet-600 hover:underline dark:text-violet-300" @click="readAll">Tandai semua dibaca</button>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <div v-if="loading && !items.length" class="grid place-items-center py-8 text-muted"><Spinner class="h-5 w-5" /></div>
                <button
                    v-for="n in items"
                    :key="n.id"
                    type="button"
                    :class="!n.is_read ? 'bg-violet-50/70 dark:bg-violet-500/10' : ''"
                    class="flex w-full gap-3 border-b border-line/70 px-4 py-3 text-left transition last:border-0 hover:bg-surface-2"
                    @click="read(n)"
                >
                    <span :class="!n.is_read ? 'bg-yellow-300 border-ink text-zinc-900' : 'bg-surface-2 border-line text-muted'" class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border-2"><Icon name="bell-ring" class="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2"><span class="truncate text-sm font-bold">{{ n.title }}</span><span v-if="!n.is_read" class="h-2 w-2 shrink-0 rounded-full bg-violet-500"></span></span>
                        <span class="line-clamp-2 block text-xs text-muted">{{ n.message }}</span>
                        <span class="mt-0.5 block text-[11px] text-muted/80">{{ relative(n.created_at) }}</span>
                    </span>
                </button>
                <p v-if="!loading && !items.length" class="px-4 py-8 text-center text-sm text-muted">Belum ada notifikasi.</p>
            </div>
            <RouterLink to="/notifications" class="block border-t border-line bg-surface-2/60 py-2.5 text-center text-sm font-bold text-violet-600 hover:bg-surface-2 dark:text-violet-300" @click="close()">Lihat semua notifikasi</RouterLink>
        </template>
    </Dropdown>
</template>
