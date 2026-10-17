<script setup>
import CanangMark from '@/components/brand/CanangMark.vue';
import Logo from '@/components/brand/Logo.vue';
import Icon from '@/components/ui/Icon.vue';
import { useUiStore } from '@/stores/ui.js';

const ui = useUiStore();
const features = [
    { icon: 'wand-sparkles', text: 'Naskah 6 bagian, prompt visual 9:16 & caption sekali klik' },
    { icon: 'list-checks', text: 'Alur review: draf, revisi, disetujui, terbit' },
    { icon: 'radio', text: 'Semua menu terbarui otomatis tanpa refresh' },
];
</script>

<template>
    <div class="grid grid-cols-1 min-h-screen lg:grid-cols-[1.05fr_1fr]">
        <aside class="animate-gradient bg-brand-gradient relative hidden overflow-hidden p-12 text-white lg:flex lg:flex-col">
            <div class="dot-bg absolute inset-0 opacity-25"></div>
            <div class="animate-float absolute -right-24 -bottom-24 h-96 w-96 rounded-full border-[28px] border-white/10"></div>
            <div class="animate-float-slow absolute top-1/3 -left-20 h-64 w-64 rounded-full bg-yellow-300/30 blur-3xl"></div>
            <div class="absolute top-16 right-12 rotate-12 opacity-90"><CanangMark :size="132" /></div>
            <Logo light class="relative" />
            <div class="relative mt-auto max-w-md">
                <h1 class="font-display text-5xl leading-[1.05] font-bold">Cerita dharma untuk anak, siap tayang hari ini.</h1>
                <p class="mt-4 text-white/85">Ketik satu kata kunci — Rwa Bhineda, Canang Sari, Tri Sandhya — dan studio menyusun naskah, prompt visual, serta caption Instagram yang sesuai aturan Ruang Dharma.</p>
                <ul class="mt-8 grid gap-3">
                    <li v-for="(f, i) in features" :key="f.text" :style="{ animationDelay: `${i * 120}ms` }" class="animate-fade-up flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-4 py-3 backdrop-blur">
                        <Icon :name="f.icon" class="h-5 w-5 text-yellow-300" />
                        <span class="text-sm font-semibold">{{ f.text }}</span>
                    </li>
                </ul>
            </div>
        </aside>
        <main class="relative flex flex-col px-5 py-6 sm:px-10">
            <div class="flex items-center justify-between">
                <Logo class="lg:invisible" />
                <button type="button" class="btn btn-ghost btn-icon" :aria-label="ui.dark ? 'Mode terang' : 'Mode gelap'" @click="ui.toggleDark()"><Icon :name="ui.dark ? 'sun' : 'moon'" class="h-5 w-5" /></button>
            </div>
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                <slot />
            </div>
            <p class="text-center text-xs text-muted">© {{ new Date().getFullYear() }} Ruang Dharma · @ruangdharma_</p>
        </main>
    </div>
</template>
