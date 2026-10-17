/* Directive kecil untuk interaksi: angka berjalan, efek ripple, fokus otomatis. */

function animateNumber(el, to, { prefix = '', suffix = '', duration = 900 } = {}) {
    const target = Number(to) || 0;
    const from = Number(el.dataset.counterValue || 0);
    el.dataset.counterValue = String(target);
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || from === target) {
        el.textContent = `${prefix}${new Intl.NumberFormat('id-ID').format(target)}${suffix}`;
        return;
    }
    const start = performance.now();
    cancelAnimationFrame(el._counterRaf);
    const step = (now) => {
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        const val = Math.round(from + (target - from) * eased);
        el.textContent = `${prefix}${new Intl.NumberFormat('id-ID').format(val)}${suffix}`;
        if (p < 1) el._counterRaf = requestAnimationFrame(step);
    };
    el._counterRaf = requestAnimationFrame(step);
}

export const counter = {
    mounted(el, { value }) {
        const v = typeof value === 'object' && value !== null ? value : { to: value };
        animateNumber(el, v.to, v);
    },
    updated(el, { value, oldValue }) {
        const v = typeof value === 'object' && value !== null ? value : { to: value };
        const o = typeof oldValue === 'object' && oldValue !== null ? oldValue : { to: oldValue };
        if (v.to !== o.to) animateNumber(el, v.to, v);
    },
};

export const ripple = {
    mounted(el) {
        el.addEventListener('pointerdown', (e) => {
            const r = el.getBoundingClientRect();
            const size = Math.max(r.width, r.height);
            const s = document.createElement('span');
            s.className = 'ripple-ink';
            s.style.width = s.style.height = `${size}px`;
            s.style.left = `${e.clientX - r.left - size / 2}px`;
            s.style.top = `${e.clientY - r.top - size / 2}px`;
            el.appendChild(s);
            setTimeout(() => s.remove(), 650);
        });
    },
};

export const autofocus = {
    mounted(el) {
        setTimeout(() => el.focus(), 60);
    },
};

export function installDirectives(app) {
    app.directive('counter', counter);
    app.directive('ripple', ripple);
    app.directive('autofocus', autofocus);
}
