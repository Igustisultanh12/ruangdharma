<script setup>
import { onMounted, reactive, ref } from 'vue';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Icon from '@/components/ui/Icon.vue';
import Modal from '@/components/ui/Modal.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Toggle from '@/components/ui/Toggle.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { relative, ROLE_LABELS, roleLabel } from '@/lib/format.js';
import { confirmDialog, toast } from '@/lib/toast.js';
import { useList } from '@/composables/useList.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const list = useList('/admin/users', { perPage: 15, filters: { search: '', role: '', status: '' }, realtime: ['users', 'all_generations'] });
const f = list.filters;
const plans = ref([]);
const roles = ref(Object.entries(ROLE_LABELS).map(([name, display_name]) => ({ name, display_name })));
onMounted(async () => {
    try {
        const [p, r] = await Promise.all([api.get('/admin/quota-plans'), api.get('/admin/roles')]);
        plans.value = p.data.data;
        roles.value = r.data.data;
    } catch {
        /* pakai default */
    }
});
const roleColor = { super_admin: 'fuchsia', admin: 'violet', editor: 'sky', pengguna: 'slate', viewer: 'zinc' };

const modal = ref(null); // 'create' | 'edit'
const saving = ref(false);
const errors = ref({});
const form = reactive({ id: null, name: '', email: '', password: '', password_confirmation: '', role: 'pengguna', quota_plan_id: '', daily_limit: '', is_active: true });
function openCreate() {
    Object.assign(form, { id: null, name: '', email: '', password: '', password_confirmation: '', role: 'pengguna', quota_plan_id: '', daily_limit: '', is_active: true });
    errors.value = {};
    modal.value = 'create';
}
function openEdit(u) {
    Object.assign(form, { id: u.id, name: u.name, email: u.email, role: u.role, quota_plan_id: u.quota_plan_id || '', daily_limit: u.daily_limit ?? '', is_active: !!u.is_active });
    errors.value = {};
    modal.value = 'edit';
}
async function save() {
    saving.value = true;
    errors.value = {};
    const base = { name: form.name, role: form.role, quota_plan_id: form.quota_plan_id || null, daily_limit: form.daily_limit === '' ? null : Number(form.daily_limit) };
    try {
        if (modal.value === 'create') {
            await api.post('/register', { ...base, email: form.email, password: form.password, password_confirmation: form.password_confirmation });
            toast.success(`Akun ${form.name} dibuat.`);
        } else {
            await api.put(`/admin/users/${form.id}`, { ...base, is_active: form.is_active });
            toast.success('Pengguna diperbarui.');
        }
        modal.value = null;
        list.load({ silent: true });
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
async function toggleActive(u) {
    const prev = u.is_active;
    u.is_active = !prev;
    try {
        const { data } = await api.post(`/admin/users/${u.id}/toggle-active`);
        toast.success(data.message);
    } catch (e) {
        u.is_active = prev;
        toast.error(errorMessage(e));
    }
}
async function remove(u) {
    if (!(await confirmDialog({ title: `Hapus ${u.name}?`, message: `Semua data milik ${u.email} (video, media, log) ikut terhapus permanen.`, tone: 'danger', confirmText: 'Hapus pengguna' }))) return;
    try {
        await api.delete(`/admin/users/${u.id}`);
        toast.success('Pengguna dihapus.');
        list.load({ silent: true });
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <div>
        <PageHeader title="Pengguna" description="Kelola akun, role, paket kuota, dan status aktif tim studio.">
            <template #actions><button type="button" class="btn btn-primary" @click="openCreate"><Icon name="user-plus" class="h-4 w-4" /> Tambah pengguna</button></template>
        </PageHeader>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap gap-3 border-b border-line bg-surface-2/40 p-4">
                <SearchInput v-model="f.search" placeholder="Cari nama atau email…" class="min-w-[220px] flex-1" />
                <select v-model="f.role" class="input input-sm w-auto" aria-label="Role"><option value="">Semua role</option><option v-for="r in roles" :key="r.name" :value="r.name">{{ r.display_name || r.name }}</option></select>
                <select v-model="f.status" class="input input-sm w-auto" aria-label="Status"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
            </div>
            <div v-if="list.loading.value && !list.loaded.value" class="space-y-3 p-6"><div v-for="i in 5" :key="i" class="skeleton h-12"></div></div>
            <EmptyState v-else-if="!list.items.value.length" icon="users" title="Tidak ada pengguna" description="Ubah pencarian atau tambahkan akun baru." />
            <div v-else class="table-wrap">
                <table class="table">
                    <thead><tr><th>Pengguna</th><th>Role</th><th>Paket / limit</th><th>Hari ini</th><th>Total</th><th>Aktif</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="u in list.items.value" :key="u.id">
                            <td>
                                <div class="flex items-center gap-3">
                                    <Avatar :user="u" size="md" />
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-1.5 font-bold">{{ u.name }} <Badge v-if="u.id === auth.user?.id" color="yellow" size="xs">Kamu</Badge></p>
                                        <p class="text-xs text-muted">{{ u.email }} · gabung {{ relative(u.created_at) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td><Badge :color="roleColor[u.role] || 'slate'" size="xs">{{ roleLabel(u.role) }}</Badge></td>
                            <td class="text-sm">
                                <span v-if="u.quota_plan" class="font-semibold">{{ u.quota_plan.name }} <span class="text-muted">({{ u.quota_plan.daily_limit }}/hari)</span></span>
                                <span v-else-if="u.daily_limit !== null" class="font-semibold">Khusus <span class="text-muted">({{ u.daily_limit }}/hari)</span></span>
                                <span v-else class="text-muted">Limit global</span>
                            </td>
                            <td class="font-bold tabular-nums">{{ u.today_generations }}</td>
                            <td class="tabular-nums text-muted">{{ u.total_generations }}</td>
                            <td><Toggle :model-value="!!u.is_active" :disabled="u.id === auth.user?.id" :label="`Status ${u.name}`" @update:model-value="toggleActive(u)" /></td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="btn btn-ghost btn-icon btn-sm" aria-label="Edit" title="Edit" @click="openEdit(u)"><Icon name="pencil" class="h-4 w-4" /></button>
                                    <button v-if="u.id !== auth.user?.id" type="button" class="btn btn-soft-danger btn-icon btn-sm" aria-label="Hapus" title="Hapus" @click="remove(u)"><Icon name="trash-2" class="h-4 w-4" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="list.meta" label="pengguna" @go="list.go" />
        </div>

        <Modal :show="!!modal" :title="modal === 'create' ? 'Tambah pengguna' : `Edit ${form.name}`" size="md" @close="modal = null">
            <form id="user-form" class="space-y-4" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label" for="u-name">Nama lengkap</label><input id="u-name" v-model="form.name" class="input" :class="{ 'input-error': errors.name }" required /><p v-if="errors.name" class="field-error">{{ errors.name }}</p></div>
                    <div v-if="modal === 'create'" class="sm:col-span-2"><label class="label" for="u-email">Email</label><input id="u-email" v-model="form.email" type="email" class="input" :class="{ 'input-error': errors.email }" required /><p v-if="errors.email" class="field-error">{{ errors.email }}</p></div>
                    <template v-if="modal === 'create'">
                        <div><label class="label" for="u-pw">Password</label><input id="u-pw" v-model="form.password" type="password" class="input" autocomplete="new-password" :class="{ 'input-error': errors.password }" required /><p v-if="errors.password" class="field-error">{{ errors.password }}</p></div>
                        <div><label class="label" for="u-pw2">Ulangi password</label><input id="u-pw2" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required /></div>
                    </template>
                    <div><label class="label" for="u-role">Role</label><select id="u-role" v-model="form.role" class="input" :disabled="form.id === auth.user?.id"><option v-for="r in roles" :key="r.name" :value="r.name">{{ r.display_name || r.name }}</option></select></div>
                    <div><label class="label" for="u-plan">Paket kuota</label><select id="u-plan" v-model="form.quota_plan_id" class="input"><option value="">Tanpa paket</option><option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }} ({{ p.daily_limit }}/hari)</option></select></div>
                    <div class="sm:col-span-2">
                        <label class="label" for="u-limit">Limit harian khusus <span class="font-normal text-muted">(opsional)</span></label>
                        <input id="u-limit" v-model="form.daily_limit" type="number" min="0" max="100" class="input" placeholder="Kosongkan untuk pakai paket / limit global" :disabled="!!form.quota_plan_id" />
                        <p class="help">Paket kuota diprioritaskan di atas limit khusus.</p>
                        <p v-if="errors.daily_limit" class="field-error">{{ errors.daily_limit }}</p>
                    </div>
                    <div v-if="modal === 'edit' && form.id !== auth.user?.id" class="flex items-center justify-between rounded-2xl bg-surface-2 p-3 sm:col-span-2">
                        <span class="text-sm font-semibold">Akun aktif</span>
                        <Toggle v-model="form.is_active" label="Akun aktif" />
                    </div>
                </div>
            </form>
            <template #footer>
                <button type="button" class="btn btn-ghost" @click="modal = null">Batal</button>
                <button type="submit" form="user-form" class="btn btn-primary" :disabled="saving"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> {{ modal === 'create' ? 'Buat akun' : 'Simpan' }}</button>
            </template>
        </Modal>
    </div>
</template>
