<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MembershipPlan;
use App\Models\SubscriptionFreeze;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'plan_id' => ['required', 'exists:membership_plans,id'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,edc'],
        ]);

        $member = User::findOrFail($validated['user_id']);
        $plan = MembershipPlan::findOrFail($validated['plan_id']);
        $branch = $request->user()->homeBranch ?? Branch::first();

        DB::transaction(function () use ($member, $plan, $branch, $validated) {
            // Determine start date: if current active sub exists, stack after it
            $currentSub = $member->activeSubscription;
            $startDate = ($currentSub && $currentSub->end_date->isFuture())
                ? (clone $currentSub->end_date)->addDay()->toDateString()
                : now()->toDateString();

            $endDate = (clone Carbon::parse($startDate))->addDays($plan->duration_days);

            UserSubscription::create([
                'user_id' => $member->id,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'remaining_quota' => $plan->quota_visits,
                'remaining_freeze_days' => $plan->max_freeze_days,
                'status' => 'active',
                'total_paid_amount' => $plan->price,
            ]);

            // Generate direct invoice
            $invoice = Invoice::create([
                'invoice_number' => 'INV-'.date('Ymd').'-'.strtoupper(Str::random(5)),
                'branch_id' => $branch->id,
                'user_id' => $member->id,
                'subtotal' => $plan->price,
                'grand_total' => $plan->price,
                'payment_method' => $validated['payment_method'],
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => 'membership',
                'item_id' => $plan->id,
                'name' => "Perpanjangan: {$plan->name}",
                'qty' => 1,
                'unit_price' => $plan->price,
                'total_price' => $plan->price,
            ]);
        });

        return back()->with('success', 'Paket berhasil diperpanjang!');
    }

    public function freeze(Request $request): RedirectResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription()->with('plan')->first()
            ?? UserSubscription::where('user_id', $user->id)->whereIn('status', ['active', 'frozen'])->latest('end_date')->first();

        if (! $subscription) {
            return back()->with('error', 'Gagal mengajukan cuti: Anda belum memiliki paket membership aktif saat ini.');
        }

        $validated = $request->validate([
            'freeze_start' => ['required', 'date', 'after_or_equal:today'],
            'freeze_end' => ['required', 'date', 'after_or_equal:freeze_start'],
        ]);

        $start = Carbon::parse($validated['freeze_start']);
        $end = Carbon::parse($validated['freeze_end']);
        $totalDays = (int) $start->diffInDays($end) + 1;

        $maxAllowedDays = $subscription->remaining_freeze_days > 0
            ? $subscription->remaining_freeze_days
            : ($subscription->plan->max_freeze_days ?? 30);

        if ($totalDays > $maxAllowedDays) {
            return back()->with('error', "Gagal: Durasi cuti ({$totalDays} hari) melebihi sisa kuota cuti paket Anda ({$maxAllowedDays} hari).");
        }

        DB::transaction(function () use ($subscription, $user, $start, $end, $totalDays) {
            SubscriptionFreeze::create([
                'subscription_id' => $subscription->id,
                'requested_by' => $user->id,
                'approved_by' => null,
                'freeze_start' => $start->toDateString(),
                'freeze_end' => $end->toDateString(),
                'total_days' => $totalDays,
                'status' => 'approved',
            ]);

            $newEndDate = $subscription->end_date->addDays($totalDays);
            $newRemaining = max(0, $subscription->remaining_freeze_days - $totalDays);

            $subscription->update([
                'end_date' => $newEndDate,
                'remaining_freeze_days' => $newRemaining,
                'status' => $start->isToday() ? 'frozen' : 'active',
            ]);
        });

        return back()->with('success', "Cuti ({$totalDays} hari) berhasil diaktifkan! Masa aktif paket Anda otomatis diperpanjang hingga {$subscription->fresh()->end_date->format('d M Y')}.");
    }
}
