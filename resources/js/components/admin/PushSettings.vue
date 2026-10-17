<script setup>
import { onMounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Toggle from '@/components/ui/Toggle.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { confirmDialog, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

/* Notifikasi push HP (Firebase Cloud Messaging) — sama seperti SISINDEN: tanpa build ulang APK. */
const status = ref(null);
const loading = ref(true);
const saving = ref(false);
const testing = ref(false);
const errors = ref({});
const googleServices = ref('');
const serviceAccount = ref('');

async function load() {
    try {
        status.value = (await api.get('/admin/push-config')).data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}
onMounted(load);
useRealtime(['settings'], () => !saving.value && load());

function readFile(e, which) {
    const target = which === 'gs' ? googleServices : serviceAccount;
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => (target.value = String(reader.result || ''));
    reader.readAsText(file);
    e.target.value = '';
}

async function save(extra = {}) {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { ...extra };
        if (googleServices.value.trim()) payload.google_services = googleServices.value.trim();
        if (serviceAccount.value.trim()) payload.service_account = serviceAccount.value.trim();
        const { data } = await api.post('/admin/push-config', payload);
        status.value = data.data;
        googleServices.value = '';
        serviceAccount.value = '';
        toast.success(data.message);
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
async function clearAll() {
    if (!(await confirmDialog({ title: 'Hapus kredensial push?', message: 'Notifikasi tetap muncul di HP lewat sinkron berkala, tapi tidak lagi instan.', confirmText: 'Hapus', icon: 'trash-2' }))) return;
    save({ clear: true });
}
async function sendTest() {
    testing.value = true;
    try {
        toast.success((await api.post('/push-test')).data.message);
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        testing.value = false;
    }
}
</script>

<template>
    <div class="space-y-5">
        <div v-if="loading" class="skeleton h-64 rounded-3xl"></div>
        <template v-else>
            <section class="card-brutal relative overflow-hidden p-6">
                <div class="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-brand-gradient opacity-15 blur-2xl"></div>
                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <span class="eyebrow"><Icon name="bell-ring" class="h-3.5 w-3.5" /> Push HP</span>
                        <h2 class="mt-3 font-display text-xl font-bold">Notifikasi aplikasi Android</h2>
                        <p class="mt-1 max-w-xl text-sm text-muted">Video selesai, Muse gagal, review & komentar langsung muncul di HP (berbunyi) walau aplikasi ditutup — sama seperti SISINDEN.</p>
                    </div>
                    <Badge :color="status?.ready ? 'emerald' : 'amber'" :icon="status?.ready ? 'circle-check' : 'circle-alert'">{{ status?.ready ? 'Push aktif' : 'Belum aktif' }}</Badge>
                </div>
                <div class="relative mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border-2 border-line p-4"><p class="text-xs font-bold text-muted">google-services.json</p><p class="mt-1 truncate font-semibold">{{ status?.client_project || '— belum diunggah' }}</p></div>
                    <div class="rounded-2xl border-2 border-line p-4"><p class="text-xs font-bold text-muted">Service account</p><p class="mt-1 truncate font-semibold">{{ status?.service_account_project || '— belum diunggah' }}</p></div>
                    <div class="rounded-2xl border-2 border-line p-4"><p class="text-xs font-bold text-muted">HP terdaftar</p><p class="mt-1 font-display text-2xl font-bold">{{ status?.devices ?? 0 }}</p></div>
                </div>
                <div class="relative mt-5 flex flex-wrap items-center gap-3">
                    <Toggle :model-value="status?.enabled_flag" @update:model-value="(v) => save({ enabled: v })" />
                    <span class="text-sm font-semibold">Kirim notifikasi push</span>
                    <button type="button" class="btn btn-brutal btn-sm ml-auto" :disabled="testing || !status?.ready" @click="sendTest"><Spinner v-if="testing" class="h-4 w-4" /><Icon v-else name="send" class="h-4 w-4" /> Kirim tes ke HP saya</button>
                </div>
            </section>

            <form class="card space-y-5 p-6" @submit.prevent="save()">
                <div>
                    <label class="label" for="gs">1. google-services.json <span class="font-mono text-xs font-normal text-muted">package {{ status?.package }}</span></label>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="btn btn-secondary btn-sm cursor-pointer"><Icon name="upload" class="h-4 w-4" /> Pilih berkas<input type="file" accept=".json,application/json" class="hidden" @change="(e) => readFile(e, 'gs')" /></label>
                        <span v-if="googleServices" class="text-xs font-semibold text-emerald-600">Berkas siap disimpan</span>
                    </div>
                    <textarea id="gs" v-model="googleServices" rows="3" class="input mt-2 font-mono text-xs" placeholder='atau tempel isi JSON di sini…'></textarea>
                    <p class="help">Firebase Console → Project settings → aplikasi Android <b>{{ status?.package }}</b> → unduh google-services.json. Tidak perlu build ulang APK.</p>
                    <p v-if="errors.google_services" class="field-error">{{ errors.google_services }}</p>
                </div>
                <div>
                    <label class="label" for="sa">2. Service account (rahasia)</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="btn btn-secondary btn-sm cursor-pointer"><Icon name="key-round" class="h-4 w-4" /> Pilih berkas<input type="file" accept=".json,application/json" class="hidden" @change="(e) => readFile(e, 'sa')" /></label>
                        <span v-if="serviceAccount" class="text-xs font-semibold text-emerald-600">Berkas siap disimpan</span>
                    </div>
                    <textarea id="sa" v-model="serviceAccount" rows="3" class="input mt-2 font-mono text-xs" placeholder='{"type": "service_account", …}'></textarea>
                    <p class="help">Project settings → Service accounts → Generate new private key. Disimpan terenkripsi, tidak pernah dikirim ke HP.</p>
                    <p v-if="errors.service_account" class="field-error">{{ errors.service_account }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
                    <button v-if="status?.client_project || status?.service_account_project" type="button" class="btn btn-soft-danger btn-sm mr-auto" @click="clearAll"><Icon name="trash-2" class="h-4 w-4" /> Hapus kredensial</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving || (!googleServices.trim() && !serviceAccount.trim())"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan kredensial</button>
                </div>
            </form>

            <div class="card p-5 text-sm text-muted">
                <p class="font-bold text-ink">Tanpa Firebase pun notifikasi tetap jalan</p>
                <p class="mt-1">Aplikasi Android memeriksa notifikasi baru setiap beberapa detik saat terbuka dan ±15 menit di latar belakang. Firebase membuatnya <b>instan</b> walau aplikasi ditutup.</p>
            </div>
        </template>
    </div>
</template>
