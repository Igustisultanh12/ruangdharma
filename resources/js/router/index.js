import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import Dashboard from '../views/Dashboard.vue';
import Generate from '../views/Generate.vue';
import History from '../views/History.vue';
import Profile from '../views/Profile.vue';
import TemplateLibrary from '../views/TemplateLibrary.vue';
import MediaLibrary from '../views/MediaLibrary.vue';
import ContentCalendar from '../views/ContentCalendar.vue';
import AdminDashboard from '../views/Admin/Dashboard.vue';
import AdminUsers from '../views/Admin/Users.vue';
import AdminHistory from '../views/Admin/History.vue';
import AdminSettings from '../views/Admin/Settings.vue';
import AdminMuseStatus from '../views/Admin/MuseStatus.vue';
import AdminMuseConfig from '../views/Admin/MuseConfig.vue';
import AdminActivityLogs from '../views/Admin/ActivityLogs.vue';
import AdminLoginHistory from '../views/Admin/LoginHistory.vue';
import AdminRolePermissions from '../views/Admin/RolePermissions.vue';
import AdminReviewQueue from '../views/Admin/ReviewQueue.vue';
import AdminAnalytics from '../views/Admin/Analytics.vue';
import AdminQuotaPlans from '../views/Admin/QuotaPlans.vue';
import AdminApiKeys from '../views/Admin/ApiKeys.vue';
import AdminWebhooks from '../views/Admin/Webhooks.vue';

const crumb = (label, to) => ({ label, to });
const dash = { label: 'Dashboard', to: '/' };
const admin = { label: 'Admin', to: '/admin' };

const routes = [
  { path: '/', name: 'dashboard', component: Dashboard, meta: { auth: true, title: 'Dashboard', breadcrumb: [crumb('Dashboard')] } },
  { path: '/login', name: 'login', component: Login, meta: { title: 'Masuk' } },
  { path: '/generate', name: 'generate', component: Generate, meta: { auth: true, title: 'Generate Video', breadcrumb: [dash, crumb('Studio'), crumb('Generate Video')] } },
  { path: '/templates', name: 'templates', component: TemplateLibrary, meta: { auth: true, title: 'Template & Ide', breadcrumb: [dash, crumb('Studio'), crumb('Template & Ide')] } },
  { path: '/media', name: 'media', component: MediaLibrary, meta: { auth: true, title: 'Media Library', breadcrumb: [dash, crumb('Studio'), crumb('Media Library')] } },
  { path: '/calendar', name: 'calendar', component: ContentCalendar, meta: { auth: true, title: 'Kalender Konten', breadcrumb: [dash, crumb('Studio'), crumb('Kalender Konten')] } },
  { path: '/history', name: 'history', component: History, meta: { auth: true, title: 'Riwayat', breadcrumb: [dash, crumb('Riwayat'), crumb('Semua Riwayat')] } },
  { path: '/profile', name: 'profile', component: Profile, meta: { auth: true, title: 'Profil', breadcrumb: [dash, crumb('Profil')] } },
  { path: '/admin', name: 'admin.dashboard', component: AdminDashboard, meta: { auth: true, admin: true, title: 'Dashboard Admin', breadcrumb: [admin, crumb('Dashboard')] } },
  { path: '/admin/users', name: 'admin.users', component: AdminUsers, meta: { auth: true, admin: true, title: 'Pengguna', breadcrumb: [admin, crumb('Pengguna')] } },
  { path: '/admin/roles', name: 'admin.roles', component: AdminRolePermissions, meta: { auth: true, admin: true, title: 'Role & Permission', breadcrumb: [admin, crumb('Role & Permission')] } },
  { path: '/admin/review', name: 'admin.review', component: AdminReviewQueue, meta: { auth: true, admin: true, title: 'Review Naskah', breadcrumb: [admin, crumb('Review Naskah')] } },
  { path: '/admin/analytics', name: 'admin.analytics', component: AdminAnalytics, meta: { auth: true, admin: true, title: 'Analitik', breadcrumb: [admin, crumb('Analitik')] } },
  { path: '/admin/activity-logs', name: 'admin.activity-logs', component: AdminActivityLogs, meta: { auth: true, admin: true, title: 'Log Aktivitas', breadcrumb: [admin, crumb('Log Aktivitas')] } },
  { path: '/admin/login-history', name: 'admin.login-history', component: AdminLoginHistory, meta: { auth: true, admin: true, title: 'Riwayat Login', breadcrumb: [admin, crumb('Riwayat Login')] } },
  { path: '/admin/history', name: 'admin.history', component: AdminHistory, meta: { auth: true, admin: true, title: 'Riwayat Semua', breadcrumb: [admin, crumb('Riwayat Semua')] } },
  { path: '/admin/settings', name: 'admin.settings', component: AdminSettings, meta: { auth: true, admin: true, title: 'Pengaturan', breadcrumb: [admin, crumb('Pengaturan')] } },
  { path: '/admin/muse', name: 'admin.muse', component: AdminMuseStatus, meta: { auth: true, admin: true, title: 'Status Muse', breadcrumb: [admin, crumb('Status Muse')] } },
  { path: '/admin/muse-config', name: 'admin.muse-config', component: AdminMuseConfig, meta: { auth: true, admin: true, title: 'Integrasi Muse', breadcrumb: [admin, crumb('Integrasi Muse')] } },
  { path: '/admin/api-keys', name: 'admin.api-keys', component: AdminApiKeys, meta: { auth: true, admin: true, title: 'API Keys', breadcrumb: [admin, crumb('API Keys')] } },
  { path: '/admin/webhooks', name: 'admin.webhooks', component: AdminWebhooks, meta: { auth: true, admin: true, title: 'Webhooks', breadcrumb: [admin, crumb('Webhooks')] } },
  { path: '/admin/quota-plans', name: 'admin.quota-plans', component: AdminQuotaPlans, meta: { auth: true, admin: true, title: 'Paket Kuota', breadcrumb: [admin, crumb('Paket Kuota')] } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({ history: createWebHistory(), routes });

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  let user = null;
  try {
    user = JSON.parse(localStorage.getItem('user') || 'null');
  } catch {
    user = null;
  }
  if (to.meta.auth && !token) return next({ name: 'login', query: { redirect: to.fullPath } });
  // Guard admin: backend baru mengenal role admin/pengguna. Role 'editor'
  // (modul Role & Permission) masih dikelola frontend sampai backend mendukungnya.
  if (to.meta.admin && user?.role !== 'admin') return next({ name: 'dashboard' });
  if (to.name === 'login' && token) return next({ name: 'dashboard' });
  next();
});

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} — Ruang Dharma Studio` : 'Ruang Dharma Studio';
});

export default router;
