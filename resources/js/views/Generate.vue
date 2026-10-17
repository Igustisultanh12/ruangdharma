<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Generate Video</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Kata kunci + durasi + model menjadi naskah, caption & prompt visual.</p>
      </div>
      <RouterLink to="/templates" class="btn-secondary btn-sm">Lihat Template & Ide</RouterLink>
    </div>

    <div class="card mb-4">
      <label class="label" for="gen-keyword">Kata kunci</label>
      <input id="gen-keyword" v-model="keyword" class="input" placeholder="misal: Rwa Bhineda" />
      <div class="flex flex-wrap gap-2 mt-2">
        <button v-for="c in chips" :key="c" type="button" @click="keyword = c" class="chip" :class="{ 'chip-active': keyword === c }">{{ c }}</button>
      </div>

      <div class="grid md:grid-cols-2 gap-4 mt-5">
        <fieldset>
          <legend class="label">Durasi</legend>
          <div class="flex gap-2">
            <label v-for="d in [30, 60, 90]" :key="d" class="chip !cursor-pointer" :class="{ 'chip-active': durasi === d }">
              <input type="radio" :value="d" v-model.number="durasi" class="sr-only" /> {{ d }} detik
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend class="label">Model</legend>
          <div class="flex gap-2 flex-wrap">
            <label v-for="m in modelOptions" :key="m.value" class="chip !cursor-pointer" :class="{ 'chip-active': model === m.value }">
              <input type="radio" :value="m.value" v-model="model" class="sr-only" /> {{ m.label }}
            </label>
          </div>
        </fieldset>
      </div>

      <label class="flex items-start gap-3 mt-5 p-3 rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950/40 cursor-pointer">
        <input type="checkbox" v-model="viaMuse" class="mt-1 h-4 w-4 accent-violet-600" />
        <span>
          <span class="font-semibold text-sm text-violet-800 dark:text-violet-200">Generate via Muse — video beneran (Cara 2)</span>
          <span class="block text-xs text-slate-600 dark:text-slate-300 mt-0.5">Job dikirim ke Muse di chat. Muse akan generate video (6 klip) lalu kirim balik via webhook. Status akan update otomatis di bawah. Kalau tidak dicentang, web hanya generate naskah + caption + prompt visual secara lokal.</span>
        </span>
      </label>
      <div v-if="museJob" class="mt-4 p-3 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-950/40 text-sm">
        <p class="font-semibold text-amber-800 dark:text-amber-200">Job Muse #{{ museJob.id }} — {{ museJob.status }}</p>
        <p class="text-slate-600 dark:text-slate-300 mt-1">{{ museJob.message || 'Menunggu Muse generate video...' }}</p>
        <p class="text-xs text-slate-500 mt-1">Halaman ini akan cek status tiap 10 detik. Kamu juga bisa cek di chat Muse: "cek job baru". Setelah selesai, video & naskah akan muncul di Riwayat.</p>
        <button v-if="museJob.status === 'completed'" @click="loadJobResult" class="btn-primary btn-sm mt-2">Lihat Hasil</button>
      </div>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-4">Sisa kuota: <b class="text-slate-900 dark:text-white">{{ store.quota.remaining }} / {{ store.quota.limit }}</b> video</p>
      <button @click="doGenerate" :disabled="store.loading || !keyword.trim()" class="btn-primary mt-3 w-full">
        <svg v-if="store.loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 3a9 9 0 109 9" /></svg>
        {{ store.loading ? 'Sedang generate...' : 'Generate' }}
      </button>

      <div v-if="store.loading" class="mt-5">
        <div class="flex justify-between text-sm mb-2"><span class="font-medium text-violet-700 dark:text-violet-300">{{ store.steps[store.step] }}...</span><span class="text-slate-500">{{ store.step + 1 }}/{{ store.steps.length }}</span></div>
        <div class="progress-track"><div class="progress-bar" :style="{ width: ((store.step + 1) / store.steps.length * 100) + '%' }"></div></div>
        <LoadingSteps :steps="store.steps" :current="store.step" class="mt-4" />
      </div>

      <div v-if="store.error" class="alert-error mt-4">
        <p class="font-semibold">{{ errorTitle }}</p>
        <p>{{ store.error }}</p>
        <p v-if="store.errorKind === 'quota'" class="mt-1">Kuota akan terisi ulang besok, atau hubungi admin untuk menaikkan limit.</p>
        <p v-if="store.errorKind === 'blocked'" class="mt-1">Kata kunci ini diblokir admin. Coba kata kunci lain dari Template & Ide.</p>
      </div>
    </div>

    <div v-if="result" class="space-y-4">
      <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <div class="flex gap-1 bg-slate-100 dark:bg-slate-700 rounded-xl p-1">
            <button v-for="t in tabs" :key="t.key" @click="tab = t.key" class="px-3 py-1.5 rounded-lg text-sm font-medium" :class="tab === t.key ? 'bg-white dark:bg-slate-800 shadow text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-slate-300'">{{ t.label }}</button>
          </div>
          <div class="flex gap-2">
            <button @click="copyText(resultText, 'Teks')" class="btn-secondary btn-sm">Salin</button>
            <button @click="downloadTxt" class="btn-primary btn-sm">Unduh .txt</button>
          </div>
        </div>

        <!-- Pratinjau 9:16 -->
        <div v-if="tab === 'preview'">
          <h2 class="font-bold text-lg text-slate-900 dark:text-white">{{ result.judul }}</h2>
          <div class="aspect-[9/16] max-w-[320px] bg-slate-900 text-white rounded-2xl p-4 flex flex-col justify-between mx-auto my-3 shadow-lg">
            <div>
              <p class="text-xs opacity-70">BAGIAN {{ current + 1 }} / {{ result.naskah.length }}</p>
              <p class="font-bold mt-2">{{ result.naskah[current]?.speaker }}</p>
              <p class="text-sm mt-1">{{ result.naskah[current]?.teks }}</p>
            </div>
            <div class="flex gap-2">
              <button @click="current = Math.max(0, current - 1)" class="bg-white text-slate-900 px-3 py-1.5 rounded-lg text-sm font-medium">Sebelumnya</button>
              <button @click="current = Math.min(result.naskah.length - 1, current + 1)" class="bg-white text-slate-900 px-3 py-1.5 rounded-lg text-sm font-medium">Berikutnya</button>
            </div>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 text-center">Pratinjau frame + subtitle. Video AI penuh dibuat lewat prompt di tab Prompt Visual / via Muse.</p>
        </div>

        <!-- Naskah -->
        <div v-if="tab === 'naskah'">
          <h2 class="font-bold text-lg text-slate-900 dark:text-white mb-2">Naskah ({{ result.naskah.length }} bagian)</h2>
          <div v-for="n in result.naskah" :key="n.bagian" class="border-b border-slate-100 dark:border-slate-700 py-3 last:border-0">
            <p class="font-semibold text-sm">Bagian {{ n.bagian }} — {{ n.speaker }} <span class="text-slate-400 font-normal">({{ n.suara }})</span></p>
            <p class="text-sm mt-1">{{ n.teks }}</p>
          </div>
        </div>

        <!-- Caption -->
        <div v-if="tab === 'caption'">
          <h2 class="font-bold text-lg text-slate-900 dark:text-white mb-2">Caption</h2>
          <pre class="whitespace-pre-wrap text-sm bg-slate-50 dark:bg-slate-700/50 rounded-xl p-4">{{ result.caption }}</pre>
          <button @click="copyText(result.caption, 'Caption')" class="btn-primary mt-3">Salin Caption</button>
        </div>

        <!-- Prompt Visual -->
        <div v-if="tab === 'prompts'">
          <h2 class="font-bold text-lg text-slate-900 dark:text-white mb-2">Prompt Visual</h2>
          <ol class="space-y-2">
            <li v-for="(p, i) in result.prompts" :key="i" class="text-sm bg-slate-50 dark:bg-slate-700/50 rounded-xl p-3"><b>Bagian {{ i + 1 }}:</b> {{ p }}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import { useGenerateStore } from '../stores/generate.js';
