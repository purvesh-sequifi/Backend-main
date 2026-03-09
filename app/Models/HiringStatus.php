<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class HiringStatus extends Model
{
    use HasFactory;

    protected $table = 'hiring_status';

    public $search_array;

    protected $fillable = [
        'id',
        'status',
        'display_order',
        'hide_status',
        'colour_code',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function OnboardingEmployees(): HasMany
    {
        return $this->hasMany(\App\Models\OnboardingEmployees::class, 'status_id', 'id')
            ->with('recruiter', 'positionDetail:id,position_name', 'onboarding_user_resend_offer_status', 'office:id,office_name,state_id')
            ->select('id', 'user_id', 'recruiter_id', 'first_name', 'last_name', 'status_id', 'sub_position_id', 'office_id', 'status_date', DB::raw('DATEDIFF(now(),`updated_at`) as days_in_status'));
    }

    public function hiringOnboardingEmployees(): HasMany
    {
        return $this->hasMany(\App\Models\OnboardingEmployees::class, 'status_id', 'id')
            ->with('recruiter', 'positionDetail:id,position_name', 'onboarding_user_resend_offer_status', 'office:id,office_name,state_id', 'OnboardingEmployeesDocuments')
            ->select('id', 'recruiter_id', 'first_name', 'last_name', 'status_id', 'sub_position_id', 'office_id', 'is_background_verificaton', 'position_id', 'status_date', DB::raw('DATEDIFF(now(),`updated_at`) as days_in_status'));
    }
}
