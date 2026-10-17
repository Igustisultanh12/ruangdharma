import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/index.js';
import { installDirectives } from './lib/directives.js';
import { setUnauthorizedHandler } from './lib/api.js';
import { toast } from './lib/toast.js';
import { useAuthStore } from './stores/auth.js';
import { useRealtimeStore } from './stores/realtime.js';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);
installDirectives(app);

// Token kedaluwarsa / akun dinonaktifkan → kembali ke halaman masuk.
let handling = false;
setUnauthorizedHandler(() => {
    const auth = useAuthStore(pinia);
    if (handling || !auth.isLoggedIn) return;
    handling = true;
    useRealtimeStore(pinia).stop();
    auth.clear();
    toast.warning('Sesi berakhir. Silakan masuk kembali.');
    router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } }).finally(() => (handling = false));
});

app.config.errorHandler = (err) => {
    console.error(err);
    toast.error('Terjadi kesalahan tampilan. Muat ulang halaman bila berlanjut.');
};

app.mount('#app');
