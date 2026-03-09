<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PositionReconciliations extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'position_reconciliations';

    protected $fillable = [
        'position_id',
        'product_id',
        'commission_withheld',
        'commission_type',
        'commission_withheld_locked',
        'commission_type_locked',
        'maximum_withheld',
        'override_settlement',
        'clawback_settlement',
        'stack_settlement',
        'tiers_commission_settlement',
        'tiers_override_settlement',
        'status',
        'effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
