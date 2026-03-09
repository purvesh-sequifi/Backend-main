<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{
    User,
    Integration,
    InterigationTransactionLog,
    UserOrganizationHistory,
    UserIsManagerHistory,
    AdditionalLocations,
    UserAdditionalOfficeOverrideHistory,
    Positions,
    UserRedlines,
    UserCommissionHistory
};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * EspQuickBase Integration Service
 *
 * Handles all interactions with the EspQuickBase API for syncing user data.
 * Follows Laravel 10 best practices with proper dependency injection,
 * type safety, and error handling.
 */
class EspQuickBaseService
{
    /**
     * Integration name in the database
     */
    private const INTEGRATION_NAME = 'EspQuickBase';
    
    /**
     * Rate limiter key prefix
     */
    private const RATE_LIMITER_KEY = 'espquickbase_api';
    
    /**
     * Maximum API requests per minute
     */
    private const MAX_REQUESTS_PER_MINUTE = 60;
    
    /**
     * Maximum retry attempts for failed API calls
     */
    private const MAX_RETRY_ATTEMPTS = 3;
    
    /**
     * Initial retry delay in milliseconds
     */
    private const INITIAL_RETRY_DELAY_MS = 1000;

    /**
     * Get the active EspQuickBase integration configuration
     *
     * @return Integration|null
     */
    private function getIntegration(): ?Integration
    {
        return Integration::where(['name' => self::INTEGRATION_NAME, 'status' => 1])->first();
    }

