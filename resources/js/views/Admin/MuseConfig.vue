<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { relative } from '@/lib/format.js';
import { confirmDialog, copyText, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const cfg = ref(null);
const tokenUsage = ref(null);
const tokenLoading = ref(false);
async function loadTokenUsage() { tokenLoading.value = true; try { tokenUsage.value = (await api.get('/admin/muse-config/token-usage')).data.data; } catch (e) {} finally { tokenLoading.value = false; } }
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
onMounted(() => { load(false); loadTokenUsage(); });
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
// ---------- Profil Muse (multi-Muse) ----------
const profiles = ref([]);
const profilesLoading = ref(false);
const showAddProfile = ref(false);
const profileBusy = ref('');
const profileTests = ref({});
// ---------- Reveal token profil (tombol mata, auto-hide 30 detik) ----------
// Nilai lengkap HANYA di state memori komponen ini — tidak pernah disimpan ke
// localStorage/sessionStorage, tidak pernah ditulis ke console.
const REVEAL_TTL_MS = 30000;
const revealedProfiles = ref({}); // { [id]: { api_token, webhook_secret, remaining } }
const revealTimeouts = new Map();
const revealIntervals = new Map();
function hideProfileSecrets(id) {
    if (revealTimeouts.has(id)) { clearTimeout(revealTimeouts.get(id)); revealTimeouts.delete(id); }
    if (revealIntervals.has(id)) { clearInterval(revealIntervals.get(id)); revealIntervals.delete(id); }
    if (revealedProfiles.value[id]) {
        const next = { ...revealedProfiles.value };
        delete next[id];
        revealedProfiles.value = next;
    }
}
function hideAllProfileSecrets() {
    [...revealTimeouts.keys()].forEach((id) => hideProfileSecrets(id));
}
onUnmounted(hideAllProfileSecrets);
async function toggleRevealProfile(p) {
    if (revealedProfiles.value[p.id]) { hideProfileSecrets(p.id); return; }
    profileBusy.value = `reveal-${p.id}`;
    try {
        const { data } = await api.post(`/admin/muse-profiles/${p.id}/reveal`);
        hideProfileSecrets(p.id);
        revealedProfiles.value = {
            ...revealedProfiles.value,
            [p.id]: { api_token: data.data?.api_token || '', webhook_secret: data.data?.webhook_secret || '', remaining: 30 },
        };
        revealIntervals.set(p.id, setInterval(() => {
            const cur = revealedProfiles.value[p.id];
            if (!cur) return;
            const remaining = cur.remaining - 1;
            if (remaining <= 0) { hideProfileSecrets(p.id); return; }
            revealedProfiles.value = { ...revealedProfiles.value, [p.id]: { ...cur, remaining } };
        }, 1000));
        revealTimeouts.set(p.id, setTimeout(() => hideProfileSecrets(p.id), REVEAL_TTL_MS));
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        profileBusy.value = '';
    }
}
const newProfile = reactive({ name: '', description: '' });
async function loadProfiles() {
    profilesLoading.value = true;
    try {
        profiles.value = (await api.get('/admin/muse-profiles')).data.data || [];
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        profilesLoading.value = false;
    }
}
onMounted(loadProfiles);
async function addProfile() {
    if (!newProfile.name.trim()) { toast.error('Nama profil wajib diisi.'); return; }
    profileBusy.value = 'add';
    try {
        const { data } = await api.post('/admin/muse-profiles', { name: newProfile.name.trim(), description: newProfile.description.trim() || null });
        toast.success(data.message);
        Object.assign(newProfile, { name: '', description: '' });
        showAddProfile.value = false;
        await loadProfiles();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        profileBusy.value = '';
    }
}
async function profileAction(p, action) {
    const labels = { 'generate-token': 'Buat token baru untuk profil ini?', 'generate-webhook-secret': 'Buat webhook secret baru untuk profil ini?', 'set-default': `Jadikan "${p.name}" profil default?`, 'toggle-active': p.is_active ? `Nonaktifkan profil "${p.name}"?` : `Aktifkan profil "${p.name}"?`, delete: `Hapus profil "${p.name}"? Job lama yang memakai profil ini tetap ada (profilnya jadi kosong).` };
    if (['generate-token', 'generate-webhook-secret', 'delete'].includes(action)) {
        const ok = await confirmDialog({ title: labels[action], message: action === 'delete' ? 'Profil yang dihapus tidak bisa dikembalikan. Token profil ini langsung tidak berlaku.' : 'Nilai lama langsung tidak berlaku.', tone: 'danger', confirmText: 'Ya, lanjutkan', icon: 'key-round' });
        if (!ok) return;
    }
    profileBusy.value = `${action}-${p.id}`;
    try {
        let data;
        if (action === 'delete') {
            ({ data } = await api.delete(`/admin/muse-profiles/${p.id}`));
        } else if (action === 'test') {
            ({ data } = await api.post(`/admin/muse-profiles/${p.id}/test`));
            profileTests.value = { ...profileTests.value, [p.id]: data.data };
            toast.success('Tes profil OK — lihat status tersimpan di bawah profil.');
            return;
        } else {
            ({ data } = await api.post(`/admin/muse-profiles/${p.id}/${action}`));
        }
        hideProfileSecrets(p.id);
        toast.success(data.message);
        await loadProfiles();
        await loadTokenUsage();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        profileBusy.value = '';
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
        <div class="card p-6 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-display text-lg font-bold">Penggunaan Token Muse</h2>
          <button class="btn btn-secondary btn-sm" :disabled="tokenLoading" @click="loadTokenUsage">Refresh</button>
        </div>
        <p class="mt-1 text-xs text-muted">Estimasi 1 token ~ 4 karakter naskah/caption. Ini pemakaian web, bukan billing resmi Muse.</p>
        <div v-if="tokenLoading" class="mt-4 text-sm text-muted">Memuat...</div>
        <div v-else-if="tokenUsage" class="mt-4 grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl border-2 border-line p-4"><p class="text-sm text-muted">Token terpakai (bulan ini)</p><p class="font-display text-2xl font-bold tabular-nums">{{ tokenUsage.month_tokens?.toLocaleString('id-ID') }}</p><p class="text-xs text-muted">dari limit {{ tokenUsage.month_limit?.toLocaleString('id-ID') }} ({{ tokenUsage.percent_used }}%)</p><div class="mt-2 h-2 rounded-full bg-surface-2 overflow-hidden"><div class="h-full bg-violet-500" :style="{ width: Math.min(100, tokenUsage.percent_used) + '%' }"></div></div></div>
          <div class="rounded-2xl border-2 border-line p-4"><p class="text-sm text-muted">Sisa token (bulan ini)</p><p class="font-display text-2xl font-bold tabular-nums text-emerald-600">{{ tokenUsage.remaining?.toLocaleString('id-ID') }}</p><p class="text-xs text-muted">Reset tiap awal bulan</p></div>
          <div class="rounded-2xl border-2 border-line p-4"><p class="text-sm text-muted">Total generasi / job</p><p class="font-display text-2xl font-bold tabular-nums">{{ tokenUsage.total_generations }} video • {{ tokenUsage.total_jobs }} job</p><p class="text-xs text-muted">Total token sepanjang waktu: {{ tokenUsage.total_tokens?.toLocaleString('id-ID') }}</p></div>
        </div>
        <div v-if="tokenUsage?.history?.length" class="mt-6">
          <p class="text-sm font-bold">Riwayat penggunaan token (20 video terbaru)</p>
          <p class="text-xs text-muted">Berapa token dipakai per video / job — estimasi 1 token ~ 4 karakter.</p>
          <div class="mt-2 overflow-x-auto">
            <table class="table">
              <thead><tr><th>Tanggal</th><th>Video / Job</th><th>Model</th><th>Karakter</th><th class="text-right">Token</th></tr></thead>
              <tbody>
                <tr v-for="h in tokenUsage.history" :key="h.id">
                  <td class="whitespace-nowrap text-xs text-muted">{{ new Date(h.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</td>
                  <td class="font-semibold">{{ h.keyword }} <span class="text-xs text-muted">#{{ h.muse_job_id || h.id }}</span></td>
                  <td><span class="text-xs">{{ h.model }} • {{ h.durasi }} dtk</span></td>
                  <td class="tabular-nums">{{ h.chars?.toLocaleString('id-ID') }}</td>
                  <td class="text-right font-bold tabular-nums">{{ h.tokens?.toLocaleString('id-ID') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-if="tokenUsage?.per_profile?.length" class="mt-6">
          <p class="text-sm font-bold">Penggunaan token per profil Muse</p>
          <p class="text-xs text-muted">Job & video yang dikerjakan tiap profil — estimasi 1 token ~ 4 karakter.</p>
          <div class="mt-2 overflow-x-auto">
            <table class="table">
              <thead><tr><th>Profil Muse</th><th>Job</th><th>Video</th><th>Karakter</th><th class="text-right">Token</th></tr></thead>
              <tbody>
                <tr v-for="pp in tokenUsage.per_profile" :key="pp.muse_profile_id ?? 'legacy'">
                  <td class="font-semibold">{{ pp.muse_profile_name }}</td>
                  <td class="tabular-nums">{{ pp.jobs }}</td>
                  <td class="tabular-nums">{{ pp.generations }}</td>
                  <td class="tabular-nums">{{ pp.chars?.toLocaleString('id-ID') }}</td>
                  <td class="text-right font-bold tabular-nums">{{ pp.tokens?.toLocaleString('id-ID') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-if="tokenUsage?.daily?.length" class="mt-4">
          <p class="text-sm font-bold">7 hari terakhir</p>
          <div class="mt-2 flex gap-2 overflow-x-auto">
            <div v-for="d in tokenUsage.daily" :key="d.date" class="min-w-[90px] rounded-xl border border-line p-2 text-center"><p class="text-xs text-muted">{{ d.date.slice(5) }}</p><p class="font-bold tabular-nums">{{ d.tokens?.toLocaleString('id-ID') }}</p><p class="text-[10px] text-muted">token</p></div>
          </div>
        </div>
      </div>

      <PageHeader title="Integrasi Muse" description="Kelola token API dan webhook untuk Muse langsung dari sini — tanpa mengedit file .env." />

        <div class="card mb-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-bold">Profil Muse</h2>
                    <p class="mt-1 text-xs text-muted">Web bisa punya lebih dari 1 koneksi Muse. Tiap profil punya token & webhook secret sendiri; saat generate, pilih Muse mana yang mengerjakan job. <b>Jangan paste token di chat — token dibuat di sini.</b> Token utuh hanya tampil 30 detik lewat tombol mata, lalu otomatis tersembunyi lagi.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" @click="showAddProfile = !showAddProfile"><Icon name="plus" class="h-4 w-4" /> Tambah Profil</button>
            </div>

            <form v-if="showAddProfile" class="mt-4 space-y-3 rounded-2xl border-2 border-line p-4" @submit.prevent="addProfile">
                <div><label class="label" for="pf-name">Nama profil</label><input id="pf-name" v-model="newProfile.name" class="input" maxlength="100" placeholder="mis. Muse Kedua" /></div>
                <div><label class="label" for="pf-desc">Deskripsi (opsional)</label><input id="pf-desc" v-model="newProfile.description" class="input" maxlength="500" placeholder="mis. Muse di laptop kedua" /></div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="profileBusy === 'add'"><Spinner v-if="profileBusy === 'add'" class="h-4 w-4" /> Simpan profil</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="showAddProfile = false">Batal</button>
                </div>
            </form>

            <div v-if="profilesLoading" class="mt-4 text-sm text-muted">Memuat profil…</div>
            <div v-else-if="!profiles.length" class="mt-4 rounded-2xl bg-surface-2 p-4 text-sm text-muted">Belum ada profil. Tambah profil pertama, atau profil "Muse Utama" dibuat otomatis dari token lama saat halaman ini dibuka.</div>
            <div v-else class="mt-4 space-y-3">
                <div v-for="p in profiles" :key="p.id" class="rounded-2xl border-2 border-line p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-bold">{{ p.name }}</p>
                        <Badge v-if="p.is_default" color="yellow" size="xs" icon="star">Default</Badge>
                        <Badge :color="p.is_active ? 'emerald' : 'slate'" size="xs">{{ p.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
                        <span class="text-xs text-muted">{{ p.jobs_count }} job · terakhir dipakai {{ p.last_used_at ? relative(p.last_used_at) : 'belum pernah' }}</span>
                    </div>
                    <p v-if="p.description" class="mt-1 text-xs text-muted">{{ p.description }}</p>
                    <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                        <div class="flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2">
                            <span class="font-bold">Token:</span>
                            <code class="min-w-0 flex-1 truncate font-mono">{{ revealedProfiles[p.id]?.api_token || p.api_token_masked || '—' }}</code>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm shrink-0" :disabled="profileBusy === `reveal-${p.id}`" :title="revealedProfiles[p.id] ? 'Sembunyikan token' : 'Tampilkan token (auto-hide 30 detik)'" :aria-label="revealedProfiles[p.id] ? 'Sembunyikan token' : 'Tampilkan token'" @click="toggleRevealProfile(p)"><Icon :name="revealedProfiles[p.id] ? 'eye-off' : 'eye'" class="h-4 w-4" /></button>
                            <button v-if="revealedProfiles[p.id]?.api_token" type="button" class="btn btn-secondary btn-sm shrink-0" @click="copyText(revealedProfiles[p.id].api_token, 'Token ' + p.name)"><Icon name="copy" class="h-4 w-4" /> Salin</button>
                        </div>
                        <div class="flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2">
                            <span class="font-bold">Webhook secret:</span>
                            <code class="min-w-0 flex-1 truncate font-mono">{{ revealedProfiles[p.id]?.webhook_secret || p.webhook_secret_masked || '—' }}</code>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm shrink-0" :disabled="profileBusy === `reveal-${p.id}`" :title="revealedProfiles[p.id] ? 'Sembunyikan webhook secret' : 'Tampilkan webhook secret (auto-hide 30 detik)'" :aria-label="revealedProfiles[p.id] ? 'Sembunyikan webhook secret' : 'Tampilkan webhook secret'" @click="toggleRevealProfile(p)"><Icon :name="revealedProfiles[p.id] ? 'eye-off' : 'eye'" class="h-4 w-4" /></button>
                            <button v-if="revealedProfiles[p.id]?.webhook_secret" type="button" class="btn btn-secondary btn-sm shrink-0" @click="copyText(revealedProfiles[p.id].webhook_secret, 'Webhook secret ' + p.name)"><Icon name="copy" class="h-4 w-4" /> Salin</button>
                        </div>
                    </div>
                    <div v-if="revealedProfiles[p.id]" class="mt-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        <p class="font-bold">Jangan bagikan / screenshot key ini. Hanya untuk menghubungkan Muse.</p>
                        <p class="mt-1">Sembunyi otomatis dalam {{ revealedProfiles[p.id].remaining }} detik · <button type="button" class="font-bold underline" @click="hideProfileSecrets(p.id)">Sembunyikan sekarang</button></p>
                    </div>
                    <div v-if="profileTests[p.id]" class="mt-2 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <p class="font-bold">Tes: {{ profileTests[p.id].message }}</p>
                        <p>Token {{ profileTests[p.id].api_token_masked }} · {{ profileTests[p.id].api_token_length }} karakter · format {{ profileTests[p.id].api_token_format }} · {{ profileTests[p.id].jobs_count }} job</p>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" :disabled="profileBusy === `generate-token-${p.id}`" @click="profileAction(p, 'generate-token')"><Spinner v-if="profileBusy === `generate-token-${p.id}`" class="h-4 w-4" /> Generate Token</button>
                        <button type="button" class="btn btn-secondary btn-sm" :disabled="profileBusy === `generate-webhook-secret-${p.id}`" @click="profileAction(p, 'generate-webhook-secret')"><Spinner v-if="profileBusy === `generate-webhook-secret-${p.id}`" class="h-4 w-4" /> Generate Webhook Secret</button>
                        <button v-if="!p.is_default" type="button" class="btn btn-secondary btn-sm" :disabled="profileBusy === `set-default-${p.id}`" @click="profileAction(p, 'set-default')"><Spinner v-if="profileBusy === `set-default-${p.id}`" class="h-4 w-4" /> Set Default</button>
                        <button type="button" class="btn btn-secondary btn-sm" :disabled="profileBusy === `toggle-active-${p.id}` || (p.is_default && p.is_active)" :title="p.is_default && p.is_active ? 'Profil default tidak bisa dinonaktifkan' : ''" @click="profileAction(p, 'toggle-active')"><Spinner v-if="profileBusy === `toggle-active-${p.id}`" class="h-4 w-4" /> {{ p.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        <button type="button" class="btn btn-secondary btn-sm" :disabled="profileBusy === `test-${p.id}`" @click="profileAction(p, 'test')"><Spinner v-if="profileBusy === `test-${p.id}`" class="h-4 w-4" /> Test</button>
                        <button type="button" class="btn btn-soft-danger btn-sm" :disabled="profileBusy === `delete-${p.id}`" @click="profileAction(p, 'delete')"><Spinner v-if="profileBusy === `delete-${p.id}`" class="h-4 w-4" /> Hapus</button>
                    </div>
                </div>
            </div>
        </div>

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
