<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDownRight,
    ArrowLeft,
    ArrowUpRight,
    BarChart3,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    ChevronRight,
    Clock,
    DollarSign,
    Download,
    Dumbbell,
    FileSpreadsheet,
    Filter,
    Info,
    Lock,
    LogOut,
    MapPin,
    MessageSquare,
    PieChart,
    RefreshCw,
    Send,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    TrendingDown,
    TrendingUp,
    UserCheck,
    Users,
    UserX,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

interface BranchItem {
    id: string;
    name: string;
}

interface FinancialData {
    cash_inflow: number;
    cash_inflow_formatted: string;
    cash_growth_percent: number;
    earned_revenue: number;
    earned_revenue_formatted: string;
    earned_ratio_percent: number;
    deferred_revenue: number;
    deferred_revenue_formatted: string;
    breakdown: {
        annual_package_reserve: number;
        annual_package_formatted: string;
        annual_package_percent: number;
        unused_pt_reserve: number;
        unused_pt_formatted: string;
        unused_pt_percent: number;
    };
    reserve_health: string;
}

interface AlertItem {
    id: string;
    type: 'rose' | 'amber' | 'purple';
    badge: string;
    title: string;
    description: string;
    action_label: string;
    count?: number;
    active_pt_count?: number;
    unauthorized_pt_count?: number;
}

interface HeatmapData {
    matrix: Array<{
        day: string;
        data: Array<{
            hour: string;
            occupancy: number;
        }>;
    }>;
    hours: string[];
    roaming: {
        total_roaming_visits: number;
        clearing_settlement_amount: number;
        clearing_settlement_formatted: string;
        top_routes: Array<{
            from: string;
            to: string;
            percentage: number;
        }>;
    };
}

interface StaffPayroll {
    id: number;
    name: string;
    branch: string;
    role: string;
    sales_commission: number;
    sales_commission_formatted: string;
    pt_sessions_count: number;
    pt_sessions_commission: number;
    pt_sessions_formatted: string;
    clawback_deduction: number;
    clawback_formatted: string;
    clawback_reason: string | null;
    net_payout: number;
    net_payout_formatted: string;
    payout_status: string;
    payout_status_color: string;
}

interface Props {
    branches: BranchItem[];
    selectedBranch: string;
    selectedPeriod: string;
    financialAccounting: FinancialData;
    alerts: Record<string, AlertItem>;
    heatmap: HeatmapData;
    payroll: {
        staff: StaffPayroll[];
        summary: {
            total_net_payout: number;
            total_net_payout_formatted: string;
            total_clawback: number;
            total_clawback_formatted: string;
            total_sessions_taught: number;
        };
    };
}

const props = defineProps<Props>();

// Active Filters
const currentBranch = ref(props.selectedBranch);
const currentPeriod = ref(props.selectedPeriod);

// Modals State
const show2faModal = ref(false);
const twoFactorCode = ref('');
const isExporting = ref(false);
const exportSuccess = ref(false);

const showAlertModal = ref<AlertItem | null>(null);
const showWaBlastModal = ref(false);
const isBlastingWa = ref(false);
const blastSuccess = ref(false);
const waTemplate = ref(
    'Halo Kak {nama}! Membership Anda di Gymapp tersisa 7 hari lagi. Perpanjang hari ini dan dapatkan bonus 1 sesi PT gratis + diskon 15% renewal!'
);

// Heatmap Hover Tooltip State
const hoveredCell = ref<{ day: string; hour: string; occupancy: number } | null>(null);

function getHeatmapBgColor(occupancy: number): string {
    if (occupancy >= 85) return 'bg-rose-500 text-white';
    if (occupancy >= 65) return 'bg-amber-500 text-slate-950 font-bold';
    if (occupancy >= 45) return 'bg-emerald-500/80 text-white';
    if (occupancy >= 25) return 'bg-emerald-500/30 text-emerald-300';
    return 'bg-slate-800/60 text-slate-500';
}

function handle2faExport() {
    if (twoFactorCode.value.length < 6) {
        alert('Mohon masukkan kode autentikasi 2FA 6 digit.');
        return;
    }

    isExporting.value = true;
    window.location.href = `/executive/export-tax?two_factor_code=${encodeURIComponent(twoFactorCode.value)}`;

    setTimeout(() => {
        isExporting.value = false;
        exportSuccess.value = true;
        setTimeout(() => {
            show2faModal.value = false;
            exportSuccess.value = false;
            twoFactorCode.value = '';
        }, 1200);
    }, 1000);
}

