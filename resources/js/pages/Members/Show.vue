<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, CheckCircle2, Clock, DoorOpen, Dumbbell, User } from '@lucide/vue';
import GymStaffNav from '@/components/GymStaffNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    member: {
        id: number;
        name: string;
        email: string;
        phone: string;
        birth_date: string | null;
        medical_notes: string | null;
        unpaid_fine_amount: string | number;
        home_branch: { name: string } | null;
        subscriptions: Array<{
            id: number;
            start_date: string;
            end_date: string;
            status: string;
            total_paid_amount: string;
            plan: { name: string; plan_type: string };
        }>;
        attendance_logs: Array<{
            id: number;
            check_in_at: string;
            check_out_at: string | null;
            locker_number: string | null;
        }>;
    };
}

const props = defineProps<Props>();
</script>

<template>
    <Head :title="`Profil Member - ${props.member.name}`" />

    <div class="min-h-screen bg-[#F8FAFC] text-[#0F172A] font-sans flex flex-col">
        <GymStaffNav active-tab="members" />

        <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
            <div class="flex items-center gap-3">
            <Link href="/members">
                <Button variant="ghost" size="icon">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </Link>
            <div>
                <h1 class="text-2xl font-bold">{{ props.member.name }}</h1>
                <p class="text-xs text-muted-foreground">{{ props.member.phone }} • {{ props.member.email }} • Cabang: {{ props.member.home_branch?.name || '-' }}</p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Subscriptions History -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-semibold">Riwayat Paket Membership</CardTitle>
                    <CardDescription>Seluruh paket yang pernah dibeli oleh member</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.member.subscriptions.length > 0" class="space-y-3">
                        <div
                            v-for="sub in props.member.subscriptions"
                            :key="sub.id"
                            class="flex items-center justify-between rounded-lg border p-3.5"
                        >
                            <div>
                                <div class="font-medium text-sm">{{ sub.plan.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ new Date(sub.start_date).toLocaleDateString('id-ID') }} s/d {{ new Date(sub.end_date).toLocaleDateString('id-ID') }}
                                </div>
                                <div class="text-xs font-semibold text-muted-foreground mt-1">
                                    Rp {{ Number(sub.total_paid_amount).toLocaleString('id-ID') }}
                                </div>
                            </div>
                            <Badge :variant="sub.status === 'active' ? 'default' : 'secondary'">
                                {{ sub.status.toUpperCase() }}
                            </Badge>
                        </div>
                    </div>
                    <div v-else class="py-6 text-center text-sm text-muted-foreground">
                        Belum ada data paket.
                    </div>
                </CardContent>
            </Card>

            <!-- Attendance History -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-semibold">Riwayat Kunjungan & Loker</CardTitle>
                    <CardDescription>15 kunjungan check-in terakhir</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.member.attendance_logs.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="py-2.5">Tanggal</th>
                                    <th class="py-2.5">Jam Masuk</th>
                                    <th class="py-2.5">Jam Keluar</th>
                                    <th class="py-2.5">Loker</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="log in props.member.attendance_logs" :key="log.id">
                                    <td class="py-2.5 text-xs font-medium">
                                        {{ new Date(log.check_in_at).toLocaleDateString('id-ID') }}
                                    </td>
                                    <td class="py-2.5 text-xs text-muted-foreground">
                                        {{ new Date(log.check_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </td>
                                    <td class="py-2.5 text-xs text-muted-foreground">
                                        {{ log.check_out_at ? new Date(log.check_out_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Masih di Gym' }}
                                    </td>
                                    <td class="py-2.5 text-xs">
                                        <Badge variant="outline">{{ log.locker_number || '-' }}</Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="py-6 text-center text-sm text-muted-foreground">
                        Belum ada riwayat kunjungan.
                    </div>
                </CardContent>
            </Card>
        </div>
        </main>
    </div>
</template>

