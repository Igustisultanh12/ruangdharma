<script setup>
import { computed } from 'vue';
import { gradient } from '@/lib/colors.js';
import Icon from './Icon.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: 0 },
    icon: { type: String, default: null },
    color: { type: String, default: 'violet' },
    hint: { type: String, default: '' },
    to: { type: [String, Object], default: null },
    suffix: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});
const isNumber = computed(() => typeof props.value === 'number');
</script>

<template>
    <component :is="to ? 'RouterLink' : 'div'" :to="to || undefined" class="group card card-lift relative block overflow-hidden p-5">
        <div :class="gradient[color] || gradient.violet" class="absolute -top-10 -right-10 h-28 w-28 rounded-full bg-gradient-to-br opacity-15 blur-xl transition-transform duration-500 group-hover:scale-150"></div>
        <div class="relative flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold text-muted">{{ label }}</p>
                <div v-if="loading" class="skeleton mt-2 h-8 w-20"></div>
                <p v-else class="mt-1.5 font-display text-3xl font-bold tabular-nums">
                    <span v-if="isNumber" v-counter="{ to: value, suffix }">{{ value }}{{ suffix }}</span>
                    <span v-else>{{ value }}</span>
                </p>
                <p v-if="hint" class="mt-1 truncate text-xs text-muted">{{ hint }}</p>
            </div>
            <div v-if="icon" :class="gradient[color] || gradient.violet" class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br text-white shadow-lg transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6">
                <Icon :name="icon" class="h-5 w-5" />
            </div>
        </div>
    </component>
</template>
