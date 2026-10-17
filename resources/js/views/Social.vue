<script setup>
import { computed, onMounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import Segmented from '@/components/ui/Segmented.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage } from '@/lib/api.js';
import { captionText, dateTime, number, relative } from '@/lib/format.js';
import { copyText, toast, confirmDialog } from '@/lib/toast.js';

const platform = ref('instagram');
const platformOptions = [
  { value: 'instagram', label: 'Instagram', icon: 'camera' },
  { value: 'facebook', label: 'Facebook', icon: 'thumbs-up' },
  { value: 'tiktok', label: 'TikTok (Manual)', icon: 'music' },
];
const loading = ref(false);
const stats = ref(null);
const publishes = ref([]);
const generations = ref([]);
const showPublish = ref(false);
const selectedGen = ref(null);
const publishing = ref(false);
const refreshing = ref(false);

const statCards = computed(() => {
  const s = stats.value;
  if (!s) return [];
  return [
    { label: 'Followers', value: s.followers || 0, icon: 'users', color: 'violet' },
    { label: 'Total Like', value: s.total_likes || 0, icon: 'heart', color: 'rose' },
    { label: 'Total Komen', value: s.total_comments || 0, icon: 'messages-square', color: 'sky' },
    { label: 'Reach', value: s.reach || 0, icon: 'eye', color: 'emerald' },
  ];
});

async function loadStats() {
  loading.value = true;
  try {
    const { data } = await api.get('/social/stats', { params: { platform: platform.value } });
    stats.value = data.data;
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat statistik.'));
  } finally { loading.value = false; }
}
async function loadPublishes() {
  try {
    const { data } = await api.get('/social/publishes', { params: { platform: platform.value === 'tiktok' ? undefined : platform.value } });
    publishes.value = data.data?.data || data.data || [];
  } catch {}
}
async function loadGenerations() {
  try {
    const { data } = await api.get('/generations', { params: { page: 1 } });
    const pag = data?.data && Array.isArray(data.data.data) ? data.data : null;
    generations.value = pag ? (pag.data || []) : (Array.isArray(data?.data) ? data.data : []);
  } catch {}
}
async function onPlatform() { stats.value = null; await loadStats(); await loadPublishes(); }
async function refreshStats() {
  refreshing.value = true;
  try {
    const { data } = await api.post('/social/refresh');
    toast.info(data.message || 'Permintaan refresh dikirim ke Muse. Buka chat Muse dan bilang "refresh statistik sosmed".');
  } catch (e) { toast.error(errorMessage(e)); } finally { refreshing.value = false; }
}
async function publish(gen, plat) {
  const ok = await confirmDialog({ title: `Upload ke ${plat === 'instagram' ? 'Instagram' : 'Facebook'}?`, message: `Video "${gen.keyword}" akan di-upload ke ${plat} dengan caption yang ada di Riwayat.`, confirmText: 'Ya, Upload', tone: 'primary' });
  if (!ok) return;
  publishing.value = true;
  try {
    const { data } = await api.post('/social/publish', { generation_id: gen.id, platform: plat });
    toast.success(data.message || 'Job publish dikirim ke Muse.');
    await loadPublishes();
  } catch (e) { toast.error(errorMessage(e)); } finally { publishing.value = false; }
}
async function publishTiktok(gen) {
  try {
    const { data } = await api.post('/social/publish', { generation_id: gen.id, platform: 'tiktok' });
    const url = data.video_url || gen.video_url;
    if (url) window.open(url, '_blank');
    await copyText(data.caption || captionText(gen.caption), 'Caption TikTok');
    toast.info('Video dibuka & caption tersalin. Upload manual di aplikasi TikTok.');
  } catch (e) { toast.error(errorMessage(e)); }
}
onMounted(async () => { await loadStats(); await loadPublishes(); await loadGenerations(); });
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-2xl font-bold">Medsos</h1>
        <p class="mt-1 text-sm text-muted">Pantau like, komen & statistik Instagram & Facebook. Upload langsung dari video yang sudah terbit. TikTok tetap manual.</p>
      </div>
      <button class="btn btn-secondary" :disabled="refreshing" @click="refreshStats">
        <Spinner v-if="refreshing" class="h-4 w-4" />
        <Icon v-else name="refresh-cw" class="h-4 w-4" />
        {{ refreshing ? 'Meminta refresh...' : 'Refresh statistik via Muse' }}
      </button>
    </div>

    <Segmented v-model="platform" :options="platformOptions" @update:model-value="onPlatform" />

    <!-- TikTok manual -->
    <div v-if="platform === 'tiktok'" class="card p-6">
      <h2 class="font-display text-lg font-bold">TikTok — Manual</h2>
      <p class="mt-1 text-sm text-muted">TikTok belum ada API otomatis di Muse. Pilih video di bawah, lalu Download + Salin Caption dan upload manual di aplikasi TikTok.</p>
      <div class="mt-4 grid gap-3">
        <div v-for="g in generations" :key="g.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-line p-3">
          <div class="min-w-0">
            <p class="font-bold">{{ g.keyword }}</p>
            <p class="text-xs text-muted">{{ g.durasi }} dtk • {{ g.model }} • {{ dateTime(g.created_at) }}</p>
          </div>
          <div class="flex gap-2">
            <a v-if="g.video_url" :href="g.video_url" target="_blank" class="btn btn-secondary btn-sm">Download Video</a>
            <button class="btn btn-primary btn-sm" @click="publishTiktok(g)">Salin Caption & Download</button>
          </div>
        </div>
        <p v-if="!generations.length" class="text-sm text-muted">Belum ada video. Buat dulu di menu Buat video.</p>
      </div>
    </div>

    <template v-else>
      <!-- Stat cards -->
      <div v-if="loading" class="card p-8 text-center"><Spinner class="mx-auto h-6 w-6" /><p class="mt-2 text-sm text-muted">Memuat statistik...</p></div>
      <div v-else-if="stats" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="c in statCards" :key="c.label" class="card p-4">
          <div class="flex items-center gap-2 text-sm text-muted"><Icon :name="c.icon" class="h-4 w-4" />{{ c.label }}</div>
          <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ number(c.value) }}</p>
          <p class="text-xs text-muted">@{{ stats.account_username || '-' }} • {{ stats.fetched_at ? relative(stats.fetched_at) : 'belum pernah diambil' }}</p>
        </div>
      </div>
      <div v-else class="card p-6 text-sm text-muted">Belum ada data statistik. Klik Refresh statistik via Muse, lalu di chat Muse bilang "refresh statistik sosmed".</div>

      <!-- Posts -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-bold">Post terbaru</h2>
        <div v-if="stats?.posts?.length" class="mt-3 space-y-3">
          <div v-for="p in stats.posts" :key="p.id || p.url" class="flex gap-3 rounded-2xl border-2 border-line p-3">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold line-clamp-2">{{ p.caption || p.text || '(tanpa caption)' }}</p>
              <p class="mt-1 text-xs text-muted">{{ p.created_at ? dateTime(p.created_at) : '' }}</p>
            </div>
            <div class="shrink-0 text-right text-sm">
              <p>❤️ {{ number(p.likes || p.like_count || 0) }}</p>
              <p>💬 {{ number(p.comments || p.comment_count || 0) }}</p>
              <a v-if="p.url" :href="p.url" target="_blank" class="text-xs text-violet-600 hover:underline">Lihat post</a>
            </div>
          </div>
        </div>
        <p v-else class="mt-2 text-sm text-muted">Belum ada data post. Refresh statistik dulu.</p>
      </div>

      <!-- Recent comments -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-bold">Komen terbaru</h2>
        <div v-if="stats?.recent_comments?.length" class="mt-3 space-y-3">
          <div v-for="c in stats.recent_comments" :key="c.id || c.text" class="rounded-2xl border-2 border-line p-3">
            <p class="text-sm font-semibold">{{ c.author || c.username || 'Seseorang' }} <span class="text-xs font-normal text-muted">{{ c.created_at ? relative(c.created_at) : '' }}</span></p>
            <p class="mt-1 text-sm">{{ c.text || c.comment }}</p>
            <a v-if="c.post_url" :href="c.post_url" target="_blank" class="text-xs text-violet-600 hover:underline">Lihat post</a>
          </div>
        </div>
        <p v-else class="mt-2 text-sm text-muted">Belum ada data komen. Refresh statistik dulu.</p>
      </div>

      <!-- Publish -->
      <div class="card p-6">
        <div class="flex items-center justify-between gap-3">
          <h2 class="font-display text-lg font-bold">Upload video ke {{ platform === 'instagram' ? 'Instagram' : 'Facebook' }}</h2>
          <Badge color="sky" icon="film">Via Muse</Badge>
        </div>
        <p class="mt-1 text-sm text-muted">Pilih video yang sudah punya file (video_url). Muse akan upload ke {{ platform }} dengan caption dari Riwayat.</p>
        <div class="mt-4 grid gap-3">
          <div v-for="g in generations" :key="g.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-line p-3">
            <div class="min-w-0">
              <p class="font-bold">{{ g.keyword }}</p>
              <p class="text-xs text-muted">{{ g.durasi }} dtk • {{ g.model }} • {{ dateTime(g.created_at) }} • <span v-if="g.video_url" class="text-emerald-600">Ada video</span><span v-else class="text-amber-600">Belum ada video</span></p>
            </div>
            <button class="btn btn-primary btn-sm" :disabled="!g.video_url || publishing" @click="publish(g, platform)">Upload ke {{ platform === 'instagram' ? 'Instagram' : 'Facebook' }}</button>
          </div>
        </div>
      </div>

      <!-- Publish jobs -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-bold">Riwayat upload</h2>
        <div v-if="publishes.length" class="mt-3 space-y-2">
          <div v-for="p in publishes" :key="p.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line px-3 py-2 text-sm">
            <span class="font-semibold">{{ p.generation?.keyword || 'Video' }} • {{ p.platform }}</span>
            <span class="flex items-center gap-2">
              <Badge :color="p.status === 'completed' ? 'emerald' : p.status === 'failed' ? 'rose' : 'sky'">{{ p.status }}</Badge>
              <a v-if="p.post_url" :href="p.post_url" target="_blank" class="text-violet-600 hover:underline">Lihat post</a>
            </span>
          </div>
        </div>
        <p v-else class="mt-2 text-sm text-muted">Belum ada job upload.</p>
      </div>
    </template>
  </div>
</template>
