<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-900 flex">
    <!-- Sidebar desktop -->
    <aside class="hidden lg:flex w-72 shrink-0 flex-col bg-slate-900 text-white sticky top-0 h-screen overflow-y-auto">
      <SidebarBrand />
      <div class="px-3 pb-6"><Sidebar :items="menu" /></div>
    </aside>

    <!-- Sidebar mobile drawer -->
    <div v-if="ui.mobileSidebarOpen" class="fixed inset-0 z-40 lg:hidden">
      <div class="absolute inset-0 bg-black/50" @click="ui.closeMobileSidebar()" />
      <aside class="absolute left-0 top-0 h-full w-72 bg-slate-900 text-white overflow-y-auto">
        <SidebarBrand show-close @close="ui.closeMobileSidebar()" />
        <div class="px-3 pb-6"><Sidebar :items="menu" @navigate="ui.closeMobileSidebar()" /></div>
      </aside>
    </div>

    <!-- Kolom utama -->
    <div class="flex-1 min-w-0 flex flex-col">
      <!-- Topbar -->
      <header class="sticky top-0 z-30 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-5 h-16">
          <button @click="ui.mobileSidebarOpen = true" class="lg:hidden p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Buka menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
          </button>

          <!-- Search global -->
          <form @submit.prevent="doGlobalSearch" class="relative flex-1 max-w-md hidden sm:block">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            <input
              ref="searchInput" v-model="globalSearch" type="text" placeholder="Cari keyword di riwayat / template... (Ctrl+K)"
              class="input !pl-9 !pr-14"
            />
            <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] bg-slate-100 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded px-1.5 py-0.5 text-slate-500 dark:text-slate-300">Ctrl+K</kbd>
          </form>
          <div class="flex-1 sm:hidden"></div>

          <!-- Dark mode toggle -->
          <button @click="ui.toggleDark()" class="p-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-200" :title="ui.darkMode ? 'Mode terang' : 'Mode gelap'" aria-label="Toggle dark mode">
            <svg v-if="ui.darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
          </button>

          <!-- Bell notifikasi -->
          <div class="relative">
            <button @click="bellOpen = !bellOpen; avatarOpen = false" class="relative p-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-200" aria-label="Notifikasi">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
              <span v-if="notif.unreadCount" class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ notif.unreadCount }}</span>
            </button>
            <div v-if="bellOpen" class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
              <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                <p class="font-semibold text-sm text-slate-900 dark:text-white">Notifikasi</p>
                <button @click="notif.markAllRead()" class="text-xs text-violet-600 hover:underline">Tandai Semua Dibaca</button>
              </div>
              <div class="max-h-96 overflow-y-auto">
                <button
                  v-for="n in notif.latest" :key="n.id"
                  @click="openNotification(n)"
                  class="w-full text-left px-4 py-3 border-b border-slate-50 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                  :class="{ 'bg-violet-50/60 dark:bg-violet-900/20': !n.read }"
                >
                  <p class="text-sm font-medium text-slate-900 dark:text-white">{{ n.title }}</p>
                  <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ n.message }}</p>
                  <p class="text-[11px] text-slate-400 mt-1">{{ formatTime(n.created_at) }}</p>
                </button>
                <p v-if="!notif.latest.length" class="text-sm text-slate-500 dark:text-slate-400 text-center py-6">Belum ada notifikasi.</p>
              </div>
            </div>
          </div>

          <!-- Avatar dropdown -->
          <div class="relative">
            <button @click="avatarOpen = !avatarOpen; bellOpen = false" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700">
              <span v-if="auth.user?.avatar" class="h-9 w-9 rounded-full object-cover overflow-hidden"><img :src="auth.user.avatar" class="h-9 w-9 rounded-full object-cover" alt="Avatar" /></span>
              <span v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-violet-600 text-white text-sm font-bold">{{ auth.initials }}</span>
              <span class="hidden md:block text-left">
                <span class="block text-sm font-semibold text-slate-900 dark:text-white leading-tight">{{ auth.user?.name }}</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400 leading-tight">{{ auth.user?.email }}</span>
              </span>
              <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div v-if="avatarOpen" class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
              <RouterLink to="/profile" @click="avatarOpen = false" class="block px-4 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100">Profil Saya</RouterLink>
              <RouterLink v-if="auth.isAdmin" to="/admin/settings" @click="avatarOpen = false" class="block px-4 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100">Pengaturan</RouterLink>
              <button @click="doLogout" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40">Keluar</button>
            </div>
          </div>
        </div>

        <!-- Breadcrumb -->
        <div v-if="route.meta.breadcrumb" class="px-3 sm:px-5 pb-3 flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 overflow-x-auto whitespace-nowrap">
          <template v-for="(c, i) in route.meta.breadcrumb" :key="i">
            <span v-if="i > 0" class="text-slate-300 dark:text-slate-600">/</span>
            <RouterLink v-if="c.to" :to="c.to" class="hover:text-violet-600">{{ c.label }}</RouterLink>
            <span v-else class="font-medium text-slate-800 dark:text-slate-100">{{ c.label }}</span>
          </template>
        </div>
      </header>

      <main class="flex-1 p-3 sm:p-5 lg:p-6 max-w-[1400px] w-full mx-auto">
        <slot />
      </main>
      <footer class="px-5 pb-5 text-center text-xs text-slate-400 dark:text-slate-500">Ruang Dharma Studio — Generate Naskah, Video & Caption</footer>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { useAuthStore } from '../stores/auth.js';
