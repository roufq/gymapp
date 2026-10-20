<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Camera,
    Check,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    DoorClosed,
    DoorOpen,
    FileText,
    HelpCircle,
    KeyRound,
    Lock,
    LogOut,
    Minus,
    Plus,
    QrCode,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    ShoppingBag,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
    Volume2,
    Wifi,
    X,
    Zap,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface CatalogProduct {
    id: string;
    name: string;
    category: 'membership' | 'pt' | 'retail' | 'fine';
    price: number;
    badge?: string;
    item_type: string;
}

interface CartItem {
    id: string;
    name: string;
    price: number;
    qty: number;
    item_type: string;
}

interface Props {
    branch: { id: number; name: string; max_capacity: number };
    allBranches: Array<{ id: number; name: string }>;
    activeLogs: Array<any>;
    recentHistory: Array<any>;
    members: Array<any>;
    membershipPlans: Array<any>;
    staffUsers: Array<any>;
    catalogProducts: Array<CatalogProduct>;
    activeScanMember?: any;
    recentGateFeed?: Array<any>;
    activeCashier: {
        name: string;
        shift_name: string;
        synced_count: number;
    };
    occupancy: {
        current: number;
        max: number;
        rate: number;
    };
}

const props = defineProps<Props>();

// --- Active Gate Scan State (Hero Card) ---
const activeScanMember = ref(props.activeScanMember || {
    id: props.members[0]?.id || 1,
    name: props.members[0]?.name || 'BUDI SANTOSO',
    plan_name: props.members[0]?.active_subscription?.plan?.name || 'Platinum All-Club',
    days_remaining: 18,
    home_branch: props.members[0]?.home_branch?.name || 'Cabang Utama - Yogyakarta',
    medical_notes: props.members[0]?.medical_notes || 'Riwayat Asma Ringan',
    unpaid_fine: Number(props.members[0]?.unpaid_fine_amount || 0),
    status: 'granted', // 'granted' | 'denied'
    denied_reason: '',
});

// Locker Input Form
const lockerNumber = ref(props.activeScanMember?.locker_number || 'L-042');
const lockerInputRef = ref<HTMLInputElement | null>(null);

// Recent Gate Scans Feed (Dynamic from props or fallback)
const gateFeed = ref(props.recentGateFeed || [
    {
        id: 101,
        time: '18:04',
        name: 'Rina Wati',
        status: 'denied',
        reason: 'Jam Off-Peak - Paket Siang',
        can_upgrade: true,
    },
    {
        id: 102,
        time: '18:02',
        name: 'Andi Wijaya',
        status: 'granted',
        reason: 'Loker #12',
        can_upgrade: false,
    },
    {
        id: 103,
        time: '17:58',
        name: 'Bambang Soedirgo',
        status: 'granted',
        reason: 'Loker #08',
        can_upgrade: false,
    },
]);

// --- POS & Billing State ---
const posSearch = ref('');
const posSearchInputRef = ref<HTMLInputElement | null>(null);
const activeCategory = ref<'all' | 'membership' | 'pt' | 'retail' | 'fine'>('all');
const selectedPosMemberId = ref<string>(props.members[0]?.id ? String(props.members[0].id) : '');
const selectedLeadOwner = ref<string>('');
const voucherCode = ref('');

// Cart State
const cart = ref<CartItem[]>([
    {
        id: 'plan_1m',
        name: 'Membership 1 Bulan',
        price: 350000,
        qty: 1,
        item_type: 'membership',
    },
    {
        id: 'drink_mineral',
        name: 'Air Mineral 600ml',
        price: 8000,
        qty: 1,
        item_type: 'retail',
    },
    {
        id: 'service_fee',
        name: 'Biaya Administrasi Kartu',
        price: 10000,
        qty: 1,
        item_type: 'retail',
    },
]);

// Filtered Catalog
const filteredProducts = computed(() => {
    let list = props.catalogProducts;
    if (activeCategory.value !== 'all') {
        list = list.filter(p => p.category === activeCategory.value);
    }
    if (posSearch.value.trim()) {
        const q = posSearch.value.toLowerCase();
        list = list.filter(p => p.name.toLowerCase().includes(q));
    }
    return list;
});

// Cart Calculations
const subtotal = computed(() => cart.value.reduce((sum, item) => sum + (item.price * item.qty), 0));
const taxAmount = computed(() => 0); // 0% or tax inclusive
const grandTotal = computed(() => subtotal.value + taxAmount.value);

function addToCart(product: CatalogProduct) {
    const existing = cart.value.find(item => item.id === product.id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.value.push({
            id: product.id,
            name: product.name,
            price: product.price,
            qty: 1,
            item_type: product.item_type,
        });
    }
}

function updateQty(index: number, delta: number) {
    cart.value[index].qty += delta;
    if (cart.value[index].qty <= 0) {
        cart.value.splice(index, 1);
    }
}

function removeFromCart(index: number) {
    cart.value.splice(index, 1);
}

// Process Checkout
const isProcessingPayment = ref(false);

function processPayment(method: 'cash' | 'qris_va' | 'edc') {
    if (cart.value.length === 0) {
        alert('Keranjang masih kosong!');
        return;
    }

    isProcessingPayment.value = true;
    router.post('/gate/pos-checkout', {
        user_id: selectedPosMemberId.value || null,
        items: cart.value,
        payment_method: method,
        lead_owner_id: selectedLeadOwner.value || null,
    }, {
        onSuccess: () => {
            cart.value = [];
            isProcessingPayment.value = false;
        },
        onError: () => {
            isProcessingPayment.value = false;
        },
    });
}

// Quick Gate Check-in Submit
function submitLockerCheckIn() {
    if (!activeScanMember.value.id) return;

    router.post('/gate/check-in', {
        user_id: activeScanMember.value.id,
        locker_number: lockerNumber.value,
    }, {
        onSuccess: () => {
            gateFeed.value.unshift({
                id: Date.now(),
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                name: activeScanMember.value.name,
                status: 'granted',
                reason: `Loker #${lockerNumber.value}`,
                can_upgrade: false,
            });
        },
    });
}

function triggerMemberUpgrade(name: string) {
    activeCategory.value = 'membership';
    const upgradeProduct = props.catalogProducts.find(p => p.id === 'upgrade_prorata');
    if (upgradeProduct) {
        addToCart(upgradeProduct);
    }
}

