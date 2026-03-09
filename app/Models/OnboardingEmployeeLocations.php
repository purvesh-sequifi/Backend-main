<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingEmployeeLocations extends Model
{
    use HasFactory;

    protected $table = 'onboarding_employee_locations';

    protected $fillable = [
        'user_id',
        'state_id',
        'city_id',
        'overrides_amount',
        'overrides_type',
        'office_id',
        'product_id',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id');
    }

    public function city(): HasOne
    {
        return $this->hasOne(\App\Models\Cities::class, 'id', 'city_id');
    }

    /**
     * Get the custom sales field for this location
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
