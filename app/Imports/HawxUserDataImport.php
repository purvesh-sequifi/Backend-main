<?php

namespace App\Imports;

use App\Jobs\EmploymentPackage\ApplyHistoryOnUsersV2Job;
use App\Models\AdditionalInfoForEmployeeToGetStarted;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\EmployeePersonalDetail;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\ManagementTeamMember;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionsDeductionLimit;
use App\Models\TiersPositionOverrides;
use App\Models\User;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDeductionHistory;
use App\Models\UserDepartmentHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UsersAdditionalEmail;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserWithheldHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;

class HawxUserDataImport implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow, WithStartRow
{
    /**
     * @var array Stores all errors encountered during import
     */
    protected $errors = [];

    /**
     * @var array Stores all successfully processed rows
     */
    protected $successItems = [];

    /**
     * @var array List of mandatory fields that must be present in the import
     */
    protected $mandatoryFields = [];

    /**
     * @var array All field names that can be present in the import (both mandatory and optional)
     */
    protected $allFields = [];

    /**
     * @var array Mapping of field names to their column indexes
     */
    protected $fieldMappings = [];

    /**
     * @var int Current row being processed
     */
    protected $currentRow = 0;

    /**
     * @var array Field validations with custom validation functions
     */
    protected $validations = [];

    /**
     * @var int Count of successfully processed rows
     */
    protected $successCount = 0;

    /**
     * @var int Count of skipped rows
     */
    protected $skippedCount = 0;

    /**
     * @var int Total count of data rows (excluding header and empty rows)
     */
    protected $totalCount = 0;

    /**
     * @var string Current sheet name being processed
     */
    protected $currentSheetName = 'Default';

    /**
     * @var array Store already seen mobile numbers to check for duplicates within the import
     */
    protected $seenMobileNumbers = [];

    /**
     * @var array Array of work emails seen in this import batch for uniqueness checking
     */
    protected $seenWorkEmails = [];

    /**
     * @var array Array of emails seen in this import batch for uniqueness checking
     */
    protected $seenEmails = [];

    /**
     * @var int Global counter for email sequencing
     */
    protected static $globalEmailCounter = 1;

    /**
     * @var int Global counter for mobile number sequencing
     */
    protected static $globalMobileCounter = 1;

    /**
     * @var int User ID of the authenticated user performing the import
     */
    protected $authUserId;

    /**
     * @var mixed Company tier settings
     */
    protected $companySettingTiers;

    /**
     * Constructor to initialize the import class
     *
     * @param  array  $allFields  Array of all possible field names (including mandatory ones)
     * @param  array  $mandatoryFields  Array of mandatory field names
     * @param  int  $authUserId  User ID of the authenticated user performing the import
     */
    public function __construct(array $allFields = [], array $mandatoryFields = [], ?int $authUserId = null)
    {
        $this->allFields = $allFields;
        $this->mandatoryFields = $mandatoryFields;
        $this->authUserId = $authUserId;
        $this->companySettingTiers = CompanySetting::where('type', 'tier')->first()?->status;
    }

    /**
     * Start processing from row 1 (which contains column headers)
     */
    public function startRow(): int
    {
        return 1;
    }

    /**
     * Define which row is the heading row
     */
    public function headingRow(): int
    {
        return 1;
    }

    /**
     * Register events for the import
     */
    public function registerEvents(): array
    {
        return [
            // Track which sheet is currently being processed
            BeforeSheet::class => function (BeforeSheet $event) {
                // Get the current sheet name
                $this->currentSheetName = $event->getSheet()->getTitle();

                // Reconnect to the database before processing a new sheet
                // This helps prevent issues with long-running imports where the connection might time out
                try {
                    DB::disconnect();
                    DB::reconnect();
                    Log::info('Database reconnected for sheet: '.$this->currentSheetName);
                } catch (\Exception $e) {
                    Log::error('Failed to reconnect database for sheet: '.$this->currentSheetName, [
                        'error' => $e->getMessage(),
                    ]);
                }

                // Do NOT reset tracking arrays - we want to maintain uniqueness across all sheets
                // for both emails and mobile numbers
            },
            AfterSheet::class => function (AfterSheet $event) {
                // This runs after each sheet has been processed

                // Optionally, you could also reconnect after each sheet is done
                // to ensure fresh connections between sheets
                DB::disconnect();
                DB::reconnect();
            },
        ];
    }

