<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import CanangLoader from '@/components/brand/CanangLoader.vue';
import CanangMark from '@/components/brand/CanangMark.vue';
import GenerationModal from '@/components/generation/GenerationModal.vue';
import PhonePreview from '@/components/generation/PhonePreview.vue';
import BacksoundPicker from '@/components/BacksoundPicker.vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import Segmented from '@/components/ui/Segmented.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage } from '@/lib/api.js';
import { saveText } from '@/lib/download.js';
import { captionText, DURATIONS, GEN_STATUS, generationToText, MODELS, partText } from '@/lib/format.js';
import { copyText, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';
import { useAuthStore } from '@/stores/auth.js';
import { useRealtimeStore } from '@/stores/realtime.js';

const STEPS = ['Menyiapkan naskah', 'Menulis dialog & narasi', 'Membuat prompt visual 9:16', 'Menyusun caption Instagram', 'Menyiapkan video'];
const SUGGESTIONS = ['Rwa Bhineda', 'Canang Sari', 'Tri Sandhya', 'Karma', 'Tri Hita Karana', 'Bhatara Sri', 'Ahimsa', 'Nyepi', 'Saraswati', 'Ngayah'];

const auth = useAuthStore();
const rt = useRealtimeStore();
const route = useRoute();

const keyword = ref('');
const durasi = ref(auth.user?.default_durasi || 60);
const model = ref(auth.user?.default_model || 'narasi');
const loading = ref(false);
const step = ref(0);
const result = ref(null);
const generation = ref(null);
const error = ref(null); // { kind, message }
const tab = ref('naskah');
const detailId = ref(null);
const sending = ref(false);
const viaMuse = ref(false);
const backsound = ref({ url: '', volume: 20, trimStart: 0, trimEnd: null });
const museGen = ref(null); // VideoGeneration status waiting_muse / processing / completed / failed

// Cara 2 — status job Muse ikut terbarui realtime (tanpa polling manual / refresh).
useRealtime(['generations'], refreshMuse);
async function refreshMuse() {
    if (!museGen.value || ['completed', 'failed'].includes(museGen.value.status)) return;
    try {
        const g = (await api.get(`/generations/${museGen.value.id}`)).data.data;
        museGen.value = g;
        if (g.status === 'completed') {
            generation.value = g;
            result.value = { judul: g.keyword, keyword: g.keyword, durasi: g.durasi, model: g.model, naskah: g.naskah || [], prompts: g.prompts || [], caption: captionText(g.caption), video_url: g.video_url, video_files: g.video_files };
            tab.value = g.video_url ? 'video' : 'naskah';
        }
    } catch {
        /* coba lagi di pulse berikutnya */
    }
}

const quota = computed(() => rt.quota || { limit: auth.user?.quota?.limit ?? 0, used: auth.user?.quota?.used_today ?? 0, remaining: auth.user?.quota?.remaining ?? 0 });
const outOfQuota = computed(() => rt.quota && rt.quota.remaining <= 0);
const canGenerate = computed(() => keyword.value.trim().length > 1 && !loading.value && !outOfQuota.value);

function applyQuery() {
    const q = route.query;
    if (q.keyword) keyword.value = String(q.keyword).slice(0, 100);
    if (DURATIONS.includes(Number(q.durasi))) durasi.value = Number(q.durasi);
    if (MODELS.some((m) => m.value === q.model)) model.value = String(q.model);
}
watch(() => route.query, applyQuery);
onMounted(applyQuery);

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

async function generate() {
    if (!canGenerate.value) return;
    if (viaMuse.value) return generateViaMuse();
    museGen.value = null;
    loading.value = true;
    error.value = null;
    result.value = null;
    step.value = 0;
    const ticker = setInterval(() => step.value < STEPS.length - 1 && step.value++, 650);
    try {
        const [res] = await Promise.all([api.post('/generations', { keyword: keyword.value.trim(), durasi: durasi.value, model: model.value, backsound_url: backsound.value.url || null, backsound_volume: backsound.value.volume, backsound_trim_start: backsound.value.trimStart, backsound_trim_end: backsound.value.trimEnd }), wait(STEPS.length * 650)]);
        step.value = STEPS.length - 1;
        await wait(350);
        result.value = res.data.result;
        generation.value = res.data.generation;
        tab.value = 'naskah';
        rt.pulseNow(); // notifikasi "Video selesai dibuat" muncul lewat realtime
    } catch (e) {
        const status = e.response?.status;
        const message = errorMessage(e, 'Gagal membuat video.');
        error.value = { kind: status === 429 ? 'quota' : status === 422 && /diblokir/i.test(message) ? 'blocked' : 'other', message };
    } finally {
        clearInterval(ticker);
        loading.value = false;
    }
}

async function generateViaMuse() {
    loading.value = true;
    error.value = null;
    result.value = null;
    try {
        const { data } = await api.post('/generations', { keyword: keyword.value.trim(), durasi: durasi.value, model: model.value, via_muse: true, backsound_url: backsound.value.url || null, backsound_volume: backsound.value.volume, backsound_trim_start: backsound.value.trimStart, backsound_trim_end: backsound.value.trimEnd });
        museGen.value = data.generation;
        rt.pulseNow();
    } catch (e) {
        const status = e.response?.status;
        const message = errorMessage(e, 'Gagal mengirim job ke Muse.');
        error.value = { kind: status === 429 ? 'quota' : status === 422 && /diblokir/i.test(message) ? 'blocked' : 'other', message };
    } finally {
        loading.value = false;
    }
}

async function sendToReview() {
    if (!generation.value) return;
    sending.value = true;
    try {
        const { data } = await api.post(`/generations/${generation.value.id}/submit-review`);
        generation.value = data.data;
        toast.success('Dikirim ke reviewer. Kamu akan dapat notifikasi saat ditinjau.');
        rt.pulseNow();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        sending.value = false;
    }
}
const fullText = computed(() => (result.value ? generationToText(result.value) : ''));
function download() {
    saveText(fullText.value, `ruang-dharma-${result.value.keyword.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.txt`);
}
</script>

<template>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[420px_1fr]">
        <!-- Formulir -->
        <section class="card relative h-fit overflow-hidden p-6 xl:sticky xl:top-24">
            <div class="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-brand-gradient opacity-15 blur-2xl"></div>
            <h1 class="relative font-display text-2xl font-bold">Buat video baru</h1>
            <p class="relative mt-1 text-sm text-muted">Satu kata kunci jadi naskah 6 bagian, prompt visual, dan caption.</p>

            <form class="relative mt-6 space-y-6" @submit.prevent="generate">
                <div>
                    <label class="label" for="keyword">Tema / kata kunci</label>
                    <div class="relative">
                        <Icon name="sparkles" class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-violet-500" />
                        <input id="keyword" v-model="keyword" class="input pl-11 text-base" maxlength="100" placeholder="mis. Rwa Bhineda" autocomplete="off" />
                    </div>
                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                        <button v-for="s in SUGGESTIONS" :key="s" type="button" :class="keyword === s ? 'chip-active' : ''" class="chip" @click="keyword = s">{{ s }}</button>
                    </div>
                </div>

                <div>
                    <p class="label">Durasi</p>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="d in DURATIONS" :key="d" type="button" :class="durasi === d ? 'border-ink bg-yellow-300 text-zinc-950 shadow-brutal-sm' : 'border-line hover:border-violet-300'" class="rounded-2xl border-2 px-3 py-3 text-center transition" @click="durasi = d">
                            <span class="block font-display text-xl font-bold">{{ d }}<span class="text-xs">dtk</span></span>
                            <span class="block text-[11px] font-semibold opacity-75">{{ d === 30 ? 'Ringkas' : d === 60 ? 'Standar' : 'Lengkap' }}</span>
                        </button>
                    </div>
                </div>

                <div>
                    <p class="label">Gaya penyampaian</p>
                    <div class="space-y-2">
                        <button v-for="m in MODELS" :key="m.value" type="button" :class="model === m.value ? 'border-ink bg-card shadow-brutal-sm' : 'border-line hover:border-violet-300'" class="flex w-full items-center gap-3 rounded-2xl border-2 px-3 py-2.5 text-left transition" @click="model = m.value">
                            <span :class="model === m.value ? 'bg-brand-gradient text-white' : 'bg-surface-2 text-muted'" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl transition"><Icon :name="m.icon" class="h-5 w-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold">{{ m.label }}</span>
                                <span class="block text-xs text-muted">{{ m.desc }}</span>
                            </span>
                            <span :class="model === m.value ? 'border-ink bg-yellow-300' : 'border-line'" class="grid h-5 w-5 place-items-center rounded-full border-2"><span v-if="model === m.value" class="h-2 w-2 rounded-full bg-ink"></span></span>
                        </button>
                    </div>
                </div>

                <BacksoundPicker v-model="backsound" />

                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 p-3 transition" :class="viaMuse ? 'border-ink bg-sky-50 shadow-brutal-sm dark:bg-sky-500/10' : 'border-line hover:border-violet-300'">
                    <input v-model="viaMuse" type="checkbox" class="checkbox mt-0.5" />
                    <span class="min-w-0">
                        <span class="flex items-center gap-1.5 text-sm font-bold"><Icon name="film" class="h-4 w-4 text-sky-500" /> Buat video beneran via Muse</span>
                        <span class="mt-0.5 block text-xs text-muted">Job dikirim ke Muse; Muse membuat 6 klip video lalu mengirim hasil lewat webhook. Statusnya terbarui otomatis di sini. Tanpa centang, studio hanya menyusun naskah, caption & prompt.</span>
                    </span>
                </label>

                <div class="rounded-2xl bg-surface-2 p-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold">Sisa kuota hari ini</span>
                        <span class="font-display font-bold tabular-nums"><span v-counter="quota.remaining">{{ quota.remaining }}</span> / {{ quota.limit }}</span>
                    </div>
                    <div class="mt-2 flex gap-1">
                        <span v-for="i in Math.min(quota.limit, 20)" :key="i" :class="i <= quota.limit - quota.remaining ? 'bg-line' : 'bg-brand-gradient'" class="h-2 flex-1 rounded-full transition-colors"></span>
                    </div>
                </div>

                <button v-ripple type="submit" class="btn btn-primary btn-lg shine w-full" :disabled="!canGenerate">
                    <Spinner v-if="loading" class="h-5 w-5" />
                    <Icon v-else name="wand-sparkles" class="h-5 w-5" />
                    {{ loading ? (viaMuse ? 'Mengirim ke Muse…' : 'Sedang membuat…') : outOfQuota ? 'Kuota hari ini habis' : viaMuse ? 'Kirim ke Muse' : 'Buat video' }}
                </button>
            </form>
        </section>

        <!-- Hasil -->
        <section class="min-w-0">
            <div v-if="loading" class="card p-8">
                <p class="mb-6 font-display text-lg font-bold">Menyusun "{{ keyword }}"…</p>
                <CanangLoader :steps="STEPS" :current="step" />
            </div>

            <div v-else-if="museGen && museGen.status !== 'completed'" class="card-brutal overflow-hidden">
                <div class="flex flex-wrap items-center gap-4 border-b-2 border-ink bg-sky-300 p-5 text-zinc-900">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl border-2 border-ink bg-white"><Icon :name="museGen.status === 'failed' ? 'circle-x' : 'film'" class="h-6 w-6" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold opacity-80">Job Muse #{{ museGen.muse_job_id }}</p>
                        <h2 class="font-display text-2xl font-bold">{{ museGen.keyword }}</h2>
                    </div>
                    <Badge :color="GEN_STATUS[museGen.status]?.color || 'slate'" :icon="GEN_STATUS[museGen.status]?.icon">{{ GEN_STATUS[museGen.status]?.label || museGen.status }}</Badge>
                </div>
                <div class="grid gap-6 p-6 md:grid-cols-[160px_1fr] md:items-center">
                    <div class="mx-auto"><CanangMark :size="140" :lit="museGen.status === 'processing' ? 3 : museGen.status === 'failed' ? 0 : 1" :active="museGen.status === 'processing' ? 2 : 0" stroke="currentColor" class="text-ink" /></div>
                    <div>
                        <p v-if="museGen.status === 'failed'" class="text-sm text-rose-600 dark:text-rose-400">{{ captionText(museGen.caption) }}</p>
                        <template v-else>
                            <ol class="space-y-2 text-sm">
                                <li class="flex items-center gap-2"><Icon name="circle-check" class="h-4 w-4 text-emerald-500" /> Job tersimpan &amp; kuota terpakai</li>
                                <li class="flex items-center gap-2"><Icon :name="museGen.status === 'processing' ? 'circle-check' : 'hourglass'" :class="museGen.status === 'processing' ? 'text-emerald-500' : 'text-amber-500'" class="h-4 w-4" /> Muse mengambil job (di chat Muse: <b>"cek job baru"</b>)</li>
                                <li class="flex items-center gap-2"><Icon name="hourglass" class="h-4 w-4 text-muted" /> Muse membuat 6 klip video lalu mengirim webhook</li>
                            </ol>
                            <p class="mt-4 rounded-2xl bg-surface-2 p-3 text-xs text-muted">Halaman ini terbarui sendiri begitu Muse selesai. Kamu juga boleh pindah halaman — hasilnya tetap muncul di Riwayat & notifikasi.</p>
                        </template>
                        <div class="mt-4 flex gap-2">
                            <RouterLink to="/history" class="btn btn-secondary btn-sm"><Icon name="clapperboard" class="h-4 w-4" /> Buka Riwayat</RouterLink>
                            <button type="button" class="btn btn-ghost btn-sm" @click="museGen = null">Buat yang lain</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="error" class="card-brutal p-6">
                <div class="flex items-start gap-4">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border-2 border-ink" :class="error.kind === 'quota' ? 'bg-amber-300' : 'bg-rose-400'">
                        <Icon :name="error.kind === 'quota' ? 'battery-low' : error.kind === 'blocked' ? 'ban' : 'circle-alert'" class="h-6 w-6 text-zinc-900" />
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-bold">{{ error.kind === 'quota' ? 'Kuota harian habis' : error.kind === 'blocked' ? 'Kata kunci diblokir admin' : 'Video belum berhasil dibuat' }}</h2>
                        <p class="mt-1 text-sm text-muted">{{ error.message }}</p>
                        <p class="mt-3 text-sm">
                            <template v-if="error.kind === 'quota'">Kuota terisi lagi besok pagi. Butuh lebih banyak? Minta admin menaikkan paket kuota akunmu.</template>
                            <template v-else-if="error.kind === 'blocked'">Pilih tema lain dari <RouterLink to="/templates" class="font-bold text-violet-600 underline dark:text-violet-300">Template & ide</RouterLink>.</template>
                            <template v-else>Periksa koneksi lalu coba lagi.</template>
                        </p>
                    </div>
                </div>
            </div>

            <div v-else-if="result" class="space-y-6">
                <div class="card-brutal overflow-hidden">
                    <div class="animate-gradient bg-brand-gradient relative overflow-hidden p-6 text-white">
                        <div class="dot-bg absolute inset-0 opacity-20"></div>
                        <div class="relative flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-white/80">{{ result.video_url ? 'Video dari Muse' : 'Siap tayang' }} · {{ result.durasi }} detik · {{ MODELS.find((m) => m.value === result.model)?.label }}</p>
                                <h2 class="mt-1 font-display text-3xl font-bold">{{ result.judul }}</h2>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm border-2 border-white/60 bg-white/15 text-white hover:bg-white/25" @click="copyText(fullText, 'Semua hasil')"><Icon name="copy" class="h-4 w-4" /> Salin semua</button>
                                <button type="button" class="btn btn-sm border-2 border-white/60 bg-white/15 text-white hover:bg-white/25" @click="download"><Icon name="download" class="h-4 w-4" /> Unduh .txt</button>
                                <button v-if="generation && ['draft', 'revisi'].includes(generation.workflow_status)" type="button" class="btn btn-brutal btn-sm" :disabled="sending" @click="sendToReview"><Icon name="send" class="h-4 w-4" /> Kirim ke review</button>
                                <Badge v-else-if="generation?.workflow_status === 'review'" color="yellow" icon="hourglass">Menunggu review</Badge>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6 p-6 2xl:grid-cols-[300px_1fr]">
                        <PhonePreview :naskah="result.naskah" :title="result.judul" :model="result.model" />
                        <div class="min-w-0">
                            <Segmented v-model="tab" :options="[...(result.video_url ? [{ value: 'video', label: 'Video Muse', icon: 'film' }] : []), { value: 'naskah', label: 'Naskah', icon: 'file-text' }, { value: 'caption', label: 'Caption', icon: 'hash' }, { value: 'prompts', label: 'Prompt visual', icon: 'image' }]" class="mb-4" />
                            <div v-if="tab === 'video'" class="space-y-3">
                                <video :src="result.video_url" controls playsinline class="max-h-[70vh] w-full rounded-2xl border-2 border-ink bg-black"></video>
                                <div class="flex flex-wrap gap-2">
                                    <a :href="result.video_url" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><Icon name="external-link" class="h-4 w-4" /> Buka video</a>
                                    <a v-for="(f, i) in result.video_files || []" :key="i" :href="typeof f === 'string' ? f : f.url" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><Icon name="film" class="h-4 w-4" /> Klip {{ i + 1 }}</a>
                                </div>
                            </div>
                            <ol v-else-if="tab === 'naskah'" class="space-y-2.5">
                                <li v-for="n in result.naskah" :key="n.bagian" class="flex gap-3 rounded-2xl border border-line p-3.5">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-ink text-xs font-extrabold text-surface">{{ n.bagian }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs"><b>{{ n.speaker }}</b> <span class="text-muted">· {{ n.suara }} · ±{{ n.estimasi_detik }} dtk</span></p>
                                        <p class="mt-1 text-[15px] leading-relaxed">{{ partText(n) }}</p>
                                    </div>
                                </li>
                            </ol>
                            <div v-else-if="tab === 'caption'">
                                <pre class="rounded-2xl border border-line bg-surface-2/60 p-4 font-sans text-sm leading-relaxed whitespace-pre-wrap">{{ result.caption }}</pre>
                                <button type="button" class="btn btn-primary btn-sm mt-3" @click="copyText(result.caption, 'Caption')"><Icon name="copy" class="h-4 w-4" /> Salin caption</button>
                            </div>
                            <ol v-else class="space-y-2.5">
                                <li v-for="(p, i) in result.prompts" :key="i" class="group flex gap-3 rounded-2xl border border-line p-3.5">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-yellow-300 text-xs font-extrabold text-zinc-900">{{ i + 1 }}</span>
                                    <p class="flex-1 text-sm leading-relaxed">{{ p }}</p>
                                    <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Salin prompt" @click="copyText(p, `Prompt bagian ${i + 1}`)"><Icon name="copy" class="h-4 w-4" /></button>
                                </li>
                            </ol>
                            <button v-if="generation" type="button" class="btn btn-secondary btn-sm mt-5" @click="detailId = generation.id"><Icon name="square-pen" class="h-4 w-4" /> Edit, komentar & jadwal</button>
                        </div>
                    </div>
                </div>
                <div v-if="result.aturan" class="rounded-3xl border-2 border-dashed border-line p-4 text-sm text-muted">
                    <b class="text-ink">Aturan Ruang Dharma diterapkan:</b> {{ result.aturan?.pembuka }} di awal, {{ result.aturan?.penutup }} di akhir · {{ result.aturan?.visual }} · {{ result.aturan?.suara }}.
                </div>
            </div>

            <div v-else class="card relative grid min-h-[480px] place-items-center overflow-hidden p-10 text-center">
                <div class="dot-bg absolute inset-0 opacity-0 dark:opacity-5"></div>
                <div class="relative">
                    <div class="animate-float mx-auto w-fit"><CanangMark :size="140" stroke="currentColor" class="text-ink" /></div>
                    <h2 class="mt-6 font-display text-2xl font-bold">Canang sudah siap, tinggal temanya</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-muted">Pilih tema di kiri lalu tekan <b>Buat video</b>. Lima kelopak akan menyala satu per satu selama naskah disusun.</p>
                    <div class="mx-auto mt-6 grid max-w-md gap-2 text-left text-sm">
                        <p class="flex items-center gap-2"><Icon name="check" class="h-4 w-4 text-emerald-500" :stroke="3" /> Selalu dibuka "Om Swastyastu" & ditutup "Om Santi Santi Santi Om"</p>
                        <p class="flex items-center gap-2"><Icon name="check" class="h-4 w-4 text-emerald-500" :stroke="3" /> Visual 3D Pixar 9:16, suara Ibu Aria & Anak Lumi</p>
                        <p class="flex items-center gap-2"><Icon name="check" class="h-4 w-4 text-emerald-500" :stroke="3" /> Hasil otomatis tersimpan di Riwayat & bisa direview</p>
                    </div>
                </div>
            </div>
        </section>

        <GenerationModal :id="detailId" @close="detailId = null" />
    </div>
</template>
