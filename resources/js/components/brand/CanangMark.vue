<script setup>
/*
 * Logo Ruang Dharma: canang sari dari atas — empat kelopak sesuai arah mata angin
 * (putih timur, merah selatan, kuning barat, biru utara) + kembang rampai di tengah.
 * `lit` = jumlah kelopak menyala (0–5), dipakai juga sebagai indikator tahap generate.
 */
const props = defineProps({
    size: { type: [Number, String], default: 40 },
    lit: { type: Number, default: 5 },
    spin: { type: Boolean, default: false },
    active: { type: Number, default: -1 },
    stroke: { type: String, default: '#15101f' },
});
const petals = [
    { cx: 32, cy: 15, fill: '#f8f5ee' },
    { cx: 49, cy: 32, fill: '#ef4444' },
    { cx: 32, cy: 49, fill: '#facc15' },
    { cx: 15, cy: 32, fill: '#3b82f6' },
];
const uid = `cg${Math.random().toString(36).slice(2, 8)}`;
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 64 64" :class="spin ? 'animate-spin-slow' : ''" aria-hidden="true">
        <defs>
            <linearGradient :id="uid" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#7c3aed" />
                <stop offset="55%" stop-color="#c026d3" />
                <stop offset="100%" stop-color="#f97316" />
            </linearGradient>
        </defs>
        <rect x="6" y="6" width="52" height="52" rx="12" transform="rotate(45 32 32)" fill="none" stroke="currentColor" stroke-opacity=".18" stroke-width="2" />
        <g v-for="(p, i) in petals" :key="i" :class="active === i ? 'petal-on' : ''">
            <ellipse
                :cx="p.cx" :cy="p.cy"
                :rx="i % 2 === 0 ? 8 : 11" :ry="i % 2 === 0 ? 11 : 8"
                :fill="i < lit ? p.fill : 'transparent'"
                :stroke="stroke" stroke-width="2.6"
                class="transition-all duration-500"
            />
        </g>
        <circle cx="32" cy="32" r="8.5" :fill="lit >= 5 ? `url(#${uid})` : 'transparent'" :stroke="stroke" stroke-width="2.6" :class="active === 4 ? 'petal-on' : ''" class="transition-all duration-500" />
        <circle v-if="lit >= 5" cx="29.5" cy="29.5" r="2" fill="#fff" fill-opacity=".85" />
    </svg>
</template>
