<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingEmployeeUpfront extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'onboarding_employee_upfronts';

    protected $fillable = [
        'user_id',
        'position_id',
        'product_id',
        'core_position_id',
        'milestone_schema_id',
        'milestone_schema_trigger_id',
        'self_gen_user',
        'updater_id',
        'tiers_id',
        'upfront_pay_amount',
        'upfront_sale_type',
        'upfront_effective_date',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    public function milestoneTrigger(): HasOne
    {
        return $this->hasOne(MilestoneSchemaTrigger::class, 'id', 'milestone_schema_trigger_id');
    }

    /**
     * Get the custom sales field for this upfront
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
