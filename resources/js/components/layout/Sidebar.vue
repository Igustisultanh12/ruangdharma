<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Logo from '@/components/brand/Logo.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Icon from '@/components/ui/Icon.vue';
import { visibleMenu } from '@/lib/menu.js';
import { roleLabel } from '@/lib/format.js';
import { confirmDialog } from '@/lib/toast.js';
import { useAuthStore } from '@/stores/auth.js';
import { useRealtimeStore } from '@/stores/realtime.js';
import { useUiStore } from '@/stores/ui.js';

const auth = useAuthStore();
const rt = useRealtimeStore();
const ui = useUiStore();
const route = useRoute();
const router = useRouter();

const groups = computed(() => visibleMenu((r) => auth.hasAnyRole(r)));
const badges = computed(() => ({ unread: rt.unread, review: rt.reviewPending }));
function isActive(item) {
    if (item.to === '/') return route.path === '/';
    if (item.to === '/admin') return route.path === '/admin';
    return route.path === item.to || route.path.startsWith(item.to + '/');
}
const quota = computed(() => rt.quota || auth.user?.quota || null);
const quotaPct = computed(() => {
    const q = quota.value;
    if (!q?.limit) return 0;
    const used = q.used ?? q.used_today ?? 0;
    return Math.min(100, Math.round((used / q.limit) * 100));
});

async function logout() {
    if (!(await confirmDialog({ title: 'Keluar dari studio?', message: 'Sesi di perangkat ini akan diakhiri.', confirmText: 'Keluar', icon: 'log-out' }))) return;
    rt.stop();
    await auth.logout();
    router.replace('/login');
}
</script>

<template>
    <div>
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-200" leave-to-class="opacity-0">
            <div v-if="ui.sidebarOpen" class="fixed inset-0 z-40 bg-zinc-950/50 backdrop-blur-sm lg:hidden" @click="ui.sidebarOpen = false"></div>
        </Transition>
        <aside
            :class="ui.sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-line bg-card transition-transform duration-300 ease-out"
            aria-label="Menu utama"
        >
            <div class="flex h-[72px] shrink-0 items-center justify-between px-5">
                <RouterLink to="/" class="group" @click="ui.sidebarOpen = false"><Logo /></RouterLink>
                <button type="button" class="btn btn-ghost btn-icon btn-sm lg:hidden" aria-label="Tutup menu" @click="ui.sidebarOpen = false"><Icon name="x" class="h-5 w-5" /></button>
            </div>

            <div class="mx-4 mb-1 flex items-center justify-between rounded-2xl border-2 border-ink bg-yellow-300 px-3 py-2 text-xs font-extrabold text-zinc-900 shadow-brutal-sm">
                <span class="flex items-center gap-1.5"><Icon :name="auth.isAdmin ? 'crown' : auth.isReviewer ? 'pen-tool' : 'sparkles'" class="h-3.5 w-3.5" /> Panel {{ roleLabel(auth.user?.role) }}</span>
                <span class="flex items-center gap-1 text-[10px] font-bold">
                    <span :class="rt.online ? 'bg-emerald-600' : 'bg-rose-600'" class="relative flex h-2 w-2"><span v-if="rt.online" class="animate-ping-slow absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75"></span><span class="relative h-2 w-2 rounded-full" :class="rt.online ? 'bg-emerald-600' : 'bg-rose-600'"></span></span>
                    {{ rt.online ? 'Live' : 'Offline' }}
                </span>
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                <div v-for="g in groups" :key="g.title">
                    <p class="px-3 pb-1.5 text-[11px] font-extrabold tracking-[0.12em] text-muted/80 uppercase">{{ g.title }}</p>
                    <ul class="space-y-0.5">
                        <li v-for="item in g.items" :key="item.to">
                            <RouterLink
                                :to="item.to"
                                :class="isActive(item) ? 'bg-ink text-surface shadow-[0_8px_20px_-8px_rgb(124_58_237/0.6)]' : 'text-muted hover:bg-surface-2 hover:text-ink'"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold transition-all duration-200"
                                @click="ui.sidebarOpen = false"
                            >
                                <span :class="isActive(item) ? 'bg-brand-gradient text-white' : 'bg-surface-2 group-hover:bg-card'" class="grid h-8 w-8 place-items-center rounded-lg transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6">
                                    <Icon :name="item.icon" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 truncate">{{ item.label }}</span>
                                <span v-if="item.badge && badges[item.badge] > 0" class="animate-pop-in rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-extrabold text-white tabular-nums">{{ badges[item.badge] > 99 ? '99+' : badges[item.badge] }}</span>
                            </RouterLink>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-line bg-surface-2/40 p-3">
                <div v-if="quota" class="mb-3 rounded-2xl border border-line bg-card px-3 py-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold">Kuota hari ini</span>
                        <span class="tabular-nums text-muted"><b class="text-ink">{{ quota.remaining }}</b> / {{ quota.limit }} tersisa</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-2">
                        <div :class="quotaPct >= 90 ? 'bg-rose-500' : 'bg-brand-gradient'" class="h-full rounded-full transition-all duration-700" :style="{ width: `${quotaPct}%` }"></div>
                    </div>
                </div>
                <RouterLink to="/profile" :class="route.path === '/profile' ? 'bg-card ring-1 ring-line' : 'hover:bg-card'" class="group flex items-center gap-3 rounded-2xl p-2 transition" @click="ui.sidebarOpen = false">
                    <Avatar :user="auth.user" size="md" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold group-hover:text-violet-600 dark:group-hover:text-violet-300">{{ auth.user?.name }}</span>
                        <span class="block truncate text-xs text-muted">{{ auth.user?.email }}</span>
                    </span>
                    <Icon name="chevron-right" class="h-4 w-4 text-muted" />
                </RouterLink>
                <button type="button" class="btn btn-soft-danger btn-sm mt-2 w-full" @click="logout"><Icon name="log-out" class="h-4 w-4" /> Keluar</button>
            </div>
        </aside>
    </div>
</template>