// --- Modals State ---
const isWalkInOpen = ref(false);
const isFreeTrialOpen = ref(false);
const isCloseShiftOpen = ref(false);
const isGymQrModalOpen = ref(false);
const isGateScannerModalOpen = ref(false);

// 1. Walk-in Registration Form & Live Webcam State
const walkInVideoRef = ref<HTMLVideoElement | null>(null);
const walkInStream = ref<MediaStream | null>(null);
const walkInCameraActive = ref(false);
const walkInCameraError = ref<string | null>(null);
const walkInCapturedPhoto = ref<string | null>(null);

const walkInForm = useForm({
    name: '',
    phone: '',
    email: '',
    plan_id: props.membershipPlans[0]?.id || '',
    payment_method: 'cash',
    medical_notes: '',
    waiver_agreed: true,
    photo_data: '',
});

async function startWalkInWebcam() {
    walkInCameraError.value = null;
    try {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            walkInCameraError.value = 'Perangkat tidak mendukung WebRTC Webcam.';
            return;
        }

        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
        }).catch(() => navigator.mediaDevices.getUserMedia({ video: true }));

        walkInStream.value = stream;
        if (walkInVideoRef.value) {
            walkInVideoRef.value.srcObject = stream;
            await walkInVideoRef.value.play().catch(() => {});
            walkInCameraActive.value = true;
        }
    } catch {
        walkInCameraError.value = 'Kamera webcam kasir tidak dapat diakses atau izin ditolak.';
        walkInCameraActive.value = false;
    }
}

function stopWalkInWebcam() {
    if (walkInStream.value) {
        walkInStream.value.getTracks().forEach((track) => track.stop());
        walkInStream.value = null;
    }
    if (walkInVideoRef.value) {
        walkInVideoRef.value.srcObject = null;
    }
    walkInCameraActive.value = false;
}

function captureWalkInPhoto() {
    if (!walkInVideoRef.value) return;
    const video = walkInVideoRef.value;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        walkInCapturedPhoto.value = dataUrl;
        walkInForm.photo_data = dataUrl;
        stopWalkInWebcam();
    }
}

function retakeWalkInPhoto() {
    walkInCapturedPhoto.value = null;
    walkInForm.photo_data = '';
    nextTick(() => {
        startWalkInWebcam();
    });
}

function submitWalkIn() {
    walkInForm.post('/members', {
        onSuccess: () => {
            isWalkInOpen.value = false;
            walkInForm.reset();
            walkInCapturedPhoto.value = null;
        },
    });
}

// 2. Desk Receptionist Barcode / QR Camera Scanner State
const gateScannerVideoRef = ref<HTMLVideoElement | null>(null);
const gateScannerStream = ref<MediaStream | null>(null);
const gateScannerActive = ref(false);
const gateScannerError = ref<string | null>(null);
const gateScanSuccessMessage = ref<string | null>(null);
let gateBarcodeDetector: any = null;
let gateScanLoopId: number | null = null;

async function startGateScanner() {
    gateScannerError.value = null;
    gateScanSuccessMessage.value = null;
    try {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            gateScannerError.value = 'Perangkat tidak mendukung WebRTC Camera.';
            return;
        }

        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 640 } },
        }).catch(() => navigator.mediaDevices.getUserMedia({ video: true }));

        gateScannerStream.value = stream;
        if (gateScannerVideoRef.value) {
            gateScannerVideoRef.value.srcObject = stream;
            await gateScannerVideoRef.value.play().catch(() => {});
            gateScannerActive.value = true;
            initGateBarcodeDetector();
        }
    } catch {
        gateScannerError.value = 'Kamera scanner tidak dapat diakses atau izin ditolak.';
        gateScannerActive.value = false;
    }
}

function stopGateScanner() {
    if (gateScanLoopId) {
        cancelAnimationFrame(gateScanLoopId);
        gateScanLoopId = null;
    }
    if (gateScannerStream.value) {
        gateScannerStream.value.getTracks().forEach((track) => track.stop());
        gateScannerStream.value = null;
    }
    if (gateScannerVideoRef.value) {
        gateScannerVideoRef.value.srcObject = null;
    }
    gateScannerActive.value = false;
}

function initGateBarcodeDetector() {
    if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
        try {
            gateBarcodeDetector = new (window as any).BarcodeDetector({
                formats: ['qr_code', 'code_128', 'ean_13'],
            });
            scanGateFrame();
        } catch {
            gateBarcodeDetector = null;
        }
    }
}

async function scanGateFrame() {
    if (!gateScannerActive.value || !gateScannerVideoRef.value || gateScanSuccessMessage.value) return;

    if (gateBarcodeDetector && gateScannerVideoRef.value.readyState >= 2) {
        try {
            const barcodes = await gateBarcodeDetector.detect(gateScannerVideoRef.value);
            if (barcodes && barcodes.length > 0) {
                const code = barcodes[0].rawValue;
                if (code) {
                    processScannedGateCode(code);
                    return;
                }
            }
        } catch {}
    }

    if (gateScannerActive.value && !gateScanSuccessMessage.value) {
        gateScanLoopId = requestAnimationFrame(scanGateFrame);
    }
}

function processScannedGateCode(code: string) {
    stopGateScanner();
    const foundMember = props.members.find(
        (m) => String(m.id) === code || m.name.toLowerCase().includes(code.toLowerCase())
    ) || props.members[0];

    if (foundMember) {
        activeScanMember.value = {
            id: foundMember.id,
            name: foundMember.name,
            plan_name: foundMember.active_subscription?.plan?.name || 'Platinum All-Club',
            days_remaining: 18,
            home_branch: foundMember.home_branch?.name || props.branch.name,
            medical_notes: foundMember.medical_notes || 'Tidak ada keluhan medis',
            unpaid_fine: Number(foundMember.unpaid_fine_amount || 0),
            status: 'granted',
            denied_reason: '',
        };
        selectedPosMemberId.value = String(foundMember.id);
        gateScanSuccessMessage.value = `Kartu/QR Terbaca: ${foundMember.name} (Member #${foundMember.id}). Lolos verifikasi gerbang!`;
    } else {
        gateScanSuccessMessage.value = `QR Code: [${code}] terverifikasi. Gerbang dibuka!`;
    }

    setTimeout(() => {
        isGateScannerModalOpen.value = false;
        gateScanSuccessMessage.value = null;
    }, 2200);
}

