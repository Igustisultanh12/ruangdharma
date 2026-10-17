<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '@/components/ui/Icon.vue';
import { visibleMenu } from '@/lib/menu.js';
import { useAuthStore } from '@/stores/auth.js';
import { useUiStore } from '@/stores/ui.js';

/* Pencarian menu cepat: Ctrl/⌘ + K. Ketik kata kunci lalu Enter untuk langsung membuat video. */
const ui = useUiStore();
const auth = useAuthStore();
const router = useRouter();
const q = ref('');
const idx = ref(0);
const input = ref(null);

const all = computed(() => visibleMenu((r) => auth.hasAnyRole(r)).flatMap((g) => g.items.map((i) => ({ ...i, group: g.title }))));
const results = computed(() => {
    const s = q.value.trim().toLowerCase();
    const list = s ? all.value.filter((i) => `${i.label} ${i.group}`.toLowerCase().includes(s)) : all.value;
    const out = list.slice(0, 8);
    if (s) out.push({ label: `Buat video "${q.value.trim()}"`, icon: 'wand-sparkles', to: { path: '/generate', query: { keyword: q.value.trim() } }, group: 'Aksi cepat' });
    return out;
});
watch(q, () => (idx.value = 0));
watch(
    () => ui.paletteOpen,
    async (v) => {
        if (v) {
            q.value = '';
            await nextTick();
            input.value?.focus();
        }
    },
);
function go(item) {
    if (!item) return;
    ui.paletteOpen = false;
    router.push(item.to);
}
function onKey(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        ui.paletteOpen = !ui.paletteOpen;
    } else if (ui.paletteOpen) {
        if (e.key === 'Escape') ui.paletteOpen = false;
        if (e.key === 'ArrowDown') { e.preventDefault(); idx.value = (idx.value + 1) % results.value.length; }
        if (e.key === 'ArrowUp') { e.preventDefault(); idx.value = (idx.value - 1 + results.value.length) % results.value.length; }
        if (e.key === 'Enter') { e.preventDefault(); go(results.value[idx.value]); }
    }
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="duration-150" enter-from-class="opacity-0" leave-active-class="duration-100" leave-to-class="opacity-0">
            <div v-if="ui.paletteOpen" class="fixed inset-0 z-[80] flex items-start justify-center bg-zinc-950/50 px-4 pt-[12vh] backdrop-blur-sm" @mousedown.self="ui.paletteOpen = false">
                <div class="animate-pop-in w-full max-w-lg overflow-hidden rounded-3xl border-2 border-ink bg-card shadow-brutal-lg">
                    <div class="flex items-center gap-3 border-b border-line px-4">
                        <Icon name="search" class="h-5 w-5 text-muted" />
                        <input ref="input" v-model="q" class="h-14 flex-1 bg-transparent text-base outline-none placeholder:text-muted" placeholder="Cari menu atau ketik kata kunci video…" aria-label="Cari" />
                        <span class="kbd">Esc</span>
                    </div>
                    <ul class="max-h-80 overflow-y-auto p-2">
                        <li v-for="(r, i) in results" :key="i">
                            <button type="button" :class="i === idx ? 'bg-ink text-surface' : 'hover:bg-surface-2'" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold" @mouseenter="idx = i" @click="go(r)">
                                <span :class="i === idx ? 'bg-brand-gradient text-white' : 'bg-surface-2'" class="grid h-8 w-8 place-items-center rounded-lg"><Icon :name="r.icon" class="h-4 w-4" /></span>
                                <span class="flex-1 truncate">{{ r.label }}</span>
                                <span :class="i === idx ? 'text-surface/60' : 'text-muted'" class="text-xs">{{ r.group }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
