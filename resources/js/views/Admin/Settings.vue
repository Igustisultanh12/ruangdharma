<script setup>
import { onMounted, reactive, ref } from 'vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Segmented from '@/components/ui/Segmented.vue';
import Spinner from '@/components/ui/Spinner.vue';
import TagsInput from '@/components/ui/TagsInput.vue';
import Toggle from '@/components/ui/Toggle.vue';
import PushSettings from '@/components/admin/PushSettings.vue';
import AppUpdateSettings from '@/components/admin/AppUpdateSettings.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const tab = ref('general');
const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const dirty = ref(false);
const s = reactive({
    app_name: '', app_description: '', app_logo: '', daily_limit: 5, blocked_keywords: [],
    auto_generate_enabled: false, auto_generate_time: '07:00', auto_generate_themes: [],
    password_min_length: 6, session_timeout: 120,
    notification_templates: {},
});
const TEMPLATE_KEYS = [
    { key: 'generate_success', label: 'Video selesai dibuat', vars: '{keyword}', def: 'Video "{keyword}" berhasil dibuat dan tersimpan di Riwayat.' },
    { key: 'review_needed', label: 'Naskah menunggu review (ke reviewer)', vars: '{name}, {keyword}', def: '{name} mengirim "{keyword}" untuk direview.' },
    { key: 'review_approved', label: 'Naskah disetujui (ke pembuat)', vars: '{keyword}, {reviewer}', def: 'Video "{keyword}" disetujui oleh {reviewer}.' },
    { key: 'review_revision', label: 'Naskah perlu revisi (ke pembuat)', vars: '{keyword}, {note}', def: 'Video "{keyword}": {note}' },
];

