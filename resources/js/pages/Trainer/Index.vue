<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Award,
    Bell,
    Calendar,
    Camera,
    Check,
    CheckCircle2,
    ChevronRight,
    Clock,
    DollarSign,
    DoorOpen,
    Dumbbell,
    Flame,
    History,
    Info,
    Layers,
    Lock,
    LogOut,
    MapPin,
    QrCode,
    RefreshCw,
    Scale,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    User,
    UserCheck,
    Users,
    X,
    Zap,
} from '@lucide/vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

interface SessionItem {
    id: number;
    user_id?: number;
    time_range: string;
    client_name: string;
    remaining_sessions: string;
    status: 'completed' | 'active_session' | 'upcoming';
    verified_badge: string | null;
    is_active: boolean;
    gate_status: string;
    gate_color: 'emerald' | 'gray';
    par_q_status: string;
    client_checked_in?: boolean;
    trainer_checked_in?: boolean;
}

interface Props {
    trainer: {
        name: string;
        role: string;
        title: string;
    };
    earningsKpi: {
        total_commission: number;
        completed_sessions: number;
        target_sessions: number;
        progress_percent: number;
        next_bonus_tier: string;
        sessions_left: number;
    };
    sessions: SessionItem[];
}

const props = defineProps<Props>();
const page = usePage();

// View Mode: Side-by-side or Tabbed on mobile
const viewMode = ref<'side_by_side' | 'screen1' | 'screen2'>('side_by_side');

// Screen 1 State
const currentActiveNav = ref<'timeline' | 'clients' | 'commissions' | 'profile'>('timeline');

// Screen 2 OTP Form State
const selectedSession = ref<SessionItem>(props.sessions.find((s) => s.is_active) || props.sessions[1]);
const otpDigits = ref<string[]>(['8', '4', '9', '2', '0', '1']);
const isSubstitute = ref(false);
const workoutNotes = ref('Latihan Dada & Trisep: Bench Press 4x8 @70kg, Incline Dumbbell 3x10 @22kg. Kondisi prima tanpa keluhan.');
const isVerifying = ref(false);
const verificationSuccess = ref(false);
const showInBodyModal = ref(false);

// InBody / Beban Modal State
const inbodyForm = ref({
    benchPress: '70',
    squat: '95',
    deadlift: '120',
    bodyWeight: '72.4',
    bodyFat: '16.5',
    notes: 'Kekuatan core meningkat pesat dibanding minggu lalu.',
});
const isSavingInBody = ref(false);

function submitInBodyMetrics() {
    isSavingInBody.value = true;
    const targetUserId = selectedSession.value?.user_id || 4;

    router.post(`/trainer/members/${targetUserId}/metrics`, {
        bench_press: inbodyForm.value.benchPress,
        squat: inbodyForm.value.squat,
        deadlift: inbodyForm.value.deadlift,
        body_weight: inbodyForm.value.bodyWeight,
        body_fat: inbodyForm.value.bodyFat,
        notes: inbodyForm.value.notes,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSavingInBody.value = false;
            showInBodyModal.value = false;
        },
        onError: () => {
            isSavingInBody.value = false;
        },
    });
}

function handleDigitInput(index: number, event: Event) {
    const input = event.target as HTMLInputElement;
    const val = input.value.slice(-1);
    otpDigits.value[index] = val;

    // Auto-focus next input
    if (val && index < 5) {
        const nextInput = document.getElementById(`otp-box-${index + 1}`) as HTMLInputElement | null;
        nextInput?.focus();
    }
}

function handleDigitKeyDown(index: number, event: KeyboardEvent) {
    if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
        const prevInput = document.getElementById(`otp-box-${index - 1}`) as HTMLInputElement | null;
        prevInput?.focus();
    }
}

function fillDemoOtp() {
    otpDigits.value = ['8', '4', '9', '2', '0', '1'];
}

// Live Camera QR Scanner for PT claim
const isPtCameraModalOpen = ref(false);
const ptVideoRef = ref<HTMLVideoElement | null>(null);
const ptStream = ref<MediaStream | null>(null);
const ptCameraActive = ref(false);
const ptCameraError = ref<string | null>(null);
const ptScanSuccess = ref<string | null>(null);
let ptBarcodeDetector: any = null;
let ptScanLoopId: number | null = null;

async function startPtCamera() {
    ptCameraError.value = null;
    ptScanSuccess.value = null;
    try {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            ptCameraError.value = 'Perangkat tidak mendukung WebRTC Camera.';
            return;
        }

        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 640 } },
        }).catch(() => navigator.mediaDevices.getUserMedia({ video: true }));

        ptStream.value = stream;
        if (ptVideoRef.value) {
            ptVideoRef.value.srcObject = stream;
            await ptVideoRef.value.play().catch(() => {});
            ptCameraActive.value = true;
            initPtBarcodeDetector();
        }
    } catch {
        ptCameraError.value = 'Kamera tidak dapat diakses atau izin ditolak.';
        ptCameraActive.value = false;
    }
}

function stopPtCamera() {
    if (ptScanLoopId) {
        cancelAnimationFrame(ptScanLoopId);
        ptScanLoopId = null;
    }
    if (ptStream.value) {
        ptStream.value.getTracks().forEach((track) => track.stop());
        ptStream.value = null;
    }
    if (ptVideoRef.value) {
        ptVideoRef.value.srcObject = null;
    }
    ptCameraActive.value = false;
}

