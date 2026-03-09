<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollAlerts extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'payroll_alerts';

    protected $fillable = [
        'user_id',
        'position_id',
        'commission',
        'pay_period_from',
        'pay_period_to',
        'payroll',
        'status',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function users(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->with('positionDetail')->select('id', 'first_name', 'last_name', 'email', 'position_id');
    }
}
