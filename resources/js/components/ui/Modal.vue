<script setup>
import { onBeforeUnmount, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    size: { type: String, default: 'md' },
    closable: { type: Boolean, default: true },
    // Lapisan: dialog konfirmasi memakai 'top' agar selalu tampil DI ATAS modal lain (mis. detail video).
    layer: { type: String, default: 'base' },
});
const emit = defineEmits(['close']);
const widths = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl', xl: 'max-w-5xl' };
const layers = { base: 'z-[70]', top: 'z-[85]' };

/* Tumpukan modal terbuka: Esc hanya menutup modal paling atas, scroll body
   baru dikembalikan setelah modal terakhir tertutup. */
const stack = (window.__rdModalStack ||= []);
const id = Symbol('modal');
function onKey(e) {
    if (e.key === 'Escape' && props.show && props.closable && stack[stack.length - 1] === id) {
        e.stopPropagation();
        emit('close');
    }
}
function open() {
    if (!stack.includes(id)) stack.push(id);
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKey);
}
function close() {
    const i = stack.indexOf(id);
    if (i >= 0) stack.splice(i, 1);
    if (!stack.length) document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
}
watch(() => props.show, (v) => (v ? open() : close()), { immediate: true });
onBeforeUnmount(close);
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="duration-200 ease-out" enter-from-class="opacity-0" leave-active-class="duration-150 ease-in" leave-to-class="opacity-0">
            <div v-if="show" :class="layers[layer] || layers.base" class="fixed inset-0 flex items-end justify-center bg-zinc-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-6" @mousedown.self="closable && emit('close')">
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
