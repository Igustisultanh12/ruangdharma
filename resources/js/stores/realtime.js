import { defineStore } from 'pinia';
import api from '@/lib/api.js';
import { toast } from '@/lib/toast.js';
import { useAuthStore } from './auth.js';

/*
 * Realtime tanpa refresh.
 * Frontend memanggil GET /api/realtime/pulse secara berkala. Server mengembalikan "stamp"
 * per resource (generations, notifications, users, review, ...). Bila stamp berubah,
 * semua komponen yang berlangganan resource itu (lihat composables/useRealtime.js)
 * memuat ulang datanya secara diam-diam. Notifikasi baru muncul sebagai toast.
 */
const VISIBLE_INTERVAL = 4000;
const HIDDEN_INTERVAL = 30000;
const listeners = new Map(); // key -> Set<fn>
let timer = null;
let inflight = false;
let activityBound = false;

export const useRealtimeStore = defineStore('realtime', {
    state: () => ({
        running: false,
        online: true,
        stamps: {},
        unread: 0,
        quota: null,
        reviewPending: null,
        lastPulseAt: null,
        lastNotificationId: null,
        sessionTimeout: 120,
        lastActivity: Date.now(),
    }),
    actions: {
        start() {
            if (this.running) return;
            this.running = true;
            this.bindActivity();
            this.pulse();
            this.schedule();
        },
        stop() {
            this.running = false;
            clearTimeout(timer);
            timer = null;
            this.stamps = {};
            this.lastNotificationId = null;
        },
        schedule() {
            clearTimeout(timer);
            if (!this.running) return;
            const wait = document.visibilityState === 'visible' ? VISIBLE_INTERVAL : HIDDEN_INTERVAL;
            timer = setTimeout(async () => {
                await this.pulse();
                this.schedule();
            }, wait);
        },
        /** Panggil setelah aksi lokal agar komponen lain ikut terbarui segera. */
        async pulseNow() {
            await this.pulse();
            this.schedule();
        },
        async pulse() {
            if (!this.running || inflight) return;
            inflight = true;
            try {
                const { data } = await api.get('/realtime/pulse');
                this.online = true;
                this.lastPulseAt = new Date();
                this.unread = data.unread_notifications ?? 0;
                this.quota = data.quota || null;
                this.reviewPending = data.review_pending;
                this.sessionTimeout = data.session_timeout || 120;

                const changed = [];
                const first = !Object.keys(this.stamps).length;
                for (const [key, val] of Object.entries(data.stamps || {})) {
                    if (!first && this.stamps[key] !== val) changed.push(key);
                }
                this.stamps = data.stamps || {};

                const latest = data.latest_notification;
                if (latest && this.lastNotificationId !== null && latest.id > this.lastNotificationId) {
                    toast.info(latest.message || '', { title: latest.title, duration: 6000 });
                }
                if (latest) this.lastNotificationId = Math.max(this.lastNotificationId ?? 0, latest.id);
                else if (this.lastNotificationId === null) this.lastNotificationId = 0;

                if (changed.includes('me')) {
                    useAuthStore().fetchMe().catch(() => {});
                }
                changed.forEach((key) => listeners.get(key)?.forEach((fn) => {
                    try {
                        fn(key);
                    } catch (e) {
                        console.error(e);
                    }
                }));
                this.checkIdle();
            } catch (e) {
                if (!e.response) this.online = false;
            } finally {
                inflight = false;
            }
        },
        subscribe(keys, fn) {
            const list = Array.isArray(keys) ? keys : [keys];
            list.forEach((k) => {
                if (!listeners.has(k)) listeners.set(k, new Set());
                listeners.get(k).add(fn);
            });
            return () => list.forEach((k) => listeners.get(k)?.delete(fn));
        },
        bindActivity() {
            if (activityBound) return;
            activityBound = true;
            let last = 0;
            const mark = () => {
                const now = Date.now();
                if (now - last > 5000) {
                    last = now;
                    this.lastActivity = now;
                }
            };
            ['pointerdown', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach((ev) => window.addEventListener(ev, mark, { passive: true }));
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.running) {
                    this.checkIdle();
                    this.pulseNow();
                }
            });
            window.addEventListener('online', () => this.running && this.pulseNow());
        },
        checkIdle() {
            const limit = (this.sessionTimeout || 120) * 60 * 1000;
            if (Date.now() - this.lastActivity > limit) {
                const auth = useAuthStore();
                this.stop();
                auth.logout().finally(() => {
                    toast.warning('Sesi berakhir karena tidak ada aktivitas. Silakan masuk lagi.');
                    window.location.assign('/login');
                });
            }
        },
    },
});
