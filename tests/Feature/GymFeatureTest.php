<?php

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\ClassBooking;
use App\Models\ClassSchedule;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\SubscriptionFreeze;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\WorkoutLog;

beforeEach(function () {
    $this->branch = Branch::create([
        'name' => 'Test Branch',
        'max_capacity' => 50,
        'opening_time' => '06:00:00',
        'closing_time' => '22:00:00',
    ]);

    $this->admin = User::create([
        'name' => 'Admin Staff',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $this->member = User::create([
        'name' => 'Active Member',
        'email' => 'member@test.com',
        'password' => bcrypt('password'),
        'role' => 'member',
        'phone' => '081299990001',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $this->plan = MembershipPlan::create([
        'name' => '1 Month Pass',
        'plan_type' => 'time_based',
        'duration_days' => 30,
        'price' => 300000,
        'is_active' => true,
    ]);

    $this->subscription = UserSubscription::create([
        'user_id' => $this->member->id,
        'plan_id' => $this->plan->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'active',
        'total_paid_amount' => 300000,
    ]);
});

test('staff can access gate monitor and check in member', function () {
    $response = $this->actingAs($this->admin)->post(route('gate.checkin'), [
        'user_id' => $this->member->id,
        'locker_number' => 'L-01',
    ]);

    $response->assertSessionHas('success');
    expect(AttendanceLog::where('user_id', $this->member->id)->whereNull('check_out_at')->exists())->toBeTrue();
});

test('member with unpaid fine is denied check-in', function () {
    $this->member->update(['unpaid_fine_amount' => 50000]);

    $response = $this->actingAs($this->admin)->post(route('gate.checkin'), [
        'user_id' => $this->member->id,
    ]);

    $response->assertSessionHas('error');
    expect(AttendanceLog::where('user_id', $this->member->id)->exists())->toBeFalse();
});

test('staff can register walk-in member with direct payment invoice', function () {
    $response = $this->actingAs($this->admin)->post(route('members.store'), [
        'name' => 'New Walkin Member',
        'email' => 'walkin@test.com',
        'phone' => '081233334444',
        'plan_id' => $this->plan->id,
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('members.index'));

    $newMember = User::where('email', 'walkin@test.com')->first();
    expect($newMember)->not->toBeNull()
        ->and($newMember->activeSubscription)->not->toBeNull()
        ->and($newMember->activeSubscription->status)->toBe('active');
});

test('member cannot access staff only routes', function () {
    // 1. Gate screen
    $this->actingAs($this->member)->get(route('gate.index'))->assertForbidden();

    // 2. Member list
    $this->actingAs($this->member)->get(route('members.index'))->assertForbidden();

    // 3. Register member
    $this->actingAs($this->member)->post(route('members.store'), [
        'name' => 'Hack Attempt',
        'email' => 'hack@test.com',
        'phone' => '081299998888',
        'plan_id' => $this->plan->id,
        'payment_method' => 'cash',
    ])->assertForbidden();

    // 4. Free self-subscription
    $this->actingAs($this->member)->post(route('subscriptions.store'), [
        'user_id' => $this->member->id,
        'plan_id' => $this->plan->id,
        'payment_method' => 'cash',
    ])->assertForbidden();
});

test('member can book studio class and waitlist when capacity full', function () {
    $schedule = ClassSchedule::create([
        'branch_id' => $this->branch->id,
        'instructor_id' => $this->admin->id,
        'class_name' => 'Pilates Core',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
        'max_capacity' => 1,
        'current_booked' => 0,
        'status' => 'scheduled',
    ]);

    // Member 1 books first seat
    $this->actingAs($this->member)->post(route('classes.book', $schedule));
    $schedule->refresh();
    expect($schedule->current_booked)->toBe(1);

    // Member 2 books when full -> enters waitlist
    $member2 = User::create([
        'name' => 'Member Two',
        'email' => 'm2@test.com',
        'password' => bcrypt('password'),
        'role' => 'member',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    UserSubscription::create([
        'user_id' => $member2->id,
        'plan_id' => $this->plan->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($member2)->post(route('classes.book', $schedule));

    $booking2 = ClassBooking::where('class_schedule_id', $schedule->id)->where('user_id', $member2->id)->first();
    expect($booking2->status)->toBe('waitlist')
        ->and($booking2->waitlist_position)->toBe(1);

    // Member 1 cancels booking -> Member 2 is promoted from waitlist to booked
    $booking1 = ClassBooking::where('class_schedule_id', $schedule->id)->where('user_id', $this->member->id)->first();
    $this->actingAs($this->member)->post(route('classes.cancel', $booking1));

    $booking2->refresh();
    expect($booking2->status)->toBe('booked')
        ->and($booking2->waitlist_position)->toBeNull();
});

test('member cannot book or cancel class that already started', function () {
    $pastSchedule = ClassSchedule::create([
        'branch_id' => $this->branch->id,
        'instructor_id' => $this->admin->id,
        'class_name' => 'Past Yoga',
        'start_time' => now()->subHour(),
        'end_time' => now()->subMinutes(10),
        'max_capacity' => 10,
        'current_booked' => 1,
        'status' => 'scheduled',
    ]);

    $this->actingAs($this->member)->post(route('classes.book', $pastSchedule))
        ->assertSessionHas('error');

    $booking = ClassBooking::create([
        'class_schedule_id' => $pastSchedule->id,
        'user_id' => $this->member->id,
        'status' => 'booked',
    ]);

    $this->actingAs($this->member)->post(route('classes.cancel', $booking))
        ->assertSessionHas('error');
});

test('future subscription does not preempt currently active subscription', function () {
    // Current subscription is for 30 days
    $currentSub = $this->member->activeSubscription;
    expect($currentSub->id)->toBe($this->subscription->id);

    // Create a future subscription starting in 30 days
    $futureSub = UserSubscription::create([
        'user_id' => $this->member->id,
        'plan_id' => $this->plan->id,
        'start_date' => now()->addDays(31)->toDateString(),
        'end_date' => now()->addDays(61)->toDateString(),
        'status' => 'active',
        'total_paid_amount' => 300000,
    ]);

    $this->member->refresh();
    $activeSub = $this->member->activeSubscription;

    expect($activeSub->id)->toBe($this->subscription->id)
        ->and($activeSub->id)->not->toBe($futureSub->id);
});

test('trainer can access trainer portal with timeline and earnings', function () {
    $trainer = User::create([
        'name' => 'Coach Ade',
        'email' => 'coachade@test.com',
        'password' => bcrypt('password'),
        'role' => 'trainer',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($trainer)->get(route('trainer.index'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trainer/Index')
            ->has('earningsKpi')
            ->has('sessions')
        );
});

test('regular member cannot access trainer portal', function () {
    $response = $this->actingAs($this->member)->get(route('trainer.index'));
    $response->assertStatus(403);
});

test('trainer can verify pt session with valid otp', function () {
    $trainer = User::create([
        'name' => 'Coach Ade 2',
        'email' => 'coachade2@test.com',
        'password' => bcrypt('password'),
        'role' => 'trainer',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($trainer)->post(route('trainer.sessions.verify', 2), [
        'otp_code' => '849201',
        'is_substitute' => false,
        'workout_notes' => 'Bench Press 4x8 @70kg',
    ]);

    $response->assertSessionHas('success');
});

test('trainer cannot verify pt session with invalid otp', function () {
    $trainer = User::create([
        'name' => 'Coach Ade 3',
        'email' => 'coachade3@test.com',
        'password' => bcrypt('password'),
        'role' => 'trainer',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($trainer)->post(route('trainer.sessions.verify', 2), [
        'otp_code' => '000000',
        'is_substitute' => false,
    ]);

    $response->assertSessionHas('error');
});

test('admin can access executive owner bi bento dashboard with full quadrants', function () {
    $response = $this->actingAs($this->admin)->get(route('executive.index'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Executive/Index')
            ->has('branches')
            ->has('financialAccounting')
            ->has('alerts')
            ->has('heatmap')
            ->has('payroll')
        );
});

test('non-admin cannot access executive owner bi dashboard', function () {
    $response = $this->actingAs($this->member)->get(route('executive.index'));
    $response->assertStatus(403);
});

test('admin can export tax report with 2fa validation', function () {
    $response = $this->actingAs($this->admin)->postJson(route('executive.export-tax'), [
        'two_factor_code' => '123456',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);
});

test('each role is routed to their corresponding portal from dashboard', function () {
    // 1. Admin -> Executive BI
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertRedirect(route('executive.index'));

    // 2. Front Desk -> Gate & POS
    $frontDesk = User::create([
        'name' => 'Sarah FD',
        'email' => 'sarahfd@test.com',
        'password' => bcrypt('password'),
        'role' => 'front_desk',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);
    $this->actingAs($frontDesk)->get(route('dashboard'))
        ->assertRedirect(route('gate.index'));

    // 3. Trainer -> Trainer PWA
    $trainer = User::create([
        'name' => 'Budi PT',
        'email' => 'budipt@test.com',
        'password' => bcrypt('password'),
        'role' => 'trainer',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);
    $this->actingAs($trainer)->get(route('dashboard'))
        ->assertRedirect(route('trainer.index'));

    // 4. Member -> Member Portal PWA
    $this->actingAs($this->member)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('member can scan gym qr code to self check-in and get assigned locker', function () {
    $response = $this->actingAs($this->member)->post(route('member.scan-checkin'), [
        'qr_token' => 'GYM-JOGJA-GATE-01',
    ]);

    $response->assertSessionHas('success');
    $log = AttendanceLog::where('user_id', $this->member->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->locker_number)->not->toBeNull()
        ->and($log->check_in_at)->not->toBeNull();
});

test('member scan check-in is blocked if member has unpaid fine', function () {
    $this->member->update(['unpaid_fine_amount' => 50000]);

    $response = $this->actingAs($this->member)->post(route('member.scan-checkin'), [
        'qr_token' => 'GYM-JOGJA-GATE-01',
    ]);

    $response->assertSessionHas('error');
    expect(AttendanceLog::where('user_id', $this->member->id)->exists())->toBeFalse();
});

test('member scan check-in prevents duplicate check-in within anti-passback window', function () {
    // First check-in succeeds
    $this->actingAs($this->member)->post(route('member.scan-checkin'), [
        'qr_token' => 'GYM-JOGJA-GATE-01',
    ])->assertSessionHas('success');

    // Second check-in immediately is blocked by anti-passback
    $this->actingAs($this->member)->post(route('member.scan-checkin'), [
        'qr_token' => 'GYM-JOGJA-GATE-01',
    ])->assertSessionHas('error');

    expect(AttendanceLog::where('user_id', $this->member->id)->count())->toBe(1);
});

test('staff can record blind cash opname and close cashier shift with discrepancy calculation', function () {
    $shift = CashierShift::create([
        'branch_id' => $this->branch->id,
        'cashier_id' => $this->admin->id,
        'opening_cash' => 500000,
        'status' => 'open',
    ]);

    Invoice::create([
        'invoice_number' => 'INV-TEST-001',
        'branch_id' => $this->branch->id,
        'user_id' => $this->member->id,
        'cashier_shift_id' => $shift->id,
        'grand_total' => 200000,
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    // Expected cash: 500.000 + 200.000 = 700.000. Cashier counts 675.000 (discrepancy: -25.000)
    $response = $this->actingAs($this->admin)->post(route('gate.blind-opname'), [
        'counted_cash' => 675000,
        'notes' => 'Selisih kembalian',
    ]);

    $response->assertSessionHas('success');
    $shift->refresh();
    expect($shift->status)->toBe('closed')
        ->and((float) $shift->actual_counted_cash)->toBe(675000.0)
        ->and((float) $shift->discrepancy_amount)->toBe(-25000.0);
});

test('staff can record free trial guest entry', function () {
    $response = $this->actingAs($this->admin)->post(route('gate.free-trial'), [
        'name' => 'Calon Member Baru',
        'phone' => '081299998888',
        'notes' => 'Minat paket tahunan',
    ]);

    $response->assertSessionHas('success');
});

test('pt session verification updates database status, remaining sessions, and commission', function () {
    $trainer = User::create([
        'name' => 'Coach Ade Test',
        'email' => 'coach.ade.test@test.com',
        'password' => bcrypt('password'),
        'role' => 'trainer',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $package = PtPackage::create([
        'user_id' => $this->member->id,
        'trainer_id' => $trainer->id,
        'total_sessions' => 10,
        'remaining_sessions' => 6,
        'price_per_session' => 180000,
        'expires_at' => now()->addMonths(2)->toDateString(),
        'status' => 'active',
    ]);

    $session = PtSession::create([
        'pt_package_id' => $package->id,
        'user_id' => $this->member->id,
        'trainer_id' => $trainer->id,
        'start_time' => now()->setTime(16, 0),
        'end_time' => now()->setTime(17, 0),
        'status' => 'booked',
        'otp_code' => '849201',
    ]);

    $response = $this->actingAs($trainer)->post(route('trainer.sessions.verify', $session->id), [
        'otp_code' => '849201',
        'is_substitute' => true,
        'workout_notes' => 'Latihan trisep & dada selesai prima',
    ]);

    $response->assertSessionHas('success');

    $session->refresh();
    $package->refresh();

    expect($session->status)->toBe('completed')
        ->and($session->is_substitute)->toBeTrue()
        ->and((float) $session->commission_amount)->toBe(150000.0)
        ->and($session->workout_notes)->toBe('Latihan trisep & dada selesai prima')
        ->and($package->remaining_sessions)->toBe(5);
});

test('member can cancel booking and waitlist seat advances', function () {
    $schedule = ClassSchedule::create([
        'branch_id' => $this->branch->id,
        'instructor_id' => $this->admin->id,
        'class_name' => 'HIIT Class',
        'start_time' => now()->addHours(14),
        'end_time' => now()->addHours(15),
        'max_capacity' => 1,
        'current_booked' => 1,
        'status' => 'scheduled',
    ]);

    $bookingMember = ClassBooking::create([
        'class_schedule_id' => $schedule->id,
        'user_id' => $this->member->id,
        'status' => 'booked',
    ]);

    $waitlistMember = User::create([
        'name' => 'Waitlist User',
        'email' => 'waitlist@test.com',
        'password' => bcrypt('password'),
        'role' => 'member',
        'phone' => '081299997777',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $bookingWaitlist = ClassBooking::create([
        'class_schedule_id' => $schedule->id,
        'user_id' => $waitlistMember->id,
        'status' => 'waitlist',
        'waitlist_position' => 1,
    ]);

    // Member cancels booking
    $response = $this->actingAs($this->member)->post(route('classes.cancel', $bookingMember->id));
    $response->assertSessionHas('success');

    $bookingMember->refresh();
    $bookingWaitlist->refresh();

    expect($bookingMember->status)->toBe('cancelled')
        ->and($bookingWaitlist->status)->toBe('booked')
        ->and($bookingWaitlist->waitlist_position)->toBeNull();
});

test('member can re-book a previously cancelled class without duplicate entry exception', function () {
    $schedule = ClassSchedule::create([
        'branch_id' => $this->branch->id,
        'instructor_id' => $this->admin->id,
        'class_name' => 'Yoga Balance',
        'start_time' => now()->addHours(14),
        'end_time' => now()->addHours(15),
        'max_capacity' => 5,
        'current_booked' => 0,
        'status' => 'scheduled',
    ]);

    // First booking
    $this->actingAs($this->member)->post(route('classes.book', $schedule))->assertSessionHas('success');
    $booking = ClassBooking::where('class_schedule_id', $schedule->id)->where('user_id', $this->member->id)->first();
    expect($booking->status)->toBe('booked');

    // Cancel booking
    $this->actingAs($this->member)->post(route('classes.cancel', $booking))->assertSessionHas('success');
    $booking->refresh();
    expect($booking->status)->toBe('cancelled');

    // Re-book the same class (should NOT throw UniqueConstraintViolationException)
    $this->actingAs($this->member)->post(route('classes.book', $schedule))->assertSessionHas('success');
    $booking->refresh();
    expect($booking->status)->toBe('booked')
        ->and($booking->cancelled_at)->toBeNull();
});

test('trainer can store member inbody and strength metrics', function () {
    $trainer = User::create([
        'name' => 'Coach Ade Test',
        'email' => 'coachade@test.com',
        'password' => bcrypt('password'),
        'role' => 'trainer',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($trainer)->from(route('trainer.index'))->post(route('trainer.members.metrics', $this->member), [
        'bench_press' => 85,
        'squat' => 110,
        'deadlift' => 140,
        'body_weight' => 73.5,
        'body_fat' => 15.2,
        'notes' => 'Peningkatan PR bench press +5kg.',
    ]);

    $response->assertSessionHas('success');
    $this->member->refresh();
    expect($this->member->medical_notes)->toContain('BB 73.5kg')
        ->and($this->member->medical_notes)->toContain('Fat 15.2%')
        ->and($this->member->medical_notes)->toContain('Bench 85kg');
});

test('member can apply for self-service subscription freeze and extend end_date', function () {
    $freezeMember = User::create([
        'name' => 'Freeze Member Test',
        'email' => 'freezemember@test.com',
        'password' => bcrypt('password'),
        'role' => 'member',
        'phone' => '081299990099',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
    ]);

    $plan = MembershipPlan::create([
        'name' => 'Gold 30 Days Test',
        'plan_type' => 'time_based',
        'duration_days' => 30,
        'price' => 500000,
        'max_freeze_days' => 14,
        'is_active' => true,
    ]);

    $sub = UserSubscription::create([
        'user_id' => $freezeMember->id,
        'plan_id' => $plan->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'remaining_freeze_days' => 14,
        'status' => 'active',
        'total_paid_amount' => 500000,
    ]);

    $initialEndDate = clone $sub->end_date;

    $response = $this->actingAs($freezeMember)->from(route('dashboard'))->post(route('subscriptions.freeze'), [
        'freeze_start' => now()->addDays(2)->toDateString(),
        'freeze_end' => now()->addDays(8)->toDateString(), // 7 days
    ]);

    $response->assertSessionHas('success');
    $sub->refresh();

    expect($sub->end_date->toDateString())->toBe($initialEndDate->addDays(7)->toDateString())
        ->and($sub->remaining_freeze_days)->toBe(7);

    expect(SubscriptionFreeze::where('subscription_id', $sub->id)->count())->toBe(1);
});

test('member can toggle ghost mode', function () {
    expect($this->member->is_ghost_mode)->toBeFalse();

    $response = $this->actingAs($this->member)
        ->from(route('dashboard'))
        ->post(route('member.toggle-ghost-mode'));

    $response->assertSessionHas('success');
    expect($this->member->fresh()->is_ghost_mode)->toBeTrue();

    // Toggle off
    $this->actingAs($this->member)
        ->from(route('dashboard'))
        ->post(route('member.toggle-ghost-mode'));

    expect($this->member->fresh()->is_ghost_mode)->toBeFalse();
});

test('member can update daily readiness and stability score', function () {
    $response = $this->actingAs($this->member)
        ->from(route('dashboard'))
        ->post(route('member.readiness'), [
            'score' => 92,
            'notes' => 'Tidur nyenyak 8 jam, siap angkat beban berat.',
        ]);

    $response->assertSessionHas('success');

    $this->member->refresh();
    expect($this->member->readiness_score)->toBe(92)
        ->and($this->member->readiness_notes)->toBe('Tidur nyenyak 8 jam, siap angkat beban berat.');
});

test('member can log a workout set and delete it', function () {
    $response = $this->actingAs($this->member)
        ->from(route('dashboard'))
        ->post(route('member.workout-log.store'), [
            'exercise_name' => 'Barbell Bench Press',
            'muscle_group' => 'Dada',
            'set_number' => 1,
            'weight_kg' => 70,
            'reps' => 10,
            'rpe' => 8,
            'notes' => 'Form solid, pause di dada.',
        ]);

    $response->assertSessionHas('success');

    $log = WorkoutLog::where('user_id', $this->member->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->exercise_name)->toBe('Barbell Bench Press')
        ->and($log->muscle_group)->toBe('Dada')
        ->and((float) $log->weight_kg)->toBe(70.0)
        ->and($log->reps)->toBe(10)
        ->and($log->rpe)->toBe(8);

    // Delete log
    $delResponse = $this->actingAs($this->member)
        ->from(route('dashboard'))
        ->delete(route('member.workout-log.destroy', $log));

    $delResponse->assertSessionHas('success');
    expect(WorkoutLog::find($log->id))->toBeNull();
});

test('member dashboard computes live presence and muscle recovery correctly', function () {
    // 1. Create another member with ghost mode active and checked in
    $ghostMember = User::create([
        'name' => 'Ghost Member',
        'email' => 'ghost@test.com',
        'password' => bcrypt('password'),
        'role' => 'member',
        'home_branch_id' => $this->branch->id,
        'email_verified_at' => now(),
        'is_ghost_mode' => true,
    ]);

    // Check in ghost member
    AttendanceLog::create([
        'user_id' => $ghostMember->id,
        'branch_id' => $this->branch->id,
        'check_in_at' => now()->subMinutes(30),
        'check_out_at' => null,
        'entry_method' => 'qr_pass',
    ]);

    // 2. Log workout for Chest today
    WorkoutLog::create([
        'user_id' => $this->member->id,
        'exercise_name' => 'Incline Dumbbell Press',
        'muscle_group' => 'Dada',
        'set_number' => 1,
        'weight_kg' => 24,
        'reps' => 12,
        'rpe' => 8,
    ]);

    $response = $this->actingAs($this->member)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('livePresence.total_in_gym')
        ->has('livePresence.attendees')
        ->has('muscleRecovery')
        ->where('muscleRecovery.0.name', 'Dada')
        ->where('muscleRecovery.0.status', 'fatigued')
        ->has('readiness.score')
    );
});