    /**
     * Process the imported collection
     */
    public function collection(Collection $collection)
    {
        // Skip if collection is empty
        if ($collection->isEmpty()) {
            $this->errors[] = 'Import file appears to be empty';

            return;
        }

        // First row contains headers
        $headers = $collection->first();

        // Map column names to their indexes
        if (! $this->mapHeaders($headers)) {
            return; // Stop if headers couldn't be mapped correctly
        }

        // Skip the header row and process each data row
        $dataRowIndex = 1; // Start with row 2 (1-based indexing, after header)
        $collection->skip(1)->each(function ($row) use (&$dataRowIndex) {
            // Convert to array if it's a collection
            $rowArray = $row instanceof Collection ? $row->toArray() : $row;

            // Check if the row has any data before processing
            $hasData = false;
            foreach ($rowArray as $value) {
                if (is_string($value)) {
                    $value = trim($value);
                }
                if (! empty($value) || $value === 0 || $value === '0') {
                    $hasData = true;
                    break;
                }
            }

            // Only process rows that have data
            if ($hasData) {
                $dataRowIndex++;
                $this->currentRow = $dataRowIndex;
                $this->totalCount++;

                $data = $this->processRow($row);
                if (! empty($data)) {
                    try {
                        // Insert the processed data into the database
                        $result = $this->insertUserData($data);

                        // Only increment success count if insertion was successful
                        if ($result === true) {
                            $this->successCount++;
                        } else {
                            // If insertion failed but returned a result array with an error message
                            $this->skippedCount++;
                            if (is_array($result) && isset($result['message'])) {
                                $this->errors[] = [
                                    'sheet' => $this->currentSheetName,
                                    'row' => $this->currentRow,
                                    'errors' => [$result['message']],
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        // Handle any exceptions during insertion
                        $this->skippedCount++;
                        $this->errors[] = [
                            'sheet' => $this->currentSheetName,
                            'row' => $this->currentRow,
                            'errors' => ['Exception: '.$e->getMessage()],
                        ];

                        Log::error('Error inserting user data', [
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'data' => $data,
                        ]);
                    }
                } else {
                    $this->skippedCount++;
                }
            }
            // We don't increment skippedCount for completely empty rows
        });
    }

    /**
     * Inserts processed user data into the database
     *
     * @param  array  $userData  User data to insert
     * @return bool|array Returns true on success, an array with error details on failure
     */
    public function insertUserData(array $userData)
    {
        // Validate primary email uniqueness
        if (! empty($userData['email'])) {
            if (
                User::where('email', $userData['email'])->exists()
            ) {
                return ['status' => false, 'message' => "Email '{$userData['email']}' is already in use"];
            }
        } else {
            return ['status' => false, 'message' => 'Email is required'];
        }

        // Validate work email if provided
        if (! empty($userData['work_email'])) {
            if (
                User::where('email', $userData['work_email'])->exists()
            ) {
                return ['status' => false, 'message' => "Work email '{$userData['work_email']}' is already in use"];
            }
        }

        // Validate additional emails if provided (1-5)
        for ($i = 1; $i <= 5; $i++) {
            $additionalEmailKey = 'additional_email_'.$i;

            if (! empty($userData[$additionalEmailKey])) {
                if (
                    User::where('email', $userData[$additionalEmailKey])->exists()
                ) {
                    return ['status' => false, 'message' => "Additional email '{$userData[$additionalEmailKey]}' is already in use"];
                }
            }
        }

        // Validate mobile number uniqueness
        if (! empty($userData['mobile_no'])) {
            if (
                User::where('mobile_no', $userData['mobile_no'])->exists()
            ) {
                return ['status' => false, 'message' => "Mobile number '{$userData['mobile_no']}' is already in use"];
            }
        } else {
            return ['status' => false, 'message' => 'Mobile number is required'];
        }

        try {
            DB::beginTransaction();

            $authUserId = $this->authUserId;
            $departmentId = null;
            $department = Department::where('name', $userData['department_id'])->first();
            if (! $department) {
                DB::rollBack();

                return ['status' => false, 'message' => 'Department not found!!'];
            }
            $departmentId = $department->id;
            $position = Positions::where(['position_name' => $userData['position_id'], 'department_id' => $departmentId])->first();
            if (! $position) {
                DB::rollBack();

                return ['status' => false, 'message' => 'Position not found!!'];
            }
            $positionId = 2; // SPECIFICALLY FOR PEST COMPANY TYPE
            $subPositionId = $position->id;
            $groupId = $position->group_id;

            $office = Locations::where('office_name', $userData['office_id'])->first();
            if (! $office) {
                DB::rollBack();

                return ['status' => false, 'message' => 'Office not found!!'];
            }
            $officeId = $office->id;
            $stateId = $office->state_id;
            $isManager = $userData['is_manager'] == 1 ? 1 : 0;
            $teamId = null;
            $team = ManagementTeam::where('team_name', $userData['team_id'])->first();
            if ($team) {
                $teamId = $team->id;
            }

            if (strtolower($userData['gender']) == 'male') {
                $sex = 'male';
            } elseif (strtolower($userData['gender']) == 'female') {
                $sex = 'female';
            } else {
                $sex = null;
            }

            $recruiterId = null;
            if ($userData['recruiter_id']) {
                $recruiter = User::whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) = ?", [$userData['recruiter_id']])->first();
                if ($recruiter) {
                    $recruiterId = $recruiter->id;
                } else {
                    $recruiter = User::whereRaw("CONCAT_WS(' ', first_name, last_name) = ?", [$userData['recruiter_id']])->first();
                    if ($recruiter) {
                        $recruiterId = $recruiter->id;
                    }
                }
            }

            $additionalRecruiter1Id = null;
            if ($userData['additional_recruiter_1_employee_id']) {
                $recruiter = User::whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) = ?", [$userData['additional_recruiter_1_employee_id']])->first();
                if ($recruiter) {
                    $additionalRecruiter1Id = $recruiter->id;
                } else {
                    $recruiter = User::whereRaw("CONCAT_WS(' ', first_name, last_name) = ?", [$userData['additional_recruiter_1_employee_id']])->first();
                    if ($recruiter) {
                        $additionalRecruiter1Id = $recruiter->id;
                    }
                }
            }

            $additionalRecruiter2Id = null;
            if ($userData['additional_recruiter_2_employee_id']) {
                $recruiter = User::whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) = ?", [$userData['additional_recruiter_2_employee_id']])->first();
                if ($recruiter) {
                    $additionalRecruiter2Id = $recruiter->id;
                } else {
                    $recruiter = User::whereRaw("CONCAT_WS(' ', first_name, last_name) = ?", [$userData['additional_recruiter_2_employee_id']])->first();
                    if ($recruiter) {
                        $additionalRecruiter2Id = $recruiter->id;
                    }
                }
            }

            $additionalInfo = [];
            $additionalInfoForEmployeeToGetStarted = AdditionalInfoForEmployeeToGetStarted::where('is_deleted', 0)->get();
            foreach ($additionalInfoForEmployeeToGetStarted as $additionalInfoGetStarted) {
                $additionalInfo[] = [
                    'id' => $additionalInfoGetStarted->id,
                    'configuration_id' => $additionalInfoGetStarted->configuration_id,
                    'field_name' => $additionalInfoGetStarted->field_name,
                    'field_type' => $additionalInfoGetStarted->field_type,
                    'dropdown' => $additionalInfoGetStarted->dropdown,
                    'field_required' => $additionalInfoGetStarted->field_required,
                    'attribute_option' => $additionalInfoGetStarted->attribute_option,
                    'is_deleted' => $additionalInfoGetStarted->is_deleted,
                    'value' => $userData['additional_info_for_employee_to_get_started_'.$additionalInfoGetStarted->id] ?? null,
                ];
            }

            $personalInfo = [];
            $employeePersonalDetails = EmployeePersonalDetail::where('is_deleted', 0)->get();
            foreach ($employeePersonalDetails as $employeePersonalDetail) {
                $personalInfo[] = [
                    'id' => $employeePersonalDetail->id,
                    'configuration_id' => $employeePersonalDetail->configuration_id,
                    'field_name' => $employeePersonalDetail->field_name,
                    'field_type' => $employeePersonalDetail->field_type,
                    'dropdown' => $employeePersonalDetail->dropdown,
                    'field_required' => $employeePersonalDetail->field_required,
                    'attribute_option' => $employeePersonalDetail->attribute_option,
                    'height_value' => $employeePersonalDetail->height_value,
                    'is_deleted' => $employeePersonalDetail->is_deleted,
                    'value' => $userData['employee_personal_detail_'.$employeePersonalDetail->id] ?? null,
                ];
            }

            $workerType = '1099';
            if (config('app.domain_name') == 'hawxw2') {
                $workerType = 'w2';
            }

            $user = User::create([
                'first_name' => $userData['first_name'],
                'middle_name' => $userData['middle_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email'],
                'mobile_no' => $userData['mobile_no'],
                'employee_id' => $userData['employee_id'] ?? null,
                'sex' => $sex,
                'recruiter_id' => $recruiterId,
                'dob' => $userData['date_of_birth'] ?? null,
                'password' => Hash::make($userData['password']),
                'work_email' => $userData['work_email'] ?? null,
                'okta_external_id' => $userData['external_user_id'] ?? null,
                'everee_workerId' => $userData['everee_worker_id'] ?? null,
                'home_address' => $userData['home_address'] ?? null,
                'home_address_line_1' => $userData['home_address_line_1'] ?? null,
                'home_address_line_2' => $userData['home_address_line_2'] ?? null,
                'home_address_city' => $userData['home_address_city'] ?? null,
                'home_address_state' => $userData['home_address_state'] ?? null,
                'home_address_zip' => $userData['home_address_zip'] ?? null,
                'department_id' => $departmentId ?? null,
                'position_id' => $positionId,
                'sub_position_id' => $subPositionId,
                'state_id' => $stateId,
                'office_id' => $officeId,
                'experience_level' => $userData['direct_experience'] ?? null,
                'team_id' => $teamId,
                'manager_id' => $userData['manager_employee_id'] ?? null,
                'additional_recruiter_id1' => $additionalRecruiter1Id ?? null,
                'additional_recruiter_id2' => $additionalRecruiter2Id ?? null,
                'is_manager' => $isManager,
                'entity_type' => $userData['entity_type'] ?? null,
                'social_sequrity_no' => $userData['social_security_no'] ?? null,
                'business_name' => $userData['business_name'] ?? null,
                'business_type' => $userData['business_type'] ?? null,
                'business_ein' => $userData['business_ein'] ?? null,
                'account_name' => $userData['account_name'] ?? null,
                'name_of_bank' => $userData['bank_name'] ?? null,
                'routing_no' => $userData['routing_number'] ?? null,
                'account_no' => $userData['account_number'] ?? null,
                'confirm_account_no' => $userData['account_number'] ?? null,
                'type_of_account' => $userData['account_type'] ?? null,
                'tax_information' => $userData['tax_information'] ?? null,
                'period_of_agreement_start_date' => gmdate('Y-m-d', ((int) $userData['agreement_start_date'] - 25569) * 86400) ?? null,
                'end_date' => gmdate('Y-m-d', ((int) $userData['agreement_end_date'] - 25569) * 86400) ?? null,
                'hiring_bonus_amount' => $userData['hiring_bonus_amount'] ?? null,
                'date_to_be_paid' => $userData['bonus_date_to_be_paid'] ?? null,
                'offer_expiry_date' => $userData['offer_expiry_date'] ?? null,
                'probation_period' => $userData['probation_period'] ?? null,
                'emergency_contact_name' => $userData['emergency_contact_name'] ?? null,
                'emergency_phone' => $userData['emergency_phone'] ?? null,
                'emergency_contact_relationship' => $userData['emergency_contact_relationship'] ?? null,
                'additional_info_for_employee_to_get_started' => json_encode($additionalInfo),
                'employee_personal_detail' => json_encode($personalInfo),
                'group_id' => $groupId,
                'status_id' => '1',
                'onboardProcess' => 1,
                'first_time_changed_password' => 1,
                'is_agreement_accepted' => '1',
                'worker_type' => $workerType,
            ]);

            $userId = $user->id;
            $effectiveDate = $user->period_of_agreement_start_date;

            if (isset($userData['additional_email_1']) && $userData['additional_email_1']) {
                UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $userData['additional_email_1']]);
            }

