<script setup>
import { computed, ref } from 'vue';

// Trim timeline ala CapCut: satu bar dengan 2 handle drag (kiri/kanan).
// Props: duration (total detik), start (detik), end (detik, null = habis/duration).
// Emit: update:start (number), update:end (number | null — null kalau end == duration).
const props = defineProps({
  duration: { type: Number, required: true },
  start: { type: Number, default: 0 },
  end: { type: [Number, null], default: null },
});
const emit = defineEmits(['update:start', 'update:end']);

const MIN_SEG = 0.5; // segmen minimal 0.5 detik
const trackRef = ref(null);
const dragging = ref(null); // 'start' | 'end' | null

const total = computed(() => Math.max(0.5, Number(props.duration) || 0.5));
const effEnd = computed(() => (props.end == null ? total.value : Math.min(total.value, Math.max(0, Number(props.end)))));
const effStart = computed(() => Math.min(Math.max(0, Number(props.start) || 0), Math.max(0, effEnd.value - MIN_SEG)));
const segLen = computed(() => Math.max(0, effEnd.value - effStart.value));

function fmtMMSS(s) {
  if (s == null || isNaN(s)) return '0:00';
  s = Math.max(0, Math.round(Number(s)));
  return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
}
function pct(v) {
  return (Math.min(Math.max(v, 0), total.value) / total.value) * 100;
}
function round01(v) {
  return Math.round(v * 10) / 10;
}

function posToSeconds(clientX) {
  const el = trackRef.value;
  if (!el) return 0;
  const rect = el.getBoundingClientRect();
  const ratio = (clientX - rect.left) / rect.width;
  return round01(Math.min(Math.max(ratio, 0), 1) * total.value);
}

function onHandleDown(e, which) {
  e.preventDefault();
  dragging.value = which;
  try { e.target.setPointerCapture(e.pointerId); } catch { /* abaikan */ }
}
function onHandleMove(e) {
  if (!dragging.value) return;
  const sec = posToSeconds(e.clientX);
  if (dragging.value === 'start') {
    const v = Math.min(Math.max(sec, 0), effEnd.value - MIN_SEG);
    emit('update:start', round01(Math.max(0, v)));
  } else {
    const v = Math.min(Math.max(sec, effStart.value + MIN_SEG), total.value);
    emit('update:end', v >= total.value ? null : round01(v));
  }
}
function onHandleUp() {
  dragging.value = null;
}

function onTrackClick(e) {
  // Klik di area timeline (bukan handle): pindahkan handle terdekat.
  if (dragging.value) return;
  const sec = posToSeconds(e.clientX);
  const dStart = Math.abs(sec - effStart.value);
  const dEnd = Math.abs(sec - effEnd.value);
  if (dStart <= dEnd) {
    emit('update:start', round01(Math.min(Math.max(sec, 0), effEnd.value - MIN_SEG)));
  } else {
    emit('update:end', sec >= total.value ? null : round01(Math.min(Math.max(sec, effStart.value + MIN_SEG), total.value)));
  }
}

function onStartNum(e) {
  const v = Number(e.target.value);
  if (isNaN(v)) return;
  emit('update:start', round01(Math.min(Math.max(v, 0), effEnd.value - MIN_SEG)));
}
function onEndNum(e) {
  const v = Number(e.target.value);
  if (isNaN(v)) return;
  emit('update:end', v >= total.value ? null : round01(Math.min(Math.max(v, effStart.value + MIN_SEG), total.value)));
}
</script>

<template>
  <div class="space-y-1">
    <!-- Label durasi segmen terpilih ala CapCut -->
    <div class="flex justify-between items-baseline text-xs">
      <span class="font-semibold">Trim: {{ segLen.toFixed(1) }}s</span>
      <span class="text-muted">{{ fmtMMSS(effStart) }} — {{ fmtMMSS(effEnd) }} / {{ fmtMMSS(total) }}</span>
    </div>

    <!-- Timeline -->
    <div
      ref="trackRef"
      class="relative h-14 rounded-lg select-none overflow-visible"
      style="background:#1f2937; touch-action:none;"
      @click="onTrackClick"
    >
      <!-- Isi timeline: strip waveform berulang (pola garis vertikal) -->
      <div
        class="absolute inset-0 rounded-lg opacity-60"
        style="background-image:repeating-linear-gradient(90deg, rgba(255,255,255,0.35) 0 2px, transparent 2px 8px); background-size:100% 60%; background-repeat:no-repeat; background-position:center;"
      ></div>

      <!-- Area luar kiri (terpotong) — redup -->
      <div
        class="absolute top-0 bottom-0 left-0 rounded-l-lg bg-black/60"
        :style="{ width: pct(effStart) + '%' }"
      ></div>
      <!-- Area luar kanan (terpotong) — redup -->
      <div
        class="absolute top-0 bottom-0 right-0 rounded-r-lg bg-black/60"
        :style="{ width: (100 - pct(effEnd)) + '%' }"
      ></div>

      <!-- Area terpilih: terang + border hijau -->
      <div
        class="absolute top-0 bottom-0 border-y-2 border-emerald-400 bg-emerald-400/15"
        :style="{ left: pct(effStart) + '%', width: pct(effEnd) - pct(effStart) + '%' }"
      ></div>

      <!-- Handle KIRI -->
      <div
        class="absolute top-0 bottom-0 z-10 flex items-center justify-center rounded-l-md bg-white shadow"
        style="width:14px; cursor:ew-resize; touch-action:none; transform:translateX(-50%);"
        :style="{ left: pct(effStart) + '%' }"
        @pointerdown.stop="(e) => onHandleDown(e, 'start')"
        @pointermove="onHandleMove"
        @pointerup="onHandleUp"
        @pointercancel="onHandleUp"
        title="Tarik untuk mengatur awal"
      >
        <span class="text-gray-700 text-[10px] font-bold leading-none select-none">||</span>
      </div>

      <!-- Handle KANAN -->
      <div
        class="absolute top-0 bottom-0 z-10 flex items-center justify-center rounded-r-md bg-white shadow"
        style="width:14px; cursor:ew-resize; touch-action:none; transform:translateX(-50%);"
        :style="{ left: pct(effEnd) + '%' }"
        @pointerdown.stop="(e) => onHandleDown(e, 'end')"
        @pointermove="onHandleMove"
        @pointerup="onHandleUp"
        @pointercancel="onHandleUp"
        title="Tarik untuk mengatur akhir"
      >
        <span class="text-gray-700 text-[10px] font-bold leading-none select-none">||</span>
      </div>
    </div>

    <p class="text-xs text-muted">Tarik handle kiri/kanan untuk potong. Klik di timeline untuk pindahkan handle terdekat.</p>

    <!-- Angka kecil tersinkron (sekunder, bukan interaksi utama) -->
    <div class="grid grid-cols-2 gap-2">
      <label class="text-xs text-muted">Mulai (detik)<input :value="effStart" type="number" min="0" :max="total" step="0.1" class="input mt-1" @change="onStartNum" /></label>
      <label class="text-xs text-muted">Sampai (detik, = total berarti habis)<input :value="effEnd" type="number" min="0" :max="total" step="0.1" class="input mt-1" @change="onEndNum" /></label>
    </div>
  </div>
</template>
