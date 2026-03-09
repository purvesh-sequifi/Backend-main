<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingEmployeeWithheld extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'onboarding_employee_withhelds';

    protected $fillable = [
        'user_id',
        'product_id',
        'updater_id',
        'position_id',
        'withheld_amount',
        'withheld_type',
        'withheld_effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }
}
