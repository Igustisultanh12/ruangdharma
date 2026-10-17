<template>
  <div class="flex items-center gap-4">
    <div class="relative" :style="{ width: size + 'px', height: size + 'px' }">
      <svg :width="size" :height="size" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="42" fill="none" stroke="#e2e8f0" :stroke-width="stroke" />
        <circle
          v-for="(seg, i) in segments" :key="seg.label"
          cx="50" cy="50" r="42" fill="none" :stroke="seg.color" :stroke-width="stroke"
          :stroke-dasharray="`${seg.fraction * circumference} ${circumference}`"
          :stroke-dashoffset="`${-offset(i) * circumference}`"
          transform="rotate(-90 50 50)" stroke-linecap="butt"
        />
      </svg>
      <div class="absolute inset-0 flex flex-col items-center justify-center">
        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ total }}</span>
        <span class="text-[10px] text-slate-500 dark:text-slate-400">total</span>
      </div>
    </div>
    <ul class="space-y-1 text-sm">
      <li v-for="seg in segments" :key="seg.label" class="flex items-center gap-2">
        <span class="h-2.5 w-2.5 rounded-full" :style="{ background: seg.color }"></span>
        <span class="text-slate-600 dark:text-slate-300">{{ seg.label }}</span>
        <span class="font-semibold text-slate-900 dark:text-white">{{ seg.value }}</span>
      </li>
      <li v-if="!segments.length" class="text-slate-500 dark:text-slate-400">Belum ada data.</li>
    </ul>
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({
  data: { type: Array, default: () => [] }, // [{label, value, color}]
  size: { type: Number, default: 120 },
  stroke: { type: Number, default: 14 },
});
const circumference = 2 * Math.PI * 42;
const total = computed(() => props.data.reduce((s, d) => s + (d.value || 0), 0));
const segments = computed(() => props.data.filter((d) => d.value > 0).map((d) => ({ ...d, fraction: total.value ? d.value / total.value : 0 })));
function offset(i) {
  return segments.value.slice(0, i).reduce((s, d) => s + d.fraction, 0);
}
</script>
