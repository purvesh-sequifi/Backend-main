<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserPersonalInfoHistory;
use App\Models\UserBankHistory;
use App\Models\UserTaxHistory;
use App\Models\UserEmploymentStatusHistory;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Handle events immediately (not after commit).
     * This ensures history is logged even within manual DB transactions.
     * Required for profile updates which use DB::beginTransaction()/commit().
     */
    public bool $afterCommit = false;

    /**
     * Encrypted fields that should be stored as-is (encrypted)
     */
    protected array $encryptedFields = [
        'social_sequrity_no',
        'business_ein',
        'account_no',
        'routing_no',
        'confirm_account_no',
    ];

    /**
     * Personal info fields to track
     */
    protected array $personalInfoFields = [
        'first_name', 'middle_name', 'last_name',
        'email', 'work_email', 'mobile_no', 'device_token',
        'sex', 'dob', 'image', 'zip_code', 'location',
        'home_address', 'home_address_line_1', 'home_address_line_2',
        'home_address_state', 'home_address_city', 'home_address_zip',
        'home_address_lat', 'home_address_long', 'home_address_timezone',
        'emergency_contact_name', 'emergency_phone', 'emergency_contact_relationship',
        'emergrncy_contact_address', 'emergrncy_contact_zip_code',
        'emergrncy_contact_state', 'emergrncy_contact_city',
        'emergency_address_line_1', 'emergency_address_line_2',
        'emergency_address_lat', 'emergency_address_long', 'emergency_address_timezone',
    ];

    /**
     * Bank info fields to track
     */
    protected array $bankInfoFields = [
        'name_of_bank', 'routing_no', 'account_no',
        'account_name', 'confirm_account_no', 'type_of_account',
    ];

    /**
     * Tax info fields to track
     */
    protected array $taxInfoFields = [
        'social_sequrity_no', 'tax_information',
        'entity_type', 'business_name', 'business_type', 'business_ein',
    ];

    /**
     * Employment status fields to track
     */
    protected array $employmentStatusFields = [
        'terminate', 'contract_ended', 'disable_login',
        'dismiss', 'stop_payroll', 'status_id',
        'rehire', 'action_item_status', 'onboardProcess', 'end_date',
    ];

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->logHistoryOnCreate($user);
        $this->clearUserCache($user->id);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Prevent duplicate logging if observer called multiple times
        static $processingUsers = [];

        $key = $user->id . '_' . time();
        if (isset($processingUsers[$key])) {
            return; // Already processed this user in this second
        }

        $processingUsers[$key] = true;

        $this->logHistoryOnUpdate($user);
        $this->clearUserCache($user->id);

        // Clean up old entries (keep only last 100)
        if (count($processingUsers) > 100) {
            $processingUsers = array_slice($processingUsers, -50, 50, true);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->logHistoryOnDelete($user);
        $this->clearUserCache($user->id);
    }

    /**
     * Log history when user is created
     */
    protected function logHistoryOnCreate(User $user): void
    {
        $changedBy = auth()->id();
        $ipAddress = request()?->ip();
        $userAgent = request()?->userAgent();

        // For create, store initial values in new_values (no old values)
        $personalData = $this->getFieldValues($user, $this->personalInfoFields);
        if (!empty($personalData)) {
            UserPersonalInfoHistory::create([
                'user_id' => $user->id,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'old_values' => null,
                'new_values' => $personalData,
                'changed_fields' => array_keys($personalData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $bankData = $this->getFieldValues($user, $this->bankInfoFields);
        if (!empty($bankData)) {
            UserBankHistory::create([
                'user_id' => $user->id,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'old_values' => null,
                'new_values' => $bankData,
                'changed_fields' => array_keys($bankData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $taxData = $this->getFieldValues($user, $this->taxInfoFields);
        if (!empty($taxData)) {
            UserTaxHistory::create([
                'user_id' => $user->id,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'old_values' => null,
                'new_values' => $taxData,
                'changed_fields' => array_keys($taxData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $statusData = $this->getFieldValues($user, $this->employmentStatusFields);
        if (!empty($statusData)) {
            UserEmploymentStatusHistory::create([
                'user_id' => $user->id,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'old_values' => null,
                'new_values' => $statusData,
                'changed_fields' => array_keys($statusData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }
    }

    /**
     * Log history when user is updated - ONLY CHANGED FIELDS
     */
    protected function logHistoryOnUpdate(User $user): void
    {
        $changedBy = auth()->id();
        $ipAddress = request()?->ip();
        $userAgent = request()?->userAgent();

        // Get only changed fields for each category
        $personalChanges = $this->getChangedFields($user, $this->personalInfoFields);
        if (!empty($personalChanges)) {
            UserPersonalInfoHistory::create([
                'user_id' => $user->id,
                'change_type' => 'update',
                'changed_by' => $changedBy,
                'old_values' => $personalChanges['old'],
                'new_values' => $personalChanges['new'],
                'changed_fields' => $personalChanges['fields'],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $bankChanges = $this->getChangedFields($user, $this->bankInfoFields);
        if (!empty($bankChanges)) {
            UserBankHistory::create([
                'user_id' => $user->id,
                'change_type' => 'update',
                'changed_by' => $changedBy,
                'old_values' => $bankChanges['old'],
                'new_values' => $bankChanges['new'],
                'changed_fields' => $bankChanges['fields'],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $taxChanges = $this->getChangedFields($user, $this->taxInfoFields);
        if (!empty($taxChanges)) {
            UserTaxHistory::create([
                'user_id' => $user->id,
                'change_type' => 'update',
                'changed_by' => $changedBy,
                'old_values' => $taxChanges['old'],
                'new_values' => $taxChanges['new'],
                'changed_fields' => $taxChanges['fields'],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $statusChanges = $this->getChangedFields($user, $this->employmentStatusFields);
        if (!empty($statusChanges)) {
            UserEmploymentStatusHistory::create([
                'user_id' => $user->id,
                'change_type' => 'update',
                'changed_by' => $changedBy,
                'old_values' => $statusChanges['old'],
                'new_values' => $statusChanges['new'],
                'changed_fields' => $statusChanges['fields'],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }
    }

    /**
     * Log history when user is deleted
     */
    protected function logHistoryOnDelete(User $user): void
    {
        $changedBy = auth()->id();
        $ipAddress = request()?->ip();
        $userAgent = request()?->userAgent();

        // On delete, store final values in old_values
        $personalData = $this->getFieldValues($user, $this->personalInfoFields);
        if (!empty($personalData)) {
            UserPersonalInfoHistory::create([
                'user_id' => $user->id,
                'change_type' => 'delete',
                'changed_by' => $changedBy,
                'old_values' => $personalData,
                'new_values' => null,
                'changed_fields' => array_keys($personalData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $bankData = $this->getFieldValues($user, $this->bankInfoFields);
        if (!empty($bankData)) {
            UserBankHistory::create([
                'user_id' => $user->id,
                'change_type' => 'delete',
                'changed_by' => $changedBy,
                'old_values' => $bankData,
                'new_values' => null,
                'changed_fields' => array_keys($bankData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $taxData = $this->getFieldValues($user, $this->taxInfoFields);
        if (!empty($taxData)) {
            UserTaxHistory::create([
                'user_id' => $user->id,
                'change_type' => 'delete',
                'changed_by' => $changedBy,
                'old_values' => $taxData,
                'new_values' => null,
                'changed_fields' => array_keys($taxData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $statusData = $this->getFieldValues($user, $this->employmentStatusFields);
        if (!empty($statusData)) {
            UserEmploymentStatusHistory::create([
                'user_id' => $user->id,
                'change_type' => 'delete',
                'changed_by' => $changedBy,
                'old_values' => $statusData,
                'new_values' => null,
                'changed_fields' => array_keys($statusData),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }
    }

    /**
     * Get field values from user model (with encryption preserved).
     */
    protected function getFieldValues(User $user, array $fields): array
    {
        $data = [];

        foreach ($fields as $field) {
            $value = null;

            // For encrypted fields, get raw value from database (keeps encryption)
            if (in_array($field, $this->encryptedFields)) {
                $value = $user->getRawOriginal($field);
            } else {
                $value = $user->$field ?? null;
            }

            // Only include non-null, non-empty values
            if ($value !== null && $value !== '') {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * Get ONLY changed fields with old and new values.
     * Only logs if values ACTUALLY changed (not just dirty).
     */
    protected function getChangedFields(User $user, array $fieldsList): array
    {
        $changedFields = [];
        $oldValues = [];
        $newValues = [];

        foreach ($fieldsList as $field) {
            if ($user->wasChanged($field)) {
                // Get old and new values
                $oldValue = null;
                $newValue = null;

                if (in_array($field, $this->encryptedFields)) {
                    // For encrypted fields, MUST get raw DB values (both old and new)
                    // Use getRawOriginal to bypass accessors and get actual DB value
                    $originalAttributes = $user->getRawOriginal();
                    $oldValue = $originalAttributes[$field] ?? null;
                    $newValue = $user->getAttributes()[$field] ?? null;
                } else {
                    $oldValue = $user->getOriginal($field);
                    $newValue = $user->$field;
                }

                // ONLY log if values actually different
                if ($oldValue !== $newValue) {
                    $changedFields[] = $field;
                    $oldValues[$field] = $oldValue;
                    $newValues[$field] = $newValue;
                }
            }
        }

        if (empty($changedFields)) {
            return [];
        }

        return [
            'fields' => $changedFields,
            'old' => $oldValues,
            'new' => $newValues,
        ];
    }

    /**
     * Clear all cached data for a user
     */
    protected function clearUserCache(int $userId): void
    {
        Cache::forget("user:data:{$userId}");
        Cache::forget("user:basic_data:{$userId}");
    }
}
