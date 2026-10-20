<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'address',
    'max_capacity',
    'opening_time',
    'closing_time',
    'timezone',
])]
class Branch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'max_capacity' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'home_branch_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function currentOccupancy(): int
    {
        return $this->attendanceLogs()
            ->whereNull('check_out_at')
            ->where('check_in_at', '>=', now()->subHours(4))
            ->count();
    }
}
