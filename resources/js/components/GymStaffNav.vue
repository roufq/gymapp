<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    Calendar,
    DoorOpen,
    Dumbbell,
    LogOut,
    Sparkles,
    User,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';

interface Props {
    activeTab?: 'executive' | 'gate' | 'members' | 'classes' | 'trainer' | 'dashboard';
    branchName?: string;
    syncedCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    activeTab: 'gate',
    branchName: 'Cabang Utama - Yogyakarta',
    syncedCount: 1420,
});

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user);
const isAdmin = computed(() => authUser.value?.role === 'admin');
const isTrainer = computed(() => authUser.value?.role === 'trainer' || authUser.value?.role === 'admin');
</script>

<template>
    <header class="h-16 border-b border-slate-200 bg-white px-4 md:px-6 flex items-center justify-between sticky top-0 z-30 shadow-xs">
        <!-- Left: Brand Logo & Terminal Branch Location -->
        <div class="flex items-center gap-4">
            <Link href="/" class="flex items-center gap-2.5 group">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-emerald-500 flex items-center justify-center text-white font-black text-sm shadow-md shadow-indigo-600/20 group-hover:scale-105 transition-transform">
                    EG
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-black text-slate-900 tracking-tight">EGMS</span>
                        <span class="rounded bg-indigo-50 px-1.5 py-0.2 text-[9px] font-bold text-indigo-700 border border-indigo-200">
                            PRO
                        </span>
                    </div>
                    <div class="text-[11px] font-semibold text-slate-500 truncate max-w-[180px]">
                        {{ props.branchName }}
                    </div>
                </div>
            </Link>

            <div class="h-6 w-px bg-slate-200 hidden lg:block" />

            <!-- Online Sync Pill -->
            <div class="hidden xl:flex items-center gap-2 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-[11px] text-emerald-800 font-medium">
                <span class="h-2 w-2 rounded-full bg-[#10B981] animate-pulse" />
                <span>ONLINE • Ready ({{ props.syncedCount.toLocaleString('id-ID') }} Synced)</span>
            </div>
        </div>

        <!-- Center: Uniform Navigation Tabs for Staff/Admin -->
        <nav class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/80">
            <!-- 1. Executive BI (Owner / Admin) -->
            <Link
                v-if="isAdmin"
                href="/executive"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                    props.activeTab === 'executive'
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                ]"
            >
                <BarChart3 class="h-3.5 w-3.5" />
                <span>👑 BI Owner</span>
            </Link>

            <!-- 2. Kasir & Gate Scanner -->
            <Link
                href="/gate"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                    props.activeTab === 'gate'
                        ? 'bg-[#0F172A] text-white shadow-sm'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                ]"
            >
                <DoorOpen class="h-3.5 w-3.5" />
                <span>🖥️ Kasir & Gate</span>
            </Link>

            <!-- 3. Members Directory -->
            <Link
                href="/members"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                    props.activeTab === 'members'
                        ? 'bg-[#0F172A] text-white shadow-sm'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                ]"
            >
                <Users class="h-3.5 w-3.5" />
                <span>👥 Members</span>
            </Link>

            <!-- 4. Kelas Studio -->
            <Link
                href="/classes"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                    props.activeTab === 'classes'
                        ? 'bg-[#0F172A] text-white shadow-sm'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                ]"
            >
                <Calendar class="h-3.5 w-3.5" />
                <span>📅 Kelas Studio</span>
            </Link>

            <!-- 5. Portal PT -->
            <Link
                v-if="isTrainer"
                href="/trainer"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                    props.activeTab === 'trainer'
                        ? 'bg-[#F97316] text-white shadow-sm'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                ]"
            >
                <Dumbbell class="h-3.5 w-3.5" />
                <span>💪 Portal PT</span>
            </Link>

            <!-- 6. Overview / Dashboard -->
            <Link
                href="/dashboard"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                    props.activeTab === 'dashboard'
                        ? 'bg-[#0F172A] text-white shadow-sm'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                ]"
            >
                <Activity class="h-3.5 w-3.5" />
                <span>📊 Overview</span>
            </Link>
        </nav>

        <!-- Right: Logged User & Logout Button -->
        <div class="flex items-center gap-2.5">
            <div class="text-right hidden sm:block">
                <div class="text-xs font-extrabold text-slate-800 leading-tight">
                    {{ authUser?.name || 'Staff Gym' }}
                </div>
                <div class="text-[10px] text-slate-400 font-mono capitalize">
                    Role: {{ authUser?.role || 'Staff' }}
                </div>
            </div>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 text-xs font-semibold transition cursor-pointer"
                title="Keluar dari Sistem"
            >
                <LogOut class="h-4 w-4" />
            </Link>
        </div>
    </header>
</template>
