<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconciliationFinalize extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'reconciliation_finalize';

    protected $fillable = [
        'office_id',
        'position_id',
        'start_date',
        'end_date',
        'commissions',
        'overrides',
        'total_due',
        'clawbacks',
        'adjustments',
        'deductions',
        'remaining',
        'payout_percentage',
        'net_amount',
        'status',
        'pay_period_from',
        'pay_period_to',
        'is_upfront',
    ];

    public function reconciliationFinalizeHistory(): HasMany
    {
        return $this->hasMany(ReconciliationFinalizeHistory::class, 'finalize_id', 'id');
    }

    public function position(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id');
    }
}
