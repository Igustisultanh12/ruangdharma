<script setup>
import Icon from './Icon.vue';

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Cari…' },
});
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative">
        <Icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted" />
        <input :value="modelValue" type="search" class="input input-sm pr-9 pl-10" :placeholder="placeholder" @input="emit('update:modelValue', $event.target.value)" />
        <button v-if="modelValue" type="button" class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded-md p-1 text-muted hover:text-ink" aria-label="Hapus pencarian" @click="emit('update:modelValue', '')"><Icon name="x" class="h-3.5 w-3.5" /></button>
    </div>
</template>
