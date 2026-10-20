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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_ghost_mode')->default(false)->after('photo_url');
            $table->unsignedSmallInteger('readiness_score')->default(85)->after('is_ghost_mode');
            $table->text('readiness_notes')->nullable()->after('readiness_score');
        });

        Schema::create('workout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('exercise_name', 100);
            $table->string('muscle_group', 50); // chest, back, legs, shoulders, arms, core
            $table->unsignedSmallInteger('set_number')->default(1);
            $table->decimal('weight_kg', 5, 2)->default(0);
            $table->unsignedSmallInteger('reps')->default(10);
            $table->unsignedSmallInteger('rpe')->nullable(); // 1-10
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'muscle_group']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_ghost_mode', 'readiness_score', 'readiness_notes']);
        });
    }
};
