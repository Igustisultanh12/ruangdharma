<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const form = reactive({ email: '', password: '' });
const errors = ref({});
const message = ref('');
const show = ref(false);
const loading = ref(false);
const info = ref({});
onMounted(async () => {
    try {
        info.value = (await api.get('/app-info')).data.data || {};
    } catch {
        /* opsional */
    }
});

async function submit() {
    loading.value = true;
    errors.value = {};
    message.value = '';
    try {
        const data = await auth.login(form.email.trim(), form.password);
        toast.success(`Om Swastyastu, ${data.user.name.split(' ')[0]}!`);
        const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/';
        router.replace(redirect);
    } catch (e) {
        errors.value = fieldErrors(e);
        message.value = errors.value.email || errors.value.password ? '' : errorMessage(e, 'Gagal masuk. Coba lagi.');
        form.password = '';
    } finally {
        loading.value = false;
    }
}
function fillDemo(type) {
    form.email = type === 'admin' ? 'admin@ruangdharma.id' : 'user@ruangdharma.id';
    form.password = type === 'admin' ? 'admin123' : 'user123';
}
</script>

<template>
    <div class="animate-fade-up">
        <span class="eyebrow"><Icon name="sparkles" class="h-3.5 w-3.5" /> Studio internal</span>
        <h1 class="mt-5 font-display text-4xl font-bold">Om Swastyastu 🙏</h1>
        <p class="mt-2 text-muted">Masuk untuk lanjut membuat video cerita dharma.</p>

        <div v-if="message" class="mt-6 flex items-start gap-3 rounded-2xl border-2 border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300" role="alert">
            <Icon name="circle-alert" class="mt-0.5 h-4 w-4" />
            <span>{{ message }}</span>
        </div>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
            <div>
                <label class="label" for="email">Email</label>
                <div class="relative">
                    <Icon name="mail" class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted" />
                    <input id="email" v-model="form.email" type="email" class="input pl-11" :class="{ 'input-error': errors.email }" autocomplete="username" placeholder="nama@ruangdharma.id" required autofocus />
                </div>
                <p v-if="errors.email" class="field-error">{{ errors.email }}</p>
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <div class="relative">
                    <Icon name="lock-keyhole" class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-muted" />
                    <input id="password" v-model="form.password" :type="show ? 'text' : 'password'" class="input pr-12 pl-11" :class="{ 'input-error': errors.password }" autocomplete="current-password" required />
                    <button type="button" class="absolute top-1/2 right-3 -translate-y-1/2 rounded-lg p-1.5 text-muted hover:text-ink" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'" @click="show = !show">
                        <Icon :name="show ? 'eye-off' : 'eye'" class="h-4 w-4" />
                    </button>
                </div>
                <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
            </div>
            <button v-ripple type="submit" class="btn btn-primary btn-lg shine w-full" :disabled="loading || !form.email || !form.password">
                <Spinner v-if="loading" class="h-5 w-5" />
                <Icon v-else name="log-in" class="h-5 w-5" />
                {{ loading ? 'Memeriksa…' : 'Masuk' }}
            </button>
        </form>

        <div v-if="info.show_demo_accounts" class="mt-8 rounded-2xl border-2 border-dashed border-line p-4">
            <p class="text-xs font-bold text-muted">Akun demo — hanya tampil di mode lokal</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" class="chip" @click="fillDemo('admin')"><Icon name="crown" class="h-3.5 w-3.5" /> Admin</button>
                <button type="button" class="chip" @click="fillDemo('user')"><Icon name="user" class="h-3.5 w-3.5" /> Pengguna</button>
            </div>
        </div>
    </div>
</template>
