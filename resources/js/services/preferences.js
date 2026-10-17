// Penyimpanan lokal (localStorage) untuk fitur yang endpoint backend-nya belum tersedia:
// favorit/arsip, default generate, auto-generate, template notifikasi, keamanan, dll.
// Pola: coba API dulu di view, fallback ke localStorage ini.
function read(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch {
    return fallback;
  }
}
function write(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}
export const localGet = read;
export const localSet = write;

// --- Default Generate (Profil) ---
export function getGenerateDefaults() {
  return read('rd_generate_defaults', { durasi: 60, model: 'narasi' });
}
export function saveGenerateDefaults(d) {
  write('rd_generate_defaults', { durasi: Number(d.durasi) || 60, model: d.model || 'narasi' });
}

// --- Favorit & Arsip (Riwayat) ---
export function getFavorites() { return read('rd_favorites', []); }
export function isFavorite(id) { return getFavorites().includes(Number(id)); }
export function toggleFavorite(id) {
  const list = getFavorites();
  const n = Number(id);
  const next = list.includes(n) ? list.filter((x) => x !== n) : [...list, n];
  write('rd_favorites', next);
  return next.includes(n);
}
export function getArchived() { return read('rd_archived', []); }
export function isArchived(id) { return getArchived().includes(Number(id)); }
export function toggleArchived(id) {
  const list = getArchived();
  const n = Number(id);
  const next = list.includes(n) ? list.filter((x) => x !== n) : [...list, n];
  write('rd_archived', next);
  return next.includes(n);
}

// --- Auto Generate Harian (Admin Settings) ---
export function getAutoGenerate() {
  return read('rd_auto_generate', { enabled: false, themes: ['', '', ''], time: '06:00' });
}
export function saveAutoGenerate(cfg) {
  write('rd_auto_generate', {
    enabled: !!cfg.enabled,
    themes: [0, 1, 2].map((i) => (cfg.themes?.[i] || '').trim()),
    time: cfg.time || '06:00',
  });
}

// --- Template Notifikasi & Keamanan (Admin Settings) ---
export function getNotificationTemplates() {
  return read('rd_notification_templates', {
    generate_success: 'Video "{keyword}" berhasil dibuat.',
    quota_warning: 'Kuota harian tersisa {remaining} video.',
    review_needed: 'Naskah "{keyword}" menunggu review.',
  });
}
export function saveNotificationTemplates(t) { write('rd_notification_templates', t); }
export function getSecuritySettings() {
  return read('rd_security_settings', { password_min: 6, session_timeout: 120 });
}
export function saveSecuritySettings(s) {
  write('rd_security_settings', { password_min: Number(s.password_min) || 6, session_timeout: Number(s.session_timeout) || 120 });
}

// --- Pengaturan Umum (Admin Settings) ---
export function getAppSettings() {
  return read('rd_app_settings', { app_name: 'Ruang Dharma Studio', description: '', logo: '' });
}
export function saveAppSettings(s) { write('rd_app_settings', s); }

// --- Versi & Komentar (Detail Riwayat) ---
export function getVersions(generationId) {
  return read(`rd_versions_${generationId}`, []);
}
export function saveVersions(generationId, versions) {
  write(`rd_versions_${generationId}`, versions);
}
export function getComments(generationId) {
  return read(`rd_comments_${generationId}`, []);
}
export function saveComments(generationId, comments) {
  write(`rd_comments_${generationId}`, comments);
}

// --- Review Queue (Admin) ---
export function getReviewOverrides() { return read('rd_review_overrides', {}); }
export function saveReviewOverrides(o) { write('rd_review_overrides', o); }

// --- Paket Kuota (Admin) ---
export function getQuotaPlans() {
  const seeded = [
    { id: 1, name: 'Free', price: 0, daily_limit: 5, features: 'Generate harian terbatas', active: true },
    { id: 2, name: 'Pro', price: 49000, daily_limit: 20, features: 'Generate lebih banyak + prioritas', active: true },
    { id: 3, name: 'Unlimited', price: 99000, daily_limit: 100, features: 'Kuota besar + semua fitur', active: true },
  ];
  const stored = read('rd_quota_plans', null);
  return stored || seeded;
}
export function saveQuotaPlans(plans) { write('rd_quota_plans', plans); }
export function getUserPlans() { return read('rd_user_plans', {}); }
export function saveUserPlans(m) { write('rd_user_plans', m); }

// --- API Keys (Admin) ---
export function getApiKeys() { return read('rd_api_keys', []); }
export function saveApiKeys(keys) { write('rd_api_keys', keys); }

// --- Webhooks (Admin) ---
export function getWebhooks() { return read('rd_webhooks', []); }
export function saveWebhooks(hooks) { write('rd_webhooks', hooks); }
export function getWebhookLogs() { return read('rd_webhook_logs', []); }
export function addWebhookLog(entry) {
  const logs = getWebhookLogs();
  logs.unshift({ id: Date.now(), created_at: new Date().toISOString(), ...entry });
  write('rd_webhook_logs', logs.slice(0, 200));
}

// --- Media Library ---
export function getMediaItems() { return read('rd_media', []); }
export function saveMediaItems(items) { write('rd_media', items); }

// --- Kalender Konten ---
export function getScheduledItems() { return read('rd_scheduled', []); }
export function saveScheduledItems(items) { write('rd_scheduled', items); }

// --- Role & Permission (Admin) ---
export const ALL_PERMISSIONS = [
  'generate.create', 'generate.view', 'history.view', 'templates.manage',
  'media.manage', 'calendar.manage', 'users.manage', 'roles.manage',
  'review.manage', 'analytics.view', 'settings.manage', 'muse.manage',
  'api_keys.manage', 'webhooks.manage', 'quota.manage', 'logs.view',
];
export function getRoles() {
  const seeded = [
    { id: 1, name: 'admin', label: 'Admin', permissions: [...ALL_PERMISSIONS] },
    { id: 2, name: 'editor', label: 'Editor', permissions: ['generate.create', 'generate.view', 'history.view', 'templates.manage', 'media.manage', 'calendar.manage', 'review.manage'] },
    { id: 3, name: 'pengguna', label: 'Pengguna', permissions: ['generate.create', 'generate.view', 'history.view'] },
  ];
  return read('rd_roles', null) || seeded;
}
export function saveRoles(roles) { write('rd_roles', roles); }
export function getUserRoleOverrides() { return read('rd_user_roles', {}); }
export function saveUserRoleOverrides(m) { write('rd_user_roles', m); }
