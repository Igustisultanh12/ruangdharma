<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Template & Ide</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Pilih tema, lalu pakai langsung di Generate.</p>
      </div>
    </div>

    <div class="card mb-4">
      <div class="grid sm:grid-cols-2 gap-3">
        <input v-model="search" class="input" placeholder="Cari template (misal: karma, canang...)" />
        <select v-model="category" class="input">
          <option value="">Semua Kategori</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>
    </div>

    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="t in filtered" :key="t.keyword" class="card card-hover flex flex-col">
        <div class="flex items-start justify-between gap-2">
          <h2 class="font-bold text-slate-900 dark:text-white">{{ t.keyword }}</h2>
          <Badge variant="violet">{{ t.category }}</Badge>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5 flex-1">{{ t.description }}</p>
        <div class="flex gap-1.5 mt-3 flex-wrap">
          <Badge variant="slate">{{ t.durasi }} detik</Badge>
          <Badge :variant="t.model === 'narasi' ? 'violet' : t.model === 'ngobrol' ? 'warning' : 'info'">{{ modelLabel(t.model) }}</Badge>
        </div>
        <p class="text-xs text-slate-400 mt-2">Ide naskah: {{ t.idea }}</p>
        <button @click="useTemplate(t)" class="btn-primary w-full mt-4">Pakai Template</button>
      </div>
    </div>
    <EmptyState v-if="!filtered.length" title="Template tidak ditemukan" description="Coba kata kunci atau kategori lain." />
  </div>
</template>
<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Badge from '../components/Badge.vue';
import EmptyState from '../components/EmptyState.vue';

const router = useRouter();
const search = ref('');
const category = ref('');

const TEMPLATES = [
  { keyword: 'Rwa Bhineda', category: 'Filosofi', durasi: 60, model: 'ngobrol', description: 'Keseimbangan baik-buruk lewat obrolan Anak & Ibu.', idea: 'Anak bertanya kenapa ada siang dan malam.' },
  { keyword: 'Canang Sari', category: 'Upacara', durasi: 60, model: 'narasi', description: 'Makna dan unsur canang sari dalam persembahan harian.', idea: 'Narasi + visual membuat canang langkah demi langkah.' },
  { keyword: 'Tri Sandhya', category: 'Doa Harian', durasi: 60, model: 'narasi', description: 'Tiga waktu sembahyang dan artinya untuk anak.', idea: 'Pagi, siang, sore — tiga momen hening.' },
  { keyword: 'Karma', category: 'Filosofi', durasi: 60, model: 'ngobrol', description: 'Hukum sebab-akibat dengan analogi menanam benih.', idea: 'Benih baik tumbuh jadi kebaikan.' },
  { keyword: 'Bhatara Sri', category: 'Dewi & Cerita', durasi: 90, model: 'gabungan', description: 'Dewi padi dan rasa syukur atas rezeki.', idea: 'Dari sawah hingga meja makan.' },
  { keyword: 'Tri Hita Karana', category: 'Filosofi', durasi: 90, model: 'gabungan', description: 'Tiga hubungan harmonis: Tuhan, sesama, alam.', idea: 'Satu hari menerapkan tiga harmoni.' },
  { keyword: 'Ahimsa', category: 'Etika', durasi: 60, model: 'ngobrol', description: 'Tidak menyakiti makhluk hidup, mulai dari hal kecil.', idea: 'Anak menolong kucing tanpa menyakitinya.' },
  { keyword: 'Nyepi', category: 'Hari Raya', durasi: 90, model: 'narasi', description: 'Makna Catur Brata Penyepian untuk keluarga.', idea: 'Sehari hening, setahun damai.' },
  { keyword: 'Ngayah', category: 'Budaya', durasi: 60, model: 'gabungan', description: 'Gotong royong tulus tanpa pamrih di banjar.', idea: 'Bersama menyiapkan upacara desa.' },
  { keyword: 'Saraswati', category: 'Dewi & Cerita', durasi: 60, model: 'narasi', description: 'Dewi ilmu pengetahuan dan cinta belajar.', idea: 'Buku, pena, dan rasa ingin tahu.' },
  { keyword: 'Galungan', category: 'Hari Raya', durasi: 90, model: 'gabungan', description: 'Kemenangan dharma atas adharma.', idea: 'Penjor, dharma, dan keluarga berkumpul.' },
  { keyword: 'Melasti', category: 'Upacara', durasi: 90, model: 'narasi', description: 'Penyucian diri dan sarana upacara ke laut.', idea: 'Perjalanan suci menuju pantai.' },
];
const categories = computed(() => [...new Set(TEMPLATES.map((t) => t.category))]);
const filtered = computed(() => TEMPLATES.filter((t) =>
  (!category.value || t.category === category.value) &&
  (!search.value || (t.keyword + ' ' + t.description + ' ' + t.idea).toLowerCase().includes(search.value.toLowerCase()))
));
function modelLabel(m) { return { narasi: 'Narasi', ngobrol: 'Ngobrol', gabungan: 'Gabungan' }[m] || m; }
function useTemplate(t) {
  router.push({ name: 'generate', query: { keyword: t.keyword, durasi: t.durasi, model: t.model } });
}
</script>
