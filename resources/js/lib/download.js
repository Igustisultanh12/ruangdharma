import api, { errorMessage } from './api.js';
import { toast } from './toast.js';

export function saveBlob(blob, filename) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1500);
}

export function saveText(text, filename) {
    saveBlob(new Blob([text], { type: 'text/plain;charset=utf-8' }), filename);
}

function filenameFrom(res, fallback) {
    const cd = res.headers?.['content-disposition'] || '';
    const m = cd.match(/filename="?([^";]+)"?/i);
    return m ? m[1] : fallback;
}

/** Unduh file dari endpoint yang butuh token Bearer (window.open tidak membawa token). */
export async function downloadFromApi(url, fallbackName, params = {}) {
    try {
        const res = await api.get(url, { params, responseType: 'blob' });
        saveBlob(res.data, filenameFrom(res, fallbackName));
        return true;
    } catch (e) {
        toast.error(errorMessage(e, 'Gagal mengunduh file.'));
        return false;
    }
}

/** Buka HTML (mis. laporan siap cetak) dari API di tab baru. */
export async function openHtmlFromApi(url, params = {}) {
    const win = window.open('', '_blank');
    try {
        const res = await api.get(url, { params, responseType: 'blob' });
        const blobUrl = URL.createObjectURL(new Blob([res.data], { type: 'text/html' }));
        if (win) win.location.href = blobUrl;
        else window.location.href = blobUrl;
    } catch (e) {
        win?.close();
        toast.error(errorMessage(e, 'Gagal membuka laporan.'));
    }
}
