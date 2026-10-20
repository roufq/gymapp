<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\ClassBooking;
use App\Models\ClassSchedule;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Branches (Jogja Utama, Sleman, Solo)
        $branchJogja = Branch::firstOrCreate(
            ['name' => 'Jogja Utama'],
            [
                'address' => 'Jl. Malioboro No. 45, Yogyakarta',
                'max_capacity' => 100,
                'opening_time' => '06:00:00',
                'closing_time' => '22:00:00',
                'timezone' => 'Asia/Jakarta',
            ]
        );

        $branchSleman = Branch::firstOrCreate(
            ['name' => 'Sleman'],
            [
                'address' => 'Jl. Kaliurang KM 9, Sleman',
                'max_capacity' => 80,
                'opening_time' => '06:00:00',
                'closing_time' => '22:00:00',
                'timezone' => 'Asia/Jakarta',
            ]
        );

        $branchSolo = Branch::firstOrCreate(
            ['name' => 'Solo'],
            [
                'address' => 'Jl. Slamet Riyadi No. 120, Solo',
                'max_capacity' => 75,
                'opening_time' => '06:00:00',
                'closing_time' => '22:00:00',
                'timezone' => 'Asia/Jakarta',
            ]
        );

        // 2. Staff & Trainers
        $admin = User::firstOrCreate(
            ['email' => 'admin@gymapp.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567890',
                'email_verified_at' => now(),
            ]
        );

        $frontDesk = User::firstOrCreate(
            ['email' => 'frontdesk@gymapp.test'],
            [
                'name' => 'Siti Rahma',
                'password' => Hash::make('password'),
                'role' => 'front_desk',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567891',
                'email_verified_at' => now(),
            ]
        );

        $coachAde = User::firstOrCreate(
            ['email' => 'trainer@gymapp.test'],
            [
                'name' => 'Coach Ade',
                'password' => Hash::make('password'),
                'role' => 'trainer',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567892',
                'email_verified_at' => now(),
            ]
        );

        $coachRina = User::firstOrCreate(
            ['email' => 'rina.trainer@gymapp.test'],
            [
                'name' => 'Coach Rina',
                'password' => Hash::make('password'),
                'role' => 'trainer',
                'home_branch_id' => $branchSleman->id,
                'phone' => '081234567811',
                'email_verified_at' => now(),
            ]
        );

        $coachDoni = User::firstOrCreate(
            ['email' => 'doni.trainer@gymapp.test'],
            [
                'name' => 'Coach Doni',
                'password' => Hash::make('password'),
                'role' => 'trainer',
                'home_branch_id' => $branchSolo->id,
                'phone' => '081234567812',
                'email_verified_at' => now(),
            ]
        );

        $coachMaya = User::firstOrCreate(
            ['email' => 'maya.trainer@gymapp.test'],
            [
                'name' => 'Coach Maya',
                'password' => Hash::make('password'),
                'role' => 'trainer',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567813',
                'email_verified_at' => now(),
            ]
        );

        $coachSinta = User::firstOrCreate(
            ['email' => 'sinta.trainer@gymapp.test'],
            [
                'name' => 'Coach Sinta',
                'password' => Hash::make('password'),
                'role' => 'trainer',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567814',
                'email_verified_at' => now(),
            ]
        );

        // Coach Rian (PT in gym without schedule for floor audit)
        $coachRian = User::firstOrCreate(
            ['email' => 'rian.trainer@gymapp.test'],
            [
                'name' => 'Coach Rian',
                'password' => Hash::make('password'),
                'role' => 'trainer',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567815',
                'email_verified_at' => now(),
            ]
        );

        // 3. Primary Key Members
        $budi = User::firstOrCreate(
            ['email' => 'member1@gymapp.test'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'role' => 'member',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567893',
                'medical_notes' => 'Riwayat Asma Ringan',
                'unpaid_fine_amount' => 0,
                'email_verified_at' => now(),
            ]
        );

        $andi = User::firstOrCreate(
            ['email' => 'member2@gymapp.test'],
            [
                'name' => 'Andi Wijaya',
                'password' => Hash::make('password'),
                'role' => 'member',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567894',
                'medical_notes' => 'PAR-Q: Aman',
                'unpaid_fine_amount' => 0,
                'email_verified_at' => now(),
            ]
        );

        $rinaMember = User::firstOrCreate(
            ['email' => 'member3@gymapp.test'],
            [
                'name' => 'Rina Wati',
                'password' => Hash::make('password'),
                'role' => 'member',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567895',
                'medical_notes' => 'PAR-Q: Aman',
                'unpaid_fine_amount' => 0,
                'email_verified_at' => now(),
            ]
        );

        $citra = User::firstOrCreate(
            ['email' => 'member4@gymapp.test'],
            [
                'name' => 'Citra Dewi',
                'password' => Hash::make('password'),
                'role' => 'member',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567896',
                'medical_notes' => 'PAR-Q: Aman',
                'unpaid_fine_amount' => 0,
                'email_verified_at' => now(),
            ]
        );

        $bambang = User::firstOrCreate(
            ['email' => 'member5@gymapp.test'],
            [
                'name' => 'Bambang Soedirgo',
                'password' => Hash::make('password'),
                'role' => 'member',
                'home_branch_id' => $branchJogja->id,
                'phone' => '081234567897',
                'medical_notes' => 'PAR-Q: Aman',
                'unpaid_fine_amount' => 0,
                'email_verified_at' => now(),
            ]
        );

        // 4. Membership Plans
        $planPlatinum = MembershipPlan::firstOrCreate(
            ['name' => 'Platinum All-Club'],
            [
                'plan_type' => 'time_based',
                'duration_days' => 30,
                'price' => 450000,
                'allow_all_branches' => true,
                'allowed_time_start' => '00:00:00',
                'allowed_time_end' => '23:59:59',
                'max_freeze_days' => 14,
                'is_active' => true,
            ]
        );

        $planGoldOffPeak = MembershipPlan::firstOrCreate(
            ['name' => 'Gold Off-Peak (Paket Siang)'],
            [
                'plan_type' => 'time_based',
                'duration_days' => 30,
                'price' => 250000,
                'allow_all_branches' => false,
                'allowed_time_start' => '09:00:00',
                'allowed_time_end' => '15:00:00',
                'max_freeze_days' => 7,
                'is_active' => true,
            ]
        );

        $plan1Month = MembershipPlan::firstOrCreate(
            ['name' => 'Membership 1 Bulan'],
            [
                'plan_type' => 'time_based',
                'duration_days' => 30,
                'price' => 350000,
                'allow_all_branches' => true,
                'max_freeze_days' => 7,
                'is_active' => true,
            ]
        );

        // 5. User Subscriptions
        UserSubscription::firstOrCreate(
            ['user_id' => $budi->id, 'plan_id' => $planPlatinum->id],
            [
                'start_date' => now()->subDays(12)->toDateString(),
                'end_date' => now()->addDays(18)->toDateString(),
                'status' => 'active',
                'total_paid_amount' => 450000,
            ]
        );

        UserSubscription::firstOrCreate(
            ['user_id' => $andi->id, 'plan_id' => $planPlatinum->id],
            [
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->addDays(25)->toDateString(),
                'status' => 'active',
                'total_paid_amount' => 450000,
            ]
        );

        UserSubscription::firstOrCreate(
            ['user_id' => $rinaMember->id, 'plan_id' => $planGoldOffPeak->id],
            [
                'start_date' => now()->subDays(10)->toDateString(),
                'end_date' => now()->addDays(20)->toDateString(),
                'status' => 'active',
                'total_paid_amount' => 250000,
            ]
        );

        UserSubscription::firstOrCreate(
            ['user_id' => $citra->id, 'plan_id' => $plan1Month->id],
            [
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDays(28)->toDateString(),
                'status' => 'active',
                'total_paid_amount' => 350000,
            ]
        );

        // Seed 48 members whose subscriptions expire within 7 days (to make the BI churn risk alert dynamic!)
        for ($i = 1; $i <= 48; $i++) {
            $churnUser = User::firstOrCreate(
                ['email' => "churn{$i}@gymapp.test"],
                [
                    'name' => "Member Expiring {$i}",
                    'password' => Hash::make('password'),
                    'role' => 'member',
                    'home_branch_id' => $branchJogja->id,
                    'phone' => '0813'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                    'email_verified_at' => now(),
                ]
            );

            UserSubscription::firstOrCreate(
                ['user_id' => $churnUser->id],
                [
                    'plan_id' => $plan1Month->id,
                    'start_date' => now()->subDays(25)->toDateString(),
                    'end_date' => now()->addDays(rand(1, 7))->toDateString(),
                    'status' => 'active',
                    'total_paid_amount' => 350000,
                ]
            );
        }

        // 6. Personal Trainer Packages
        $pkgAndi = PtPackage::firstOrCreate(
            ['user_id' => $andi->id, 'trainer_id' => $coachAde->id],
            [
                'total_sessions' => 10,
                'remaining_sessions' => 4,
                'price_per_session' => 180000,
                'expires_at' => now()->addMonths(2)->toDateString(),
                'status' => 'active',
            ]
        );

        $pkgBudi = PtPackage::firstOrCreate(
            ['user_id' => $budi->id, 'trainer_id' => $coachAde->id],
            [
                'total_sessions' => 10,
                'remaining_sessions' => 6,
                'price_per_session' => 180000,
                'expires_at' => now()->addMonths(3)->toDateString(),
                'status' => 'active',
            ]
        );

        $pkgCitra = PtPackage::firstOrCreate(
            ['user_id' => $citra->id, 'trainer_id' => $coachAde->id],
            [
                'total_sessions' => 12,
                'remaining_sessions' => 11,
                'price_per_session' => 180000,
                'expires_at' => now()->addMonths(3)->toDateString(),
                'status' => 'active',
            ]
        );

        // 7. Today's Coaching Timeline Sessions for Coach Ade
        // Sesi 1: 09:00 - 10:00 (Completed)
        PtSession::firstOrCreate(
            ['pt_package_id' => $pkgAndi->id, 'start_time' => now()->setTime(9, 0)],
            [
                'user_id' => $andi->id,
                'trainer_id' => $coachAde->id,
                'end_time' => now()->setTime(10, 0),
                'status' => 'completed',
                'otp_code' => '123456',
                'commission_amount' => 150000,
                'verified_at' => now()->setTime(10, 0),
                'workout_notes' => 'Sesi beban dada & trisep selesai dengan baik.',
            ]
        );

        // Sesi 2: 16:00 - 17:00 (Active Session)
        PtSession::firstOrCreate(
            ['pt_package_id' => $pkgBudi->id, 'start_time' => now()->setTime(16, 0)],
            [
                'user_id' => $budi->id,
                'trainer_id' => $coachAde->id,
                'end_time' => now()->setTime(17, 0),
                'status' => 'booked',
                'otp_code' => '849201',
                'commission_amount' => 150000,
            ]
        );

        // Sesi 3: 19:00 - 20:00 (Upcoming)
        PtSession::firstOrCreate(
            ['pt_package_id' => $pkgCitra->id, 'start_time' => now()->setTime(19, 0)],
            [
                'user_id' => $citra->id,
                'trainer_id' => $coachAde->id,
                'end_time' => now()->setTime(20, 0),
                'status' => 'booked',
                'otp_code' => '312894',
                'commission_amount' => 150000,
            ]
        );

        // Seed 23 past completed sessions this month for Coach Ade to reach 24 completed sessions (Rp 3.600.000 PT commission)
        for ($s = 1; $s <= 23; $s++) {
            PtSession::firstOrCreate(
                ['trainer_id' => $coachAde->id, 'user_id' => $andi->id, 'start_time' => now()->subDays($s)->setTime(10, 0)],
                [
                    'pt_package_id' => $pkgAndi->id,
                    'end_time' => now()->subDays($s)->setTime(11, 0),
                    'status' => 'completed',
                    'otp_code' => '888'.str_pad((string) $s, 3, '0', STR_PAD_LEFT),
                    'commission_amount' => 150000,
                    'verified_at' => now()->subDays($s)->setTime(11, 0),
                ]
            );
        }

        // 8. Gate Turnstile Attendance Logs
        // Andi Wijaya (checked in 08:55, checked out 10:05, L-012)
        AttendanceLog::firstOrCreate(
            ['user_id' => $andi->id, 'check_in_at' => now()->setTime(8, 55)],
            [
                'branch_id' => $branchJogja->id,
                'check_out_at' => now()->setTime(10, 5),
                'locker_number' => 'L-012',
                'verified_by_staff_id' => $frontDesk->id,
            ]
        );

        // Budi Santoso (checked in 15:48, still active inside, L-042)
        AttendanceLog::firstOrCreate(
            ['user_id' => $budi->id, 'check_in_at' => now()->setTime(15, 48)],
            [
                'branch_id' => $branchJogja->id,
                'check_out_at' => null,
                'locker_number' => 'L-042',
                'verified_by_staff_id' => $frontDesk->id,
            ]
        );

        // Bambang Soedirgo (checked in 17:58, still active, L-008)
        AttendanceLog::firstOrCreate(
            ['user_id' => $bambang->id, 'check_in_at' => now()->setTime(17, 58)],
            [
                'branch_id' => $branchJogja->id,
                'check_out_at' => null,
                'locker_number' => 'L-008',
                'verified_by_staff_id' => $frontDesk->id,
            ]
        );

        // Coach Ade checked in at gate 08:30 (active in gym)
        AttendanceLog::firstOrCreate(
            ['user_id' => $coachAde->id, 'check_in_at' => now()->setTime(8, 30)],
            [
                'branch_id' => $branchJogja->id,
                'check_out_at' => null,
                'locker_number' => 'ST-01',
                'verified_by_staff_id' => $frontDesk->id,
            ]
        );

        // Coach Rian checked in at 15:10 without scheduled session (unauthorized PT floor alert)
        AttendanceLog::firstOrCreate(
            ['user_id' => $coachRian->id, 'check_in_at' => now()->setTime(15, 10)],
            [
                'branch_id' => $branchJogja->id,
                'check_out_at' => null,
                'locker_number' => 'ST-02',
                'verified_by_staff_id' => $frontDesk->id,
            ]
        );

        // Seed 7 days x hours attendance logs for dynamic heatmap
        for ($d = 0; $d < 7; $d++) {
            $date = now()->subDays($d);
            foreach ([7, 8, 9, 12, 14, 17, 18, 19, 20] as $hour) {
                $count = in_array($hour, [17, 18, 19, 20], true) ? 6 : 2;
                for ($k = 0; $k < $count; $k++) {
                    AttendanceLog::create([
                        'user_id' => $budi->id,
                        'branch_id' => $branchJogja->id,
                        'check_in_at' => (clone $date)->setTime($hour, rand(5, 50)),
                        'check_out_at' => (clone $date)->setTime($hour + 1, rand(0, 30)),
                        'locker_number' => 'L-'.rand(10, 90),
                    ]);
                }
            }
        }

        // 9. Cashier Shifts (including 2 with discrepancies for BI Alert)
        // Shift 1: Sleman Shift Siang (-Rp 85.000)
        CashierShift::firstOrCreate(
            ['branch_id' => $branchSleman->id, 'shift_name' => 'Shift Siang Sleman'],
            [
                'cashier_id' => $frontDesk->id,
                'opened_at' => now()->subDay()->setTime(14, 0),
                'closed_at' => now()->subDay()->setTime(21, 0),
                'opening_cash' => 500000,
                'actual_counted_cash' => 415000,
                'discrepancy_amount' => -85000,
                'notes' => 'Selisih uang kembalian fisik kasir.',
                'status' => 'closed',
            ]
        );

        // Shift 2: Solo Shift Malam (-Rp 40.000)
        CashierShift::firstOrCreate(
            ['branch_id' => $branchSolo->id, 'shift_name' => 'Shift Malam Solo'],
            [
                'cashier_id' => $frontDesk->id,
                'opened_at' => now()->subDay()->setTime(17, 0),
                'closed_at' => now()->subDay()->setTime(22, 0),
                'opening_cash' => 500000,
                'actual_counted_cash' => 460000,
                'discrepancy_amount' => -40000,
                'notes' => 'Deviasi fisik kasir shift malam.',
                'status' => 'closed',
            ]
        );

        // Shift 3: Jogja Utama Current Open Shift
        $openShift = CashierShift::firstOrCreate(
            ['branch_id' => $branchJogja->id, 'shift_name' => 'Shift Pagi: Siti Rahma', 'status' => 'open'],
            [
                'cashier_id' => $frontDesk->id,
                'opened_at' => now()->setTime(6, 0),
                'opening_cash' => 1000000,
            ]
        );

        // 10. Invoices for Financial Cash Inflow (~Rp 184.500.000)
        Invoice::firstOrCreate(
            ['invoice_number' => 'INV-202610-001'],
            [
                'branch_id' => $branchJogja->id,
                'user_id' => $budi->id,
                'cashier_shift_id' => $openShift->id,
                'lead_owner_id' => $coachAde->id,
                'subtotal' => 1800000,
                'grand_total' => 1800000,
                'payment_method' => 'cash',
                'status' => 'paid',
                'paid_at' => now(),
            ]
        );

        // Seed large bulk invoice to realistically reflect Rp 184.500.000 monthly cash inflow
        Invoice::firstOrCreate(
            ['invoice_number' => 'INV-202610-TOTAL-OCT'],
            [
                'branch_id' => $branchJogja->id,
                'user_id' => $andi->id,
                'cashier_shift_id' => $openShift->id,
                'lead_owner_id' => $frontDesk->id,
                'subtotal' => 182700000,
                'grand_total' => 182700000,
                'payment_method' => 'transfer',
                'status' => 'paid',
                'paid_at' => now()->subDays(3),
            ]
        );

        // Seed 1 refunded invoice for clawback deduction (-Rp 350.000 for Coach Rina)
        Invoice::firstOrCreate(
            ['invoice_number' => 'INV-1049-REFUND'],
            [
                'branch_id' => $branchSleman->id,
                'user_id' => $rinaMember->id,
                'cashier_shift_id' => $openShift->id,
                'lead_owner_id' => $coachRina->id,
                'subtotal' => -350000,
                'grand_total' => -350000,
                'payment_method' => 'cash',
                'status' => 'refunded',
                'paid_at' => now()->subDays(1),
            ]
        );

        // 11. Studio Class Schedules
        // Class A: Zumba Party (17:00, Coach Maya, Studio B)
        $classZumba = ClassSchedule::firstOrCreate(
            ['class_name' => 'Zumba Party', 'start_time' => now()->setTime(17, 0)],
            [
                'branch_id' => $branchJogja->id,
                'instructor_id' => $coachMaya->id,
                'room_name' => 'Studio B',
                'end_time' => now()->setTime(18, 0),
                'max_capacity' => 25,
                'current_booked' => 7,
                'status' => 'scheduled',
            ]
        );

        // Class B: RPM Cycling (18:30, Coach Doni, Spin Room)
        $classRpm = ClassSchedule::firstOrCreate(
            ['class_name' => 'RPM Cycling', 'start_time' => now()->setTime(18, 30)],
            [
                'branch_id' => $branchJogja->id,
                'instructor_id' => $coachDoni->id,
                'room_name' => 'Spin Room',
                'end_time' => now()->setTime(19, 30),
                'max_capacity' => 20,
                'current_booked' => 18,
                'status' => 'scheduled',
            ]
        );

        // Class C: Hatha Yoga (19:30, Coach Sinta, Studio A)
        $classYoga = ClassSchedule::firstOrCreate(
            ['class_name' => 'Hatha Yoga', 'start_time' => now()->setTime(19, 30)],
            [
                'branch_id' => $branchJogja->id,
                'instructor_id' => $coachSinta->id,
                'room_name' => 'Studio A',
                'end_time' => now()->setTime(20, 30),
                'max_capacity' => 20,
                'current_booked' => 20,
                'status' => 'scheduled',
            ]
        );

        // Class D: BodyCombat - Studio A (booked by Budi Santoso)
        $classCombat = ClassSchedule::firstOrCreate(
            ['class_name' => 'BodyCombat - Studio A', 'start_time' => now()->setTime(18, 30)],
            [
                'branch_id' => $branchJogja->id,
                'instructor_id' => $coachRina->id,
                'room_name' => 'Studio A',
                'end_time' => now()->setTime(19, 30),
                'max_capacity' => 25,
                'current_booked' => 12,
                'status' => 'scheduled',
            ]
        );

        // Member Budi booked BodyCombat
        ClassBooking::firstOrCreate(
            ['class_schedule_id' => $classCombat->id, 'user_id' => $budi->id],
            [
                'status' => 'booked',
                'booked_at' => now()->subHours(2),
            ]
        );
    }
}
