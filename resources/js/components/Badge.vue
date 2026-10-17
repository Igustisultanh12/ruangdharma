<template>
  <span class="badge" :class="cls"><slot /></span>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ variant: { type: String, default: 'slate' } });
const cls = computed(() => ({
  success: 'badge-success', warning: 'badge-warning', info: 'badge-info',
  danger: 'badge-danger', violet: 'badge-violet', slate: 'badge-slate',
}[props.variant] || 'badge-slate'));
</script>
