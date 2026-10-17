<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
      <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
      <div class="relative w-full bg-white dark:bg-slate-800 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto" :class="sizeClass">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800">
          <h3 class="font-bold text-lg text-slate-900 dark:text-white">{{ title }}</h3>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-2xl leading-none" aria-label="Tutup">&times;</button>
        </div>
        <div class="px-5 py-4"><slot /></div>
        <div v-if="$slots.footer" class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2 sticky bottom-0 bg-white dark:bg-slate-800"><slot name="footer" /></div>
      </div>
    </div>
  </Teleport>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ show: Boolean, title: String, size: { type: String, default: 'md' } });
defineEmits(['close']);
const sizeClass = computed(() => ({ sm: 'sm:max-w-md', md: 'sm:max-w-2xl', lg: 'sm:max-w-4xl' }[props.size] || 'sm:max-w-2xl'));
</script>
