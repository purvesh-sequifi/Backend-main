<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OverridePoolQuarterlyAdvance extends Model
{
    use HasFactory;

    protected $table = 'override_pool_quarterly_advances';

    protected $fillable = [
        'user_id',
        'year',
        'q1_advance',
        'q2_advance',
        'q3_advance',
    ];

    protected $casts = [
        'user_id'    => 'integer',
        'year'       => 'integer',
        'q1_advance' => 'float',
        'q2_advance' => 'float',
        'q3_advance' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by calculation year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Compute total advances paid (Q1 + Q2 + Q3).
     */
    public function totalAdvances(): float
    {
        return (float) ($this->q1_advance + $this->q2_advance + $this->q3_advance);
    }
}
