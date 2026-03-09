<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingEmployees extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'onboarding_employees';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Status change tracking and automation are now handled by OnboardingEmployeesObserver
        // See: app/Observers/OnboardingEmployeesObserver.php
    }

    protected $fillable = [
        'user_id',
        'lead_id',
        'aveyo_hs_id',
        'employee_id',
        'first_name',
        'last_name',
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
        'is_manager',
        'manager_id',
        'self_gen_accounts',
        'self_gen_type',
        'team_id',
        'status_id', // check hiring_status table
        'recruiter_id',
        'additional_recruiter',
        'additional_recruiter_id1',
        'additional_recruiter_id2',
        'commission',
        'commission_type',
        'redline',
        'redline_amount_type',
        'redline_type',
        'upfront_pay_amount',
        'upfront_sale_type',
        'withheld_amount',
        'self_gen_withheld_amount',
        'offer_include_bonus',
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
        'user_offer_letter',
        'document_id',
        'response',
        'sex',
        'image',
        'middle_name',
        'dob',
        'zip_code',
        'work_email',
        'home_address',
        'type',
        'hiring_type',
        'created_at',
        'office_id',
        'self_gen_redline',
        'self_gen_redline_amount_type',
        'self_gen_redline_type',
        'self_gen_commission',
        'self_gen_commission_type',
        'self_gen_upfront_amount',
        'self_gen_upfront_type',
        'withheld_type',
        'self_gen_withheld_type',
        'commission_selfgen',
        'commission_selfgen_type',
        'hired_by_uid',
        'is_background_verificaton',
        'worker_type',
        'pay_type',
        'pay_rate',
        'pay_rate_type',
        'expected_weekly_hours',
        'overtime_rate',
        'pto_hours',
        'unused_pto_expires',
        'hiring_signature',
        'offer_review_uid',
        'experience_level',
        'custom_fields',
        'old_status_id',
        'employee_admin_only_fields',
        'is_new_contract',
        // Custom Sales Field feature
        'commission_custom_sales_field_id',
        'self_gen_commission_custom_sales_field_id',
        'upfront_custom_sales_field_id',
        'direct_custom_sales_field_id',
        'indirect_custom_sales_field_id',
        'office_custom_sales_field_id',
    ];

    // Documents relation
    public function OnboardingEmployeesDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\Documents::class, 'user_id', 'id')->where('is_active', '1')->where('user_id_from', 'onboarding_employees')->where('document_uploaded_type', 'secui_doc_uploaded');
        // ->with('DocumentFileIs');

    }

    // new Documents tbl relation
    // tbl: new_sequi_docs_documents
    public function newOnboardingEmployeesDocuments(): HasMany
    {

        return $this->hasMany(\App\Models\NewSequiDocsDocument::class, 'user_id', 'id')->where('is_active', '1')->where('user_id_from', 'onboarding_employees')->where('is_post_hiring_document', '0');

    }

    // hirde user data
    public function mainUserData(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    // Onboarding employee document status and data
    public static function onboarding_employees_document_status($onboarding_employees_documents)
    {
        $other_doc_data = ['Background Security Check', 'W9 Tax Form'];

        $other_doc_status = [
            'backgroundVerification' => 2,
            'w9' => 2,
            'message' => '0 - Send And Not Signed , 1 - Send And Signed , 2 - Not Send',
        ];

        $is_all_doc_sign = true;
        if ($onboarding_employees_documents != null && count($onboarding_employees_documents) > 0) {
            foreach ($onboarding_employees_documents as $onboarding_employees_documents_key => $onboarding_employees_document_row) {
                $description = $onboarding_employees_document_row['description'];
                $document_response_status = $onboarding_employees_document_row['document_response_status'];
                if ($description == $other_doc_data[0]) {
                    $other_doc_status['backgroundVerification'] = $document_response_status;
                } elseif ($description == $other_doc_data[1]) {
                    $other_doc_status['w9'] = $document_response_status;
                }

                if ($document_response_status != 1) {
                    $is_all_doc_sign = false;
                }
            }
        } else {
            $is_all_doc_sign = false;
        }

        return $response = [
            'is_all_doc_sign' => $is_all_doc_sign,
            'other_doc_status' => $other_doc_status,
            'onboarding_employees_documents' => $onboarding_employees_documents,
        ];

    }

    public static function onboarding_employees_new_document_status($onboarding_employees_new_documents)
    {

        $is_all_new_doc_sign = true;

        if ($onboarding_employees_new_documents != null && count($onboarding_employees_new_documents) > 0) {

            foreach ($onboarding_employees_new_documents as $onboarding_employees_new_documents_key => $onboarding_employees_document_new_row) {

                $description = $onboarding_employees_document_new_row['description'];
                $document_response_status = $onboarding_employees_document_new_row['document_response_status'];
                $signed_status = $onboarding_employees_document_new_row['signed_status'];
                $is_sign_required_for_hire = $onboarding_employees_document_new_row['is_sign_required_for_hire'];
                $document_uploaded_type = $onboarding_employees_document_new_row['document_uploaded_type'];

                // is_sign_required_for_hire
                // signed_status
                // document_response_status == 1 => Accepted

                // Simplified validation: Use document_response_status for ALL document types
                // document_response_status = 1 means document is completed (signed, uploaded, or responded to)
                // This matches the existing system logic that looks for document_response_status = 0 for incomplete documents
                if ($document_response_status != 1 && $is_sign_required_for_hire == 1) {
                    $is_all_new_doc_sign = false;
                }

            }

        } else {
            $is_all_new_doc_sign = false;
        }

        return $response = [
            'is_all_new_doc_sign' => $is_all_new_doc_sign,
            'onboarding_employees_new_documents' => $onboarding_employees_new_documents,
        ];

    }

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function departmentDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'department_id')->select('id', 'name');
    }

    public function statusDetail(): HasOne
    {
        return $this->hasOne(\App\Models\HiringStatus::class, 'id', 'status_id');
    }

    public function teamsDetail(): HasOne
    {
        return $this->hasOne(\App\Models\ManagementTeam::class, 'id', 'team_id');
    }

    public function additionalDetail(): HasMany
    {
        return $this->hasMany(\App\Models\AdditionalRecruiters::class, 'hiring_id', 'id')->with('additionalRecruiterDetail');
    }

    public function recruiter(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'recruiter_id')->select('id', 'first_name', 'last_name', 'recruiter_id', 'mobile_no', 'sub_position_id');
    }

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id');
    }

    public function managerDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'manager_id')->select('id', 'first_name', 'last_name', 'email', 'department_id', 'sub_position_id');
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

    public function OnboardingAdditionalEmails(): HasMany
    {
        return $this->hasMany(\App\Models\OnboardingAdditionalEmails::class, 'onboarding_user_id', 'id')->select('onboarding_user_id', 'email');
    }

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id')->with('State');
    }

    public function Commission(): HasOne
    {
        return $this->hasOne(\App\Models\PositionCommission::class, 'id', 'compensation_plan_id');
    }

    public function additionalLocation(): HasOne
    {
        return $this->hasOne(\App\Models\OnboardingEmployeeLocations::class, 'user_id', 'id')->with('state', 'city');
    }

    public function additionalLocations(): HasMany
    {
        return $this->hasMany(\App\Models\OnboardingEmployeeLocations::class, 'user_id', 'id')->with('state', 'city');
    }

    // public function additionalDetail
    public function subpositionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id');
    }

    public function hiring_status(): HasOne
    {
        return $this->hasOne(\App\Models\HiringStatus::class, 'id', 'status_id');
    }

    public function onboarding_user_resend_offer_status(): HasOne
    {
        return $this->hasOne(\App\Models\NewSequiDocsDocument::class, 'user_id', 'id')
            ->where([
                'user_id_from' => 'onboarding_employees',
                'description' => 'Offer Letter',
                'is_active' => 1,
            ])->select('user_id', 'is_document_resend');
    }

    public function wage(): HasOne
    {
        return $this->hasOne(\App\Models\OnboardingEmployeeWages::class, 'user_id', 'id');
    }

    public function positionWages(): HasOne
    {
        return $this->hasOne(\App\Models\PositionWage::class, 'position_id', 'sub_position_id');
    }

    public static function getProductIds($id)
    {
        // Get distinct product_ids from both tables

        $commisionProductIds = OnboardingUserRedline::where('user_id', $id)->distinct()->pluck('product_id')->toArray();
        $overrideProductIds = OnboardingEmployeeOverride::where('user_id', $id)->distinct()->pluck('product_id')->toArray();
        $upfrontProductIds = OnboardingEmployeeUpfront::where('user_id', $id)->distinct()->pluck('product_id')->toArray();

        // Merge the product_ids and remove duplicates
        $combinedProductIds = array_unique(array_merge($commisionProductIds, $overrideProductIds, $upfrontProductIds));
        $products = Products::select('id', 'name', 'product_id')->wherein('id', $combinedProductIds)->get();

        // Return the list of product_ids
        return $products;
    }

    public function hiredby(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'hired_by_uid');
    }

    /** Offer accept relation with all conditions **/
    public function newSequiDocsOfferAccept(): HasOne
    {
        // is_offer_accept = 1 means it goes in offer accept
        return $this->hasOne(\App\Models\NewSequiDocsDocument::class, 'user_id', 'id')
            ->where('is_active', '1')
            ->where('user_id_from', 'onboarding_employees')
            ->where('is_sign_required_for_hire', '1')
            ->where('is_post_hiring_document', '0')
            ->selectRaw('
            user_id,
            IF(
                SUM(category_id = 1 AND signed_status = 0) > 0 AND COUNT(*) = 1, 
                1,
                IF(
                    SUM(category_id = 1 AND signed_status = 1) > 0 AND COUNT(*) > 1,
                    1,
                    IF(SUM(category_id = 1 AND signed_status = 1) = 1 AND COUNT(*) = 1, 0, 1)
                )
            ) AS is_offer_accept
        ')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('category_id', 1)->whereIn('signed_status', [0, 1]);
                })->orWhere(function ($q) {
                    $q->where('category_id', '!=', 1)->where('signed_status', 0);
                });
            })
            ->groupBy('user_id');
    }

    /** Doc review relation **/
    public function newSequiDocsDocRview(): HasOne
    {
        // is_doc_review = 0 will goes in Doc Review
        return $this->hasOne(\App\Models\NewSequiDocsDocument::class, 'user_id', 'id')
            ->where('is_active', '1')
            ->where('user_id_from', 'onboarding_employees')
            ->where('is_sign_required_for_hire', '1')
            ->where('is_post_hiring_document', '0')
            ->where('signed_status', '0')
            ->selectRaw('COUNT(signed_status) AS is_doc_review, user_id, id')
            ->groupBy('user_id');
    }

    public function additionalRecruiter1(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'additional_recruiter_id1')->select('id', 'first_name', 'last_name', 'recruiter_id', 'mobile_no', 'sub_position_id');
    }

    public function additionalRecruiter2(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'additional_recruiter_id2')->select('id', 'first_name', 'last_name', 'recruiter_id', 'mobile_no', 'sub_position_id');
    }

    // ==========================================
    // Custom Sales Field Relationships
    // ==========================================

    /**
     * Get the commission custom sales field
     */
    public function commissionCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'commission_custom_sales_field_id');
    }

    /**
     * Get the self-gen commission custom sales field
     */
    public function selfGenCommissionCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'self_gen_commission_custom_sales_field_id');
    }

    /**
     * Get the upfront custom sales field
     */
    public function upfrontCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'upfront_custom_sales_field_id');
    }

    /**
     * Get the direct override custom sales field
     */
    public function directCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'direct_custom_sales_field_id');
    }

    /**
     * Get the indirect override custom sales field
     */
    public function indirectCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'indirect_custom_sales_field_id');
    }

    /**
     * Get the office override custom sales field
     */
    public function officeCustomSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'office_custom_sales_field_id');
    }
}
