<template>
  <div class="card card-hover">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ title }}</p>
        <p class="text-3xl font-bold mt-1 text-slate-900 dark:text-white">{{ value }}</p>
        <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.icon" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="toneClass">
        <slot name="icon" />
      </div>
    </div>
    <div v-if="$slots.footer" class="mt-3"><slot name="footer" /></div>
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ title: String, value: [String, Number], subtitle: String, tone: { type: String, default: 'violet' } });
const toneClass = computed(() => ({
  violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300',
  amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
  emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
  sky: 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300',
  rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
}[props.tone] || 'bg-violet-100 text-violet-700'));
</script>
