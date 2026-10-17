import { defineStore } from 'pinia';

// Dark mode + status sidebar. Dark mode disimpan di localStorage,
// class .dark diterapkan ke <html> agar Tailwind + app.css mengikutinya.
export const useUiStore = defineStore('ui', {
  state: () => ({
    darkMode: localStorage.getItem('rd_dark_mode') === '1',
    mobileSidebarOpen: false,
    expandedMenus: {},
  }),
  actions: {
    initDark() {
      document.documentElement.classList.toggle('dark', this.darkMode);
    },
    toggleDark() {
      this.darkMode = !this.darkMode;
      localStorage.setItem('rd_dark_mode', this.darkMode ? '1' : '0');
      this.initDark();
    },
    toggleMenu(key) {
      this.expandedMenus[key] = !this.expandedMenus[key];
    },
    setMenu(key, open) {
      this.expandedMenus[key] = open;
    },
    closeMobileSidebar() {
      this.mobileSidebarOpen = false;
    },
  },
});
