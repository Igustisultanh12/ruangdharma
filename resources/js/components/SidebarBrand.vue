<template>
  <div class="flex items-center gap-3 px-5 h-16 border-b border-white/10">
    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-amber-500 text-white font-bold text-lg shrink-0">RD</span>
    <div class="min-w-0">
      <p class="font-bold leading-tight truncate">Ruang Dharma Studio</p>
      <p class="text-[11px] text-slate-400 leading-tight">Generate Naskah, Video, Caption</p>
    </div>
    <button v-if="showClose" @click="$emit('close')" class="ml-auto text-slate-400 hover:text-white text-2xl leading-none" aria-label="Tutup menu">&times;</button>
  </div>
</template>
<script setup>
defineProps({ showClose: { type: Boolean, default: false } });
defineEmits(['close']);
</script>
