<script setup>
import { onMounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { confirmDialog, toast } from '@/lib/toast.js';

const info = ref(null);
const loading = ref(true);
const uploading = ref(false);
const syncing = ref(false);
const notifying = ref(false);
const errors = ref({});

const apkFile = ref(null);
const apkFileName = ref('');
const manualVersion = ref('');
const notes = ref('');
const notifyUsers = ref(true);

async function load() {
    try {
        const res = await api.get('/admin/app-update');
        info.value = res.data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}
onMounted(load);

function onFileSelect(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.apk')) {
        toast.error('Berkas harus berekstensi .apk');
        return;
    }
    apkFile.value = file;
    apkFileName.value = file.name;
    errors.value = {};
}

async function uploadApk() {
    if (!apkFile.value) {
        toast.error('Pilih berkas APK terlebih dahulu.');
        return;
    }
    uploading.value = true;
    errors.value = {};
    try {
        const fd = new FormData();
        fd.append('apk', apkFile.value);
        if (manualVersion.value.trim()) fd.append('version', manualVersion.value.trim());
        if (notes.value.trim()) fd.append('notes', notes.value.trim());
        fd.append('notify', notifyUsers.value ? '1' : '0');

        const { data } = await api.post('/admin/app-update', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        toast.success(data.message);
        apkFile.value = null;
        apkFileName.value = '';
        manualVersion.value = '';
        notes.value = '';
        await load();
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        uploading.value = false;
    }
}

async function deleteApk() {
    if (!await confirmDialog('Hapus APK pembaruan dari server? Aplikasi HP akan kembali menggunakan rilis GitHub.')) return;
    try {
        const { data } = await api.delete('/admin/app-update');
        toast.success(data.message);
        await load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function syncGitHub() {
    syncing.value = true;
    try {
        const { data } = await api.post('/admin/app-update/sync-github');
        toast.success(data.message);
        await load();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        syncing.value = false;
    }
}

async function sendNotification() {
    notifying.value = true;
    try {
        const { data } = await api.post('/admin/app-update/notify');
        toast.success(data.message);
        await load();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        notifying.value = false;
    }
}

function copySha() {
    if (!info.value?.current?.sha256) return;
    navigator.clipboard?.writeText(info.value.current.sha256);
    toast.success('SHA-256 disalin ke papan klip.');
}

function formatBytes(bytes) {
    if (!bytes) return '0 MB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}
</script>

<template>
    <div v-if="loading" class="skeleton h-96 rounded-3xl"></div>
    <div v-else class="space-y-6">
        <!-- Banner status -->
        <div class="card space-y-4 p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                        <Icon name="smartphone" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Pembaruan Aplikasi Android (Auto Update)</h3>
                        <p class="text-sm text-muted">Aplikasi HP memeriksa, mengunduh, dan memasang pembaruan secara otomatis seperti di SISINDEN.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-secondary text-xs" :disabled="syncing" @click="syncGitHub">
                        <Spinner v-if="syncing" class="h-3.5 w-3.5" />
                        <Icon v-else name="refresh-cw" class="h-3.5 w-3.5" />
                        Sinkronkan GitHub
                    </button>
                    <button v-if="info?.current && info?.push_ready" type="button" class="btn btn-primary text-xs" :disabled="notifying" @click="sendNotification">
                        <Spinner v-if="notifying" class="h-3.5 w-3.5" />
                        <Icon v-else name="bell" class="h-3.5 w-3.5" />
                        Kirim Notifikasi ke Semua HP
                    </button>
                </div>
            </div>

            <!-- Kartu versi aktif -->
            <div v-if="info?.current" class="rounded-2xl border-2 border-line bg-surface-2 p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-extrabold text-ink">v{{ info.current.version }}</span>
                            <Badge :variant="info.current.source === 'manual' ? 'brand' : 'accent'">
                                {{ info.current.source === 'manual' ? 'Unggahan Server' : 'Rilis GitHub' }}
                            </Badge>
                            <span class="text-xs text-muted">(versionCode {{ info.current.version_code || '—' }})</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">
                            Ukuran: <strong class="text-ink">{{ formatBytes(info.current.size) }}</strong> ·
                            Disediakan oleh: <strong class="text-ink">{{ info.current.uploaded_by }}</strong> ·
                            Waktu: {{ new Date(info.current.uploaded_at).toLocaleString('id-ID') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a :href="info.current.download_url" target="_blank" class="btn btn-secondary text-xs">
                            <Icon name="download" class="h-3.5 w-3.5" />
                            Unduh APK
                        </a>
                        <button v-if="info.current.source === 'manual'" type="button" class="btn btn-danger text-xs" @click="deleteApk">
                            <Icon name="trash-2" class="h-3.5 w-3.5" />
                            Hapus
                        </button>
                    </div>
                </div>

                <div v-if="info.current.sha256" class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-line bg-surface px-3 py-2 text-xs">
                    <span class="truncate font-mono text-muted">
                        <strong class="text-ink">SHA-256:</strong> {{ info.current.sha256 }}
                    </span>
                    <button type="button" class="text-brand hover:underline shrink-0" @click="copySha">Salin</button>
                </div>

                <div v-if="info.current.notes" class="mt-3 text-xs text-muted">
                    <p class="font-bold text-ink">Catatan rilis:</p>
                    <p class="mt-1 whitespace-pre-wrap rounded-xl border border-line bg-surface p-3">{{ info.current.notes }}</p>
                </div>
            </div>
            <div v-else class="rounded-2xl border-2 border-dashed border-line p-6 text-center text-sm text-muted">
                Belum ada berkas pembaruan di server maupun di GitHub Releases. Unggah APK di bawah atau buat rilis di GitHub.
            </div>
        </div>

        <!-- Unggah APK baru -->
        <div class="card space-y-5 p-6">
            <div>
                <h4 class="font-bold">Unggah APK Baru (Manual)</h4>
                <p class="text-xs text-muted">Unggah berkas APK Android rilis resmi (<code class="font-mono">id.ruangdharma.studio</code>). Versi dan tanda tangan akan diperiksa otomatis.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="label">Berkas APK (.apk)</label>
                    <div class="flex items-center gap-3">
                        <label class="btn btn-secondary cursor-pointer">
                            <Icon name="upload" class="h-4 w-4" />
                            Pilih Berkas APK
                            <input type="file" accept=".apk" class="hidden" @change="onFileSelect" />
                        </label>
                        <span class="text-xs text-muted truncate max-w-xs">{{ apkFileName || 'Belum ada berkas dipilih' }}</span>
                    </div>
                    <p class="help">Maksimal {{ info?.upload_limit_mb || 200 }} MB (dibatasi konfigurasi PHP server).</p>
                    <p v-if="errors.apk" class="field-error">{{ errors.apk }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Nomor Versi <span class="text-muted font-normal">(opsional, default dari manifest)</span></label>
                        <input v-model="manualVersion" class="input" placeholder="contoh: 1.3.0" />
                        <p v-if="errors.version" class="field-error">{{ errors.version }}</p>
                    </div>
                    <div>
                        <label class="label">Pengumuman ke Pengguna</label>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer">
                            <input v-model="notifyUsers" type="checkbox" class="checkbox" />
                            <span class="text-sm">Kirim notifikasi push FCM ke semua HP setelah diunggah</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="label">Catatan Rilis (Changelog)</label>
                    <textarea v-model="notes" rows="3" class="input" placeholder="Apa saja yang baru di versi ini…"></textarea>
                    <p v-if="errors.notes" class="field-error">{{ errors.notes }}</p>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="btn btn-primary" :disabled="uploading || !apkFile" @click="uploadApk">
                        <Spinner v-if="uploading" class="h-4 w-4" />
                        <Icon v-else name="upload" class="h-4 w-4" />
                        Unggah & Aktifkan Pembaruan
                    </button>
                </div>
            </div>
        </div>

        <!-- Panduan teknis -->
        <div class="rounded-2xl border border-line bg-surface-2 p-5 text-xs text-muted space-y-2">
            <p class="font-bold text-ink">Cara Kerja Auto Update di HP:</p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Otomatis lewat CI/CD:</strong> Setiap rilis baru di GitHub Actions (<code class="font-mono">v*</code>) otomatis terdeteksi server via API GitHub Releases + SHA-256. Pengguna HP langsung mendapat pembaruan tanpa perlu unggah manual ke server.</li>
                <li><strong>Keamanan:</strong> Aplikasi HP memverifikasi SHA-256 dan memastikan sertifikat keystore rilis cocok persis sebelum memulai pemasangan lewat <code class="font-mono">PackageInstaller</code> Android.</li>
                <li><strong>Tanpa Konfirmasi (Android 12+):</strong> Di perangkat Android 12 ke atas, pembaruan berikutnya dapat terpasang di latar belakang tanpa repot menekan tombol konfirmasi.</li>
            </ul>
        </div>
    </div>
</template>
