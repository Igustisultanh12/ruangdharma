<script setup>
import { computed } from 'vue';
import { number } from '@/lib/format.js';

/* Daftar batang horizontal untuk peringkat (kata kunci / pengguna teratas). */
const props = defineProps({
    rows: { type: Array, default: () => [] }, // [{ label, value, sub? }]
    color: { type: String, default: '#7c3aed' },
    unit: { type: String, default: '' },
});
const max = computed(() => Math.max(1, ...props.rows.map((r) => Number(r.value) || 0)));
</script>

<template>
    <ul class="space-y-3">
        <li v-for="(r, i) in rows" :key="i" class="group">
            <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span class="min-w-0 truncate font-semibold">{{ r.label }} <span v-if="r.sub" class="font-normal text-muted">· {{ r.sub }}</span></span>
                <span class="shrink-0 font-bold tabular-nums">{{ number(r.value) }} <span v-if="unit" class="font-normal text-muted">{{ unit }}</span></span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full rounded-full transition-all duration-700 group-hover:brightness-110" :style="{ width: `${(Number(r.value) / max) * 100}%`, background: color }"></div>
            </div>
        </li>
        <li v-if="!rows.length" class="py-4 text-center text-sm text-muted">Belum ada data.</li>
    </ul>
</template>
