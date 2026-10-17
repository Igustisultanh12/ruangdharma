<script setup>
import { badge, dot as dotColor } from '@/lib/colors.js';
import Icon from './Icon.vue';

defineProps({
    color: { type: String, default: 'slate' },
    dot: { type: Boolean, default: false },
    icon: { type: String, default: null },
    size: { type: String, default: 'sm' },
});
</script>

<template>
    <span
        :class="[badge[color] || badge.slate, size === 'xs' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs']"
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full font-bold ring-1 ring-inset"
    >
        <span v-if="dot" :class="dotColor[color] || dotColor.slate" class="h-1.5 w-1.5 rounded-full"></span>
        <Icon v-if="icon" :name="icon" class="h-3.5 w-3.5" />
        <slot />
    </span>
</template>
