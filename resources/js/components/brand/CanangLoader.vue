<script setup>
import Icon from '@/components/ui/Icon.vue';
import CanangMark from './CanangMark.vue';

/* Loader 5 tahap generate: setiap tahap menyalakan satu kelopak canang. */
defineProps({
    steps: { type: Array, required: true },
    current: { type: Number, default: 0 },
    done: { type: Boolean, default: false },
});
const petalNames = ['Putih · timur', 'Merah · selatan', 'Kuning · barat', 'Biru · utara', 'Rampai · tengah'];
</script>

<template>
    <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center">
        <div class="relative grid h-40 w-40 shrink-0 place-items-center rounded-full bg-surface-2">
            <div class="absolute inset-3 rounded-full border-2 border-dashed border-line animate-spin-slow"></div>
            <CanangMark :size="120" :lit="done ? 5 : current + 1" :active="done ? -1 : current" stroke="currentColor" class="text-ink" />
        </div>
        <ol class="w-full flex-1 space-y-2">
            <li v-for="(s, i) in steps" :key="s" class="flex items-center gap-3 text-sm">
                <span
                    :class="done || i < current ? 'bg-emerald-400 border-ink text-zinc-950' : i === current ? 'bg-yellow-300 border-ink text-zinc-950 animate-pulse' : 'border-line text-muted'"
                    class="grid h-7 w-7 shrink-0 place-items-center rounded-lg border-2 text-xs font-extrabold transition"
                >
                    <Icon v-if="done || i < current" name="check" class="h-4 w-4" :stroke="3" />
                    <span v-else>{{ i + 1 }}</span>
                </span>
                <span :class="i <= current || done ? 'font-bold text-ink' : 'text-muted'" class="flex-1">{{ s }}</span>
                <span class="hidden text-[11px] text-muted sm:inline">{{ petalNames[i] }}</span>
            </li>
        </ol>
    </div>
</template>
