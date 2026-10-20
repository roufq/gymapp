<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    Award,
    BatteryCharging,
    Bell,
    Calendar,
    Camera,
    Check,
    CheckCircle2,
    ChevronRight,
    Clock,
    Dumbbell,
    Eye,
    EyeOff,
    Flame,
    Ghost,
    HeartPulse,
    Home,
    Info,
    LineChart,
    Lock,
    LogOut,
    MapPin,
    Pause,
    Play,
    Plus,
    QrCode,
    RefreshCw,
    RotateCcw,
    Scan,
    Shield,
    ShieldCheck,
    Sparkles,
    Timer,
    Trash2,
    TrendingDown,
    TrendingUp,
    User,
    Users,
    X,
    Zap,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface Props {
    member: {
        name: string;
        email: string;
        phone: string;
        photo_url?: string | null;
        no_show_strike_count: number;
        unpaid_fine_amount: number;
        home_branch_name: string;
    };
    subscription: {
        id?: number | null;
        plan_name: string;
        plan_badge: string;
        days_remaining: number;
        end_date: string;
        remaining_quota: number | null;
        remaining_freeze_days?: number;
        status: string;
    };
    crowdMeter: {
        branch_name: string;
        current: number;
        max: number;
        rate: number;
        label: string;
    };
    livePresence?: {
        total_in_gym: number;
        is_ghost_mode: boolean;
        attendees: Array<{
            id: number;
            name: string;
            role: string;
            is_trainer: boolean;
            is_you: boolean;
            check_in_time: string;
            duration_text: string;
            photo_url?: string | null;
        }>;
    };
    readiness?: {
        score: number;
        status: string;
        notes: string;
    };
    muscleRecovery?: Array<{
        name: string;
        status: 'ready' | 'recovering' | 'fatigued';
        label: string;
        percent: number;
        hours_ago: number | null;
        last_trained: string;
    }>;
    recentWorkouts?: Array<{
        id: number;
        exercise_name: string;
        muscle_group: string;
        set_number: number;
        weight_kg: number;
        reps: number;
        rpe?: number | null;
        notes?: string | null;
        date_text: string;
    }>;
    streakDays?: number;
    upcomingSchedule: {
        booking_id?: number | null;
        class_name: string;
        time_text: string;
        can_cancel: boolean;
    };
    ptSummary: {
        total_sessions: number;
        remaining_sessions: number;
        trainer_name: string;
        pin_otp: string;
    };
    metrics: {
        weight: number;
        weight_diff: number;
        muscle_mass: number;
        muscle_diff: number;
        body_fat: number;
        fat_diff: number;
        chart_data: Array<{ month: string; weight: number; muscle: number }>;
    };
    timelineClasses?: Array<any>;
}

const props = defineProps<Props>();

// Modal & Navigation States
const isQrModalOpen = ref(false);
const isProfileModalOpen = ref(false);
const isFreezeModalOpen = ref(false);
const isSubmittingFreeze = ref(false);
const todayStr = new Date().toISOString().split('T')[0];
const freezeForm = ref({
    freeze_start: todayStr,
    freeze_end: '',
    reason: 'Dinas luar kota / Istirahat',
});

// Community Radar (Live Presence) State
const isAttendeesListOpen = ref(false);
const isTogglingGhost = ref(false);

function toggleGhostMode() {
    isTogglingGhost.value = true;
    router.post('/member/toggle-ghost-mode', {}, {
        preserveScroll: true,
        onFinish: () => {
            isTogglingGhost.value = false;
        },
    });
}

// Daily Readiness & Stability State
const isReadinessModalOpen = ref(false);
const isSubmittingReadiness = ref(false);
const readinessSleep = ref(4);
const readinessDoms = ref<'none' | 'mild' | 'heavy'>('none');
const readinessEnergy = ref<'high' | 'medium' | 'low'>('high');
const readinessNotesInput = ref('');

const calculatedReadinessScore = computed(() => {
    let base = 50;
    base += readinessSleep.value * 6;
    if (readinessDoms.value === 'none') base += 12;
    else if (readinessDoms.value === 'mild') base += 4;
    else base -= 15;

    if (readinessEnergy.value === 'high') base += 14;
    else if (readinessEnergy.value === 'medium') base += 5;
    else base -= 10;

    return Math.min(100, Math.max(25, base));
});

function submitReadiness() {
    isSubmittingReadiness.value = true;
    router.post('/member/readiness', {
        score: calculatedReadinessScore.value,
        notes: readinessNotesInput.value || `Kesiapan Mandiri: Tidur ${readinessSleep.value}/5, Energi ${readinessEnergy.value}, Nyeri Otot ${readinessDoms.value}.`,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingReadiness.value = false;
            isReadinessModalOpen.value = false;
        },
        onError: () => {
            isSubmittingReadiness.value = false;
        },
    });
}

// Workout Logger State
const isWorkoutModalOpen = ref(false);
const isSubmittingWorkout = ref(false);
const workoutForm = ref({
    exercise_name: '',
    muscle_group: 'Dada',
    set_number: 1,
    weight_kg: 40,
    reps: 10,
    rpe: 8,
    notes: '',
});

function submitWorkoutLog() {
    if (!workoutForm.value.exercise_name) {
        alert('Mohon masukkan nama latihan.');
        return;
    }
    isSubmittingWorkout.value = true;
    router.post('/member/workout-log', workoutForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingWorkout.value = false;
            isWorkoutModalOpen.value = false;
            workoutForm.value.exercise_name = '';
            workoutForm.value.notes = '';
            // Auto start rest timer for convenient gym flow
            startRestTimer(90);
        },
        onError: () => {
            isSubmittingWorkout.value = false;
        },
    });
}

function deleteWorkoutLog(id: number) {
    if (confirm('Hapus catatan set latihan ini?')) {
        router.delete(`/member/workout-log/${id}`, { preserveScroll: true });
    }
}

// Smart Rest Timer State
const restTimerSeconds = ref(90);
const restTimerTotal = ref(90);
const isRestTimerRunning = ref(false);
let restTimerInterval: ReturnType<typeof setInterval> | null = null;

function startRestTimer(seconds?: number) {
    if (seconds) {
        restTimerTotal.value = seconds;
        restTimerSeconds.value = seconds;
    }
    if (restTimerInterval) clearInterval(restTimerInterval);
    isRestTimerRunning.value = true;
    restTimerInterval = setInterval(() => {
        if (restTimerSeconds.value > 1) {
            restTimerSeconds.value -= 1;
        } else {
            restTimerSeconds.value = 0;
            isRestTimerRunning.value = false;
            if (restTimerInterval) clearInterval(restTimerInterval);
            // Browser vibrate notification if supported
            if (typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate([200, 100, 200]);
            }
        }
    }, 1000);
}

function pauseRestTimer() {
    isRestTimerRunning.value = false;
    if (restTimerInterval) clearInterval(restTimerInterval);
}

function resetRestTimer() {
    isRestTimerRunning.value = false;
    if (restTimerInterval) clearInterval(restTimerInterval);
    restTimerSeconds.value = restTimerTotal.value;
}

const restTimerProgress = computed(() => {
    if (restTimerTotal.value <= 0) return 0;
    return Math.round(((restTimerTotal.value - restTimerSeconds.value) / restTimerTotal.value) * 100);
});

const restTimerText = computed(() => {
    const mins = Math.floor(restTimerSeconds.value / 60);
    const secs = restTimerSeconds.value % 60;
    return `${mins}:${secs < 10 ? '0' + secs : secs}`;
});

const calculatedFreezeDays = computed(() => {
    if (!freezeForm.value.freeze_start || !freezeForm.value.freeze_end) return 0;
    const start = new Date(freezeForm.value.freeze_start);
    const end = new Date(freezeForm.value.freeze_end);
    if (end < start) return 0;
    const diff = Math.abs(end.getTime() - start.getTime());
    return Math.ceil(diff / (1000 * 60 * 60 * 24)) + 1;
});

function submitFreeze() {
    if (!freezeForm.value.freeze_start || !freezeForm.value.freeze_end) {
        alert('Mohon pilih tanggal mulai dan selesai cuti.');
        return;
    }
    if (calculatedFreezeDays.value <= 0) {
        alert('Tanggal selesai cuti harus sama atau setelah tanggal mulai.');
        return;
    }
    const maxDays = props.subscription.remaining_freeze_days ?? 14;
    if (calculatedFreezeDays.value > maxDays) {
        alert(`Durasi cuti (${calculatedFreezeDays.value} hari) melebihi sisa kuota cuti Anda (${maxDays} hari).`);
        return;
    }

    isSubmittingFreeze.value = true;
    router.post('/subscriptions/freeze', {
        freeze_start: freezeForm.value.freeze_start,
        freeze_end: freezeForm.value.freeze_end,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmittingFreeze.value = false;
            isFreezeModalOpen.value = false;
        },
        onError: () => {
            isSubmittingFreeze.value = false;
        },
    });
}

const qrModalTab = ref<'scanner' | 'pass'>('scanner');
const isScanning = ref(false);
const scanResult = ref<{
    success: boolean;
    locker_number: string;
    message: string;
    time: string;
} | null>(null);

// Live Camera QR Scanner State
const videoScannerRef = ref<HTMLVideoElement | null>(null);
const scannerStream = ref<MediaStream | null>(null);
const isCameraActive = ref(false);
const cameraError = ref<string | null>(null);
let barcodeDetector: any = null;
let scanAnimationFrameId: number | null = null;

async function startCamera() {
    cameraError.value = null;
    try {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraError.value = 'Perangkat tidak mendukung WebRTC Camera API.';
            return;
        }

        let stream: MediaStream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 640 } },
            });
        } catch {
            // Fallback for laptops/desktops without back camera
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
        }

        scannerStream.value = stream;
        if (videoScannerRef.value) {
            videoScannerRef.value.srcObject = stream;
            await videoScannerRef.value.play().catch(() => {});
            isCameraActive.value = true;
            initBarcodeDetector();
        }
    } catch (err: any) {
        cameraError.value = 'Kamera tidak dapat diakses atau izin ditolak.';
        isCameraActive.value = false;
    }
}

function stopCamera() {
    if (scanAnimationFrameId) {
        cancelAnimationFrame(scanAnimationFrameId);
        scanAnimationFrameId = null;
    }
    if (scannerStream.value) {
        scannerStream.value.getTracks().forEach((track) => track.stop());
        scannerStream.value = null;
    }
    if (videoScannerRef.value) {
        videoScannerRef.value.srcObject = null;
    }
    isCameraActive.value = false;
}

function initBarcodeDetector() {
    if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
        try {
            barcodeDetector = new (window as any).BarcodeDetector({
                formats: ['qr_code', 'code_128', 'ean_13'],
            });
            scanVideoFrame();
        } catch {
            barcodeDetector = null;
        }
    }
}

async function scanVideoFrame() {
    if (!isCameraActive.value || !videoScannerRef.value || scanResult.value) return;

    if (barcodeDetector && videoScannerRef.value.readyState >= 2) {
        try {
            const barcodes = await barcodeDetector.detect(videoScannerRef.value);
            if (barcodes && barcodes.length > 0) {
                const rawVal = barcodes[0].rawValue;
                if (rawVal && !isScanning.value) {
                    triggerScanGymQr(rawVal);
                    return;
                }
            }
        } catch {
            // Drop unreadable frames silently
        }
    }

    if (isCameraActive.value && !scanResult.value) {
        scanAnimationFrameId = requestAnimationFrame(scanVideoFrame);
    }
}

watch([isQrModalOpen, qrModalTab], ([open, tab]) => {
    if (open && tab === 'scanner') {
        nextTick(() => {
            startCamera();
        });
    } else {
        stopCamera();
    }
});

const scheduleCategory = ref<'studio' | 'pt'>('studio');
const selectedDayIndex = ref(2); // Wednesday / Mid-week by default
const activeTab = ref<'overview' | 'jadwal' | 'progres'>('overview');