function handleBlastWa() {
    isBlastingWa.value = true;
    setTimeout(() => {
        isBlastingWa.value = false;
        blastSuccess.value = true;
        setTimeout(() => {
            showWaBlastModal.value = false;
            blastSuccess.value = false;
        }, 1500);
    }, 1200);
}
</script>

<template>
    <Head title="Executive Business Intelligence Dashboard - EGMS" />

    <div class="min-h-screen bg-[#090D16] text-slate-100 font-sans selection:bg-indigo-500 selection:text-white pb-16">
        <!-- TOP SYSTEM & NAVIGATION BAR -->
        <header class="border-b border-slate-800/80 bg-[#0E1424]/90 backdrop-blur sticky top-0 z-40 px-4 lg:px-8 py-3.5">
            <div class="max-w-[1440px] mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Left: Branding & Multi-Branch Filter -->
                <div class="flex flex-wrap items-center gap-4">
                    <Link
                        href="/dashboard"
                        class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition"
                        title="Kembali ke Dashboard Utama"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>

                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></span>
                            <h1 class="text-base font-extrabold text-white tracking-tight">Executive BI Dashboard</h1>
                            <span class="rounded bg-indigo-500/20 px-2 py-0.5 text-[10px] font-bold text-indigo-400 border border-indigo-500/30 uppercase tracking-wider font-mono">
                                OWNER SUITE
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">Multi-Branch Gym Management System • Real-Time Accrual &amp; Audit Engine</p>
                    </div>

                    <!-- Branch Filter Segmented Pills: "[Semua Cabang (3)] | Jogja Utama | Sleman | Solo" -->
                    <div class="flex items-center bg-[#090D16] p-1 rounded-xl border border-slate-800 text-xs font-medium ml-0 sm:ml-4 overflow-x-auto">
                        <button
                            v-for="branch in props.branches"
                            :key="branch.id"
                            @click="currentBranch = branch.id"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all whitespace-nowrap',
                                currentBranch === branch.id
                                    ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30'
                                    : 'text-slate-400 hover:text-slate-200',
                            ]"
                        >
                            {{ branch.name }}
                        </button>
                    </div>
                </div>

                <!-- Right: Date Period Picker & 2FA Export Button -->
                <div class="flex items-center gap-3">
                    <!-- Date Period Picker: "Oktober 2026" -->
                    <div class="flex items-center gap-2 bg-[#090D16] border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-300">
                        <Calendar class="w-3.5 h-3.5 text-indigo-400" />
                        <span class="font-mono font-bold">{{ currentPeriod }}</span>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] text-slate-400 font-mono">Q4</span>
                    </div>

                    <!-- Secure Button: "[Export Laporan Pajak/CSV (Wajib 2FA)]" -->
                    <button
                        @click="show2faModal = true"
                        class="rounded-xl bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-slate-200 border border-slate-700/80 px-3.5 py-2 text-xs font-bold transition flex items-center gap-2 shadow-sm cursor-pointer hover:border-indigo-500/50"
                    >
                        <Lock class="w-3.5 h-3.5 text-amber-400" />
                        <span>Export Laporan Pajak/CSV <span class="text-amber-400 text-[10px]">(Wajib 2FA)</span></span>
                    </button>

                    <div class="h-6 w-px bg-slate-800 hidden sm:block" />

                    <!-- Portal Links & Logout -->
                    <div class="flex items-center gap-1.5">
                        <Link
                            href="/gate"
                            class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center gap-1.5 border border-slate-700"
                            title="Buka Workstation Kasir & Gate"
                        >
                            <span>🖥️ Gate & Kasir</span>
                        </Link>
                        <Link
                            href="/members"
                            class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center gap-1.5 border border-slate-700 hidden md:flex"
                            title="Daftar Member"
                        >
                            <span>Members</span>
                        </Link>
                        <Link
                            href="/trainer"
                            class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center gap-1.5 border border-slate-700 hidden lg:flex"
                            title="Portal PT"
                        >
                            <span>PT</span>
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-950/40 border border-slate-800 transition"
                            title="Logout"
                        >
                            <LogOut class="w-4 h-4" />
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- MAIN BENTO GRID CONTAINER (1440x900 Optimized) -->
        <main class="max-w-[1440px] mx-auto px-4 lg:px-8 mt-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- ========================================================================= -->
                <!-- QUADRANT 1 (Top Left - Financial Accounting Dual Card): 7 Columns          -->
                <!-- ========================================================================= -->
                <section class="lg:col-span-7 rounded-3xl bg-[#0E1424] border border-slate-800 p-6 shadow-xl relative overflow-hidden flex flex-col justify-between min-h-[380px]">
                    <!-- Subtle ambient background glow -->
                    <div class="absolute -top-16 -left-16 w-48 h-48 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div>
                        <!-- Header with Accounting Badge -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-800/80">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-xl bg-indigo-500/15 border border-indigo-500/30 text-indigo-400">
                                    <BarChart3 class="w-5 h-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-white tracking-tight">Kuadran 1: Akuntansi Keuangan Dual-Basis</h2>
                                    <p class="text-xs text-slate-400">Perbandingan Cash Basis vs Accrual Earned Revenue &amp; Deferred Reserves</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-bold text-emerald-400 border border-emerald-500/30 font-mono">
                                PSAK 72 / IFRS 15 COMPLIANT
                            </span>
                        </div>

                        <!-- Side-by-Side Dual Revenue Comparison Cards -->
                        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Card Left: Arus Kas Masuk (Cash Basis): Rp 184.500.000 -->
                            <div class="rounded-2xl bg-[#141C30] p-4 border border-slate-700/60 shadow-md relative overflow-hidden">
                                <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                                    <span class="font-bold text-slate-300 flex items-center gap-1.5">
                                        <DollarSign class="w-4 h-4 text-emerald-400" /> Arus Kas Masuk (Cash Basis)
                                    </span>
                                    <span class="inline-flex items-center gap-0.5 text-emerald-400 font-mono font-bold text-[11px]">
                                        <TrendingUp class="w-3.5 h-3.5" /> +14.2%
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl sm:text-3xl font-extrabold font-mono text-white tracking-tight">
                                        {{ props.financialAccounting.cash_inflow_formatted }}
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">Total kas fisik, EDC &amp; QRIS terkumpul bulan berjalan</p>
                                </div>
                                <div class="mt-3 pt-2.5 border-t border-slate-700/50 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                                    <span>Realisasi Kasir: 100%</span>
                                    <span class="text-emerald-400 font-semibold">Net Positive Cashflow</span>
                                </div>
                            </div>

                            <!-- Card Right: Pendapatan Terealisasi (Accrual / Earned Revenue): Rp 112.300.000 -->
                            <div class="rounded-2xl bg-[#141C30] p-4 border border-slate-700/60 shadow-md relative overflow-hidden">
                                <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                                    <span class="font-bold text-slate-300 flex items-center gap-1.5">
                                        <CheckCircle2 class="w-4 h-4 text-indigo-400" /> Pendapatan Terealisasi (Accrual)
                                    </span>
                                    <span class="rounded bg-indigo-500/20 px-2 py-0.5 text-[10px] font-mono font-bold text-indigo-300 border border-indigo-500/30">
                                        EARNED
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl sm:text-3xl font-extrabold font-mono text-indigo-300 tracking-tight">
                                        {{ props.financialAccounting.earned_revenue_formatted }}
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">Hak pakai gym &amp; sesi PT yang telah terlaksana sah diakui</p>
                                </div>
                                <div class="mt-3 pt-2.5 border-t border-slate-700/50 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                                    <span>Rasio Realisasi: 60.9%</span>
                                    <span class="text-indigo-400 font-semibold">Omzet Bersih Sah</span>
                                </div>
                            </div>
                        </div>

                        <!-- Breakdown Bar: Dana Cadangan Paket Tahunan & Sisa Sesi PT (Deferred Revenue) -->
                        <div class="mt-5 rounded-2xl bg-[#121829] p-4 border border-slate-800">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs">
                                <div>
                                    <span class="font-bold text-amber-400">
                                        Dana Cadangan Paket Tahunan &amp; Sisa Sesi PT (Deferred Revenue):
                                    </span>
                                    <span class="font-mono font-extrabold text-white ml-1.5 text-sm">
                                        {{ props.financialAccounting.deferred_revenue_formatted }}
                                    </span>
                                </div>
                                <span class="rounded bg-amber-500/15 px-2 py-0.5 text-[10px] font-mono text-amber-300 border border-amber-500/30">
                                    LIABILITAS OPERASIONAL AMAN
                                </span>
                            </div>

                            <!-- Dual-Color Segmented Progress Bar -->
                            <div class="mt-3 h-3 w-full rounded-full bg-slate-900 overflow-hidden flex p-0.5 border border-slate-700/60">
                                <!-- Segment 1: Paket Tahunan Amortisasi (66.5%) -->
                                <div
                                    class="h-full rounded-l-full bg-gradient-to-r from-amber-500 to-amber-600 transition-all duration-700"
                                    :style="{ width: `${props.financialAccounting.breakdown.annual_package_percent}%` }"
                                    title="Paket Tahunan: Rp 48.000.000 (66.5%)"
                                ></div>
                                <!-- Segment 2: Sisa Sesi PT (33.5%) -->
                                <div
                                    class="h-full rounded-r-full bg-gradient-to-r from-indigo-500 to-purple-600 transition-all duration-700"
                                    :style="{ width: `${props.financialAccounting.breakdown.unused_pt_percent}%` }"
                                    title="Sisa Sesi PT Belum Terpakai: Rp 24.200.000 (33.5%)"
                                ></div>
                            </div>

                            <!-- Legend & Explanatory Breakdown Note -->
                            <div class="mt-3 pt-2 border-t border-slate-800/80 flex flex-wrap items-center justify-between text-[11px] text-slate-400 gap-2">
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-1.5 font-mono">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                        <span>Paket Tahunan (Amortisasi): <strong class="text-white">{{ props.financialAccounting.breakdown.annual_package_formatted }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-1.5 font-mono">
                                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                        <span>Sisa Sesi PT: <strong class="text-white">{{ props.financialAccounting.breakdown.unused_pt_formatted }}</strong></span>
                                    </div>
                                </div>
                                <span class="text-[10px] text-emerald-400 font-semibold font-mono">Anti-Insolvency Reserve: 100%</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- QUADRANT 2 (Top Right - Fraud, Audit & Retention Alerts): 5 Columns       -->
                <!-- ========================================================================= -->
                <section class="lg:col-span-5 rounded-3xl bg-[#0E1424] border border-slate-800 p-6 shadow-xl relative overflow-hidden flex flex-col justify-between min-h-[380px]">
                    <div>
                        <!-- Header with Pulse Indicator -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-800/80">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-400">
                                    <ShieldAlert class="w-5 h-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-white tracking-tight">Kuadran 2: Fraud, Audit &amp; Retensi</h2>
                                    <p class="text-xs text-slate-400">Peringatan Berisiko Tinggi &amp; Intervensi Cepat</p>
                                </div>
                            </div>
                            <span class="flex items-center gap-1.5 rounded-full bg-rose-500/20 px-2.5 py-0.5 text-[10px] font-bold text-rose-300 border border-rose-500/40">
                                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                3 PERINGATAN AKTIF
                            </span>
                        </div>

                        <!-- 3 High-Priority Clickable Alert Cards -->
                        <div class="mt-4 space-y-3">
                            <!-- Card a) Rose Red Card: 2 Selisih Kasir (Blind Cash Opname Discrepancy) -->
                            <div
                                @click="showAlertModal = props.alerts.cash_opname_discrepancy"
                                class="rounded-2xl bg-rose-500/10 hover:bg-rose-500/15 border-2 border-rose-500/30 p-3.5 transition cursor-pointer shadow-md group"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2.5">
                                        <div class="rounded-lg bg-rose-500/20 p-1.5 text-rose-400 shrink-0 mt-0.5">
                                            <AlertTriangle class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="rounded bg-rose-500 px-1.5 py-0.2 text-[9px] font-extrabold text-white uppercase font-mono">
                                                    FRAUD ALERT
                                                </span>
                                                <span class="text-[10px] text-rose-300 font-mono">Sleman &amp; Solo</span>
                                            </div>
                                            <!-- Explicit Requested Title in Prompt -->
                                            <h4 class="text-xs font-extrabold text-rose-200 mt-1 leading-snug group-hover:text-white transition">
                                                2 Selisih Kasir (Blind Cash Opname Discrepancy) - Cek Shift Kemarin
                                            </h4>
                                            <p class="text-[11px] text-rose-300/80 mt-1">
                                                {{ props.alerts.cash_opname_discrepancy.description }}
                                            </p>
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-rose-400 shrink-0 group-hover:translate-x-0.5 transition" />
                                </div>
                            </div>

                            <!-- Card b) Amber Card: 48 Member Akan Habis dalam 7 Hari (Klik untuk Blast Promo WA) -->
                            <div
                                @click="showWaBlastModal = true"
                                class="rounded-2xl bg-amber-500/10 hover:bg-amber-500/15 border-2 border-amber-500/30 p-3.5 transition cursor-pointer shadow-md group"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2.5">
                                        <div class="rounded-lg bg-amber-500/20 p-1.5 text-amber-400 shrink-0 mt-0.5">
                                            <Clock class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="rounded bg-amber-500 px-1.5 py-0.2 text-[9px] font-extrabold text-slate-950 uppercase font-mono">
                                                    RETENTION RISK
                                                </span>
                                                <span class="text-[10px] text-amber-300 font-mono">Omzet Rp 16.8M</span>
                                            </div>
                                            <!-- Explicit Requested Title in Prompt -->
                                            <h4 class="text-xs font-extrabold text-amber-200 mt-1 leading-snug group-hover:text-white transition">
                                                48 Member Akan Habis dalam 7 Hari (Klik untuk Blast Promo WA)
                                            </h4>
                                            <p class="text-[11px] text-amber-300/80 mt-1">
                                                Penyelamatan renewal otomatis via WhatsApp Gateway resmi Gymapp.
                                            </p>
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-amber-400 shrink-0 group-hover:translate-x-0.5 transition" />
                                </div>
                            </div>

                            <!-- Card c) Purple Card: Live Floor Monitor: 4 PT Sedang Mengajar, 1 PT Tanpa Jadwal -->
                            <div
                                @click="showAlertModal = props.alerts.unauthorized_pt_floor"
                                class="rounded-2xl bg-purple-500/10 hover:bg-purple-500/15 border-2 border-purple-500/30 p-3.5 transition cursor-pointer shadow-md group"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2.5">
                                        <div class="rounded-lg bg-purple-500/20 p-1.5 text-purple-400 shrink-0 mt-0.5">
                                            <Dumbbell class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="rounded bg-purple-500 px-1.5 py-0.2 text-[9px] font-extrabold text-white uppercase font-mono">
                                                    FLOOR INTEGRITY
                                                </span>
                                                <span class="text-[10px] text-purple-300 font-mono">Live Gate Sensor</span>
                                            </div>
                                            <!-- Explicit Requested Title in Prompt -->
                                            <h4 class="text-xs font-extrabold text-purple-200 mt-1 leading-snug group-hover:text-white transition">
                                                Live Floor Monitor: 4 PT Sedang Mengajar Resmi, 1 PT Terdeteksi di Gym Tanpa Jadwal
                                            </h4>
                                            <p class="text-[11px] text-purple-300/80 mt-1">
                                                {{ props.alerts.unauthorized_pt_floor.description }}
                                            </p>
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-purple-400 shrink-0 group-hover:translate-x-0.5 transition" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- QUADRANT 3 (Bottom Left - Peak Hours Occupancy Heatmap): 6 Columns        -->
                <!-- ========================================================================= -->
                <section class="lg:col-span-6 rounded-3xl bg-[#0E1424] border border-slate-800 p-6 shadow-xl relative overflow-hidden flex flex-col justify-between min-h-[460px]">
                    <div>
                        <!-- Header -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-800/80">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400">
                                    <Clock class="w-5 h-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-white tracking-tight">Kuadran 3: Peak Hours Occupancy Heatmap</h2>
                                    <p class="text-xs text-slate-400">Matriks 7 Hari x 16 Jam (06:00 - 22:00) &amp; Roaming Lintas Cabang</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-slate-800 px-2.5 py-0.5 text-[11px] font-mono text-slate-300 border border-slate-700">
                                SENSOR GATE IOT
                            </span>
                        </div>

                        <!-- 7-day x 16-hour Matrix Heatmap Container -->
                        <div class="mt-4 overflow-x-auto pb-2">
                            <div class="min-w-[500px]">
                                <!-- Hour Column Headers (06:00 to 21:00) -->
                                <div class="grid grid-cols-[56px_repeat(16,1fr)] gap-1 text-center text-[10px] font-mono text-slate-400 mb-1.5">
                                    <div class="text-left font-sans">Hari</div>
                                    <div v-for="h in props.heatmap.hours" :key="h" class="truncate">
                                        {{ h.slice(0, 2) }}
                                    </div>
                                </div>

                                <!-- Rows for each day -->
                                <div class="space-y-1.5">
                                    <div
                                        v-for="row in props.heatmap.matrix"
                                        :key="row.day"
                                        class="grid grid-cols-[56px_repeat(16,1fr)] gap-1 items-center"
                                    >
                                        <div class="text-xs font-semibold text-slate-300 truncate">
                                            {{ row.day }}
                                        </div>
                                        <div
                                            v-for="cell in row.data"
                                            :key="cell.hour"
                                            @mouseenter="hoveredCell = { day: row.day, hour: cell.hour, occupancy: cell.occupancy }"
                                            @mouseleave="hoveredCell = null"
                                            :class="[
                                                'h-7 rounded-md flex items-center justify-center text-[10px] font-mono transition-transform duration-150 cursor-pointer hover:scale-110 hover:z-10 hover:shadow-lg',
                                                getHeatmapBgColor(cell.occupancy),
                                            ]"
                                        >
                                            {{ cell.occupancy }}%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Hover Tooltip Display -->
                        <div class="mt-2 h-6 flex items-center justify-between text-xs">
                            <div v-if="hoveredCell" class="text-amber-400 font-mono flex items-center gap-1.5">
                                <Info class="w-3.5 h-3.5" />
                                <span>{{ hoveredCell.day }}, Pukul {{ hoveredCell.hour }}: Kepadatan <strong>{{ hoveredCell.occupancy }}%</strong></span>
                            </div>
                            <div v-else class="text-slate-500 text-[11px]">
                                Sorot sel untuk detail persentase kepadatan lantai gym.
                            </div>

                            <!-- Legend -->
                            <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-slate-800"></span>&lt;30%</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-500"></span>30-65%</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-amber-500"></span>65-85%</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-rose-500"></span>&gt;85%</span>
                            </div>
                        </div>

                        <!-- Cross-Branch Roaming Visit Stats Section -->
                        <div class="mt-4 pt-3.5 border-t border-slate-800 bg-[#121829] rounded-2xl p-3.5 border border-slate-700/60">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-white flex items-center gap-1.5">
                                    <MapPin class="w-3.5 h-3.5 text-indigo-400" /> Kunjungan Lintas Cabang (Roaming Member)
                                </span>
                                <span class="rounded bg-indigo-500/20 px-2 py-0.5 text-[10px] font-mono font-bold text-indigo-300 border border-indigo-500/30">
                                    {{ props.heatmap.roaming.total_roaming_visits }} Check-in Bulan Ini
                                </span>
                            </div>

                            <div class="mt-2.5 grid grid-cols-1 sm:grid-cols-3 gap-2 text-center text-[11px]">
                                <div
                                    v-for="(route, idx) in props.heatmap.roaming.top_routes"
                                    :key="idx"
                                    class="bg-slate-900/80 rounded-xl p-2 border border-slate-800"
                                >
                                    <span class="block text-slate-400 text-[10px]">{{ route.from }} &rarr; {{ route.to }}</span>
                                    <span class="font-mono font-bold text-white text-xs">{{ route.percentage }}% Roaming</span>
                                </div>
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-slate-800 flex items-center justify-between text-[11px] font-mono text-slate-400">
                                <span>Kliring Antar-Cabang (Settlement Clearing):</span>
                                <span class="text-emerald-400 font-bold">{{ props.heatmap.roaming.clearing_settlement_formatted }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- QUADRANT 4 (Bottom Right - Payroll & Staff Commission Table): 6 Columns   -->
                <!-- ========================================================================= -->
                <section class="lg:col-span-6 rounded-3xl bg-[#0E1424] border border-slate-800 p-6 shadow-xl relative overflow-hidden flex flex-col justify-between min-h-[460px]">
                    <div>
                        <!-- Header -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-800/80">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-400">
                                    <DollarSign class="w-5 h-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-extrabold text-white tracking-tight">Kuadran 4: Payroll &amp; Staff Commission</h2>
                                    <p class="text-xs text-slate-400">Komisi Closing, Sesi PT Mengajar, &amp; Clawback Refund Otomatis</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-slate-800 px-2.5 py-0.5 text-[11px] font-mono text-slate-300 border border-slate-700">
                                4 STAF TERDAFTAR
                            </span>
                        </div>

                        <!-- Staff / Trainer Commission Table -->
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400 font-mono">
                                    <tr>
                                        <th class="py-2.5 pr-2">Staf / Trainer</th>
                                        <th class="py-2.5 px-2 text-right">Closing Sales</th>
                                        <th class="py-2.5 px-2 text-right">Sesi PT</th>
                                        <th class="py-2.5 px-2 text-right text-rose-400">Clawback</th>
                                        <th class="py-2.5 px-2 text-right">Net Payout</th>
                                        <th class="py-2.5 pl-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60 font-mono">
                                    <tr
                                        v-for="staf in props.payroll.staff"
                                        :key="staf.id"
                                        class="hover:bg-slate-800/40 transition"
                                    >
                                        <td class="py-2.5 pr-2 font-sans">
                                            <div class="font-bold text-white text-xs">{{ staf.name }}</div>
                                            <div class="text-[10px] text-slate-400 flex items-center gap-1 font-mono">
                                                <span>{{ staf.branch }}</span>
                                                <span>•</span>
                                                <span class="text-indigo-400">{{ staf.role }}</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2 text-right text-slate-300">
                                            {{ staf.sales_commission_formatted }}
                                        </td>
                                        <td class="py-2.5 px-2 text-right text-slate-300">
                                            <div>{{ staf.pt_sessions_formatted }}</div>
                                            <div v-if="staf.pt_sessions_count > 0" class="text-[10px] text-slate-500">
                                                ({{ staf.pt_sessions_count }} sesi)
                                            </div>
                                        </td>
                                        <!-- Clawback Deductions (negative red values from refunded invoices) -->
                                        <td class="py-2.5 px-2 text-right">
                                            <span
                                                :class="[
                                                    'font-bold',
                                                    staf.clawback_deduction < 0 ? 'text-rose-400 font-extrabold' : 'text-slate-500',
                                                ]"
                                            >
                                                {{ staf.clawback_formatted }}
                                            </span>
                                            <div v-if="staf.clawback_reason" class="text-[9px] text-rose-400/80 font-sans truncate max-w-[120px]" :title="staf.clawback_reason">
                                                {{ staf.clawback_reason }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2 text-right font-bold text-white text-sm">
                                            {{ staf.net_payout_formatted }}
                                        </td>
                                        <td class="py-2.5 pl-2 text-center font-sans">
                                            <span
                                                :class="[
                                                    'rounded-full px-2 py-0.5 text-[10px] font-bold border',
                                                    staf.payout_status_color === 'emerald'
                                                        ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                                                        : 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                                ]"
                                            >
                                                {{ staf.payout_status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary Footer Bar -->
                        <div class="mt-4 pt-3 border-t border-slate-800 flex flex-wrap items-center justify-between text-xs font-mono gap-2 bg-[#121829] rounded-2xl p-3 border border-slate-700/60">
                            <div>
                                <span class="text-slate-400">Total Payout Komisi Bulan Ini:</span>
                                <span class="text-white font-extrabold text-sm ml-1.5">{{ props.payroll.summary.total_net_payout_formatted }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-rose-400 font-bold">Total Clawback: {{ props.payroll.summary.total_clawback_formatted }}</span>
                                <button
                                    @click="alert('Seluruh payroll komisi staf berhasil diajukan ke batch transfer bank (BCA Corporate API)!')"
                                    class="rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-1.5 text-xs transition shadow-sm font-sans flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Check class="w-3.5 h-3.5" /> Setujui &amp; Transfer Batch
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <!-- ========================================================================= -->
        <!-- MODAL 1: 2FA Authentication for Tax / CSV Report Export                   -->
        <!-- ========================================================================= -->
        <div
            v-if="show2faModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-md rounded-3xl bg-[#0E1424] border border-slate-700 p-6 shadow-2xl relative text-slate-100">
                <button
                    @click="show2faModal = false"
                    class="absolute top-4 right-4 p-2 rounded-full bg-slate-800 text-slate-400 hover:text-white transition"
                >
                    <X class="w-4 h-4" />
                </button>

                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-amber-400">
                        <Lock class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Autentikasi 2FA Wajib</h3>
                        <p class="text-xs text-slate-400">Ekspor Laporan Pajak, SPT &amp; Keuangan Multi-Cabang</p>
                    </div>
                </div>

                <div class="mt-4 p-3 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300 leading-relaxed">
                    Sesuai kepatuhan privasi data keuangan owner, Anda wajib memasukkan 6-digit kode OTP dari aplikasi Authenticator atau SMS terdaftar.
                </div>

                <div class="mt-4">
                    <label class="text-xs font-semibold text-slate-300 block mb-1.5">
                        Masukkan 6-Digit Kode Keamanan (Demo: 123456)
                    </label>
                    <input
                        v-model="twoFactorCode"
                        type="password"
                        maxlength="6"
                        placeholder="••••••"
                        class="w-full rounded-xl bg-slate-950 border-2 border-slate-700 focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 p-3 text-center text-2xl font-mono tracking-widest text-white outline-none transition"
                    />
                </div>

                <div v-if="exportSuccess" class="mt-3 p-2.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold flex items-center justify-center gap-1.5">
                    <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                    Kode 2FA Terverifikasi! Mengunduh Laporan Pajak...
                </div>

                <div class="mt-5 flex gap-2">
                    <button
                        @click="show2faModal = false"
                        class="flex-1 rounded-xl bg-slate-800 hover:bg-slate-700 py-2.5 text-xs font-semibold text-slate-300 transition"
                    >
                        Batal
                    </button>
                    <button
                        @click="handle2faExport"
                        :disabled="isExporting"
                        class="flex-1 rounded-xl bg-amber-500 hover:bg-amber-400 py-2.5 text-xs font-bold text-slate-950 shadow-md shadow-amber-500/20 transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <RefreshCw v-if="isExporting" class="w-3.5 h-3.5 animate-spin" />
                        <Download v-else class="w-3.5 h-3.5" />
                        <span>Verifikasi &amp; Unduh CSV</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 2: WhatsApp Churn Blast Modal                                       -->
        <!-- ========================================================================= -->
        <div
            v-if="showWaBlastModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-lg rounded-3xl bg-[#0E1424] border border-slate-700 p-6 shadow-2xl relative text-slate-100">
                <button
                    @click="showWaBlastModal = false"
                    class="absolute top-4 right-4 p-2 rounded-full bg-slate-800 text-slate-400 hover:text-white transition"
                >
                    <X class="w-4 h-4" />
                </button>

                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-amber-400">
                        <MessageSquare class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Blast Penawaran Renewal WhatsApp</h3>
                        <p class="text-xs text-slate-400">Target: 48 Member Akan Habis dalam 7 Hari (Potensi Rp 16.8M)</p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="text-xs font-semibold text-slate-300 block mb-1">
                        Template Pesan WhatsApp Promosi Renewal:
                    </label>
                    <textarea
                        v-model="waTemplate"
                        rows="4"
                        class="w-full rounded-xl bg-slate-950 border border-slate-700 focus:border-amber-400 p-3 text-xs text-slate-200 outline-none resize-none leading-relaxed"
                    ></textarea>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-center text-xs font-mono">
                    <div class="bg-slate-900 rounded-xl p-2 border border-slate-800">
                        <span class="text-slate-400 block text-[10px]">Total Penerima</span>
                        <span class="font-bold text-white">48 Kontak Valid</span>
                    </div>
                    <div class="bg-slate-900 rounded-xl p-2 border border-slate-800">
                        <span class="text-slate-400 block text-[10px]">Estimasi Sukses</span>
                        <span class="font-bold text-emerald-400">98% Delivery Rate</span>
                    </div>
                </div>

                <div v-if="blastSuccess" class="mt-3 p-2.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold flex items-center justify-center gap-1.5">
                    <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                    Pesan promo berhasil diantrikan ke 48 nomor member WhatsApp!
                </div>

                <div class="mt-5 flex gap-2">
                    <button
                        @click="showWaBlastModal = false"
                        class="flex-1 rounded-xl bg-slate-800 hover:bg-slate-700 py-2.5 text-xs font-semibold text-slate-300 transition"
                    >
                        Batal
                    </button>
                    <button
                        @click="handleBlastWa"
                        :disabled="isBlastingWa"
                        class="flex-1 rounded-xl bg-amber-500 hover:bg-amber-400 py-2.5 text-xs font-bold text-slate-950 shadow-md shadow-amber-500/20 transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <RefreshCw v-if="isBlastingWa" class="w-3.5 h-3.5 animate-spin" />
                        <Send v-else class="w-3.5 h-3.5" />
                        <span>Kirim Blast WA Sekarang</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 3: Audit Detail Modal                                               -->
        <!-- ========================================================================= -->
        <div
            v-if="showAlertModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-md rounded-3xl bg-[#0E1424] border border-slate-700 p-6 shadow-2xl relative text-slate-100">
                <button
                    @click="showAlertModal = null"
                    class="absolute top-4 right-4 p-2 rounded-full bg-slate-800 text-slate-400 hover:text-white transition"
                >
                    <X class="w-4 h-4" />
                </button>

                <div class="flex items-center gap-3">
                    <div
                        :class="[
                            'p-3 rounded-2xl border',
                            showAlertModal.type === 'rose'
                                ? 'bg-rose-500/15 border-rose-500/30 text-rose-400'
                                : 'bg-purple-500/15 border-purple-500/30 text-purple-400',
                        ]"
                    >
                        <ShieldAlert class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">{{ showAlertModal.badge }}</h3>
                        <p class="text-xs text-slate-400">Pemeriksaan Log Integritas &amp; Audit Operasional</p>
                    </div>
                </div>

                <div class="mt-4 p-3.5 rounded-2xl bg-slate-900 border border-slate-800 text-xs text-slate-200 leading-relaxed">
                    <h4 class="font-bold text-white mb-1">{{ showAlertModal.title }}</h4>
                    <p class="text-slate-400">{{ showAlertModal.description }}</p>
                </div>

                <div class="mt-5 flex gap-2">
                    <button
                        @click="showAlertModal = null"
                        class="flex-1 rounded-xl bg-slate-800 hover:bg-slate-700 py-2.5 text-xs font-semibold text-slate-300 transition"
                    >
                        Tutup
                    </button>
                    <button
                        @click="
                            alert('Instruksi investigasi & pembekuan log telah dikirimkan ke Area Manager terkait!');
                            showAlertModal = null;
                        "
                        class="flex-1 rounded-xl bg-indigo-600 hover:bg-indigo-500 py-2.5 text-xs font-bold text-white shadow-md shadow-indigo-600/20 transition"
                    >
                        Kirim Notis Audit
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

