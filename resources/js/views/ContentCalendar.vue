<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Kalender Konten</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Jadwalkan ide publish per tanggal.</p>
      </div>
      <div class="flex gap-2">
        <button @click="shiftMonth(-1)" class="btn-secondary btn-sm">Bulan Sebelumnya</button>
        <button @click="shiftMonth(1)" class="btn-secondary btn-sm">Bulan Berikutnya</button>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
      <div class="card lg:col-span-2">
        <h2 class="font-bold text-slate-900 dark:text-white mb-3">{{ monthLabel }}</h2>
        <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
          <span v-for="d in ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']" :key="d">{{ d }}</span>
        </div>
        <div class="grid grid-cols-7 gap-1">
          <div v-for="cell in cells" :key="cell.key" @click="cell.date && selectDate(cell.date)" class="min-h-20 rounded-xl border p-1.5 text-left cursor-pointer" :class="cell.date === selected ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/30' : 'border-slate-200 dark:border-slate-700 hover:border-violet-300'">
            <span v-if="cell.date" class="text-xs font-semibold">{{ new Date(cell.date + 'T00:00:00').getDate() }}</span>
            <div v-if="cell.date" class="space-y-1 mt-1">
              <p v-for="s in itemsFor(cell.date).slice(0, 2)" :key="s.id" class="text-[10px] leading-tight bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 rounded px-1 py-0.5 truncate">{{ s.time }} {{ s.keyword }}</p>
              <p v-if="itemsFor(cell.date).length > 2" class="text-[10px] text-slate-400">+{{ itemsFor(cell.date).length - 2 }} lagi</p>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="card">
          <h2 class="font-bold text-slate-900 dark:text-white mb-3">Jadwalkan untuk {{ selectedLabel }}</h2>
          <div class="space-y-3">
            <div><label class="label">Kata kunci</label><input v-model="form.keyword" class="input" placeholder="misal: Canang Sari" /></div>
            <div class="grid grid-cols-2 gap-3">
              <div><label class="label">Durasi</label><select v-model.number="form.durasi" class="input"><option :value="30">30 dtk</option><option :value="60">60 dtk</option><option :value="90">90 dtk</option></select></div>
              <div><label class="label">Model</label><select v-model="form.model" class="input"><option value="narasi">Narasi</option><option value="ngobrol">Ngobrol</option><option value="gabungan">Gabungan</option></select></div>
            </div>
            <div><label class="label">Jam publish (publish_at)</label><input v-model="form.time" type="time" class="input" /></div>
            <button @click="addSchedule" class="btn-primary w-full" :disabled="!form.keyword.trim()">Tambah Jadwal</button>
          </div>
        </div>
        <div class="card">
          <h2 class="font-bold text-slate-900 dark:text-white mb-3">Jadwal {{ selectedLabel }}</h2>
          <div v-if="itemsFor(selected).length" class="space-y-2">
            <div v-for="s in itemsFor(selected)" :key="s.id" class="flex items-center justify-between gap-2 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2">
              <div><p class="text-sm font-medium">{{ s.keyword }}</p><p class="text-xs text-slate-500">{{ s.time }} • {{ s.durasi }} dtk • {{ s.model }}</p></div>
              <div class="flex gap-1.5">
                <button @click="generateNow(s)" class="btn-primary btn-sm">Generate</button>
                <button @click="removeSchedule(s)" class="btn-danger btn-sm">Hapus</button>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-slate-500 dark:text-slate-400">Belum ada jadwal pada tanggal ini.</p>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { getScheduledItems, saveScheduledItems } from '../services/preferences.js';

const router = useRouter();
const viewDate = ref(new Date());
const todayKey = new Date().toISOString().slice(0, 10);
const selected = ref(todayKey);
const form = ref({ keyword: '', durasi: 60, model: 'narasi', time: '07:00' });
const items = ref(getScheduledItems());

const monthLabel = computed(() => viewDate.value.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }));
const selectedLabel = computed(() => new Date(selected.value + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
const cells = computed(() => {
  const y = viewDate.value.getFullYear();
  const m = viewDate.value.getMonth();
  const first = new Date(y, m, 1);
  const startOffset = first.getDay();
  const daysInMonth = new Date(y, m + 1, 0).getDate();
  const arr = [];
  for (let i = 0; i < startOffset; i++) arr.push({ key: `blank-${i}`, date: null });
  for (let d = 1; d <= daysInMonth; d++) {
    const key = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    arr.push({ key, date: key });
  }
  return arr;
});
function itemsFor(date) {
  return items.value.filter((s) => s.date === date).sort((a, b) => a.time.localeCompare(b.time));
}
function selectDate(date) {
  selected.value = date;
}
function shiftMonth(delta) {
  const d = new Date(viewDate.value);
  d.setMonth(d.getMonth() + delta);
  viewDate.value = d;
}
function addSchedule() {
  items.value.push({ id: Date.now(), date: selected.value, keyword: form.value.keyword.trim(), durasi: form.value.durasi, model: form.value.model, time: form.value.time });
  saveScheduledItems(items.value);
  form.value.keyword = '';
  Swal.fire({ icon: 'success', title: 'Jadwal ditambahkan', timer: 1200, showConfirmButton: false });
}
function generateNow(s) {
  router.push({ name: 'generate', query: { keyword: s.keyword, durasi: s.durasi, model: s.model } });
}
async function removeSchedule(s) {
  const res = await Swal.fire({ title: 'Hapus jadwal?', text: s.keyword, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
  if (!res.isConfirmed) return;
  items.value = items.value.filter((x) => x.id !== s.id);
  saveScheduledItems(items.value);
}
</script>
