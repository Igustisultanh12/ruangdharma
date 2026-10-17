<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label || undefined"
        :disabled="disabled"
        :class="modelValue ? 'bg-brand-gradient border-ink' : 'bg-surface-2 border-line'"
        class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border-2 transition-colors disabled:opacity-50"
        @click="emit('update:modelValue', !modelValue)"
    >
        <span :class="modelValue ? 'translate-x-5' : 'translate-x-0.5'" class="inline-block h-5 w-5 rounded-full border-2 border-ink bg-white shadow transition-transform duration-200"></span>
    </button>
</template>
