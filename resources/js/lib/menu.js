/* Struktur menu sidebar. `roles` kosong = semua pengguna yang login. `badge` diisi dari store realtime. */
export const ADMIN = ['admin', 'super_admin'];
export const REVIEWERS = ['admin', 'super_admin', 'editor'];

export const menu = [
    {
        title: 'Studio',
        items: [
            { label: 'Beranda', to: '/', icon: 'house', name: 'dashboard' },
            { label: 'Buat video', to: '/generate', icon: 'wand-sparkles', name: 'generate' },
            { label: 'Template & ide', to: '/templates', icon: 'lightbulb', name: 'templates' },
            { label: 'Riwayat saya', to: '/history', icon: 'clapperboard', name: 'history' },
            { label: 'Kalender konten', to: '/calendar', icon: 'calendar-days', name: 'calendar' },
            { label: 'Media', to: '/media', icon: 'images', name: 'media' },
            { label: 'Notifikasi', to: '/notifications', icon: 'bell', name: 'notifications', badge: 'unread' },
            { label: 'Medsos', to: '/social', icon: 'share-2', name: 'social' },
            { label: 'YouTube', to: '/youtube', icon: 'play', name: 'youtube' },
        ],
    },
    {
        title: 'Review',
        roles: REVIEWERS,
        items: [{ label: 'Antrean review', to: '/admin/review', icon: 'list-checks', name: 'admin.review', badge: 'review' }],
    },
    {
        title: 'Admin',
        roles: ADMIN,
        items: [
            { label: 'Ringkasan admin', to: '/admin', icon: 'layout-dashboard', name: 'admin.dashboard' },
            { label: 'Analitik', to: '/admin/analytics', icon: 'chart-column', name: 'admin.analytics' },
            { label: 'Semua video', to: '/admin/history', icon: 'library', name: 'admin.history' },
            { label: 'Pengguna', to: '/admin/users', icon: 'users', name: 'admin.users' },
            { label: 'Role & izin', to: '/admin/roles', icon: 'shield-check', name: 'admin.roles' },
            { label: 'Paket kuota', to: '/admin/quota-plans', icon: 'gem', name: 'admin.quota-plans' },
        ],
    },
    {
        title: 'Sistem',
        roles: ADMIN,
        items: [
            { label: 'Pengaturan', to: '/admin/settings', icon: 'settings', name: 'admin.settings' },
            { label: 'Integrasi Muse', to: '/admin/muse-config', icon: 'plug-zap', name: 'admin.muse-config' },
            { label: 'API key', to: '/admin/api-keys', icon: 'key-round', name: 'admin.api-keys' },
            { label: 'Webhook', to: '/admin/webhooks', icon: 'webhook', name: 'admin.webhooks' },
            { label: 'Log aktivitas', to: '/admin/activity-logs', icon: 'scroll-text', name: 'admin.activity-logs' },
            { label: 'Riwayat login', to: '/admin/login-history', icon: 'log-in', name: 'admin.login-history' },
        ],
    },
];

export function visibleMenu(hasAnyRole) {
    return menu.filter((g) => !g.roles || hasAnyRole(g.roles)).map((g) => ({ ...g, items: g.items.filter((i) => !i.roles || hasAnyRole(i.roles)) }));
}