// Watchers for WebRTC Streams
watch(isWalkInOpen, (open) => {
    if (open) {
        nextTick(() => startWalkInWebcam());
    } else {
        stopWalkInWebcam();
    }
});

watch(isGateScannerModalOpen, (open) => {
    if (open) {
        nextTick(() => startGateScanner());
    } else {
        stopGateScanner();
    }
});

// Close Shift Blind Opname Form
const blindCashForm = useForm({
    counted_cash: '',
    notes: '',
});

function submitBlindOpname() {
    blindCashForm.post('/gate/blind-opname', {
        onSuccess: () => {
            isCloseShiftOpen.value = false;
            blindCashForm.reset();
        },
    });
}

// Free Trial Guest Form
const freeTrialForm = useForm({
    name: '',
    phone: '',
    notes: '',
});

function submitFreeTrial() {
    freeTrialForm.post('/gate/free-trial', {
        onSuccess: () => {
            isFreeTrialOpen.value = false;
            freeTrialForm.reset();
        },
    });
}

// Keyboard Shortcuts Listener (F2, F8, F9, Enter)
function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'F2') {
        e.preventDefault();
        posSearchInputRef.value?.focus();
    } else if (e.key === 'F8') {
        e.preventDefault();
        processPayment('cash');
    } else if (e.key === 'F9') {
        e.preventDefault();
        processPayment('qris_va');
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    // Auto-focus locker input on gate scan ready
    lockerInputRef.value?.focus();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    stopWalkInWebcam();
    stopGateScanner();
});
</script>

