import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import { ADMIN, REVIEWERS } from '@/lib/menu.js';

const home = { label: 'Beranda', to: '/' };
const adm = { label: 'Admin', to: '/admin' };
const page = (path, name, component, title, extra = {}) => ({ path, name, component, meta: { auth: true, title, breadcrumb: [extra.parent || home, { label: title }], ...extra.meta } });
const admin = (path, name, component, title) => page(path, name, component, title, { parent: adm, meta: { roles: ADMIN } });

const routes = [
    { path: '/login', name: 'login', component: () => import('@/views/Login.vue'), meta: { guest: true, title: 'Masuk' } },
    { path: '/', name: 'dashboard', component: () => import('@/views/Dashboard.vue'), meta: { auth: true, title: 'Beranda', breadcrumb: [{ label: 'Beranda' }] } },
    page('/generate', 'generate', () => import('@/views/Generate.vue'), 'Buat video'),
    page('/templates', 'templates', () => import('@/views/TemplateLibrary.vue'), 'Template & ide'),
    page('/history', 'history', () => import('@/views/History.vue'), 'Riwayat saya'),
    page('/calendar', 'calendar', () => import('@/views/ContentCalendar.vue'), 'Kalender konten'),
    page('/media', 'media', () => import('@/views/MediaLibrary.vue'), 'Media'),
    page('/notifications', 'notifications', () => import('@/views/Notifications.vue'), 'Notifikasi'),
    page('/profile', 'profile', () => import('@/views/Profile.vue'), 'Profil'),
    page('/social', 'social', () => import('@/views/Social.vue'), 'Medsos'),
    page('/admin/review', 'admin.review', () => import('@/views/Admin/ReviewQueue.vue'), 'Antrean review', { parent: adm, meta: { roles: REVIEWERS } }),
    { ...admin('/admin', 'admin.dashboard', () => import('@/views/Admin/Dashboard.vue'), 'Ringkasan admin'), meta: { auth: true, roles: ADMIN, title: 'Ringkasan admin', breadcrumb: [adm, { label: 'Ringkasan' }] } },
    admin('/admin/analytics', 'admin.analytics', () => import('@/views/Admin/Analytics.vue'), 'Analitik'),
    admin('/admin/history', 'admin.history', () => import('@/views/Admin/History.vue'), 'Semua video'),
    admin('/admin/users', 'admin.users', () => import('@/views/Admin/Users.vue'), 'Pengguna'),
    admin('/admin/roles', 'admin.roles', () => import('@/views/Admin/RolePermissions.vue'), 'Role & izin'),
    admin('/admin/quota-plans', 'admin.quota-plans', () => import('@/views/Admin/QuotaPlans.vue'), 'Paket kuota'),
    admin('/admin/settings', 'admin.settings', () => import('@/views/Admin/Settings.vue'), 'Pengaturan'),
    admin('/admin/muse-config', 'admin.muse-config', () => import('@/views/Admin/MuseConfig.vue'), 'Integrasi Muse'),
    { path: '/admin/muse', redirect: '/admin/muse-config' },
    admin('/admin/api-keys', 'admin.api-keys', () => import('@/views/Admin/ApiKeys.vue'), 'API key'),
    admin('/admin/webhooks', 'admin.webhooks', () => import('@/views/Admin/Webhooks.vue'), 'Webhook'),
    admin('/admin/activity-logs', 'admin.activity-logs', () => import('@/views/Admin/ActivityLogs.vue'), 'Log aktivitas'),
    admin('/admin/login-history', 'admin.login-history', () => import('@/views/Admin/LoginHistory.vue'), 'Riwayat login'),
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: (to, from, saved) => saved || { top: 0 },
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    if (to.meta.auth && !auth.isLoggedIn) return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    if (to.name === 'login' && auth.isLoggedIn) return { name: 'dashboard' };
    if (to.meta.roles && !auth.hasAnyRole(to.meta.roles)) return { name: 'dashboard' };
    return true;
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} — Ruang Dharma Studio` : 'Ruang Dharma Studio';
});

// Setelah deploy baru, chunk lama bisa hilang → muat ulang halaman sekali.
router.onError((err, to) => {
    if (/Failed to fetch dynamically imported module|Importing a module script failed/i.test(err?.message || '')) {
        window.location.assign(to.fullPath);
    }
});

export default router;
