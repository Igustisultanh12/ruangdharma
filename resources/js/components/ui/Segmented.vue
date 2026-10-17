<script setup>
import Icon from './Icon.vue';

defineProps({
    modelValue: { type: [String, Number, Boolean], default: null },
    options: { type: Array, required: true }, // [{ value, label, icon?, count? }]
});
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="segment max-w-full overflow-x-auto" role="tablist">
        <button
            v-for="o in options"
            :key="String(o.value)"
            type="button"
            role="tab"
            :aria-selected="modelValue === o.value"
            :class="modelValue === o.value ? 'segment-item-active' : ''"
            class="segment-item shrink-0"
            @click="emit('update:modelValue', o.value)"
        >
            <Icon v-if="o.icon" :name="o.icon" class="h-4 w-4" />
            {{ o.label }}
            <span v-if="o.count !== undefined && o.count !== null" :class="modelValue === o.value ? 'bg-yellow-300 text-zinc-900' : 'bg-card text-muted'" class="rounded-full px-1.5 py-0.5 text-[10px] font-extrabold tabular-nums">{{ o.count }}</span>
        </button>
    </div>
</template>
