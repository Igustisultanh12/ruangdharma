<script setup>
import CanangMark from './CanangMark.vue';

defineProps({ compact: { type: Boolean, default: false }, light: { type: Boolean, default: false } });
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <span :class="light ? 'bg-white' : 'bg-yellow-300'" class="grid h-10 w-10 place-items-center rounded-xl border-2 border-ink shadow-brutal-sm transition-transform duration-300 group-hover:-rotate-6">
            <CanangMark :size="30" />
        </span>
        <span v-if="!compact" class="leading-none">
            <span :class="light ? 'text-white' : 'text-ink'" class="block font-display text-[17px] font-bold tracking-tight">Ruang Dharma</span>
            <span :class="light ? 'text-white/75' : 'text-muted'" class="mt-1 block text-[11px] font-semibold">Studio video cerita Hindu</span>
        </span>
    </span>
</template>
