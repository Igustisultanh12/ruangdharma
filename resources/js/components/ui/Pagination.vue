<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    meta: { type: Object, required: true },
    label: { type: String, default: 'data' },
});
const emit = defineEmits(['go']);
const pages = computed(() => {
    const { page, lastPage } = props.meta;
    const out = [];
    const add = (p) => out.push(p);
    for (let p = 1; p <= lastPage; p++) {
        if (p === 1 || p === lastPage || Math.abs(p - page) <= 1) add(p);
        else if (out[out.length - 1] !== '…') add('…');
    }
    return out;
});
</script>

<template>
    <div v-if="meta.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 text-xs text-muted sm:px-6">
        <p>Menampilkan <b class="text-ink">{{ meta.from }}–{{ meta.to }}</b> dari <b class="text-ink">{{ meta.total }}</b> {{ label }}</p>
        <div v-if="meta.lastPage > 1" class="flex items-center gap-1">
            <button type="button" class="btn btn-ghost btn-icon btn-sm" :disabled="meta.page <= 1" aria-label="Halaman sebelumnya" @click="emit('go', meta.page - 1)"><Icon name="chevron-left" class="h-4 w-4" /></button>
            <template v-for="(p, i) in pages" :key="i">
                <span v-if="p === '…'" class="px-1">…</span>
                <button v-else type="button" :class="p === meta.page ? 'bg-ink text-surface' : 'hover:bg-surface-2 text-ink'" class="h-8 min-w-8 rounded-lg px-2 text-xs font-bold transition" @click="emit('go', p)">{{ p }}</button>
            </template>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" :disabled="meta.page >= meta.lastPage" aria-label="Halaman berikutnya" @click="emit('go', meta.page + 1)"><Icon name="chevron-right" class="h-4 w-4" /></button>
        </div>
    </div>
</template>
