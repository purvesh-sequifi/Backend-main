<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Subscriptions extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'plan_type_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'paid_status',
        'total_pid',
        'total_m2',
        'sales_tax_per',
        'sales_tax_amount',
        'credit_amount',
        'used_credit',
        'balance_credit',
        'taxable_amount',
        'minimum_billing',
        'grand_total',
        'amount',
        'active_user_billing',
        'paid_active_user_billing',
        'sale_approval_active_user_billing',
        'logged_in_active_user_billing',
    ];

    public function billingType(): HasOne
    {
        // return $this->hasOne(BillingType::class, 'foreign_key', 'local_key');
        // return $this->hasOne(BillingType::class, 'id', 'plan_type_id');
        return $this->hasOne(BillingFrequency::class, 'id', 'plan_type_id');
    }

    public function plans(): HasOne
    {
        // return $this->hasOne('App\Models\Plans','id', 'plan_id');
        return $this->hasOne(Plans::class, 'id', 'plan_id');

    }
}
