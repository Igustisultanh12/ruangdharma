<script setup>
import { computed, ref } from 'vue';
import { chartColors } from '@/lib/colors.js';
import { number } from '@/lib/format.js';

/* Donut + legenda bernilai (identitas tidak hanya lewat warna). */
const props = defineProps({
    rows: { type: Array, default: () => [] }, // [{ label, value, color? }]
    centerLabel: { type: String, default: 'Total' },
    size: { type: Number, default: 168 },
});
const hover = ref(null);
const total = computed(() => props.rows.reduce((s, r) => s + (Number(r.value) || 0), 0));
const R = 70, STROKE = 22, C = 2 * Math.PI * R;
const segs = computed(() => {
    let acc = 0;
    const gap = props.rows.filter((r) => r.value > 0).length > 1 ? 2 : 0;
    return props.rows.map((r, i) => {
        const v = Number(r.value) || 0;
        const len = total.value ? (v / total.value) * C : 0;
        const seg = { ...r, v, i, color: r.color || chartColors[i % chartColors.length], dash: `${Math.max(0, len - gap)} ${C}`, offset: -acc, pct: total.value ? Math.round((v / total.value) * 100) : 0 };
        acc += len;
        return seg;
    });
});
const active = computed(() => (hover.value !== null ? segs.value[hover.value] : null));
</script>

<template>
    <div class="flex flex-wrap items-center justify-center gap-5">
        <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
            <svg viewBox="0 0 180 180" class="h-full w-full -rotate-90" role="img" :aria-label="`Diagram donut, total ${total}`">
                <circle cx="90" cy="90" :r="R" fill="none" class="stroke-surface-2" :stroke-width="STROKE" />
                <circle
                    v-for="s in segs"
                    :key="s.i"
                    cx="90" cy="90" :r="R" fill="none"
                    :stroke="s.color"
                    :stroke-width="hover === s.i ? STROKE + 6 : STROKE"
                    :stroke-dasharray="s.dash"
                    :stroke-dashoffset="s.offset"
                    class="cursor-pointer transition-all duration-300"
                    @mouseenter="hover = s.i" @mouseleave="hover = null"
                />
            </svg>
            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                <div>
                    <p class="font-display text-2xl font-bold tabular-nums">{{ active ? number(active.v) : number(total) }}</p>
                    <p class="max-w-24 truncate text-[11px] font-semibold text-muted">{{ active ? active.label : centerLabel }}</p>
                </div>
            </div>
        </div>
        <ul class="min-w-[170px] flex-1 space-y-1.5">
            <li v-for="s in segs" :key="s.i" :class="hover === s.i ? 'bg-surface-2' : ''" class="flex items-center gap-2.5 rounded-xl px-2 py-1.5 text-sm transition" @mouseenter="hover = s.i" @mouseleave="hover = null">
                <span class="h-3 w-3 shrink-0 rounded-[4px]" :style="{ background: s.color }"></span>
                <span class="min-w-0 flex-1 truncate font-semibold">{{ s.label }}</span>
                <span class="tabular-nums text-muted">{{ s.pct }}%</span>
                <span class="w-8 text-right font-bold tabular-nums">{{ number(s.v) }}</span>
            </li>
            <li v-if="!rows.length" class="text-sm text-muted">Belum ada data.</li>
        </ul>
    </div>
</template>
