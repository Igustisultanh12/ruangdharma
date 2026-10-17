import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
    state: () => ({
        dark: document.documentElement.classList.contains('dark'),
        sidebarOpen: false,
        paletteOpen: false,
    }),
    actions: {
        setDark(v) {
            this.dark = v;
            document.documentElement.classList.toggle('dark', v);
            try {
                localStorage.setItem('rd_theme', v ? 'dark' : 'light');
            } catch {
                /* mode privat */
            }
        },
        toggleDark() {
            this.setDark(!this.dark);
        },
    },
});
