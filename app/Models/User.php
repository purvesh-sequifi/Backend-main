<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasRoles;
    use HasFactory, Notifiable;
    use SpatieLogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'users';

    protected $fillable = [
        'employee_id',
        'aveyo_hs_id',
        'first_name',
        'middle_name',
        'last_name',
        'sex',
        'email',
        'api_token',
        'password',
        'mobile_no',
        'state_id',
        'city_id',
        'location',
        'department_id',
        'position_id',
        'sub_position_id',
        'group_id',
        'manager_id',
        'manager_id_effective_date',
        'team_id',
        'team_id_effective_date',
        'status_id', // status_id map in user_statuses tbl
        'recruiter_id',
        'additional_recruiter',
        'additional_recruiter_id1',
        'additional_recruiter_id2',
        'additional_recruiter1_per_kw_amount',
        'additional_recruiter2_per_kw_amount',
        'commission',
        'commission_type',
        'redline',
        'redline_amount_type',
        'redline_type',
        'upfront_pay_amount',
        'upfront_sale_type',
        'direct_overrides_amount',
        'direct_overrides_type',
        'indirect_overrides_amount',
        'indirect_overrides_type',
        'office_overrides_amount',
        'office_overrides_type',
        'office_stack_overrides_amount',
        'probation_period',
        'hiring_bonus_amount',
        'date_to_be_paid',
        'period_of_agreement_start_date',
        'end_date',
        'offer_expiry_date',
        'sex',
        'is_super_admin',
        'image',
        'middle_name',
        'dob',
        'zip_code',
        'work_email',
        'home_address',
        'home_address_line_1',
        'home_address_line_2',
        'home_address_state',
        'home_address_city',
        'home_address_zip',
        'home_address_lat',
        'home_address_long',
        'home_address_timezone',
        'emergency_address_line_1',
        'emergency_address_line_2',
        'emergency_address_lat',
        'emergency_address_long',
        'emergency_address_timezone',
        'emergency_contact_name',
        'emergency_phone',
        'emergency_contact_relationship',
        'emergrncy_contact_address',
        'emergrncy_contact_city',
        'emergrncy_contact_state',
        'emergrncy_contact_zip_code',
        'type',
        'rent',
        'is_manager',
        'is_manager_effective_date',
        'travel',
        'phone_Bill',
        'stop_payroll',
        'dismiss',
        'disable_login',
        'contract_ended',
        'device_token',
        'social_sequrity_no',
        'tax_information',
        'name_of_bank',
        'account_name',
        'routing_no',
        'account_no',
        'confirm_account_no',
        'type_of_account',
        'shirt_size',
        'onboardProcess',
        'hat_size',
        'additional_info_for_employee_to_get_started',
        'employee_personal_detail',
        'team_lead_status',
        'office_id',
        'user_uuid',
        'self_gen_redline',
        'self_gen_redline_amount_type',
        'self_gen_redline_type',
        'self_gen_commission',
        'self_gen_commission_type',
        'self_gen_upfront_amount',
        'self_gen_upfront_type',
        'self_gen_accounts',
        'self_gen_type',
        'entity_type',
        'business_name',
        'business_type',
        'business_ein',
        'withheld_amount',
        'withheld_type',
        'self_gen_withheld_amount',
        'self_gen_withheld_type',
        'everee_workerId',
        'okta_external_id',
        'everee_json_response',
        'experience_level',
        'position_id_effective_date',
        'pay_type',
        'pay_rate',
        'pay_rate_type',
        'pto_hours',
        'unused_pto_expires',
        'expected_weekly_hours',
        'overtime_rate',
        'worker_type',
        'everee_embed_onboard_profile',
        'terminate',
        'last_login_at',
        'first_time_changed_password',
        'is_agreement_accepted',
        'employee_admin_only_fields',
        // Custom Sales Field feature
        'commission_custom_sales_field_id',
        'self_gen_commission_custom_sales_field_id',
        'upfront_custom_sales_field_id',
        'direct_custom_sales_field_id',
        'indirect_custom_sales_field_id',
        'office_custom_sales_field_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        // 'created_at',
        // 'updated_at',
        'api_token',
        'email_verified_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getRememberToken()
    {
        return $this->remember_token;
    }

    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    /**
     * Get a fullname combination of first_name and last_name
     */
    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Prepare proper error handling for url attribute
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->info) {
            return asset($this->info->avatar_url);
        }

        return asset(theme()->getMediaUrlPath().'avatars/blank.png');
    }

    /**
     * User relation to info model
     */
    public function info(): HasOne
    {
        return $this->hasOne(UserInfo::class);
    }

    /**
     * User relation to info model
     */
    public function departmentDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'department_id')->select('id', 'name');
    }

    public function statusDetail(): HasOne
    {
        return $this->hasOne(\App\Models\UserStatus::class, 'id', 'status_id');
    }

    public function teamsDetail(): HasOne
    {
        return $this->hasOne(\App\Models\ManagementTeam::class, 'id', 'team_id')->with('user');
    }

    public function additionalDetail(): HasMany
    {
        return $this->hasMany(\App\Models\AdditionalRecruiters::class, 'user_id')->with('additionalRecruiterDetail');
    }

    public function recruitersDetail(): HasMany
    {
        return $this->hasMany(\App\Models\AdditionalRecruiters::class, 'user_id')->with('additionalRecruiterDetail');
    }

    public function additionalRedline(): HasMany
    {
        return $this->hasMany(\App\Models\UserRedlines::class, 'user_id', 'id');
    }

    public function override_status(): HasOne
    {
        return $this->hasOne(\App\Models\OverrideStatus::class, 'user_id', 'id');
    }

    public function override_history(): HasOne
    {
        return $this->hasOne(\App\Models\UserOverrideHistory::class, 'user_id', 'id');
    }

    public function recruiter(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'recruiter_id')->select('id', 'first_name', 'last_name', 'recruiter_id', 'image');
    }

    public function additionalRecruiterOne(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'additional_recruiter_id1')->select('id', 'first_name', 'last_name', 'recruiter_id');
    }

    public function additionalRecruiterTwo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'additional_recruiter_id2')->select('id', 'first_name', 'last_name', 'recruiter_id');
    }

    public function parents(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'recruiter_id')->select('id', 'first_name', 'last_name', 'recruiter_id', 'image', 'position_id')->with('positionDetail', 'parents');
    }

    public function teamDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Teams::class, 'id', 'team_id')->select('id', 'name');
    }

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id');
    }

    public function positionDeductionLimit(): HasOne
    {
        return $this->hasOne(\App\Models\PositionsDeductionLimit::class, 'position_id', 'sub_position_id');
    }

    public function positionCommissionDeduction(): HasMany
    {
        return $this->hasMany(\App\Models\PositionCommissionDeduction::class, 'position_id', 'sub_position_id');
    }

    public function userDeduction(): HasMany
    {
        return $this->hasMany(\App\Models\UserDeduction::class, 'user_id', 'id');
    }

    public function userSelfGenCommission(): HasMany
    {
        return $this->hasMany(\App\Models\UserSelfGenCommmissionHistory::class, 'user_id', 'id');
    }

    public function groupDetail(): HasOne
    {
        return $this->hasOne(\App\Models\GroupMaster::class, 'id', 'group_id');
    }

    public function managerDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'manager_id')->select('id', 'first_name', 'last_name', 'email');
    }

    public function activeLeads(): HasMany
    {
        return $this->hasMany(\App\Models\Lead::class, 'recruiter_id')->where('status', 'Follow Up');
    }

    public function notInterestedLeads(): HasMany
    {
        return $this->hasMany(\App\Models\Lead::class, 'recruiter_id')->where('status', 'Not Interested');
    }

    public function hiredLeads(): HasMany
    {
        return $this->hasMany(\App\Models\Lead::class, 'recruiter_id')->where('status', 'Hired');
    }

    public function lastHiredLeads(): HasOne
    {
        return $this->hasOne(\App\Models\Lead::class, 'recruiter_id')->select('last_hired_date')->where('status', 'Hired');
    }

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
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id')->with('State', 'redline_data');
    }

    public function office_selected(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id')->select('id', 'office_name')->with('State', 'redline_data');
    }

    public function user(): HasOne
    {
        return $this->hasOne('App\Models\Positionuser', 'id', 'compensation_plan_id');
    }

    public function closerCount(): HasMany
    {
        return $this->hasMany(SaleMasterProcess::class, 'closer1_id', 'id')->select('closer1_id', 'pid');
    }

    public function closerCountSecond(): HasMany
    {
        return $this->hasMany(SaleMasterProcess::class, 'closer2_id', 'id')->select('closer2_id', 'pid');
    }

    public function additionalLocations(): HasOne
    {
        return $this->hasOne(\App\Models\AdditionalLocations::class, 'user_id', 'id')->with('office');
    }

    public function additionalLocation(): HasMany
    {
        return $this->hasMany(\App\Models\AdditionalLocations::class, 'user_id', 'id');
    }

    public function orgChild(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id')->select('id', 'manager_id', 'first_name', 'last_name', 'image')->with('orgChild');
    }

    public function childs(): HasMany
    {
        return $this->hasMany(self::class, 'recruiter_id')->select('id', 'recruiter_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'first_name', 'last_name')->with('childs');
    }

    public function childs1(): HasMany
    {
        return $this->hasMany(self::class, 'additional_recruiter_id1')->select('id', 'recruiter_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'first_name', 'last_name')->with('childs1');
    }

    public function childs2(): HasMany
    {
        return $this->hasMany(self::class, 'additional_recruiter_id2')->select('id', 'recruiter_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'first_name', 'last_name')->with('childs2');
    }

    public function employeeBank(): HasOne
    {
        return $this->hasOne(\App\Models\EmployeeBanking::class, 'id', 'user_id');
    }

    // public function onboarding()
    // {
    //     return $this->hasMany('App\Models\OnboardingEmployees', 'manager_id','id')->count();
    // }

    public function subpositionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id');
    }

    public function onboardingUserRedline(): HasOne
    {
        return $this->hasOne(\App\Models\OnboardingUserRedline::class, 'id', 'user_id');
    }

    public function payroll(): HasOne
    {
        return $this->hasOne(\App\Models\Payroll::class, 'user_id', 'id');
    }

    public function reconciliations(): HasOne
    {
        return $this->hasOne(\App\Models\PositionReconciliations::class, 'position_id', 'sub_position_id')/* ->select('position_id','status') */;
    }

    public function upfront(): HasOne
    {
        return $this->hasOne(\App\Models\PositionCommissionUpfronts::class, 'position_id', 'sub_position_id')->select('position_id', 'upfront_status');
    }

    public function team(): HasOne
    {
        return $this->hasOne(\App\Models\ManagementTeam::class, 'team_lead_id', 'id');
    }

    public function positionDetailTeam(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function positionpayfrequencies(): HasOne
    {
        return $this->hasOne(\App\Models\PositionPayFrequency::class, 'position_id', 'sub_position_id');
    }

    public function additionalEmails(): HasMany
    {
        return $this->hasMany(\App\Models\UsersAdditionalEmail::class, 'user_id');
    }

    public function flexibleIds(): HasMany
    {
        return $this->hasMany(\App\Models\UserFlexibleId::class, 'user_id');
    }

    /**
     * Get flexible IDs created by this user.
     */
    public function createdFlexibleIds(): HasMany
    {
        return $this->hasMany(\App\Models\UserFlexibleId::class, 'created_by');
    }

    /**
     * Get flexible IDs updated by this user.
     */
    public function updatedFlexibleIds(): HasMany
    {
        return $this->hasMany(\App\Models\UserFlexibleId::class, 'updated_by');
    }

    /**
     * Get flexible IDs deleted by this user.
     */
    public function deletedFlexibleIds(): HasMany
    {
        return $this->hasMany(\App\Models\UserFlexibleId::class, 'deleted_by');
    }

    public function lastLogiingTime(): HasOne
    {
        return $this->hasOne(\App\Models\PersonalAccessToken::class, 'tokenable_id', 'id')->latest('id');
    }

    public function userCommissions(): HasMany
    {
        return $this->hasMany(UserCommissionHistory::class, 'user_id', 'id');
    }

    public function userOrganization(): HasOne
    {
        return $this->hasOne(UserOrganizationHistory::class, 'user_id', 'id');
    }

    public function userRedLines(): HasMany
    {
        return $this->hasMany(UserRedlines::class, 'user_id', 'id');
    }

    public function userUpFronts(): HasMany
    {
        return $this->hasMany(UserUpfrontHistory::class, 'user_id', 'id');
    }

    public function userWithHolds(): HasMany
    {
        return $this->hasMany(UserWithheldHistory::class, 'user_id', 'id');
    }

    public function userOverride(): HasOne
    {
        return $this->hasOne(UserOverrideHistory::class, 'user_id', 'id');
    }

    public function userSelfGenCommissionHistory(): HasOne
    {
        return $this->hasOne(UserSelfGenCommmissionHistory::class, 'user_id', 'id');
    }

    public function parentPositionDetail(): HasOne
    {
        return $this->hasOne(Positions::class, 'id', 'position_id');
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function states(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function payrollHistory(): HasMany
    {
        return $this->hasMany(PayrollHistory::class);
    }

    public function ApprovalsAndRequests(): HasMany
    {
        return $this->hasMany(ApprovalsAndRequest::class, 'user_id', 'id');
    }

    public function approvedBy()
    {
        return $this->hasone(\App\Models\User::class, 'approved_by', 'id');
    }

    public function userSchedules(): HasMany
    {
        return $this->hasMany(WpUserSchedule::class, 'user_id', 'id');
    }

    /**
     * Method getSocialSequrityNoAttribute
     *
     * @param  $value  $value [explicite description]
     */
    public function getSocialSequrityNoAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }    public function setSocialSequrityNoAttribute($value)
    {
        // Handle null/empty values
        if ($value === null || $value === '') {
            $this->attributes['social_sequrity_no'] = null;
            return;
        }
        
        // Try to decrypt - if it returns different value, it was encrypted
        $decrypted = dataDecrypt($value);
        if ($decrypted !== $value) {
            // Value was already encrypted, keep as-is
            $this->attributes['social_sequrity_no'] = $value;
        } else {
            // Value is plain text, encrypt it
            $this->attributes['social_sequrity_no'] = dataEncrypt($value);
        }
    }

    /**
     * Method getBusinessEinAttribute
     *
     * @param  $value  $value [explicite description]
     */
    public function getBusinessEinAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }    public function setBusinessEinAttribute($value)
    {
        // Handle null/empty values
        if ($value === null || $value === '') {
            $this->attributes['business_ein'] = null;
            return;
        }
        
        // Try to decrypt - if it returns different value, it was encrypted
        $decrypted = dataDecrypt($value);
        if ($decrypted !== $value) {
            // Value was already encrypted, keep as-is
            $this->attributes['business_ein'] = $value;
        } else {
            // Value is plain text, encrypt it
            $this->attributes['business_ein'] = dataEncrypt($value);
        }
    }

    /**
     * Method getBusinessEinAttribute
     *
     * @param  $value  $value [explicite description]
     */
    public function getAccountNoAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }    public function setAccountNoAttribute($value)
    {
        // Handle null/empty values
        if ($value === null || $value === '') {
            $this->attributes['account_no'] = null;
            return;
        }
        
        // Try to decrypt - if it returns different value, it was encrypted
        $decrypted = dataDecrypt($value);
        if ($decrypted !== $value) {
            // Value was already encrypted, keep as-is
            $this->attributes['account_no'] = $value;
        } else {
            // Value is plain text, encrypt it
            $this->attributes['account_no'] = dataEncrypt($value);
        }
    }

    /**
     * Method getBusinessEinAttribute
     *
     * @param  $value  $value [explicite description]
     */
    public function getRoutingNoAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }    public function setRoutingNoAttribute($value)
    {
        // Handle null/empty values
        if ($value === null || $value === '') {
            $this->attributes['routing_no'] = null;
            return;
        }
        
        // Try to decrypt - if it returns different value, it was encrypted
        $decrypted = dataDecrypt($value);
        if ($decrypted !== $value) {
            // Value was already encrypted, keep as-is
            $this->attributes['routing_no'] = $value;
        } else {
            // Value is plain text, encrypt it
            $this->attributes['routing_no'] = dataEncrypt($value);
        }
    }

    /**
     * Method getBusinessEinAttribute
     *
     * @param  $value  $value [explicit description]
     */
    public function getConfirmAccountNoAttribute($value)
    {
        if (isEncrypted($value)) {
            return dataDecrypt($value);
        }

        return $value;
    }    public function setConfirmAccountNoAttribute($value)
    {
        // Handle null/empty values
        if ($value === null || $value === '') {
            $this->attributes['confirm_account_no'] = null;
            return;
        }
        
        // Try to decrypt - if it returns different value, it was encrypted
        $decrypted = dataDecrypt($value);
        if ($decrypted !== $value) {
            // Value was already encrypted, keep as-is
            $this->attributes['confirm_account_no'] = $value;
        } else {
            // Value is plain text, encrypt it
            $this->attributes['confirm_account_no'] = dataEncrypt($value);
        }
    }

    public function scopeExcludeTerminated($query)
    {
        // status_id = 7 is terminnate
        return $query->where('status_id', '!=', 7);
    }

    // protected static function booted()
    // {
    //     static::addGlobalScope('notTerminated', function (\Illuminate\Database\Eloquent\Builder $builder) {
    //         // $builder->where('status_id', '!=', 7);
    //         $builder->where('terminate', 0);
    //     });
    // }

    // Define an accessor for the 'email' attribute
    // public function getEmailAttribute($value)
    // {
    //     // Check if the email contains '~~~'
    //     if (strpos($value, '~~~') !== false) {
    //         // Extract and return the part before '~~~'
    //         return substr($value, 0, strpos($value, '~~~'));
    //     }

    //     // If '~~~' is not present, return the original email
    //     return $value;
    // }

    // public function getMobileNoAttribute($value)
    // {
    //     // Check if the email contains '~~~'
    //     if (strpos($value, '~~~') !== false) {
    //         // Extract and return the part before '~~~'
    //         return substr($value, 0, strpos($value, '~~~'));
    //     }

    //     // If '~~~' is not present, return the original email
    //     return $value;
    // }

    public function agreement(): HasOne
    {
        return $this->hasOne(UserAgreementHistory::class, 'user_id', 'id');
    }

    /**
     * Get the user's theme preferences.
     */
    public function themePreferences(): HasMany
    {
        return $this->hasMany(UserThemePreference::class);
    }

    /**
     * Get the user's active theme preference.
     */
    public function activeThemePreference(): HasOne
    {
        return $this->hasOne(UserThemePreference::class)->where('is_active', true);
    }

    /**
     * Get the user's current theme name.
     */
    public function getCurrentTheme(): string
    {
        $activeTheme = $this->activeThemePreference;

        return $activeTheme ? $activeTheme->theme_name : 'default';
    }

    /**
     * Set the user's active theme.
     */
    public function setActiveTheme(string $themeName, ?array $themeConfig = null): UserThemePreference
    {
        return UserThemePreference::setActiveThemeForUser($this->id, $themeName, $themeConfig);
    }

    // terminate user methods
    public function terminateHistories(): HasMany
    {
        return $this->hasMany(UserTerminateHistory::class);
    }

    public function isTerminatedOn($date)
    {
        $terminationEntry = $this->terminateHistories()
            ->whereDate('terminate_effective_date', '<=', $date)
            ->orderBy('terminate_effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $terminationEntry ? $terminationEntry->is_terminate == 1 : false;
    }

    public function terminateHistoryOn($date)
    {
        $terminationEntry = $this->terminateHistories()
            ->whereDate('terminate_effective_date', '<=', $date)
            ->orderBy('terminate_effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $terminationEntry;
    }

    public function isTodayTerminated()
    {
        return $this->isTerminatedOn(Carbon::today());
    }

    public function isTerminated()
    {
        $latestTermination = $this->terminateHistories()
            ->orderBy('terminate_effective_date', 'desc')
            ->first();

        return $latestTermination ? $latestTermination->is_terminate == 1 : false;
    }

    public function dismissHistories(): HasMany
    {
        return $this->hasMany(UserDismissHistory::class);
    }

    public function dismissHistoryOn($date)
    {
        $dismissEntry = $this->dismissHistories()
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        return $dismissEntry;
    }

    public function scopeTerminated(Builder $query)
    {
        return $query->whereHas('terminateHistories', function ($q) {
            $q->where('is_terminate', 1)
                ->whereDate('effective_date', '<=', Carbon::today());
        });
    }

    public function scopeNotTerminated(Builder $query)
    {
        return $query->whereDoesntHave('terminateHistories', function ($q) {
            $q->where('is_terminate', 1)
                ->whereDate('effective_date', '<=', Carbon::today());
        });
    }

    public function payrollSubPosition()
    {
        return $this->hasOne(Positions::class, 'id', 'sub_position_id');
    }

    public function positionPayFrequency()
    {
        return $this->hasOne(PositionPayFrequency::class, 'position_id', 'sub_position_id');
    }

    /**
     * Get specific flexible ID by type.
     */
    public function getFlexibleId($type)
    {
        return $this->flexibleIds()->where('flexible_id_type', $type)->first();
    }

    /**
     * Get Flexi ID 1 value.
     */
    public function getFlexiId1Attribute()
    {
        $flexibleId = $this->getFlexibleId('flexi_id_1');

        return $flexibleId ? $flexibleId->flexible_id_value : null;
    }

    /**
     * Get Flexi ID 2 value.
     */
    public function getFlexiId2Attribute()
    {
        $flexibleId = $this->getFlexibleId('flexi_id_2');

        return $flexibleId ? $flexibleId->flexible_id_value : null;
    }

    /**
     * Get Flexi ID 3 value.
     */
    public function getFlexiId3Attribute()
    {
        $flexibleId = $this->getFlexibleId('flexi_id_3');

        return $flexibleId ? $flexibleId->flexible_id_value : null;
    }

    /**
     * Static method to find user by any flexible ID with priority order.
     * Priority: Flexi ID 1 → Flexi ID 2 → Flexi ID 3 → Primary Email → Work Email → Additional Emails
     */
    public static function findByFlexibleIdOrEmail($identifier)
    {
        // Priority 1-3: Check flexible IDs (case-insensitive, active records only)
        $user = self::whereHas('flexibleIds', function ($q) use ($identifier) {
            $q->whereRaw('LOWER(flexible_id_value) = LOWER(?)', [$identifier])
                ->whereNull('deleted_at'); // Exclude soft-deleted flexible IDs
        })->first();

        if ($user) {
            return $user;
        }

        // Priority 4: Check primary email
        $user = self::where('email', strtolower($identifier))->first();
        if ($user) {
            return $user;
        }

        // Priority 5: Check work email
        $user = self::where('work_email', strtolower($identifier))->first();
        if ($user) {
            return $user;
        }

        // Priority 6: Check additional emails
        $additionalEmail = \App\Models\UsersAdditionalEmail::where('email', strtolower($identifier))->first();
        if ($additionalEmail) {
            return $additionalEmail->user;
        }

        return null;
    }

    /**
     * Set or update a flexible ID for the user.
     */
    public function setFlexibleId($type, $value)
    {
        // Get existing flexible ID of this type
        $existing = $this->flexibleIds()->where('flexible_id_type', $type)->first();

        // Normalize the new value (lowercase, trim)
        $normalizedNewValue = ! empty($value) ? strtolower(trim($value)) : null;

        // If no existing flexible ID
        if (! $existing) {
            // Create new one if value is provided
            if (! empty($normalizedNewValue)) {
                return $this->flexibleIds()->create([
                    'flexible_id_type' => $type,
                    'flexible_id_value' => $normalizedNewValue,
                ]);
            }

            return null;
        }

        // If existing flexible ID found
        $existingValue = $existing->flexible_id_value;

        // If new value is empty, delete existing
        if (empty($normalizedNewValue)) {
            $existing->delete();

            return null;
        }

        // If values are the same, do nothing (prevent unnecessary delete/create)
        if ($existingValue === $normalizedNewValue) {
            return $existing; // No changes needed
        }

        // Values are different, replace existing
        $existing->delete(); // Soft delete old one

        return $this->flexibleIds()->create([
            'flexible_id_type' => $type,
            'flexible_id_value' => $normalizedNewValue,
        ]);
    }

    /**
     * Batch update all flexible IDs for the user (supports shuffling).
     */
    public function setFlexibleIds($flexibleIds)
    {
        $results = [];

        // Process each flexible ID individually to avoid unnecessary operations
        foreach ($flexibleIds as $type => $value) {
            $result = $this->setFlexibleId($type, $value);
            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    // ==========================================
    // Custom Sales Field Relationships
    // ==========================================

    /**
     * Get the commission custom sales field for this user
     */
    public function commissionCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'commission_custom_sales_field_id');
    }

    /**
     * Get the self-gen commission custom sales field for this user
     */
    public function selfGenCommissionCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'self_gen_commission_custom_sales_field_id');
    }

    /**
     * Get the upfront custom sales field for this user
     */
    public function upfrontCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'upfront_custom_sales_field_id');
    }

    /**
     * Get the direct override custom sales field for this user
     */
    public function directCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'direct_custom_sales_field_id');
    }

    /**
     * Get the indirect override custom sales field for this user
     */
    public function indirectCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'indirect_custom_sales_field_id');
    }

    /**
     * Get the office override custom sales field for this user
     */
    public function officeCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'office_custom_sales_field_id');
    }
}
