// Log aktivitas & riwayat login (localStorage).
// Backend belum menyediakan endpoint log, jadi frontend mencatat aktivitas
// dari aksi nyata pengguna; view Admin mencoba endpoint API dulu, lalu fallback ke sini.
import { localGet, localSet } from './preferences.js';

export const ACTIVITY_ACTIONS = ['login', 'logout', 'generate', 'update', 'delete', 'approve', 'reject', 'publish', 'settings', 'token', 'review', 'create'];

export function logActivity({ action, description, user = null, ip = '-' }) {
  const logs = localGet('rd_activity_logs', []);
  logs.unshift({
    id: Date.now() + Math.floor(Math.random() * 1000),
    created_at: new Date().toISOString(),
    user_name: user?.name || currentUserName(),
    user_email: user?.email || currentUserEmail(),
    action,
    description,
    ip,
  });
  localSet('rd_activity_logs', logs.slice(0, 300));
  return logs[0];
}

export function getActivities() {
  return localGet('rd_activity_logs', []);
}

function currentUserName() {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')?.name || 'Tamu';
  } catch {
    return 'Tamu';
  }
}
function currentUserEmail() {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')?.email || '-';
  } catch {
    return '-';
  }
}

export function recordLogin({ email, success, device = null }) {
  const logs = localGet('rd_login_history', []);
  logs.unshift({
    id: Date.now(),
    created_at: new Date().toISOString(),
    email,
    user_name: success ? currentUserName() : email,
    ip: '-',
    device: device || navigator.userAgent.slice(0, 80),
    success,
  });
  localSet('rd_login_history', logs.slice(0, 300));
}

export function getLoginHistory() {
  return localGet('rd_login_history', []);
}
