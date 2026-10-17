<template>
  <div>
    <h1 class="text-2xl font-bold mb-3">Generate Video — Ruang Dharma</h1>
    <div class="card mb-4">
      <label class="block mb-1 font-medium">Kata kunci</label>
      <input v-model="keyword" class="input" placeholder="misal: Rwa Bhineda" />
      <div class="flex flex-wrap gap-2 mt-2">
        <button v-for="c in chips" :key="c" @click="keyword=c" class="px-3 py-1 bg-violet-100 rounded-full text-sm">{{ c }}</button>
      </div>
      <div class="grid md:grid-cols-2 gap-3 mt-4">
        <div><label class="block mb-1">Durasi</label><select v-model="durasi" class="input"><option :value="30">30 detik</option><option :value="60">60 detik</option><option :value="90">90 detik</option></select></div>
        <div><label class="block mb-1">Model</label><select v-model="model" class="input"><option value="narasi">Narasi / Reels</option><option value="ngobrol">Ngobrol (Anak–Ibu)</option><option value="gabungan">Gabungan</option></select></div>
      </div>
      <p class="text-sm mt-2">Sisa kuota: <b>{{ store.quota.remaining }} / {{ store.quota.limit }}</b></p>
      <button @click="doGenerate" :disabled="store.loading" class="btn-primary mt-3 w-full">{{ store.loading ? 'Sedang generate…' : '✨ Generate' }}</button>
      <div v-if="store.loading" class="mt-4">
        <p class="font-medium">⏳ {{ steps[store.step] }}… ({{ store.step+1 }}/5)</p>
        <div class="h-3 bg-slate-200 rounded mt-2"><div class="h-3 bg-violet-600 rounded" :style="{width: ((store.step+1)/5*100)+'%'}"></div></div>
      </div>
      <p v-if="store.error" class="text-red-600 mt-2">{{ store.error }}</p>
    </div>
    <div v-if="store.result" class="space-y-4">
      <div class="card">
        <h2 class="font-bold text-lg">📺 Pratinjau Video 9:16 — {{ store.result.result.judul }}</h2>
        <div class="aspect-[9/16] max-w-[320px] bg-slate-900 text-white rounded-xl p-4 flex flex-col justify-between mx-auto my-3">
          <div><p class="text-sm opacity-70">BAGIAN {{ current+1 }} / {{ store.result.result.naskah.length }}</p><p class="font-bold">{{ store.result.result.naskah[current]?.speaker }}</p><p>{{ store.result.result.naskah[current]?.teks }}</p></div>
          <div class="flex gap-2"><button @click="current=Math.max(0,current-1)" class="bg-white text-black px-2 py-1 rounded text-sm">‹ Prev</button><button @click="current=Math.min(store.result.result.naskah.length-1,current+1)" class="bg-white text-black px-2 py-1 rounded text-sm">Next ›</button></div>
        </div>
        <p class="text-sm text-slate-500">Pratinjau frame + subtitle. Video AI penuh dibuat lewat prompt di bawah / via Muse.</p>
      </div>
      <div class="card"><h2 class="font-bold">📝 Naskah ({{ store.result.result.naskah.length }} bagian)</h2>
        <div v-for="n in store.result.result.naskah" :key="n.bagian" class="border-b py-2"><b>Bagian {{ n.bagian }} — {{ n.speaker }} ({{ n.suara }})</b><p>{{ n.teks }}</p><p class="text-xs text-slate-500">Prompt: {{ store.result.result.prompts[n.bagian-1] }}</p></div>
      </div>
      <div class="card"><h2 class="font-bold">💬 Caption Instagram</h2><pre class="whitespace-pre-wrap text-sm">{{ store.result.result.caption }}</pre><button @click="copyCaption" class="btn-primary mt-2">Salin Caption</button></div>
      <div class="card"><button @click="downloadTxt" class="btn-primary">⬇ Unduh Paket Produksi (.txt)</button></div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'; import { useGenerateStore } from '../stores/generate.js';
const store = useGenerateStore(); const keyword=ref('Rwa Bhineda'); const durasi=ref(60); const model=ref('narasi'); const current=ref(0);
const chips=['Rwa Bhineda','Canang Sari','Tri Sandhya','Karma','Tri Hita Karana','Bhatara Sri'];
const steps=['Menyiapkan naskah','Menulis dialog/narasi','Membuat prompt visual','Menyusun caption','Menyiapkan video'];
onMounted(()=> store.fetchQuota());
async function doGenerate(){ current.value=0; try{ await store.generate({keyword:keyword.value, durasi:durasi.value, model:model.value}); }catch(e){} }
function copyCaption(){ navigator.clipboard.writeText(store.result.result.caption); alert('Caption tersalin!'); }
function downloadTxt(){ const r=store.result.result; let t=`RUANG DHARMA STUDIO\nJudul: ${r.judul}\nKata kunci: ${r.keyword} | Durasi: ${r.durasi} | Model: ${r.model}\n\n=== NASKAH ===\n`; r.naskah.forEach(n=>{ t+=`\nBagian ${n.bagian} — ${n.speaker} (${n.suara})\n${n.teks}\nPrompt: ${r.prompts[n.bagian-1]}\n`; }); t+=`\n=== CAPTION ===\n${r.caption}\n`; const blob=new Blob([t],{type:'text/plain'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`ruang-dharma-${r.keyword.toLowerCase().replace(/\s+/g,'-')}.txt`; a.click(); }
</script>
