<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Icon from '@/components/ui/Icon.vue';
import Modal from '@/components/ui/Modal.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { confirmDialog, toast } from '@/lib/toast.js';
import { useRealtime } from '@/composables/useRealtime.js';

const roles = ref([]);
const permissions = ref([]);
const matrix = reactive({}); // roleId -> Set(name)
const dirty = reactive({});
const loading = ref(true);
const saving = ref(null);
const BUILTIN = ['super_admin', 'admin', 'pengguna'];

async function load() {
    try {
        const [r, p] = await Promise.all([api.get('/admin/roles'), api.get('/admin/permissions')]);
        roles.value = r.data.data;
        permissions.value = p.data.data;
        roles.value.forEach((role) => {
            if (!dirty[role.id]) matrix[role.id] = new Set(role.permissions.map((x) => x.name));
        });
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}
onMounted(load);
useRealtime(['roles'], load);

const groups = computed(() => {
    const out = {};
    permissions.value.forEach((p) => (out[p.group || 'lainnya'] ||= []).push(p));
    return out;
});
function toggle(role, perm) {
    if (role.name === 'super_admin') return;
    const set = new Set(matrix[role.id]);
    set.has(perm) ? set.delete(perm) : set.add(perm);
    matrix[role.id] = set;
    dirty[role.id] = true;
}
async function saveRole(role) {
    saving.value = role.id;
    try {
        await api.post(`/admin/roles/${role.id}/permissions`, { permissions: [...matrix[role.id]] });
        dirty[role.id] = false;
        toast.success(`Izin ${role.display_name || role.name} disimpan.`);
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        saving.value = null;
    }
}
async function removeRole(role) {
    if (!(await confirmDialog({ title: `Hapus role ${role.display_name || role.name}?`, message: 'Pengguna dengan role ini kehilangan izinnya.', tone: 'danger', confirmText: 'Hapus role' }))) return;
    try {
        await api.delete(`/admin/roles/${role.id}`);
        toast.success('Role dihapus.');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

const showCreate = ref(false);
const form = reactive({ name: '', display_name: '', description: '' });
const errors = ref({});
async function create() {
    errors.value = {};
    try {
        await api.post('/admin/roles', { ...form, name: form.name.trim().toLowerCase().replace(/\s+/g, '_') });
        toast.success('Role dibuat.');
        showCreate.value = false;
        Object.assign(form, { name: '', display_name: '', description: '' });
        load();
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <div>
        <PageHeader title="Role & izin" description="Centang izin yang dimiliki tiap role. Super Admin selalu punya semua izin.">
            <template #actions><button type="button" class="btn btn-primary" @click="showCreate = true"><Icon name="plus" class="h-4 w-4" /> Role baru</button></template>
        </PageHeader>

        <div v-if="loading" class="skeleton h-96 rounded-3xl"></div>
        <div v-else class="card overflow-hidden">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 min-w-56 bg-surface-2">Izin</th>
                            <th v-for="r in roles" :key="r.id" class="min-w-32 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-ink">{{ r.display_name || r.name }}</span>
                                    <span class="font-mono text-[10px] font-normal">{{ r.name }}</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(perms, group) in groups" :key="group">
                            <tr><td :colspan="roles.length + 1" class="bg-surface-2/40 py-2 text-xs font-extrabold text-muted capitalize">{{ group }}</td></tr>
                            <tr v-for="p in perms" :key="p.id">
                                <td class="sticky left-0 z-10 bg-card"><p class="font-semibold">{{ p.display_name || p.name }}</p><p class="font-mono text-[11px] text-muted">{{ p.name }}</p></td>
                                <td v-for="r in roles" :key="r.id" class="text-center">
                                    <button
                                        type="button"
                                        :aria-pressed="r.name === 'super_admin' || matrix[r.id]?.has(p.name)"
                                        :aria-label="`${p.name} untuk ${r.name}`"
                                        :disabled="r.name === 'super_admin'"
                                        :class="r.name === 'super_admin' || matrix[r.id]?.has(p.name) ? 'border-ink bg-lime-300 text-zinc-900 shadow-brutal-sm' : 'border-line text-transparent hover:border-violet-300'"
                                        class="mx-auto grid h-7 w-7 place-items-center rounded-lg border-2 transition disabled:opacity-70"
                                        @click="toggle(r, p.name)"
                                    ><Icon name="check" class="h-4 w-4" :stroke="3" /></button>
                                </td>
                            </tr>
                        </template>
                        <tr>
                            <td class="sticky left-0 z-10 bg-card text-xs text-muted">Simpan per role</td>
                            <td v-for="r in roles" :key="r.id" class="text-center">
                                <div class="flex flex-col items-center gap-1.5">
                                    <button v-if="r.name !== 'super_admin'" type="button" :class="dirty[r.id] ? 'btn-primary' : 'btn-secondary'" class="btn btn-sm" :disabled="!dirty[r.id] || saving === r.id" @click="saveRole(r)"><Spinner v-if="saving === r.id" class="h-3.5 w-3.5" /> Simpan</button>
                                    <button v-if="!BUILTIN.includes(r.name)" type="button" class="text-[11px] font-bold text-rose-500 hover:underline" @click="removeRole(r)">Hapus role</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showCreate" title="Role baru" size="sm" @close="showCreate = false">
            <form id="role-form" class="space-y-4" @submit.prevent="create">
                <div><label class="label" for="r-display">Nama tampilan</label><input id="r-display" v-model="form.display_name" class="input" placeholder="mis. Penulis Naskah" required /></div>
                <div><label class="label" for="r-name">Kode role</label><input id="r-name" v-model="form.name" class="input font-mono" placeholder="penulis_naskah" required /><p class="help">Huruf kecil & garis bawah, tidak bisa diubah.</p><p v-if="errors.name" class="field-error">{{ errors.name }}</p></div>
                <div><label class="label" for="r-desc">Deskripsi</label><textarea id="r-desc" v-model="form.description" rows="2" class="input"></textarea></div>
            </form>
            <template #footer>
                <button type="button" class="btn btn-ghost" @click="showCreate = false">Batal</button>
                <button type="submit" form="role-form" class="btn btn-primary"><Icon name="plus" class="h-4 w-4" /> Buat role</button>
            </template>
        </Modal>
    </div>
</template>