import { useNotificationStore } from '../stores/notifications.js';
import { useAuthStore } from '../stores/auth.js';
import { getGenerateDefaults } from '../services/preferences.js';
import api from '../services/api.js';
import { logActivity } from '../services/activity.js';
import LoadingSteps from '../components/LoadingSteps.vue';

const store = useGenerateStore();
const notif = useNotificationStore();
const auth = useAuthStore();
const route = useRoute();

const keyword = ref('Rwa Bhineda');
const viaMuse = ref(false);
const museJob = ref(null);
let musePoll = null;
const durasi = ref(60);
const model = ref('narasi');
const current = ref(0);
const tab = ref('preview');
const chips = ['Rwa Bhineda', 'Canang Sari', 'Tri Sandhya', 'Karma', 'Tri Hita Karana', 'Bhatara Sri'];
const modelOptions = [
  { value: 'narasi', label: 'Narasi' },
  { value: 'ngobrol', label: 'Ngobrol' },
  { value: 'gabungan', label: 'Gabungan' },
];
const tabs = [
  { key: 'preview', label: 'Pratinjau 9:16' },
  { key: 'naskah', label: 'Naskah' },
  { key: 'caption', label: 'Caption' },
  { key: 'prompts', label: 'Prompt Visual' },
];

const result = computed(() => store.result?.result || null);
const errorTitle = computed(() => store.errorKind === 'quota' ? 'Kuota harian habis' : store.errorKind === 'blocked' ? 'Kata kunci diblokir' : 'Gagal generate');
const resultText = computed(() => {
  if (!result.value) return '';
  const r = result.value;
  let t = `RUANG DHARMA STUDIO\nJudul: ${r.judul}\nKata kunci: ${r.keyword} | Durasi: ${r.durasi} | Model: ${r.model}\n\n=== NASKAH ===\n`;
  r.naskah.forEach((n) => { t += `\nBagian ${n.bagian} — ${n.speaker} (${n.suara})\n${n.teks}\nPrompt: ${r.prompts[n.bagian - 1]}\n`; });
  t += `\n=== CAPTION ===\n${r.caption}\n`;
  return t;
});

