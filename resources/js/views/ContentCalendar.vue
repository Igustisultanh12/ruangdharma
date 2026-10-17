<script setup>
import { computed, ref, watch } from 'vue';
import GenerationModal from '@/components/generation/GenerationModal.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage } from '@/lib/api.js';
import { isoDate, time, workflowLabel } from '@/lib/format.js';
import { toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const cursor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const items = ref([]);
const loading = ref(false);
const detailId = ref(null);
const dragging = ref(null);
const dropDay = ref(null);

const month = computed(() => `${cursor.value.getFullYear()}-${String(cursor.value.getMonth() + 1).padStart(2, '0')}`);
const monthLabel = computed(() => cursor.value.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }));
const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const today = isoDate();

async function load(silent = false) {
    if (!silent) loading.value = true;
    try {
        items.value = (await api.get('/generations/calendar', { params: { month: month.value } })).data.data.data || [];
    } catch (e) {
        if (!silent) toast.error(errorMessage(e, 'Gagal memuat kalender.'));
    } finally {
        loading.value = false;
    }
}
watch(month, () => load(), { immediate: true });
useRealtime(['generations'], () => load(true));

const days = computed(() => {
    const first = new Date(cursor.value);
    const offset = (first.getDay() + 6) % 7; // Senin = 0
    const start = new Date(first);
    start.setDate(1 - offset);
    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const key = isoDate(d);
        return {
            key,
            date: d,
            inMonth: d.getMonth() === cursor.value.getMonth(),
            items: items.value.filter((g) => isoDate(g.publish_at || g.created_at) === key).sort((a, b) => new Date(a.publish_at || a.created_at) - new Date(b.publish_at || b.created_at)),
        };
    });
});
const scheduledCount = computed(() => items.value.filter((g) => g.publish_at && isoDate(g.publish_at).startsWith(month.value)).length);

function shift(n) {
    cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + n, 1);
}
function goToday() {
    cursor.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
}
async function drop(day) {
    const g = dragging.value;
    dropDay.value = null;
    dragging.value = null;
    if (!g) return;
    const base = g.publish_at ? new Date(g.publish_at) : new Date(new Date().setHours(9, 0, 0, 0));
    const target = new Date(day.date);
    target.setHours(base.getHours(), base.getMinutes(), 0, 0);
    const prev = g.publish_at;
    g.publish_at = target.toISOString();
    try {
        await api.post(`/generations/${g.id}/schedule-publish`, { publish_at: target.toISOString() });
        toast.success(`"${g.keyword}" dijadwalkan ${target.toLocaleDateString('id-ID', { day: 'numeric', month: 'long' })} pukul ${time(target)}.`);
    } catch (e) {
        g.publish_at = prev;
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <div>
        <PageHeader title="Kalender konten" description="Seret kartu video ke tanggal lain untuk menjadwalkan tayang. Kartu berwarna = sudah dijadwalkan, garis putus = tanggal dibuat." />

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                <div class="flex items-center gap-1">
                    <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Bulan sebelumnya" @click="shift(-1)"><Icon name="chevron-left" class="h-5 w-5" /></button>
                    <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Bulan berikutnya" @click="shift(1)"><Icon name="chevron-right" class="h-5 w-5" /></button>
                </div>
                <h2 class="font-display text-xl font-bold capitalize">{{ monthLabel }}</h2>
                <Spinner v-if="loading" class="h-4 w-4 text-muted" />
                <span class="ml-auto text-sm text-muted"><b class="text-ink">{{ scheduledCount }}</b> terjadwal</span>
                <button type="button" class="btn btn-secondary btn-sm" @click="goToday">Bulan ini</button>
            </div>
            <div class="overflow-x-auto">
                <div class="min-w-[760px]">
                    <div class="grid grid-cols-7 border-b border-line bg-surface-2/50">
                        <div v-for="w in weekdays" :key="w" class="px-3 py-2 text-xs font-bold text-muted">{{ w }}</div>
                    </div>
                    <div class="grid grid-cols-7">
                        <div
                            v-for="d in days"
                            :key="d.key"
                            :class="[d.inMonth ? '' : 'bg-surface-2/40 text-muted', dropDay === d.key ? 'bg-yellow-100 ring-2 ring-inset ring-yellow-400 dark:bg-yellow-400/10' : '']"
                            class="min-h-[118px] border-r border-b border-line p-1.5 transition-colors [&:nth-child(7n)]:border-r-0"
                            @dragover.prevent="dropDay = d.key"
                            @dragleave="dropDay === d.key && (dropDay = null)"
                            @drop.prevent="drop(d)"
                        >
                            <p :class="d.key === today ? 'bg-ink text-surface' : ''" class="mb-1 grid h-7 w-7 place-items-center rounded-lg text-xs font-bold">{{ d.date.getDate() }}</p>
                            <div class="space-y-1">
                                <button
                                    v-for="g in d.items.slice(0, 3)"
                                    :key="g.id"
                                    type="button"
                                    draggable="true"
                                    :class="g.publish_at ? (g.workflow_status === 'published' ? 'bg-fuchsia-100 text-fuchsia-900 border-fuchsia-300 dark:bg-fuchsia-500/15 dark:text-fuchsia-200 dark:border-fuchsia-500/40' : 'bg-violet-100 text-violet-900 border-violet-300 dark:bg-violet-500/15 dark:text-violet-200 dark:border-violet-500/40') : 'border-dashed border-line text-muted'"
                                    class="block w-full cursor-grab truncate rounded-lg border px-1.5 py-1 text-left text-[11px] font-semibold active:cursor-grabbing"
                                    :title="`${g.keyword} — ${workflowLabel(g.workflow_status)}`"
                                    @dragstart="dragging = g"
                                    @dragend="dragging = null; dropDay = null"
                                    @click="detailId = g.id"
                                >
                                    <span v-if="g.publish_at" class="font-bold">{{ time(g.publish_at) }}</span> {{ g.keyword }}
                                </button>
                                <p v-if="d.items.length > 3" class="px-1 text-[10px] font-bold text-muted">+{{ d.items.length - 3 }} lagi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <GenerationModal :id="detailId" @close="detailId = null" @changed="load(true)" @deleted="load(true)" />
    </div>
</template>
