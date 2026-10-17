<script setup>
import { ref, watch } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import Segmented from '@/components/ui/Segmented.vue';
import { dateTime, relative } from '@/lib/format.js';
import { useList } from '@/composables/useList.js';

const status = ref('');
const list = useList('/admin/login-histories', { perPage: 25, filters: { email: '', success: '' }, realtime: ['logins'] });
watch(status, (v) => (list.filters.success = v));
function device(ua = '') {
    const os = /Android/i.test(ua) ? 'Android' : /iPhone|iPad/i.test(ua) ? 'iOS' : /Windows/i.test(ua) ? 'Windows' : /Mac OS/i.test(ua) ? 'macOS' : /Linux/i.test(ua) ? 'Linux' : 'Perangkat lain';
    const br = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : /curl|Guzzle|Symfony/i.test(ua) ? 'API' : 'Browser';
    return `${br} · ${os}`;
}
</script>

<template>
    <div>
        <PageHeader title="Riwayat login" description="Pantau percobaan masuk, termasuk yang gagal. 5 kali gagal dari satu IP akan diblokir 5 menit." />
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-line bg-surface-2/40 p-4">
                <SearchInput v-model="list.filters.email" placeholder="Cari email…" class="min-w-[220px] flex-1" />
                <Segmented v-model="status" :options="[{ value: '', label: 'Semua' }, { value: '1', label: 'Berhasil', icon: 'circle-check' }, { value: '0', label: 'Gagal', icon: 'circle-x' }]" />
            </div>
            <div v-if="list.loading.value && !list.loaded.value" class="space-y-3 p-6"><div v-for="i in 8" :key="i" class="skeleton h-10"></div></div>
            <EmptyState v-else-if="!list.items.value.length" icon="log-in" title="Belum ada riwayat login" />
            <div v-else class="table-wrap">
                <table class="table">
                    <thead><tr><th>Waktu</th><th>Email</th><th>Status</th><th>Perangkat</th><th>IP</th></tr></thead>
                    <tbody>
                        <tr v-for="l in list.items.value" :key="l.id">
                            <td class="whitespace-nowrap"><p class="font-semibold">{{ relative(l.created_at) }}</p><p class="text-xs text-muted">{{ dateTime(l.created_at) }}</p></td>
                            <td><p class="font-semibold">{{ l.email }}</p><p v-if="l.user" class="text-xs text-muted">{{ l.user.name }}</p></td>
                            <td><Badge :color="l.success ? 'emerald' : 'rose'" :icon="l.success ? 'circle-check' : 'circle-x'" size="xs">{{ l.success ? 'Berhasil' : 'Gagal' }}</Badge></td>
                            <td class="text-sm" :title="l.user_agent"><Icon name="monitor-smartphone" class="mr-1 inline h-4 w-4 text-muted" />{{ device(l.user_agent) }}</td>
                            <td class="font-mono text-xs text-muted">{{ l.ip || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="list.meta" label="login" @go="list.go" />
        </div>
    </div>
</template>