async function load() {
    try {
        const data = (await api.get('/admin/settings')).data.settings;
        Object.assign(s, data, { notification_templates: Array.isArray(data.notification_templates) ? {} : data.notification_templates || {} });
        dirty.value = false;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}
onMounted(load);
useRealtime(['settings'], () => !dirty.value && !saving.value && load());

const tabKeys = {
    general: ['app_name', 'app_description', 'app_logo', 'daily_limit', 'blocked_keywords'],
    auto_generate: ['auto_generate_enabled', 'auto_generate_time', 'auto_generate_themes'],
    security: ['password_min_length', 'session_timeout'],
    notifications: ['notification_templates'],
};
async function save() {
    saving.value = true;
    errors.value = {};
    const payload = Object.fromEntries(tabKeys[tab.value].map((k) => [k, s[k]]));
    if (tab.value === 'notifications') payload.notification_templates = Object.fromEntries(Object.entries(s.notification_templates).filter(([, v]) => v && v.trim()));
    try {
        const { data } = await api.put(`/admin/settings/${tab.value}`, payload);
        Object.assign(s, data.settings, { notification_templates: Array.isArray(data.settings.notification_templates) ? {} : data.settings.notification_templates });
        dirty.value = false;
        toast.success('Pengaturan tersimpan.');
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-4xl">
        <PageHeader title="Pengaturan" description="Atur identitas studio, batas kuota, kata terlarang, generate otomatis, dan keamanan." />
        <Segmented v-model="tab" :options="[{ value: 'general', label: 'Umum', icon: 'settings' }, { value: 'auto_generate', label: 'Generate otomatis', icon: 'calendar-clock' }, { value: 'security', label: 'Keamanan', icon: 'shield-check' }, { value: 'notifications', label: 'Template notifikasi', icon: 'bell' }, { value: 'push', label: 'Push HP', icon: 'smartphone' }, { value: 'app_update', label: 'Pembaruan APK', icon: 'download' }]" class="mb-5" />

        <PushSettings v-if="tab === 'push'" />
        <AppUpdateSettings v-else-if="tab === 'app_update'" />
        <div v-else-if="loading" class="skeleton h-96 rounded-3xl"></div>
        <form v-else class="card space-y-6 p-6" @submit.prevent="save" @input="dirty = true" @change="dirty = true">
            <template v-if="tab === 'general'">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="label" for="s-name">Nama aplikasi</label><input id="s-name" v-model="s.app_name" class="input" maxlength="100" /><p v-if="errors.app_name" class="field-error">{{ errors.app_name }}</p></div>
                    <div><label class="label" for="s-limit">Limit global video / hari</label><input id="s-limit" v-model.number="s.daily_limit" type="number" min="1" max="100" class="input" /><p class="help">Berlaku untuk pengguna tanpa paket atau limit khusus.</p><p v-if="errors.daily_limit" class="field-error">{{ errors.daily_limit }}</p></div>
                    <div class="sm:col-span-2"><label class="label" for="s-desc">Deskripsi</label><textarea id="s-desc" v-model="s.app_description" rows="2" class="input" maxlength="500"></textarea></div>
                    <div class="sm:col-span-2"><label class="label" for="s-logo">URL logo <span class="font-normal text-muted">(opsional)</span></label><input id="s-logo" v-model="s.app_logo" class="input" placeholder="https://…" /></div>
                </div>
                <div>
                    <label class="label" for="s-blocked">Kata kunci diblokir</label>
                    <TagsInput id="s-blocked" v-model="s.blocked_keywords" placeholder="Ketik kata lalu Enter" @update:model-value="dirty = true" />
                    <p class="help">Generate dengan tema yang mengandung kata ini akan ditolak.</p>
                </div>
            </template>

            <template v-else-if="tab === 'auto_generate'">
                <div class="flex items-center justify-between gap-4 rounded-2xl border-2 border-line p-4">
                    <div><p class="font-bold">Generate 3 video setiap hari</p><p class="text-sm text-muted">Berjalan lewat scheduler Laravel (cron <code class="rounded bg-surface-2 px-1">schedule:run</code> tiap menit) dengan rotasi gaya narasi → ngobrol → gabungan.</p></div>
                    <Toggle v-model="s.auto_generate_enabled" label="Aktifkan generate otomatis" @update:model-value="dirty = true" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[180px_1fr]">
                    <div><label class="label" for="s-time">Jam jalan</label><input id="s-time" v-model="s.auto_generate_time" type="time" class="input" /><p v-if="errors.auto_generate_time" class="field-error">{{ errors.auto_generate_time }}</p></div>
                    <div><label class="label" for="s-themes">Tema (maks. 10, 3 pertama dipakai)</label><TagsInput id="s-themes" v-model="s.auto_generate_themes" :max="10" @update:model-value="dirty = true" /></div>
                </div>
            </template>

            <template v-else-if="tab === 'security'">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="label" for="s-pwmin">Panjang minimal password</label><input id="s-pwmin" v-model.number="s.password_min_length" type="number" min="6" max="32" class="input" /><p class="help">Dipakai saat membuat akun & mengganti password.</p></div>
                    <div><label class="label" for="s-timeout">Logout otomatis setelah tidak aktif (menit)</label><input id="s-timeout" v-model.number="s.session_timeout" type="number" min="5" max="1440" class="input" /><p class="help">Pengguna yang tidak beraktivitas selama ini akan keluar otomatis.</p></div>
                </div>
            </template>

            <template v-else>
                <p class="text-sm text-muted">Kosongkan untuk memakai teks bawaan. Variabel di dalam kurung kurawal akan diganti otomatis.</p>
                <div v-for="t in TEMPLATE_KEYS" :key="t.key">
                    <label class="label" :for="`tpl-${t.key}`">{{ t.label }} <span class="font-mono text-xs font-normal text-muted">{{ t.vars }}</span></label>
                    <input :id="`tpl-${t.key}`" v-model="s.notification_templates[t.key]" class="input" :placeholder="t.def" />
                </div>
            </template>

            <div class="flex items-center justify-end gap-3 border-t border-line pt-5">
                <span v-if="dirty" class="text-xs font-semibold text-amber-600 dark:text-amber-400">Ada perubahan belum disimpan</span>
                <button type="submit" class="btn btn-primary" :disabled="saving"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan pengaturan</button>
            </div>
        </form>
    </div>
</template>
