<script setup>
import { computed, onMounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import api, { errorMessage } from '@/lib/api.js';
import { modelColor, modelLabel } from '@/lib/format.js';

const all = ref([]);
const loading = ref(true);
const error = ref('');
const q = ref('');
const tag = ref('');
const accents = ['from-violet-500 to-fuchsia-500', 'from-orange-400 to-rose-500', 'from-sky-400 to-indigo-500', 'from-emerald-400 to-teal-500', 'from-amber-400 to-orange-500', 'from-fuchsia-500 to-pink-500'];

onMounted(async () => {
    try {
        all.value = (await api.get('/templates')).data.data || [];
    } catch (e) {
        error.value = errorMessage(e, 'Gagal memuat template.');
    } finally {
        loading.value = false;
    }
});
const tags = computed(() => [...new Set(all.value.flatMap((t) => t.tags || []))].sort());
const filtered = computed(() => {
    const s = q.value.trim().toLowerCase();
    return all.value.filter((t) => (!tag.value || (t.tags || []).includes(tag.value)) && (!s || `${t.judul} ${t.deskripsi} ${(t.tags || []).join(' ')}`.toLowerCase().includes(s)));
});
</script>

<template>
    <div>
        <PageHeader title="Template & ide" description="Tema siap pakai lengkap dengan durasi dan gaya yang paling cocok. Pilih satu, lalu sesuaikan sebelum membuat video." />

        <div class="mb-5 flex flex-wrap items-center gap-3">
            <SearchInput v-model="q" placeholder="Cari tema, mis. sembahyang" class="w-full sm:w-72" />
            <div class="flex flex-wrap gap-1.5">
                <button type="button" :class="!tag ? 'chip-active' : ''" class="chip" @click="tag = ''">Semua</button>
                <button v-for="t in tags" :key="t" type="button" :class="tag === t ? 'chip-active' : ''" class="chip" @click="tag = tag === t ? '' : t">#{{ t }}</button>
            </div>
        </div>

        <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"><div v-for="i in 6" :key="i" class="skeleton h-48 rounded-3xl"></div></div>
        <p v-else-if="error" class="text-sm text-rose-600">{{ error }}</p>
        <EmptyState v-else-if="!filtered.length" icon="lightbulb" title="Tema tidak ditemukan" description="Coba kata lain — atau langsung buat video dengan tema bebas.">
            <RouterLink :to="{ path: '/generate', query: q ? { keyword: q } : {} }" class="btn btn-primary btn-sm"><Icon name="wand-sparkles" class="h-4 w-4" /> Buat dengan tema ini</RouterLink>
        </EmptyState>
        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="(t, i) in filtered" :key="t.keyword" class="group card card-lift flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <span :class="accents[i % accents.length]" class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br text-white shadow-lg transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6"><Icon name="flower-2" class="h-6 w-6" /></span>
                    <div class="flex gap-1.5">
                        <Badge :color="modelColor(t.default_model)" size="xs">{{ modelLabel(t.default_model) }}</Badge>
                        <Badge size="xs">{{ t.default_durasi }} dtk</Badge>
                    </div>
                </div>
                <h3 class="mt-4 font-display text-xl font-bold">{{ t.judul }}</h3>
                <p class="mt-1 flex-1 text-sm text-muted">{{ t.deskripsi }}</p>
                <div class="mt-3 flex flex-wrap gap-1">
                    <button v-for="tg in t.tags" :key="tg" type="button" class="text-xs font-semibold text-violet-600 hover:underline dark:text-violet-300" @click="tag = tg">#{{ tg }}</button>
                </div>
                <RouterLink :to="{ path: '/generate', query: { keyword: t.keyword, durasi: t.default_durasi, model: t.default_model } }" class="btn btn-secondary btn-sm mt-4 group-hover:border-ink group-hover:bg-yellow-300 group-hover:text-zinc-950">
                    <Icon name="wand-sparkles" class="h-4 w-4" /> Pakai template
                </RouterLink>
            </article>
        </div>
    </div>
</template>