// Member Scan Gym QR Logic
function triggerScanGymQr(qrToken: string = 'GYM-JOGJA-GATE-01') {
    if (isScanning.value) return;
    isScanning.value = true;
    router.post(
        '/member/scan-checkin',
        { qr_token: qrToken },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                isScanning.value = false;
                const flashError = (page.props as any)?.flash?.error;
                const flashSuccess = (page.props as any)?.flash?.success;

                if (flashError) {
                    scanResult.value = {
                        success: false,
                        locker_number: '-',
                        message: flashError,
                        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' WIB',
                    };
                } else {
                    const lockerMatch = flashSuccess ? flashSuccess.match(/L-\d+/) : null;
                    const assignedLocker = lockerMatch ? lockerMatch[0] : 'L-042';

                    scanResult.value = {
                        success: true,
                        locker_number: assignedLocker,
                        message: flashSuccess || 'Check-in Berhasil! Gate terbuka otomatis. Selamat berlatih!',
                        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' WIB',
                    };
                }
                stopCamera();
            },
            onError: (errors) => {
                isScanning.value = false;
                const err = Object.values(errors)[0] || 'Gagal memproses check-in gerbang.';
                scanResult.value = {
                    success: false,
                    locker_number: '-',
                    message: String(err),
                    time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' WIB',
                };
                stopCamera();
            },
        }
    );
}

// Countdown Timer for Dynamic QR Pass (TOTP 60s)
const countdown = ref(48);
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    // Ensure dark theme is applied to document for cohesive athletic dark mode
    document.documentElement.classList.add('dark');

    timer = setInterval(() => {
        if (countdown.value > 1) {
            countdown.value -= 1;
        } else {
            countdown.value = 60;
        }
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
    stopCamera();
});

const countdownText = computed(() => {
    const s = countdown.value;
    return `00:${s < 10 ? '0' + s : s}`;
});

// Horizontal Date Strip Days
const weekDays = [
    { day: 'SEN', date: '13', full: 'Senin, 13 Okt', active: false },
    { day: 'SEL', date: '14', full: 'Selasa, 14 Okt', active: false },
    { day: 'RAB', date: '15', full: 'Rabu, 15 Okt', active: true },
    { day: 'KAM', date: '16', full: 'Kamis, 16 Okt', active: false },
    { day: 'JUM', date: '17', full: 'Jumat, 17 Okt', active: false },
    { day: 'SAB', date: '18', full: 'Sabtu, 18 Okt', active: false },
    { day: 'MIN', date: '19', full: 'Minggu, 19 Okt', active: false },
];

function selectDay(index: number) {
    selectedDayIndex.value = index;
    weekDays.forEach((d, i) => (d.active = i === index));
}

// Booking feedback states
const bookingSuccessMessage = ref<string | null>(null);

function handleBook(classId?: number, className: string = 'Kelas') {
    if (!classId) {
        bookingSuccessMessage.value = `Berhasil memesan kursi untuk kelas ${className}! Tiket masuk tersedia di jadwal Anda.`;
        setTimeout(() => {
            bookingSuccessMessage.value = null;
        }, 4000);
        return;
    }

    router.post(`/classes/${classId}/book`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            bookingSuccessMessage.value = `Berhasil memesan kursi untuk kelas ${className}! Tiket masuk tersedia di jadwal Anda.`;
            setTimeout(() => {
                bookingSuccessMessage.value = null;
            }, 4000);
        },
    });
}

function handleWaitlist(classId?: number, className: string = 'Kelas') {
    if (!classId) {
        bookingSuccessMessage.value = `Anda masuk antrean Waitlist (#3) untuk kelas ${className}. Notifikasi WA akan dikirim jika ada kursi kosong.`;
        setTimeout(() => {
            bookingSuccessMessage.value = null;
        }, 4000);
        return;
    }

    router.post(`/classes/${classId}/book`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            bookingSuccessMessage.value = `Anda masuk antrean Waitlist untuk kelas ${className}. Notifikasi WA akan dikirim jika ada kursi kosong.`;
            setTimeout(() => {
                bookingSuccessMessage.value = null;
            }, 4000);
        },
    });
}

function handleCancelBooking(bookingId?: number | null) {
    if (!bookingId) {
        bookingSuccessMessage.value = 'Pembatalan berhasil diproses tanpa penalti strike (>12 jam). Kuota Anda telah dikembalikan.';
        setTimeout(() => {
            bookingSuccessMessage.value = null;
        }, 4000);
        return;
    }

    router.post(`/classes/bookings/${bookingId}/cancel`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            bookingSuccessMessage.value = 'Pemesanan kelas berhasil dibatalkan tanpa penalti (>12 jam).';
            setTimeout(() => {
                bookingSuccessMessage.value = null;
            }, 4000);
        },
    });
}
</script>

