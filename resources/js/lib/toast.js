import { reactive } from 'vue';

/* Toast & dialog konfirmasi global (pengganti SweetAlert, mengikuti tema aplikasi). */
export const toastState = reactive({ items: [] });
let seq = 0;

function push(type, message, opts = {}) {
    const id = ++seq;
    toastState.items.push({ id, type, message, title: opts.title || null, action: opts.action || null });
    if (toastState.items.length > 5) toastState.items.shift();
    setTimeout(() => dismissToast(id), opts.duration ?? (type === 'error' ? 6000 : 3800));
    return id;
}
export function dismissToast(id) {
    const i = toastState.items.findIndex((t) => t.id === id);
    if (i >= 0) toastState.items.splice(i, 1);
}
export const toast = {
    success: (m, o) => push('success', m, o),
    error: (m, o) => push('error', m, o),
    info: (m, o) => push('info', m, o),
    warning: (m, o) => push('warning', m, o),
};

export const confirmState = reactive({ open: false, opts: {}, resolve: null, value: '' });

/**
 * await confirmDialog({ title, message, confirmText, tone: 'danger'|'primary', input: { label, placeholder, required, multiline } })
 * → false bila batal; true (atau teks input) bila dikonfirmasi.
 */
export function confirmDialog(opts = {}) {
    return new Promise((resolve) => {
        if (confirmState.resolve) confirmState.resolve(false);
        confirmState.opts = { confirmText: 'Ya, lanjutkan', cancelText: 'Batal', tone: 'primary', ...opts };
        confirmState.value = opts.input?.value || '';
        confirmState.resolve = resolve;
        confirmState.open = true;
    });
}
export function closeConfirm(result) {
    const r = confirmState.resolve;
    confirmState.open = false;
    confirmState.resolve = null;
    r?.(result);
}

export async function copyText(text, label = 'Teks') {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text || '');
        } else {
            const ta = document.createElement('textarea');
            ta.value = text || '';
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        toast.success(`${label} disalin ke clipboard.`);
        return true;
    } catch {
        toast.error(`Gagal menyalin ${label.toLowerCase()}.`);
        return false;
    }
}
