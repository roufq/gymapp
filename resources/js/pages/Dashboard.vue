<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart3, DoorOpen, UserCheck, Users } from '@lucide/vue';
import MemberPortal from '@/components/MemberPortal.vue';
import GymStaffNav from '@/components/GymStaffNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

interface Props {
    role: string;
    stats?: {
        currentOccupancy: number;
        maxCapacity: number;
        occupancyRate: number;
        todayCheckIns: number;
        activeMembersCount: number;
        branchName: string;
    };
    recentLogs?: Array<{
        id: number;
        locker_number: string | null;
        check_in_at: string;
        user: { name: string; phone: string };
        branch: { name: string };
    }>;
    member?: any;
    subscription?: any;
    crowdMeter?: any;
    upcomingSchedule?: any;
    ptSummary?: any;
    metrics?: any;
    timelineClasses?: any;
    myBookings?: any;
}

const props = defineProps<Props>();

function checkout(logId: number) {
    router.post(`/gate/check-out/${logId}`);
}
</script>

<template>
    <Head :title="props.role === 'member' ? 'Member Portal - EGMS' : 'Staff Gym Dashboard'" />

    <!-- 1. ATHLETIC DARK-MODE MEMBER PORTAL -->
    <template v-if="props.role === 'member' || !props.stats">
        <MemberPortal
            :member="props.member || {
                name: 'Budi Santoso',
                email: 'budi@example.com',
                phone: '081234567890',
                no_show_strike_count: 0,
                unpaid_fine_amount: 0,
                home_branch_name: 'Cabang Utama'
            }"
            :subscription="props.subscription || {
                plan_name: 'PLATINUM ALL-CLUB',
                plan_badge: 'PLATINUM ALL-CLUB • ACTIVE',
                days_remaining: 18,
                end_date: '02 Nov 2026',
                remaining_quota: null,
                status: 'ACTIVE'
            }"
            :crowd-meter="props.crowdMeter || {
                branch_name: 'Cabang Utama',
                current: 38,
                max: 100,
                rate: 42,
                label: 'Jam Ideal Latihan'
            }"
            :upcoming-schedule="props.upcomingSchedule || {
                class_name: 'BodyCombat - Studio A',
                time_text: 'Hari ini, 18:30 WIB • Coach Rina',
                can_cancel: true
            }"
            :pt-summary="props.ptSummary || {
                total_sessions: 10,
                remaining_sessions: 6,
                trainer_name: 'Coach Ade',
                pin_otp: '849 - 201'
            }"
            :metrics="props.metrics || {
                weight: 72.4,
                weight_diff: -1.8,
                muscle_mass: 34.2,
                muscle_diff: 0.9,
                body_fat: 16.5,
                fat_diff: -2.1,
                chart_data: [
                    { month: 'Mei', weight: 74.8, muscle: 32.5 },
                    { month: 'Jun', weight: 74.2, muscle: 32.8 },
                    { month: 'Jul', weight: 73.6, muscle: 33.1 },
                    { month: 'Agu', weight: 73.1, muscle: 33.6 },
                    { month: 'Sep', weight: 72.8, muscle: 33.9 },
                    { month: 'Okt', weight: 72.4, muscle: 34.2 }
                ]
            }"
            :timeline-classes="props.timelineClasses || []"
        />
    </template>

    <!-- 2. STAFF / ADMIN DASHBOARD (UNIFIED GYM WORKSTATION) -->
    <div v-else class="min-h-screen bg-[#F8FAFC] text-[#0F172A] font-sans flex flex-col">
        <GymStaffNav active-tab="dashboard" :branch-name="props.stats?.branchName || 'Cabang Utama'" />

        <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
            <!-- Metrics Row -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Kepadatan Gym Saat Ini</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats?.currentOccupancy }} / {{ props.stats?.maxCapacity }}</div>
                        <div class="mt-2 flex items-center gap-2">
                            <Badge :variant="props.stats && props.stats.occupancyRate > 80 ? 'destructive' : 'secondary'">
                                {{ props.stats?.occupancyRate }}% Terisi
                            </Badge>
                            <span class="text-xs text-muted-foreground">{{ props.stats?.branchName }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Kunjungan Hari Ini</CardTitle>
                        <DoorOpen class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats?.todayCheckIns }}</div>
                        <p class="mt-1 text-xs text-muted-foreground">Total member check-in hari ini</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Member Aktif</CardTitle>
                        <UserCheck class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats?.activeMembersCount }}</div>
                        <p class="mt-1 text-xs text-muted-foreground">Memiliki paket aktif</p>
                    </CardContent>
                </Card>

                <Card class="flex flex-col justify-between">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Aksi Cepat Meja Kasir</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <Link v-if="props.role === 'admin'" href="/executive">
                            <Button class="w-full justify-start bg-indigo-600 hover:bg-indigo-500 text-white" size="sm">
                                <BarChart3 class="mr-2 h-4 w-4" /> Buka Executive BI (Owner)
                            </Button>
                        </Link>
                        <Link href="/gate">
                            <Button class="w-full justify-start" size="sm" :variant="props.role === 'admin' ? 'outline' : 'default'">
                                <DoorOpen class="mr-2 h-4 w-4" /> Buka Gate Scanner &amp; POS
                            </Button>
                        </Link>
                        <Link href="/members">
                            <Button variant="outline" class="w-full justify-start" size="sm">
                                <Users class="mr-2 h-4 w-4" /> Daftar / Registrasi Member
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Check-ins List -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Aktivitas Gate Masuk Terkini</CardTitle>
                        <CardDescription>Daftar member yang baru saja melakukan check-in</CardDescription>
                    </div>
                    <Link href="/gate">
                        <Button variant="ghost" size="sm">Lihat Semua di Gate &rarr;</Button>
                    </Link>
                </CardHeader>
                <CardContent>
                    <div v-if="props.recentLogs && props.recentLogs.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="py-3">Member</th>
                                    <th class="py-3">No. HP</th>
                                    <th class="py-3">No. Loker</th>
                                    <th class="py-3">Waktu Masuk</th>
                                    <th class="py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="log in props.recentLogs" :key="log.id" class="hover:bg-muted/50">
                                    <td class="py-3 font-medium">{{ log.user.name }}</td>
                                    <td class="py-3 text-muted-foreground">{{ log.user.phone }}</td>
                                    <td class="py-3">
                                        <Badge variant="outline">{{ log.locker_number || 'Tanpa Loker' }}</Badge>
                                    </td>
                                    <td class="py-3 text-muted-foreground">{{ new Date(log.check_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</td>
                                    <td class="py-3 text-right">
                                        <Button size="sm" variant="outline" @click="checkout(log.id)">
                                            Check-out
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="py-8 text-center text-sm text-muted-foreground">
                        Belum ada aktivitas check-in hari ini.
                    </div>
                </CardContent>
            </Card>
        </main>
    </div>
</template>