            if (isset($userData['additional_email_2']) && $userData['additional_email_2']) {
                UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $userData['additional_email_2']]);
            }

            if (isset($userData['additional_email_3']) && $userData['additional_email_3']) {
                UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $userData['additional_email_3']]);
            }

            if (isset($userData['additional_email_4']) && $userData['additional_email_4']) {
                UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $userData['additional_email_4']]);
            }

            if (isset($userData['additional_email_5']) && $userData['additional_email_5']) {
                UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $userData['additional_email_5']]);
            }

            if (! empty($teamId) && $team) {
                ManagementTeamMember::create([
                    'team_id' => $team->id,
                    'team_lead_id' => $team->team_lead_id,
                    'team_member_id' => $userId,
                ]);
            }

            UserIsManagerHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'effective_date' => $effectiveDate,
                'is_manager' => $isManager,
                'position_id' => $positionId,
                'sub_position_id' => $subPositionId,
            ]);

            UserTransferHistory::create([
                'user_id' => $userId,
                'transfer_effective_date' => $effectiveDate,
                'updater_id' => $authUserId ?? 0,
                'state_id' => $stateId,
                'office_id' => $officeId,
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'sub_position_id' => $subPositionId,
                'is_manager' => $isManager,
                'manager_id' => null,
                'team_id' => $teamId,
            ]);

            UserDepartmentHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'effective_date' => $effectiveDate,
                'department_id' => $departmentId,
            ]);

            if (! empty($userId) && isset($officeId)) {
                Locations::where('id', $officeId)->update(['archived_at' => null]);
            }

            $positionProduct = PositionProduct::where(['position_id' => $subPositionId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($positionProduct) {
                $products = PositionProduct::where(['position_id' => $subPositionId, 'effective_date' => $positionProduct->effective_date])->get();
            } else {
                $products = PositionProduct::where(['position_id' => $subPositionId])->whereNull('effective_date')->get();
            }
            foreach ($products as $product) {
                UserOrganizationHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'product_id' => $product->product_id,
                    'position_id' => $positionId,
                    'sub_position_id' => $subPositionId,
                    'effective_date' => $effectiveDate,
                    'self_gen_accounts' => 0,
                ]);
            }

            $commission = PositionCommission::where(['position_id' => $subPositionId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission) {
                $commissions = PositionCommission::where(['position_id' => $subPositionId, 'effective_date' => $commission->effective_date])->get();
            } else {
                $commissions = PositionCommission::where(['position_id' => $subPositionId])->whereNull('effective_date')->get();
            }

            foreach ($commissions as $commission) {
                $userCommissionData = UserCommissionHistory::create([
                    'user_id' => $userId,
                    'commission_effective_date' => $effectiveDate,
                    'product_id' => $commission->product_id,
                    'position_id' => $positionId,
                    'core_position_id' => 2,
                    'sub_position_id' => $subPositionId,
                    'updater_id' => $authUserId ?? 0,
                    'self_gen_user' => 0,
                    'commission' => $commission->commission,
                    'commission_type' => $commission->commission_type,
                    'tiers_id' => $commission->tiers_id,
                ]);

                if ($this->companySettingTiers && $commission->tiers_id) {
                    $range = $commission->tiersRange;
                    foreach ($range as $rang) {
                        UserCommissionHistoryTiersRange::create([
                            'user_id' => $userId,
                            'user_commission_history_id' => $userCommissionData->id ?? null,
                            'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                            'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                            'value' => $rang['commission_value'] ?? null,
                            'value_type' => $rang['commission_type'] ?? null,
                        ]);
                    }
                }
            }

            $upfront = PositionCommissionUpfronts::where(['position_id' => $subPositionId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($upfront) {
                $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $subPositionId, 'effective_date' => $upfront->effective_date])->get();
            } else {
                $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $subPositionId])->whereNull('effective_date')->get();
            }

            foreach ($upFronts as $key => $upFront) {
                if ($key == 0) {
                    $upfrontAmount = $userData['upfront_amount'];
                    $upfrontType = $userData['upfront_type'];
                } else {
                    $upfrontAmount = $upFront->upfront_ammount;
                    $upfrontType = $upFront->calculated_by;
                }
                $userUpFrontData = UserUpfrontHistory::create([
                    'user_id' => $userId,
                    'upfront_effective_date' => $effectiveDate,
                    'position_id' => $positionId,
                    'core_position_id' => 2,
                    'product_id' => $upFront->product_id,
                    'milestone_schema_id' => $upFront->milestone_schema_id,
                    'milestone_schema_trigger_id' => $upFront->milestone_schema_trigger_id,
                    'sub_position_id' => $subPositionId,
                    'updater_id' => $authUserId ?? 0,
                    'self_gen_user' => 0,
                    'upfront_pay_amount' => $upfrontAmount,
                    'upfront_sale_type' => $upfrontType,
                    'tiers_id' => $upFront->tiers_id,
                ]);

                if ($this->companySettingTiers && $upFront->tiers_id) {
                    $range = $upFront->tiersRange;
                    foreach ($range as $rang) {
                        UserUpfrontHistoryTiersRange::create([
                            'user_id' => $userId,
                            'user_upfront_history_id' => $userUpFrontData->id ?? null,
                            'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                            'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                            'value' => $rang['upfront_value'] ?? null,
                            'value_type' => $rang['upfront_type'] ?? null,
                        ]);
                    }
                }
            }

            $override = PositionOverride::where(['position_id' => $subPositionId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($override) {
                $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $subPositionId, 'effective_date' => $override->effective_date])->get();
            } else {
                $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $subPositionId])->whereNull('effective_date')->get();
            }

            $finalOverrides = [];
            $directOverride = [];
            $indirectOverride = [];
            $officeOverride = [];
            foreach ($overrides as $override) {
                $finalOverrides[$override->product_id] = $override->product_id;
                $directOverride[$override->product_id] = [
                    'overrides_id' => $override->id,
                    'overrides_amount' => $override->override_ammount,
                    'overrides_type' => $override->type,
                    'overrides_tiers_id' => $override->direct_tiers_id,
                ];
                $indirectOverride[$override->product_id] = [
                    'overrides_id' => $override->id,
                    'overrides_amount' => $override->override_ammount,
                    'overrides_type' => $override->type,
                    'overrides_tiers_id' => $override->indirect_tiers_id,
                ];
                $officeOverride[$override->product_id] = [
                    'overrides_id' => $override->id,
                    'overrides_amount' => $override->override_ammount,
                    'overrides_type' => $override->type,
                    'overrides_tiers_id' => $override->office_overrides_type,
                ];
            }

            foreach ($finalOverrides as $key => $finalOverride) {
                $userOverrideData = UserOverrideHistory::create([
                    'user_id' => $userId,
                    'override_effective_date' => $effectiveDate,
                    'updater_id' => $authUserId ?? 0,
                    'product_id' => $key,
                    'direct_overrides_amount' => @$directOverride[$key]['overrides_amount'],
                    'direct_overrides_type' => @$directOverride[$key]['overrides_type'],
                    'indirect_overrides_amount' => @$indirectOverride[$key]['overrides_amount'],
                    'indirect_overrides_type' => @$indirectOverride[$key]['overrides_type'],
                    'office_overrides_amount' => @$officeOverride[$key]['overrides_amount'],
                    'office_overrides_type' => @$officeOverride[$key]['overrides_type'],
                    'office_stack_overrides_amount' => 0,
                    'direct_tiers_id' => @$directOverride[$key]['overrides_tiers_id'] ?? null,
                    'indirect_tiers_id' => @$indirectOverride[$key]['overrides_tiers_id'] ?? null,
                    'office_tiers_id' => @$officeOverride[$key]['overrides_tiers_id'] ?? null,
                ]);

                if ($this->companySettingTiers && $userOverrideData->direct_tiers_id) {
                    $range = TiersPositionOverrides::where('position_overrides_id', @$directOverride[$key]['overrides_id'])->get();
                    foreach ($range as $rang) {
                        UserDirectOverrideHistoryTiersRange::create([
                            'user_id' => $userId,
                            'user_override_history_id' => $userOverrideData->id ?? null,
                            'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                            'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                            'value' => $rang['override_value'] ?? null,
                            'value_type' => $rang['override_type'] ?? null,
                        ]);
                    }
                }
                if ($this->companySettingTiers && $userOverrideData->indirect_tiers_id) {
                    $range = TiersPositionOverrides::where('position_overrides_id', @$indirectOverride[$key]['overrides_id'])->get();
                    foreach ($range as $rang) {
                        UserIndirectOverrideHistoryTiersRange::create([
                            'user_id' => $userId,
                            'user_override_history_id' => $userOverrideData->id ?? null,
                            'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                            'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                            'value' => $rang['override_value'] ?? null,
                            'value_type' => $rang['override_type'] ?? null,
                        ]);
                    }
                }
                if ($this->companySettingTiers && $userOverrideData->office_tiers_id) {
                    $range = TiersPositionOverrides::where('position_overrides_id', @$officeOverride[$key]['overrides_id'])->get();
                    foreach ($range as $rang) {
                        UserOfficeOverrideHistoryTiersRange::create([
                            'user_id' => $userId,
                            'user_office_override_history_id' => $userOverrideData->id ?? null,
                            'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                            'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                            'value' => $rang['override_value'] ?? null,
                            'value_type' => $rang['override_type'] ?? null,
                        ]);
                    }
                }
            }

            $positionDeductionLimit = PositionsDeductionLimit::where('position_id', $subPositionId)->first();
            $positionDeductions = PositionCommissionDeduction::where('position_id', $subPositionId)->get();
            foreach ($positionDeductions as $positionDeduction) {
                UserDeductionHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'cost_center_id' => $positionDeduction->cost_center_id,
                    'amount_par_paycheque' => $positionDeduction->ammount_par_paycheck,
                    'sub_position_id' => $subPositionId,
                    'limit_value' => isset($positionDeductionLimit->limit_ammount) ? $positionDeductionLimit->limit_ammount : null,
                    'changes_type' => $positionDeduction->changes_type,
                    'changes_field' => $positionDeduction->changes_field,
                    'pay_period_from' => $positionDeduction->pay_period_from,
                    'pay_period_to' => $positionDeduction->pay_period_to,
                    'effective_date' => $effectiveDate,
                ]);
            }

            $settlements = PositionReconciliations::where(['position_id' => $subPositionId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($settlements) {
                $settlements = PositionReconciliations::where(['position_id' => $subPositionId, 'effective_date' => $override->effective_date])->get();
            } else {
                $settlements = PositionReconciliations::where(['position_id' => $subPositionId])->whereNull('effective_date')->get();
            }
            foreach ($settlements as $settlement) {
                UserWithheldHistory::create([
                    'user_id' => $userId,
                    'product_id' => $settlement->product_id,
                    'withheld_effective_date' => $effectiveDate,
                    'updater_id' => $authUserId ?? 0,
                    'position_id' => $positionId,
                    'sub_position_id' => $subPositionId,
                    'withheld_amount' => isset($settlement->commission_withheld) ? $settlement->commission_withheld : 0,
                    'withheld_type' => isset($settlement->commission_type) ? $settlement->commission_type : null,
                ]);
            }

            UserAgreementHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'probation_period' => $user->probation_period,
                'offer_include_bonus' => $user->offer_include_bonus,
                'hiring_bonus_amount' => $user->hiring_bonus_amount,
                'date_to_be_paid' => $user->date_to_be_paid,
                'period_of_agreement' => $user->period_of_agreement_start_date,
                'end_date' => $user->end_date,
                'offer_expiry_date' => $user->offer_expiry_date,
            ]);

            ApplyHistoryOnUsersV2Job::dispatch($userId, $authUserId)->afterCommit();

            DB::commit();

            // Successfully inserted user
            $this->successItems[] = $userData;

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            // Add detailed error information
            $errorMsg = 'Database error: '.$e->getMessage().' '.$e->getLine();

            // Add error to errors array
            $this->errors[] = [
                'sheet' => $this->currentSheetName,
                'row' => $this->currentRow,
                'errors' => [$errorMsg],
            ];

            // Log the error for debugging
            Log::error('User import error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'row_data' => json_encode($userData),
            ]);

            return ['status' => false, 'message' => $errorMsg];
        }
    }

    public function managerDataInsert($userData, $userId, $authUserId, $positionId, $subPositionId, $effectiveDate, $teamId)
    {
        try {
            $managerId = null;
            if ($userData['manager_employee_id']) {
                $manager = User::whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) = ?", [$userData['manager_employee_id']])->first();
                if ($manager) {
                    $managerId = $manager->id;
                } else {
                    $manager = User::whereRaw("CONCAT_WS(' ', first_name, last_name) = ?", [$userData['manager_employee_id']])->first();
                    if ($manager) {
                        $managerId = $manager->id;
                    }
                }
            }

            UserManagerHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'effective_date' => $effectiveDate,
                'manager_id' => $managerId ?? 0,
                'team_id' => $teamId,
                'position_id' => $positionId,
                'sub_position_id' => $subPositionId,
            ]);

            ApplyHistoryOnUsersV2Job::dispatch($userId, $authUserId)->afterCommit();
        } catch (\Exception $e) {
            // Add detailed error information
            $errorMsg = 'Database error: '.$e->getMessage().' '.$e->getLine();

            // Add error to errors array
            $this->errors[] = [
                'sheet' => $this->currentSheetName,
                'row' => $this->currentRow,
                'errors' => [$errorMsg],
            ];

            // Log the error for debugging
            Log::error('User import error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'row_data' => json_encode($userData),
            ]);
        }
    }

    /**
     * Map the headers to their column indexes
     *
     * @param  Collection|array  $headers
     * @return bool True if mapping was successful, false if mandatory fields are missing
     */
    protected function mapHeaders($headers): bool
    {
        // Convert to array if it's a collection
        $headerArray = $headers instanceof Collection ? $headers->toArray() : $headers;

        // Reset field mappings
        $this->fieldMappings = [];

        // Map column names to their indexes
        foreach ($headerArray as $index => $headerName) {
            if (in_array($headerName, $this->allFields)) {
                $this->fieldMappings[$headerName] = $index;
            }
        }

        // Check if all mandatory fields are present
        $missingMandatoryFields = [];
        foreach ($this->mandatoryFields as $field) {
            if (! isset($this->fieldMappings[$field])) {
                $missingMandatoryFields[] = $field;
            }
        }

        // If mandatory fields are missing, add to errors and return false
        if (! empty($missingMandatoryFields)) {
            $this->errors[] = 'Missing mandatory column(s): '.implode(', ', $missingMandatoryFields);

            return false;
        }

        return true;
    }

    /**
     * Process a single row of data
     *
     * @param  Collection|array  $row
     * @return array|null Processed data or null if there were errors
     */
    protected function processRow($row): ?array
    {
        // Apply any custom validations that were added
        $this->applyDefaultValidations();
        // Convert to array if it's a collection
        $rowArray = $row instanceof Collection ? $row->toArray() : $row;

        // Check if the row is empty (all values are null, empty string, or whitespace)
        $hasData = false;
        foreach ($rowArray as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            if (! empty($value) || $value === 0 || $value === '0') {
                $hasData = true;
                break;
            }
        }

        // Skip processing if the row has no data
        if (! $hasData) {
            return null;
        }

        // Prepare data array with null values for all fields
        $data = array_fill_keys($this->allFields, null);
        $rowErrors = [];

        // For each mapped field, extract the value from the correct column
        foreach ($this->fieldMappings as $fieldName => $columnIndex) {
            // Skip fields that don't exist in this row (this shouldn't happen in most cases)
            if (! isset($rowArray[$columnIndex])) {
                continue;
            }

            $value = $rowArray[$columnIndex];

            // Trim string values
            if (is_string($value)) {
                $value = trim($value);
            }

            // Store the value in data before validation
            $data[$fieldName] = $value;

            // We'll apply validations after collecting all field values
        }

        // Check mandatory fields
        foreach ($this->mandatoryFields as $field) {
            if (
                isset($this->fieldMappings[$field]) &&
                (empty($data[$field]) && $data[$field] !== 0 && $data[$field] !== '0')
            ) {
                $rowErrors[] = "Field '{$field}' is mandatory but has no value";
            }
        }

        // Apply all validations
        foreach ($this->validations as $key => $validation) {
            $fieldName = $validation['field'];
            // Always run validation, even for empty fields, to catch required fields
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            $validationResult = call_user_func($validation['function'], $value, $data);
            if ($validationResult !== true) {
                // If validationResult is a string, use it as a custom error message
                if (is_string($validationResult)) {
                    $rowErrors[] = str_replace('{value}', $value ? $value : '(empty)', $validationResult);
                } else {
                    // Otherwise use the default message
                    $rowErrors[] = str_replace('{value}', $value ? $value : '(empty)', $validation['message']);
                }
            }
        }

        // If there are errors for this row, add them and return null
        if (! empty($rowErrors)) {
            $this->errors[] = [
                'row' => $this->currentRow,
                'errors' => $rowErrors,
            ];

            return null;
        }

        // Return the processed data
        return $data;
    }

    /**
     * Get all errors encountered during import
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all successfully processed items
     */
    public function getSuccessItems(): array
    {
        return $this->successItems;
    }

    /**
     * Get the count of successfully processed items
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get the count of skipped items
     */
    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    /**
     * Get the total count of data rows processed (excluding header and empty rows)
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Check if any errors occurred during import
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get the field mappings (columns to indexes)
     */
    public function getFieldMappings(): array
    {
        return $this->fieldMappings;
    }

    /**
     * Add custom validation rule for a specific field
     *
     * @param  string  $key  Unique key for this validation
     * @param  string  $fieldName  Name of the field to validate
     * @param  callable  $validationFunction  Function that takes a value and returns true if valid, false otherwise
     * @param  string  $errorMessage  Error message to display if validation fails
     */
    public function addFieldValidation(string $key, string $fieldName, callable $validationFunction, string $errorMessage): static
    {
        // Store validation info for the field with a unique key
        $this->validations[$key] = [
            'field' => $fieldName,
            'function' => $validationFunction,
            'message' => $errorMessage,
        ];

        return $this;
    }

    /**
     * Apply default validations for common fields
     */
    protected function applyDefaultValidations()
    {
        // Add email format validation if not already added
        if (in_array('email', $this->allFields) && ! isset($this->validations['email_format'])) {
            $this->addFieldValidation('email_format', 'email', function ($value) {
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            }, 'Email "{value}" is not in a valid format');
        }

        // Add email uniqueness validation if not already added
        if (in_array('email', $this->allFields) && ! isset($this->validations['email_unique'])) {
            $this->addFieldValidation('email_unique', 'email', function ($value) {
                return User::where('email', $value)->doesntExist();
            }, 'Email "{value}" already exists in the database');
        }
    }
}
