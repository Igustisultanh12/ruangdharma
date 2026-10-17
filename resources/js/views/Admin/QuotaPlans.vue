<script setup>
import { onMounted, reactive, ref } from 'vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import Modal from '@/components/ui/Modal.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Toggle from '@/components/ui/Toggle.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { rupiah } from '@/lib/format.js';
import { confirmDialog, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const plans = ref([]);
const loading = ref(true);
async function load() {
    try {
        plans.value = (await api.get('/admin/quota-plans')).data.data;
    } finally {
        loading.value = false;
    }
}
onMounted(load);
useRealtime(['plans'], load);

const modal = ref(false);
const saving = ref(false);
const errors = ref({});
const form = reactive({ id: null, name: '', daily_limit: 5, price: 0, description: '', is_active: true });
const accents = ['bg-sky-300', 'bg-yellow-300', 'bg-fuchsia-300', 'bg-lime-300', 'bg-orange-300'];
function open(p = null) {
    Object.assign(form, p ? { ...p, description: p.description || '' } : { id: null, name: '', daily_limit: 5, price: 0, description: '', is_active: true });
    errors.value = {};
    modal.value = true;
}
async function save() {
    saving.value = true;
    errors.value = {};
    const payload = { name: form.name, daily_limit: Number(form.daily_limit), price: Number(form.price) || 0, description: form.description || null, is_active: form.is_active };
    try {
        if (form.id) await api.put(`/admin/quota-plans/${form.id}`, payload);
        else await api.post('/admin/quota-plans', payload);
        toast.success(form.id ? 'Paket diperbarui.' : 'Paket dibuat.');
        modal.value = false;
        load();
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
async function remove(p) {
    if (!(await confirmDialog({ title: `Hapus paket ${p.name}?`, tone: 'danger', confirmText: 'Hapus' }))) return;
    try {
        await api.delete(`/admin/quota-plans/${p.id}`);
        toast.success('Paket dihapus.');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <div>
        <PageHeader title="Paket kuota" description="Atur batas video per hari. Tetapkan paket ke pengguna dari menu Pengguna.">
            <template #actions><button type="button" class="btn btn-primary" @click="open()"><Icon name="plus" class="h-4 w-4" /> Paket baru</button></template>
        </PageHeader>
        <div v-if="loading" class="grid grid-cols-1 gap-5 md:grid-cols-3"><div v-for="i in 3" :key="i" class="skeleton h-64 rounded-3xl"></div></div>
        <div v-else-if="!plans.length" class="card"><EmptyState icon="gem" title="Belum ada paket" /></div>
        <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="(p, i) in plans" :key="p.id" :class="!p.is_active ? 'opacity-60' : ''" class="card-brutal flex flex-col p-6 transition hover:-translate-y-1">
                <div class="flex items-start justify-between">
                    <span :class="accents[i % accents.length]" class="grid h-12 w-12 place-items-center rounded-2xl border-2 border-ink text-zinc-900"><Icon name="gem" class="h-6 w-6" /></span>
                    <span v-if="!p.is_active" class="rounded-full bg-surface-2 px-2 py-1 text-xs font-bold text-muted">Nonaktif</span>
                </div>
                <h3 class="mt-4 font-display text-2xl font-bold">{{ p.name }}</h3>
                <p class="mt-1 text-sm text-muted">{{ p.description || 'Tanpa deskripsi.' }}</p>
                <div class="mt-5 flex items-baseline gap-1"><span class="font-display text-4xl font-bold">{{ p.daily_limit >= 10000 ? '∞' : p.daily_limit }}</span><span class="text-sm text-muted">video / hari</span></div>
                <p class="mt-1 text-sm font-semibold">{{ p.price ? `${rupiah(p.price)} / bulan` : 'Gratis' }}</p>
                <div class="mt-6 flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm flex-1" @click="open(p)"><Icon name="pencil" class="h-4 w-4" /> Edit</button>
                    <button type="button" class="btn btn-soft-danger btn-icon btn-sm" aria-label="Hapus paket" @click="remove(p)"><Icon name="trash-2" class="h-4 w-4" /></button>
                </div>
            </article>
        </div>

        <Modal :show="modal" :title="form.id ? `Edit paket ${form.name}` : 'Paket baru'" size="sm" @close="modal = false">
            <form id="plan-form" class="space-y-4" @submit.prevent="save">
                <div><label class="label" for="pl-name">Nama paket</label><input id="pl-name" v-model="form.name" class="input" required /><p v-if="errors.name" class="field-error">{{ errors.name }}</p></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="pl-limit">Video / hari</label><input id="pl-limit" v-model="form.daily_limit" type="number" min="1" max="10000" class="input" required /><p v-if="errors.daily_limit" class="field-error">{{ errors.daily_limit }}</p></div>
                    <div><label class="label" for="pl-price">Harga (Rp)</label><input id="pl-price" v-model="form.price" type="number" min="0" step="1000" class="input" /></div>
                </div>
                <div><label class="label" for="pl-desc">Deskripsi</label><textarea id="pl-desc" v-model="form.description" rows="2" class="input"></textarea></div>
                <div class="flex items-center justify-between rounded-2xl bg-surface-2 p-3"><span class="text-sm font-semibold">Paket aktif</span><Toggle v-model="form.is_active" label="Paket aktif" /></div>
            </form>
            <template #footer>
                <button type="button" class="btn btn-ghost" @click="modal = false">Batal</button>
                <button type="submit" form="plan-form" class="btn btn-primary" :disabled="saving"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan</button>
            </template>
        </Modal>
    </div>
</template>
