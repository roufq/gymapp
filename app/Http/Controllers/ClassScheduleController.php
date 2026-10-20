<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ClassBooking;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $rawClasses = ClassSchedule::with(['instructor', 'branch'])
            ->withCount('bookings')
            ->whereDate('start_time', '>=', now()->toDateString())
            ->orderBy('start_time')
            ->get();

        // Fix finding 6: Eagerly fetch user bookings in 1 query instead of N+1 in map loop
        $userBookings = ClassBooking::whereIn('class_schedule_id', $rawClasses->pluck('id'))
            ->where('user_id', $user->id)
            ->whereIn('status', ['booked', 'waitlist'])
            ->get()
            ->keyBy('class_schedule_id');

        $classes = $rawClasses->map(function ($schedule) use ($userBookings) {
            $userBooking = $userBookings->get($schedule->id);

            return [
                'id' => $schedule->id,
                'class_name' => $schedule->class_name,
                'room_name' => $schedule->room_name,
                'instructor_name' => $schedule->instructor->name,
                'branch_name' => $schedule->branch->name,
                'start_time' => $schedule->start_time->format('d M Y, H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'max_capacity' => $schedule->max_capacity,
                'current_booked' => $schedule->current_booked,
                'available_seats' => max(0, $schedule->max_capacity - $schedule->current_booked),
                'is_full' => $schedule->current_booked >= $schedule->max_capacity,
                'my_booking' => $userBooking ? [
                    'id' => $userBooking->id,
                    'status' => $userBooking->status,
                    'waitlist_position' => $userBooking->waitlist_position,
                ] : null,
            ];
        });

        $trainers = User::where('role', 'trainer')->select('id', 'name')->get();
        $branches = Branch::all();

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'trainers' => $trainers,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class_name' => ['required', 'string', 'max:100'],
            'room_name' => ['nullable', 'string', 'max:50'],
            'instructor_id' => ['required', 'exists:users,id'],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $branch = $request->user()->homeBranch ?? Branch::first();

        ClassSchedule::create([
            ...$validated,
            'branch_id' => $branch->id,
            'current_booked' => 0,
            'status' => 'scheduled',
        ]);

        return back()->with('success', 'Jadwal kelas studio berhasil ditambahkan!');
    }

    public function book(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        $user = $request->user();

        // Fix finding 5: Reject if class has already started
        if ($schedule->start_time <= now()) {
            return back()->with('error', 'Booking Ditolak: Kelas ini sudah dimulai atau telah selesai.');
        }

        // 1. Check Suspension
        if ($user->booking_suspended_until && $user->booking_suspended_until->isFuture()) {
            return back()->with('error', 'Booking Ditolak: Anda sedang dalam masa penalti suspensi booking.');
        }

        // 2. Check Active Subscription & Quota
        $sub = $user->activeSubscription()->with('plan')->first();
        if (! $sub) {
            return back()->with('error', 'Booking Ditolak: Anda membutuhkan paket membership aktif untuk memesan kelas.');
        }

        if ($sub->plan->plan_type === 'quota_based' && $sub->remaining_quota <= 0) {
            return back()->with('error', 'Booking Ditolak: Kuota kunjungan paket Anda telah habis.');
        }

        // 3. Check already booked or waitlisted
        $existing = ClassBooking::where('class_schedule_id', $schedule->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && in_array($existing->status, ['booked', 'waitlist'], true)) {
            return back()->with('info', 'Anda sudah memesan kursi di kelas ini.');
        }

        try {
            return DB::transaction(function () use ($schedule, $user, $existing) {
                // Atomic update to grab a seat
                $allocated = DB::table('class_schedules')
                    ->where('id', $schedule->id)
                    ->where('current_booked', '<', $schedule->max_capacity)
                    ->increment('current_booked');

                if ($allocated) {
                    if ($existing) {
                        $existing->update([
                            'status' => 'booked',
                            'waitlist_position' => null,
                            'booked_at' => now(),
                            'cancelled_at' => null,
                        ]);
                    } else {
                        ClassBooking::create([
                            'class_schedule_id' => $schedule->id,
                            'user_id' => $user->id,
                            'status' => 'booked',
                            'booked_at' => now(),
                        ]);
                    }

                    return back()->with('success', "Berhasil! Kursi di kelas {$schedule->class_name} telah diamankan.");
                }

                // Otherwise, put in Waitlist
                $waitlistCount = ClassBooking::where('class_schedule_id', $schedule->id)
                    ->where('status', 'waitlist')
                    ->count();

                $pos = $waitlistCount + 1;

                if ($existing) {
                    $existing->update([
                        'status' => 'waitlist',
                        'waitlist_position' => $pos,
                        'booked_at' => now(),
                        'cancelled_at' => null,
                    ]);
                } else {
                    ClassBooking::create([
                        'class_schedule_id' => $schedule->id,
                        'user_id' => $user->id,
                        'status' => 'waitlist',
                        'waitlist_position' => $pos,
                        'booked_at' => now(),
                    ]);
                }

                return back()->with('info', "Kelas penuh! Anda masuk dalam antrean (Waitlist #{$pos}).");
            });
        } catch (UniqueConstraintViolationException) {
            return back()->with('info', 'Anda sudah memesan kursi di kelas ini.');
        }
    }

    public function cancel(Request $request, ClassBooking $booking): RedirectResponse
    {
        $user = $request->user();

        if ($booking->user_id !== $user->id && ! $user->isFrontDesk()) {
            abort(403);
        }

        if ($booking->status === 'cancelled') {
            return back()->with('info', 'Pemesanan ini sudah dibatalkan sebelumnya.');
        }

        // Fix finding 5: Reject if class has already started
        if ($booking->schedule->start_time <= now()) {
            return back()->with('error', 'Pembatalan Ditolak: Kelas sudah dimulai atau telah selesai.');
        }

        DB::transaction(function () use ($booking) {
            $schedule = $booking->schedule;
            $wasBooked = $booking->status === 'booked';

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            if ($wasBooked) {
                // Check if someone is on waitlist
                $nextInLine = ClassBooking::where('class_schedule_id', $schedule->id)
                    ->where('status', 'waitlist')
                    ->orderBy('waitlist_position')
                    ->first();

                if ($nextInLine) {
                    $nextInLine->update([
                        'status' => 'booked',
                        'waitlist_position' => null,
                    ]);
                } else {
                    // Decrement current_booked if waitlist is empty
                    DB::table('class_schedules')
                        ->where('id', $schedule->id)
                        ->where('current_booked', '>', 0)
                        ->decrement('current_booked');
                }
            }
        });

        return back()->with('success', 'Pemesanan kelas berhasil dibatalkan.');
    }
}