<template>
    <div class="min-h-screen bg-[#09090B] text-zinc-100 font-sans selection:bg-[#A3E635] selection:text-zinc-950 pb-24 md:pb-12">
        <!-- Toast Notification Alert -->
        <div v-if="bookingSuccessMessage" class="fixed top-4 right-4 z-50 max-w-md animate-in fade-in slide-in-from-top-4 duration-300">
            <div class="rounded-2xl bg-zinc-900 border-2 border-[#A3E635] p-4 text-xs shadow-2xl text-white flex items-start gap-3">
                <CheckCircle2 class="h-5 w-5 text-[#A3E635] shrink-0" />
                <div class="flex-1">
                    <span class="font-bold text-[#A3E635] block">Konfirmasi Booking</span>
                    <p class="text-zinc-300 mt-0.5 leading-relaxed">{{ bookingSuccessMessage }}</p>
                </div>
                <button @click="bookingSuccessMessage = null" class="text-zinc-400 hover:text-white">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- MAIN RESPONSIVE CONTAINER (Full-Width Desktop & Seamless Mobile) -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-6">
            <!-- ========================================================================= -->
            <!-- 1. TOP HEADER: Member Avatar, Greeting, Membership Badges & Action        -->
            <!-- ========================================================================= -->
            <header class="rounded-3xl bg-[#18181B] border border-[#27272A] p-5 sm:p-6 shadow-xl relative overflow-hidden">
                <!-- Ambient lime glow corner decoration -->
                <div class="absolute -top-16 -right-16 w-56 h-56 bg-[#A3E635]/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-5 relative z-10">
                    <!-- Left: Avatar, Name & Membership Status -->
                    <div class="flex items-center gap-4">
                        <div class="relative shrink-0">
                            <div class="h-16 w-16 rounded-2xl bg-gradient-to-tr from-zinc-800 to-zinc-700 flex items-center justify-center border-2 border-[#27272A] font-extrabold text-[#A3E635] text-xl shadow-lg">
                                {{ props.member.name.slice(0, 2).toUpperCase() }}
                            </div>
                            <div class="absolute -bottom-1 -right-1 rounded-full bg-[#18181B] p-1 border border-[#27272A] shadow" title="Device Locked: 1 HP 1 Akun">
                                <Lock class="h-3.5 w-3.5 text-[#A3E635]" />
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">Halo, {{ props.member.name }}</h1>
                                <span class="hidden sm:inline-block h-2 w-2 rounded-full bg-[#10B981] animate-pulse"></span>
                            </div>

                            <div class="mt-1.5 flex items-center gap-2 flex-wrap">
                                <!-- Membership Badge: "PLATINUM ALL-CLUB • ACTIVE" -->
                                <span class="rounded-full bg-[#10B981]/15 px-3 py-1 text-xs font-extrabold tracking-wider text-[#10B981] border border-[#10B981]/30">
                                    {{ props.subscription.plan_badge }}
                                </span>

                                <!-- Remaining Days Pill: "18 Days Left" -->
                                <span class="rounded-full bg-[#F59E0B]/15 px-3 py-1 text-xs font-mono font-bold text-[#F59E0B] border border-[#F59E0B]/30 flex items-center gap-1">
                                    <Clock class="h-3 w-3" /> {{ props.subscription.days_remaining }} Days Left
                                </span>

                                <!-- Cuti (Freeze) Action Pill Button -->
                                <button
                                    type="button"
                                    @click="isFreezeModalOpen = true"
                                    class="rounded-full bg-sky-500/15 hover:bg-sky-500/25 px-3 py-1 text-xs font-bold text-sky-400 border border-sky-500/30 flex items-center gap-1.5 transition active:scale-95 cursor-pointer"
                                    title="Ajukan Cuti / Freeze Paket Mandiri"
                                >
                                    <span>❄️ Cuti Mandiri ({{ props.subscription.remaining_freeze_days ?? 14 }} Hari)</span>
                                </button>

                                <span class="text-xs text-zinc-400 hidden lg:inline">
                                    • {{ props.member.home_branch_name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Quick Actions & Big Glowing QR Pass Trigger Button -->
                    <div class="flex items-center gap-3">
                        <button class="relative rounded-2xl bg-zinc-800/80 hover:bg-zinc-800 p-3 text-zinc-400 hover:text-white border border-[#27272A] transition">
                            <Bell class="h-5 w-5" />
                            <span class="absolute top-2.5 right-2.5 h-2 w-2 rounded-full bg-[#E11D48]"></span>
                        </button>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="rounded-2xl bg-zinc-800/80 hover:bg-zinc-800 p-3 text-zinc-400 hover:text-rose-400 border border-[#27272A] transition cursor-pointer"
                            title="Keluar / Logout"
                        >
                            <LogOut class="h-5 w-5" />
                        </Link>

                        <!-- PROMINENT GLOWING ELECTRIC LIME BUTTON: SCAN QR ABSEN GYM -->
                        <button
                            type="button"
                            @click="isQrModalOpen = true; qrModalTab = 'scanner'; scanResult = null"
                            class="flex-1 sm:flex-initial rounded-2xl bg-[#A3E635] hover:bg-lime-400 active:scale-[0.98] px-5 py-3 text-sm font-black text-zinc-950 shadow-xl shadow-[#A3E635]/25 transition-all flex items-center justify-center gap-2.5 cursor-pointer"
                        >
                            <Camera class="h-5 w-5 text-zinc-950" />
                            <span>Scan QR Absen Masuk Gym</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- ========================================================================= -->
            <!-- 2. MAIN DASHBOARD CONTENT GRID (Desktop 12-Cols / Tablet / Mobile)        -->
            <!-- ========================================================================= -->
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- ===================================================================== -->
                <!-- LEFT COLUMN (4 Cols): Crowd Meter + PT Package Summary + Strike Banner -->
                <!-- ===================================================================== -->
                <div class="lg:col-span-4 space-y-6">
                    <!-- 2. Live Gym Crowd Meter Card -->
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-lg">
                        <div class="flex items-center justify-between text-xs pb-1">
                            <span class="font-bold text-zinc-300 flex items-center gap-1.5">
                                <Users class="h-4 w-4 text-[#A3E635]" /> Kepadatan Gym Saat Ini
                            </span>
                            <span class="font-mono font-extrabold text-[#10B981] text-sm">{{ props.crowdMeter.rate }}%</span>
                        </div>
                        <p class="text-[11px] text-zinc-400 mt-0.5">{{ props.crowdMeter.branch_name }}</p>

                        <!-- Horizontal Progress Bar (42% in Emerald Green) -->
                        <div class="mt-3.5 h-3 w-full rounded-full bg-zinc-900/90 overflow-hidden p-0.5 border border-zinc-800">
                            <div
                                class="h-full rounded-full bg-[#10B981] shadow-sm transition-all duration-700"
                                :style="{ width: `${props.crowdMeter.rate}%` }"
                            ></div>
                        </div>

                        <!-- Subtitle: "38/100 Member di dalam • Jam Ideal Latihan" -->
                        <div class="mt-3 flex items-center justify-between text-xs text-zinc-400">
                            <span class="font-mono">{{ props.crowdMeter.current }}/{{ props.crowdMeter.max }} Member di dalam</span>
                            <span class="text-[#10B981] font-bold flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#10B981]"></span>
                                {{ props.crowdMeter.label }}
                            </span>
                        </div>
                    </div>

                    <!-- Live Community Radar ("Siapa yang Sedang di Gym?") -->
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-lg relative overflow-hidden">
                        <div class="flex items-center justify-between pb-3 border-b border-[#27272A]">
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                </span>
                                <h3 class="text-xs font-bold text-zinc-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <Users class="h-4 w-4 text-[#A3E635]" /> Radar Gym Aktif
                                </h3>
                            </div>
                            <span class="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-mono font-bold text-emerald-400 border border-emerald-500/20">
                                {{ props.livePresence?.total_in_gym ?? props.crowdMeter.current }} Orang di Gym
                            </span>
                        </div>

                        <!-- Ghost Mode Toggle Strip -->
                        <div class="mt-3.5 p-3 rounded-2xl bg-zinc-900/90 border border-zinc-800 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <Ghost class="h-4 w-4" :class="props.livePresence?.is_ghost_mode ? 'text-sky-400' : 'text-zinc-400'" />
                                <div>
                                    <span class="font-bold text-white block">Ghost Mode</span>
                                    <span class="text-[10px] text-zinc-400">
                                        {{ props.livePresence?.is_ghost_mode ? 'Profil Anda tersembunyi dari radar' : 'Nama Anda terlihat di radar gym' }}
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="toggleGhostMode"
                                :disabled="isTogglingGhost"
                                class="rounded-xl px-2.5 py-1.5 text-[11px] font-bold transition flex items-center gap-1 border cursor-pointer"
                                :class="props.livePresence?.is_ghost_mode
                                    ? 'bg-sky-500/20 text-sky-300 border-sky-500/30 hover:bg-sky-500/30'
                                    : 'bg-zinc-800 text-zinc-300 border-zinc-700 hover:bg-zinc-700'"
                            >
                                <EyeOff v-if="props.livePresence?.is_ghost_mode" class="h-3 w-3 text-sky-400" />
                                <Eye v-else class="h-3 w-3 text-zinc-400" />
                                <span>{{ props.livePresence?.is_ghost_mode ? 'Hantu ON' : 'Terlihat' }}</span>
                            </button>
                        </div>

                        <!-- Attendee Avatars List Preview -->
                        <div class="mt-3.5 space-y-2">
                            <div
                                v-for="attendee in (props.livePresence?.attendees || []).slice(0, 3)"
                                :key="attendee.id"
                                class="flex items-center justify-between p-2 rounded-xl bg-zinc-900/50 border border-zinc-800/80 text-xs"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-lg bg-gradient-to-tr from-zinc-800 to-zinc-700 flex items-center justify-center font-bold text-[11px] text-[#A3E635] border border-zinc-700">
                                        {{ attendee.name.slice(0, 2).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold text-white">{{ attendee.name }}</span>
                                            <span
                                                v-if="attendee.is_trainer"
                                                class="rounded px-1.5 py-0.2 text-[9px] font-bold bg-[#A3E635]/20 text-[#A3E635] border border-[#A3E635]/30"
                                            >
                                                COACH
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-zinc-400">Masuk {{ attendee.check_in_time }} • {{ attendee.duration_text }}</span>
                                    </div>
                                </div>
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            </div>
                        </div>

                        <!-- Button to open full attendees modal -->
                        <button
                            type="button"
                            @click="isAttendeesListOpen = true"
                            class="mt-3 w-full py-2 rounded-xl bg-zinc-800/70 hover:bg-zinc-800 border border-zinc-700/60 text-xs font-bold text-zinc-300 hover:text-white transition flex items-center justify-center gap-1 cursor-pointer"
                        >
                            <span>Lihat Semua yang di Gym ({{ props.livePresence?.attendees?.length ?? 0 }})</span>
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Kesiapan & Kestabilan Tubuh (Readiness & Body Stability) -->
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-lg relative overflow-hidden">
                        <div class="flex items-center justify-between pb-3 border-b border-[#27272A]">
                            <h3 class="text-xs font-bold text-zinc-200 uppercase tracking-wider flex items-center gap-1.5">
                                <HeartPulse class="h-4 w-4 text-rose-400" /> Kesiapan &amp; Kestabilan Tubuh
                            </h3>
                            <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-mono text-zinc-400">Harian</span>
                        </div>

                        <div class="mt-4 flex items-center gap-4">
                            <!-- Circular Score Ring -->
                            <div class="relative h-18 w-18 shrink-0 flex items-center justify-center">
                                <svg class="h-18 w-18 -rotate-90" viewBox="0 0 36 36">
                                    <path
                                        class="text-zinc-800"
                                        stroke-width="3.5"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                    <path
                                        class="text-[#A3E635]"
                                        stroke-width="3.5"
                                        :stroke-dasharray="`${props.readiness?.score ?? 85}, 100`"
                                        stroke-linecap="round"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                </svg>
                                <div class="absolute font-mono text-sm font-black text-white">
                                    {{ props.readiness?.score ?? 85 }}%
                                </div>
                            </div>

                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider border"
                                        :class="(props.readiness?.score ?? 85) >= 80
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                                            : ((props.readiness?.score ?? 85) >= 60
                                                ? 'bg-amber-500/10 text-amber-400 border-amber-500/30'
                                                : 'bg-rose-500/10 text-rose-400 border-rose-500/30')"
                                    >
                                        {{ props.readiness?.status ?? 'Optimal & Prima' }}
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-300 mt-1 line-clamp-2 leading-relaxed">
                                    {{ props.readiness?.notes ?? 'Sistem saraf & stabilitas otot optimal. Siap latihan beban hari ini.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Attendance Streak Pill -->
                        <div class="mt-4 p-2.5 rounded-xl bg-zinc-900/80 border border-zinc-800 flex items-center justify-between text-xs">
                            <span class="text-zinc-400 flex items-center gap-1.5">
                                <Flame class="h-4 w-4 text-[#F59E0B]" /> Konsistensi Latihan:
                            </span>
                            <span class="font-bold text-white font-mono">{{ props.streakDays ?? 6 }} Hari Bulan Ini</span>
                        </div>

                        <!-- Check In Button -->
                        <button
                            type="button"
                            @click="isReadinessModalOpen = true"
                            class="mt-3 w-full py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 hover:text-white font-bold text-xs border border-zinc-700 transition flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <Sparkles class="h-3.5 w-3.5 text-[#A3E635]" />
                            <span>Cek Kesiapan Tubuh Hari Ini</span>
                        </button>
                    </div>

                    <!-- 4. Personal Trainer (PT) Package Summary Card -->
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-lg relative overflow-hidden">
                        <div class="flex items-center justify-between pb-3 border-b border-[#27272A]">
                            <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-1.5">
                                <Dumbbell class="h-4 w-4 text-[#A3E635]" /> Sesi Personal Trainer (PT)
                            </h3>
                            <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-mono text-zinc-400">Aktif</span>
                        </div>

                        <div class="mt-4 flex items-center gap-4">
                            <!-- Circular Progress SVG: "6 dari 10 Sesi Tersisa" -->
                            <div class="relative h-18 w-18 shrink-0 flex items-center justify-center">
                                <svg class="h-18 w-18 -rotate-90" viewBox="0 0 36 36">
                                    <path
                                        class="text-zinc-800"
                                        stroke-width="3.5"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                    <path
                                        class="text-[#A3E635]"
                                        stroke-width="3.5"
                                        stroke-dasharray="60, 100"
                                        stroke-linecap="round"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                </svg>
                                <div class="absolute font-mono text-sm font-black text-[#A3E635]">6/10</div>
                            </div>

                            <div>
                                <span class="text-sm font-extrabold text-white block">6 dari 10 Sesi Tersisa</span>
                                <span class="text-xs text-zinc-400 mt-0.5 block">Dedicated Coach: <strong class="text-white">{{ props.ptSummary.trainer_name }}</strong></span>
                                <span class="text-[11px] text-zinc-500 font-mono mt-0.5 block">PIN Klaim: {{ props.ptSummary.pin_otp }}</span>
                            </div>
                        </div>

                        <!-- Action Buttons: "+ Book Sesi PT" & "Catatan InBody" -->
                        <div class="mt-4 pt-3 border-t border-[#27272A] grid grid-cols-2 gap-2">
                            <button
                                @click="handleBook('Sesi Personal Trainer dengan ' + props.ptSummary.trainer_name)"
                                class="rounded-xl bg-[#A3E635] hover:bg-lime-400 text-zinc-950 font-extrabold py-2 px-3 text-xs transition flex items-center justify-center gap-1 shadow-sm"
                            >
                                + Book Sesi PT
                            </button>
                            <button
                                @click="activeTab = 'progres'"
                                class="rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-bold py-2 px-3 text-xs border border-[#27272A] transition flex items-center justify-center gap-1"
                            >
                                Catatan InBody
                            </button>
                        </div>
                    </div>

                    <!-- Penalty & Strike Status Banner -->
                    <div class="rounded-2xl bg-zinc-900 border border-[#27272A] p-4 text-xs">
                        <div class="flex items-start gap-2.5">
                            <Shield class="h-4 w-4 text-[#F59E0B] shrink-0 mt-0.5" />
                            <div>
                                <span class="font-bold text-white block">Ketentuan Reservasi &amp; No-Show</span>
                                <p class="text-zinc-400 mt-1 leading-relaxed">
                                    Status Strike No-Show: <strong class="text-emerald-400 font-mono">0/3</strong> bulan ini. Pembatalan wajib <strong class="text-[#A3E635]">&gt;12 jam</strong> sebelum kelas dimulai.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===================================================================== -->
                <!-- RIGHT COLUMN (8 Cols): Upcoming Schedule + Class Booking Schedule    -->
                <!-- ===================================================================== -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- 1. Jadwal Latihan & Recovery Heatmap (Visual Otot) Card -->
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-xl relative overflow-hidden">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#27272A]">
                            <div>
                                <h3 class="text-sm sm:text-base font-extrabold text-white flex items-center gap-2">
                                    <Dumbbell class="h-5 w-5 text-[#A3E635]" /> Jadwal Otot &amp; Visual Recovery Heatmap
                                </h3>
                                <p class="text-xs text-zinc-400 mt-0.5">Pantau kesiapan pemulihan serat otot &amp; hindari overtraining</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="isWorkoutModalOpen = true"
                                    class="rounded-xl bg-[#A3E635] hover:bg-lime-400 text-zinc-950 font-black px-3.5 py-2 text-xs transition shadow-md flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Plus class="h-3.5 w-3.5 stroke-[3]" />
                                    <span>Catat Latihan (Log Set)</span>
                                </button>
                            </div>
                        </div>

                        <!-- Smart Rest Timer Bar -->
                        <div class="mt-4 p-3.5 rounded-2xl bg-zinc-900/90 border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-xl bg-zinc-800 border border-zinc-700 text-[#A3E635]">
                                    <Timer class="h-5 w-5" />
                                </div>
                                <div>
                                    <span class="text-xs font-bold text-white block">Smart Rest Timer Antar Set</span>
                                    <span class="text-[11px] text-zinc-400">Istirahat optimal: 60s - 120s</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="font-mono text-lg font-black tracking-wider" :class="restTimerSeconds <= 10 && isRestTimerRunning ? 'text-rose-400 animate-pulse' : 'text-[#A3E635]'">
                                    {{ restTimerText }}
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <button
                                        v-if="!isRestTimerRunning"
                                        type="button"
                                        @click="startRestTimer()"
                                        class="p-2 rounded-xl bg-[#A3E635] text-zinc-950 font-bold hover:bg-lime-400 transition cursor-pointer"
                                        title="Mulai Timer"
                                    >
                                        <Play class="h-3.5 w-3.5 fill-current" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        @click="pauseRestTimer()"
                                        class="p-2 rounded-xl bg-amber-500 text-zinc-950 font-bold hover:bg-amber-400 transition cursor-pointer"
                                        title="Jeda"
                                    >
                                        <Pause class="h-3.5 w-3.5 fill-current" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="resetRestTimer()"
                                        class="p-2 rounded-xl bg-zinc-800 text-zinc-400 hover:text-white border border-zinc-700 transition cursor-pointer"
                                        title="Reset"
                                    >
                                        <RotateCcw class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <!-- Preset Pills -->
                                <div class="hidden md:flex items-center gap-1 pl-2 border-l border-zinc-700">
                                    <button
                                        v-for="preset in [60, 90, 120]"
                                        :key="preset"
                                        type="button"
                                        @click="startRestTimer(preset)"
                                        class="px-2 py-1 rounded-lg text-[10px] font-mono font-bold border transition cursor-pointer"
                                        :class="restTimerTotal === preset && isRestTimerRunning
                                            ? 'bg-[#A3E635]/20 text-[#A3E635] border-[#A3E635]/40'
                                            : 'bg-zinc-800/80 text-zinc-400 border-zinc-700 hover:text-zinc-200'"
                                    >
                                        {{ preset }}s
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Muscle Groups Heatmap Grid (6 Muscle Groups) -->
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div
                                v-for="muscle in (props.muscleRecovery || [])"
                                :key="muscle.name"
                                class="p-3 rounded-2xl bg-zinc-900/70 border border-[#27272A] flex flex-col justify-between"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-white">{{ muscle.name }}</span>
                                    <span
                                        class="text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase font-mono"
                                        :class="muscle.status === 'ready'
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                            : (muscle.status === 'recovering'
                                                ? 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                                                : 'bg-rose-500/10 text-rose-400 border-rose-500/20')"
                                    >
                                        {{ muscle.percent }}%
                                    </span>
                                </div>

                                <!-- Progress recovery bar -->
                                <div class="mt-2.5 h-1.5 w-full bg-zinc-800 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="muscle.status === 'ready'
                                            ? 'bg-emerald-500'
                                            : (muscle.status === 'recovering' ? 'bg-amber-500' : 'bg-rose-500')"
                                        :style="{ width: `${muscle.percent}%` }"
                                    ></div>
                                </div>

                                <div class="mt-2 flex items-center justify-between text-[10px] text-zinc-400">
                                    <span>{{ muscle.label }}</span>
                                    <span class="font-mono text-zinc-500">{{ muscle.last_trained }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Workout Sets Accordion / Snippet -->
                        <div v-if="(props.recentWorkouts || []).length > 0" class="mt-4 pt-3 border-t border-[#27272A]">
                            <div class="flex items-center justify-between text-xs pb-2">
                                <span class="font-bold text-zinc-400">Catatan Set Terakhir Hari Ini:</span>
                                <span class="text-[11px] font-mono text-zinc-500">{{ props.recentWorkouts.length }} Set</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div
                                    v-for="log in props.recentWorkouts.slice(0, 4)"
                                    :key="log.id"
                                    class="p-2.5 rounded-xl bg-zinc-900/60 border border-zinc-800 text-xs flex items-center justify-between"
                                >
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-white">{{ log.exercise_name }}</span>
                                            <span class="text-[10px] text-zinc-400 font-mono">({{ log.muscle_group }})</span>
                                        </div>
                                        <div class="text-[11px] text-zinc-400 mt-0.5 font-mono">
                                            Set #{{ log.set_number }} • <strong class="text-[#A3E635]">{{ log.weight_kg }} kg</strong> x {{ log.reps }} reps
                                            <span v-if="log.rpe" class="text-zinc-500">• RPE {{ log.rpe }}</span>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="deleteWorkoutLog(log.id)"
                                        class="p-1 text-zinc-500 hover:text-rose-400 transition"
                                        title="Hapus set"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Upcoming Schedule Card -->
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-xl relative overflow-hidden">
                        <div class="flex items-center justify-between text-xs pb-2 border-b border-[#27272A]">
                            <span class="font-bold text-[#A3E635] flex items-center gap-1.5">
                                <Clock class="h-4 w-4" /> Jadwal Kelas Berikutnya
                            </span>
                            <span class="rounded-full bg-zinc-800 px-3 py-0.5 text-[11px] font-mono text-zinc-300">Studio A</span>
                        </div>

                        <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">{{ props.upcomingSchedule.class_name }}</h2>
                                <p class="text-xs sm:text-sm text-zinc-400 mt-1 flex items-center gap-1.5 font-mono">
                                    <Calendar class="h-3.5 w-3.5 text-zinc-500" /> {{ props.upcomingSchedule.time_text }}
                                </p>
                            </div>

                            <!-- Buttons: Batalkan (>12 Jam) & Lihat Tiket -->
                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    v-if="props.upcomingSchedule.can_cancel"
                                    type="button"
                                    @click="handleCancelBooking(props.upcomingSchedule.booking_id)"
                                    class="rounded-xl bg-zinc-800/80 hover:bg-zinc-800 py-2.5 px-4 text-xs font-bold text-zinc-300 transition cursor-pointer"
                                >
                                    Batalkan (&gt;12 Jam)
                                </button>
                                <button
                                    type="button"
                                    @click="isQrModalOpen = true"
                                    class="rounded-xl bg-transparent hover:bg-white/5 py-2.5 px-4 text-xs font-extrabold text-[#A3E635] border border-[#A3E635]/40 transition cursor-pointer"
                                >
                                    Lihat Tiket
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Class & PT Booking Schedule Section -->
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-xl">
                        <!-- Top Segmented Control Toggle: "Kelas Studio" (Active) | "Sesi Personal Trainer" -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-[#27272A]">
                            <div>
                                <h3 class="text-base font-extrabold text-white">Jadwal &amp; Booking Kelas</h3>
                                <p class="text-xs text-zinc-400">Pilih hari dan amankan kursi latihan Anda</p>
                            </div>

                            <div class="flex items-center bg-zinc-900 p-1 rounded-2xl border border-[#27272A] self-start sm:self-auto">
                                <button
                                    type="button"
                                    @click="scheduleCategory = 'studio'"
                                    :class="[
                                        'px-4 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        scheduleCategory === 'studio'
                                            ? 'bg-[#A3E635] text-zinc-950 shadow-md shadow-[#A3E635]/20'
                                            : 'text-zinc-400 hover:text-white',
                                    ]"
                                >
                                    Kelas Studio
                                </button>
                                <button
                                    type="button"
                                    @click="scheduleCategory = 'pt'"
                                    :class="[
                                        'px-4 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                                        scheduleCategory === 'pt'
                                            ? 'bg-[#A3E635] text-zinc-950 shadow-md shadow-[#A3E635]/20'
                                            : 'text-zinc-400 hover:text-white',
                                    ]"
                                >
                                    Sesi Personal Trainer
                                </button>
                            </div>
                        </div>

                        <!-- Horizontal Date Strip Calendar: Mon to Sun with current date highlighted in Electric Lime -->
                        <div class="mt-4 flex items-center justify-between gap-2 overflow-x-auto pb-2">
                            <button
                                v-for="(day, idx) in weekDays"
                                :key="idx"
                                @click="selectDay(idx)"
                                :class="[
                                    'flex-1 min-w-[54px] rounded-2xl p-2.5 text-center transition-all cursor-pointer border',
                                    day.active
                                        ? 'bg-[#A3E635] text-zinc-950 border-[#A3E635] font-extrabold shadow-lg shadow-[#A3E635]/20'
                                        : 'bg-zinc-900/60 text-zinc-400 border-zinc-800 hover:border-zinc-700 hover:text-white',
                                ]"
                            >
                                <span class="text-[10px] uppercase font-bold block">{{ day.day }}</span>
                                <span class="font-mono text-base font-black block mt-0.5">{{ day.date }}</span>
                            </button>
                        </div>

                        <!-- Vertical Timeline of Class Cards (Dynamic from Props / DB) -->
                        <div class="mt-5 space-y-3.5">
                            <div
                                v-for="cls in (props.timelineClasses && props.timelineClasses.length ? props.timelineClasses : [
                                    { id: 1, class_name: 'Zumba Party', instructor_name: 'Maya', room_name: 'Studio B', start_time_text: '17:00 WIB', max_capacity: 25, current_booked: 7, remaining_seats: 18, is_full: false, is_booked: false },
                                    { id: 2, class_name: 'RPM Cycling', instructor_name: 'Doni', room_name: 'Spin Room', start_time_text: '18:30 WIB', max_capacity: 20, current_booked: 18, remaining_seats: 2, is_full: false, is_booked: false },
                                    { id: 3, class_name: 'Hatha Yoga', instructor_name: 'Sinta', room_name: 'Studio A', start_time_text: '19:30 WIB', max_capacity: 20, current_booked: 20, remaining_seats: 0, is_full: true, is_booked: false },
                                ])"
                                :key="cls.id"
                                class="rounded-2xl bg-zinc-900/90 p-4 border border-[#27272A] hover:border-zinc-700 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold" :class="cls.is_full ? 'text-zinc-500' : (cls.remaining_seats <= 2 ? 'text-[#F59E0B]' : 'text-[#A3E635]')">
                                            {{ cls.start_time_text }}
                                        </span>
                                        <span
                                            v-if="cls.is_full"
                                            class="rounded-full bg-[#E11D48]/15 px-2.5 py-0.5 text-[10px] font-bold text-[#E11D48] border border-[#E11D48]/30"
                                        >
                                            PENUH ({{ cls.current_booked }}/{{ cls.max_capacity }})
                                        </span>
                                        <span
                                            v-else-if="cls.remaining_seats <= 2"
                                            class="rounded-full bg-[#F59E0B]/15 px-2.5 py-0.5 text-[10px] font-bold text-[#F59E0B] border border-[#F59E0B]/30 animate-pulse"
                                        >
                                            Sisa {{ cls.remaining_seats }} Sepeda/Kursi!
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-[#10B981]/15 px-2.5 py-0.5 text-[10px] font-bold text-[#10B981] border border-[#10B981]/30"
                                        >
                                            Tersedia ({{ cls.remaining_seats }}/{{ cls.max_capacity }} Kursi)
                                        </span>
                                    </div>
                                    <h4 class="text-base font-extrabold text-white mt-1">{{ cls.class_name }}</h4>
                                    <p class="text-xs text-zinc-400">Coach {{ cls.instructor_name }} • {{ cls.room_name }}</p>
                                </div>

                                <div class="self-start sm:self-auto flex items-center gap-2">
                                    <button
                                        v-if="cls.is_booked"
                                        type="button"
                                        @click="handleCancelBooking(cls.booking_id)"
                                        class="rounded-xl bg-rose-500/10 border border-rose-500/30 hover:bg-rose-500 text-rose-300 hover:text-white font-bold px-4 py-2 text-xs transition cursor-pointer"
                                    >
                                        Batalkan Booking
                                    </button>
                                    <button
                                        v-else-if="cls.is_waitlist"
                                        type="button"
                                        class="rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 font-bold px-4 py-2 text-xs"
                                    >
                                        Di Antrean Waitlist
                                    </button>
                                    <button
                                        v-else-if="cls.is_full"
                                        type="button"
                                        @click="handleWaitlist(cls.id, cls.class_name)"
                                        class="rounded-xl bg-transparent border-2 border-[#F59E0B] text-[#F59E0B] hover:bg-[#F59E0B]/10 font-bold px-4 py-2.5 text-xs transition cursor-pointer"
                                    >
                                        Gabung Waitlist (Antrean #3)
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        @click="handleBook(cls.id, cls.class_name)"
                                        class="rounded-xl bg-[#A3E635] hover:bg-lime-400 text-zinc-950 font-extrabold px-5 py-2.5 text-xs transition shadow-sm cursor-pointer"
                                    >
                                        Book Kursi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===================================================================== -->
                <!-- BOTTOM ROW (12 Cols): Body Metrics & Fitness Progress (InBody)        -->
                <!-- ===================================================================== -->
                <div class="lg:col-span-12">
                    <div class="rounded-3xl bg-[#18181B] p-5 sm:p-6 border border-[#27272A] shadow-xl">
                        <div class="flex items-center justify-between pb-4 border-b border-[#27272A]">
                            <div>
                                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                                    <Activity class="h-5 w-5 text-[#A3E635]" /> Body Metrics &amp; Fitness Progress (InBody Tracker)
                                </h3>
                                <p class="text-xs text-zinc-400">Pencatatan komposisi tubuh &amp; grafik tren 6 bulan terakhir</p>
                            </div>
                            <span class="rounded-full bg-zinc-800 px-3 py-1 text-xs font-mono text-[#A3E635] border border-zinc-700">
                                InBody 770 Bio-Scanner
                            </span>
                        </div>

                        <!-- 1. Top Summary Stat Cards (3 Columns) -->
                        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Card 1: Berat Badan -->
                            <div class="rounded-2xl bg-zinc-900/80 p-4 border border-[#27272A]">
                                <span class="text-xs text-zinc-400 font-semibold block">Berat Badan</span>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="font-mono text-2xl font-black text-white">{{ props.metrics.weight }}</span>
                                    <span class="text-xs text-zinc-400">kg</span>
                                </div>
                                <div class="mt-2 flex items-center gap-1 text-xs font-mono font-bold text-[#10B981]">
                                    <TrendingDown class="h-3.5 w-3.5" /> {{ props.metrics.weight_diff }} kg vs bln lalu
                                </div>
                            </div>

                            <!-- Card 2: Massa Otot -->
                            <div class="rounded-2xl bg-zinc-900/80 p-4 border border-[#27272A]">
                                <span class="text-xs text-zinc-400 font-semibold block">Massa Otot (SMM)</span>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="font-mono text-2xl font-black text-[#A3E635]">{{ props.metrics.muscle_mass }}</span>
                                    <span class="text-xs text-zinc-400">kg</span>
                                </div>
                                <div class="mt-2 flex items-center gap-1 text-xs font-mono font-bold text-[#A3E635]">
                                    <TrendingUp class="h-3.5 w-3.5" /> +{{ props.metrics.muscle_diff }} kg peningkatan
                                </div>
                            </div>

                            <!-- Card 3: Lemak Tubuh -->
                            <div class="rounded-2xl bg-zinc-900/80 p-4 border border-[#27272A]">
                                <span class="text-xs text-zinc-400 font-semibold block">Lemak Tubuh (PBF)</span>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="font-mono text-2xl font-black text-white">{{ props.metrics.body_fat }}</span>
                                    <span class="text-xs text-zinc-400">%</span>
                                </div>
                                <div class="mt-2 flex items-center gap-1 text-xs font-mono font-bold text-[#10B981]">
                                    <TrendingDown class="h-3.5 w-3.5" /> {{ props.metrics.fat_diff }}% fat loss
                                </div>
                            </div>
                        </div>

                        <!-- 2. Dual-Line Trend Chart (Weight vs Muscle Mass over 6 Months) -->
                        <div class="mt-6 rounded-2xl bg-zinc-900/80 p-5 border border-[#27272A]">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                <div>
                                    <span class="font-bold text-white block">Grafik Tren 6 Bulan Terakhir</span>
                                    <span class="text-zinc-400 text-[11px]">Korelasi penurunan berat badan vs kenaikan massa otot</span>
                                </div>
                                <div class="flex items-center gap-4 text-xs font-mono">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-1 bg-zinc-400 rounded-full"></span>
                                        <span class="text-zinc-300">Berat Badan (kg)</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-1 bg-[#A3E635] rounded-full"></span>
                                        <span class="text-[#A3E635] font-bold">Massa Otot (kg)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- SVG Responsive Area Chart -->
                            <div class="mt-4 h-48 w-full">
                                <svg class="w-full h-full overflow-visible" viewBox="0 0 500 150" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="muscleGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#A3E635" stop-opacity="0.3" />
                                            <stop offset="100%" stop-color="#A3E635" stop-opacity="0.0" />
                                        </linearGradient>
                                    </defs>

                                    <!-- Grid lines -->
                                    <line x1="0" y1="30" x2="500" y2="30" stroke="#27272A" stroke-dasharray="3 3" />
                                    <line x1="0" y1="75" x2="500" y2="75" stroke="#27272A" stroke-dasharray="3 3" />
                                    <line x1="0" y1="120" x2="500" y2="120" stroke="#27272A" stroke-dasharray="3 3" />

                                    <!-- Muscle Area & Line (Electric Lime) -->
                                    <path
                                        d="M 20,110 Q 110,105 200,98 T 350,85 T 480,75 L 480,140 L 20,140 Z"
                                        fill="url(#muscleGrad)"
                                    />
                                    <path
                                        d="M 20,110 Q 110,105 200,98 T 350,85 T 480,75"
                                        fill="none"
                                        stroke="#A3E635"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                    />

                                    <!-- Weight Line (Zinc/White) -->
                                    <path
                                        d="M 20,35 Q 110,45 200,55 T 350,68 T 480,82"
                                        fill="none"
                                        stroke="#D4D4D8"
                                        stroke-width="2.5"
                                        stroke-dasharray="4 2"
                                        stroke-linecap="round"
                                    />

                                    <!-- Data Points -->
                                    <circle cx="20" cy="110" r="4" fill="#A3E635" />
                                    <circle cx="200" cy="98" r="4" fill="#A3E635" />
                                    <circle cx="480" cy="75" r="5" fill="#A3E635" stroke="#18181B" stroke-width="2" />

                                    <circle cx="20" cy="35" r="3.5" fill="#D4D4D8" />
                                    <circle cx="200" cy="55" r="3.5" fill="#D4D4D8" />
                                    <circle cx="480" cy="82" r="4" fill="#D4D4D8" stroke="#18181B" stroke-width="2" />
                                </svg>

                                <!-- X-Axis Months Labels -->
                                <div class="mt-2 grid grid-cols-6 text-center text-xs font-mono text-zinc-500">
                                    <span>Mei</span>
                                    <span>Jun</span>
                                    <span>Jul</span>
                                    <span>Agu</span>
                                    <span>Sep</span>
                                    <span class="text-[#A3E635] font-bold">Okt (Kini)</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Progress Photos Section (Private Signed Access) -->
                        <div class="mt-6 rounded-2xl bg-zinc-900/80 p-5 border border-[#27272A]">
                            <div class="flex items-center justify-between pb-3 border-b border-[#27272A]">
                                <div class="flex items-center gap-2">
                                    <Lock class="h-4 w-4 text-[#A3E635]" />
                                    <h4 class="text-sm font-bold text-white">Foto Progres Before-After (Terlindungi Enkripsi)</h4>
                                </div>
                                <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-mono text-zinc-400">
                                    Private Access
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                <!-- Thumbnails -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="relative rounded-xl overflow-hidden bg-zinc-800 border border-zinc-700 aspect-square flex flex-col items-center justify-center p-3 text-center">
                                        <div class="h-10 w-10 rounded-full bg-zinc-700 flex items-center justify-center text-zinc-400 mb-2">
                                            <User class="h-5 w-5" />
                                        </div>
                                        <span class="text-xs font-bold text-zinc-300">Before (Mei 2026)</span>
                                        <span class="text-[10px] text-zinc-500 font-mono">74.8 kg • 18.6% Fat</span>
                                    </div>

                                    <div class="relative rounded-xl overflow-hidden bg-zinc-800 border-2 border-[#A3E635]/40 aspect-square flex flex-col items-center justify-center p-3 text-center">
                                        <div class="h-10 w-10 rounded-full bg-[#A3E635]/20 flex items-center justify-center text-[#A3E635] mb-2">
                                            <Award class="h-5 w-5" />
                                        </div>
                                        <span class="text-xs font-bold text-white">After (Okt 2026)</span>
                                        <span class="text-[10px] text-[#A3E635] font-mono font-bold">72.4 kg • 16.5% Fat</span>
                                    </div>
                                </div>

                                <!-- Coach Ade Evaluation Notes -->
                                <div class="rounded-xl bg-zinc-950 p-4 border border-zinc-800">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="h-2 w-2 rounded-full bg-[#A3E635]"></span>
                                        <span class="text-xs font-bold text-white">Catatan Evaluasi Coach Ade</span>
                                    </div>
                                    <p class="text-xs text-zinc-300 leading-relaxed">
                                        "Definisi otot deltoid (bahu) dan core perut terbentuk sangat rapi dalam 5 bulan. Postur bahu membaik berkat latihan face pull rutin. Pertahankan surplus kalori bersih untuk fase lean bulking bulan depan."
                                    </p>
                                    <div class="mt-3 text-[10px] font-mono text-zinc-500 flex items-center justify-between">
                                        <span>Terakhir update: 14 Okt 2026</span>
                                        <span class="text-[#A3E635] font-semibold">InBody Score: 88/100</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MOBILE FLOATING BOTTOM NAVIGATION BAR (Visible on Mobile Viewport)        -->
        <!-- 5 items: Home, Jadwal, [QR PASS] FAB in glowing Lime, Progres, Profil    -->
        <!-- ========================================================================= -->
        <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#18181B]/95 backdrop-blur border-t border-[#27272A] px-4 py-2">
            <div class="flex items-center justify-around relative">
                <!-- 1. Home -->
                <button
                    type="button"
                    @click="activeTab = 'overview'"
                    :class="[
                        'flex flex-col items-center gap-1 py-1 text-[10px] font-semibold transition',
                        activeTab === 'overview' ? 'text-[#A3E635]' : 'text-zinc-400 hover:text-white',
                    ]"
                >
                    <Home class="h-5 w-5" />
                    <span>Home</span>
                </button>

                <!-- 2. Jadwal -->
                <button
                    type="button"
                    @click="activeTab = 'jadwal'"
                    :class="[
                        'flex flex-col items-center gap-1 py-1 text-[10px] font-semibold transition',
                        activeTab === 'jadwal' ? 'text-[#A3E635]' : 'text-zinc-400 hover:text-white',
                    ]"
                >
                    <Calendar class="h-5 w-5" />
                    <span>Jadwal</span>
                </button>

                <!-- 3. Prominent Floating Center Action Button [SCAN ABSEN] -->
                <div class="-mt-7">
                    <button
                        type="button"
                        @click="isQrModalOpen = true; qrModalTab = 'scanner'; scanResult = null"
                        class="h-14 w-14 rounded-full bg-[#A3E635] text-zinc-950 flex flex-col items-center justify-center font-black shadow-xl shadow-[#A3E635]/40 border-4 border-[#09090B] active:scale-95 transition-transform cursor-pointer"
                        title="Buka Scanner Absen Masuk Gym"
                    >
                        <Scan class="h-6 w-6 text-zinc-950" />
                    </button>
                    <span class="text-[9px] font-black text-[#A3E635] tracking-wider text-center block mt-1 uppercase">SCAN ABSEN</span>
                </div>

                <!-- 4. Progres -->
                <button
                    type="button"
                    @click="activeTab = 'progres'"
                    :class="[
                        'flex flex-col items-center gap-1 py-1 text-[10px] font-semibold transition',
                        activeTab === 'progres' ? 'text-[#A3E635]' : 'text-zinc-400 hover:text-white',
                    ]"
                >
                    <Activity class="h-5 w-5" />
                    <span>Progres</span>
                </button>

                <!-- 5. Profil -->
                <button
                    type="button"
                    @click="isProfileModalOpen = true"
                    class="flex flex-col items-center gap-1 py-1 text-[10px] font-semibold text-zinc-400 hover:text-white transition cursor-pointer"
                >
                    <User class="h-5 w-5" />
                    <span>Profil</span>
                </button>
            </div>
        </nav>

        <!-- ========================================================================= -->
        <!-- SCANNER & QR PASS MODAL: Dual-Tab (1. Scan QR Pintu Gym, 2. QR Pass & PIN PT) -->
        <!-- ========================================================================= -->
        <div
            v-if="isQrModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-200"
            @click.self="isQrModalOpen = false"
        >
            <div class="relative w-full max-w-sm rounded-[2.5rem] bg-[#18181B] border border-[#27272A] text-white p-5 sm:p-6 shadow-2xl flex flex-col items-center">
                <!-- Close Button -->
                <button
                    type="button"
                    @click="isQrModalOpen = false"
                    class="absolute top-4 right-4 rounded-full bg-zinc-800 p-2 text-zinc-400 hover:bg-zinc-700 hover:text-white transition cursor-pointer"
                >
                    <X class="h-4 w-4" />
                </button>

                <!-- 1. Dual Tab Switch Header -->
                <div class="flex items-center gap-1 bg-[#09090B] p-1 rounded-2xl border border-[#27272A] w-full mt-2 mb-4">
                    <button
                        type="button"
                        @click="qrModalTab = 'scanner'"
                        :class="[
                            'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                            qrModalTab === 'scanner'
                                ? 'bg-[#A3E635] text-zinc-950 shadow-md font-black'
                                : 'text-zinc-400 hover:text-zinc-200'
                        ]"
                    >
                        <Camera class="h-3.5 w-3.5" />
                        <span>Scan QR Pintu Gym</span>
                    </button>
                    <button
                        type="button"
                        @click="qrModalTab = 'pass'"
                        :class="[
                            'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                            qrModalTab === 'pass'
                                ? 'bg-[#A3E635] text-zinc-950 shadow-md font-black'
                                : 'text-zinc-400 hover:text-zinc-200'
                        ]"
                    >
                        <QrCode class="h-3.5 w-3.5" />
                        <span>ID Pass & PT</span>
                    </button>
                </div>

                <!-- TAB 1: CAMERA SCANNER (MEMBER SCAN PINTU GYM UNTUK ABSEN) -->
                <div v-if="qrModalTab === 'scanner'" class="w-full flex flex-col items-center text-center">
                    <div class="mb-3">
                        <h3 class="text-base font-black text-white">Scan Barcode / QR Pintu Gym</h3>
                        <p class="text-xs text-zinc-400 mt-0.5">
                            Arahkan kamera ke QR Code di layar Gerbang / Meja Kasir gym untuk check-in & buka loker otomatis.
                        </p>
                    </div>

                    <!-- Camera Viewfinder Box -->
                    <div class="relative w-full aspect-square max-w-[260px] rounded-3xl bg-[#09090B] border-2 border-[#27272A] flex items-center justify-center overflow-hidden shadow-inner my-2">
                        <!-- Live WebRTC Video Element -->
                        <video
                            ref="videoScannerRef"
                            autoplay
                            playsinline
                            muted
                            class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300"
                            :class="{ 'opacity-100': isCameraActive, 'opacity-0': !isCameraActive }"
                        ></video>

                        <!-- Corner Crosshairs -->
                        <div class="absolute top-3 left-3 w-6 h-6 border-t-4 border-l-4 border-[#A3E635] rounded-tl-lg pointer-events-none z-10"></div>
                        <div class="absolute top-3 right-3 w-6 h-6 border-t-4 border-r-4 border-[#A3E635] rounded-tr-lg pointer-events-none z-10"></div>
                        <div class="absolute bottom-3 left-3 w-6 h-6 border-b-4 border-l-4 border-[#A3E635] rounded-bl-lg pointer-events-none z-10"></div>
                        <div class="absolute bottom-3 right-3 w-6 h-6 border-b-4 border-r-4 border-[#A3E635] rounded-br-lg pointer-events-none z-10"></div>

                        <!-- Active Camera Live Indicator Pill -->
                        <div
                            v-if="isCameraActive && !scanResult"
                            class="absolute top-3 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1.5 rounded-full bg-black/75 px-2.5 py-0.5 text-[9px] font-mono text-[#A3E635] border border-[#A3E635]/40 backdrop-blur pointer-events-none"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#A3E635] animate-ping"></span>
                            <span>KAMERA LIVE AKTIF</span>
                        </div>

                        <!-- Camera Inactive / Placeholder Notice -->
                        <div v-if="!isCameraActive && !scanResult" class="flex flex-col items-center gap-2 text-zinc-500 z-10 p-3 text-center">
                            <Camera class="h-10 w-10 text-[#A3E635]/60 animate-pulse" />
                            <span class="text-[11px] font-mono text-zinc-400">
                                {{ cameraError || 'Menghubungkan kamera QR...' }}
                            </span>
                            <button
                                v-if="cameraError"
                                type="button"
                                @click="startCamera"
                                class="text-[10px] text-[#A3E635] underline font-bold mt-1 cursor-pointer"
                            >
                                Coba Aktifkan Kamera Lagi
                            </button>
                        </div>

                        <!-- Animated Laser Scan Bar -->
                        <div
                            v-if="!scanResult"
                            class="absolute left-4 right-4 h-0.5 bg-gradient-to-r from-transparent via-[#A3E635] to-transparent shadow-[0_0_12px_#A3E635] animate-pulse z-10 pointer-events-none"
                            style="top: 45%;"
                        ></div>

                        <!-- Result Overlay inside Viewfinder -->
                        <div v-if="scanResult" class="absolute inset-0 bg-[#09090B]/95 p-4 flex flex-col items-center justify-center text-center animate-in zoom-in-95 duration-200 z-20">
                            <!-- 1. Success State -->
                            <template v-if="scanResult.success">
                                <div class="h-12 w-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/40 mb-2">
                                    <CheckCircle2 class="h-7 w-7" />
                                </div>
                                <span class="text-xs font-black uppercase tracking-wider text-emerald-400">Akses Diterima</span>
                                <span class="text-xs text-zinc-300 font-medium mt-1">Gerbang Terbuka Otomatis!</span>

                                <div class="mt-3 rounded-xl bg-zinc-900 border border-[#27272A] px-3 py-2 w-full">
                                    <span class="text-[10px] text-zinc-400 uppercase font-bold tracking-wider block">Kunci Loker Anda</span>
                                    <span class="text-lg font-black font-mono text-[#A3E635]">{{ scanResult.locker_number }}</span>
                                </div>
                            </template>

                            <!-- 2. Denied / Error State -->
                            <template v-else>
                                <div class="h-12 w-12 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center border border-rose-500/40 mb-2">
                                    <AlertCircle class="h-7 w-7" />
                                </div>
                                <span class="text-xs font-black uppercase tracking-wider text-rose-400">Akses Ditolak</span>
                                <span class="text-xs text-zinc-300 font-medium mt-1 max-w-[210px]">{{ scanResult.message }}</span>
                            </template>
                        </div>
                    </div>

                    <!-- Scan Result Card (Outside Viewfinder) -->
                    <div
                        v-if="scanResult"
                        :class="[
                            'w-full mt-3 rounded-2xl p-3 text-left flex items-start gap-2.5 border',
                            scanResult.success
                                ? 'bg-emerald-950/40 border-emerald-500/30'
                                : 'bg-rose-950/40 border-rose-500/30'
                        ]"
                    >
                        <Check v-if="scanResult.success" class="h-5 w-5 text-emerald-400 shrink-0 mt-0.5" />
                        <AlertCircle v-else class="h-5 w-5 text-rose-400 shrink-0 mt-0.5" />
                        <div class="text-xs">
                            <div :class="scanResult.success ? 'font-bold text-emerald-300' : 'font-bold text-rose-300'">
                                {{ scanResult.message }}
                            </div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">Waktu: {{ scanResult.time }}</div>
                        </div>
                    </div>

                    <!-- Trigger / Action Simulation Buttons -->
                    <div class="w-full mt-4 space-y-2">
                        <button
                            type="button"
                            v-if="!scanResult"
                            @click="triggerScanGymQr('GYM-JOGJA-GATE-01')"
                            :disabled="isScanning"
                            class="w-full py-3 px-4 rounded-2xl bg-[#A3E635] hover:bg-lime-400 active:scale-[0.98] text-zinc-950 font-black text-xs shadow-lg shadow-[#A3E635]/25 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <Zap class="h-4 w-4 fill-zinc-950" />
                            <span>{{ isScanning ? 'Memproses Check-in...' : '⚡ Scan QR Gerbang Gym Sekarang' }}</span>
                        </button>

                        <button
                            type="button"
                            v-else
                            @click="scanResult = null; nextTick(() => startCamera())"
                            class="w-full py-2.5 px-4 rounded-2xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-bold text-xs border border-zinc-700 transition cursor-pointer"
                        >
                            Scan QR Lagi
                        </button>
                    </div>

                    <div class="mt-3 text-[10px] text-zinc-500 flex items-center gap-1.5">
                        <ShieldCheck class="h-3.5 w-3.5 text-[#10B981]" />
                        <span>Sistem Anti-Passback aktif (1 Scan per 12 Jam per Member)</span>
                    </div>
                </div>

                <!-- TAB 2: MEMBER ID PASS & PT PIN (SCREEN 2) -->
                <div v-else class="w-full rounded-[2rem] bg-white text-zinc-950 p-4 sm:p-5 flex flex-col items-center text-center">
                    <!-- Member Header -->
                    <div class="flex flex-col items-center">
                        <div class="relative">
                            <div class="h-14 w-14 rounded-full bg-zinc-900 text-[#A3E635] flex items-center justify-center font-extrabold text-lg shadow-md border-2 border-zinc-200">
                                {{ props.member.name.slice(0, 2).toUpperCase() }}
                            </div>
                            <div class="absolute -bottom-1 -right-1 rounded-full bg-emerald-500 p-0.5 border-2 border-white shadow">
                                <Check class="h-3 w-3 text-white stroke-[3]" />
                            </div>
                        </div>

                        <h3 class="mt-2 text-base font-black tracking-tight text-zinc-900">{{ props.member.name }}</h3>
                        <p class="text-[11px] text-zinc-500 font-medium">Member ID: GYM-2026-0842</p>

                        <!-- Green Verified Badge -->
                        <div class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black text-emerald-800 border border-emerald-300">
                            <ShieldCheck class="h-3.5 w-3.5 text-emerald-600" />
                            ACCESS GRANTED - NO UNPAID FINES
                        </div>
                    </div>

                    <!-- Dynamic TOTP QR Code -->
                    <div class="mt-3 relative p-2 rounded-2xl border-4 border-[#A3E635] bg-white shadow-inner">
                        <svg class="h-[180px] w-[180px]" viewBox="0 0 220 220" fill="none">
                            <!-- Corner Markers -->
                            <rect x="10" y="10" width="55" height="55" rx="10" fill="#09090B" />
                            <rect x="20" y="20" width="35" height="35" rx="6" fill="white" />
                            <rect x="27" y="27" width="21" height="21" rx="4" fill="#09090B" />

                            <rect x="155" y="10" width="55" height="55" rx="10" fill="#09090B" />
                            <rect x="165" y="20" width="35" height="35" rx="6" fill="white" />
                            <rect x="172" y="27" width="21" height="21" rx="4" fill="#09090B" />

                            <rect x="10" y="155" width="55" height="55" rx="10" fill="#09090B" />
                            <rect x="20" y="165" width="35" height="35" rx="6" fill="white" />
                            <rect x="27" y="172" width="21" height="21" rx="4" fill="#09090B" />

                            <!-- Data matrix points -->
                            <circle cx="80" cy="25" r="5" fill="#09090B" />
                            <circle cx="100" cy="25" r="5" fill="#09090B" />
                            <circle cx="120" cy="25" r="5" fill="#09090B" />
                            <circle cx="140" cy="25" r="5" fill="#09090B" />
                            <circle cx="80" cy="45" r="5" fill="#09090B" />
                            <circle cx="110" cy="45" r="5" fill="#09090B" />
                            <circle cx="135" cy="45" r="5" fill="#09090B" />

                            <circle cx="25" cy="85" r="5" fill="#09090B" />
                            <circle cx="45" cy="85" r="5" fill="#09090B" />
                            <circle cx="85" cy="85" r="6" fill="#09090B" />
                            <circle cx="105" cy="85" r="6" fill="#09090B" />
                            <circle cx="125" cy="85" r="6" fill="#09090B" />
                            <circle cx="145" cy="85" r="5" fill="#09090B" />
                            <circle cx="175" cy="85" r="5" fill="#09090B" />
                            <circle cx="195" cy="85" r="5" fill="#09090B" />

                            <circle cx="85" cy="110" r="6" fill="#09090B" />
                            <circle cx="110" cy="110" r="8" fill="#A3E635" stroke="#09090B" stroke-width="3" />
                            <circle cx="135" cy="110" r="6" fill="#09090B" />

                            <circle cx="25" cy="135" r="5" fill="#09090B" />
                            <circle cx="65" cy="135" r="5" fill="#09090B" />
                            <circle cx="85" cy="135" r="6" fill="#09090B" />
                            <circle cx="105" cy="135" r="6" fill="#09090B" />
                            <circle cx="125" cy="135" r="6" fill="#09090B" />
                            <circle cx="175" cy="135" r="5" fill="#09090B" />

                            <circle cx="80" cy="175" r="5" fill="#09090B" />
                            <circle cx="105" cy="175" r="5" fill="#09090B" />
                            <circle cx="130" cy="175" r="5" fill="#09090B" />
                            <circle cx="155" cy="175" r="5" fill="#09090B" />
                            <circle cx="180" cy="175" r="5" fill="#09090B" />
                            <circle cx="200" cy="175" r="5" fill="#09090B" />
                            <circle cx="105" cy="195" r="5" fill="#09090B" />
                            <circle cx="140" cy="195" r="5" fill="#09090B" />
                            <circle cx="170" cy="195" r="5" fill="#09090B" />
                        </svg>
                    </div>

                    <!-- Countdown Progress Bar -->
                    <div class="mt-3 w-full">
                        <div class="flex items-center justify-between text-[11px] font-mono font-bold text-zinc-700 mb-1 px-1">
                            <span>Berubah otomatis dalam:</span>
                            <span class="text-zinc-950 font-black">{{ countdownText }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-zinc-200 overflow-hidden">
                            <div
                                class="h-full rounded-full bg-[#10B981] transition-all duration-1000"
                                :style="{ width: `${(countdown / 60) * 100}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- PT Session Verification Box: "PIN Sesi PT Anda: 849 - 201" -->
                    <div class="mt-3 w-full rounded-xl bg-zinc-100 border border-zinc-200 p-2.5 text-center">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider block">
                            PIN Sesi PT Anda (Tunjukkan ke Coach)
                        </span>
                        <div class="mt-0.5 font-mono text-lg font-black tracking-widest text-zinc-900">
                            {{ props.ptSummary.pin_otp }}
                        </div>
                    </div>

                    <p class="text-[10px] text-zinc-400 mt-2">
                        Tunjukkan PIN ini ke Personal Trainer untuk verifikasi sesi latihan Anda.
                    </p>
                </div>
            </div>
        </div>

        <!-- PROFILE MODAL -->
        <div
            v-if="isProfileModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-200"
            @click.self="isProfileModalOpen = false"
        >
            <div class="relative w-full max-w-sm rounded-[2.5rem] bg-[#18181B] border border-[#27272A] text-white p-6 shadow-2xl flex flex-col items-center">
                <button
                    type="button"
                    @click="isProfileModalOpen = false"
                    class="absolute top-4 right-4 rounded-full bg-zinc-800 p-2 text-zinc-400 hover:bg-zinc-700 hover:text-white transition cursor-pointer"
                >
                    <X class="h-4 w-4" />
                </button>

                <div class="h-20 w-20 rounded-2xl bg-gradient-to-tr from-zinc-800 to-zinc-700 flex items-center justify-center border-2 border-[#27272A] font-extrabold text-[#A3E635] text-2xl shadow-lg mt-2">
                    {{ props.member.name.slice(0, 2).toUpperCase() }}
                </div>

                <h3 class="mt-3 text-lg font-black text-white">{{ props.member.name }}</h3>
                <p class="text-xs text-zinc-400">{{ props.member.email }} • {{ props.member.phone }}</p>

                <div class="mt-4 w-full space-y-2.5 bg-[#09090B] p-4 rounded-2xl border border-[#27272A] text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">Paket Aktif:</span>
                        <span class="font-extrabold text-[#A3E635]">{{ props.subscription.plan_name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">Masa Berlaku:</span>
                        <span class="font-bold text-white">{{ props.subscription.days_remaining }} Hari Lagi</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">Cabang Asal:</span>
                        <span class="font-bold text-white">{{ props.member.home_branch_name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">Status No-Show:</span>
                        <span class="font-bold text-emerald-400">0/3 Strike (Aman)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">Tunggakan Denda:</span>
                        <span class="font-bold text-emerald-400">Rp {{ props.member.unpaid_fine_amount.toLocaleString('id-ID') }}</span>
                    </div>
                </div>

                <div class="w-full mt-5">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="w-full py-3 rounded-2xl bg-rose-600/20 hover:bg-rose-600 text-rose-400 hover:text-white border border-rose-500/30 font-bold text-xs flex items-center justify-center gap-2 transition cursor-pointer"
                    >
                        <LogOut class="h-4 w-4" />
                        <span>Keluar dari Akun</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- MEMBERSHIP FREEZE (CUTI MANDIRI) MODAL -->
        <div
            v-if="isFreezeModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-200"
            @click.self="isFreezeModalOpen = false"
        >
            <div class="relative w-full max-w-md rounded-[2.5rem] bg-[#18181B] border border-sky-500/30 text-white p-6 shadow-2xl flex flex-col">
                <button
                    type="button"
                    @click="isFreezeModalOpen = false"
                    class="absolute top-4 right-4 rounded-full bg-zinc-800 p-2 text-zinc-400 hover:bg-zinc-700 hover:text-white transition cursor-pointer"
                >
                    <X class="h-4 w-4" />
                </button>

                <!-- Icon & Title -->
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-2xl bg-sky-500/15 border border-sky-500/30 text-sky-400">
                        <Calendar class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-sky-500/20 px-2 py-0.5 text-[10px] font-bold text-sky-400 border border-sky-500/30 uppercase tracking-wider font-mono">
                                SELF-SERVICE
                            </span>
                        </div>
                        <h3 class="text-base font-black text-white mt-0.5">Pengajuan Cuti Membership (Freeze)</h3>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-4 p-3.5 rounded-2xl bg-zinc-900 border border-zinc-800 text-xs space-y-2">
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Paket Aktif:</span>
                        <span class="font-extrabold text-white">{{ props.subscription.plan_name }}</span>
                    </div>
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Sisa Kuota Cuti Anda:</span>
                        <span class="font-bold text-sky-400 font-mono">{{ props.subscription.remaining_freeze_days ?? 14 }} Hari</span>
                    </div>
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Masa Aktif Saat Ini:</span>
                        <span class="font-mono text-zinc-300">s/d {{ props.subscription.end_date }}</span>
                    </div>
                </div>

                <!-- Date Range Inputs -->
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-semibold text-zinc-300 block mb-1">Tanggal Mulai Cuti:</label>
                        <input
                            v-model="freezeForm.freeze_start"
                            type="date"
                            :min="todayStr"
                            class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white outline-none focus:border-sky-400 font-mono"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-zinc-300 block mb-1">Tanggal Selesai Cuti:</label>
                        <input
                            v-model="freezeForm.freeze_end"
                            type="date"
                            :min="freezeForm.freeze_start || todayStr"
                            class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white outline-none focus:border-sky-400 font-mono"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-zinc-300 block mb-1">Alasan Cuti (Opsional):</label>
                        <input
                            v-model="freezeForm.reason"
                            type="text"
                            placeholder="Contoh: Dinas luar kota / pemulihan cedera"
                            class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-zinc-200 outline-none focus:border-sky-400"
                        />
                    </div>
                </div>

                <!-- Live Preview Result Box -->
                <div v-if="calculatedFreezeDays > 0" class="mt-4 p-3 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-sky-300 font-medium">Total Hari Cuti:</span>
                        <span class="font-mono font-bold text-sky-200 text-sm">{{ calculatedFreezeDays }} Hari</span>
                    </div>
                    <p class="text-[11px] text-sky-400/80 mt-1">
                        Masa aktif paket Anda akan otomatis diperpanjang sebanyak <strong>{{ calculatedFreezeDays }} hari</strong> setelah tanggal selesai cuti.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="mt-5 flex gap-2">
                    <button
                        type="button"
                        @click="isFreezeModalOpen = false"
                        class="flex-1 rounded-xl bg-zinc-800 hover:bg-zinc-700 py-2.5 text-xs font-semibold text-zinc-300 transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitFreeze"
                        :disabled="isSubmittingFreeze || calculatedFreezeDays <= 0"
                        class="flex-1 rounded-xl bg-sky-500 hover:bg-sky-400 py-2.5 text-xs font-bold text-zinc-950 shadow-lg shadow-sky-500/20 transition flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    >
                        <RefreshCw v-if="isSubmittingFreeze" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ isSubmittingFreeze ? 'Memproses...' : 'Aktifkan Cuti' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 1. FULL ATTENDEES (RADAR KOMUNITAS) MODAL -->
        <div
            v-if="isAttendeesListOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-200"
            @click.self="isAttendeesListOpen = false"
        >
            <div class="relative w-full max-w-md rounded-[2.5rem] bg-[#18181B] border border-[#27272A] text-white p-6 shadow-2xl flex flex-col max-h-[85vh]">
                <button
                    type="button"
                    @click="isAttendeesListOpen = false"
                    class="absolute top-4 right-4 rounded-full bg-zinc-800 p-2 text-zinc-400 hover:bg-zinc-700 hover:text-white transition cursor-pointer"
                >
                    <X class="h-4 w-4" />
                </button>

                <div class="flex items-center gap-3 pb-3 border-b border-[#27272A]">
                    <div class="p-2.5 rounded-2xl bg-[#A3E635]/15 border border-[#A3E635]/30 text-[#A3E635]">
                        <Users class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-white">Radar Komunitas Gym</h3>
                            <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-400 border border-emerald-500/20">
                                {{ props.livePresence?.total_in_gym ?? props.crowdMeter.current }} Aktif
                            </span>
                        </div>
                        <p class="text-xs text-zinc-400 mt-0.5">Daftar member &amp; coach yang sedang check-in di gym saat ini</p>
                    </div>
                </div>

                <!-- Ghost Mode Notice -->
                <div class="mt-4 p-3 rounded-2xl bg-zinc-900 border border-zinc-800 text-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Ghost class="h-4 w-4" :class="props.livePresence?.is_ghost_mode ? 'text-sky-400' : 'text-zinc-400'" />
                        <div>
                            <span class="font-bold text-white block">Status Ghost Mode Anda:</span>
                            <span class="text-[11px] text-zinc-400">
                                {{ props.livePresence?.is_ghost_mode ? 'Aktif: Member lain tidak melihat nama Anda.' : 'Nonaktif: Terlihat di radar.' }}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="toggleGhostMode"
                        :disabled="isTogglingGhost"
                        class="rounded-xl px-2.5 py-1.5 text-xs font-bold transition border cursor-pointer"
                        :class="props.livePresence?.is_ghost_mode
                            ? 'bg-sky-500/20 text-sky-300 border-sky-500/30'
                            : 'bg-zinc-800 text-zinc-300 border-zinc-700 hover:bg-zinc-700'"
                    >
                        {{ props.livePresence?.is_ghost_mode ? 'Matikan' : 'Aktifkan' }}
                    </button>
                </div>

                <!-- Attendees Scrollable List -->
                <div class="mt-4 overflow-y-auto space-y-2 pr-1 flex-1">
                    <div
                        v-for="attendee in (props.livePresence?.attendees || [])"
                        :key="attendee.id"
                        class="p-3 rounded-2xl bg-zinc-900/60 border border-zinc-800 text-xs flex items-center justify-between"
                        :class="attendee.is_you ? 'border-[#A3E635]/40 bg-[#A3E635]/5' : ''"
                    >
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-zinc-800 to-zinc-700 flex items-center justify-center font-black text-xs text-[#A3E635] border border-zinc-700 shadow-sm">
                                {{ attendee.name.slice(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-white text-sm">{{ attendee.name }}</span>
                                    <span
                                        v-if="attendee.is_trainer"
                                        class="rounded px-1.5 py-0.5 text-[9px] font-bold bg-[#A3E635]/20 text-[#A3E635] border border-[#A3E635]/30"
                                    >
                                        COACH
                                    </span>
                                    <span
                                        v-if="attendee.is_you"
                                        class="rounded px-1.5 py-0.5 text-[9px] font-bold bg-sky-500/20 text-sky-300 border border-sky-500/30"
                                    >
                                        ANDA
                                    </span>
                                </div>
                                <span class="text-[11px] text-zinc-400 mt-0.5 block">
                                    Check-in: {{ attendee.check_in_time }} • Sedang berlatih {{ attendee.duration_text }}
                                </span>
                            </div>
                        </div>

                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-[#27272A]">
                    <button
                        type="button"
                        @click="isAttendeesListOpen = false"
                        class="w-full py-2.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-xs font-bold text-zinc-300 transition cursor-pointer"
                    >
                        Tutup Radar
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. READINESS & STABILITY QUESTIONNAIRE MODAL -->
        <div
            v-if="isReadinessModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-200"
            @click.self="isReadinessModalOpen = false"
        >
            <div class="relative w-full max-w-md rounded-[2.5rem] bg-[#18181B] border border-rose-500/30 text-white p-6 shadow-2xl flex flex-col">
                <button
                    type="button"
                    @click="isReadinessModalOpen = false"
                    class="absolute top-4 right-4 rounded-full bg-zinc-800 p-2 text-zinc-400 hover:bg-zinc-700 hover:text-white transition cursor-pointer"
                >
                    <X class="h-4 w-4" />
                </button>

                <div class="flex items-center gap-3 pb-3 border-b border-[#27272A]">
                    <div class="p-2.5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-400">
                        <HeartPulse class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-black text-white">Evaluasi Kesiapan &amp; Kestabilan Tubuh</h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Penilaian mandiri kondisi saraf &amp; pemulihan otot harian</p>
                    </div>
                </div>

                <!-- Form Controls -->
                <div class="mt-4 space-y-4">
                    <!-- Sleep Quality -->
                    <div>
                        <label class="text-xs font-bold text-zinc-300 block mb-1.5">
                            Kualitas &amp; Durasi Tidur Semalam (1 - 5):
                        </label>
                        <div class="grid grid-cols-5 gap-2">
                            <button
                                v-for="val in [1, 2, 3, 4, 5]"
                                :key="val"
                                type="button"
                                @click="readinessSleep = val"
                                class="py-2 rounded-xl text-xs font-bold border transition text-center cursor-pointer"
                                :class="readinessSleep === val
                                    ? 'bg-[#A3E635] text-zinc-950 border-[#A3E635]'
                                    : 'bg-zinc-900 text-zinc-400 border-zinc-800 hover:bg-zinc-800'"
                            >
                                {{ val }} ⭐
                            </button>
                        </div>
                    </div>

                    <!-- Muscle Soreness (DOMS) -->
                    <div>
                        <label class="text-xs font-bold text-zinc-300 block mb-1.5">
                            Kondisi Nyeri Otot (DOMS / Soreness):
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="readinessDoms = 'none'"
                                class="py-2 px-2 rounded-xl text-xs font-bold border transition text-center cursor-pointer"
                                :class="readinessDoms === 'none'
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                                    : 'bg-zinc-900 text-zinc-400 border-zinc-800 hover:bg-zinc-800'"
                            >
                                Bebas Pegal
                            </button>
                            <button
                                type="button"
                                @click="readinessDoms = 'mild'"
                                class="py-2 px-2 rounded-xl text-xs font-bold border transition text-center cursor-pointer"
                                :class="readinessDoms === 'mild'
                                    ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                                    : 'bg-zinc-900 text-zinc-400 border-zinc-800 hover:bg-zinc-800'"
                            >
                                Pegal Ringan
                            </button>
                            <button
                                type="button"
                                @click="readinessDoms = 'heavy'"
                                class="py-2 px-2 rounded-xl text-xs font-bold border transition text-center cursor-pointer"
                                :class="readinessDoms === 'heavy'
                                    ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                                    : 'bg-zinc-900 text-zinc-400 border-zinc-800 hover:bg-zinc-800'"
                            >
                                Sangat Pegal
                            </button>
                        </div>
                    </div>

                    <!-- Energy Level -->
                    <div>
                        <label class="text-xs font-bold text-zinc-300 block mb-1.5">
                            Tingkat Energi &amp; Fokus Mental:
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="readinessEnergy = 'high'"
                                class="py-2 px-2 rounded-xl text-xs font-bold border transition text-center cursor-pointer"
                                :class="readinessEnergy === 'high'
                                    ? 'bg-[#A3E635]/20 text-[#A3E635] border-[#A3E635]/40'
                                    : 'bg-zinc-900 text-zinc-400 border-zinc-800 hover:bg-zinc-800'"
                            >
                                Tinggi (Siap Max)
                            </button>
                            <button
                                type="button"
                                @click="readinessEnergy = 'medium'"
                                class="py-2 px-2 rounded-xl text-xs font-bold border transition text-center cursor-pointer"
                                :class="readinessEnergy === 'medium'
                                    ? 'bg-zinc-800 text-zinc-200 border-zinc-700'
                                    : 'bg-zinc-900 text-zinc-400 border-zinc-800 hover:bg-zinc-800'"
                            >
                                Normal
                            </button>
                            <button
                                type="button"
                                @click="readinessEnergy = 'low'"
                                class="py-2 px-2 rounded-xl text-xs font-bold border transition text-center cursor-pointer"
                                :class="readinessEnergy === 'low'
                                    ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                                    : 'bg-zinc-900 text-zinc-400 border-zinc-800 hover:bg-zinc-800'"
                            >
                                Lelah / Rendah
                            </button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="text-xs font-bold text-zinc-300 block mb-1">Catatan Tambahan (Opsional):</label>
                        <input
                            v-model="readinessNotesInput"
                            type="text"
                            placeholder="Contoh: Otot bahu kanan agak tegang, siap latihan kaki."
                            class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white outline-none focus:border-[#A3E635]"
                        />
                    </div>
                </div>

                <!-- Live Score Preview Box -->
                <div class="mt-4 p-3.5 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-zinc-400 block">Estimasi Skor Kesiapan:</span>
                        <span class="font-extrabold text-white text-sm">
                            {{ calculatedReadinessScore >= 80 ? 'Optimal (Beban Berat)' : (calculatedReadinessScore >= 60 ? 'Moderat (Beban Menengah)' : 'Ringan / Rest Day') }}
                        </span>
                    </div>
                    <span class="font-mono text-2xl font-black text-[#A3E635]">{{ calculatedReadinessScore }}%</span>
                </div>

                <!-- Submit Buttons -->
                <div class="mt-5 flex gap-2">
                    <button
                        type="button"
                        @click="isReadinessModalOpen = false"
                        class="flex-1 rounded-xl bg-zinc-800 hover:bg-zinc-700 py-2.5 text-xs font-semibold text-zinc-300 transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitReadiness"
                        :disabled="isSubmittingReadiness"
                        class="flex-1 rounded-xl bg-rose-500 hover:bg-rose-400 py-2.5 text-xs font-bold text-white shadow-lg shadow-rose-500/20 transition flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    >
                        <RefreshCw v-if="isSubmittingReadiness" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ isSubmittingReadiness ? 'Menyimpan...' : 'Simpan Kesiapan' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. WORKOUT LOGGER (CATAT SET LATIHAN) MODAL -->
        <div
            v-if="isWorkoutModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-200"
            @click.self="isWorkoutModalOpen = false"
        >
            <div class="relative w-full max-w-md rounded-[2.5rem] bg-[#18181B] border border-[#A3E635]/30 text-white p-6 shadow-2xl flex flex-col">
                <button
                    type="button"
                    @click="isWorkoutModalOpen = false"
                    class="absolute top-4 right-4 rounded-full bg-zinc-800 p-2 text-zinc-400 hover:bg-zinc-700 hover:text-white transition cursor-pointer"
                >
                    <X class="h-4 w-4" />
                </button>

                <div class="flex items-center gap-3 pb-3 border-b border-[#27272A]">
                    <div class="p-2.5 rounded-2xl bg-[#A3E635]/15 border border-[#A3E635]/30 text-[#A3E635]">
                        <Dumbbell class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-black text-white">Catat Set Latihan (Workout Log)</h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Catatan beban &amp; repetisi otomatis memperbarui heatmap pemulihan</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-zinc-300 block mb-1">Nama Latihan:</label>
                        <input
                            v-model="workoutForm.exercise_name"
                            type="text"
                            placeholder="Contoh: Barbell Bench Press, Squat, Lat Pulldown"
                            class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white outline-none focus:border-[#A3E635]"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-zinc-300 block mb-1">Bagian Otot:</label>
                            <select
                                v-model="workoutForm.muscle_group"
                                class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white outline-none focus:border-[#A3E635]"
                            >
                                <option value="Dada">Dada (Chest)</option>
                                <option value="Punggung">Punggung (Back)</option>
                                <option value="Kaki">Kaki (Legs)</option>
                                <option value="Bahu">Bahu (Shoulders)</option>
                                <option value="Lengan">Lengan (Arms)</option>
                                <option value="Core">Core (Perut)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-300 block mb-1">Set ke-:</label>
                            <input
                                v-model.number="workoutForm.set_number"
                                type="number"
                                min="1"
                                max="50"
                                class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white font-mono outline-none focus:border-[#A3E635]"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-zinc-300 block mb-1">Beban (kg):</label>
                            <input
                                v-model.number="workoutForm.weight_kg"
                                type="number"
                                step="0.5"
                                min="0"
                                max="500"
                                class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white font-mono outline-none focus:border-[#A3E635]"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-300 block mb-1">Repetisi (Reps):</label>
                            <input
                                v-model.number="workoutForm.reps"
                                type="number"
                                min="1"
                                max="200"
                                class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white font-mono outline-none focus:border-[#A3E635]"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-zinc-300 block mb-1">RPE (Skala Kelelahan 1-10):</label>
                            <select
                                v-model.number="workoutForm.rpe"
                                class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white outline-none focus:border-[#A3E635]"
                            >
                                <option :value="null">Opsional</option>
                                <option :value="6">RPE 6 (Sangat Mudah)</option>
                                <option :value="7">RPE 7 (Bisa 3 Reps Lagi)</option>
                                <option :value="8">RPE 8 (Bisa 2 Reps Lagi)</option>
                                <option :value="9">RPE 9 (Bisa 1 Rep Lagi)</option>
                                <option :value="10">RPE 10 (Maksimal / Failure)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-300 block mb-1">Catatan Tambahan:</label>
                            <input
                                v-model="workoutForm.notes"
                                type="text"
                                placeholder="Contoh: Form bersih, grip lebar"
                                class="w-full rounded-xl bg-zinc-950 border border-zinc-700 p-2.5 text-xs text-white outline-none focus:border-[#A3E635]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="mt-5 flex gap-2">
                    <button
                        type="button"
                        @click="isWorkoutModalOpen = false"
                        class="flex-1 rounded-xl bg-zinc-800 hover:bg-zinc-700 py-2.5 text-xs font-semibold text-zinc-300 transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitWorkoutLog"
                        :disabled="isSubmittingWorkout || !workoutForm.exercise_name"
                        class="flex-1 rounded-xl bg-[#A3E635] hover:bg-lime-400 py-2.5 text-xs font-black text-zinc-950 shadow-lg shadow-[#A3E635]/20 transition flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    >
                        <RefreshCw v-if="isSubmittingWorkout" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ isSubmittingWorkout ? 'Menyimpan...' : 'Simpan & Mulai Rest Timer' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