function initPtBarcodeDetector() {
    if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
        try {
            ptBarcodeDetector = new (window as any).BarcodeDetector({
                formats: ['qr_code', 'code_128', 'ean_13'],
            });
            scanPtFrame();
        } catch {
            ptBarcodeDetector = null;
        }
    }
}

async function scanPtFrame() {
    if (!ptCameraActive.value || !ptVideoRef.value || ptScanSuccess.value) return;

    if (ptBarcodeDetector && ptVideoRef.value.readyState >= 2) {
        try {
            const barcodes = await ptBarcodeDetector.detect(ptVideoRef.value);
            if (barcodes && barcodes.length > 0) {
                const val = barcodes[0].rawValue;
                if (val) {
                    processPtScannedQr(val);
                    return;
                }
            }
        } catch {}
    }

    if (ptCameraActive.value && !ptScanSuccess.value) {
        ptScanLoopId = requestAnimationFrame(scanPtFrame);
    }
}

function processPtScannedQr(token: string) {
    stopPtCamera();
    const digitsOnly = token.replace(/\D/g, '');
    if (digitsOnly.length >= 6) {
        otpDigits.value = digitsOnly.slice(0, 6).split('');
    } else {
        fillDemoOtp();
    }
    ptScanSuccess.value = 'QR Member Terbaca! PIN 6 Digit berhasil diisi otomatis.';
    setTimeout(() => {
        isPtCameraModalOpen.value = false;
        ptScanSuccess.value = null;
    }, 1500);
}

watch(isPtCameraModalOpen, (open) => {
    if (open) {
        nextTick(() => startPtCamera());
    } else {
        stopPtCamera();
    }
});

onUnmounted(() => {
    stopPtCamera();
});

function submitVerification() {
    const fullOtp = otpDigits.value.join('');
    if (fullOtp.length !== 6) {
        alert('Mohon masukkan 6 digit PIN secara lengkap.');
        return;
    }

    isVerifying.value = true;

    router.post(`/trainer/sessions/${selectedSession.value.id}/verify`, {
        otp_code: fullOtp,
        is_substitute: isSubstitute.value,
        workout_notes: workoutNotes.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isVerifying.value = false;
            verificationSuccess.value = true;
            selectedSession.value.status = 'completed';
            selectedSession.value.verified_badge = 'Verified +Rp 150.000';
            selectedSession.value.is_active = false;
        },
        onError: () => {
            isVerifying.value = false;
        },
    });
}

function formatRupiah(amount: number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}
</script>

