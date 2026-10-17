<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { number } from '@/lib/format.js';

/* Grafik batang satu seri (SVG) dengan tooltip hover & label nilai tertinggi. */
const props = defineProps({
    rows: { type: Array, default: () => [] }, // [{ label, value, title? }]
    height: { type: Number, default: 220 },
    color: { type: String, default: '#7c3aed' },
    unit: { type: String, default: 'video' },
    labelEvery: { type: Number, default: 1 },
});
const hover = ref(null);
// Lebar viewBox = lebar asli kontainer agar teks sumbu tetap 11px (tidak ikut membesar).
const box = ref(null);
const width = ref(640);
let ro = null;
onMounted(() => {
    ro = new ResizeObserver(([e]) => (width.value = Math.max(240, Math.round(e.contentRect.width))));
    if (box.value) ro.observe(box.value);
});
onBeforeUnmount(() => ro?.disconnect());
const pad = { t: 22, r: 8, b: 26, l: 30 };
const max = computed(() => Math.max(1, ...props.rows.map((r) => Number(r.value) || 0)));
const niceMax = computed(() => {
    const m = max.value;
    const step = m <= 5 ? 1 : m <= 10 ? 2 : m <= 50 ? 10 : Math.pow(10, Math.floor(Math.log10(m)));
    return Math.ceil(m / step) * step;
});
const ticks = computed(() => [0, 0.5, 1].map((f) => Math.round(niceMax.value * f)));
const plotH = computed(() => props.height - pad.t - pad.b);
const W = computed(() => width.value);
const band = computed(() => (W.value - pad.l - pad.r) / Math.max(1, props.rows.length));
const barW = computed(() => Math.max(3, Math.min(42, band.value - 2)));
const bars = computed(() =>
    props.rows.map((r, i) => {
        const v = Number(r.value) || 0;
        const h = (v / niceMax.value) * plotH.value;
        const x = pad.l + i * band.value + (band.value - barW.value) / 2;
        return { ...r, v, h, x, y: pad.t + plotH.value - h, i };
    }),
);
const peak = computed(() => bars.value.reduce((a, b) => (b.v > (a?.v ?? -1) ? b : a), null));
function barPath(b) {
    const r = Math.min(4, barW.value / 2, b.h);
    const x = b.x, y = b.y, w = barW.value, h = b.h;
    if (h <= 0) return '';
    return `M${x},${y + h} L${x},${y + r} Q${x},${y} ${x + r},${y} L${x + w - r},${y} Q${x + w},${y} ${x + w},${y + r} L${x + w},${y + h} Z`;
}
</script>

<template>
    <div ref="box" class="relative">
        <svg :viewBox="`0 0 ${W} ${height}`" class="w-full overflow-visible" role="img" :aria-label="`Grafik batang, ${rows.length} titik data`">
            <g class="text-line">
                <line v-for="t in ticks" :key="t" :x1="pad.l" :x2="W - pad.r" :y1="pad.t + plotH - (t / niceMax) * plotH" :y2="pad.t + plotH - (t / niceMax) * plotH" stroke="currentColor" :stroke-dasharray="t === 0 ? '' : '3 4'" />
            </g>
            <g class="fill-muted text-[11px]" font-size="11">
                <text v-for="t in ticks" :key="'t' + t" :x="pad.l - 8" :y="pad.t + plotH - (t / niceMax) * plotH + 4" text-anchor="end">{{ t }}</text>
            </g>
            <g>
                <path v-for="b in bars" :key="b.i" :d="barPath(b)" :fill="color" :opacity="hover === null || hover === b.i ? 1 : 0.35" class="transition-opacity" />
                <rect v-for="b in bars" :key="'h' + b.i" :x="pad.l + b.i * band" :y="pad.t" :width="band" :height="plotH + 4" fill="transparent" @mouseenter="hover = b.i" @mouseleave="hover = null" @focus="hover = b.i" @blur="hover = null" tabindex="0" :aria-label="`${b.title || b.label}: ${b.v} ${unit}`" />
            </g>
            <text v-if="peak && peak.v > 0 && hover === null" :x="peak.x + barW / 2" :y="peak.y - 6" text-anchor="middle" font-size="11" font-weight="700" class="fill-ink">{{ peak.v }}</text>
            <g class="fill-muted" font-size="10.5">
                <template v-for="b in bars" :key="'l' + b.i">
                    <text v-if="b.i % labelEvery === 0 || b.i === bars.length - 1" :x="b.x + barW / 2" :y="height - 8" text-anchor="middle">{{ b.label }}</text>
                </template>
            </g>
        </svg>
        <div
            v-if="hover !== null && bars[hover]"
            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-xl border-2 border-ink bg-card px-3 py-1.5 text-xs whitespace-nowrap shadow-brutal-sm"
            :style="{ left: `${((bars[hover].x + barW / 2) / W) * 100}%`, top: `${(bars[hover].y / height) * 100}%`, marginTop: '-8px' }"
        >
            <p class="font-bold">{{ bars[hover].title || bars[hover].label }}</p>
            <p class="text-muted"><b class="text-ink">{{ number(bars[hover].v) }}</b> {{ unit }}</p>
        </div>
    </div>
</template>
