import { reactive, ref, watch } from 'vue';
import api, { errorMessage } from '@/lib/api.js';
import { useRealtime } from './useRealtime.js';

/**
 * Daftar berpaginasi + filter + auto-refresh realtime.
 * const list = useList('/generations', { filters: { search: '' }, realtime: ['generations'] })
 */
export function useList(url, { filters = {}, perPage = 15, realtime = [], transform = null, immediate = true } = {}) {
    const items = ref([]);
    const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
    const loading = ref(false);
    const loaded = ref(false);
    const error = ref('');
    const extra = ref({});
    const state = reactive({ ...filters });
    let reqId = 0;

    async function load({ page = meta.page, silent = false } = {}) {
        const id = ++reqId;
        if (!silent) loading.value = true;
        error.value = '';
        try {
            const params = { page, per_page: perPage };
            Object.entries(state).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined && v !== false) params[k] = v === true ? 1 : v;
            });
            const { data } = await api.get(typeof url === 'function' ? url() : url, { params });
            if (id !== reqId) return;
            const p = data.data;
            const rows = Array.isArray(p) ? p : p?.data || [];
            items.value = transform ? rows.map(transform) : rows;
            meta.page = p?.current_page || 1;
            meta.lastPage = p?.last_page || 1;
            meta.total = p?.total ?? rows.length;
            meta.from = p?.from || (rows.length ? 1 : 0);
            meta.to = p?.to || rows.length;
            const { data: _d, ...rest } = data;
            extra.value = rest;
            if (meta.page > meta.lastPage && meta.lastPage >= 1) return load({ page: meta.lastPage, silent });
        } catch (e) {
            if (id === reqId) error.value = errorMessage(e, 'Gagal memuat data.');
        } finally {
            if (id === reqId) {
                loading.value = false;
                loaded.value = true;
            }
        }
    }

    let t = null;
    watch(
        () => ({ ...state }),
        () => {
            clearTimeout(t);
            t = setTimeout(() => load({ page: 1 }), 300);
        },
        { deep: true },
    );

    if (realtime.length) useRealtime(realtime, () => load({ silent: true }));
    if (immediate) load({ page: 1 });

    return { items, meta, loading, loaded, error, filters: state, extra, load, go: (page) => load({ page }) };
}
