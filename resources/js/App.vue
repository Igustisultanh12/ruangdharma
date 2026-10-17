<template>
  <div class="min-h-screen">
    <AppLayout v-if="isLoggedIn">
      <RouterView />
    </AppLayout>
    <div v-else class="min-h-screen bg-gradient-to-br from-violet-700 via-violet-600 to-amber-500 flex items-center justify-center px-4 py-10">
      <div class="w-full max-w-md">
        <RouterView />
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from './stores/auth.js';
import { useUiStore } from './stores/ui.js';
import AppLayout from './layouts/AppLayout.vue';

const auth = useAuthStore();
const ui = useUiStore();
const route = useRoute();
auth.loadFromStorage();
const isLoggedIn = computed(() => !!auth.token && route.name !== 'login');
onMounted(() => ui.initDark());
</script>