import { useUiStore } from '../stores/ui.js';
import { useNotificationStore } from '../stores/notifications.js';
import Sidebar from '../components/Sidebar.vue';
import SidebarBrand from '../components/SidebarBrand.vue';

const auth = useAuthStore();
const ui = useUiStore();
const notif = useNotificationStore();
const route = useRoute();
const router = useRouter();
const bellOpen = ref(false);
const avatarOpen = ref(false);
const globalSearch = ref('');
const searchInput = ref(null);

const menu = computed(() => {
  const items = [
    { label: '', items: [{ label: 'Dashboard', to: '/', icon: 'dashboard', exact: true }] },
    {
      label: 'Studio',
      items: [{
        label: 'Studio', icon: 'studio', children: [
          { label: 'Generate Video', to: '/generate', icon: 'spark' },
          { label: 'Template & Ide', to: '/templates', icon: 'template' },
          { label: 'Media Library', to: '/media', icon: 'media' },
          { label: 'Kalender Konten', to: '/calendar', icon: 'calendar' },
        ],
      }],
    },
    {
      label: 'Riwayat',
      items: [{
        label: 'Riwayat', icon: 'history', children: [
          { label: 'Semua Riwayat', to: '/history', icon: 'list' },
          { label: 'Favorit', to: '/history?tab=favorit', icon: 'star' },
          { label: 'Arsip', to: '/history?tab=arsip', icon: 'archive' },
        ],
      }],
    },
    { label: '', items: [{ label: 'Profil', to: '/profile', icon: 'user' }] },
  ];
  if (auth.isAdmin) {
    items.push({
      label: 'Admin',
      items: [{
        label: 'Admin', icon: 'admin', children: [
          { label: 'Dashboard Admin', to: '/admin', icon: 'dashboard' },
          { label: 'Pengguna', to: '/admin/users', icon: 'users' },
          { label: 'Role & Permission', to: '/admin/roles', icon: 'key' },
          { label: 'Review Naskah', to: '/admin/review', icon: 'review' },
          { label: 'Analitik', to: '/admin/analytics', icon: 'chart' },
          { label: 'Log Aktivitas', to: '/admin/activity-logs', icon: 'log' },
          { label: 'Riwayat Login', to: '/admin/login-history', icon: 'log' },
          { label: 'Riwayat Semua', to: '/admin/history', icon: 'history' },
          { label: 'Pengaturan', to: '/admin/settings', icon: 'settings' },
          { label: 'Integrasi Muse', to: '/admin/muse-config', icon: 'muse' },
          { label: 'API Keys', to: '/admin/api-keys', icon: 'key' },
          { label: 'Webhooks', to: '/admin/webhooks', icon: 'webhook' },
          { label: 'Paket Kuota', to: '/admin/quota-plans', icon: 'package' },
        ],
      }],
    });
  }
  return items;
});

function doGlobalSearch() {
  const q = globalSearch.value.trim();
  if (!q) return;
  bellOpen.value = false;
  avatarOpen.value = false;
  router.push({ name: 'history', query: { q } });
}
function onKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    searchInput.value?.focus();
  }
  if (e.key === 'Escape') {
    bellOpen.value = false;
    avatarOpen.value = false;
  }
}
function openNotification(n) {
  notif.markRead(n.id);
  bellOpen.value = false;
  if (n.link) router.push(n.link);
}
function formatTime(iso) {
  try {
    return new Date(iso).toLocaleString('id-ID');
  } catch {
    return iso;
  }
}
async function doLogout() {
  avatarOpen.value = false;
  const res = await Swal.fire({ title: 'Keluar?', text: 'Sesi akan diakhiri.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' });
  if (res.isConfirmed) {
    await auth.logout();
    router.push('/login');
  }
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>
