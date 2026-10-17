<script setup>
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import { dateTime, relative } from '@/lib/format.js';
import { useList } from '@/composables/useList.js';

const list = useList('/admin/activity-logs', { perPage: 25, filters: { search: '', action: '' }, realtime: ['activity'] });
const f = list.filters;
const actions = [
    ['', 'Semua aksi'], ['login', 'Login'], ['logout', 'Logout'], ['generate', 'Generate'], ['generate.edit', 'Edit naskah'], ['generate.delete', 'Hapus video'],
    ['review.submit', 'Kirim review'], ['review.approve', 'Setujui'], ['review.reject', 'Minta revisi'], ['review.publish', 'Terbitkan'],
    ['user.create', 'Buat user'], ['user.update', 'Ubah user'], ['user.toggle', 'Aktif/nonaktif user'], ['user.delete', 'Hapus user'],
    ['setting.update', 'Pengaturan'], ['muse.token', 'Token Muse'], ['profile.update', 'Profil'], ['profile.password', 'Ganti password'],
];
const color = (a = '') => (a.startsWith('login') ? 'emerald' : a.startsWith('logout') ? 'zinc' : a.startsWith('generate') ? 'violet' : a.startsWith('review') ? 'amber' : a.startsWith('user') ? 'sky' : a.includes('delete') ? 'rose' : 'slate');
</script>

<template>
    <div>
        <PageHeader title="Log aktivitas" description="Jejak semua aksi penting di studio. Baris baru muncul otomatis." />
        <div class="card overflow-hidden">
            <div class="flex flex-wrap gap-3 border-b border-line bg-surface-2/40 p-4">
                <SearchInput v-model="f.search" placeholder="Cari deskripsi…" class="min-w-[220px] flex-1" />
                <select v-model="f.action" class="input input-sm w-auto" aria-label="Aksi"><option v-for="[v, l] in actions" :key="v" :value="v">{{ l }}</option></select>
            </div>
            <div v-if="list.loading.value && !list.loaded.value" class="space-y-3 p-6"><div v-for="i in 8" :key="i" class="skeleton h-10"></div></div>
            <EmptyState v-else-if="!list.items.value.length" icon="scroll-text" title="Belum ada aktivitas" />
            <div v-else class="table-wrap">
                <table class="table">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Deskripsi</th><th>IP</th></tr></thead>
                    <tbody>
                        <tr v-for="a in list.items.value" :key="a.id">
                            <td class="whitespace-nowrap" :title="dateTime(a.created_at)"><p class="font-semibold">{{ relative(a.created_at) }}</p><p class="text-xs text-muted">{{ dateTime(a.created_at) }}</p></td>
                            <td><div class="flex items-center gap-2"><Avatar :user="a.user" :name="a.user?.name || 'Sistem'" size="xs" /><span class="text-sm font-semibold">{{ a.user?.name || 'Sistem' }}</span></div></td>
                            <td><Badge :color="color(a.action)" size="xs"><span class="font-mono">{{ a.action }}</span></Badge></td>
                            <td class="max-w-md text-sm">{{ a.description }}</td>
                            <td class="font-mono text-xs text-muted">{{ a.ip || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="list.meta" label="aktivitas" @go="list.go" />
        </div>
    </div>
</template>
