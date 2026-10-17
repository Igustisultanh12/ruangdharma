import { onBeforeUnmount, onMounted } from 'vue';
import { useRealtimeStore } from '@/stores/realtime.js';

/**
 * Jalankan `callback` setiap kali resource berubah di server.
 * Contoh: useRealtime(['generations'], () => load({ silent: true }))
 */
export function useRealtime(keys, callback) {
    const rt = useRealtimeStore();
    let off = null;
    let debounce = null;
    const run = () => {
        clearTimeout(debounce);
        debounce = setTimeout(callback, 150);
    };
    onMounted(() => {
        off = rt.subscribe(keys, run);
    });
    onBeforeUnmount(() => {
        clearTimeout(debounce);
        off?.();
    });
    return rt;
}
