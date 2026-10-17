<template>
  <div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">Media Library</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Kumpulan visual & referensi untuk dipakai di Generate.</p>

    <div class="card mb-4">
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <input v-model="search" class="input" placeholder="Cari judul / tag..." />
        <input v-model="newTitle" class="input" placeholder="Judul media baru" />
        <input v-model="newTags" class="input" placeholder="Tag (pisahkan koma)" />
        <label class="btn-secondary cursor-pointer text-center">
          Upload Gambar
          <input type="file" accept="image/*" class="hidden" @change="onUpload" />
        </label>
      </div>
      <p class="text-xs text-slate-400 mt-2">Upload disimpan di perangkat ini (localStorage, maks 2MB per gambar). Media contoh memakai gambar placeholder.</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="m in filtered" :key="m.id" class="card !p-0 overflow-hidden card-hover">
        <img :src="m.url" :alt="m.title" class="w-full h-40 object-cover" loading="lazy" />
        <div class="p-4">
          <h3 class="font-semibold text-sm text-slate-900 dark:text-white truncate">{{ m.title }}</h3>
          <div class="flex gap-1 flex-wrap mt-1.5">
            <Badge v-for="tag in m.tags" :key="tag" variant="slate">{{ tag }}</Badge>
          </div>
          <div class="flex gap-2 mt-3">
            <button @click="useInGenerate(m)" class="btn-primary btn-sm flex-1">Pakai di Generate</button>
            <button @click="removeItem(m)" class="btn-danger btn-sm">Hapus</button>
          </div>
        </div>
      </div>
    </div>
    <EmptyState v-if="!filtered.length" title="Media tidak ditemukan" description="Upload media baru atau ubah kata kunci pencarian." />
  </div>
</template>
<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import Badge from '../components/Badge.vue';
import EmptyState from '../components/EmptyState.vue';
import { getMediaItems, saveMediaItems } from '../services/preferences.js';

const router = useRouter();
const search = ref('');
const newTitle = ref('');
const newTags = ref('');
const items = ref(getMediaItems());

if (!items.value.length) {
  const seeds = [
    { title: 'Pura saat matahari terbit', tags: ['pura', 'pagi'] },
    { title: 'Canang sari di atas alas', tags: ['canang', 'persembahan'] },
    { title: 'Anak Bali tersenyum', tags: ['anak', 'karakter'] },
    { title: 'Sawah hijau berundak', tags: ['sawah', 'alam'] },
    { title: 'Penjor Galungan', tags: ['galungan', 'penjor'] },
    { title: 'Lontar dan dupa', tags: ['lontar', 'doa'] },
  ];
  items.value = seeds.map((s, i) => ({ id: Date.now() + i, title: s.title, tags: s.tags, url: `https://picsum.photos/seed/ruangdharma-${i}/480/320` }));
  saveMediaItems(items.value);
}

const filtered = computed(() => items.value.filter((m) => {
  const q = search.value.toLowerCase();
  return !q || m.title.toLowerCase().includes(q) || m.tags.some((t) => t.toLowerCase().includes(q));
}));

function onUpload(e) {
  const f = e.target.files?.[0];
  if (!f) return;
  if (f.size > 2 * 1024 * 1024) {
    Swal.fire({ icon: 'error', title: 'File terlalu besar', text: 'Maksimal 2MB per gambar.' });
    return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    items.value.unshift({ id: Date.now(), title: newTitle.value.trim() || f.name, tags: newTags.value.split(',').map((t) => t.trim()).filter(Boolean), url: reader.result });
    saveMediaItems(items.value);
    newTitle.value = '';
    newTags.value = '';
    Swal.fire({ icon: 'success', title: 'Media diunggah', timer: 1200, showConfirmButton: false });
  };
  reader.readAsDataURL(f);
  e.target.value = '';
}
function useInGenerate(m) {
  router.push({ name: 'generate', query: { keyword: m.title, media: m.title } });
}
async function removeItem(m) {
  const res = await Swal.fire({ title: 'Hapus media?', text: m.title, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  items.value = items.value.filter((x) => x.id !== m.id);
  saveMediaItems(items.value);
}
</script>
