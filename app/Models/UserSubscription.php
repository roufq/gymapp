<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'plan_id',
    'start_date',
    'end_date',
    'remaining_quota',
    'remaining_freeze_days',
    'status',
    'total_paid_amount',
])]
class UserSubscription extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'remaining_quota' => 'integer',
            'remaining_freeze_days' => 'integer',
            'total_paid_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(SubscriptionFreeze::class, 'subscription_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->start_date->isPast() || $this->start_date->isToday())
            && ($this->end_date->isFuture() || $this->end_date->isToday());
    }
}