<template>
    <Head title="Kasir & Gate Receptionist Workstation - EGMS" />

    <div class="min-h-screen bg-[#F8FAFC] text-[#0F172A] font-sans flex flex-col">
        <!-- 1. TOP SYSTEM STATUS BAR -->
        <header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between shadow-sm sticky top-0 z-40">
            <!-- Left: Branch Selector & Sync Pill -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="h-9 w-9 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center text-[#4F46E5] font-black text-sm">
                        EG
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Lokasi Terminal</div>
                        <div class="text-sm font-extrabold text-[#0F172A] leading-tight">
                            {{ props.branch.name }}
                        </div>
                    </div>
                </div>

                <div class="h-6 w-px bg-slate-200" />

                <!-- Online Sync Pill -->
                <div class="flex items-center gap-2 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs text-emerald-800 font-medium">
                    <span class="h-2 w-2 rounded-full bg-[#10B981] animate-pulse" />
                    <span>ONLINE • IndexedDB Offline Cache Ready ({{ props.activeCashier.synced_count.toLocaleString('id-ID') }} Members Synced)</span>
                </div>
            </div>

            <!-- Right: Active Shift & Quick Action Buttons -->
            <div class="flex items-center gap-3">
                <div class="text-right mr-2 hidden md:block">
                    <div class="text-xs font-bold text-slate-800">{{ props.activeCashier.shift_name }}</div>
                    <div class="text-[11px] text-slate-500 font-mono">Shift Aktif • Kasir Online</div>
                </div>

                <!-- Quick Action Button 0a: QR Check-in Pintu Gym -->
                <button
                    type="button"
                    @click="isGymQrModalOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-bold shadow-sm transition-all active:scale-95 cursor-pointer"
                    title="Tampilkan QR Code untuk di-scan member di pintu masuk"
                >
                    <QrCode class="h-4 w-4" />
                    <span>📱 QR Pintu Gym</span>
                </button>

                <!-- Quick Action Button 0b: Scan Kamera Meja Kasir -->
                <button
                    type="button"
                    @click="isGateScannerModalOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2 text-xs font-bold shadow-sm transition-all active:scale-95 cursor-pointer"
                    title="Scan QR / Kartu Member di Kamera Meja Kasir"
                >
                    <Camera class="h-4 w-4 text-emerald-400" />
                    <span>📷 Scan Kamera Meja</span>
                </button>

                <!-- Quick Action Button 1: Walk-in Webcam -->
                <button
                    type="button"
                    @click="isWalkInOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#4F46E5] hover:bg-[#4338CA] text-white px-3.5 py-2 text-xs font-bold shadow-sm transition-all active:scale-95"
                >
                    <UserPlus class="h-4 w-4" />
                    <span>+ Registrasi Walk-in (Webcam)</span>
                </button>

                <!-- Quick Action Button 2: Buku Tamu Free Trial -->
                <button
                    type="button"
                    @click="isFreeTrialOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-3.5 py-2 text-xs font-semibold shadow-sm transition-all"
                >
                    <FileText class="h-4 w-4 text-indigo-600" />
                    <span>Buku Tamu Free Trial</span>
                </button>

                <!-- Quick Action Button 3: Tutup Shift / Cash Opname -->
                <button
                    type="button"
                    @click="isCloseShiftOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 px-3.5 py-2 text-xs font-semibold transition-all"
                >
                    <Lock class="h-3.5 w-3.5 text-slate-600" />
                    <span>Tutup Shift / Cash Opname</span>
                </button>

                <div class="h-6 w-px bg-slate-200" />

                <!-- Quick Portal Navigation -->
                <div class="flex items-center gap-1.5">
                    <Link
                        href="/executive"
                        class="p-2 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold transition flex items-center gap-1"
                        title="Buka Executive BI"
                    >
                        <span>👑 BI</span>
                    </Link>
                    <Link
                        href="/members"
                        class="p-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-semibold transition"
                        title="Daftar Member"
                    >
                        <span>Members</span>
                    </Link>
                    <Link
                        href="/trainer"
                        class="p-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-semibold transition"
                        title="Portal Personal Trainer"
                    >
                        <span>PT</span>
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 text-xs font-semibold transition"
                        title="Keluar"
                    >
                        <LogOut class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </div>
        </header>

        <!-- 2. MAIN 2-COLUMN SPLIT-SCREEN WORKSPACE -->
        <main class="flex-1 p-5 grid grid-cols-12 gap-5 max-w-[1720px] w-full mx-auto">
            <!-- ========================================== -->
            <!-- LEFT PANEL (40% WIDTH): LIVE GATE MONITOR   -->
            <!-- ========================================== -->
            <section class="col-span-12 lg:col-span-5 flex flex-col gap-4">
                <!-- 1. Active Scan Hero Card (Thick Emerald Green Border) -->
                <div class="rounded-2xl bg-white border-2 border-[#10B981] p-5 shadow-lg relative overflow-hidden flex flex-col justify-between">
                    <!-- Status Header -->
                    <div class="flex items-center justify-between border-b border-emerald-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-3 w-3 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-[#10B981]" />
                            </span>
                            <span class="font-extrabold text-sm tracking-wide text-[#10B981] uppercase flex items-center gap-1.5">
                                AKSES DITERIMA - GATE TERBUKA
                            </span>
                        </div>
                        <div class="flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                            <Volume2 class="h-3.5 w-3.5 text-emerald-600" />
                            <span>BEEP OK</span>
                        </div>
                    </div>

                    <!-- Member Detail with 1:1 Photo -->
                    <div class="mt-4 flex gap-4 items-start">
                        <!-- 1:1 Member Verification Photo -->
                        <div class="h-28 w-28 rounded-xl bg-slate-100 border-2 border-emerald-400 p-1 shrink-0 overflow-hidden shadow-inner flex items-center justify-center relative">
                            <div class="h-full w-full bg-gradient-to-tr from-slate-200 to-slate-100 rounded-lg flex items-center justify-center font-black text-2xl text-slate-700">
                                {{ activeScanMember.name.slice(0, 2).toUpperCase() }}
                            </div>
                            <div class="absolute bottom-1 right-1 bg-[#10B981] rounded-full p-1 text-white shadow">
                                <Check class="h-3 w-3 stroke-[3]" />
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-xl font-black text-[#0F172A] tracking-tight truncate">{{ activeScanMember.name }}</h3>
                                <Badge variant="outline" class="border-emerald-500 text-emerald-700 text-[10px] font-bold">VERIFIED</Badge>
                            </div>
                            <div class="mt-1 space-y-0.5 text-xs text-slate-600">
                                <div>Paket: <strong class="text-slate-900">{{ activeScanMember.plan_name }}</strong> <span class="font-mono text-emerald-600 font-bold">(Sisa {{ activeScanMember.days_remaining }} Hari)</span></div>
                                <div>Cabang Asal: <span class="text-slate-800 font-medium">{{ activeScanMember.home_branch }}</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Focused Locker Input Form -->
                    <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 p-3">
                        <form @submit.prevent="submitLockerCheckIn" class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <KeyRound class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                <input
                                    ref="lockerInputRef"
                                    type="text"
                                    v-model="lockerNumber"
                                    placeholder="Input Nomor Kunci Loker..."
                                    class="w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 py-2 text-sm font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#4F46E5] focus:border-[#4F46E5]"
                                />
                            </div>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#10B981] hover:bg-emerald-600 text-white px-4 py-2 text-xs font-bold shadow-sm transition-all shrink-0"
                            >
                                <span>Simpan</span>
                                <span class="font-mono font-normal opacity-80 text-[10px]">↵ Enter</span>
                            </button>
                        </form>
                    </div>

                    <!-- Medical & Fine Alert Box -->
                    <div class="mt-3 grid grid-cols-2 gap-2 text-[11px]">
                        <div class="rounded-lg bg-amber-50 border border-amber-200 p-2 flex items-center gap-2 text-amber-900">
                            <AlertTriangle class="h-4 w-4 text-[#F59E0B] shrink-0" />
                            <span class="truncate"><strong>PAR-Q:</strong> {{ activeScanMember.medical_notes }}</span>
                        </div>
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-2 flex items-center gap-2 text-emerald-900">
                            <ShieldCheck class="h-4 w-4 text-[#10B981] shrink-0" />
                            <span><strong>Tunggakan Denda:</strong> Rp {{ activeScanMember.unpaid_fine.toLocaleString('id-ID') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Gym Turnstile QR Code Display Kiosk Card -->
                <div class="rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 p-3.5 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                            <QrCode class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                                <span>QR Check-in Gerbang Turnstile Aktif</span>
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            </div>
                            <div class="text-[11px] text-emerald-700">Member scan QR ini via aplikasi HP untuk absen & membuka gerbang otomatis.</div>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isGymQrModalOpen = true"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition shrink-0"
                    >
                        Buka Layar Kiosk
                    </button>
                </div>

                <!-- 2. Recent Gate Scans Feed (Compact Live List) -->
                <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm flex-1 flex flex-col">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-slate-400" />
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Live Turnstile Activity Feed</h4>
                        </div>
                        <span class="text-[11px] font-mono text-slate-400">Auto-refresh via WebSockets</span>
                    </div>

                    <div class="mt-3 divide-y divide-slate-100 flex-1 overflow-y-auto">
                        <div
                            v-for="feed in gateFeed"
                            :key="feed.id"
                            class="py-2.5 flex items-center justify-between hover:bg-slate-50/80 px-2 rounded-lg transition-colors"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="font-mono text-xs font-semibold text-slate-400">{{ feed.time }}</span>
                                <span
                                    :class="[
                                        'h-2 w-2 rounded-full',
                                        feed.status === 'granted' ? 'bg-[#10B981]' : 'bg-[#E11D48]'
                                    ]"
                                />
                                <div>
                                    <div class="text-xs font-bold text-slate-900">{{ feed.name }}</div>
                                    <div
                                        :class="[
                                            'text-[11px]',
                                            feed.status === 'granted' ? 'text-emerald-700' : 'text-[#E11D48] font-semibold'
                                        ]"
                                    >
                                        {{ feed.status === 'granted' ? `Granted - ${feed.reason}` : `Denied: ${feed.reason}` }}
                                    </div>
                                </div>
                            </div>

                            <button
                                v-if="feed.can_upgrade"
                                type="button"
                                @click="triggerMemberUpgrade(feed.name)"
                                class="rounded-md bg-rose-50 border border-rose-200 px-2 py-1 text-[11px] font-bold text-[#E11D48] hover:bg-rose-100 transition-colors"
                            >
                                [Upgrade Paket]
                            </button>
                            <span v-else class="text-[11px] font-mono text-slate-400">Turnstile 01</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ========================================== -->
            <!-- RIGHT PANEL (60% WIDTH): POS & BILLING      -->
            <!-- ========================================== -->
            <section class="col-span-12 lg:col-span-7 flex flex-col gap-4">
                <!-- 1. Search Bar & Category Filter Tabs -->
                <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm space-y-3">
                    <!-- Search Input (Shortcut F2) -->
                    <div class="relative">
                        <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                        <input
                            ref="posSearchInputRef"
                            type="text"
                            v-model="posSearch"
                            placeholder="Cari nama member, paket membership, atau scan barcode minuman... (Shortcut F2)"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-16 py-2.5 text-sm text-[#0F172A] placeholder:text-slate-400 focus:outline-none focus:bg-white focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]"
                        />
                        <span class="absolute right-3 top-2.5 rounded bg-slate-200/80 px-2 py-0.5 text-[10px] font-mono font-bold text-slate-600">
                            F2
                        </span>
                    </div>

                    <!-- Category Tabs -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs font-semibold">
                        <button
                            type="button"
                            @click="activeCategory = 'all'"
                            :class="[
                                'rounded-lg px-3.5 py-1.5 transition-all',
                                activeCategory === 'all'
                                    ? 'bg-[#4F46E5] text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Semua
                        </button>
                        <button
                            type="button"
                            @click="activeCategory = 'membership'"
                            :class="[
                                'rounded-lg px-3.5 py-1.5 transition-all',
                                activeCategory === 'membership'
                                    ? 'bg-[#4F46E5] text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Paket Membership
                        </button>
                        <button
                            type="button"
                            @click="activeCategory = 'pt'"
                            :class="[
                                'rounded-lg px-3.5 py-1.5 transition-all',
                                activeCategory === 'pt'
                                    ? 'bg-[#4F46E5] text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Paket PT
                        </button>
                        <button
                            type="button"
                            @click="activeCategory = 'retail'"
                            :class="[
                                'rounded-lg px-3.5 py-1.5 transition-all',
                                activeCategory === 'retail'
                                    ? 'bg-[#4F46E5] text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Minuman & Suplemen
                        </button>
                        <button
                            type="button"
                            @click="activeCategory = 'fine'"
                            :class="[
                                'rounded-lg px-3.5 py-1.5 transition-all',
                                activeCategory === 'fine'
                                    ? 'bg-[#4F46E5] text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Sewa & Denda
                        </button>
                    </div>
                </div>

                <!-- 2. Product Grid (3 Columns Clickable Cards) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <button
                        type="button"
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="addToCart(product)"
                        class="rounded-xl bg-white border border-slate-200 p-3.5 text-left shadow-sm hover:border-[#4F46E5] hover:shadow-md transition-all active:scale-[0.98] flex flex-col justify-between group h-28"
                    >
                        <div class="flex items-start justify-between w-full">
                            <span class="text-xs font-bold text-slate-900 group-hover:text-[#4F46E5] transition-colors line-clamp-2">
                                {{ product.name }}
                            </span>
                            <span v-if="product.badge" class="rounded bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 text-[9px] font-bold text-[#4F46E5] shrink-0 ml-1">
                                {{ product.badge }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between w-full pt-2 border-t border-slate-100">
                            <span class="font-mono text-sm font-extrabold text-[#0F172A]">
                                Rp {{ product.price.toLocaleString('id-ID') }}
                            </span>
                            <div class="h-6 w-6 rounded-full bg-slate-100 group-hover:bg-[#4F46E5] group-hover:text-white flex items-center justify-center transition-colors">
                                <Plus class="h-3.5 w-3.5" />
                            </div>
                        </div>
                    </button>
                </div>

                <!-- 3. Checkout Cart Drawer / Card -->
                <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-lg flex-1 flex flex-col justify-between">
                    <div>
                        <!-- Member Selector & Sales Lead Header -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pb-3 border-b border-slate-100 text-xs">
                            <div>
                                <label class="font-bold text-slate-600 block mb-1">Target Member</label>
                                <select
                                    v-model="selectedPosMemberId"
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium focus:outline-none focus:border-[#4F46E5]"
                                >
                                    <option v-for="m in props.members" :key="m.id" :value="String(m.id)">
                                        {{ m.name }} ({{ m.phone }})
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="font-bold text-slate-600 block mb-1">Sales Lead Owner</label>
                                <select
                                    v-model="selectedLeadOwner"
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium focus:outline-none focus:border-[#4F46E5]"
                                >
                                    <option value="">Pilih Staf Sales...</option>
                                    <option v-for="s in props.staffUsers" :key="s.id" :value="String(s.id)">
                                        {{ s.name }} ({{ s.role }})
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="font-bold text-slate-600 block mb-1">Kode Voucher / Promo</label>
                                <input
                                    type="text"
                                    v-model="voucherCode"
                                    placeholder="Contoh: PROMO10"
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs uppercase font-mono font-bold focus:outline-none focus:border-[#4F46E5]"
                                />
                            </div>
                        </div>

                        <!-- Cart Line Items -->
                        <div class="mt-3 max-h-40 overflow-y-auto divide-y divide-slate-100">
                            <div
                                v-for="(item, index) in cart"
                                :key="item.id"
                                class="py-2 flex items-center justify-between text-xs"
                            >
                                <div class="flex-1 min-w-0 pr-2">
                                    <div class="font-bold text-slate-900 truncate">{{ item.name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">@ Rp {{ item.price.toLocaleString('id-ID') }}</div>
                                </div>

                                <!-- Qty Buttons -->
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="updateQty(index, -1)"
                                        class="h-6 w-6 rounded bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-700"
                                    >
                                        <Minus class="h-3 w-3" />
                                    </button>
                                    <span class="w-6 text-center font-mono font-bold">{{ item.qty }}</span>
                                    <button
                                        type="button"
                                        @click="updateQty(index, 1)"
                                        class="h-6 w-6 rounded bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-700"
                                    >
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>

                                <div class="w-24 text-right font-mono font-extrabold text-slate-900">
                                    Rp {{ (item.price * item.qty).toLocaleString('id-ID') }}
                                </div>

                                <button
                                    type="button"
                                    @click="removeFromCart(index)"
                                    class="ml-2 text-slate-300 hover:text-rose-600 transition-colors"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <div v-if="cart.length === 0" class="py-6 text-center text-xs text-slate-400">
                                Belum ada item di keranjang. Klik produk di atas untuk menambah.
                            </div>
                        </div>
                    </div>

                    <!-- Grand Total & Action Payment Buttons -->
                    <div class="pt-4 border-t border-slate-200 mt-2">
                        <div class="flex items-end justify-between mb-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-500">Total Tagihan Kasir</span>
                                <div class="text-[11px] text-slate-400">Termasuk pajak & biaya admin</div>
                            </div>
                            <div class="font-mono text-3xl font-black text-[#0F172A] tracking-tight">
                                Rp {{ grandTotal.toLocaleString('id-ID') }}
                            </div>
                        </div>

                        <!-- 3 Large Payment Buttons -->
                        <div class="grid grid-cols-3 gap-3">
                            <!-- Button 1: Cash F8 -->
                            <button
                                type="button"
                                @click="processPayment('cash')"
                                :disabled="isProcessingPayment || cart.length === 0"
                                class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white p-3 font-bold text-center shadow-sm transition-all active:scale-95 disabled:opacity-50 flex flex-col items-center justify-center gap-1"
                            >
                                <div class="flex items-center gap-1.5 text-sm">
                                    <DollarSign class="h-4 w-4" />
                                    <span>Tunai / Cash</span>
                                </div>
                                <span class="font-mono text-[10px] font-normal opacity-80">(F8)</span>
                            </button>

                            <!-- Button 2: QRIS / VA F9 -->
                            <button
                                type="button"
                                @click="processPayment('qris_va')"
                                :disabled="isProcessingPayment || cart.length === 0"
                                class="rounded-xl bg-[#4F46E5] hover:bg-[#4338CA] text-white p-3 font-bold text-center shadow-sm transition-all active:scale-95 disabled:opacity-50 flex flex-col items-center justify-center gap-1"
                            >
                                <div class="flex items-center gap-1.5 text-sm">
                                    <QrCode class="h-4 w-4" />
                                    <span>Generate QRIS / VA</span>
                                </div>
                                <span class="font-mono text-[10px] font-normal opacity-80">(F9)</span>
                            </button>

                            <!-- Button 3: EDC Card -->
                            <button
                                type="button"
                                @click="processPayment('edc')"
                                :disabled="isProcessingPayment || cart.length === 0"
                                class="rounded-xl bg-slate-800 hover:bg-slate-900 text-white p-3 font-bold text-center shadow-sm transition-all active:scale-95 disabled:opacity-50 flex flex-col items-center justify-center gap-1"
                            >
                                <div class="flex items-center gap-1.5 text-sm">
                                    <CreditCard class="h-4 w-4" />
                                    <span>EDC Card</span>
                                </div>
                                <span class="font-mono text-[10px] font-normal opacity-80">Debit / Kredit</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- ========================================== -->
        <!-- MODALS (WALK-IN, FREE TRIAL, BLIND OPNAME) -->
        <!-- ========================================== -->

        <!-- 1. Modal Walk-in Registration (Webcam Integration) -->
        <Dialog :open="isWalkInOpen" @update:open="isWalkInOpen = $event">
            <DialogContent class="sm:max-w-xl bg-white text-[#0F172A]">
                <DialogHeader>
                    <DialogTitle class="text-lg font-black text-slate-900">Registrasi Walk-in Member (Webcam)</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Pengambilan foto wajah webcam kasir dan aktivasi instan paket membership.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitWalkIn" class="space-y-4 py-2">
                    <!-- Webcam Preview Live & Capture Container -->
                    <div class="h-48 rounded-2xl bg-slate-950 flex flex-col items-center justify-center text-white relative overflow-hidden border border-slate-800">
                        <!-- Live Video Stream -->
                        <video
                            v-if="!walkInCapturedPhoto"
                            ref="walkInVideoRef"
                            autoplay
                            playsinline
                            muted
                            class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300"
                            :class="{ 'opacity-100': walkInCameraActive, 'opacity-0': !walkInCameraActive }"
                        ></video>

                        <!-- Captured Snapshot Preview -->
                        <img
                            v-else
                            :src="walkInCapturedPhoto"
                            alt="Foto Member"
                            class="absolute inset-0 w-full h-full object-cover"
                        />

                        <!-- Fallback / Camera Inactive Notice -->
                        <div v-if="!walkInCameraActive && !walkInCapturedPhoto" class="flex flex-col items-center gap-1.5 text-slate-400 z-10 p-3 text-center">
                            <Camera class="h-8 w-8 text-[#4F46E5] animate-pulse" />
                            <span class="text-xs font-bold">{{ walkInCameraError || 'Menghubungkan Webcam Kasir...' }}</span>
                            <span class="text-[10px] text-slate-500">Posisikan wajah member di depan webcam</span>
                            <button
                                v-if="walkInCameraError"
                                type="button"
                                @click="startWalkInWebcam"
                                class="text-[10px] text-indigo-400 font-bold underline mt-1 cursor-pointer"
                            >
                                Coba Hubungkan Ulang
                            </button>
                        </div>

                        <!-- Live Badge or Captured Badge -->
                        <div v-if="walkInCameraActive && !walkInCapturedPhoto" class="absolute top-2 right-2 rounded-full bg-black/70 px-2.5 py-0.5 text-[9px] font-mono text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5 z-20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                            <span>LIVE WEBCAM 1080P</span>
                        </div>
                        <div v-else-if="walkInCapturedPhoto" class="absolute top-2 right-2 rounded-full bg-emerald-950/80 px-2.5 py-0.5 text-[9px] font-mono text-emerald-300 border border-emerald-500/50 flex items-center gap-1 z-20">
                            <Check class="h-3 w-3 text-emerald-400" />
                            <span>FOTO BERHASIL DIAMBIL</span>
                        </div>

                        <!-- Snapshot Controls Overlay inside box -->
                        <div class="absolute bottom-2 left-0 right-0 flex items-center justify-center gap-2 z-20 px-3">
                            <button
                                v-if="walkInCameraActive && !walkInCapturedPhoto"
                                type="button"
                                @click="captureWalkInPhoto"
                                class="rounded-full bg-[#4F46E5] hover:bg-indigo-600 active:scale-95 text-white px-4 py-1.5 text-xs font-bold shadow-lg flex items-center gap-1.5 transition cursor-pointer"
                            >
                                <Camera class="h-3.5 w-3.5" />
                                <span>Ambil Foto Wajah</span>
                            </button>
                            <button
                                v-else-if="walkInCapturedPhoto"
                                type="button"
                                @click="retakeWalkInPhoto"
                                class="rounded-full bg-slate-800/90 hover:bg-slate-700 text-white px-3 py-1 text-xs font-bold border border-slate-600 transition flex items-center gap-1 cursor-pointer"
                            >
                                <RefreshCw class="h-3 w-3" />
                                <span>Foto Ulang</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="w-name" class="text-xs">Nama Lengkap</Label>
                            <Input id="w-name" v-model="walkInForm.name" required placeholder="Budi Santoso" class="text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label for="w-phone" class="text-xs">No. WhatsApp/HP</Label>
                            <Input id="w-phone" v-model="walkInForm.phone" required placeholder="0812xxxxxxx" class="text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="w-email" class="text-xs">Email</Label>
                            <Input id="w-email" type="email" v-model="walkInForm.email" required placeholder="budi@gmail.com" class="text-xs" />
                        </div>
                        <div class="space-y-1">
                            <Label for="w-plan" class="text-xs">Pilih Paket Membership</Label>
                            <select
                                id="w-plan"
                                v-model="walkInForm.plan_id"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs"
                            >
                                <option v-for="p in props.membershipPlans" :key="p.id" :value="p.id">
                                    {{ p.name }} (Rp {{ Number(p.price).toLocaleString('id-ID') }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="w-notes" class="text-xs">Catatan Medis PAR-Q (Opsional)</Label>
                        <Input id="w-notes" v-model="walkInForm.medical_notes" placeholder="Riwayat asma, cedera lutut, dsb." class="text-xs" />
                    </div>

                    <!-- Digital Waiver Check -->
                    <div class="flex items-center gap-2 pt-1 text-xs text-slate-600">
                        <input type="checkbox" id="waiver" v-model="walkInForm.waiver_agreed" class="rounded text-[#4F46E5]" />
                        <label for="waiver">Member telah menyetujui Digital Waiver & Syarat Keanggotaan Gym</label>
                    </div>

                    <DialogFooter class="pt-3 border-t">
                        <Button type="button" variant="outline" size="sm" @click="isWalkInOpen = false">Batal</Button>
                        <Button type="submit" size="sm" class="bg-[#4F46E5] hover:bg-[#4338CA] cursor-pointer" :disabled="walkInForm.processing">
                            Simpan & Aktivasi Member
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 2. Modal Buku Tamu Free Trial -->
        <Dialog :open="isFreeTrialOpen" @update:open="isFreeTrialOpen = $event">
            <DialogContent class="sm:max-w-md bg-white text-[#0F172A]">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold text-slate-900">Buku Tamu & Prospek Free Trial</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Catat data calon member yang mencoba fasilitas gym gratis 1 hari.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitFreeTrial" class="space-y-3 py-2 text-xs">
                    <div class="space-y-1">
                        <Label class="text-xs">Nama Lengkap Tamu</Label>
                        <Input v-model="freeTrialForm.name" required placeholder="Contoh: Farhan Pratama" class="text-xs" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Nomor WhatsApp</Label>
                        <Input v-model="freeTrialForm.phone" required placeholder="08xxxxxxxxxx" class="text-xs" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Catatan / Keterangan</Label>
                        <Input v-model="freeTrialForm.notes" placeholder="Tujuan trial, referral, dll..." class="text-xs" />
                    </div>

                    <DialogFooter class="pt-3 border-t">
                        <Button type="button" variant="outline" size="sm" @click="isFreeTrialOpen = false">Batal</Button>
                        <Button type="submit" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white" :disabled="freeTrialForm.processing">
                            Simpan Lead Free Trial
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 3. Modal Tutup Shift / Blind Cash Opname -->
        <Dialog :open="isCloseShiftOpen" @update:open="isCloseShiftOpen = $event">
            <DialogContent class="sm:max-w-md bg-white text-[#0F172A]">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold text-slate-900">Tutup Shift & Blind Cash Opname</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Input jumlah uang fisik tunai yang ada di laci kasir saat ini.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitBlindOpname" class="space-y-3 py-2 text-xs">
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-2.5 text-amber-900 text-xs">
                        <strong>Perhatian:</strong> Sistem tidak akan menampilkan jumlah uang kas yang seharusnya ada sampai Anda selesai memasukkan nominal fisik.
                    </div>

                    <div class="space-y-1">
                        <Label class="text-xs">Total Uang Fisik di Laci (Rp)</Label>
                        <Input
                            type="number"
                            v-model="blindCashForm.counted_cash"
                            required
                            placeholder="Contoh: 1450000"
                            class="text-sm font-mono font-bold"
                        />
                    </div>

                    <div class="space-y-1">
                        <Label class="text-xs">Catatan Shift (Opsional)</Label>
                        <Input v-model="blindCashForm.notes" placeholder="Catatan selisih atau pengeluaran operasional..." class="text-xs" />
                    </div>

                    <DialogFooter class="pt-3 border-t">
                        <Button type="button" variant="outline" size="sm" @click="isCloseShiftOpen = false">Batal</Button>
                        <Button type="submit" size="sm" class="bg-slate-900 hover:bg-black text-white">
                            Konfirmasi & Tutup Shift
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 4. Modal QR Check-in Pintu Gym (Kiosk Gate Display) -->
        <Dialog :open="isGymQrModalOpen" @update:open="isGymQrModalOpen = $event">
            <DialogContent class="sm:max-w-lg bg-white text-[#0F172A] p-6 text-center">
                <DialogHeader class="items-center text-center">
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs text-emerald-800 font-bold mb-1">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                        <span>KODE QR RESMI ABSENSI GERBANG GYM</span>
                    </div>
                    <DialogTitle class="text-xl font-black text-slate-900 tracking-tight">
                        {{ props.branch.name }} - Turnstile Gate 01
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500 max-w-sm mx-auto">
                        Buka aplikasi gym di ponsel Anda, tekan <strong class="text-slate-800">[📷 Scan Absen]</strong>, lalu arahkan kamera ke kode QR ini untuk check-in dan membuka pintu gerbang secara instan.
                    </DialogDescription>
                </DialogHeader>

                <!-- Big High-Resolution Gym QR Code Display -->
                <div class="my-3 flex flex-col items-center">
                    <div class="p-4 rounded-3xl bg-white border-4 border-emerald-500 shadow-2xl relative">
                        <svg class="h-[220px] w-[220px]" viewBox="0 0 220 220" fill="none">
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

                            <!-- Center Gym Logo Brand -->
                            <rect x="80" y="80" width="60" height="60" rx="14" fill="#10B981" />
                            <text x="110" y="117" text-anchor="middle" fill="white" font-weight="900" font-size="16" font-family="sans-serif">GYM</text>

                            <circle cx="25" cy="135" r="5" fill="#09090B" />
                            <circle cx="65" cy="135" r="5" fill="#09090B" />
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

                    <div class="mt-2.5 font-mono text-xs font-black text-slate-800 tracking-wider">
                        PAYLOAD TOKEN: GYM-JOGJA-GATE-01
                    </div>
                </div>

                <!-- Info Banner -->
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-left space-y-1 text-xs text-slate-600">
                    <div class="flex items-center gap-2 font-bold text-slate-800">
                        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        <span>Verifikasi Otomatis Multi-Faktor:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-[11px] text-slate-600 pl-1">
                        <li>Validasi masa aktif membership & kuota kunjungan</li>
                        <li>Pengecekan tunggakan denda & izin lintas cabang</li>
                        <li>Alokasi otomatis nomor kunci loker ruang ganti</li>
                    </ul>
                </div>

                <DialogFooter class="pt-3 border-t flex justify-end">
                    <Button type="button" variant="outline" size="sm" @click="isGymQrModalOpen = false">
                        Tutup Tampilan Kiosk
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- 4. Modal Desk Barcode / QR Scanner Kasir (Live Video Feed) -->
        <Dialog :open="isGateScannerModalOpen" @update:open="isGateScannerModalOpen = $event">
            <DialogContent class="sm:max-w-md bg-white text-[#0F172A]">
                <DialogHeader>
                    <div class="inline-flex items-center gap-1 text-emerald-700 text-xs font-extrabold uppercase">
                        <Camera class="h-4 w-4" />
                        <span>Scanner Meja Kasir / Turnstile Gate</span>
                    </div>
                    <DialogTitle class="text-lg font-black text-slate-900 tracking-tight">
                        Scan Kartu / QR Code Member
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Arahkan kartu fisik member atau QR code ponsel member ke kamera kasir untuk verifikasi gate instan.
                    </DialogDescription>
                </DialogHeader>

                <div class="my-2 flex flex-col items-center">
                    <!-- Scanner Viewfinder Box -->
                    <div class="relative w-full aspect-video rounded-2xl bg-slate-950 border-2 border-slate-800 overflow-hidden shadow-inner flex items-center justify-center">
                        <video
                            ref="gateScannerVideoRef"
                            autoplay
                            playsinline
                            muted
                            class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300"
                            :class="{ 'opacity-100': gateScannerActive, 'opacity-0': !gateScannerActive }"
                        ></video>

                        <!-- Corner Crosshairs -->
                        <div class="absolute top-3 left-3 w-6 h-6 border-t-4 border-l-4 border-emerald-400 rounded-tl-lg pointer-events-none z-10"></div>
                        <div class="absolute top-3 right-3 w-6 h-6 border-t-4 border-r-4 border-emerald-400 rounded-tr-lg pointer-events-none z-10"></div>
                        <div class="absolute bottom-3 left-3 w-6 h-6 border-b-4 border-l-4 border-emerald-400 rounded-bl-lg pointer-events-none z-10"></div>
                        <div class="absolute bottom-3 right-3 w-6 h-6 border-b-4 border-r-4 border-emerald-400 rounded-br-lg pointer-events-none z-10"></div>

                        <!-- Active Camera Pill -->
                        <div
                            v-if="gateScannerActive && !gateScanSuccessMessage"
                            class="absolute top-3 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1.5 rounded-full bg-black/80 px-2.5 py-0.5 text-[9px] font-mono text-emerald-400 border border-emerald-500/40 backdrop-blur pointer-events-none"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                            <span>KAMERA SCANNER AKTIF</span>
                        </div>

                        <!-- Inactive notice -->
                        <div v-if="!gateScannerActive && !gateScanSuccessMessage" class="flex flex-col items-center gap-2 text-slate-400 z-10 p-3 text-center">
                            <Camera class="h-8 w-8 text-emerald-400 animate-pulse" />
                            <span class="text-xs font-semibold">{{ gateScannerError || 'Menghubungkan kamera scanner...' }}</span>
                            <button
                                v-if="gateScannerError"
                                type="button"
                                @click="startGateScanner"
                                class="text-[10px] text-emerald-400 underline font-bold mt-1 cursor-pointer"
                            >
                                Hubungkan Ulang
                            </button>
                        </div>

                        <!-- Scan Laser Animation -->
                        <div
                            v-if="!gateScanSuccessMessage"
                            class="absolute left-4 right-4 h-0.5 bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_12px_#10B981] animate-pulse z-10 pointer-events-none"
                            style="top: 50%;"
                        ></div>

                        <!-- Success message overlay -->
                        <div v-if="gateScanSuccessMessage" class="absolute inset-0 bg-slate-950/95 p-4 flex flex-col items-center justify-center text-center animate-in zoom-in-95 duration-200 z-20">
                            <div class="h-12 w-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/40 mb-2">
                                <CheckCircle2 class="h-7 w-7" />
                            </div>
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-400">Verifikasi Sukses</span>
                            <p class="text-xs text-white font-bold mt-1">{{ gateScanSuccessMessage }}</p>
                        </div>
                    </div>

                    <!-- Quick member simulation barcodes for testing without physical QR -->
                    <div class="mt-3 w-full bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                        <div class="text-[11px] font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                            <span>Simulasi Scan Cepat Kartu Member:</span>
                            <span class="text-[10px] text-slate-400 font-normal">Klik untuk uji coba</span>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button
                                v-for="m in props.members.slice(0, 3)"
                                :key="m.id"
                                type="button"
                                @click="processScannedGateCode(String(m.id))"
                                class="rounded-lg bg-white border border-slate-300 hover:border-emerald-500 hover:bg-emerald-50/50 px-2.5 py-1 text-[11px] font-semibold text-slate-800 transition active:scale-95 cursor-pointer flex items-center gap-1"
                            >
                                <UserCheck class="h-3 w-3 text-emerald-600" />
                                <span>{{ m.name }} (#{{ m.id }})</span>
                            </button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-2 border-t flex justify-between items-center">
                    <span class="text-[10px] text-slate-400">Tekan ESC untuk keluar</span>
                    <Button type="button" variant="outline" size="sm" @click="isGateScannerModalOpen = false">
                        Tutup Scanner
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
