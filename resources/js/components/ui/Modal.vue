<script setup>
import { onBeforeUnmount, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    size: { type: String, default: 'md' },
    closable: { type: Boolean, default: true },
});
const emit = defineEmits(['close']);
const widths = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl', xl: 'max-w-5xl' };

function onKey(e) {
    if (e.key === 'Escape' && props.show && props.closable) emit('close');
}
watch(
    () => props.show,
    (v) => {
        document.body.style.overflow = v ? 'hidden' : '';
        if (v) window.addEventListener('keydown', onKey);
        else window.removeEventListener('keydown', onKey);
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="duration-200 ease-out" enter-from-class="opacity-0" leave-active-class="duration-150 ease-in" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-[70] flex items-end justify-center bg-zinc-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-6" @mousedown.self="closable && emit('close')">
                <div :class="widths[size] || widths.md" class="animate-pop-in flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-[2rem] border-2 border-ink bg-card shadow-brutal-lg sm:rounded-[2rem]" role="dialog" aria-modal="true" :aria-label="title">
                    <div v-if="title || $slots.header" class="flex items-start gap-3 border-b border-line px-6 py-4">
                        <div class="min-w-0 flex-1">
                            <slot name="header">
                                <h2 class="font-display text-lg font-bold">{{ title }}</h2>
                                <p v-if="subtitle" class="mt-0.5 text-sm text-muted">{{ subtitle }}</p>
                            </slot>
                        </div>
                        <button v-if="closable" type="button" class="btn btn-ghost btn-icon btn-sm -mr-2" aria-label="Tutup" @click="emit('close')"><Icon name="x" class="h-5 w-5" /></button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-6 py-5"><slot /></div>
                    <div v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-surface-2/50 px-6 py-4"><slot name="footer" /></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
