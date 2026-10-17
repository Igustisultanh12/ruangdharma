import { defineStore } from 'pinia';
import api, { TOKEN_KEY, USER_KEY } from '@/lib/api.js';

function readUser() {
    try {
        return JSON.parse(localStorage.getItem(USER_KEY) || 'null');
    } catch {
        return null;
    }
}

export const REVIEWER_ROLES = ['admin', 'super_admin', 'editor'];
export const ADMIN_ROLES = ['admin', 'super_admin'];

export const useAuthStore = defineStore('auth', {
    state: () => ({ user: readUser(), token: localStorage.getItem(TOKEN_KEY) }),
    getters: {
        isLoggedIn: (s) => !!s.token,
        roles: (s) => [...new Set([s.user?.role, ...(s.user?.roles || [])].filter(Boolean))],
        isAdmin() {
            return this.user?.is_admin ?? this.roles.some((r) => ADMIN_ROLES.includes(r));
        },
        isReviewer() {
            return this.user?.is_reviewer ?? this.roles.some((r) => REVIEWER_ROLES.includes(r));
        },
        firstName: (s) => (s.user?.name || '').split(' ')[0] || 'Kawan',
    },
    actions: {
        hasAnyRole(list = []) {
            return !list.length || this.roles.some((r) => list.includes(r));
        },
        persist() {
            if (this.token) localStorage.setItem(TOKEN_KEY, this.token);
            else localStorage.removeItem(TOKEN_KEY);
            if (this.user) localStorage.setItem(USER_KEY, JSON.stringify(this.user));
            else localStorage.removeItem(USER_KEY);
        },
        setUser(user) {
            this.user = user;
            this.persist();
        },
        async login(email, password) {
            const { data } = await api.post('/login', { email, password });
            this.token = data.token;
            this.user = data.user;
            this.persist();
            return data;
        },
        async fetchMe() {
            const { data } = await api.get('/me');
            this.setUser(data.user);
            return data.user;
        },
        async logout({ remote = true } = {}) {
            if (remote && this.token) {
                try {
                    await api.post('/logout');
                } catch {
                    /* token mungkin sudah tidak berlaku */
                }
            }
            this.clear();
        },
        clear() {
            this.token = null;
            this.user = null;
            this.persist();
        },
    },
});
