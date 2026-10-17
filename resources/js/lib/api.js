import axios from 'axios';

/** Instance axios ke Laravel API. Token Sanctum dikirim sebagai Bearer. */
const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

export const TOKEN_KEY = 'rd_token';
export const USER_KEY = 'rd_user';

api.interceptors.request.use((cfg) => {
    const t = localStorage.getItem(TOKEN_KEY);
    if (t) cfg.headers.Authorization = `Bearer ${t}`;
    return cfg;
});

let onUnauthorized = null;
export function setUnauthorizedHandler(fn) {
    onUnauthorized = fn;
}

api.interceptors.response.use(
    (r) => r,
    (err) => {
        if (err.response?.status === 401 && !err.config?.url?.endsWith('/login')) {
            onUnauthorized?.();
        }
        return Promise.reject(err);
    },
);

/** Ambil pesan error yang ramah dari respons Laravel (validasi 422, 403, 429, dsb). */
export function errorMessage(e, fallback = 'Terjadi kesalahan. Coba lagi.') {
    const d = e?.response?.data;
    if (d?.errors) {
        const first = Object.values(d.errors)[0];
        if (Array.isArray(first) && first[0]) return first[0];
    }
    if (d?.message) return d.message;
    if (e?.code === 'ERR_NETWORK') return 'Tidak bisa terhubung ke server. Periksa koneksi internet.';
    return fallback;
}

/** Error per field untuk form (422). */
export function fieldErrors(e) {
    const errs = e?.response?.data?.errors || {};
    return Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
}

export default api;
