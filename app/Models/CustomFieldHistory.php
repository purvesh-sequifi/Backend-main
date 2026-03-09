<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomFieldHistory extends Model
{
    use HasFactory;

    protected $table = 'custom_field_history';

    protected $fillable = [
        'user_id',
        'payroll_id',
        'column_id',
        'value',
        'comment',
        'approved_by',
        'ref_id',
        'is_mark_paid',
        'is_next_payroll',
        'pay_period_from',
        'pay_period_to',
        'user_worker_type',
        'pay_frequency',
        'is_onetime_payment',
        'one_time_payment_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getColumn(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PayrollSsetup::class, 'column_id', 'id');
    }

    public function getApprovedBy(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'approved_by');
    }

    public function PayrollSsetup(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollSsetup::class, 'id', 'column_id');
    }

    public function oneTimePaymentDetail(): HasOne
    {
        return $this->hasOne(\App\Models\OneTimePayments::class, 'id', 'one_time_payment_id');
    }
}
