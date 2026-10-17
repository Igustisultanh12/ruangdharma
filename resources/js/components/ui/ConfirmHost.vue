<script setup>
import { computed } from 'vue';
import { closeConfirm, confirmState } from '@/lib/toast.js';
import Icon from './Icon.vue';
import Modal from './Modal.vue';

const o = computed(() => confirmState.opts);
const needsInput = computed(() => !!o.value.input);
const invalid = computed(() => needsInput.value && o.value.input.required && !confirmState.value.trim());
function ok() {
    if (invalid.value) return;
    closeConfirm(needsInput.value ? confirmState.value.trim() : true);
}
</script>

<template>
    <Modal :show="confirmState.open" size="sm" layer="top" @close="closeConfirm(false)">
        <div class="flex gap-4">
            <span :class="o.tone === 'danger' ? 'bg-rose-400' : 'bg-yellow-300'" class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border-2 border-ink text-zinc-950">
                <Icon :name="o.icon || (o.tone === 'danger' ? 'trash-2' : 'circle-question-mark')" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="font-display text-lg font-bold">{{ o.title || 'Yakin?' }}</h3>
                <p v-if="o.message" class="mt-1 text-sm text-muted">{{ o.message }}</p>
                <div v-if="needsInput" class="mt-4">
                    <label class="label" for="confirm-input">{{ o.input.label }}</label>
                    <textarea v-if="o.input.multiline" id="confirm-input" v-model="confirmState.value" v-autofocus rows="3" class="input" :placeholder="o.input.placeholder"></textarea>
                    <input v-else id="confirm-input" v-model="confirmState.value" v-autofocus class="input" :placeholder="o.input.placeholder" @keyup.enter="ok" />
                </div>
            </div>
        </div>
        <template #footer>
            <button type="button" class="btn btn-ghost" @click="closeConfirm(false)">{{ o.cancelText }}</button>
            <button type="button" :class="o.tone === 'danger' ? 'btn-danger' : 'btn-primary'" class="btn" :disabled="invalid" @click="ok">{{ o.confirmText }}</button>
        </template>
    </Modal>
</template>
