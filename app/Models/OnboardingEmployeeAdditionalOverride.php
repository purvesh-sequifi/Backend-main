<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingEmployeeAdditionalOverride extends Model
{
    use HasFactory;

    protected $table = 'onboarding_employee_additional_overrides';

    protected $fillable = [
        'onboarding_location_id',
        'user_id',
        'tiers_id',
        'product_id',
        'overrides_amount',
        'overrides_type',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function OnboardingEmployeeLocations(): HasOne
    {
        return $this->hasOne(\App\Models\OnboardingEmployeeLocations::class, 'id', 'onboarding_location_id');
    }

    public function OnboardingEmployeeLocation(): HasOne
    {
        return $this->hasOne(\App\Models\OnboardingEmployeeLocation::class, 'id', 'onboarding_location_id')->with(['state', 'city', 'office']);
    }

    /**
     * Get the custom sales field for this additional override
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
