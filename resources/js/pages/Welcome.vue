<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    BarChart3,
    CheckCircle2,
    DoorOpen,
    Dumbbell,
    Lock,
    QrCode,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Users,
} from '@lucide/vue';
import { dashboard, login, register } from '@/routes';

const portals = [
    {
        title: 'Executive Owner BI Dashboard',
        roleBadge: 'Super Admin / Owner',
        badgeColor: 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
        borderColor: 'border-indigo-500/40 hover:border-indigo-400',
        route: '/executive',
        credentials: 'admin@gymapp.test',
        icon: BarChart3,
        accentColor: 'text-indigo-400',
        description: 'Bento Grid 1440x900 dengan Dual-Basis Accounting (Cash Inflow vs Accrual Earned Revenue), Peringatan Fraud Selisih Kasir, Heatmap 7x16 Jam, dan Rekapitulasi Payroll Komisi.',
        features: ['Cash Basis: Rp 184.5M vs Accrual: Rp 112.3M', '3 High-Priority Fraud & Audit Alerts', 'Heatmap Kepadatan Jam Sibuk 06:00 - 22:00', 'Payroll Staf & Commission Clawback']
    },
    {
        title: 'Front Desk & POS Workstation',
        roleBadge: 'Receptionist & Kasir',
        badgeColor: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
        borderColor: 'border-emerald-500/40 hover:border-emerald-400',
        route: '/gate',
        credentials: 'frontdesk@gymapp.test',
        icon: DoorOpen,
        accentColor: 'text-emerald-400',
        description: 'Desktop 1440x900 Clean Light Mode dengan Split-Screen 40:60. Live Gate Monitor WebSocket, verifikasi foto 1:1, auto-loker L-042, POS Cepat (F2/F8/F9), dan Kiosk QR Pintu Masuk.',
        features: ['Left 40%: Live Turnstile Gate Feed & Foto 1:1', 'Auto-Focused Loker: [ L-042 ] + PAR-Q / Denda', 'Right 60%: Point of Sale & Grid Produk 3 Kolom', 'Kiosk Display QR Code Check-in Pintu Gym']
    },
    {
        title: 'Personal Trainer Mobile PWA',
        roleBadge: 'Personal Trainer (PT)',
        badgeColor: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
        borderColor: 'border-amber-500/40 hover:border-amber-400',
        route: '/trainer',
        credentials: 'trainer@gymapp.test',
        icon: Dumbbell,
        accentColor: 'text-[#F97316]',
        description: 'Mobile PWA Dark Slate dengan 2 Layar Bersebelahan. Timeline coaching harian, status kehadiran fisik member di gerbang, dan modal klaim komisi sesi berbasis OTP 6-Digit anti-fraud.',
        features: ['Top Earnings & KPI: Rp 4.850.000 (Tier 2)', 'Today Timeline: Sesi Budi Santoso (Border Oranye)', 'Status Gate: Sudah Check-in di Gate 15:48', 'Anti-Fraud PIN OTP: [8][4][9]-[2][0][1]']
    },
    {
        title: 'Member Portal PWA',
        roleBadge: 'Gym Member (Pelanggan)',
        badgeColor: 'bg-lime-500/20 text-[#A3E635] border-lime-500/30',
        borderColor: 'border-[#A3E635]/40 hover:border-[#A3E635]',
        route: '/dashboard',
        credentials: 'member1@gymapp.test',
        icon: Smartphone,
        accentColor: 'text-[#A3E635]',
        description: 'Athletic Dark Mode (#09090B). Dilengkapi Pemindai QR Kamera untuk scan QR Pintu Gym, Dynamic TOTP QR Pass 60s, Crowd Meter kepadatan, Booking Kelas Studio, dan Pelacak InBody.',
        features: ['Scan Barcode/QR Gerbang Masuk (Alokasi Loker L-042)', 'Dynamic TOTP Pass 60s & PIN Sesi PT: 849-201', 'Live Crowd Meter Kepadatan 42% (Ideal)', 'Booking Kelas Studio & Grafik InBody 6 Bulan']
    }
];
</script>

