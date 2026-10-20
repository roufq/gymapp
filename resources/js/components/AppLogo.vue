<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-sm"
    >
        <AppLogoIcon class="size-4.5 stroke-white" />
    </div>
    <div class="ml-1.5 grid flex-1 text-left text-sm">
        <span class="truncate leading-tight font-black text-slate-900 dark:text-white">EGMS Gym</span>
        <span class="text-[10px] text-slate-400 font-mono leading-none">Management Suite</span>
    </div>
</template>