<template>
    <Head title="Personal Trainer Portal - EGMS" />

    <div class="min-h-screen bg-[#0F172A] text-slate-100 font-sans selection:bg-[#F97316] selection:text-white pb-12">
        <!-- Top App Navigation Bar -->
        <header class="border-b border-slate-800 bg-[#0F172A]/90 backdrop-blur sticky top-0 z-40 px-4 py-3">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        href="/gate"
                        class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-700 transition"
                        title="Buka Gate & Kasir"
                    >
                        <DoorOpen class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-[#10B981] animate-pulse"></span>
                            <h1 class="text-base font-bold text-white tracking-tight">Trainer Mobile PWA Workspace</h1>
                            <span class="rounded bg-[#F97316]/20 px-2 py-0.5 text-[11px] font-bold text-[#F97316] border border-[#F97316]/30 uppercase">
                                PT PORTAL
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">Jadwal Coaching Harian & Verifikasi Anti-Fraud OTP Sesi</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Desktop / Tablet View Mode Switcher -->
                    <div class="flex items-center gap-2 bg-slate-900 p-1 rounded-xl border border-slate-800 text-xs font-medium">
                        <button
                            @click="viewMode = 'side_by_side'"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5',
                                viewMode === 'side_by_side'
                                    ? 'bg-[#F97316] text-white font-bold shadow-md shadow-[#F97316]/20'
                                    : 'text-slate-400 hover:text-slate-200',
                            ]"
                        >
                            <Layers class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">2 Layar Bersebelahan</span>
                            <span class="sm:hidden">Side-by-Side</span>
                        </button>
                        <button
                            @click="viewMode = 'screen1'"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all',
                                viewMode === 'screen1'
                                    ? 'bg-[#F97316] text-white font-bold shadow-md shadow-[#F97316]/20'
                                    : 'text-slate-400 hover:text-slate-200',
                            ]"
                        >
                            Screen 1: Timeline
                        </button>
                        <button
                            @click="viewMode = 'screen2'"
                            :class="[
                                'px-3 py-1.5 rounded-lg transition-all',
                                viewMode === 'screen2'
                                    ? 'bg-[#F97316] text-white font-bold shadow-md shadow-[#F97316]/20'
                                    : 'text-slate-400 hover:text-slate-200',
                            ]"
                        >
                            Screen 2: Claim OTP
                        </button>
                    </div>

                    <!-- Logout Button -->
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-950/40 border border-slate-800 transition"
                        title="Keluar"
                    >
                        <LogOut class="w-4 h-4" />
                    </Link>
                </div>
            </div>
        </header>

        <!-- Flash Message Banner -->
        <div v-if="page.props.flash?.success" class="max-w-7xl mx-auto px-4 mt-4">
            <div class="p-3 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-sm flex items-center gap-2">
                <CheckCircle2 class="w-5 h-5 text-[#10B981] shrink-0" />
                <span>{{ page.props.flash.success }}</span>
            </div>
        </div>
        <div v-if="page.props.flash?.error" class="max-w-7xl mx-auto px-4 mt-4">
            <div class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-2">
                <AlertCircle class="w-5 h-5 text-rose-400 shrink-0" />
                <span>{{ page.props.flash.error }}</span>
            </div>
        </div>

        <!-- MAIN 2-SCREEN WRAPPER -->
        <main class="max-w-7xl mx-auto px-4 mt-6">
            <div
                :class="[
                    'grid gap-8 items-start justify-center',
                    viewMode === 'side_by_side' ? 'grid-cols-1 lg:grid-cols-2' : 'grid-cols-1 max-w-md mx-auto',
                ]"
            >
                <!-- ========================================================================= -->
                <!-- SCREEN 1: Trainer Daily Timeline & Earnings Header                        -->
                <!-- ========================================================================= -->
                <div
                    v-if="viewMode === 'side_by_side' || viewMode === 'screen1'"
                    class="w-full max-w-md mx-auto rounded-[2.5rem] border-4 border-slate-700/60 bg-[#0F172A] p-5 shadow-2xl relative overflow-hidden flex flex-col justify-between min-h-[820px]"
                >
                    <!-- Device Frame Notch Indicator -->
                    <div class="mb-3 flex items-center justify-between text-xs text-slate-500 font-mono border-b border-slate-800 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F97316]"></span>
                            <span class="font-bold text-slate-300">SCREEN 1: Trainer Daily Timeline</span>
                        </div>
                        <span class="text-[#F97316] font-semibold text-[11px]">PWA READY</span>
                    </div>

                    <div>
                        <!-- Trainer Profile Top Header -->
                        <div class="flex items-center justify-between pt-1 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <div class="h-12 w-12 rounded-full bg-gradient-to-tr from-slate-700 to-slate-600 flex items-center justify-center border-2 border-[#F97316] font-bold text-white shadow-md">
                                        CA
                                    </div>
                                    <div class="absolute -bottom-1 -right-1 rounded-full bg-[#0F172A] p-0.5" title="PT Active Verified">
                                        <Award class="h-4 w-4 text-[#F97316]" />
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <h2 class="text-base font-extrabold text-white leading-tight">Coach Ade</h2>
                                        <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-[#10B981] border border-emerald-500/30">
                                            ON GYM FLOOR
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5">Senior Functional & Hypertrophy Coach</p>
                                </div>
                            </div>
                            <button class="relative rounded-full bg-[#1E293B] p-2.5 text-slate-300 hover:text-white border border-slate-700/80 transition">
                                <Bell class="h-5 w-5" />
                                <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-[#F97316]"></span>
                            </button>
                        </div>

                        <!-- 1. Top Earnings & KPI Banner -->
                        <div class="rounded-2xl bg-[#1E293B] p-4 border border-slate-700 shadow-lg relative overflow-hidden">
                            <!-- Background glow subtle decoration -->
                            <div class="absolute -top-12 -right-12 w-32 h-32 bg-[#F97316]/10 rounded-full blur-2xl pointer-events-none"></div>

                            <div class="flex items-center justify-between text-xs text-slate-400 pb-1">
                                <span class="font-semibold text-slate-300 flex items-center gap-1.5">
                                    <DollarSign class="w-3.5 h-3.5 text-[#F97316]" /> Komisi Bulan Ini
                                </span>
                                <span class="rounded bg-[#F97316]/20 px-2 py-0.5 text-[10px] font-bold text-[#F97316] border border-[#F97316]/30 font-mono">
                                    TIER 1 (80%)
                                </span>
                            </div>

                            <!-- Large Monospace Total -->
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl sm:text-3xl font-extrabold font-mono text-white tracking-tight">
                                    {{ formatRupiah(props.earningsKpi.total_commission) }}
                                </span>
                            </div>

                            <!-- Progress Bar & Target Subtitle -->
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-xs font-medium mb-1.5">
                                    <span class="text-slate-300 font-mono">
                                        {{ props.earningsKpi.completed_sessions }} / {{ props.earningsKpi.target_sessions }} Sesi Selesai
                                    </span>
                                    <span class="text-[#F97316] font-bold text-[11px]">
                                        ({{ props.earningsKpi.sessions_left }} sesi lagi menuju Bonus Tier 2!)
                                    </span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-slate-900/90 overflow-hidden p-0.5 border border-slate-700/50">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-amber-500 to-[#F97316] transition-all duration-700 shadow-sm"
                                        :style="{ width: `${props.earningsKpi.progress_percent}%` }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Mini KPI Row -->
                            <div class="mt-3.5 pt-3 border-t border-slate-700/60 grid grid-cols-3 gap-2 text-center text-[11px]">
                                <div class="bg-slate-800/60 rounded-lg p-1.5 border border-slate-700/40">
                                    <span class="block text-slate-400 text-[10px]">Pencapaian</span>
                                    <span class="font-bold text-white font-mono">80% Target</span>
                                </div>
                                <div class="bg-slate-800/60 rounded-lg p-1.5 border border-slate-700/40">
                                    <span class="block text-slate-400 text-[10px]">Bonus Tier 2</span>
                                    <span class="font-bold text-[#10B981] font-mono">+Rp 1.5M</span>
                                </div>
                                <div class="bg-slate-800/60 rounded-lg p-1.5 border border-slate-700/40">
                                    <span class="block text-slate-400 text-[10px]">Client Rating</span>
                                    <span class="font-bold text-amber-400 font-mono">4.9 ★</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Today's Coaching Timeline Header -->
                        <div class="mt-5 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                                <Calendar class="w-4 h-4 text-[#F97316]" /> Jadwal Coaching Hari Ini
                            </h3>
                            <span class="text-[11px] font-mono text-slate-400">Rabu, 15 Okt 2026</span>
                        </div>

                        <!-- Vertical Cards Timeline -->
                        <div class="mt-3 space-y-3.5">
                            <!-- Card A: 09:00 - 10:00 (Sesi Selesai) -->
                            <div class="rounded-2xl bg-[#1E293B] p-4 border border-slate-800 hover:border-slate-700 transition">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-mono font-bold text-slate-400 flex items-center gap-1.5">
                                        <Clock class="w-3.5 h-3.5 text-slate-500" /> 09:00 - 10:00
                                    </span>
                                    <!-- Green check badge: "Verified +Rp 150.000" -->
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-bold text-[#10B981] border border-emerald-500/30">
                                        <CheckCircle2 class="w-3.5 h-3.5 text-[#10B981]" /> Verified +Rp 150.000
                                    </span>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-white">Sesi Selesai • Andi Wijaya</h4>
                                        <p class="text-xs text-slate-400 font-mono mt-0.5">Sisa 4/10 Sesi</p>
                                    </div>
                                    <span class="rounded bg-slate-800 px-2 py-1 text-[11px] text-slate-400 border border-slate-700/50">
                                        Sudah Check-out 10:05
                                    </span>
                                </div>
                            </div>

                            <!-- Card B: 16:00 - 17:00 (Current Active Card with Orange border) -->
                            <div
                                class="rounded-2xl bg-[#1E293B] p-4 border-2 border-[#F97316] shadow-xl shadow-[#F97316]/10 relative overflow-hidden"
                            >
                                <!-- Pulsing LIVE badge -->
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-[#F97316] flex items-center gap-1.5">
                                            <Clock class="w-3.5 h-3.5 text-[#F97316]" /> 16:00 - 17:00
                                        </span>
                                        <span class="inline-flex items-center gap-1 rounded bg-[#F97316] px-1.5 py-0.2 text-[9px] font-extrabold text-white uppercase tracking-wider animate-pulse">
                                            SESI AKTIF
                                        </span>
                                    </div>
                                    <span class="text-[11px] font-mono text-slate-400">Gym Floor</span>
                                </div>

                                <div class="mt-2.5">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-base font-extrabold text-white">Budi Santoso</h4>
                                        <span class="font-mono text-xs font-semibold text-slate-300 bg-slate-800/80 px-2 py-0.5 rounded border border-slate-700">
                                            Sisa 6/10 Sesi
                                        </span>
                                    </div>

                                    <!-- Status Pills Row -->
                                    <div class="mt-2.5 flex items-center gap-2 flex-wrap">
                                        <!-- Medical Alert pill -->
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-bold text-[#10B981] border border-emerald-500/30">
                                            <ShieldCheck class="w-3.5 h-3.5 text-[#10B981]" /> PAR-Q: Aman
                                        </span>
                                        <!-- Gate Status pill in green -->
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-bold text-[#10B981] border border-emerald-500/30">
                                            <MapPin class="w-3.5 h-3.5 text-[#10B981]" /> Sudah Check-in di Gate 15:48
                                        </span>
                                    </div>
                                </div>

                                <!-- Action Buttons: Klaim Sesi Selesai (OTP) & Input Beban / InBody -->
                                <div class="mt-4 grid grid-cols-2 gap-2 pt-3 border-t border-slate-700/60">
                                    <button
                                        @click="
                                            selectedSession = props.sessions[1];
                                            viewMode = 'side_by_side';
                                            fillDemoOtp();
                                        "
                                        class="rounded-xl bg-[#F97316] hover:bg-orange-600 active:scale-[0.98] py-2 px-3 text-xs font-bold text-white shadow-md shadow-[#F97316]/20 transition flex items-center justify-center gap-1.5"
                                    >
                                        <QrCode class="w-3.5 h-3.5" />
                                        Klaim Sesi Selesai (OTP)
                                    </button>
                                    <button
                                        @click="showInBodyModal = true"
                                        class="rounded-xl bg-slate-800 hover:bg-slate-700 active:scale-[0.98] py-2 px-3 text-xs font-bold text-slate-200 border border-slate-600 transition flex items-center justify-center gap-1.5"
                                    >
                                        <Scale class="w-3.5 h-3.5 text-[#F97316]" />
                                        Input Beban / InBody
                                    </button>
                                </div>
                            </div>

                            <!-- Card C: 19:00 - 20:00 (Upcoming Session) -->
                            <div class="rounded-2xl bg-[#1E293B] p-4 border border-slate-800 opacity-80 hover:opacity-100 transition">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-mono font-bold text-slate-400 flex items-center gap-1.5">
                                        <Clock class="w-3.5 h-3.5 text-slate-500" /> 19:00 - 20:00
                                    </span>
                                    <span class="text-[11px] text-slate-400">Jadwal Malam</span>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-white">Citra Dewi</h4>
                                        <p class="text-xs text-slate-400 font-mono mt-0.5">Sisa 11/12 Sesi</p>
                                    </div>
                                    <!-- Gate Status pill in gray -->
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2.5 py-0.5 text-[11px] font-semibold text-slate-400 border border-slate-700">
                                        <MapPin class="w-3.5 h-3.5 text-slate-500" /> Belum Check-in di Gym
                                    </span>
                                </div>
                                <div class="mt-3 pt-2 text-[11px] text-slate-500 flex items-center justify-between border-t border-slate-800">
                                    <span>Klaim PIN OTP terbuka setelah member tap gate</span>
                                    <button disabled class="text-slate-500 text-xs font-semibold cursor-not-allowed">
                                        Terkunci
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PWA Bottom Navigation Bar -->
                    <div class="mt-6 pt-3 border-t border-slate-800">
                        <div class="grid grid-cols-4 gap-1 text-center">
                            <button
                                @click="currentActiveNav = 'timeline'"
                                :class="[
                                    'flex flex-col items-center gap-1 py-1 text-[10px] font-semibold transition',
                                    currentActiveNav === 'timeline' ? 'text-[#F97316]' : 'text-slate-400 hover:text-slate-200',
                                ]"
                            >
                                <Clock class="w-4 h-4" />
                                <span>Timeline</span>
                            </button>
                            <button
                                @click="currentActiveNav = 'clients'"
                                :class="[
                                    'flex flex-col items-center gap-1 py-1 text-[10px] font-semibold transition',
                                    currentActiveNav === 'clients' ? 'text-[#F97316]' : 'text-slate-400 hover:text-slate-200',
                                ]"
                            >
                                <Users class="w-4 h-4" />
                                <span>Klien Saya</span>
                            </button>
                            <button
                                @click="currentActiveNav = 'commissions'"
                                :class="[
                                    'flex flex-col items-center gap-1 py-1 text-[10px] font-semibold transition',
                                    currentActiveNav === 'commissions' ? 'text-[#F97316]' : 'text-slate-400 hover:text-slate-200',
                                ]"
                            >
                                <DollarSign class="w-4 h-4" />
                                <span>Komisi</span>
                            </button>
                            <button
                                @click="currentActiveNav = 'profile'"
                                :class="[
                                    'flex flex-col items-center gap-1 py-1 text-[10px] font-semibold transition',
                                    currentActiveNav === 'profile' ? 'text-[#F97316]' : 'text-slate-400 hover:text-slate-200',
                                ]"
                            >
                                <User class="w-4 h-4" />
                                <span>Profil</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- SCREEN 2: Anti-Fraud PT Session Claim Modal (OTP Verification)            -->
                <!-- ========================================================================= -->
                <div
                    v-if="viewMode === 'side_by_side' || viewMode === 'screen2'"
                    class="w-full max-w-md mx-auto rounded-[2.5rem] border-4 border-slate-700/60 bg-[#0F172A] p-5 shadow-2xl relative overflow-hidden flex flex-col justify-between min-h-[820px]"
                >
                    <!-- Device Frame Notch Indicator -->
                    <div class="mb-3 flex items-center justify-between text-xs text-slate-500 font-mono border-b border-slate-800 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#10B981]"></span>
                            <span class="font-bold text-slate-300">SCREEN 2: Anti-Fraud Session Claim Modal</span>
                        </div>
                        <span class="text-[#10B981] font-semibold text-[11px]">DUAL GEO-PRESENCE</span>
                    </div>

                    <div class="space-y-4">
                        <!-- Top Header Info -->
                        <div class="flex items-center justify-between pt-1">
                            <div>
                                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                                    <Shield class="w-4 h-4 text-[#F97316]" /> Verifikasi Sesi PT
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Klaim komisi kehadiran & anti-fraud validasi</p>
                            </div>
                            <span class="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-mono text-[#F97316] border border-slate-700">
                                Sesi #5 / 10
                            </span>
                        </div>

                        <!-- Client Summary Compact Box -->
                        <div class="rounded-xl bg-[#1E293B] p-3 border border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center font-bold text-white text-xs border border-slate-600">
                                    BS
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white leading-tight">Budi Santoso</h4>
                                    <p class="text-xs text-slate-400 font-mono">Paket PT: 10 Sesi (Sisa 6)</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-[#10B981] border border-emerald-500/30">
                                Active Session
                            </span>
                        </div>

                        <!-- 1. Security Pre-check Banner (Green) -->
                        <div class="rounded-2xl bg-emerald-500/10 border-2 border-emerald-500/30 p-4 relative overflow-hidden shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="rounded-xl bg-emerald-500/20 p-2 text-[#10B981] shrink-0 border border-emerald-500/30">
                                    <ShieldCheck class="w-5 h-5 text-[#10B981]" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <h4 class="text-xs font-bold text-emerald-400 uppercase tracking-wider">
                                            Validasi Kehadiran Fisik Lolos
                                        </h4>
                                    </div>
                                    <!-- Explicit Text requested in prompt -->
                                    <p class="text-xs font-medium text-emerald-200 mt-1 leading-relaxed">
                                        Validasi Fisik: Member (Budi Santoso) &amp; PT (Coach Ade) tercatat sudah Check-in di pintu gym hari ini.
                                    </p>
                                    <div class="mt-2.5 pt-2 border-t border-emerald-500/20 flex items-center justify-between text-[10px] font-mono text-emerald-300">
                                        <span>Gate Member: 15:48 WIB</span>
                                        <span>•</span>
                                        <span>Gate Trainer: 08:32 WIB</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. 6-Digit OTP Input Boxes -->
                        <div class="rounded-2xl bg-[#1E293B] p-4 border border-slate-800 shadow-md">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <label class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <QrCode class="w-3.5 h-3.5 text-[#F97316]" />
                                    Masukkan 6 Digit PIN dari layar QR HP Member:
                                </label>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="isPtCameraModalOpen = true"
                                        class="inline-flex items-center gap-1 rounded-lg bg-[#F97316]/20 hover:bg-[#F97316]/30 text-[#F97316] border border-[#F97316]/40 px-2 py-0.5 text-[10px] font-bold transition cursor-pointer"
                                        title="Scan QR Code Member dengan Kamera"
                                    >
                                        <Camera class="w-3 h-3" />
                                        <span>📷 Scan Kamera QR</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="fillDemoOtp"
                                        class="text-[10px] font-bold text-slate-400 hover:text-[#F97316] hover:underline cursor-pointer"
                                    >
                                        Isi Demo [849201]
                                    </button>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Minta member membuka tombol tengah <span class="text-lime-400 font-semibold">[QR PASS]</span> pada aplikasi mobile gymapp mereka.
                            </p>

                            <!-- The 6 Digit Boxes: [8] [4] [9] - [2] [0] [1] -->
                            <div class="mt-4 flex items-center justify-center gap-2">
                                <input
                                    v-for="(digit, idx) in otpDigits.slice(0, 3)"
                                    :key="idx"
                                    :id="`otp-box-${idx}`"
                                    type="text"
                                    maxlength="1"
                                    :value="digit"
                                    @input="handleDigitInput(idx, $event)"
                                    @keydown="handleDigitKeyDown(idx, $event)"
                                    class="w-11 h-13 rounded-xl bg-slate-900 border-2 border-slate-700 focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 text-center font-mono text-xl font-bold text-white shadow-inner transition outline-none"
                                />
                                <span class="text-slate-500 font-bold text-xl px-0.5">-</span>
                                <input
                                    v-for="(digit, idx) in otpDigits.slice(3, 6)"
                                    :key="idx + 3"
                                    :id="`otp-box-${idx + 3}`"
                                    type="text"
                                    maxlength="1"
                                    :value="digit"
                                    @input="handleDigitInput(idx + 3, $event)"
                                    @keydown="handleDigitKeyDown(idx + 3, $event)"
                                    class="w-11 h-13 rounded-xl bg-slate-900 border-2 border-slate-700 focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 text-center font-mono text-xl font-bold text-white shadow-inner transition outline-none"
                                />
                            </div>
                        </div>

                        <!-- 3. Substitute Trainer Toggle -->
                        <div class="rounded-2xl bg-[#1E293B] p-4 border border-slate-800">
                            <label class="flex items-center justify-between cursor-pointer">
                                <div class="pr-3">
                                    <span class="text-xs font-bold text-slate-200 block">
                                        Apakah Anda PT Pengganti (Substitute) untuk sesi ini?
                                    </span>
                                    <span class="text-[11px] text-slate-400 block mt-0.5">
                                        Aktifkan jika Coach reguler berhalangan hadir.
                                    </span>
                                </div>
                                <!-- Modern Switch Toggle -->
                                <button
                                    type="button"
                                    @click="isSubstitute = !isSubstitute"
                                    :class="[
                                        'w-11 h-6 flex items-center rounded-full p-1 transition-colors duration-300 shrink-0',
                                        isSubstitute ? 'bg-[#F97316]' : 'bg-slate-700',
                                    ]"
                                >
                                    <div
                                        :class="[
                                            'bg-white w-4 h-4 rounded-full shadow-md transform transition-transform duration-300',
                                            isSubstitute ? 'translate-x-5' : 'translate-x-0',
                                        ]"
                                    ></div>
                                </button>
                            </label>

                            <div v-if="isSubstitute" class="mt-2.5 p-2 rounded-lg bg-orange-500/10 border border-orange-500/30 text-[11px] text-orange-300">
                                Catatan Pengganti: Komisi sesi ini akan otomatis dialihkan ke payroll akun Anda setelah diverifikasi.
                            </div>
                        </div>

                        <!-- 4. Quick Workout Notes text area -->
                        <div class="rounded-2xl bg-[#1E293B] p-4 border border-slate-800">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-200">
                                    Catatan Latihan / Beban Terangkat:
                                </label>
                                <span class="text-[10px] font-mono text-slate-500">{{ workoutNotes.length }}/500</span>
                            </div>
                            <textarea
                                v-model="workoutNotes"
                                rows="3"
                                placeholder="Contoh: Bench press 4x8 @70kg, Incline dumbbell 3x10 @22kg. Kondisi fisik prima."
                                class="mt-2 w-full rounded-xl bg-slate-900 border border-slate-700 focus:border-[#F97316] focus:ring-1 focus:ring-[#F97316] p-2.5 text-xs text-slate-200 placeholder-slate-500 resize-none outline-none transition"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Primary Orange Submit Button: "Verifikasi & Cairkan Komisi Sesi" -->
                    <div class="mt-4 pt-2">
                        <button
                            type="button"
                            @click="submitVerification"
                            :disabled="isVerifying"
                            class="w-full rounded-2xl bg-[#F97316] hover:bg-orange-600 active:scale-[0.98] py-3.5 px-4 text-sm font-extrabold text-white shadow-xl shadow-[#F97316]/25 transition-all flex flex-col items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <span v-if="!isVerifying" class="flex items-center gap-2">
                                <CheckCircle2 class="w-4 h-4" /> Verifikasi &amp; Cairkan Komisi Sesi
                            </span>
                            <span v-else class="flex items-center gap-2">
                                <RefreshCw class="w-4 h-4 animate-spin" /> Memvalidasi Gate &amp; PIN...
                            </span>
                            <span class="text-[10px] font-normal text-orange-100 opacity-90 mt-0.5 font-mono">
                                Anti-fraud locked • Komisi Rp 150.000 otomatis masuk payroll
                            </span>
                        </button>

                        <div v-if="verificationSuccess" class="mt-2 p-2 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-center text-xs text-emerald-300 font-bold flex items-center justify-center gap-1.5">
                            <Check class="w-4 h-4 text-[#10B981]" /> Komisi Berhasil Dicairkan ke Saldo Anda!
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- ========================================================================= -->
        <!-- MODAL: Input Beban / InBody Tracker                                        -->
        <!-- ========================================================================= -->
        <div
            v-if="showInBodyModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
        >
            <div class="w-full max-w-md rounded-3xl bg-[#1E293B] border border-slate-700 p-6 shadow-2xl relative text-slate-100">
                <button
                    @click="showInBodyModal = false"
                    class="absolute top-4 right-4 p-2 rounded-full bg-slate-800 text-slate-400 hover:text-white transition"
                >
                    <X class="w-4 h-4" />
                </button>

                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-[#F97316]/20 border border-[#F97316]/30 text-[#F97316]">
                        <Scale class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Input Metrik Latihan &amp; InBody</h3>
                        <p class="text-xs text-slate-400">Member: Budi Santoso • Tanggal: 15 Okt 2026</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3.5">
                    <!-- 3 Core Lifts Row -->
                    <div>
                        <label class="text-xs font-semibold text-slate-300 block mb-1.5">Beban Latihan Utama (kg)</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <span class="text-[10px] text-slate-400">Bench Press</span>
                                <input
                                    v-model="inbodyForm.benchPress"
                                    type="number"
                                    class="mt-1 w-full rounded-xl bg-slate-900 border border-slate-700 p-2 text-center text-sm font-mono font-bold text-white outline-none focus:border-[#F97316]"
                                />
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400">Squat</span>
                                <input
                                    v-model="inbodyForm.squat"
                                    type="number"
                                    class="mt-1 w-full rounded-xl bg-slate-900 border border-slate-700 p-2 text-center text-sm font-mono font-bold text-white outline-none focus:border-[#F97316]"
                                />
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400">Deadlift</span>
                                <input
                                    v-model="inbodyForm.deadlift"
                                    type="number"
                                    class="mt-1 w-full rounded-xl bg-slate-900 border border-slate-700 p-2 text-center text-sm font-mono font-bold text-white outline-none focus:border-[#F97316]"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- InBody Metrics Row -->
                    <div>
                        <label class="text-xs font-semibold text-slate-300 block mb-1.5">Komposisi Tubuh Terkini</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-[10px] text-slate-400">Berat Badan (kg)</span>
                                <input
                                    v-model="inbodyForm.bodyWeight"
                                    type="number"
                                    step="0.1"
                                    class="mt-1 w-full rounded-xl bg-slate-900 border border-slate-700 p-2 text-center text-sm font-mono font-bold text-white outline-none focus:border-[#F97316]"
                                />
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400">Lemak Tubuh (%)</span>
                                <input
                                    v-model="inbodyForm.bodyFat"
                                    type="number"
                                    step="0.1"
                                    class="mt-1 w-full rounded-xl bg-slate-900 border border-slate-700 p-2 text-center text-sm font-mono font-bold text-white outline-none focus:border-[#F97316]"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="text-xs font-semibold text-slate-300 block mb-1">Catatan Evaluasi Coach</label>
                        <textarea
                            v-model="inbodyForm.notes"
                            rows="2"
                            class="w-full rounded-xl bg-slate-900 border border-slate-700 p-2.5 text-xs text-slate-200 outline-none focus:border-[#F97316] resize-none"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-5 flex gap-2">
                    <button
                        @click="showInBodyModal = false"
                        class="flex-1 rounded-xl bg-slate-800 hover:bg-slate-700 py-2.5 text-xs font-semibold text-slate-300 transition"
                    >
                        Batal
                    </button>
                    <button
                        @click="submitInBodyMetrics"
                        :disabled="isSavingInBody"
                        class="flex-1 rounded-xl bg-[#F97316] hover:bg-orange-600 py-2.5 text-xs font-bold text-white shadow-md shadow-[#F97316]/20 transition flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    >
                        <RefreshCw v-if="isSavingInBody" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ isSavingInBody ? 'Menyimpan...' : 'Simpan Metrik' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Camera QR Scanner Modal for PT Session Claim -->
        <div
            v-if="isPtCameraModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-in fade-in"
            @click.self="isPtCameraModalOpen = false"
        >
            <div class="relative w-full max-w-sm rounded-[2rem] bg-[#1E293B] border border-slate-700 p-5 shadow-2xl flex flex-col items-center text-center">
                <button
                    type="button"
                    @click="isPtCameraModalOpen = false"
                    class="absolute top-4 right-4 rounded-full bg-slate-800 p-1.5 text-slate-400 hover:text-white transition cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>

                <div class="flex items-center gap-1.5 text-[#F97316] text-xs font-bold uppercase tracking-wider mb-1">
                    <Camera class="w-4 h-4" />
                    <span>Scanner QR Kehadiran Member</span>
                </div>
                <h3 class="text-base font-extrabold text-white">Scan QR Pass Member</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-[240px]">
                    Arahkan kamera ke layar QR ponsel member untuk verifikasi kehadiran & isi PIN otomatis.
                </p>

                <!-- Camera Viewfinder Box -->
                <div class="relative w-full aspect-square max-w-[240px] rounded-2xl bg-slate-950 border-2 border-slate-700 overflow-hidden shadow-inner my-3 flex items-center justify-center">
                    <video
                        ref="ptVideoRef"
                        autoplay
                        playsinline
                        muted
                        class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300"
                        :class="{ 'opacity-100': ptCameraActive, 'opacity-0': !ptCameraActive }"
                    ></video>

                    <!-- Corner Crosshairs -->
                    <div class="absolute top-2.5 left-2.5 w-5 h-5 border-t-3 border-l-3 border-[#F97316] rounded-tl-md pointer-events-none z-10"></div>
                    <div class="absolute top-2.5 right-2.5 w-5 h-5 border-t-3 border-r-3 border-[#F97316] rounded-tr-md pointer-events-none z-10"></div>
                    <div class="absolute bottom-2.5 left-2.5 w-5 h-5 border-b-3 border-l-3 border-[#F97316] rounded-bl-md pointer-events-none z-10"></div>
                    <div class="absolute bottom-2.5 right-2.5 w-5 h-5 border-b-3 border-r-3 border-[#F97316] rounded-br-md pointer-events-none z-10"></div>

                    <!-- Live Indicator Pill -->
                    <div
                        v-if="ptCameraActive && !ptScanSuccess"
                        class="absolute top-2.5 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1.5 rounded-full bg-black/80 px-2 py-0.5 text-[9px] font-mono text-[#F97316] border border-[#F97316]/40 backdrop-blur pointer-events-none"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F97316] animate-ping"></span>
                        <span>KAMERA LIVE AKTIF</span>
                    </div>

                    <!-- Inactive Notice -->
                    <div v-if="!ptCameraActive && !ptScanSuccess" class="flex flex-col items-center gap-1.5 text-slate-400 z-10 p-3 text-center">
                        <Camera class="w-8 h-8 text-[#F97316] animate-pulse" />
                        <span class="text-[11px] font-semibold">{{ ptCameraError || 'Menghubungkan kamera...' }}</span>
                        <button
                            v-if="ptCameraError"
                            type="button"
                            @click="startPtCamera"
                            class="text-[10px] text-[#F97316] underline font-bold mt-1 cursor-pointer"
                        >
                            Hubungkan Ulang
                        </button>
                    </div>

                    <!-- Laser Scan Line -->
                    <div
                        v-if="!ptScanSuccess"
                        class="absolute left-3 right-3 h-0.5 bg-gradient-to-r from-transparent via-[#F97316] to-transparent shadow-[0_0_12px_#F97316] animate-pulse z-10 pointer-events-none"
                        style="top: 48%;"
                    ></div>

                    <!-- Success Overlay -->
                    <div v-if="ptScanSuccess" class="absolute inset-0 bg-slate-950/95 p-3 flex flex-col items-center justify-center text-center animate-in zoom-in-95 duration-200 z-20">
                        <div class="h-10 w-10 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/40 mb-1.5">
                            <CheckCircle2 class="w-6 h-6" />
                        </div>
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-400">QR Berhasil Terbaca!</span>
                        <p class="text-[11px] text-slate-200 mt-1">{{ ptScanSuccess }}</p>
                    </div>
                </div>

                <div class="w-full flex gap-2 mt-2">
                    <button
                        type="button"
                        @click="processPtScannedQr('849201')"
                        class="flex-1 rounded-xl bg-slate-800 hover:bg-slate-700 py-2 text-xs font-semibold text-slate-300 transition cursor-pointer"
                    >
                        ⚡ Simulasikan Scan
                    </button>
                    <button
                        type="button"
                        @click="isPtCameraModalOpen = false"
                        class="flex-1 rounded-xl bg-slate-700 hover:bg-slate-600 py-2 text-xs font-semibold text-white transition cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
