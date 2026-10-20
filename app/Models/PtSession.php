<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pt_package_id',
    'user_id',
    'trainer_id',
    'attendance_log_id',
    'start_time',
    'end_time',
    'status',
    'otp_code',
    'is_substitute',
    'workout_notes',
    'commission_amount',
    'verified_at',
])]
class PtSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'verified_at' => 'datetime',
            'is_substitute' => 'boolean',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PtPackage::class, 'pt_package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}
