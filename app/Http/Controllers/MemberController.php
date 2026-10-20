<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $members = User::where('role', 'member')
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->with(['activeSubscription.plan', 'homeBranch'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $plans = MembershipPlan::where('is_active', true)->get();
        $branches = Branch::all();

        return Inertia::render('Members/Index', [
            'members' => $members,
            'plans' => $plans,
            'branches' => $branches,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:25', 'unique:users,phone'],
            'plan_id' => ['required', 'exists:membership_plans,id'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,edc'],
            'medical_notes' => ['nullable', 'string'],
        ]);

        $branch = $request->user()->homeBranch ?? Branch::first();
        $plan = MembershipPlan::findOrFail($validated['plan_id']);

        DB::transaction(function () use ($validated, $branch, $plan) {
            // 1. Create Member User (Default password = nomor HP agar member bisa login PWA)
            $member = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['phone']),
                'role' => 'member',
                'home_branch_id' => $branch->id,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'email_verified_at' => now(),
            ]);

            // 2. Create Active Subscription
            $subscription = UserSubscription::create([
                'user_id' => $member->id,
                'plan_id' => $plan->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays($plan->duration_days)->toDateString(),
                'remaining_quota' => $plan->quota_visits,
                'remaining_freeze_days' => $plan->max_freeze_days,
                'status' => 'active',
                'total_paid_amount' => $plan->price,
            ]);

            // 3. Create Direct Cash/Manual Invoice
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

            // 4. Create Invoice Item
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => 'membership',
                'item_id' => $plan->id,
                'name' => "Membership: {$plan->name}",
                'qty' => 1,
                'unit_price' => $plan->price,
                'total_price' => $plan->price,
            ]);
        });

        return redirect()->route('members.index')->with('success', 'Registrasi member & aktivasi paket berhasil! Password default member adalah nomor HP.');
    }

    public function show(Request $request, User $member): Response
    {
        abort_if(! $request->user()->isFrontDesk() && $request->user()->id !== $member->id, 403);

        $member->load([
            'homeBranch',
            'subscriptions.plan',
            'attendanceLogs' => fn ($q) => $q->latest('check_in_at')->take(15),
        ]);

        return Inertia::render('Members/Show', [
            'member' => $member,
        ]);
    }
}
