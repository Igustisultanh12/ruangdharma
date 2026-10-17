<template>
  <div>
    <div class="flex h-40 items-end gap-2 sm:gap-3">
      <div v-for="bar in bars" :key="bar.label" class="flex flex-1 flex-col items-center gap-1.5 min-w-0">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ bar.value }}</span>
        <div class="w-full max-w-10 rounded-t-lg bg-gradient-to-t from-violet-600 to-amber-400 transition-all" :style="{ height: bar.height + '%' }" :title="`${bar.label}: ${bar.value}`"></div>
        <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate w-full text-center">{{ bar.label }}</span>
      </div>
    </div>
    <p v-if="!bars.length" class="text-sm text-slate-500 dark:text-slate-400 text-center py-6">Belum ada data.</p>
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ data: { type: Array, default: () => [] } }); // [{label, value}]
const max = computed(() => Math.max(1, ...props.data.map((d) => d.value || 0)));
const bars = computed(() => props.data.map((d) => ({ ...d, height: Math.max(d.value > 0 ? 6 : 0, (d.value / max.value) * 100) })));
</script>
