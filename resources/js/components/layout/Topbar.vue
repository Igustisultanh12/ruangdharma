<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Icon from '@/components/ui/Icon.vue';
import { dateLong } from '@/lib/format.js';
import { useRealtimeStore } from '@/stores/realtime.js';
import { useUiStore } from '@/stores/ui.js';
import NotificationBell from './NotificationBell.vue';

const ui = useUiStore();
const rt = useRealtimeStore();
const route = useRoute();
const crumbs = computed(() => route.meta.breadcrumb || []);
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || '');
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-line bg-surface/80 backdrop-blur-xl">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button type="button" class="btn btn-secondary btn-icon lg:hidden" aria-label="Buka menu" @click="ui.sidebarOpen = true"><Icon name="menu" class="h-5 w-5" /></button>
            <div class="min-w-0">
                <nav v-if="crumbs.length" class="hidden items-center gap-1.5 text-xs font-semibold text-muted sm:flex" aria-label="Breadcrumb">
                    <template v-for="(c, i) in crumbs" :key="i">
                        <Icon v-if="i" name="chevron-right" class="h-3 w-3" />
                        <RouterLink v-if="c.to && i < crumbs.length - 1" :to="c.to" class="hover:text-ink">{{ c.label }}</RouterLink>
                        <span v-else :class="i === crumbs.length - 1 ? 'text-ink' : ''">{{ c.label }}</span>
                    </template>
                </nav>
                <p class="truncate text-[11px] text-muted sm:mt-0.5">{{ dateLong() }}</p>
            </div>

            <button type="button" class="ml-auto hidden h-10 w-64 items-center gap-2 rounded-2xl border-2 border-line bg-card px-3 text-sm text-muted transition hover:border-violet-300 md:flex" @click="ui.paletteOpen = true">
                <Icon name="search" class="h-4 w-4" />
                <span class="flex-1 text-left">Cari menu…</span>
                <span class="kbd">{{ isMac ? '⌘' : 'Ctrl' }}</span><span class="kbd">K</span>
            </button>

            <div class="ml-auto flex items-center gap-1 md:ml-2">
                <button type="button" class="btn btn-ghost btn-icon md:hidden" aria-label="Cari menu" @click="ui.paletteOpen = true"><Icon name="search" class="h-5 w-5" /></button>
                <RouterLink v-if="rt.quota" to="/generate" class="hidden items-center gap-1.5 rounded-xl border-2 border-line px-2.5 py-1.5 text-xs font-bold transition hover:border-violet-300 sm:flex" title="Sisa kuota generate hari ini">
                    <Icon name="zap" class="h-3.5 w-3.5 text-orange-500" />
                    <span class="tabular-nums">{{ rt.quota.remaining }}/{{ rt.quota.limit }}</span>
                </RouterLink>
                <button type="button" class="btn btn-ghost btn-icon" :aria-label="ui.dark ? 'Mode terang' : 'Mode gelap'" @click="ui.toggleDark()">
                    <Icon :name="ui.dark ? 'sun' : 'moon'" class="h-5 w-5" />
                </button>
                <NotificationBell />
            </div>
        </div>
    </header>
</template>
