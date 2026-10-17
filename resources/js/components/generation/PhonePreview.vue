<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CanangMark from '@/components/brand/CanangMark.vue';
import Icon from '@/components/ui/Icon.vue';
import { partText } from '@/lib/format.js';

/*
 * Pratinjau 9:16 ala story: tiap bagian naskah tampil sebagai subtitle dengan
 * durasi sesuai `estimasi_detik`. Bisa diputar otomatis, dijeda, atau dipindah manual.
 */
const props = defineProps({
    naskah: { type: Array, default: () => [] },
    title: { type: String, default: '' },
    model: { type: String, default: 'narasi' },
});
const current = ref(0);
const playing = ref(false);
const progress = ref(0); // 0..1 untuk bagian aktif
let raf = null;
let startedAt = 0;
let offset = 0;

const part = computed(() => props.naskah[current.value] || null);
const seconds = (n) => Math.max(3, Number(n?.estimasi_detik) || 10);
const scenes = {
    Narator: 'from-violet-600 via-fuchsia-600 to-orange-500',
    Ibu: 'from-orange-500 via-rose-500 to-fuchsia-600',
    Anak: 'from-sky-500 via-indigo-500 to-violet-600',
};
const scene = computed(() => scenes[part.value?.speaker] || scenes.Narator);

function tick(now) {
    const dur = seconds(part.value) * 1000;
    progress.value = Math.min(1, offset + (now - startedAt) / dur);
    if (progress.value >= 1) {
        if (current.value < props.naskah.length - 1) {
            current.value++;
            offset = 0;
            startedAt = now;
        } else {
            playing.value = false;
            return;
        }
    }
    raf = requestAnimationFrame(tick);
}
function play() {
    if (!props.naskah.length) return;
    if (current.value === props.naskah.length - 1 && progress.value >= 1) {
        current.value = 0;
        progress.value = 0;
    }
    playing.value = true;
    offset = progress.value;
    startedAt = performance.now();
    raf = requestAnimationFrame(tick);
}
function pause() {
    playing.value = false;
    cancelAnimationFrame(raf);
}
function go(i) {
    const wasPlaying = playing.value;
    pause();
    current.value = Math.max(0, Math.min(props.naskah.length - 1, i));
    progress.value = 0;
    if (wasPlaying) play();
}
watch(() => props.naskah, () => {
    pause();
    current.value = 0;
    progress.value = 0;
});
onBeforeUnmount(() => cancelAnimationFrame(raf));
</script>

<template>
    <div class="mx-auto w-full max-w-[300px]">
        <div class="relative aspect-[9/16] overflow-hidden rounded-[2.2rem] border-[6px] border-ink bg-ink shadow-brutal-lg">
            <div :class="scene" class="absolute inset-0 bg-gradient-to-br transition-all duration-700"></div>
            <div class="dot-bg absolute inset-0 opacity-20"></div>
            <div class="animate-float absolute top-1/4 left-1/2 -translate-x-1/2 opacity-90"><CanangMark :size="96" /></div>

            <!-- Segmen progres -->
            <div class="absolute inset-x-3 top-3 flex gap-1">
                <button v-for="(n, i) in naskah" :key="i" type="button" class="h-1 flex-1 overflow-hidden rounded-full bg-white/35" :aria-label="`Bagian ${i + 1}`" @click="go(i)">
                    <span class="block h-full bg-white" :style="{ width: i < current ? '100%' : i === current ? `${progress * 100}%` : '0%' }"></span>
                </button>
            </div>
            <div class="absolute inset-x-3 top-6 flex items-center justify-between text-white">
                <span class="max-w-[70%] truncate text-[11px] font-bold drop-shadow">{{ title }}</span>
                <span class="rounded-full bg-black/30 px-2 py-0.5 text-[10px] font-bold">{{ current + 1 }}/{{ naskah.length }}</span>
            </div>

            <!-- Tap kiri / kanan -->
            <button type="button" class="absolute inset-y-12 left-0 w-1/3" aria-label="Bagian sebelumnya" @click="go(current - 1)"></button>
            <button type="button" class="absolute inset-y-12 right-0 w-1/3" aria-label="Bagian berikutnya" @click="go(current + 1)"></button>

            <!-- Subtitle -->
            <div class="absolute inset-x-3 bottom-16">
                <Transition mode="out-in" enter-active-class="transition duration-300" enter-from-class="opacity-0 translate-y-3" leave-active-class="transition duration-150" leave-to-class="opacity-0">
                    <div v-if="part" :key="current" class="rounded-2xl bg-black/45 p-3 text-white backdrop-blur-sm">
                        <span class="inline-flex items-center gap-1 rounded-full bg-yellow-300 px-2 py-0.5 text-[10px] font-extrabold text-zinc-900">
                            <Icon :name="part.speaker === 'Anak' ? 'baby' : part.speaker === 'Ibu' ? 'heart-handshake' : 'mic'" class="h-3 w-3" /> {{ part.speaker }}
                        </span>
                        <p class="mt-1.5 text-[13px] leading-snug font-semibold">{{ partText(part) }}</p>
                    </div>
                </Transition>
            </div>

            <div class="absolute inset-x-0 bottom-4 flex items-center justify-center gap-3">
                <button type="button" class="grid h-9 w-9 place-items-center rounded-full bg-white/20 text-white backdrop-blur hover:bg-white/30" aria-label="Sebelumnya" @click="go(current - 1)"><Icon name="skip-back" class="h-4 w-4" /></button>
                <button type="button" class="grid h-11 w-11 place-items-center rounded-full border-2 border-ink bg-yellow-300 text-zinc-900 shadow-brutal-sm" :aria-label="playing ? 'Jeda' : 'Putar'" @click="playing ? pause() : play()">
                    <Icon :name="playing ? 'pause' : 'play'" class="h-5 w-5" />
                </button>
                <button type="button" class="grid h-9 w-9 place-items-center rounded-full bg-white/20 text-white backdrop-blur hover:bg-white/30" aria-label="Berikutnya" @click="go(current + 1)"><Icon name="skip-forward" class="h-4 w-4" /></button>
            </div>
        </div>
        <p class="mt-3 text-center text-xs text-muted">Pratinjau subtitle per bagian (±{{ naskah.reduce((s, n) => s + seconds(n), 0) }} detik). Video AI penuh dibuat dari prompt visual.</p>
    </div>
</template>
