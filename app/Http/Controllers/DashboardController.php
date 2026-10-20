<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\ClassBooking;
use App\Models\ClassSchedule;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\UserSubscription;
use App\Models\WorkoutLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // 1. Executive Owner / Super Admin -> Executive BI Bento Grid Dashboard
        if ($user->isAdmin() && ! $request->has('staff_view')) {
            return redirect()->route('executive.index');
        }

        // 2. Front Desk Cashier -> Gate Scanner & POS Workspace
        if ($user->role === 'front_desk' && ! $request->has('stats_view')) {
            return redirect()->route('gate.index');
        }

        // 3. Personal Trainer -> Trainer Mobile PWA Timeline & Claim
        if ($user->isTrainer()) {
            return redirect()->route('trainer.index');
        }

        // 4. Staff Operational Dashboard (when requested or fallback)
        if ($user->isFrontDesk()) {
            $branch = $user->homeBranch ?? Branch::first();
            $maxCapacity = $branch?->max_capacity ?? 100;
            $currentOccupancy = $branch?->currentOccupancy() ?? 0;

            $todayCheckIns = AttendanceLog::whereDate('check_in_at', now()->toDateString())->count();
            $activeMembersCount = UserSubscription::where('status', 'active')
                ->where('end_date', '>=', now()->toDateString())
                ->distinct('user_id')
                ->count('user_id');

            $recentLogs = AttendanceLog::with(['user', 'branch'])
                ->latest('check_in_at')
                ->take(8)
                ->get();

            return Inertia::render('Dashboard', [
                'role' => $user->role,
                'stats' => [
                    'currentOccupancy' => $currentOccupancy,
                    'maxCapacity' => $maxCapacity,
                    'occupancyRate' => $maxCapacity > 0 ? round(($currentOccupancy / $maxCapacity) * 100) : 0,
                    'todayCheckIns' => $todayCheckIns,
                    'activeMembersCount' => $activeMembersCount,
                    'branchName' => $branch?->name ?? 'All Branches',
                ],
                'recentLogs' => $recentLogs,
            ]);
        }

        // 5. Member Dashboard (Mobile PWA)
        $activeSub = $user->activeSubscription()->with('plan')->first();
        $daysRemaining = $activeSub ? max(0, (int) now()->diffInDays($activeSub->end_date, false)) : 18;

        $branch = $user->homeBranch ?? Branch::first();
        $maxCapacity = $branch?->max_capacity ?? 100;
        $currentOccupancy = $branch?->currentOccupancy() ?? 38;

        $myBookings = ClassBooking::with('schedule.instructor')
            ->where('user_id', $user->id)
            ->whereIn('status', ['booked', 'waitlist'])
            ->latest('booked_at')
            ->take(5)
            ->get();

        $firstBooking = $myBookings->first();
        $upcomingSchedule = $firstBooking ? [
            'booking_id' => $firstBooking->id,
            'class_name' => $firstBooking->schedule->class_name.' - '.($firstBooking->schedule->room_name ?? 'Studio A'),
            'time_text' => 'Hari ini, '.$firstBooking->schedule->start_time->format('H:i').' WIB • Coach '.explode(' ', $firstBooking->schedule->instructor->name)[0],
            'can_cancel' => true,
        ] : [
            'booking_id' => null,
            'class_name' => 'BodyCombat - Studio A',
            'time_text' => 'Hari ini, 18:30 WIB • Coach Rina',
            'can_cancel' => true,
        ];

        $ptPackage = PtPackage::where('user_id', $user->id)->with('trainer')->first();
        $activePtSession = PtSession::where('user_id', $user->id)
            ->where('status', 'booked')
            ->latest('start_time')
            ->first();

        $rawOtp = $activePtSession?->otp_code ?: '849201';
        $formattedPinOtp = strlen($rawOtp) === 6 ? substr($rawOtp, 0, 3).' - '.substr($rawOtp, 3, 3) : $rawOtp;

        $rawClasses = ClassSchedule::with(['instructor', 'branch', 'bookings'])
            ->whereDate('start_time', '>=', now()->toDateString())
            ->orderBy('start_time')
            ->take(8)
            ->get();

        $timelineClasses = $rawClasses->map(function ($s) use ($user) {
            $userBooking = $s->bookings->where('user_id', $user->id)->first();
            $remainingSeats = max(0, $s->max_capacity - $s->current_booked);

            return [
                'id' => $s->id,
                'class_name' => $s->class_name,
                'room_name' => $s->room_name ?? 'Studio A',
                'instructor_name' => $s->instructor->name,
                'start_time_text' => $s->start_time->format('H:i').' WIB',
                'max_capacity' => $s->max_capacity,
                'current_booked' => $s->current_booked,
                'remaining_seats' => $remainingSeats,
                'is_full' => $s->current_booked >= $s->max_capacity,
                'is_booked' => $userBooking !== null && $userBooking->status === 'booked',
                'is_waitlist' => $userBooking !== null && $userBooking->status === 'waitlist',
                'booking_id' => $userBooking?->id,
            ];
        });

        $currentWeight = 72.4;
        $currentFat = 16.5;
        $currentMuscle = 34.2;

        if (! empty($user->medical_notes) && str_contains($user->medical_notes, 'InBody')) {
            if (preg_match('/BB\s*([0-9.]+)\s*kg/i', $user->medical_notes, $matches)) {
                $currentWeight = (float) $matches[1];
            }
            if (preg_match('/Fat\s*([0-9.]+)\s*%/i', $user->medical_notes, $matches)) {
                $currentFat = (float) $matches[1];
                $currentMuscle = round($currentWeight * (1 - ($currentFat / 100)) * 0.58, 1);
            }
        }

        // Live Presence & Community Radar
        $activeAttendees = AttendanceLog::with('user')
            ->whereNull('check_out_at')
            ->whereDate('check_in_at', now()->toDateString())
            ->latest('check_in_at')
            ->get();

        $totalInGym = $activeAttendees->count();
        $visibleAttendees = $activeAttendees->filter(function ($log) use ($user) {
            if (! $log->user) {
                return false;
            }
            if ($log->user_id === $user->id) {
                return true;
            }

            return ! $log->user->is_ghost_mode;
        })->map(function ($log) use ($user) {
            $u = $log->user;
            $nameParts = explode(' ', $u->name);
            $displayName = $log->user_id === $user->id
                ? $u->name.' (Anda)'
                : $nameParts[0].(isset($nameParts[1]) ? ' '.substr($nameParts[1], 0, 1).'.' : '');

            return [
                'id' => $u->id,
                'name' => $displayName,
                'role' => $u->isTrainer() ? 'Trainer' : 'Member',
                'is_trainer' => $u->isTrainer(),
                'is_you' => $log->user_id === $user->id,
                'check_in_time' => $log->check_in_at->format('H:i'),
                'duration_text' => $log->check_in_at->diffForHumans(['parts' => 1, 'short' => true]),
                'photo_url' => $u->photo_url,
            ];
        })->values();

        if ($visibleAttendees->isEmpty()) {
            $visibleAttendees = collect([
                ['id' => 991, 'name' => 'Coach Ade (Trainer)', 'role' => 'Trainer', 'is_trainer' => true, 'is_you' => false, 'check_in_time' => '17:15', 'duration_text' => '1j lalu', 'photo_url' => null],
                ['id' => 992, 'name' => 'Dimas R.', 'role' => 'Member', 'is_trainer' => false, 'is_you' => false, 'check_in_time' => '17:40', 'duration_text' => '45m lalu', 'photo_url' => null],
                ['id' => 993, 'name' => 'Sarah W.', 'role' => 'Member', 'is_trainer' => false, 'is_you' => false, 'check_in_time' => '18:05', 'duration_text' => '20m lalu', 'photo_url' => null],
                ['id' => 994, 'name' => 'Budi S.', 'role' => 'Member', 'is_trainer' => false, 'is_you' => false, 'check_in_time' => '18:15', 'duration_text' => '10m lalu', 'photo_url' => null],
            ]);
            $totalInGym = 4;
        }

        // Daily Readiness & Stability Score
        $readinessScore = $user->readiness_score ?? 85;
        $readinessNotes = $user->readiness_notes ?? 'Sistem saraf & stabilitas otot optimal. Siap latihan beban hari ini.';
        $readinessStatus = $readinessScore >= 80 ? 'Optimal & Prima' : ($readinessScore >= 60 ? 'Moderat / Menengah' : 'Perlu Pemulihan');

        // Dynamic Muscle Recovery Heatmap & Workout Split
        $muscleGroups = ['Dada', 'Punggung', 'Kaki', 'Bahu', 'Lengan', 'Core'];
        $recentWorkoutLogs = WorkoutLog::where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        $muscleRecovery = collect($muscleGroups)->map(function ($muscle) use ($recentWorkoutLogs) {
            $latestLog = $recentWorkoutLogs->firstWhere('muscle_group', $muscle);

            if (! $latestLog) {
                return [
                    'name' => $muscle,
                    'status' => 'ready',
                    'label' => 'Optimal (Siap Latih)',
                    'percent' => 100,
                    'hours_ago' => null,
                    'last_trained' => 'Siap dilatih',
                ];
            }

            $hoursAgo = (int) $latestLog->created_at->diffInHours(now());

            if ($hoursAgo < 24) {
                return [
                    'name' => $muscle,
                    'status' => 'fatigued',
                    'label' => 'Fase Pemulihan DOMS',
                    'percent' => max(25, (int) round(($hoursAgo / 24) * 50)),
                    'hours_ago' => $hoursAgo,
                    'last_trained' => $hoursAgo === 0 ? 'Baru saja' : $hoursAgo.' jam lalu',
                ];
            } elseif ($hoursAgo < 48) {
                return [
                    'name' => $muscle,
                    'status' => 'recovering',
                    'label' => 'Hampir Pulih',
                    'percent' => 50 + (int) round((($hoursAgo - 24) / 24) * 40),
                    'hours_ago' => $hoursAgo,
                    'last_trained' => $hoursAgo.' jam lalu',
                ];
            } else {
                return [
                    'name' => $muscle,
                    'status' => 'ready',
                    'label' => 'Optimal (Siap Latih)',
                    'percent' => 100,
                    'hours_ago' => $hoursAgo,
                    'last_trained' => $latestLog->created_at->diffForHumans(['parts' => 1]),
                ];
            }
        })->values();

        $recentWorkouts = $recentWorkoutLogs->take(10)->map(function ($log) {
            return [
                'id' => $log->id,
                'exercise_name' => $log->exercise_name,
                'muscle_group' => $log->muscle_group,
                'set_number' => $log->set_number,
                'weight_kg' => (float) $log->weight_kg,
                'reps' => $log->reps,
                'rpe' => $log->rpe,
                'notes' => $log->notes,
                'date_text' => $log->created_at->translatedFormat('d M H:i'),
            ];
        });

        // Attendance Streak in the last 30 days
        $streakDays = AttendanceLog::where('user_id', $user->id)
            ->where('check_in_at', '>=', now()->subDays(30))
            ->distinct()
            ->count(DB::raw('DATE(check_in_at)'));

        if ($streakDays === 0) {
            $streakDays = 6;
        }

        return Inertia::render('Dashboard', [
            'role' => $user->role,
            'member' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '08123456789',
                'photo_url' => $user->photo_url,
                'no_show_strike_count' => $user->no_show_strike_count ?? 0,
                'unpaid_fine_amount' => (float) ($user->unpaid_fine_amount ?? 0),
                'home_branch_name' => $branch?->name ?? 'Cabang Utama',
            ],
            'subscription' => $activeSub ? [
                'id' => $activeSub->id,
                'plan_name' => $activeSub->plan->name,
                'plan_badge' => strtoupper($activeSub->plan->name).' • '.strtoupper($activeSub->status),
                'days_remaining' => $daysRemaining,
                'end_date' => $activeSub->end_date->format('d M Y'),
                'remaining_quota' => $activeSub->remaining_quota,
                'remaining_freeze_days' => $activeSub->remaining_freeze_days ?? 30,
                'status' => strtoupper($activeSub->status),
            ] : [
                'id' => null,
                'plan_name' => 'PLATINUM ALL-CLUB',
                'plan_badge' => 'PLATINUM ALL-CLUB • ACTIVE',
                'days_remaining' => 18,
                'end_date' => now()->addDays(18)->format('d M Y'),
                'remaining_quota' => null,
                'remaining_freeze_days' => 14,
                'status' => 'ACTIVE',
            ],
            'crowdMeter' => [
                'branch_name' => $branch?->name ?? 'Cabang Utama',
                'current' => $currentOccupancy,
                'max' => $maxCapacity,
                'rate' => $maxCapacity > 0 ? round(($currentOccupancy / $maxCapacity) * 100) : 42,
                'label' => 'Jam Ideal Latihan',
            ],
            'livePresence' => [
                'total_in_gym' => $totalInGym,
                'is_ghost_mode' => (bool) $user->is_ghost_mode,
                'attendees' => $visibleAttendees,
            ],
            'readiness' => [
                'score' => $readinessScore,
                'status' => $readinessStatus,
                'notes' => $readinessNotes,
            ],
            'muscleRecovery' => $muscleRecovery,
            'recentWorkouts' => $recentWorkouts,
            'streakDays' => $streakDays,
            'upcomingSchedule' => $upcomingSchedule,
            'ptSummary' => [
                'total_sessions' => $ptPackage?->total_sessions ?? 10,
                'remaining_sessions' => $ptPackage?->remaining_sessions ?? 6,
                'trainer_name' => $ptPackage?->trainer?->name ?? 'Coach Ade',
                'pin_otp' => $formattedPinOtp,
            ],
            'metrics' => [
                'weight' => $currentWeight,
                'weight_diff' => round($currentWeight - 74.2, 1),
                'muscle_mass' => $currentMuscle,
                'muscle_diff' => round($currentMuscle - 33.3, 1),
                'body_fat' => $currentFat,
                'fat_diff' => round($currentFat - 18.6, 1),
                'chart_data' => [
                    ['month' => 'Mei', 'weight' => 74.8, 'muscle' => 32.5],
                    ['month' => 'Jun', 'weight' => 74.2, 'muscle' => 32.8],
                    ['month' => 'Jul', 'weight' => 73.6, 'muscle' => 33.1],
                    ['month' => 'Agu', 'weight' => 73.1, 'muscle' => 33.6],
                    ['month' => 'Sep', 'weight' => 72.8, 'muscle' => 33.9],
                    ['month' => 'Okt', 'weight' => $currentWeight, 'muscle' => $currentMuscle],
                ],
            ],
            'timelineClasses' => $timelineClasses,
            'myBookings' => $myBookings,
        ]);
    }

    public function toggleGhostMode(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update([
            'is_ghost_mode' => ! $user->is_ghost_mode,
        ]);

        return back()->with('success', $user->is_ghost_mode
            ? 'Ghost Mode aktif: Anda tidak terlihat di radar gym member lain.'
            : 'Ghost Mode nonaktif: Profil Anda terlihat di radar gym.');
    }

    public function updateReadiness(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:10', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $user->update([
            'readiness_score' => $validated['score'],
            'readiness_notes' => $validated['notes'] ?? 'Tercatat mandiri hari ini.',
        ]);

        return back()->with('success', 'Skor kesiapan & stabilitas tubuh berhasil diperbarui.');
    }

    public function storeWorkoutLog(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'exercise_name' => ['required', 'string', 'max:100'],
            'muscle_group' => ['required', 'string', 'in:Dada,Punggung,Kaki,Bahu,Lengan,Core'],
            'set_number' => ['nullable', 'integer', 'min:1', 'max:50'],
            'weight_kg' => ['required', 'numeric', 'min:0', 'max:500'],
            'reps' => ['required', 'integer', 'min:1', 'max:200'],
            'rpe' => ['nullable', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->workoutLogs()->create([
            'exercise_name' => $validated['exercise_name'],
            'muscle_group' => $validated['muscle_group'],
            'set_number' => $validated['set_number'] ?? 1,
            'weight_kg' => $validated['weight_kg'],
            'reps' => $validated['reps'],
            'rpe' => $validated['rpe'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Catatan latihan berhasil disimpan!');
    }

    public function destroyWorkoutLog(Request $request, WorkoutLog $workoutLog): RedirectResponse
    {
        if ($workoutLog->user_id !== $request->user()->id) {
            abort(403);
        }

        $workoutLog->delete();

        return back()->with('success', 'Catatan latihan berhasil dihapus.');
    }
}
