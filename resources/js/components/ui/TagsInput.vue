<script setup>
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Ketik lalu Enter' },
    max: { type: Number, default: 50 },
    id: { type: String, default: undefined },
});
const emit = defineEmits(['update:modelValue']);
const text = ref('');
function add() {
    const parts = text.value.split(',').map((t) => t.trim()).filter(Boolean);
    const next = [...props.modelValue];
    parts.forEach((p) => {
        if (!next.some((x) => x.toLowerCase() === p.toLowerCase()) && next.length < props.max) next.push(p);
    });
    emit('update:modelValue', next);
    text.value = '';
}
function remove(i) {
    const next = [...props.modelValue];
    next.splice(i, 1);
    emit('update:modelValue', next);
}
function onKey(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        add();
    } else if (e.key === 'Backspace' && !text.value && props.modelValue.length) remove(props.modelValue.length - 1);
}
</script>

<template>
    <div class="flex min-h-12 flex-wrap items-center gap-1.5 rounded-2xl border-2 border-line bg-card px-2.5 py-2 transition focus-within:border-violet-500 focus-within:ring-4 focus-within:ring-violet-500/15">
        <span v-for="(t, i) in modelValue" :key="t" class="animate-pop-in inline-flex items-center gap-1 rounded-full border-2 border-ink bg-yellow-300 py-0.5 pr-1 pl-2.5 text-xs font-bold text-zinc-900">
            {{ t }}
            <button type="button" class="rounded-full p-0.5 hover:bg-black/10" :aria-label="`Hapus ${t}`" @click="remove(i)"><Icon name="x" class="h-3 w-3" /></button>
        </span>
        <input :id="id" v-model="text" class="min-w-32 flex-1 bg-transparent px-1 py-1 text-sm outline-none placeholder:text-muted/70" :placeholder="modelValue.length >= max ? `Maksimal ${max}` : placeholder" :disabled="modelValue.length >= max" @keydown="onKey" @blur="text && add()" />
    </div>
</template>
