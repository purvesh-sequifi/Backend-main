<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionBillingHistory extends Model
{
    use HasFactory;

    protected $table = 'subscription_billing_histories';

    protected $fillable = [
        'subscription_id',
        'amount',
        'paid_status',
        'invoice_no',
        'billing_date',
        'plan_id',
        'plan_name',
        'unique_pid_rack_price',
        'unique_pid_discount_price',
        'm2_rack_price',
        'm2_discount_price',
        'billing_id',
        'client_secret',
        'created_at',
        'updated_at',
    ];

    public static function genrate_invoice()
    {
        return $invoice_no = 'S-'.time();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscriptions::class, 'subscription_id');
    }

    public function plans(): HasOne
    {
        return $this->hasOne(Plans::class, 'id', 'plan_id');
    }

    public function SalesInvoiceDetail(): HasMany
    {
        return $this->hasMany(SalesInvoiceDetail::class, 'billing_history_id', 'id');
    }
}
