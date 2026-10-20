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
        Schema::table('pt_sessions', function (Blueprint $table) {
            $table->string('otp_code', 10)->nullable()->after('status');
            $table->boolean('is_substitute')->default(false)->after('otp_code');
            $table->text('workout_notes')->nullable()->after('is_substitute');
            $table->decimal('commission_amount', 12, 2)->default(150000)->after('workout_notes');
        });

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->string('shift_name', 50)->nullable()->after('cashier_id');
            $table->text('notes')->nullable()->after('discrepancy_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('lead_owner_id')->nullable()->after('cashier_shift_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['lead_owner_id']);
            $table->dropColumn('lead_owner_id');
        });

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropColumn(['shift_name', 'notes']);
        });

        Schema::table('pt_sessions', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'is_substitute', 'workout_notes', 'commission_amount']);
        });
    }
};
