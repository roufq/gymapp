<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Branches
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('address')->nullable();
            $table->unsignedInteger('max_capacity')->default(100);
            $table->time('opening_time')->default('06:00:00');
            $table->time('closing_time')->default('22:00:00');
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->timestamps();
        });

        // 2. Add gym fields to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('member')->index(); // admin, front_desk, trainer, member
            $table->foreignId('home_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('phone', 25)->nullable()->unique();
            $table->string('photo_url')->nullable();
            $table->string('totp_secret', 100)->nullable();
            $table->string('active_device_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('medical_notes')->nullable();
            $table->unsignedInteger('no_show_strike_count')->default(0);
            $table->timestamp('booking_suspended_until')->nullable();
            $table->decimal('unpaid_fine_amount', 12, 2)->default(0);
        });

        // 3. Membership Plans
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('plan_type', 30)->default('time_based'); // time_based, quota_based
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedInteger('quota_visits')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('allow_all_branches')->default(false);
            $table->time('allowed_time_start')->default('00:00:00');
            $table->time('allowed_time_end')->default('23:59:59');
            $table->unsignedInteger('max_freeze_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Subscriptions
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('membership_plans')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('remaining_quota')->nullable();
            $table->unsignedInteger('remaining_freeze_days')->default(0);
            $table->string('status', 30)->default('active')->index(); // active, frozen, expired, cancelled
            $table->decimal('total_paid_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        // 5. Subscription Freezes
        Schema::create('subscription_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('freeze_start');
            $table->date('freeze_end');
            $table->unsignedInteger('total_days');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, completed
            $table->timestamps();
        });

        // 6. Attendance Logs
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
            $table->timestamp('check_in_at');
            $table->timestamp('check_out_at')->nullable();
            $table->string('checkout_type', 30)->nullable(); // manual, auto_timeout
            $table->string('locker_number', 20)->nullable();
            $table->foreignId('verified_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'check_in_at']);
            $table->index(['branch_id', 'check_in_at']);
        });

        // 7. Class Schedules
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->string('class_name', 100);
            $table->string('room_name', 50)->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->unsignedInteger('max_capacity');
            $table->unsignedInteger('current_booked')->default(0);
            $table->string('status', 30)->default('scheduled'); // scheduled, completed, cancelled
            $table->timestamps();
        });

        // 8. Class Bookings
        Schema::create('class_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_schedule_id')->constrained('class_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('booked'); // booked, waitlist, attended, cancelled, no_show
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamp('booked_at')->useCurrent();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['class_schedule_id', 'user_id']);
        });

        // 9. Personal Trainer Packages
        Schema::create('pt_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('remaining_sessions');
            $table->decimal('price_per_session', 12, 2)->default(0);
            $table->date('expires_at');
            $table->string('status', 20)->default('active'); // active, exhausted, expired
            $table->timestamps();
        });

        // 10. Personal Trainer Sessions
        Schema::create('pt_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_package_id')->constrained('pt_packages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status', 30)->default('booked'); // booked, completed, cancelled
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // 11. Cashier Shifts
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('actual_counted_cash', 12, 2)->nullable();
            $table->decimal('discrepancy_amount', 12, 2)->nullable();
            $table->string('status', 20)->default('open'); // open, closed
            $table->timestamps();
        });

        // 12. Invoices (Manual / Cash POS flow - no third-party gateway needed)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('payment_method', 30)->default('cash'); // cash, transfer, edc
            $table->string('status', 25)->default('paid'); // paid, pending, void
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 13. Invoice Items (Retail drinks/items, Membership, PT)
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('item_type', 30); // membership, pt, retail
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('name', 150);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('pt_sessions');
        Schema::dropIfExists('pt_packages');
        Schema::dropIfExists('class_bookings');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('subscription_freezes');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('membership_plans');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['home_branch_id']);
            $table->dropColumn([
                'role',
                'home_branch_id',
                'phone',
                'photo_url',
                'totp_secret',
                'active_device_id',
                'birth_date',
                'medical_notes',
                'no_show_strike_count',
                'booking_suspended_until',
                'unpaid_fine_amount',
            ]);
        });

        Schema::dropIfExists('branches');
    }
};
