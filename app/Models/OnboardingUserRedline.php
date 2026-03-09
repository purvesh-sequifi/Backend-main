<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingUserRedline extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'onboarding_user_redlines';

    protected $fillable = [
        'user_id',
        'product_id',
        'self_gen_user',
        'updater_id',
        'tiers_id',
        'redline_amount_type',
        'redline',
        'redline_type',
        'state_id',
        'start_date',
        'commission',
        'commission_type',
        'commission_effective_date',
        'upfront_pay_amount',
        'upfront_sale_type',
        'upfront_effective_date',
        'position_id',
        'core_position_id',
        'withheld_amount',
        'withheld_type',
        'withheld_effective_date',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    /**
     * Get the custom sales field for this redline
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
