<script setup>
import { onMounted, reactive, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import Modal from '@/components/ui/Modal.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Toggle from '@/components/ui/Toggle.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { dateTime, relative } from '@/lib/format.js';
import { confirmDialog, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const EVENTS = [
    { value: 'generation.completed', label: 'Video selesai dibuat' },
    { value: 'generation.published', label: 'Video diterbitkan' },
    { value: '*', label: 'Semua event' },
];
const hooks = ref([]);
const loading = ref(true);
const logsFor = ref(null);
const logs = ref([]);
async function load() {
    try {
        hooks.value = (await api.get('/webhooks')).data.data.data;
        if (logsFor.value) openLogs(logsFor.value, true);
    } finally {
        loading.value = false;
    }
}
onMounted(load);
useRealtime(['webhooks'], load);

const modal = ref(false);
const saving = ref(false);
const errors = ref({});
const form = reactive({ id: null, url: '', events: ['generation.completed'], is_active: true });
function open(h = null) {
    Object.assign(form, h ? { id: h.id, url: h.url, events: [...(h.events || [])], is_active: h.is_active } : { id: null, url: '', events: ['generation.completed'], is_active: true });
    errors.value = {};
    modal.value = true;
}
function toggleEvent(v) {
    const i = form.events.indexOf(v);
    i >= 0 ? form.events.splice(i, 1) : form.events.push(v);
}
async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { url: form.url, events: form.events, is_active: form.is_active };
        if (form.id) await api.put(`/webhooks/${form.id}`, payload);
        else await api.post('/webhooks', payload);
        toast.success(form.id ? 'Webhook diperbarui.' : 'Webhook dibuat.');
        modal.value = false;
        load();
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
async function sendTest(h) {
    try {
        const { data } = await api.post(`/webhooks/${h.id}/test`);
        const code = data.data?.status_code;
        code >= 200 && code < 300 ? toast.success(`Tes terkirim · HTTP ${code}`) : toast.warning(`Tes terkirim tapi dijawab HTTP ${code || 'gagal'}: ${data.data?.response?.slice(0, 120) || ''}`);
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
async function remove(h) {
    if (!(await confirmDialog({ title: 'Hapus webhook?', message: h.url, tone: 'danger', confirmText: 'Hapus' }))) return;
    try {
        await api.delete(`/webhooks/${h.id}`);
        toast.success('Webhook dihapus.');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
async function openLogs(h, silent = false) {
    logsFor.value = h;
    if (!silent) logs.value = [];
    try {
        logs.value = (await api.get(`/webhooks/${h.id}/logs`)).data.data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <PageHeader title="Webhook" description="Kirim kabar otomatis ke aplikasi lain (mis. Zapier, n8n) setiap ada video baru atau terbit.">
            <template #actions><button type="button" class="btn btn-primary" @click="open()"><Icon name="plus" class="h-4 w-4" /> Webhook baru</button></template>
        </PageHeader>

        <div class="card overflow-hidden">
            <div v-if="loading" class="space-y-3 p-6"><div v-for="i in 3" :key="i" class="skeleton h-14"></div></div>
            <EmptyState v-else-if="!hooks.length" icon="webhook" title="Belum ada webhook" description="Tambahkan URL tujuan untuk menerima event dari studio." />
            <ul v-else class="divide-y divide-line">
                <li v-for="h in hooks" :key="h.id" class="flex flex-wrap items-center gap-4 px-5 py-4">
                    <span :class="h.is_active ? 'bg-lime-300 border-ink text-zinc-900' : 'bg-surface-2 border-line text-muted'" class="grid h-10 w-10 place-items-center rounded-xl border-2"><Icon name="webhook" class="h-4 w-4" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-mono text-sm font-bold">{{ h.url }}</p>
                        <div class="mt-1 flex flex-wrap gap-1"><Badge v-for="e in h.events" :key="e" color="violet" size="xs">{{ e }}</Badge><span class="text-xs text-muted">· {{ h.logs_count }} pengiriman</span></div>
                    </div>
                    <div class="flex gap-1">
                        <button type="button" class="btn btn-secondary btn-sm" @click="sendTest(h)"><Icon name="send-horizontal" class="h-4 w-4" /> Tes</button>
                        <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Log" title="Log pengiriman" @click="openLogs(h)"><Icon name="scroll-text" class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Edit" @click="open(h)"><Icon name="pencil" class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-soft-danger btn-icon btn-sm" aria-label="Hapus" @click="remove(h)"><Icon name="trash-2" class="h-4 w-4" /></button>
                    </div>
                </li>
            </ul>
        </div>

        <Modal :show="modal" :title="form.id ? 'Edit webhook' : 'Webhook baru'" size="md" @close="modal = false">
            <form id="wh-form" class="space-y-4" @submit.prevent="save">
                <div><label class="label" for="wh-url">URL tujuan</label><input id="wh-url" v-model="form.url" type="url" class="input font-mono" placeholder="https://contoh.com/webhook" required /><p v-if="errors.url" class="field-error">{{ errors.url }}</p></div>
                <div>
                    <p class="label">Event</p>
                    <div class="space-y-2">
                        <label v-for="e in EVENTS" :key="e.value" class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-line p-3 transition hover:border-violet-300">
                            <input type="checkbox" class="checkbox" :checked="form.events.includes(e.value)" @change="toggleEvent(e.value)" />
                            <span class="flex-1 text-sm font-semibold">{{ e.label }}</span>
                            <code class="font-mono text-xs text-muted">{{ e.value }}</code>
                        </label>
                    </div>
                    <p v-if="errors.events" class="field-error">{{ errors.events }}</p>
                </div>
                <div class="flex items-center justify-between rounded-2xl bg-surface-2 p-3"><span class="text-sm font-semibold">Aktif</span><Toggle v-model="form.is_active" label="Webhook aktif" /></div>
            </form>
            <template #footer>
                <button type="button" class="btn btn-ghost" @click="modal = false">Batal</button>
                <button type="submit" form="wh-form" class="btn btn-primary" :disabled="saving || !form.events.length"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan</button>
            </template>
        </Modal>

        <Modal :show="!!logsFor" title="Log pengiriman" :subtitle="logsFor?.url" size="lg" @close="logsFor = null">
            <ul v-if="logs.length" class="space-y-2">
                <li v-for="l in logs" :key="l.id" class="rounded-2xl border border-line p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :color="l.status_code >= 200 && l.status_code < 300 ? 'emerald' : 'rose'" size="xs">HTTP {{ l.status_code || 'gagal' }}</Badge>
                        <code class="font-mono text-xs">{{ l.event }}</code>
                        <span class="ml-auto text-xs text-muted" :title="dateTime(l.created_at)">{{ relative(l.created_at) }}</span>
                    </div>
                    <pre v-if="l.response" class="mt-2 max-h-28 overflow-auto rounded-xl bg-surface-2 p-2 font-mono text-[11px] whitespace-pre-wrap">{{ l.response }}</pre>
                </li>
            </ul>
            <EmptyState v-else compact icon="scroll-text" title="Belum ada pengiriman" description="Tekan Tes untuk mengirim contoh event." />
        </Modal>
    </div>
</template>
