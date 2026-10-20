<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExecutiveController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TrainerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Executive Owner Business Intelligence Portal
    Route::get('executive', [ExecutiveController::class, 'index'])->name('executive.index');
    Route::match(['get', 'post'], 'executive/export-tax', [ExecutiveController::class, 'exportTaxReport'])->name('executive.export-tax');

    // Classes: Viewing & Booking (Member & Staff)
    Route::get('classes', [ClassScheduleController::class, 'index'])->name('classes.index');
    Route::post('classes/{schedule}/book', [ClassScheduleController::class, 'book'])->name('classes.book');
    Route::post('classes/bookings/{booking}/cancel', [ClassScheduleController::class, 'cancel'])->name('classes.cancel');

    // Member QR Scan Check-in (Member scans Gym Entrance QR Code)
    Route::post('member/scan-checkin', [AttendanceController::class, 'memberScanCheckIn'])->name('member.scan-checkin');

    // Self-Service Subscription Freeze (Cuti Mandiri Member)
    Route::post('subscriptions/freeze', [SubscriptionController::class, 'freeze'])->name('subscriptions.freeze');

    // Member Community Radar, Readiness & Workout Log
    Route::post('member/toggle-ghost-mode', [DashboardController::class, 'toggleGhostMode'])->name('member.toggle-ghost-mode');
    Route::post('member/readiness', [DashboardController::class, 'updateReadiness'])->name('member.readiness');
    Route::post('member/workout-log', [DashboardController::class, 'storeWorkoutLog'])->name('member.workout-log.store');
    Route::delete('member/workout-log/{workoutLog}', [DashboardController::class, 'destroyWorkoutLog'])->name('member.workout-log.destroy');

    // Personal Trainer Portal
    Route::get('trainer', [TrainerController::class, 'index'])->name('trainer.index');
    Route::post('trainer/sessions/{session}/verify', [TrainerController::class, 'verifySession'])->name('trainer.sessions.verify');
    Route::post('trainer/members/{user}/metrics', [TrainerController::class, 'storeMemberMetrics'])->name('trainer.members.metrics');

    // STAFF & ADMIN PROTECTED ROUTES
    Route::middleware('staff')->group(function () {
        // Gate & Attendance
        Route::get('gate', [AttendanceController::class, 'index'])->name('gate.index');
        Route::post('gate/check-in', [AttendanceController::class, 'checkIn'])->name('gate.checkin');
        Route::post('gate/check-out/{log}', [AttendanceController::class, 'checkOut'])->name('gate.checkout');
        Route::post('gate/pos-checkout', [AttendanceController::class, 'posCheckout'])->name('gate.pos-checkout');
        Route::post('gate/blind-opname', [AttendanceController::class, 'blindOpname'])->name('gate.blind-opname');
        Route::post('gate/free-trial', [AttendanceController::class, 'freeTrial'])->name('gate.free-trial');

        // Members Directory & Walk-in Registration
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');

        // Subscriptions Activation
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');

        // Class Schedule Management
        Route::post('classes', [ClassScheduleController::class, 'store'])->name('classes.store');
    });
});

require __DIR__.'/settings.php';
