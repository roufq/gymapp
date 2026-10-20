<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Calendar, CheckCircle2, Clock, MapPin, Plus, User, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import GymStaffNav from '@/components/GymStaffNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    classes: Array<{
        id: number;
        class_name: string;
        room_name: string;
        instructor_name: string;
        branch_name: string;
        start_time: string;
        end_time: string;
        max_capacity: number;
        current_booked: number;
        available_seats: number;
        is_full: boolean;
        my_booking: {
            id: number;
            status: string;
            waitlist_position: number | null;
        } | null;
    }>;
    trainers: Array<{ id: number; name: string }>;
    branches: Array<{ id: number; name: string }>;
}

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => page.props.auth?.user as Record<string, any> | undefined);
const isStaff = computed(() => user.value?.role === 'admin' || user.value?.role === 'front_desk');

const isCreateOpen = ref(false);

const createForm = useForm({
    class_name: '',
    room_name: 'Studio A',
    instructor_id: props.trainers[0]?.id || '',
    start_time: '',
    end_time: '',
    max_capacity: 15,
});

function submitCreate() {
    createForm.post('/classes', {
        onSuccess: () => {
            isCreateOpen.value = false;
            createForm.reset();
        },
    });
}

function bookClass(scheduleId: number) {
    router.post(`/classes/${scheduleId}/book`);
}

function cancelBooking(bookingId: number) {
    if (confirm('Apakah Anda yakin ingin membatalkan pemesanan kelas ini?')) {
        router.post(`/classes/bookings/${bookingId}/cancel`);
    }
}
</script>

<template>
    <Head title="Jadwal Kelas Studio - EGMS" />

    <div class="min-h-screen bg-[#F8FAFC] text-[#0F172A] font-sans flex flex-col">
        <GymStaffNav active-tab="classes" />

        <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Jadwal Kelas Studio</h1>
                <p class="text-sm text-muted-foreground">Pilih dan pesan kelas olahraga favorit Anda atau pantau ketersediaan kursi</p>
            </div>
            <Button v-if="isStaff" @click="isCreateOpen = true">
                <Plus class="mr-2 h-4 w-4" /> Tambah Jadwal Kelas
            </Button>
        </div>

        <div v-if="props.classes.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="c in props.classes"
                :key="c.id"
                class="flex flex-col justify-between transition-all hover:shadow-md"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <CardTitle class="text-lg font-bold">{{ c.class_name }}</CardTitle>
                            <CardDescription class="mt-1 flex items-center gap-1.5 text-xs">
                                <User class="h-3.5 w-3.5" /> Instruktur: {{ c.instructor_name }}
                            </CardDescription>
                        </div>
                        <Badge :variant="c.is_full ? 'destructive' : 'secondary'">
                            {{ c.is_full ? 'Penuh' : `${c.available_seats} Kursi Sisa` }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-3 pb-3 text-xs text-muted-foreground">
                    <div class="flex items-center gap-2">
                        <Clock class="h-4 w-4 text-primary" />
                        <span class="font-medium text-foreground">{{ c.start_time }} - {{ c.end_time }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <MapPin class="h-4 w-4" />
                        <span>{{ c.room_name || 'Studio Utama' }} • {{ c.branch_name }}</span>
                    </div>

                    <!-- Occupancy Bar -->
                    <div class="space-y-1 pt-1">
                        <div class="flex justify-between text-[11px]">
                            <span>Terisi</span>
                            <span class="font-bold">{{ c.current_booked }} / {{ c.max_capacity }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full bg-primary transition-all"
                                :style="{ width: `${Math.min(100, (c.current_booked / c.max_capacity) * 100)}%` }"
                            />
                        </div>
                    </div>
                </CardContent>

                <div class="border-t p-4 pt-3 flex items-center justify-between">
                    <!-- My booking status -->
                    <div v-if="c.my_booking">
                        <Badge v-if="c.my_booking.status === 'booked'" variant="outline" class="border-emerald-500 text-emerald-600 bg-emerald-50 dark:bg-emerald-950">
                            <CheckCircle2 class="mr-1 h-3.5 w-3.5" /> Terdaftar
                        </Badge>
                        <Badge v-else-if="c.my_booking.status === 'waitlist'" variant="secondary">
                            Waitlist #{{ c.my_booking.waitlist_position }}
                        </Badge>
                    </div>
                    <div v-else class="text-xs text-muted-foreground">
                        Belum memesan
                    </div>

                    <!-- Actions -->
                    <div>
                        <Button
                            v-if="c.my_booking"
                            size="sm"
                            variant="outline"
                            class="text-destructive hover:bg-destructive/10"
                            @click="cancelBooking(c.my_booking.id)"
                        >
                            Batalkan
                        </Button>
                        <Button
                            v-else
                            size="sm"
                            :variant="c.is_full ? 'secondary' : 'default'"
                            @click="bookClass(c.id)"
                        >
                            {{ c.is_full ? 'Gabung Waitlist' : 'Pesan Kursi' }}
                        </Button>
                    </div>
                </div>
            </Card>
        </div>

        <div v-else class="rounded-xl border border-dashed py-16 text-center">
            <Calendar class="mx-auto h-10 w-10 text-muted-foreground" />
            <h3 class="mt-3 text-base font-semibold">Belum Ada Kelas Studio</h3>
            <p class="mt-1 text-sm text-muted-foreground">Jadwal kelas akan segera dipublikasikan.</p>
        </div>

        <!-- Create Class Dialog -->
        <Dialog :open="isCreateOpen" @update:open="isCreateOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tambah Jadwal Kelas Studio</DialogTitle>
                    <DialogDescription>Jadwalkan sesi kelas olahraga baru untuk para member.</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                    <div class="space-y-1">
                        <Label for="class_name">Nama Kelas</Label>
                        <Input id="class_name" v-model="createForm.class_name" required placeholder="Contoh: Body Pump, Yoga Flow" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="room_name">Ruangan / Studio</Label>
                            <Input id="room_name" v-model="createForm.room_name" placeholder="Studio A" />
                        </div>
                        <div class="space-y-1">
                            <Label for="instructor">Instruktur / Trainer</Label>
                            <select
                                id="instructor"
                                v-model="createForm.instructor_id"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option v-for="t in props.trainers" :key="t.id" :value="t.id">
                                    {{ t.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="start_time">Waktu Mulai</Label>
                            <Input id="start_time" type="datetime-local" v-model="createForm.start_time" required />
                        </div>
                        <div class="space-y-1">
                            <Label for="end_time">Waktu Selesai</Label>
                            <Input id="end_time" type="datetime-local" v-model="createForm.end_time" required />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="max_capacity">Kapasitas Maksimal Peserta</Label>
                        <Input id="max_capacity" type="number" min="1" max="100" v-model="createForm.max_capacity" required />
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isCreateOpen = false">Batal</Button>
                        <Button type="submit" :disabled="createForm.processing">Publikasikan Jadwal</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        </main>
    </div>
</template>

