<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'exercise_name',
    'muscle_group',
    'set_number',
    'weight_kg',
    'reps',
    'rpe',
    'notes',
])]
class WorkoutLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'weight_kg' => 'decimal:2',
            'reps' => 'integer',
            'rpe' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
