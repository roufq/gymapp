<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'trainer_id',
    'total_sessions',
    'remaining_sessions',
    'price_per_session',
    'expires_at',
    'status',
])]
class PtPackage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total_sessions' => 'integer',
            'remaining_sessions' => 'integer',
            'price_per_session' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PtSession::class);
    }
}
