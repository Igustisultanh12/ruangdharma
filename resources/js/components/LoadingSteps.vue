<template>
  <ol class="space-y-3">
    <li v-for="(s, i) in steps" :key="s" class="flex items-start gap-3">
      <span
        class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-bold"
        :class="i < current ? 'bg-emerald-100 border-emerald-300 text-emerald-700' : i === current ? 'bg-violet-100 border-violet-300 text-violet-700' : 'bg-slate-100 border-slate-300 text-slate-400'"
      >
        <svg v-if="i < current" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        <svg v-else-if="i === current" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 3a9 9 0 109 9" /></svg>
        <span v-else>{{ i + 1 }}</span>
      </span>
      <span :class="i === current ? 'font-semibold text-violet-700' : i < current ? 'text-slate-700' : 'text-slate-400'">{{ s }}</span>
    </li>
  </ol>
</template>
<script setup>
defineProps({ steps: { type: Array, default: () => [] }, current: { type: Number, default: 0 } });
</script>
