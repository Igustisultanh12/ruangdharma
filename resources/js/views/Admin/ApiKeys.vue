<script setup>
import { onMounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Toggle from '@/components/ui/Toggle.vue';
import api, { errorMessage } from '@/lib/api.js';
import { dateTime, relative } from '@/lib/format.js';
import { confirmDialog, copyText, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const keys = ref([]);
const loading = ref(true);
const name = ref('');
const creating = ref(false);
const fresh = ref(null);
async function load() {
    try {
        keys.value = (await api.get('/api-keys')).data.data;
    } finally {
        loading.value = false;
    }
}
onMounted(load);
useRealtime(['api_keys'], load);

async function create() {
    if (!name.value.trim()) return;
    creating.value = true;
    try {
        const { data } = await api.post('/api-keys', { name: name.value.trim() });
        fresh.value = data.data;
        name.value = '';
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        creating.value = false;
    }
}
async function toggle(k) {
    k.is_active = !k.is_active;
    try {
        const { data } = await api.post(`/api-keys/${k.id}/toggle`);
        toast.success(data.message);
    } catch (e) {
        k.is_active = !k.is_active;
        toast.error(errorMessage(e));
    }
}
async function remove(k) {
    if (!(await confirmDialog({ title: `Cabut key "${k.name}"?`, message: 'Aplikasi yang memakai key ini langsung ditolak.', tone: 'danger', confirmText: 'Cabut key' }))) return;
    try {
        await api.delete(`/api-keys/${k.id}`);
        toast.success('API key dicabut.');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
const origin = window.location.origin;
</script>

<template>
    <div class="mx-auto max-w-4xl">
        <PageHeader title="API key" description="Kunci untuk aplikasi luar yang membaca riwayat videomu lewat header X-Api-Key." />

        <form class="card mb-6 flex flex-wrap items-end gap-3 p-5" @submit.prevent="create">
            <div class="min-w-[220px] flex-1"><label class="label" for="k-name">Nama key</label><input id="k-name" v-model="name" class="input" maxlength="100" placeholder="mis. Dashboard Instagram" /></div>
            <button type="submit" class="btn btn-primary" :disabled="creating || !name.trim()"><Spinner v-if="creating" class="h-4 w-4" /><Icon v-else name="key-round" class="h-4 w-4" /> Buat key</button>
        </form>

        <div v-if="fresh" class="card-brutal mb-6 bg-yellow-300 p-5 text-zinc-900">
            <p class="flex items-center gap-2 font-bold"><Icon name="triangle-alert" class="h-5 w-5" /> Salin key "{{ fresh.name }}" sekarang — tidak akan ditampilkan lagi.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <code class="min-w-0 flex-1 truncate rounded-xl border-2 border-ink bg-white px-3 py-2 font-mono text-sm">{{ fresh.key }}</code>
                <button type="button" class="btn btn-dark btn-sm" @click="copyText(fresh.key, 'API key')"><Icon name="copy" class="h-4 w-4" /> Salin</button>
                <button type="button" class="btn btn-ghost btn-sm text-zinc-900" @click="fresh = null">Sudah disimpan</button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div v-if="loading" class="space-y-3 p-6"><div v-for="i in 3" :key="i" class="skeleton h-12"></div></div>
            <EmptyState v-else-if="!keys.length" icon="key-round" title="Belum ada API key" description="Buat key pertama untuk menghubungkan aplikasi lain." />
            <ul v-else class="divide-y divide-line">
                <li v-for="k in keys" :key="k.id" class="flex flex-wrap items-center gap-4 px-5 py-4">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-surface-2"><Icon name="key-round" class="h-4 w-4" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-bold">{{ k.name }} <Badge :color="k.is_active ? 'emerald' : 'zinc'" size="xs">{{ k.is_active ? 'Aktif' : 'Nonaktif' }}</Badge></p>
                        <p class="font-mono text-xs text-muted">{{ k.key_preview }}</p>
                        <p class="text-xs text-muted" :title="dateTime(k.created_at)">Dibuat {{ relative(k.created_at) }} · {{ k.last_used_at ? `terakhir dipakai ${relative(k.last_used_at)}` : 'belum pernah dipakai' }}</p>
                    </div>
                    <Toggle :model-value="k.is_active" :label="`Status ${k.name}`" @update:model-value="toggle(k)" />
                    <button type="button" class="btn btn-soft-danger btn-icon btn-sm" aria-label="Cabut key" @click="remove(k)"><Icon name="trash-2" class="h-4 w-4" /></button>
                </li>
            </ul>
        </div>

        <div class="card mt-6 p-6">
            <h2 class="font-display text-lg font-bold">Cara pakai</h2>
            <pre class="mt-3 overflow-x-auto rounded-2xl bg-zinc-950 p-4 font-mono text-xs text-lime-300">curl -H "X-Api-Key: rd_xxxxxxxx" {{ origin }}/api/external/generations?per_page=10</pre>
        </div>
    </div>
</template>
