<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import Modal from '@/components/ui/Modal.vue';
import Segmented from '@/components/ui/Segmented.vue';
import Spinner from '@/components/ui/Spinner.vue';
import PhonePreview from './PhonePreview.vue';
import api, { errorMessage } from '@/lib/api.js';
import { downloadFromApi } from '@/lib/download.js';
import { captionText, dateTime, GEN_STATUS, isPendingMuse, modelColor, modelLabel, partText, relative, workflowColor, workflowLabel, WORKFLOW } from '@/lib/format.js';
import { confirmDialog, copyText, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';
import { useAuthStore } from '@/stores/auth.js';

/* Detail satu video: pratinjau, naskah (bisa diedit + versi), prompt, caption, komentar & alur review. */
const props = defineProps({ id: { type: [Number, String], default: null } });
const emit = defineEmits(['close', 'changed', 'deleted']);

const auth = useAuthStore();
const router = useRouter();
const g = ref(null);
const loading = ref(false);
const busy = ref('');
const tab = ref('preview');
const editing = ref(false);
const draft = reactive({ naskah: [], caption: '' });
const versions = ref([]);
const comments = ref([]);
const commentBody = ref('');
const scheduleAt = ref('');

const isOwner = computed(() => g.value && auth.user && g.value.user_id === auth.user.id);
const canEdit = computed(() => isOwner.value || auth.isReviewer);
const tabs = computed(() => [
    { value: 'preview', label: 'Pratinjau', icon: 'smartphone' },
    { value: 'naskah', label: 'Naskah', icon: 'file-text' },
    { value: 'prompts', label: 'Prompt', icon: 'image' },
    { value: 'caption', label: 'Caption', icon: 'hash' },
    { value: 'versions', label: 'Versi', icon: 'history', count: versions.value.length || null },
    { value: 'comments', label: 'Komentar', icon: 'message-circle', count: comments.value.length || null },
]);
const steps = ['draft', 'review', 'approved', 'published'];
const stepIndex = computed(() => {
    const s = g.value?.workflow_status;
    if (s === 'revisi') return 1;
    return Math.max(0, steps.indexOf(s));
});

async function load(silent = false) {
    if (!props.id) return;
    if (!silent) loading.value = true;
    try {
        const [d, v, c] = await Promise.all([
            api.get(`/generations/${props.id}`),
            api.get(`/generations/${props.id}/versions`),
            api.get(`/generations/${props.id}/comments`, { params: { per_page: 50 } }),
        ]);
        g.value = d.data.data;
        versions.value = v.data.data?.data || [];
        comments.value = c.data.data?.data || [];
        scheduleAt.value = g.value.publish_at ? toLocalInput(g.value.publish_at) : '';
        if (!editing.value) resetDraft();
    } catch (e) {
        if (!silent) {
            toast.error(errorMessage(e, 'Video tidak bisa dibuka.'));
            emit('close');
        }
    } finally {
        loading.value = false;
    }
}
function resetDraft() {
    draft.naskah = (g.value?.naskah || []).map((n) => ({ ...n, teks: partText(n) }));
    draft.caption = captionText(g.value?.caption);
}
function toLocalInput(v) {
    const d = new Date(v);
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

watch(
    () => props.id,
    (id) => {
        g.value = null;
        editing.value = false;
        tab.value = 'preview';
        commentBody.value = '';
        if (id) load();
    },
    { immediate: true },
);
useRealtime(['generations', 'review', 'comments'], () => props.id && load(true));

async function act(key, fn, okMsg) {
    busy.value = key;
    try {
        const res = await fn();
        if (okMsg !== false) toast.success(okMsg || res?.data?.message || 'Tersimpan.');
        await load(true);
        emit('changed');
        return res;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        busy.value = '';
    }
}

const saveNaskah = () =>
    act('save', () => api.put(`/generations/${props.id}/naskah`, { naskah: draft.naskah, caption: draft.caption })).then((r) => {
        if (r) editing.value = false;
    });
const toggleFavorite = () => act('fav', () => api.post(`/generations/${props.id}/toggle-favorite`));
const toggleArchive = () => act('arch', () => api.post(`/generations/${props.id}/toggle-archive`));
const submitReview = () => act('review', () => api.post(`/generations/${props.id}/submit-review`));
const approve = () => act('approve', () => api.post(`/generations/${props.id}/approve`));
const publish = async () => {
    if (await confirmDialog({ title: 'Terbitkan video ini?', message: 'Status berubah menjadi Terbit dan pemilik mendapat notifikasi.', confirmText: 'Terbitkan', icon: 'send' })) act('publish', () => api.post(`/generations/${props.id}/publish`));
};
const reject = async () => {
    const note = await confirmDialog({ title: 'Minta revisi', message: 'Tulis apa yang perlu diperbaiki. Pemilik video akan menerima catatan ini.', confirmText: 'Kirim catatan', icon: 'rotate-ccw', input: { label: 'Catatan revisi', placeholder: 'Contoh: bagian 3 terlalu panjang untuk durasi 30 detik', required: true, multiline: true } });
    if (note) act('reject', () => api.post(`/generations/${props.id}/reject`, { review_note: note }));
};
const saveSchedule = () => act('schedule', () => api.post(`/generations/${props.id}/schedule-publish`, { publish_at: scheduleAt.value ? new Date(scheduleAt.value).toISOString() : null }));
const restore = async (v) => {
    if (await confirmDialog({ title: `Pulihkan versi ${v.version_number}?`, message: 'Naskah saat ini disimpan dulu sebagai versi baru, jadi tidak ada yang hilang.', confirmText: 'Pulihkan', icon: 'history' })) act('restore', () => api.post(`/generations/${props.id}/versions/${v.id}/restore`));
};
async function sendComment() {
    if (!commentBody.value.trim()) return;
    const r = await act('comment', () => api.post(`/generations/${props.id}/comments`, { body: commentBody.value.trim() }), false);
    if (r) commentBody.value = '';
}
const deleteComment = async (c) => {
    if (await confirmDialog({ title: 'Hapus komentar?', tone: 'danger', confirmText: 'Hapus' })) act('del-comment', () => api.delete(`/generations/${props.id}/comments/${c.id}`));
};
async function remove() {
    if (!(await confirmDialog({ title: 'Hapus video ini?', message: `"${g.value.keyword}" beserta versi & komentarnya akan dihapus permanen.`, tone: 'danger', confirmText: 'Hapus permanen' }))) return;
    busy.value = 'delete';
    try {
        await api.delete(`/generations/${props.id}`);
        toast.success('Video dihapus.');
        emit('deleted');
        emit('close');
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        busy.value = '';
    }
}
function regenerate() {
    emit('close');
    router.push({ path: '/generate', query: { keyword: g.value.keyword, durasi: g.value.durasi, model: g.value.model } });
}
const download = () => downloadFromApi(`/generations/${props.id}/download-txt`, `ruang-dharma-${g.value.keyword}.txt`);
</script>

<template>
    <Modal :show="!!id" size="xl" @close="emit('close')">
        <template #header>
            <div v-if="g" class="flex flex-wrap items-center gap-2">
                <h2 class="font-display text-xl font-bold">{{ g.keyword }}</h2>
                <Badge :color="workflowColor(g.workflow_status)" :icon="WORKFLOW[g.workflow_status]?.icon">{{ workflowLabel(g.workflow_status) }}</Badge>
                <Badge v-if="g.is_favorite" color="yellow" icon="star">Favorit</Badge>
                <Badge v-if="g.is_archived" color="zinc" icon="archive">Arsip</Badge>
                <Badge v-if="GEN_STATUS[g.status]" :color="GEN_STATUS[g.status].color" :icon="GEN_STATUS[g.status].icon">{{ GEN_STATUS[g.status].label }}</Badge>
                <Badge v-if="g.video_url" color="sky" icon="film">Video Muse</Badge>
            </div>
            <div v-else class="skeleton h-7 w-48"></div>
            <p v-if="g" class="mt-1 text-sm text-muted">
                {{ g.durasi }} detik · <Badge :color="modelColor(g.model)" size="xs">{{ modelLabel(g.model) }}</Badge> · dibuat {{ relative(g.created_at) }}
                <span v-if="g.user && !isOwner"> oleh <b class="text-ink">{{ g.user.name }}</b></span>
            </p>
        </template>

        <div v-if="loading && !g" class="grid place-items-center py-24 text-muted"><Spinner class="h-8 w-8" /></div>
        <div v-else-if="g" class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="min-w-0">
                <Segmented v-model="tab" :options="tabs" class="mb-5" />

                <!-- Pratinjau -->
                <div v-if="tab === 'preview'" class="py-2">
                    <div v-if="isPendingMuse(g)" class="rounded-3xl border-2 border-dashed border-sky-300 bg-sky-50 p-6 text-center text-sm text-sky-900 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200">
                        <Icon name="hourglass" class="mx-auto mb-2 h-8 w-8" />
                        <p class="font-bold">{{ GEN_STATUS[g.status].label }} — job #{{ g.muse_job_id }}</p>
                        <p class="mt-1">Video, naskah & caption muncul di sini otomatis setelah Muse mengirim hasilnya.</p>
                    </div>
                    <div v-else-if="g.video_url" class="space-y-3">
                        <video :src="g.video_url" controls playsinline preload="metadata" class="mx-auto max-h-[65vh] rounded-2xl border-2 border-ink bg-black"></video>
                        <div class="flex flex-wrap justify-center gap-2">
                            <a :href="g.video_url" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><Icon name="external-link" class="h-4 w-4" /> Buka video</a>
                            <a v-for="(f, i) in g.video_files || []" :key="i" :href="typeof f === 'string' ? f : f.url" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><Icon name="film" class="h-4 w-4" /> Klip {{ i + 1 }}</a>
                        </div>
                    </div>
                    <PhonePreview v-else :naskah="g.naskah || []" :title="g.keyword" :model="g.model" />
                </div>

                <!-- Naskah -->
                <div v-else-if="tab === 'naskah'">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-muted">{{ (g.naskah || []).length }} bagian · setiap simpan membuat versi baru</p>
                        <div v-if="canEdit" class="flex gap-2">
                            <template v-if="editing">
                                <button type="button" class="btn btn-ghost btn-sm" @click="editing = false; resetDraft()">Batal</button>
                                <button type="button" class="btn btn-primary btn-sm" :disabled="busy === 'save'" @click="saveNaskah"><Spinner v-if="busy === 'save'" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan versi</button>
                            </template>
                            <button v-else type="button" class="btn btn-secondary btn-sm" @click="editing = true"><Icon name="pencil" class="h-4 w-4" /> Edit naskah</button>
                        </div>
                    </div>
                    <ol class="space-y-3">
                        <li v-for="(n, i) in editing ? draft.naskah : g.naskah" :key="i" class="rounded-2xl border border-line p-4">
                            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                                <span class="grid h-6 w-6 place-items-center rounded-lg bg-ink text-[11px] font-extrabold text-surface">{{ n.bagian || i + 1 }}</span>
                                <Badge :color="n.speaker === 'Anak' ? 'sky' : n.speaker === 'Ibu' ? 'orange' : 'violet'" size="xs">{{ n.speaker }}</Badge>
                                <span class="text-muted">{{ n.suara }}</span>
                                <span v-if="n.estimasi_detik" class="ml-auto font-semibold text-muted">±{{ n.estimasi_detik }} dtk</span>
                            </div>
                            <textarea v-if="editing" v-model="n.teks" rows="3" class="input" :aria-label="`Teks bagian ${i + 1}`"></textarea>
                            <p v-else class="text-[15px] leading-relaxed">{{ partText(n) }}</p>
                        </li>
                    </ol>
                    <div v-if="editing" class="mt-4">
                        <label class="label" for="edit-caption">Caption</label>
                        <textarea id="edit-caption" v-model="draft.caption" rows="5" class="input"></textarea>
                    </div>
                </div>

                <!-- Prompt -->
                <ol v-else-if="tab === 'prompts'" class="space-y-3">
                    <li v-for="(p, i) in g.prompts || []" :key="i" class="group flex gap-3 rounded-2xl border border-line p-4">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-yellow-300 text-xs font-extrabold text-zinc-900">{{ i + 1 }}</span>
                        <p class="flex-1 text-sm leading-relaxed">{{ p }}</p>
                        <button type="button" class="btn btn-ghost btn-icon btn-sm opacity-60 group-hover:opacity-100" aria-label="Salin prompt" @click="copyText(p, `Prompt bagian ${i + 1}`)"><Icon name="copy" class="h-4 w-4" /></button>
                    </li>
                </ol>

                <!-- Caption -->
                <div v-else-if="tab === 'caption'">
                    <pre class="rounded-2xl border border-line bg-surface-2/60 p-4 font-sans text-sm leading-relaxed whitespace-pre-wrap">{{ captionText(g.caption) }}</pre>
                    <button type="button" class="btn btn-primary btn-sm mt-3" @click="copyText(captionText(g.caption), 'Caption')"><Icon name="copy" class="h-4 w-4" /> Salin caption</button>
                </div>

                <!-- Versi -->
                <div v-else-if="tab === 'versions'">
                    <ul v-if="versions.length" class="space-y-2">
                        <li v-for="v in versions" :key="v.id" class="flex items-center gap-3 rounded-2xl border border-line p-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-surface-2 font-display font-bold">v{{ v.version_number }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ partText((v.naskah || [])[0]) || 'Naskah' }}</p>
                                <p class="text-xs text-muted">{{ dateTime(v.created_at) }}<span v-if="v.editor"> oleh {{ v.editor.name }}</span></p>
                            </div>
                            <button v-if="canEdit" type="button" class="btn btn-secondary btn-sm" :disabled="busy === 'restore'" @click="restore(v)"><Icon name="rotate-ccw" class="h-4 w-4" /> Pulihkan</button>
                        </li>
                    </ul>
                    <EmptyState v-else compact icon="history" title="Belum ada versi" description="Versi tersimpan otomatis setiap kali naskah diedit." />
                </div>

                <!-- Komentar -->
                <div v-else-if="tab === 'comments'">
                    <form class="mb-4 flex gap-2" @submit.prevent="sendComment">
                        <Avatar :user="auth.user" size="md" />
                        <input v-model="commentBody" class="input flex-1" placeholder="Tulis komentar atau masukan…" maxlength="2000" aria-label="Komentar" />
                        <button type="submit" class="btn btn-primary" :disabled="!commentBody.trim() || busy === 'comment'"><Icon name="send-horizontal" class="h-4 w-4" /></button>
                    </form>
                    <ul v-if="comments.length" class="space-y-3">
                        <li v-for="c in comments" :key="c.id" class="flex gap-3">
                            <Avatar :user="c.user" size="sm" />
                            <div class="min-w-0 flex-1 rounded-2xl rounded-tl-md bg-surface-2 px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-bold">{{ c.user?.name }}</p>
                                    <p class="text-xs text-muted">{{ relative(c.created_at) }}</p>
                                    <button v-if="c.user_id === auth.user?.id || auth.isAdmin" type="button" class="ml-auto text-muted hover:text-rose-500" aria-label="Hapus komentar" @click="deleteComment(c)"><Icon name="trash-2" class="h-3.5 w-3.5" /></button>
                                </div>
                                <p class="mt-0.5 text-sm whitespace-pre-line">{{ c.body }}</p>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else compact icon="message-circle" title="Belum ada komentar" description="Diskusikan naskah ini dengan reviewer di sini." />
                </div>
            </div>

            <!-- Panel samping: alur & aksi -->
            <aside class="space-y-4">
                <div class="rounded-3xl border border-line p-4">
                    <p class="mb-3 text-sm font-bold">Alur review</p>
                    <ol class="space-y-2.5">
                        <li v-for="(s, i) in steps" :key="s" class="flex items-center gap-2.5 text-sm">
                            <span :class="i < stepIndex || (i === stepIndex && s === 'published') ? 'bg-emerald-400 border-ink text-zinc-900' : i === stepIndex ? (g.workflow_status === 'revisi' ? 'bg-rose-400 border-ink text-zinc-900' : 'bg-yellow-300 border-ink text-zinc-900') : 'border-line text-muted'" class="grid h-6 w-6 place-items-center rounded-lg border-2 text-[11px] font-extrabold">
                                <Icon v-if="i < stepIndex || (i === stepIndex && s === 'published')" name="check" class="h-3.5 w-3.5" :stroke="3" />
                                <span v-else>{{ i + 1 }}</span>
                            </span>
                            <span :class="i <= stepIndex ? 'font-semibold' : 'text-muted'">{{ i === 1 && g.workflow_status === 'revisi' ? 'Perlu revisi' : workflowLabel(s) }}</span>
                        </li>
                    </ol>
                    <div v-if="g.review_note" class="mt-3 rounded-2xl border-2 border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                        <p class="font-bold">Catatan reviewer{{ g.reviewer ? ` · ${g.reviewer.name}` : '' }}</p>
                        <p class="mt-0.5">{{ g.review_note }}</p>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <button v-if="isOwner && ['draft', 'revisi', 'archived'].includes(g.workflow_status)" type="button" class="btn btn-brutal btn-sm" :disabled="busy === 'review'" @click="submitReview"><Icon name="send" class="h-4 w-4" /> Kirim ke review</button>
                        <template v-if="auth.isReviewer">
                            <button v-if="['review', 'revisi', 'draft'].includes(g.workflow_status)" type="button" class="btn btn-success btn-sm" :disabled="busy === 'approve'" @click="approve"><Icon name="badge-check" class="h-4 w-4" /> Setujui</button>
                            <button v-if="['review', 'approved', 'draft'].includes(g.workflow_status)" type="button" class="btn btn-secondary btn-sm" :disabled="busy === 'reject'" @click="reject"><Icon name="rotate-ccw" class="h-4 w-4" /> Minta revisi</button>
                            <button v-if="g.workflow_status !== 'published'" type="button" class="btn btn-primary btn-sm" :disabled="busy === 'publish'" @click="publish"><Icon name="send" class="h-4 w-4" /> Terbitkan</button>
                        </template>
                    </div>
                </div>

                <div v-if="isOwner" class="rounded-3xl border border-line p-4">
                    <label class="mb-2 block text-sm font-bold" for="schedule-at">Jadwal tayang</label>
                    <input id="schedule-at" v-model="scheduleAt" type="datetime-local" class="input input-sm" />
                    <button type="button" class="btn btn-secondary btn-sm mt-2 w-full" :disabled="busy === 'schedule' || (!scheduleAt && !g.publish_at)" @click="saveSchedule"><Icon name="calendar-check" class="h-4 w-4" /> {{ !scheduleAt && g.publish_at ? 'Hapus jadwal' : 'Simpan jadwal' }}</button>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" @click="copyText(captionText(g.caption), 'Caption')"><Icon name="copy" class="h-4 w-4" /> Caption</button>
                    <button type="button" class="btn btn-secondary btn-sm" @click="download"><Icon name="download" class="h-4 w-4" /> Unduh .txt</button>
                    <button type="button" class="btn btn-secondary btn-sm" @click="regenerate"><Icon name="refresh-cw" class="h-4 w-4" /> Buat ulang</button>
                    <button v-if="isOwner" type="button" :class="g.is_favorite ? 'border-yellow-400 text-yellow-700 dark:text-yellow-300' : ''" class="btn btn-secondary btn-sm" :disabled="busy === 'fav'" @click="toggleFavorite"><Icon name="star" class="h-4 w-4" /> {{ g.is_favorite ? 'Favorit' : 'Favoritkan' }}</button>
                    <button v-if="isOwner" type="button" class="btn btn-secondary btn-sm" :disabled="busy === 'arch'" @click="toggleArchive"><Icon :name="g.is_archived ? 'archive-restore' : 'archive'" class="h-4 w-4" /> {{ g.is_archived ? 'Pulihkan' : 'Arsipkan' }}</button>
                    <button v-if="isOwner" type="button" class="btn btn-soft-danger btn-sm" :disabled="busy === 'delete'" @click="remove"><Icon name="trash-2" class="h-4 w-4" /> Hapus</button>
                </div>
            </aside>
        </div>
    </Modal>
</template>
