<script setup>
import { dismissToast, toastState } from '@/lib/toast.js';
import Icon from './Icon.vue';

const style = {
    success: { icon: 'circle-check', cls: 'bg-emerald-400' },
    error: { icon: 'circle-x', cls: 'bg-rose-400' },
    warning: { icon: 'triangle-alert', cls: 'bg-amber-300' },
    info: { icon: 'bell-ring', cls: 'bg-sky-300' },
};
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[90] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-5 sm:bottom-5 sm:items-end" aria-live="polite">
        <TransitionGroup enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-4 opacity-0 sm:translate-x-6 sm:translate-y-0" leave-active-class="transition duration-200 ease-in" leave-to-class="opacity-0 scale-95" move-class="transition duration-300">
            <div v-for="t in toastState.items" :key="t.id" class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border-2 border-ink bg-card p-3 pr-2 shadow-brutal">
                <span :class="style[t.type]?.cls" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border-2 border-ink text-zinc-950"><Icon :name="style[t.type]?.icon || 'info'" class="h-4 w-4" /></span>
                <div class="min-w-0 flex-1 py-0.5">
                    <p v-if="t.title" class="text-sm font-bold">{{ t.title }}</p>
                    <p class="text-sm text-ink/80">{{ t.message }}</p>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-surface-2 hover:text-ink" aria-label="Tutup notifikasi" @click="dismissToast(t.id)"><Icon name="x" class="h-4 w-4" /></button>
            </div>
        </TransitionGroup>
    </div>
</template>