    /**
     * Send user data to EspQuickBase API
     *
     * @param int $userId - The user ID to sync
     * @param string $type - The sync type (e.g., 'user_profile_update', 'employee_onboarding')
     * @return array - Response array with status, message, and API response
     */
    public function sendUserData(int $userId, string $type = 'user_profile_update'): array
    {
        try {
            $integration = $this->getIntegration();

            if (!$integration) {
                Log::error('EspQuickBase Integration not found');
                return ['status' => false, 'message' => 'Integration not found'];
            }

            $user = User::with('office', 'managerDetail', 'departmentDetail')
                ->where('id', $userId)
                ->first();

            if (!$user) {
                Log::error('User not found for user_id: ' . $userId);
                return ['status' => false, 'message' => 'User not found'];
            }

            // Build the payload
            $fields = $this->buildUserPayload($user);

            // Send the API request
            $url = trim($integration->value);
            $apiResponse = $this->sendApiRequest($url, $fields);

            // Log the transaction
            $this->logTransaction('Push Rep Data ' . $type, $fields, $apiResponse, $url);

            return $apiResponse;

        } catch (\Exception $e) {
            Log::error('Error sending user data to EspQuickBase', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => false,
                'message' => 'Error sending user data to EspQuickBase: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send user data silently (fire and forget)
     * 
     * NOTE: This is NOT truly async - it runs synchronously but doesn't throw exceptions.
     * It's called "silent" because it logs errors without affecting the main flow.
     * 
     * For true async processing, dispatch a queue job instead.
     * 
     * @param int $userId - The user ID to sync
     * @param string $type - The sync type
     * @return void
     */
    public function sendUserDataSilently(int $userId, string $type = 'user_profile_update'): void
    {
        try {
            $this->sendUserData($userId, $type);
        } catch (\Exception $e) {
            Log::error('Silent EspQuickBase sync failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * @deprecated Use sendUserDataSilently() instead. This method name is misleading.
     */
    public function sendUserDataAsync(int $userId, string $type = 'user_profile_update'): void
    {
        $this->sendUserDataSilently($userId, $type);
    }

    /**
     * Build the complete user payload for EspQuickBase API
     *
     * @param User $user - The user model
     * @return array - Formatted payload
     */
    private function buildUserPayload(User $user): array
    {
        $office = $user->office;
        $employeeCompensation = $this->getUserCompensationDetails($user->id);
        $userOrganization = $this->getUserOrganizationDetails($user->id);

        return [
            'BasicInfo' => [
                'UserID' => $user->id,
                'EmployeeID' => $user->employee_id,
                'FirstName' => $user->first_name,
                'LastName' => $user->last_name,
                'PositionName' => $userOrganization['sub_position_name'] ?? null,
                'Office' => $office?->office_name ?? null,
                'OfficeExternalID' => $office?->id ?? null,
                'Email' => $user->email,
                'Phone' => $user->mobile_no
            ],
            'PersonalInfo' => [
                'UserID' => $user->id,
                'EmployeeID' => $user->employee_id,
                'FirstName' => $user->first_name,
                'LastName' => $user->last_name,
                'PersonalEmail' => $user->email,
                'Phone' => $user->mobile_no,
                'DateOfBirth' => $user->dob,
                'Gender' => $user->sex,
                'Address' => $user->home_address,
                'City' => $user->home_address_city,
                'State' => $user->home_address_state,
                'Postal' => $user->home_address_zip
            ],
            'EmploymentPackage' => [
                'UserID' => $user->id,
                'EmployeeID' => $user->employee_id,
                'FirstName' => $user->first_name,
                'LastName' => $user->last_name,
                'IsManager' => isset($userOrganization['is_manager'])
                    ? ($userOrganization['is_manager'] == 1 ? 'YES' : 'NO')
                    : null,
                'PositionName' => $userOrganization['sub_position_name'] ?? null,
                'Active' => true,
                'CompensationDetails' => $employeeCompensation,
                'AdditionalLocations' => $userOrganization['additional_locations'] ?? null
            ],
        ];
    }

    /**
     * Send API request to EspQuickBase using cURL with rate limiting and retry logic
     * 
     * @param string $url - API endpoint URL
     * @param array $fields - Payload data
     * @return array - Response array with status and API response
     */
    private function sendApiRequest(string $url, array $fields): array
    {
        // Check rate limit
        if (!$this->checkRateLimit()) {
            Log::warning('EspQuickBase API rate limit exceeded', [
                'url' => $url
            ]);
            
            return [
                'status' => false,
                'message' => 'Rate limit exceeded',
                'statusCode' => 429,
                'response' => 'Too many requests. Please try again later.'
            ];
        }
        
        $headers = [
            "accept: application/json",
            "content-type: application/json"
        ];
        
        // Retry logic with exponential backoff
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            try {
                $response = curlRequestWithStatusCode($url, json_encode($fields), $headers, 'POST');
                
                // Check if response indicates success
                if ($response['statusCode'] >= 200 && $response['statusCode'] < 300) {
                    return [
                        'status' => true,
                        'message' => 'Success',
                        'statusCode' => $response['statusCode'],
                        'response' => $response['body'],
                        'attempts' => $attempt + 1
                    ];
                }
                
                // Non-2xx response - don't retry client errors (4xx)
                if ($response['statusCode'] >= 400 && $response['statusCode'] < 500) {
                    Log::warning('EspQuickBase API client error (not retrying)', [
                        'status_code' => $response['statusCode'],
                        'attempt' => $attempt + 1
                    ]);
                    
                    return [
                        'status' => false,
                        'message' => 'Client error',
                        'statusCode' => $response['statusCode'],
                        'response' => $response['body']
                    ];
                }
                
                // Server error (5xx) - retry
                Log::warning('EspQuickBase API server error (will retry)', [
                    'status_code' => $response['statusCode'],
                    'attempt' => $attempt + 1
                ]);
                
            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning('EspQuickBase API request failed (will retry)', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage()
                ]);
            }
            
            $attempt++;
            
            // Don't sleep after last attempt
            if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                // Exponential backoff: 1s, 2s, 4s
                $delayMs = self::INITIAL_RETRY_DELAY_MS * pow(2, $attempt - 1);
                usleep($delayMs * 1000); // Convert to microseconds
            }
        }
        
        // All retries failed
        $errorMessage = $lastException ? $lastException->getMessage() : 'Unknown error';
        
        Log::error('EspQuickBase API request failed after all retries', [
            'attempts' => self::MAX_RETRY_ATTEMPTS,
            'error' => $errorMessage
        ]);
        
        return [
            'status' => false,
            'message' => 'Request failed after ' . self::MAX_RETRY_ATTEMPTS . ' attempts',
            'statusCode' => $lastException ? $lastException->getCode() : 0,
            'response' => $errorMessage
        ];
    }
    
    /**
     * Check if API rate limit allows request
     * 
     * @return bool - True if request is allowed, false if rate limited
     */
    private function checkRateLimit(): bool
    {
        $key = self::RATE_LIMITER_KEY . ':' . now()->format('Y-m-d-H-i');
        
        return RateLimiter::attempt(
            $key,
            self::MAX_REQUESTS_PER_MINUTE,
            function() {
                // Request is allowed
            },
            60 // Decay time in seconds
        );
    }

    /**
     * Log transaction to database
     *
     * @param string $apiName - API operation name
     * @param array $payload - Request payload
     * @param array $response - API response
     * @param string $url - API endpoint
     * @return void
     */
    private function logTransaction(string $apiName, array $payload, array $response, string $url): void
    {
        InterigationTransactionLog::create([
            'interigation_name' => 'RepDataPushToQuickBase',
            'api_name' => $apiName,
            'payload' => json_encode($payload),
            'response' => json_encode($response),
            'url' => $url,
        ]);
    }

    /**
     * Get employee compensation details for a specific user and product
     *
     * @param int $userId - The user ID
     * @param int|null $productId - The product ID (auto-detected if null)
     * @return array - Employee compensation details
     */
    private function getUserCompensationDetails(int $userId, ?int $productId = null): array
    {
        try {
            // Auto-detect product ID if not provided
            if ($productId === null) {
                $recentOrganization = UserOrganizationHistory::where('user_id', $userId)
                    ->whereNotNull('product_id')
                    ->orderBy('effective_date', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                $productId = $recentOrganization?->product_id ?? 1;
            }

            $effectiveDate = date('Y-m-d');
            $employeeCompensation = [];

            // Get user organization history
            $userOrganization = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])
                ->where('effective_date', '<=', $effectiveDate)
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$userOrganization) {
                $userOrganization = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])
                    ->where('effective_date', '>=', $effectiveDate)
                    ->orderBy('effective_date', 'ASC')
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            // Get position details
            $position = Positions::withoutGlobalScope('notSuperAdmin')
                ->where('id', $userOrganization?->sub_position_id)
                ->first();

            // Determine core positions based on position type
            $corePositions = [];
            if ($position?->is_selfgen == '1') {
                $corePositions = [2, 3, null];
            } else if ($position?->is_selfgen == '2' || $position?->is_selfgen == '3') {
                $corePositions = [$position->is_selfgen];
            } else if ($position?->is_selfgen == '0') {
                $corePositions = [2];
            }

            // Build employee compensation for each core position
            foreach ($corePositions as $corePosition) {
                // Get redline information
                $redLine = null;
                $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])
                    ->where('start_date', '<=', $effectiveDate)
                    ->orderBy('start_date', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                if (!$redLineHistory) {
                    $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])
                        ->where('start_date', '>=', $effectiveDate)
                        ->orderBy('start_date', 'ASC')
                        ->orderBy('id', 'DESC')
                        ->first();
                }

