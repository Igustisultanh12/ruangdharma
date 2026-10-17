<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import CommandPalette from '@/components/layout/CommandPalette.vue';
import Sidebar from '@/components/layout/Sidebar.vue';
import Topbar from '@/components/layout/Topbar.vue';
import { useRealtimeStore } from '@/stores/realtime.js';

const rt = useRealtimeStore();
onMounted(() => rt.start());
onBeforeUnmount(() => rt.stop());
</script>

<template>
    <div class="min-h-screen bg-surface">
        <Sidebar />
        <div class="lg:pl-72">
            <Topbar />
            <main class="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <slot />
            </main>
        </div>
        <CommandPalette />
    </div>
</template>
