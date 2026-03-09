<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingEmployeeLocation extends Model
{
    use HasFactory;

    protected $table = 'onboarding_employee_locations';

    protected $fillable = [
        'user_id',
        'state_id',
        'city_id',
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

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id');
    }
}
