<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    width: { type: String, default: 'w-56' },
    align: { type: String, default: 'right' },
});
const open = ref(false);
const root = ref(null);
const onDoc = (e) => {
    if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
};
const onKey = (e) => {
    if (e.key === 'Escape') open.value = false;
};
onMounted(() => {
    document.addEventListener('pointerdown', onDoc);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDoc);
    document.removeEventListener('keydown', onKey);
});
defineExpose({ close: () => (open.value = false) });
</script>

<template>
    <div ref="root" class="relative">
        <div @click="open = !open"><slot name="trigger" :open="open" /></div>
        <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95 -translate-y-1" leave-active-class="transition duration-100 ease-in" leave-to-class="opacity-0 scale-95">
            <div v-if="open" :class="[width, align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left']" class="absolute z-50 mt-2 overflow-hidden rounded-2xl border-2 border-ink bg-card shadow-brutal">
                <slot :close="() => (open = false)" />
            </div>
        </Transition>
    </div>
</template>
