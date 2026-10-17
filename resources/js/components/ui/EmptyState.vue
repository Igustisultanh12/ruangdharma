<script setup>
import Icon from './Icon.vue';

defineProps({
    icon: { type: String, default: 'inbox' },
    title: { type: String, default: 'Belum ada data' },
    description: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="compact ? 'py-8' : 'py-14'" class="flex flex-col items-center justify-center px-6 text-center">
        <div class="relative mb-4">
            <div class="absolute inset-0 rotate-6 rounded-2xl bg-yellow-300"></div>
            <div class="relative grid h-14 w-14 place-items-center rounded-2xl border-2 border-ink bg-card text-ink">
                <Icon :name="icon" class="h-6 w-6" />
            </div>
        </div>
        <p class="font-display text-base font-bold">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
