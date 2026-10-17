<template>
  <div class="flex items-center justify-between gap-3 mt-4 flex-wrap">
    <p class="text-sm text-slate-500 dark:text-slate-400">Halaman {{ page }} dari {{ lastPage }} <span v-if="total">({{ total }} data)</span></p>
    <div class="flex gap-2">
      <button class="btn-secondary btn-sm" :disabled="page <= 1" @click="$emit('prev')">Sebelumnya</button>
      <button class="btn-secondary btn-sm" :disabled="page >= lastPage" @click="$emit('next')">Berikutnya</button>
    </div>
  </div>
</template>
<script setup>
defineProps({ page: { type: Number, default: 1 }, lastPage: { type: Number, default: 1 }, total: { type: Number, default: 0 } });
defineEmits(['prev', 'next']);
</script>