<template>
    <Head title="Enterprise Gym Management System (EGMS)" />

    <div class="min-h-screen bg-[#090D16] text-slate-100 font-sans selection:bg-[#A3E635] selection:text-zinc-950 flex flex-col justify-between">
        <!-- Top Navigation Bar -->
        <header class="border-b border-slate-800/80 bg-[#0E1424]/90 backdrop-blur sticky top-0 z-40 px-6 py-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-emerald-500 flex items-center justify-center font-black text-white text-lg shadow-lg">
                        EG
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-base font-black text-white tracking-tight">EGMS</span>
                            <span class="rounded bg-indigo-500/20 px-2 py-0.5 text-[10px] font-bold text-indigo-400 border border-indigo-500/30 uppercase tracking-wider font-mono">
                                Enterprise Suite
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">Enterprise Gym Management System</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="dashboard()"
                        class="rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-600 text-white px-4 py-2 text-xs font-bold shadow-md shadow-indigo-600/30 transition flex items-center gap-1.5"
                    >
                        <span>Buka Dashboard</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 px-4 py-2 text-xs font-bold transition"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-xl bg-[#A3E635] hover:bg-lime-400 text-zinc-950 font-black px-4 py-2 text-xs transition shadow-md shadow-[#A3E635]/20"
                        >
                            Daftar Member
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="max-w-7xl mx-auto px-6 py-12 flex-1">
            <div class="text-center max-w-3xl mx-auto mb-12">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-800/80 border border-slate-700 px-3 py-1 text-xs text-slate-300 font-semibold mb-4">
                    <Sparkles class="h-3.5 w-3.5 text-[#A3E635]" />
                    <span>Multi-Branch Operational &amp; Financial Suite</span>
                </div>
                <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
                    Sistem Manajemen Gym Terpadu &amp; Berkecepatan Tinggi
                </h1>
                <p class="mt-4 text-sm sm:text-base text-slate-400 leading-relaxed">
                    Arsitektur terintegrasi mencakup 4 workstation dan portal khusus untuk masing-masing peran operasional pusat kebugaran. Pilih modul di bawah untuk langsung mencoba tampilan antarmuka.
                </p>
            </div>

            <!-- 4 Portal Gateway Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div
                    v-for="portal in portals"
                    :key="portal.title"
                    class="rounded-3xl bg-[#0E1424] border p-6 shadow-xl relative overflow-hidden flex flex-col justify-between transition-all duration-300 group hover:shadow-2xl"
                    :class="portal.borderColor"
                >
                    <div>
                        <!-- Header Card -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center shrink-0 shadow-inner" :class="portal.accentColor">
                                    <component :is="portal.icon" class="h-6 w-6" />
                                </div>
                                <div>
                                    <span class="rounded px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-wider border" :class="portal.badgeColor">
                                        {{ portal.roleBadge }}
                                    </span>
                                    <h3 class="text-lg font-black text-white tracking-tight mt-1">{{ portal.title }}</h3>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-xs text-slate-300 mt-4 leading-relaxed">
                            {{ portal.description }}
                        </p>

                        <!-- Key Features Checklist -->
                        <div class="mt-4 space-y-1.5 bg-slate-900/60 p-3.5 rounded-2xl border border-slate-800/80">
                            <div v-for="feat in portal.features" :key="feat" class="flex items-center gap-2 text-xs text-slate-300">
                                <CheckCircle2 class="h-3.5 w-3.5 text-emerald-400 shrink-0" />
                                <span>{{ feat }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="mt-6 pt-4 border-t border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-[11px] font-mono text-slate-400">
                            <span>Akun Demo: </span>
                            <strong class="text-slate-200">{{ portal.credentials }}</strong>
                            <span class="opacity-70"> (pass: password)</span>
                        </div>

                        <Link
                            :href="portal.route"
                            class="rounded-xl px-4 py-2.5 text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 group-hover:border-slate-500"
                        >
                            <span>Buka Tampilan</span>
                            <ArrowRight class="h-3.5 w-3.5 group-hover:translate-x-1 transition-transform" />
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800/80 bg-[#0E1424] px-6 py-6 text-center text-xs text-slate-500">
            <p>&copy; 2026 Enterprise Gym Management System (EGMS). Built with Laravel 13, Inertia.js &amp; Vue 3.</p>
        </footer>
    </div>
</template>
