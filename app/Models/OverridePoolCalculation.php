<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class OverridePoolCalculation extends Model
{
    use HasFactory;

    protected $table = 'override_pool_calculations';

    protected $fillable = [
        'user_id',
        'year',
        'downline_count',
        'downline_sales',
        'pool_percentage',
        'gross_pool_value',
        'part1',
        'part2_total',
        'total_pool_payment',
        'pool_rate',
        'part2_breakdown',
        'q4_trueup',
        'calculation_error',
    ];

    protected $casts = [
        'user_id'            => 'integer',
        'year'               => 'integer',
        'downline_count'     => 'integer',
        'downline_sales'     => 'integer',
        'pool_percentage'    => 'float',
        'gross_pool_value'   => 'float',
        'part1'              => 'float',
        'part2_total'        => 'float',
        'total_pool_payment' => 'float',
        'pool_rate'          => 'float',
        'part2_breakdown'    => 'array',
        'q4_trueup'          => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fetch the quarterly advance record for the same user and year.
     */
    public function advance(): HasOne
    {
        return $this->hasOne(OverridePoolQuarterlyAdvance::class, 'user_id', 'user_id')
            ->where('year', $this->year ?? 0);
    }

    /**
     * Scope to filter by calculation year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Whether the Q4 true-up is negative (agent was overpaid).
     */
    public function isOverpaid(): bool
    {
        return $this->q4_trueup !== null && $this->q4_trueup < 0;
    }
}
