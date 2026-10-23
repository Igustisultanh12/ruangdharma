const locale = 'id-ID';

export function toDate(v) {
    if (!v) return null;
    const d = v instanceof Date ? v : new Date(v);
    return Number.isNaN(d.getTime()) ? null : d;
}

export function date(v, opts = { day: 'numeric', month: 'short', year: 'numeric' }) {
    const d = toDate(v);
    return d ? d.toLocaleDateString(locale, opts) : '—';
}

export function dateTime(v) {
    const d = toDate(v);
    return d ? d.toLocaleString(locale, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
}

export function time(v) {
    const d = toDate(v);
    return d ? d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }) : '—';
}

export function dateLong(v = new Date()) {
    const d = toDate(v);
    return d ? d.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '—';
}

export function relative(v) {
    const d = toDate(v);
    if (!d) return '—';
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 45) return 'baru saja';
    if (diff < 3600) return `${Math.round(diff / 60)} menit lalu`;
    if (diff < 86400) return `${Math.round(diff / 3600)} jam lalu`;
    if (diff < 86400 * 7) return `${Math.round(diff / 86400)} hari lalu`;
    return date(d);
}

export function number(n) {
    return new Intl.NumberFormat(locale).format(Number(n) || 0);
}

export function rupiah(n) {
    return new Intl.NumberFormat(locale, { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(n) || 0);
}

export function bytes(n) {
    n = Number(n) || 0;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(0)} KB`;
    return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

export function greeting() {
    const h = new Date().getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 19) return 'Selamat sore';
    return 'Selamat malam';
}

export function initials(name = '') {
    return (name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
}

export function isoDate(d = new Date()) {
    const x = toDate(d);
    if (!x) return '';
    return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
}

/* ---------- Kosakata domain ---------- */
export const MODELS = [
    { value: 'narasi', label: 'Narasi', desc: 'Satu narator, visual diam + voiceover', color: 'violet', icon: 'mic' },
    { value: 'ngobrol', label: 'Ngobrol', desc: 'Dialog Ibu (Aria) & Anak (Lumi)', color: 'sky', icon: 'messages-square' },
    { value: 'gabungan', label: 'Gabungan', desc: 'Narator + dialog Ibu & Anak', color: 'orange', icon: 'sparkles' },
    { value: 'presenter', label: 'Presenter', desc: 'Ibu bercerita langsung ke kamera, latar berganti tiap bagian', color: 'pink', icon: 'video' },
];
export const DURATIONS = [30, 60, 90];

/* Mesin generate video via Muse (kolom engine): video + audio dibuat platform yang dipilih. */
export const GENERATE_ENGINES = [
    { value: 'flow', label: 'Google Flow', short: 'Google Flow', desc: 'Video + audio dibuat Google Flow (Veo)', icon: 'film' },
    { value: 'elevenlabs', label: 'ElevenLabs', short: 'ElevenLabs', desc: 'Video + audio dibuat ElevenLabs', icon: 'mic' },
];
export function engineLabel(v) {
    return GENERATE_ENGINES.find((s) => s.value === v)?.label || 'Google Flow';
}
export function engineShort(v) {
    return GENERATE_ENGINES.find((s) => s.value === v)?.short || 'Google Flow';
}
/* Alias lama (voice_source) — dipertahankan agar impor lama tidak rusak. */
export const VOICE_SOURCES = GENERATE_ENGINES;
export const voiceSourceLabel = engineLabel;
export const voiceSourceShort = engineShort;

export function modelLabel(v) {
    return MODELS.find((m) => m.value === v)?.label || v || '—';
}
export function modelColor(v) {
    return MODELS.find((m) => m.value === v)?.color || 'slate';
}

export const WORKFLOW = {
    draft: { label: 'Draf', color: 'slate', icon: 'file-pen' },
    review: { label: 'Menunggu review', color: 'amber', icon: 'hourglass' },
    revisi: { label: 'Perlu revisi', color: 'rose', icon: 'rotate-ccw' },
    approved: { label: 'Disetujui', color: 'emerald', icon: 'badge-check' },
    published: { label: 'Terbit', color: 'fuchsia', icon: 'send' },
    archived: { label: 'Diarsipkan', color: 'zinc', icon: 'archive' },
};
export function workflowLabel(v) {
    return WORKFLOW[v]?.label || v || '—';
}
export function workflowColor(v) {
    return WORKFLOW[v]?.color || 'slate';
}

/* Status proses (kolom `status`): Cara 2 via Muse memakai waiting_muse → processing → completed / failed. */
export const GEN_STATUS = {
    pending_confirmation: { label: 'Menunggu konfirmasi', color: 'amber', icon: 'hourglass' },
    waiting_muse: { label: 'Menunggu Muse', color: 'sky', icon: 'hourglass' },
    processing: { label: 'Muse memproses', color: 'violet', icon: 'loader-circle' },
    failed: { label: 'Muse gagal', color: 'rose', icon: 'circle-x' },
};
export function isPendingMuse(g) {
    return g && ['waiting_muse', 'processing'].includes(g.status);
}

export const ROLE_LABELS = {
    super_admin: 'Super Admin',
    admin: 'Admin',
    editor: 'Editor',
    pengguna: 'Pengguna',
    viewer: 'Viewer',
};
export function roleLabel(v) {
    return ROLE_LABELS[v] || v || '—';
}

/** Teks naskah per bagian (kompatibel data lama: teks / dialog / narasi). */
export function partText(n) {
    return n?.teks ?? n?.dialog ?? n?.narasi ?? '';
}

export function captionText(c) {
    if (!c) return '';
    if (typeof c === 'string') return c;
    return c.text || c.caption || '';
}

/** Gabungkan hasil generate jadi teks siap salin / unduh. */
export function generationToText(g) {
    const naskah = g?.naskah || [];
    const prompts = g?.prompts || [];
    let t = `RUANG DHARMA STUDIO\nJudul: ${g?.judul || g?.keyword}\nKata kunci: ${g?.keyword} | Durasi: ${g?.durasi} detik | Model: ${modelLabel(g?.model)}\n\n=== NASKAH ===\n`;
    naskah.forEach((n, i) => {
        t += `\nBagian ${n.bagian ?? i + 1} — ${n.speaker || '-'}${n.suara ? ` (${n.suara})` : ''}\n${partText(n)}\nPrompt: ${n.prompt_visual || prompts[i] || '-'}\n`;
    });
    t += `\n=== CAPTION ===\n${captionText(g?.caption)}\n`;
    return t;
}
