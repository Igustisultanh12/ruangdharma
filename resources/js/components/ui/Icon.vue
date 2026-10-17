<script setup>
import { computed } from 'vue';
import { icons } from '@/lib/icons.js';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: null },
    stroke: { type: [Number, String], default: 2 },
});
const body = computed(() => icons[props.name] || icons['circle-question-mark'] || '');
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        :stroke-width="stroke"
        stroke-linecap="round"
        stroke-linejoin="round"
        :width="size || undefined"
        :height="size || undefined"
        aria-hidden="true"
        class="shrink-0"
        v-html="body"
    />
</template>
