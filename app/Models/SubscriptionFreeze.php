<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subscription_id',
    'requested_by',
    'approved_by',
    'freeze_start',
    'freeze_end',
    'total_days',
    'status',
])]
class SubscriptionFreeze extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'freeze_start' => 'date',
            'freeze_end' => 'date',
            'total_days' => 'integer',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
