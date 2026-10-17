<script setup>
import { computed, reactive, ref } from 'vue';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Segmented from '@/components/ui/Segmented.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Toggle from '@/components/ui/Toggle.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { date, DURATIONS, MODELS, roleLabel } from '@/lib/format.js';
import { toast } from '@/lib/toast.js';
import { useAuthStore } from '@/stores/auth.js';
import { useRealtimeStore } from '@/stores/realtime.js';
import { useUiStore } from '@/stores/ui.js';

const auth = useAuthStore();
const rt = useRealtimeStore();
const ui = useUiStore();
const tab = ref('profile');
const saving = ref(false);
const errors = ref({});
const form = reactive({ name: auth.user?.name || '', email: auth.user?.email || '', default_durasi: auth.user?.default_durasi || 60, default_model: auth.user?.default_model || 'narasi' });
const avatarFile = ref(null);
const avatarPreview = computed(() => (avatarFile.value ? URL.createObjectURL(avatarFile.value) : null));
const pw = reactive({ current_password: '', password: '', password_confirmation: '' });
const showPw = ref(false);
const quota = computed(() => rt.quota || auth.user?.quota);

const strength = computed(() => {
    const p = pw.password;
    let s = 0;
    if (p.length >= 8) s++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++;
    if (/\d/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return p ? Math.max(1, s) : 0;
});
const strengthLabel = ['', 'Lemah', 'Cukup', 'Kuat', 'Sangat kuat'];

function pickAvatar(e) {
    const f = e.target.files?.[0];
    if (!f) return;
    if (f.size > 2 * 1024 * 1024) return toast.error('Avatar maksimal 2 MB.');
    avatarFile.value = f;
}
async function saveProfile(extra = {}) {
    saving.value = true;
    errors.value = {};
    try {
        const fd = new FormData();
        Object.entries({ ...form, ...extra }).forEach(([k, v]) => fd.append(k, v));
        if (avatarFile.value) fd.append('avatar', avatarFile.value);
        const { data } = await api.post('/profile', fd);
        auth.setUser(data.user);
        avatarFile.value = null;
        toast.success(data.message || 'Profil disimpan.');
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
async function savePassword() {
    saving.value = true;
    errors.value = {};
    try {
        const { data } = await api.post('/profile/password', pw);
        Object.assign(pw, { current_password: '', password: '', password_confirmation: '' });
        toast.success(data.message);
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <PageHeader title="Profil" description="Kelola data diri, keamanan akun, dan pengaturan default saat membuat video." />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[300px_1fr]">
            <aside class="card-brutal h-fit overflow-hidden">
                <div class="animate-gradient bg-brand-gradient h-20"></div>
                <div class="-mt-12 px-5 pb-5 text-center">
                    <div class="mx-auto w-fit rounded-3xl border-4 border-card"><Avatar :user="avatarPreview ? { name: auth.user?.name, avatar_url: avatarPreview } : auth.user" size="xl" /></div>
                    <h2 class="mt-3 font-display text-xl font-bold">{{ auth.user?.name }}</h2>
                    <p class="text-sm text-muted">{{ auth.user?.email }}</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                        <Badge v-for="r in auth.roles" :key="r" color="violet" icon="shield-check">{{ roleLabel(r) }}</Badge>
                        <Badge v-if="auth.user?.quota_plan" color="yellow" icon="gem">{{ auth.user.quota_plan.name }}</Badge>
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-2 text-left">
                        <div class="rounded-2xl bg-surface-2 p-3"><dt class="text-[11px] font-bold text-muted">Kuota/hari</dt><dd class="font-display text-lg font-bold">{{ quota?.limit ?? '—' }}</dd></div>
                        <div class="rounded-2xl bg-surface-2 p-3"><dt class="text-[11px] font-bold text-muted">Sisa hari ini</dt><dd class="font-display text-lg font-bold">{{ quota?.remaining ?? '—' }}</dd></div>
                    </dl>
                    <p v-if="auth.user?.created_at" class="mt-3 text-xs text-muted">Bergabung {{ date(auth.user.created_at) }}</p>
                </div>
            </aside>

            <section class="min-w-0">
                <Segmented v-model="tab" :options="[{ value: 'profile', label: 'Data diri', icon: 'user' }, { value: 'security', label: 'Keamanan', icon: 'lock-keyhole' }, { value: 'prefs', label: 'Preferensi', icon: 'sliders-horizontal' }]" class="mb-5" />

                <form v-if="tab === 'profile'" class="card space-y-5 p-6" @submit.prevent="saveProfile()">
                    <div class="flex flex-wrap items-center gap-4">
                        <Avatar :user="avatarPreview ? { name: auth.user?.name, avatar_url: avatarPreview } : auth.user" size="lg" />
                        <div class="flex flex-wrap gap-2">
                            <label class="btn btn-secondary btn-sm cursor-pointer"><Icon name="image-up" class="h-4 w-4" /> Ganti foto<input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickAvatar" /></label>
                            <button v-if="auth.user?.avatar && !avatarFile" type="button" class="btn btn-soft-danger btn-sm" @click="saveProfile({ remove_avatar: 1 })">Hapus foto</button>
                            <button v-if="avatarFile" type="button" class="btn btn-ghost btn-sm" @click="avatarFile = null">Batal</button>
                        </div>
                        <p class="w-full text-xs text-muted">JPG, PNG, atau WEBP, maksimal 2 MB.</p>
                        <p v-if="errors.avatar" class="field-error w-full">{{ errors.avatar }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="p-name">Nama</label>
                            <input id="p-name" v-model="form.name" class="input" :class="{ 'input-error': errors.name }" required />
                            <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
                        </div>
                        <div>
                            <label class="label" for="p-email">Email</label>
                            <input id="p-email" v-model="form.email" type="email" class="input" :class="{ 'input-error': errors.email }" required />
                            <p v-if="errors.email" class="field-error">{{ errors.email }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="saving"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan perubahan</button></div>
                </form>

                <form v-else-if="tab === 'security'" class="card space-y-5 p-6" @submit.prevent="savePassword">
                    <div>
                        <label class="label" for="pw-current">Password saat ini</label>
                        <input id="pw-current" v-model="pw.current_password" :type="showPw ? 'text' : 'password'" class="input" autocomplete="current-password" :class="{ 'input-error': errors.current_password }" required />
                        <p v-if="errors.current_password" class="field-error">{{ errors.current_password }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="pw-new">Password baru</label>
                            <input id="pw-new" v-model="pw.password" :type="showPw ? 'text' : 'password'" class="input" autocomplete="new-password" :class="{ 'input-error': errors.password }" required />
                            <div v-if="pw.password" class="mt-2 flex items-center gap-2">
                                <div class="flex flex-1 gap-1"><span v-for="i in 4" :key="i" :class="i <= strength ? ['bg-rose-500', 'bg-amber-400', 'bg-emerald-400', 'bg-emerald-600'][strength - 1] : 'bg-surface-2'" class="h-1.5 flex-1 rounded-full transition"></span></div>
                                <span class="text-xs font-bold text-muted">{{ strengthLabel[strength] }}</span>
                            </div>
                            <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
                        </div>
                        <div>
                            <label class="label" for="pw-confirm">Ulangi password baru</label>
                            <input id="pw-confirm" v-model="pw.password_confirmation" :type="showPw ? 'text' : 'password'" class="input" autocomplete="new-password" required />
                            <p v-if="pw.password_confirmation && pw.password !== pw.password_confirmation" class="field-error">Password belum sama.</p>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold"><input v-model="showPw" type="checkbox" class="checkbox" /> Tampilkan password</label>
                    <p class="rounded-2xl bg-surface-2 p-3 text-xs text-muted"><Icon name="shield-check" class="mr-1 inline h-3.5 w-3.5" /> Setelah password diganti, sesi di perangkat lain otomatis keluar.</p>
                    <div class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="saving || !pw.password || pw.password !== pw.password_confirmation"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="key-round" class="h-4 w-4" /> Ganti password</button></div>
                </form>

                <div v-else class="card space-y-6 p-6">
                    <div>
                        <p class="label">Durasi default</p>
                        <div class="flex gap-2"><button v-for="d in DURATIONS" :key="d" type="button" :class="form.default_durasi === d ? 'chip-active' : ''" class="chip" @click="form.default_durasi = d">{{ d }} detik</button></div>
                    </div>
                    <div>
                        <p class="label">Gaya default</p>
                        <div class="flex flex-wrap gap-2"><button v-for="m in MODELS" :key="m.value" type="button" :class="form.default_model === m.value ? 'chip-active' : ''" class="chip" @click="form.default_model = m.value"><Icon :name="m.icon" class="h-3.5 w-3.5" /> {{ m.label }}</button></div>
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-surface-2 p-4">
                        <div><p class="text-sm font-bold">Mode gelap</p><p class="text-xs text-muted">Tersimpan di perangkat ini.</p></div>
                        <Toggle :model-value="ui.dark" label="Mode gelap" @update:model-value="ui.setDark($event)" />
                    </div>
                    <div class="flex justify-end"><button type="button" class="btn btn-primary" :disabled="saving" @click="saveProfile()"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan preferensi</button></div>
                </div>
            </section>
        </div>
    </div>
</template>
