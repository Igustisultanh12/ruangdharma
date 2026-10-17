<script setup>
import { computed, reactive, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import Modal from '@/components/ui/Modal.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import Segmented from '@/components/ui/Segmented.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { bytes, relative } from '@/lib/format.js';
import { confirmDialog, copyText, toast } from '@/lib/toast.js';
import { useList } from '@/composables/useList.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const list = useList('/media', { perPage: 18, filters: { search: '', type: '' }, realtime: ['media'] });
const f = list.filters;
const showUpload = ref(false);
const preview = ref(null);
const uploading = ref(false);
const progress = ref(0);
const errors = ref({});
const dragOver = ref(false);
const form = reactive({ title: '', tags: '', file: null });
const filePreview = computed(() => (form.file ? URL.createObjectURL(form.file) : null));

function pick(file) {
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) return toast.error('Ukuran file maksimal 10 MB.');
    form.file = file;
    if (!form.title) form.title = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
    showUpload.value = true;
}
function onDrop(e) {
    dragOver.value = false;
    pick(e.dataTransfer.files?.[0]);
}
async function upload() {
    if (!form.file) return;
    uploading.value = true;
    progress.value = 0;
    errors.value = {};
    const fd = new FormData();
    fd.append('title', form.title.trim());
    fd.append('file', form.file);
    form.tags.split(',').map((t) => t.trim()).filter(Boolean).forEach((t) => fd.append('tags[]', t));
    try {
        await api.post('/media', fd, { onUploadProgress: (e) => (progress.value = e.total ? Math.round((e.loaded / e.total) * 100) : 0) });
        toast.success('Media diunggah.');
        showUpload.value = false;
        Object.assign(form, { title: '', tags: '', file: null });
        list.load({ page: 1, silent: true });
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e, 'Gagal mengunggah.'));
    } finally {
        uploading.value = false;
    }
}
async function remove(m) {
    if (!(await confirmDialog({ title: `Hapus "${m.title}"?`, message: 'File akan dihapus dari server.', tone: 'danger', confirmText: 'Hapus' }))) return;
    try {
        await api.delete(`/media/${m.id}`);
        toast.success('Media dihapus.');
        preview.value = null;
        list.load({ silent: true });
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
const canDelete = (m) => m.user_id === auth.user?.id || auth.isAdmin;
</script>

<template>
    <div @dragover.prevent="dragOver = true" @dragleave.self="dragOver = false" @drop.prevent="onDrop">
        <PageHeader title="Media" description="Simpan aset gambar & video untuk konten. Seret file ke halaman ini untuk mengunggah.">
            <template #actions>
                <label class="btn btn-primary cursor-pointer"><Icon name="upload" class="h-4 w-4" /> Unggah media<input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm" @change="pick($event.target.files[0]); $event.target.value = ''" /></label>
            </template>
        </PageHeader>

        <div v-if="dragOver" class="pointer-events-none fixed inset-0 z-[60] grid place-items-center bg-violet-600/20 backdrop-blur-sm">
            <div class="rounded-3xl border-4 border-dashed border-ink bg-yellow-300 px-10 py-8 text-center font-display text-2xl font-bold text-zinc-900 shadow-brutal-lg"><Icon name="upload" class="mx-auto mb-2 h-10 w-10" /> Lepaskan untuk mengunggah</div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Segmented v-model="f.type" :options="[{ value: '', label: 'Semua', icon: 'images' }, { value: 'image', label: 'Gambar', icon: 'image' }, { value: 'video', label: 'Video', icon: 'film' }]" />
            <SearchInput v-model="f.search" placeholder="Cari judul…" class="w-full sm:ml-auto sm:w-64" />
        </div>

        <div v-if="list.loading.value && !list.loaded.value" class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6"><div v-for="i in 12" :key="i" class="skeleton aspect-square rounded-3xl"></div></div>
        <div v-else-if="!list.items.value.length" class="card">
            <EmptyState icon="images" title="Belum ada media" description="Unggah ilustrasi, foto canang, atau klip video untuk dipakai di konten.">
                <label class="btn btn-primary btn-sm cursor-pointer"><Icon name="upload" class="h-4 w-4" /> Pilih file<input type="file" class="sr-only" accept="image/*,video/*" @change="pick($event.target.files[0])" /></label>
            </EmptyState>
        </div>
        <div v-else class="card overflow-hidden">
            <div class="grid grid-cols-2 gap-4 p-4 md:grid-cols-4 xl:grid-cols-6">
                <button v-for="m in list.items.value" :key="m.id" type="button" class="group relative aspect-square overflow-hidden rounded-3xl border border-line bg-surface-2 text-left" @click="preview = m">
                    <img v-if="m.type === 'image'" :src="m.url" :alt="m.title" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                    <video v-else :src="m.url" muted preload="metadata" class="h-full w-full object-cover"></video>
                    <span v-if="m.type === 'video'" class="absolute top-2 left-2 grid h-7 w-7 place-items-center rounded-lg bg-black/50 text-white"><Icon name="play" class="h-3.5 w-3.5" /></span>
                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-3 pt-8 text-white">
                        <span class="block truncate text-sm font-bold">{{ m.title }}</span>
                        <span class="block text-[11px] text-white/75">{{ bytes(m.size) }} · {{ relative(m.created_at) }}</span>
                    </span>
                </button>
            </div>
            <Pagination :meta="list.meta" label="media" @go="list.go" />
        </div>

        <!-- Unggah -->
        <Modal :show="showUpload" title="Unggah media" size="md" @close="!uploading && (showUpload = false)">
            <div class="space-y-4">
                <div class="overflow-hidden rounded-2xl border-2 border-dashed border-line bg-surface-2">
                    <img v-if="form.file?.type.startsWith('image/')" :src="filePreview" alt="" class="max-h-64 w-full object-contain" />
                    <video v-else-if="form.file" :src="filePreview" controls class="max-h-64 w-full"></video>
                    <p class="px-3 py-2 text-xs text-muted">{{ form.file?.name }} · {{ bytes(form.file?.size) }}</p>
                </div>
                <div>
                    <label class="label" for="m-title">Judul</label>
                    <input id="m-title" v-model="form.title" class="input" maxlength="255" :class="{ 'input-error': errors.title }" />
                    <p v-if="errors.title" class="field-error">{{ errors.title }}</p>
                </div>
                <div>
                    <label class="label" for="m-tags">Tag <span class="font-normal text-muted">(pisahkan dengan koma)</span></label>
                    <input id="m-tags" v-model="form.tags" class="input" placeholder="canang, upacara, pura" />
                </div>
                <p v-if="errors.file" class="field-error">{{ errors.file }}</p>
                <div v-if="uploading" class="h-2 overflow-hidden rounded-full bg-surface-2"><div class="h-full bg-brand-gradient transition-all" :style="{ width: `${progress}%` }"></div></div>
            </div>
            <template #footer>
                <button type="button" class="btn btn-ghost" :disabled="uploading" @click="showUpload = false">Batal</button>
                <button type="button" class="btn btn-primary" :disabled="uploading || !form.title.trim()" @click="upload"><Spinner v-if="uploading" class="h-4 w-4" /><Icon v-else name="upload" class="h-4 w-4" /> {{ uploading ? `Mengunggah ${progress}%` : 'Unggah' }}</button>
            </template>
        </Modal>

        <!-- Pratinjau -->
        <Modal :show="!!preview" :title="preview?.title || ''" :subtitle="preview ? `${bytes(preview.size)} · diunggah ${relative(preview.created_at)} oleh ${preview.user?.name || '—'}` : ''" size="lg" @close="preview = null">
            <template v-if="preview">
                <div class="overflow-hidden rounded-2xl bg-surface-2">
                    <img v-if="preview.type === 'image'" :src="preview.url" :alt="preview.title" class="max-h-[60vh] w-full object-contain" />
                    <video v-else :src="preview.url" controls class="max-h-[60vh] w-full"></video>
                </div>
                <div v-if="preview.tags?.length" class="mt-3 flex flex-wrap gap-1.5"><Badge v-for="t in preview.tags" :key="t" color="violet" size="xs">#{{ t }}</Badge></div>
            </template>
            <template #footer>
                <button v-if="preview && canDelete(preview)" type="button" class="btn btn-soft-danger mr-auto" @click="remove(preview)"><Icon name="trash-2" class="h-4 w-4" /> Hapus</button>
                <button type="button" class="btn btn-secondary" @click="copyText(preview?.url, 'Tautan')"><Icon name="link" class="h-4 w-4" /> Salin tautan</button>
                <RouterLink :to="{ path: '/generate', query: { keyword: preview?.title } }" class="btn btn-primary"><Icon name="wand-sparkles" class="h-4 w-4" /> Buat video dari tema ini</RouterLink>
            </template>
        </Modal>
    </div>
</template>