function applyQuery() {
  const q = route.query;
  // Prioritas: query (template / generate ulang / media / kalender) > default pengguna > bawaan.
  const defaults = getGenerateDefaults();
  if (q.keyword) keyword.value = String(q.keyword);
  if (q.durasi && [30, 60, 90].includes(Number(q.durasi))) durasi.value = Number(q.durasi);
  else if (!q.keyword && !q.durasi) durasi.value = defaults.durasi;
  if (q.model && ['narasi', 'ngobrol', 'gabungan'].includes(String(q.model))) model.value = String(q.model);
  else if (!q.keyword && !q.model) model.value = defaults.model;
  if (q.media) keyword.value = String(q.keyword || q.media);
}

async function doGenerate() {
  current.value = 0;
  tab.value = 'preview';
  museJob.value = null;
  if (musePoll) { clearInterval(musePoll); musePoll = null; }
  try {
    if (viaMuse.value) {
      // Cara 2 — via Muse: kirim job queued, lalu polling status tiap 10 detik
      const { data } = await api.post('/generations', { keyword: keyword.value.trim(), durasi: durasi.value, model: model.value, via_muse: true });
      museJob.value = { id: data.muse_job_id, status: data.job_status || 'queued', message: data.message };
      logActivity({ action: 'generate', description: `Generate via Muse "${keyword.value}" (${durasi.value} dtk, ${model.value}) — job #${data.muse_job_id}`, user: auth.user });
      notif.push({ title: 'Job dikirim ke Muse', message: `Job #${data.muse_job_id} untuk "${keyword.value}" sedang menunggu Muse generate video.`, type: 'info', link: '/history' });
      Swal.fire({ icon: 'info', title: 'Job dikirim ke Muse', text: `Job #${data.muse_job_id} dibuat. Buka chat Muse dan bilang "cek job baru" agar Muse generate videonya, lalu hasil akan balik ke web via webhook.` });
      musePoll = setInterval(async () => {
        try {
          const { data: gen } = await api.get(`/generations/${data.generation.id}`);
          const g = gen.data || gen;
          if (g.status === 'completed') {
            clearInterval(musePoll); musePoll = null;
            museJob.value = { ...museJob.value, status: 'completed', message: 'Video selesai! Lihat di Riwayat.' };
            notif.push({ title: 'Video via Muse selesai', message: `"${keyword.value}" sudah jadi.`, type: 'success', link: '/history' });
          } else if (g.status === 'failed') {
            clearInterval(musePoll); musePoll = null;
            museJob.value = { ...museJob.value, status: 'failed', message: 'Job gagal. Cek Riwayat / hubungi admin.' };
          } else {
            museJob.value = { ...museJob.value, status: g.status || 'waiting_muse' };
          }
        } catch {}
      }, 10000);
      return;
    }
    const data = await store.generate({ keyword: keyword.value.trim(), durasi: durasi.value, model: model.value });
    logActivity({ action: 'generate', description: `Generate video "${keyword.value}" (${durasi.value} dtk, ${model.value})`, user: auth.user });
    notif.push({ title: 'Video berhasil dibuat', message: `"${data?.result?.judul || keyword.value}" siap dilihat di Riwayat.`, type: 'success', link: '/history' });
  } catch (e) { /* error sudah ditampilkan dari store */ }
}
async function loadJobResult() {
  // Arahkan ke Riwayat — hasil lengkap (naskah + video_url) ada di sana
  window.location.href = '/history';
}
async function copyText(text, label) {
  try {
    await navigator.clipboard.writeText(text || '');
    Swal.fire({ icon: 'success', title: `${label} tersalin`, timer: 1200, showConfirmButton: false });
  } catch {
    Swal.fire({ icon: 'error', title: 'Gagal menyalin' });
  }
}
function copyCaption() { copyText(result.value?.caption, 'Caption'); }
function downloadTxt() {
  const blob = new Blob([resultText.value], { type: 'text/plain' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `ruang-dharma-${(result.value?.keyword || 'video').toLowerCase().replace(/\s+/g, '-')}.txt`;
  a.click();
  URL.revokeObjectURL(a.href);
}

watch(() => route.query, applyQuery);
onMounted(() => {
  applyQuery();
  store.fetchQuota().catch(() => {});
});
</script>