                if ($redLineHistory) {
                    $redLine = [
                        'redline' => $redLineHistory->redline,
                        'redline_type' => $redLineHistory->redline_type,
                        'redline_amount_type' => $redLineHistory->redline_amount_type,
                        'redline_effective_date' => $redLineHistory->start_date
                    ];
                }

                // Get user commission history
                $userCommissionHistory = UserCommissionHistory::with('tiers')
                    ->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])
                    ->where('commission_effective_date', '<=', $effectiveDate)
                    ->orderBy('commission_effective_date', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                if (!$userCommissionHistory) {
                    $userCommissionHistory = UserCommissionHistory::with('tiers')
                        ->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])
                        ->where('commission_effective_date', '>=', $effectiveDate)
                        ->orderBy('commission_effective_date', 'ASC')
                        ->orderBy('id', 'DESC')
                        ->first();
                }

                // Build the compensation array
                $employeeCompensation[] = [
                    'position' => match ($corePosition) {
                        2 => 'Closer',
                        3 => 'Setter',
                        default => 'Self-Gen',
                    },
                    'redline' => $redLine,
                    'commission' => [
                        'commission' => $userCommissionHistory?->commission,
                        'commission_type' => $userCommissionHistory?->commission_type,
                        'commission_effective_date' => $userCommissionHistory?->commission_effective_date
                    ]
                ];
            }

            return $employeeCompensation;
        } catch (\Exception $e) {
            Log::error('Error getting user compensation details', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get user organization details including position, locations, and overrides
     *
     * @param int $id - The user ID
     * @return array - User organization details
     */
    private function getUserOrganizationDetails(int $id): array
    {
        try {
            $user = User::withoutGlobalScopes()
                ->with('state', 'office', 'departmentDetail', 'recruiter', 'additionalDetail')
                ->find($id);

            if (!$user) {
                return ['status' => false, 'message' => 'User not found'];
            }

            $userId = $user->id;
            $effectiveDate = date('Y-m-d');

            // Get is_manager status
            $isManager = UserIsManagerHistory::where(['user_id' => $userId])
                ->where('effective_date', '<=', $effectiveDate)
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$isManager) {
                $isManager = UserIsManagerHistory::where(['user_id' => $userId])
                    ->where('effective_date', '>=', $effectiveDate)
                    ->orderBy('effective_date', 'ASC')
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            // Get user organization history
            $userOrganization = UserOrganizationHistory::with('position', 'subPositionId')
                ->where(['user_id' => $userId])
                ->where('effective_date', '<=', $effectiveDate)
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$userOrganization) {
                $userOrganization = UserOrganizationHistory::with('position', 'subPositionId')
                    ->where(['user_id' => $userId])
                    ->where('effective_date', '>=', $effectiveDate)
                    ->orderBy('effective_date', 'ASC')
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            // Get additional locations
            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])
                ->where('effective_date', '<=', $effectiveDate)
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$currentAdditional) {
                $currentAdditional = AdditionalLocations::where(['user_id' => $userId])
                    ->where('effective_date', '>=', $effectiveDate)
                    ->orderBy('effective_date', 'ASC')
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            $additionalLocations = AdditionalLocations::with('state', 'office')
                ->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])
                ->get();

            $additionalOffice = [];
            foreach ($additionalLocations as $additionalLocation) {
                $officeId = $additionalLocation?->office?->id;
                $additionalOverride = UserAdditionalOfficeOverrideHistory::where([
                        'user_id' => $userId,
                        'office_id' => $officeId
                    ])
                    ->where('override_effective_date', '<=', $effectiveDate)
                    ->orderBy('override_effective_date', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                if (!$additionalOverride) {
                    $additionalOverride = UserAdditionalOfficeOverrideHistory::where([
                            'user_id' => $userId,
                            'office_id' => $officeId
                        ])
                        ->where('override_effective_date', '>=', $effectiveDate)
                        ->orderBy('override_effective_date', 'ASC')
                        ->orderBy('id', 'DESC')
                        ->first();
                }

                $additionalOffice[] = [
                    'state_name' => $additionalLocation?->state?->name,
                    'office_name' => $additionalLocation?->office?->office_name,
                    'effective_date' => $additionalLocation->effective_date,
                    'overrides_amount' => $additionalOverride?->office_overrides_amount ?? null,
                    'overrides_type' => $additionalOverride?->office_overrides_type ?? null
                ];
            }

            return [
                'state_name' => $user?->state?->name,
                'office_name' => $user?->office?->office_name,
                'department_name' => $user?->departmentDetail?->name,
                'position_name' => $userOrganization?->position?->position_name,
                'sub_position_name' => $userOrganization?->subPositionId?->position_name,
                'is_manager' => $isManager?->is_manager ?? 0,
                'additional_locations' => $additionalOffice
            ];
        } catch (\Exception $e) {
            Log::error('Error getting user organization details', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => false,
                'message' => 'Error getting user organization details: ' . $e->getMessage()
            ];
        }
    }
}

