<script setup>
import { computed, onMounted, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Icon from '@/components/ui/Icon.vue';
import Spinner from '@/components/ui/Spinner.vue';
import api, { errorMessage, fieldErrors } from '@/lib/api.js';
import { toast } from '@/lib/toast.js';

/* Pengaturan ElevenLabs (TTS untuk sumber suara video via Muse).
   API key disimpan terenkripsi di server dan tidak pernah ditampilkan utuh —
   di sini hanya terlihat status + 4 digit terakhir. Kosongkan field API key
   saat menyimpan untuk mempertahankan key lama. */
const loading = ref(true);
const saving = ref(false);
const loadingVoices = ref(false);
const errors = ref({});
const configured = ref(false);
const masked = ref(null);
const apiKey = ref('');
const removeKey = ref(false);
const form = ref({ voice_ibu: '', voice_anak: '', voice_narator: '', model_id: 'eleven_multilingual_v2' });
const voices = ref([]);
const MODEL_OPTIONS = ['eleven_multilingual_v2', 'eleven_flash_v2_5', 'eleven_turbo_v2_5', 'eleven_v3'];

const voiceName = (id) => voices.value.find((v) => v.voice_id === id)?.name || null;

async function load() {
    try {
        const { data } = await api.get('/admin/elevenlabs');
        configured.value = !!data.data.configured;
        masked.value = data.data.api_key_masked;
        form.value = {
            voice_ibu: data.data.voice_ibu || '',
            voice_anak: data.data.voice_anak || '',
            voice_narator: data.data.voice_narator || '',
            model_id: data.data.model_id || 'eleven_multilingual_v2',
        };
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}
onMounted(load);

async function loadVoices() {
    loadingVoices.value = true;
    try {
        const { data } = await api.get('/admin/elevenlabs/voices');
        voices.value = data.data || [];
        toast.success(`${voices.value.length} suara dimuat dari akun ElevenLabs.`);
    } catch (e) {
        toast.error(errorMessage(e, 'Gagal memuat daftar suara. Pastikan API key sudah disimpan & benar.'));
    } finally {
        loadingVoices.value = false;
    }
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { ...form.value, remove_api_key: removeKey.value };
        if (apiKey.value.trim()) payload.api_key = apiKey.value.trim();
        const { data } = await api.put('/admin/elevenlabs', payload);
        configured.value = !!data.data.configured;
        masked.value = data.data.api_key_masked;
        apiKey.value = '';
        removeKey.value = false;
        toast.success('Pengaturan ElevenLabs tersimpan.');
    } catch (e) {
        errors.value = fieldErrors(e);
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
const keyStatus = computed(() => (configured.value ? `Sudah diatur (${masked.value || '••••'})` : 'Belum diatur'));
</script>

<template>
    <div v-if="loading" class="skeleton h-96 rounded-3xl"></div>
    <form v-else class="card space-y-6 p-6" @submit.prevent="save">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-display text-lg font-bold">ElevenLabs — sumber suara video</h3>
                <p class="text-sm text-muted">Dipakai saat pengguna memilih sumber suara <b>ElevenLabs</b> di form Buat Video. Visual tetap dibuat Muse lewat Google Flow; dialog/narasi dibuat ElevenLabs dari Voice ID di bawah.</p>
            </div>
            <Badge :color="configured ? 'emerald' : 'amber'" :icon="configured ? 'badge-check' : 'triangle-alert'">{{ keyStatus }}</Badge>
        </div>

        <div>
            <label class="label" for="el-key">API key ElevenLabs</label>
            <input id="el-key" v-model="apiKey" type="password" class="input" autocomplete="new-password" placeholder="sk_… (kosongkan untuk mempertahankan key lama)" />
            <p class="help">Dapatkan dari elevenlabs.io → Profile → API Keys. Key disimpan terenkripsi di server dan tidak pernah ditampilkan lagi secara utuh.</p>
            <p v-if="errors.api_key" class="field-error">{{ errors.api_key }}</p>
            <label v-if="configured" class="mt-2 flex cursor-pointer items-center gap-2 text-sm text-rose-600 dark:text-rose-400">
                <input v-model="removeKey" type="checkbox" class="checkbox" /> Hapus API key (video bersuara ElevenLabs tidak bisa dibuat sampai key diisi lagi)
            </label>
        </div>

        <div class="rounded-2xl border-2 border-line p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-bold">Voice ID per peran</p>
                <button type="button" class="btn btn-secondary btn-sm" :disabled="loadingVoices" @click="loadVoices">
                    <Spinner v-if="loadingVoices" class="h-4 w-4" /><Icon v-else name="refresh-cw" class="h-4 w-4" /> Muat daftar suara dari ElevenLabs
                </button>
            </div>
            <p class="mt-1 text-xs text-muted">Tombol di atas sekaligus mengetes API key. Setelah daftar dimuat, ketik nama suara di field untuk memilih Voice ID-nya. Pilih suara yang mendukung Bahasa Indonesia (model multilingual).</p>
            <datalist id="el-voices">
                <option v-for="v in voices" :key="v.voice_id" :value="v.voice_id">{{ v.name }}<span v-if="v.category"> · {{ v.category }}</span></option>
            </datalist>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="el-ibu">Suara Ibu</label>
                    <input id="el-ibu" v-model="form.voice_ibu" class="input" list="el-voices" placeholder="Voice ID Ibu" />
                    <p v-if="voiceName(form.voice_ibu)" class="help">{{ voiceName(form.voice_ibu) }}</p>
                    <p v-if="errors.voice_ibu" class="field-error">{{ errors.voice_ibu }}</p>
                </div>
                <div>
                    <label class="label" for="el-anak">Suara Anak</label>
                    <input id="el-anak" v-model="form.voice_anak" class="input" list="el-voices" placeholder="Voice ID Anak" />
                    <p v-if="voiceName(form.voice_anak)" class="help">{{ voiceName(form.voice_anak) }}</p>
                    <p v-if="errors.voice_anak" class="field-error">{{ errors.voice_anak }}</p>
                </div>
                <div>
                    <label class="label" for="el-narator">Suara Narator</label>
                    <input id="el-narator" v-model="form.voice_narator" class="input" list="el-voices" placeholder="Voice ID Narator" />
                    <p v-if="voiceName(form.voice_narator)" class="help">{{ voiceName(form.voice_narator) }}</p>
                    <p v-if="errors.voice_narator" class="field-error">{{ errors.voice_narator }}</p>
                </div>
            </div>
        </div>

        <div>
            <label class="label" for="el-model">Model ElevenLabs</label>
            <input id="el-model" v-model="form.model_id" class="input" list="el-models" placeholder="eleven_multilingual_v2" />
            <datalist id="el-models">
                <option v-for="m in MODEL_OPTIONS" :key="m" :value="m"></option>
            </datalist>
            <p class="help"><code>eleven_multilingual_v2</code> mendukung Bahasa Indonesia dan paling stabil untuk narasi. <code>eleven_flash_v2_5</code> lebih cepat &amp; murah.</p>
            <p v-if="errors.model_id" class="field-error">{{ errors.model_id }}</p>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary" :disabled="saving"><Spinner v-if="saving" class="h-4 w-4" /><Icon v-else name="save" class="h-4 w-4" /> Simpan pengaturan ElevenLabs</button>
        </div>
    </form>
</template>
