<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { relative } from '@/lib/format.js';
import { confirmDialog, copyText, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const cfg = ref(null);
const revealed = ref(false);
const fresh = ref(null); // { label, value } setelah generate
const busy = ref('');
const test = ref(null);
const errors = ref({});
const form = reactive({ muse_base_url: '', muse_timeout: 15 });

async function load(show = revealed.value) {
    try {
        cfg.value = (await api.get('/admin/muse-config', { params: { show: show ? 1 : 0 } })).data;
        if (!busy.value) Object.assign(form, { muse_base_url: cfg.value.muse_base_url || '', muse_timeout: cfg.value.muse_timeout || 15 });
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
onMounted(() => load(false));
useRealtime(['muse', 'settings'], () => load());

async function toggleReveal() {
    revealed.value = !revealed.value;
    await load(revealed.value);
}
async function regenerate(kind) {
    const isToken = kind === 'token';
    const ok = await confirmDialog({
        title: isToken ? 'Buat token Muse baru?' : 'Buat webhook secret baru?',
        message: 'Nilai lama langsung tidak berlaku. Pastikan kamu segera memperbarui konfigurasi di sisi Muse.',
        tone: 'danger', confirmText: 'Ya, buat baru', icon: 'key-round',
    });
    if (!ok) return;
    busy.value = kind;
    try {
        const { data } = await api.post(isToken ? '/admin/muse-config/generate-token' : '/admin/muse-config/generate-webhook-secret');
        fresh.value = { label: isToken ? 'MUSE_API_TOKEN' : 'MUSE_WEBHOOK_SECRET', value: isToken ? data.muse_api_token : data.muse_webhook_secret };
        toast.success(data.message);
        await load();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        busy.value = '';
    }
}
async function saveConfig() {
    busy.value = 'save';
    errors.value = {};
    try {
        const { data } = await api.put('/admin/muse-config', { muse_base_url: form.muse_base_url || null, muse_timeout: Number(form.muse_timeout) });
        toast.success(data.message);
        await load();
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        busy.value = '';
    }
}
async function runTest() {
    busy.value = 'test';
    try {
        test.value = (await api.post('/admin/muse-config/test')).data;
    } catch (e) {
        test.value = { connected: false, message: errorMessage(e) };
    } finally {
        busy.value = '';
    }
}
const origin = window.location.origin;
const tokenShown = computed(() => (revealed.value && cfg.value?.muse_api_token) || cfg.value?.muse_api_token_masked || 'TOKEN_KAMU');
const curls = computed(() => [
    { title: 'Cek kesehatan (tanpa token)', cmd: `curl ${origin}/api/muse/health` },
    { title: 'Status koneksi', cmd: `curl -H "X-Muse-Token: ${tokenShown.value}" ${origin}/api/muse/status` },
    { title: 'Generate naskah', cmd: `curl -X POST -H "X-Muse-Token: ${tokenShown.value}" -H "Content-Type: application/json" \\\n  -d '{"keyword":"Rwa Bhineda","durasi":60,"model":"narasi"}' ${origin}/api/muse/generate` },
]);
</script>

<template>
    <div>
        <PageHeader title="Integrasi Muse" description="Kelola token API dan webhook untuk Muse langsung dari sini — tanpa mengedit file .env." />

        <div v-if="!cfg" class="grid grid-cols-1 gap-6 lg:grid-cols-2"><div v-for="i in 4" :key="i" class="skeleton h-48 rounded-3xl"></div></div>
        <template v-else>
            <div v-if="fresh" class="card-brutal mb-6 flex flex-wrap items-center gap-4 bg-yellow-300 p-5 text-zinc-900">
                <Icon name="triangle-alert" class="h-6 w-6" />
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ fresh.label }} baru — salin & kirim ke Muse lewat jalur aman.</p>
                    <code class="mt-1 block truncate font-mono text-sm">{{ fresh.value }}</code>
                </div>
                <button type="button" class="btn btn-dark btn-sm" @click="copyText(fresh.value, fresh.label)"><Icon name="copy" class="h-4 w-4" /> Salin</button>
                <button type="button" class="btn btn-ghost btn-icon btn-sm text-zinc-900" aria-label="Tutup" @click="fresh = null"><Icon name="x" class="h-4 w-4" /></button>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="card p-6 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-lg font-bold">Kredensial</h2>
                        <button type="button" class="btn btn-ghost btn-sm" @click="toggleReveal"><Icon :name="revealed ? 'eye-off' : 'eye'" class="h-4 w-4" /> {{ revealed ? 'Sembunyikan' : 'Tampilkan' }}</button>
                    </div>
                    <div class="mt-4 space-y-4">
                        <div v-for="k in [{ key: 'token', label: 'MUSE_API_TOKEN', has: cfg.has_token, val: cfg.muse_api_token, mask: cfg.muse_api_token_masked, hint: 'Header X-Muse-Token untuk memanggil /api/muse/*' }, { key: 'secret', label: 'MUSE_WEBHOOK_SECRET', has: cfg.has_webhook_secret, val: cfg.muse_webhook_secret, mask: cfg.muse_webhook_secret_masked, hint: 'HMAC X-Muse-Signature untuk /api/muse/webhook' }]" :key="k.key" class="rounded-2xl border-2 border-line p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-mono text-sm font-bold">{{ k.label }}</p>
                                <Badge :color="k.has ? 'emerald' : 'rose'" size="xs" :icon="k.has ? 'circle-check' : 'circle-x'">{{ k.has ? 'Tersedia' : 'Belum dibuat' }}</Badge>
                            </div>
                            <p class="mt-0.5 text-xs text-muted">{{ k.hint }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <code class="min-w-0 flex-1 truncate rounded-xl bg-surface-2 px-3 py-2 font-mono text-sm">{{ (revealed && k.val) || k.mask || '—' }}</code>
                                <button v-if="k.has" type="button" class="btn btn-secondary btn-sm" :disabled="!revealed" :title="revealed ? '' : 'Tampilkan dulu untuk menyalin'" @click="copyText(k.val, k.label)"><Icon name="copy" class="h-4 w-4" /> Salin</button>
                                <button type="button" class="btn btn-brutal btn-sm" :disabled="busy === k.key" @click="regenerate(k.key)"><Spinner v-if="busy === k.key" class="h-4 w-4" /><Icon v-else name="refresh-cw" class="h-4 w-4" /> {{ k.has ? 'Buat ulang' : 'Buat sekarang' }}</button>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-muted">Sumber token aktif: <b class="text-ink">{{ cfg.source === 'database' ? 'menu Admin (terenkripsi di database)' : cfg.source === 'env' ? 'file .env (fallback)' : 'belum ada' }}</b>.</p>
                </div>

                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Status</h2>
                    <div class="mt-4 flex items-center gap-3">
                        <span :class="cfg.status?.connected ? 'bg-emerald-400' : 'bg-rose-400'" class="grid h-12 w-12 place-items-center rounded-2xl border-2 border-ink text-zinc-900"><Icon :name="cfg.status?.connected ? 'plug-zap' : 'unplug'" class="h-6 w-6" /></span>
                        <div><p class="font-bold">{{ cfg.status?.connected ? 'Endpoint siap' : 'Tidak terhubung' }}</p><p class="text-xs text-muted">diperbarui {{ relative(cfg.status?.last_active) }}</p></div>
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-2">
                        <div class="rounded-2xl bg-surface-2 p-3"><dt class="text-[11px] font-bold text-muted">Job antre</dt><dd class="font-display text-xl font-bold">{{ cfg.status?.queue_pending ?? 0 }}</dd></div>
                        <div class="rounded-2xl bg-surface-2 p-3"><dt class="text-[11px] font-bold text-muted">Video hari ini</dt><dd class="font-display text-xl font-bold">{{ cfg.status?.daily_generations ?? 0 }}</dd></div>
                    </dl>
                    <button type="button" class="btn btn-secondary btn-sm mt-4 w-full" :disabled="busy === 'test'" @click="runTest"><Spinner v-if="busy === 'test'" class="h-4 w-4" /><Icon v-else name="activity" class="h-4 w-4" /> Tes koneksi</button>
                    <div v-if="test" :class="test.connected ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300'" class="mt-3 rounded-2xl p-3 text-xs">
                        <p class="font-bold">{{ test.connected ? 'Berhasil' : 'Gagal' }}{{ test.latency_ms !== undefined ? ` · ${test.latency_ms} ms` : '' }}</p>
                        <p>{{ test.message }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <form class="card space-y-4 p-6" @submit.prevent="saveConfig">
                    <h2 class="font-display text-lg font-bold">Endpoint Muse</h2>
                    <div><label class="label" for="mu-url">Base URL Muse</label><input id="mu-url" v-model="form.muse_base_url" class="input" placeholder="Kosong = pakai generator lokal" /><p v-if="errors.muse_base_url" class="field-error">{{ errors.muse_base_url }}</p></div>
                    <div><label class="label" for="mu-timeout">Timeout (detik)</label><input id="mu-timeout" v-model="form.muse_timeout" type="number" min="5" max="60" class="input" /><p v-if="errors.muse_timeout" class="field-error">{{ errors.muse_timeout }}</p></div>
                    <button type="submit" class="btn btn-primary w-full" :disabled="busy === 'save'"><Spinner v-if="busy === 'save'" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan</button>
                </form>
                <div class="card p-6 lg:col-span-2">
                    <h2 class="font-display text-lg font-bold">Contoh pemanggilan</h2>
                    <p class="text-sm text-muted">Tampilkan kredensial untuk menyalin perintah lengkap.</p>
                    <div class="mt-4 space-y-3">
                        <div v-for="c in curls" :key="c.title">
                            <div class="mb-1 flex items-center justify-between"><p class="text-xs font-bold">{{ c.title }}</p><button type="button" class="text-xs font-bold text-violet-600 hover:underline dark:text-violet-300" @click="copyText(c.cmd, 'Perintah')">Salin</button></div>
                            <pre class="overflow-x-auto rounded-2xl bg-zinc-950 p-3 font-mono text-xs leading-relaxed text-lime-300">{{ c.cmd }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
