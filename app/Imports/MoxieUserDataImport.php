<?php

namespace App\Imports;

use App\Jobs\EmploymentPackage\ApplyHistoryOnUsersV2Job;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\ManagementTeamMember;
use App\Models\OnboardingEmployees;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionProduct;
use App\Models\Positions;
use App\Models\TiersPositionOverrides;
use App\Models\User;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDepartmentHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserIsManagerHistory;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UsersAdditionalEmail;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
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

class MoxieUserDataImport implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow, WithStartRow
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
     * @var string Current sheet name being processed
     */
    protected $currentSheetName = '';

    /**
     * @var array List of mandatory fields that must be present in the import
     */
    protected $mandatoryFields = [];

    /**
     * @var array List of mandatory fields that must be present in the import
     */
    protected $authUserId = null;

    /**
     * @var array List of company setting fields that must be present in the import
     */
    protected $companySettingTiers = 0;

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
     * @var int Count of successfully processed rows
     */
    protected $successCount = 0;

    /**
     * @var int Global counter for email sequencing
     */
    protected static $globalEmailCounter = 1;

    /**
     * @var int Global counter for mobile number sequencing
     */
    protected static $globalMobileCounter = 1;

    /**
     * @var int Count of skipped rows
     */
    protected $skippedCount = 0;

    /**
     * @var int Total count of data rows (excluding header and empty rows)
     */
    protected $totalCount = 0;

    /**
     * Constructor to initialize the import class
     *
     * @param  array  $allFields  Array of all possible field names (including mandatory ones)
     */
    public function __construct(array $allFields, array $mandatoryFields, $authUserId)
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
     * Define which row is the heading row
     */
    public function headingRow(): int
    {
        return 1;
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
                    // Insert the processed data into the database
                    // We don't add to successItems here - only after successful insertion
                    $result = $this->insertUserData($data);

                    // Check if insertion was successful
                    if ($result === true) {
                        // Success is now handled within insertUserData
                        $this->successCount++;
                    } elseif (is_array($result) && isset($result['status']) && $result['status'] === false) {
                        // Handle error from insertUserData
                        $this->errors[] = [
                            'sheet' => $this->currentSheetName,
                            'row' => $this->currentRow,
                            'errors' => [isset($result['message']) ? $result['message'] : 'Error creating user'],
                        ];
                        $this->skippedCount++;
                    } else {
                        $this->skippedCount++;
                    }
                } else {
                    $this->skippedCount++;
                }
            }
            // We don't increment skippedCount for completely empty rows
        });
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
     * Process an individual row from the import
     *
     * @param  array|Collection  $row
     */
    protected function processRow($row): ?array
    {
        $this->applyDefaultValidations();

        // Convert row to array if it's a Collection
        $rowArray = $row instanceof Collection ? $row->toArray() : $row;

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

            // Convert Excel date numbers to date strings for specific date fields
            if (in_array($fieldName, ['period_of_agreement_start_date', 'end_date', 'date_to_be_paid', 'offer_expiry_date']) && is_numeric($value)) {
                try {
                    $excelEpoch = new \DateTime('1899-12-30');
                    $days = (int) $value;
                    $interval = new \DateInterval("P{$days}D");
                    $excelEpoch->add($interval);

                    // Format as a standard date string
                    $value = $excelEpoch->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep the original value if conversion fails
                }
            }

            // Store the value in data
            $data[$fieldName] = $value;
        }

        // Perform mandatory field checks
        foreach ($this->mandatoryFields as $field) {
            if (empty($data[$field]) && $data[$field] !== 0 && $data[$field] !== '0') {
                $rowErrors[] = "Field '{$field}' is mandatory but has no value";
            }
        }

        // Extract first and last name from rep_name
        if (isset($data['rep_name']) && ! empty($data['rep_name'])) {
            $nameParts = explode(' ', $data['rep_name'], 2);
            $data['first_name'] = $nameParts[0] ?? '';
            $data['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';

            if (empty($data['first_name'])) {
                $rowErrors[] = "Could not extract first name from rep_name '{$data['rep_name']}'";
            }
        }

        // Apply all custom validations
        $isValid = true;
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
                $isValid = false;
            }
        }

        // If there are errors for this row, add them and return null
        if (! empty($rowErrors)) {
            $this->errors[] = [
                'sheet' => $this->currentSheetName,
                'row' => $this->currentRow,
                'errors' => $rowErrors,
            ];

            return null;
        }

        // Don't track uniqueness here - both email and mobile are tracked in validation functions

        // Add the sheet name to the data
        $data['sheet_name'] = $this->currentSheetName;

        // Return the processed data (don't add to successItems here as it's already done in collection)
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
     * Get simplified data for successful imports (only specific fields)
     */
    public function getSimplifiedSuccessItems(): array
    {
        $simplifiedItems = [];

        foreach ($this->successItems as $item) {
            $simplifiedItems[] = [
                'first_name' => $item['first_name'] ?? '',
                'last_name' => $item['last_name'] ?? '',
                'email' => $item['email'] ?? '',
                'mobile_no' => $item['mobile_no'] ?? '',
                'employee_id' => $item['employee_id'] ?? '',
            ];
        }

        return $simplifiedItems;
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
     * Inserts processed user data into the database
     *
     * @param  array  $userData  User data to insert
     * @return bool|array Returns true on success, an array with error details on failure
     */
    public function insertUserData(array $userData)
    {
        // Keep checking until we find an unused email
        while (
            User::where('email', $userData['email'])->exists() ||
            UsersAdditionalEmail::where('email', $userData['email'])->exists() ||
            Lead::where('email', $userData['email'])->exists() ||
            OnboardingEmployees::where('email', $userData['email'])->exists()
        ) {
            self::$globalEmailCounter++;
            $formattedCounter = str_pad(self::$globalEmailCounter, 2, '0', STR_PAD_LEFT);
            $userData['email'] = 'superadmin'.$formattedCounter.'@sequifi.com';
        }

        if (empty($userData['email'])) {
            self::$globalEmailCounter++;
            $formattedCounter = str_pad(self::$globalEmailCounter, 2, '0', STR_PAD_LEFT);
            $userData['email'] = 'superadmin'.$formattedCounter.'@sequifi.com';
        }

        // Handle work_email with a similar pattern if it exists/is needed
        if (isset($userData['work_email']) || ! empty($userData['work_email'])) {
            // Keep checking until we find an unused work email
            while (
                User::where('email', $userData['work_email'])->exists() ||
                User::where('work_email', $userData['work_email'])->exists() ||
                UsersAdditionalEmail::where('email', $userData['work_email'])->exists() ||
                Lead::where('email', $userData['work_email'])->exists() ||
                OnboardingEmployees::where('email', $userData['work_email'])->exists()
            ) {
                self::$globalEmailCounter++;
                $formattedCounter = str_pad(self::$globalEmailCounter, 2, '0', STR_PAD_LEFT);
                $userData['work_email'] = 'workadmin'.$formattedCounter.'@sequifi.com';
            }
        }

        // Keep checking until we find an unused mobile number
        while (
            User::where('mobile_no', $userData['mobile_no'])->exists() ||
            Lead::where('mobile_no', $userData['mobile_no'])->exists() ||
            OnboardingEmployees::where('mobile_no', $userData['mobile_no'])->exists()
        ) {
            self::$globalMobileCounter++;
            $userData['mobile_no'] = '123456'.str_pad(self::$globalMobileCounter, 4, '0', STR_PAD_LEFT);
        }

        if (empty($userData['mobile_no'])) {
            self::$globalMobileCounter++;
            $userData['mobile_no'] = '123456'.str_pad(self::$globalMobileCounter, 4, '0', STR_PAD_LEFT);
        }

        try {
            DB::beginTransaction();

            $authUserId = $this->authUserId;
            $position = Positions::where('position_name', $userData['sub_position_id'])->first();
            if (! $position) {
                DB::rollBack();

                return ['status' => false, 'message' => 'Position not found!!'];
            }
            $departmentId = $position->department_id;
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

            $user = User::create([
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email'],
                'work_email' => $userData['work_email'],
                'password' => Hash::make('Moxie#123'),
                'mobile_no' => $userData['mobile_no'],
                'employee_id' => $userData['employee_id'],
                'state_id' => $stateId,
                'office_id' => $officeId,
                'department_id' => $departmentId,
                'is_manager' => $isManager,
                'team_id' => $teamId,
                'status_id' => '1',
                'position_id' => $positionId,
                'sub_position_id' => $subPositionId,
                'group_id' => $groupId,
                'probation_period' => $userData['probation_period'],
                'offer_include_bonus' => $userData['hiring_bonus_amount'] ? 1 : 0,
                'hiring_bonus_amount' => $userData['hiring_bonus_amount'],
                'date_to_be_paid' => $userData['date_to_be_paid'],
                'period_of_agreement_start_date' => $userData['period_of_agreement_start_date'],
                'end_date' => $userData['end_date'],
                'offer_expiry_date' => $userData['offer_expiry_date'],
                'onboardProcess' => 1,
                'first_time_changed_password' => 1,
                'is_agreement_accepted' => '1',
            ]);

            $userId = $user->id;
            $effectiveDate = $user->period_of_agreement_start_date;

            if ($userData['work_email']) {
                UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $userData['work_email']]);
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
                    'commission' => $userData['commission'],
                    'commission_type' => $userData['commission_type'],
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

            foreach ($upFronts as $upFront) {
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
                    'upfront_pay_amount' => $userData['upfront_pay_amount'],
                    'upfront_sale_type' => $userData['upfront_sale_type'],
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
                    'overrides_amount' => $userData['direct_overrides_amount'],
                    'overrides_type' => $userData['direct_overrides_type'],
                    'overrides_tiers_id' => $override->direct_tiers_id,
                ];
                $indirectOverride[$override->product_id] = [
                    'overrides_id' => $override->id,
                    'overrides_amount' => $userData['indirect_overrides_amount'],
                    'overrides_type' => $userData['indirect_overrides_type'],
                    'overrides_tiers_id' => $override->indirect_tiers_id,
                ];
                $officeOverride[$override->product_id] = [
                    'overrides_id' => $override->id,
                    'overrides_amount' => $userData['office_overrides_amount'],
                    'overrides_type' => $userData['office_overrides_type'],
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

            // Only add to successItems after successful database insertion
            // This ensures we only count users who are fully inserted into the database
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
        // Email validations - removed validation requirements since we'll generate fake emails
        if (in_array('email', $this->allFields)) {
            // No validations needed for email since we'll replace it
            $this->addFieldValidation('email_format', 'email', function ($value) {
                return true; // Always valid, we'll replace it
            }, '');

            // No uniqueness check needed
            $this->addFieldValidation('email_unique_import', 'email', function ($value) {
                return true; // Always valid, we'll replace it
            }, '');
        }

        // Work Email validation - removed validation requirements
        if (in_array('work_email', $this->allFields)) {
            // No format validation needed
            $this->addFieldValidation('work_email_format', 'work_email', function ($value) {
                return true; // Always valid, we'll replace it
            }, '');

            // No uniqueness check needed
            $this->addFieldValidation('work_email_unique_import', 'work_email', function ($value) {
                return true; // Always valid, we'll replace it
            }, '');
        }

        // Mobile number validation - removed validation requirements
        if (in_array('mobile_no', $this->allFields)) {
            // No uniqueness check needed
            $this->addFieldValidation('mobile_unique_import', 'mobile_no', function ($value) {
                return true; // Always valid, we'll replace it
            }, '');
        }

        // Office ID validation
        if (in_array('office_id', $this->allFields)) {
            $this->addFieldValidation('office_exists', 'office_id', function ($value) {
                if (empty($value)) {
                    return true;
                }

                // Check if office exists by name
                return Locations::where('office_name', $value)->exists();
            }, 'Office with name "{value}" does not exist');
        }

        // Sub Position validation
        if (in_array('sub_position_id', $this->allFields)) {
            $this->addFieldValidation('sub_position_exists', 'sub_position_id', function ($value) {
                if (empty($value)) {
                    return true;
                }

                // Check if sub position exists by name using the database directly
                // since we couldn't find a direct SubPosition model
                return Positions::where('position_name', $value)->exists();
            }, 'Sub Position with name "{value}" does not exist');
        }

        // Manager ID and is_manager validation
        if (in_array('manager_id', $this->allFields) && in_array('is_manager', $this->allFields)) {
            $this->addFieldValidation('manager_required', 'manager_id', function ($value, $data) {
                $isManager = $data['is_manager'] ?? null;
                if ($isManager == 0 && empty($value)) {
                    return false;
                }

                return true;
            }, 'Manager ID is required when is_manager is 0');
        }

        // Date validation
        if (in_array('period_of_agreement_start_date', $this->allFields)) {
            $this->addFieldValidation('valid_start_date', 'period_of_agreement_start_date', function ($value) {
                if (empty($value)) {
                    return true;
                }

                // Handle Excel numeric date format (serial number)
                if (is_numeric($value)) {
                    // Convert Excel date to PHP date
                    // Excel dates are days since 1900-01-01 (with leap year bug)
                    try {
                        // Adjust for Excel's leap year bug (1900 is not a leap year, but Excel thinks it is)
                        $excelEpoch = new \DateTime('1899-12-30');
                        $days = (int) $value;

                        // Add the number of days to the Excel epoch
                        $interval = new \DateInterval("P{$days}D");
                        $excelEpoch->add($interval);

                        // Store the converted date back in the proper format
                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                }

                // Handle string date format
                try {
                    $date = \DateTime::createFromFormat('Y-m-d', $value);

                    return $date && $date->format('Y-m-d') === $value;
                } catch (\Exception $e) {
                    return false;
                }
            }, 'Period agreement start date "{value}" is not a valid date (unable to parse)');
        }

        // Commission type validations
        $commissionFields = [
            'commission_type',
            'upfront_sale_type',
            'direct_overrides_type',
            'indirect_overrides_type',
            'office_overrides_type',
        ];

        foreach ($commissionFields as $field) {
            if (in_array($field, $this->allFields)) {
                $this->addFieldValidation($field.'_valid', $field, function ($value) {
                    if (empty($value)) {
                        return true;
                    }
                    $value = strtolower($value);

                    return in_array($value, ['percent', 'per sale']);
                }, $field.' "{value}" must be either "percent" or "per sale"');
            }
        }
    }
}
