<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'plan_type',
    'duration_days',
    'quota_visits',
    'price',
    'allow_all_branches',
    'allowed_time_start',
    'allowed_time_end',
    'max_freeze_days',
    'is_active',
])]
class MembershipPlan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'quota_visits' => 'integer',
            'price' => 'decimal:2',
            'allow_all_branches' => 'boolean',
            'max_freeze_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'plan_id');
    }
}
