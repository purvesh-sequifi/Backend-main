<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingEmployeeOverride extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'onboarding_employee_override';

    protected $fillable = [
        'user_id',
        'product_id',
        'updater_id',
        'direct_tiers_id',
        'indirect_tiers_id',
        'office_tiers_id',
        'override_effective_date',
        'direct_overrides_amount',
        'direct_overrides_type',
        'indirect_overrides_amount',
        'indirect_overrides_type',
        'office_overrides_amount',
        'office_overrides_type',
        'office_stack_overrides_amount',
        // Custom Sales Field feature
        'direct_custom_sales_field_id',
        'indirect_custom_sales_field_id',
        'office_custom_sales_field_id',
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
     * Get the direct custom sales field
     */
    public function directCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'direct_custom_sales_field_id');
    }

    /**
     * Get the indirect custom sales field
     */
    public function indirectCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'indirect_custom_sales_field_id');
    }

    /**
     * Get the office custom sales field
     */
    public function officeCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'office_custom_sales_field_id');
    }
}
