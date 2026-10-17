<script setup>
import { computed } from 'vue';
import { initials } from '@/lib/format.js';

const props = defineProps({
    user: { type: Object, default: null },
    name: { type: String, default: '' },
    size: { type: String, default: 'md' },
});
const label = computed(() => props.user?.name || props.name || '?');
const url = computed(() => props.user?.avatar_url || (props.user?.avatar ? props.user.avatar : null));
const sizes = { xs: 'h-6 w-6 text-[10px] rounded-lg', sm: 'h-8 w-8 text-xs rounded-xl', md: 'h-10 w-10 text-sm rounded-xl', lg: 'h-16 w-16 text-xl rounded-2xl', xl: 'h-24 w-24 text-3xl rounded-3xl' };
const palettes = ['from-violet-500 to-fuchsia-500', 'from-orange-400 to-rose-500', 'from-sky-400 to-indigo-500', 'from-emerald-400 to-teal-500', 'from-amber-400 to-orange-500', 'from-fuchsia-500 to-pink-500'];
const palette = computed(() => {
    let h = 0;
    for (const c of label.value) h = (h * 31 + c.charCodeAt(0)) >>> 0;
    return palettes[h % palettes.length];
});
</script>

<template>
    <span :class="sizes[size] || sizes.md" class="relative inline-grid shrink-0 place-items-center overflow-hidden font-bold text-white select-none">
        <img v-if="url" :src="url" :alt="label" class="h-full w-full object-cover" />
        <span v-else :class="palette" class="grid h-full w-full place-items-center bg-gradient-to-br">{{ initials(label) }}</span>
    </span>
</template>
