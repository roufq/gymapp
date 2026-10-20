<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, RefreshCw, Search, UserPlus, Users } from '@lucide/vue';
import { ref } from 'vue';
import GymStaffNav from '@/components/GymStaffNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    members: {
        data: Array<{
            id: number;
            name: string;
            phone: string;
            email: string;
            home_branch: { name: string } | null;
            active_subscription: {
                id: number;
                start_date: string;
                end_date: string;
                status: string;
                remaining_quota: number | null;
                plan: { id: number; name: string; price: string };
            } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    plans: Array<{
        id: number;
        name: string;
        price: string;
        duration_days: number;
    }>;
    branches: Array<{
        id: number;
        name: string;
    }>;
    filters: {
        search?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const isRegisterOpen = ref(false);
const isRenewOpen = ref(false);
const selectedMemberForRenew = ref<any>(null);

const registerForm = useForm({
    name: '',
    email: '',
    phone: '',
    plan_id: props.plans[0]?.id || '',
    payment_method: 'cash',
    medical_notes: '',
});

const renewForm = useForm({
    user_id: '',
    plan_id: props.plans[0]?.id || '',
    payment_method: 'cash',
});

function handleSearch() {
    router.get('/members', { search: searchQuery.value }, { preserveState: true });
}

function submitRegister() {
    registerForm.post('/members', {
        onSuccess: () => {
            isRegisterOpen.value = false;
            registerForm.reset();
        },
    });
}

function openRenew(member: any) {
    selectedMemberForRenew.value = member;
    renewForm.user_id = String(member.id);
    isRenewOpen.value = true;
}

function submitRenew() {
    renewForm.post('/subscriptions', {
        onSuccess: () => {
            isRenewOpen.value = false;
            renewForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Direktori Member - EGMS" />

    <div class="min-h-screen bg-[#F8FAFC] text-[#0F172A] font-sans flex flex-col">
        <GymStaffNav active-tab="members" />

        <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
        <!-- Header & Action Bar -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Direktori Member</h1>
                <p class="text-sm text-muted-foreground">Kelola basis data member gym, aktivasi langganan, dan riwayat paket</p>
            </div>
            <Button @click="isRegisterOpen = true">
                <UserPlus class="mr-2 h-4 w-4" /> Registrasi Walk-in Member
            </Button>
        </div>

        <!-- Search Bar -->
        <Card>
            <CardContent class="p-4">
                <form @submit.prevent="handleSearch" class="flex gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Cari member berdasarkan nama, nomor HP, atau email..."
                            class="pl-9"
                        />
                    </div>
                    <Button type="submit" variant="secondary">Cari</Button>
                </form>
            </CardContent>
        </Card>

        <!-- Members Table -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base font-semibold">Daftar Member Terdaftar</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="py-3">Member</th>
                                <th class="py-3">Kontak</th>
                                <th class="py-3">Cabang Asal</th>
                                <th class="py-3">Paket Aktif</th>
                                <th class="py-3">Berlaku Sampai</th>
                                <th class="py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="m in props.members.data" :key="m.id" class="hover:bg-muted/40">
                                <td class="py-3 font-medium">
                                    <Link :href="`/members/${m.id}`" class="hover:underline text-primary">
                                        {{ m.name }}
                                    </Link>
                                </td>
                                <td class="py-3 text-xs text-muted-foreground">
                                    <div>{{ m.phone }}</div>
                                    <div>{{ m.email }}</div>
                                </td>
                                <td class="py-3 text-xs">{{ m.home_branch?.name || '-' }}</td>
                                <td class="py-3">
                                    <Badge v-if="m.active_subscription" variant="secondary">
                                        {{ m.active_subscription.plan.name }}
                                    </Badge>
                                    <Badge v-else variant="outline" class="text-muted-foreground">Tidak Ada</Badge>
                                </td>
                                <td class="py-3 text-xs">
                                    <span v-if="m.active_subscription" class="font-medium text-emerald-600">
                                        {{ new Date(m.active_subscription.end_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="py-3 text-right space-x-2">
                                    <Button size="sm" variant="outline" @click="openRenew(m)">
                                        <RefreshCw class="mr-1 h-3.5 w-3.5" /> Perpanjang
                                    </Button>
                                    <Link :href="`/members/${m.id}`">
                                        <Button size="sm" variant="ghost">Detail</Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="props.members.data.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                    Tidak ada member yang ditemukan.
                </div>
            </CardContent>
        </Card>

        <!-- Register Member Dialog -->
        <Dialog :open="isRegisterOpen" @update:open="isRegisterOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Registrasi Walk-in Member</DialogTitle>
                    <DialogDescription>Input data member baru dan langsung aktifkan paket membership kasir.</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitRegister" class="space-y-4 py-2">
                    <div class="space-y-1">
                        <Label for="name">Nama Lengkap</Label>
                        <Input id="name" v-model="registerForm.name" required placeholder="Contoh: Andi Pratama" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="phone">No. WhatsApp/HP</Label>
                            <Input id="phone" v-model="registerForm.phone" required placeholder="0812xxxxxxx" />
                        </div>
                        <div class="space-y-1">
                            <Label for="email">Email</Label>
                            <Input id="email" type="email" v-model="registerForm.email" required placeholder="andi@mail.com" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="plan">Pilih Paket Membership</Label>
                            <select
                                id="plan"
                                v-model="registerForm.plan_id"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option v-for="p in props.plans" :key="p.id" :value="p.id">
                                    {{ p.name }} (Rp {{ Number(p.price).toLocaleString('id-ID') }})
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <Label for="payment">Metode Pembayaran</Label>
                            <select
                                id="payment"
                                v-model="registerForm.payment_method"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="cash">Tunai / Cash</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="edc">Kartu Debit / EDC</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="notes">Catatan Medis (Opsional)</Label>
                        <Input id="notes" v-model="registerForm.medical_notes" placeholder="Riwayat cedera, asma, dsb." />
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isRegisterOpen = false">Batal</Button>
                        <Button type="submit" :disabled="registerForm.processing">Simpan & Aktifkan Paket</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Renew Subscription Dialog -->
        <Dialog :open="isRenewOpen" @update:open="isRenewOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Perpanjang Paket Membership</DialogTitle>
                    <DialogDescription>
                        Perpanjangan untuk member <strong>{{ selectedMemberForRenew?.name }}</strong>
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitRenew" class="space-y-4 py-2">
                    <div class="space-y-1">
                        <Label for="renew-plan">Pilih Paket Baru</Label>
                        <select
                            id="renew-plan"
                            v-model="renewForm.plan_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="p in props.plans" :key="p.id" :value="p.id">
                                {{ p.name }} (Rp {{ Number(p.price).toLocaleString('id-ID') }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <Label for="renew-payment">Metode Pembayaran</Label>
                        <select
                            id="renew-payment"
                            v-model="renewForm.payment_method"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="cash">Tunai / Cash</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="edc">Kartu Debit / EDC</option>
                        </select>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isRenewOpen = false">Batal</Button>
                        <Button type="submit" :disabled="renewForm.processing">Proses Pembayaran</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        </main>
    </div>
</template>

