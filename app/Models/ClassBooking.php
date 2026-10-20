<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'class_schedule_id',
    'user_id',
    'status',
    'waitlist_position',
    'booked_at',
    'cancelled_at',
])]
class ClassBooking extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'waitlist_position' => 'integer',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'class_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
