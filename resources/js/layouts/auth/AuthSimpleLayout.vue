<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-[#090D16] text-white p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col items-center gap-3">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="h-12 w-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-emerald-500 flex items-center justify-center text-white shadow-xl shadow-indigo-500/20"
                        >
                            <AppLogoIcon class="size-6 stroke-white" />
                        </div>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span class="text-lg font-black tracking-tight text-white">EGMS Gym</span>
                            <span class="rounded bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-500/30 uppercase tracking-wider font-mono">Suite</span>
                        </div>
                    </Link>
                    <div class="space-y-1 text-center">
                        <h1 class="text-xl font-black text-white tracking-tight">{{ title }}</h1>
                        <p class="text-center text-xs text-slate-400">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <div class="rounded-3xl bg-[#131B2E] border border-slate-800 p-6 shadow-2xl text-slate-100">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
