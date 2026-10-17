<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import ConfirmHost from '@/components/ui/ConfirmHost.vue';
import ToastHost from '@/components/ui/ToastHost.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const route = useRoute();
const guest = computed(() => route.meta.guest || !auth.isLoggedIn);
</script>

<template>
    <AuthLayout v-if="guest">
        <RouterView />
    </AuthLayout>
    <AppLayout v-else>
        <RouterView v-slot="{ Component, route: r }">
            <Transition name="page" mode="out-in">
                <component :is="Component" :key="r.path" />
            </Transition>
        </RouterView>
    </AppLayout>
    <ToastHost />
    <ConfirmHost />
</template>
