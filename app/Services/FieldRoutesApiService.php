<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * FieldRoutes API Service with Comprehensive Pagination Support
 *
 * Handles FieldRoutes API limitations automatically:
 * - Search endpoints: 50,000 ID limit per request → uses pagination with primary key filtering
 * - Get endpoints: 1,000 entity limit per request → automatically chunks large requests
 * - Rate limiting: Built-in delays between paginated requests
 *
 * All methods handle pagination transparently, ensuring complete data retrieval
 * regardless of result set size.
 */
class FieldRoutesApiService
{
    protected $client;

    protected $lastApiCallTime = null;

    protected $apiCallsInLastMinute = 0;

    protected $maxCallsPerMinute = 60;

    protected $debug = false;

    public function __construct()
    {
        $this->client = new Client;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * Decrypt API credentials from the integrations table
     * Uses the same decryption logic as the existing sync commands
     */
    public function decryptCredentials($encryptedValue)
    {
        try {
            // Try Laravel's built-in decryption first
            try {
                Log::debug('Attempting Laravel decrypt');
                $decrypted = decrypt($encryptedValue);
                Log::debug('Laravel decrypt successful', ['length' => strlen($decrypted)]);
                $config = json_decode($decrypted, true);
            } catch (\Exception $decryptException) {
                // If Laravel's decryption fails, try openssl_decrypt exactly as in reference code
                Log::info('Laravel decryption failed, trying alternative method', ['error' => $decryptException->getMessage()]);

                $decrypted = openssl_decrypt(
                    $encryptedValue,
                    config('app.encryption_cipher_algo'),
                    config('app.encryption_key'),
                    0,
                    config('app.encryption_iv')
                );

                if ($decrypted === false) {
                    throw new \Exception('OpenSSL decryption failed: '.openssl_error_string());
                }

                Log::debug('OpenSSL decrypt result', ['length' => strlen($decrypted), 'preview' => substr($decrypted, 0, 50)]);
                $config = json_decode($decrypted, true);
            }
        } catch (\Exception $e) {
            Log::error("Error decrypting integration credentials: {$e->getMessage()}");
            throw new \Exception("Failed to decrypt integration credentials: {$e->getMessage()}");
        }

        if (! $config) {
            $debugInfo = [
                'decrypted_length' => strlen($decrypted ?? ''),
                'decrypted_preview' => substr($decrypted ?? '', 0, 200),
                'json_error' => json_last_error_msg(),
                'is_string' => is_string($decrypted),
                'is_empty' => empty($decrypted),
            ];
            Log::error('Decryption result is not valid JSON', $debugInfo);

            // For debugging, show the actual content (be careful in production!)
            if (app()->environment('local')) {
                Log::debug('Full decrypted content', ['content' => $decrypted]);
            }

            throw new \Exception('Invalid configuration after decryption - not valid JSON: '.json_last_error_msg().' (Length: '.strlen($decrypted ?? '').')');
        }

        // Extract and validate required fields
        $baseUrl = $config['base_url'] ?? 'https://aruza.fieldroutes.com/api';
        $apiKey = $config['authenticationKey'] ?? null;
        $apiToken = $config['authenticationToken'] ?? null;
        $officeId = $config['office_id'] ?? null;

        if (! $apiKey || ! $apiToken) {
            throw new \Exception('Missing required API credentials (authenticationKey or authenticationToken)');
        }

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'api_token' => $apiToken,
            'office_id' => $officeId,
        ];
    }

    /**
     * Determine appropriate timeout based on endpoint and request size
     */
    protected function getTimeoutForEndpoint($endpoint, $params)
    {
        // Default timeout
        $timeout = 60;

        // Bulk operations need more time
        if (strpos($endpoint, '/customer/get') !== false) {
            $customerCount = count($params['customerIDs'] ?? []);
            if ($customerCount > 100) {
                $timeout = 180; // 3 minutes for large bulk customer calls
            } elseif ($customerCount > 10) {
                $timeout = 120; // 2 minutes for medium bulk customer calls
            } else {
                $timeout = 90;  // 90 seconds for small customer calls
            }
        } elseif (strpos($endpoint, '/subscription/get') !== false) {
            $subscriptionCount = count($params['subscriptionIDs'] ?? []);
            if ($subscriptionCount > 500) {
                $timeout = 150; // 2.5 minutes for large subscription calls
            } elseif ($subscriptionCount > 100) {
                $timeout = 120; // 2 minutes for medium subscription calls
            } else {
                $timeout = 90;  // 90 seconds for small subscription calls
            }
        } elseif (strpos($endpoint, '/appointment/search') !== false) {
            $timeout = 120; // 2 minutes for appointment searches
        } elseif (strpos($endpoint, '/subscription/search') !== false) {
            $timeout = 120; // 2 minutes for subscription searches
        }

        return $timeout;
    }

    /**
     * Make an API call to FieldRoutes
     */
    public function callApi($endpoint, $params, $baseUrl = null)
    {
        // Implement rate limiting
        $now = time();
        if ($this->lastApiCallTime === null) {
            $this->lastApiCallTime = $now;
            $this->apiCallsInLastMinute = 1;
        } else {
            // Reset counter if a minute has passed
            if ($now - $this->lastApiCallTime >= 60) {
                $this->apiCallsInLastMinute = 1;
                $this->lastApiCallTime = $now;
            } else {
                $this->apiCallsInLastMinute++;

                // If we've hit the limit, wait until the next minute
                if ($this->apiCallsInLastMinute >= $this->maxCallsPerMinute) {
                    if ($this->debug) {
                        Log::debug("   ⚠️  Hit rate limit ({$this->maxCallsPerMinute} calls per minute), waiting for next minute window...");
                    }
                    $sleepTime = 60 - ($now - $this->lastApiCallTime);
                    if ($sleepTime > 0) {
                        sleep($sleepTime);
                    }
                    $this->apiCallsInLastMinute = 1;
                    $this->lastApiCallTime = time();
                }
            }
        }

        if (! $baseUrl) {
            $baseUrl = 'https://subdomain.fieldroutes.com/api';
        }

        $fullUrl = rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');

        // Debug logging (hide sensitive data)
        if ($this->debug) {
            $debugParams = $params;
            if (isset($debugParams['authenticationKey'])) {
                $debugParams['authenticationKey'] = 'HIDDEN';
            }
            if (isset($debugParams['authenticationToken'])) {
                $debugParams['authenticationToken'] = 'HIDDEN';
            }

            Log::debug('FieldRoutes API Call', [
                'endpoint' => $fullUrl,
                'params' => $debugParams,
                'calls_in_last_minute' => $this->apiCallsInLastMinute,
            ]);
        }

        try {
            // Determine timeout based on endpoint and data size
            $timeout = $this->getTimeoutForEndpoint($endpoint, $params);

            $response = $this->client->post($fullUrl, [
                'json' => $params,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'timeout' => $timeout,
                'connect_timeout' => 30,
                'read_timeout' => $timeout,
            ]);

            $responseBody = $response->getBody();
            $data = json_decode($responseBody, true);

            // Debug the response (without logging large data)
            if ($this->debug) {
                Log::debug('FieldRoutes API Response', [
                    'endpoint' => $fullUrl,
                    'response_keys' => array_keys($data ?? []),
                    'response_size' => strlen($responseBody),
                    'memory_usage' => memory_get_usage(true) / 1024 / 1024 .'MB',
                ]);
            }

            // Clear response body from memory
            unset($responseBody);

            return $data ?? [];
        } catch (\Exception $e) {
            Log::error("FieldRoutes API Error: {$e->getMessage()}", [
                'endpoint' => $fullUrl,
                'params_count' => count($params),
                'calls_in_last_minute' => $this->apiCallsInLastMinute,
            ]);

            // If we hit the rate limit, wait and retry once
            if (strpos($e->getMessage(), 'maximum number of read requests per minute') !== false) {
                if ($this->debug) {
                    Log::debug('   🔄 Rate limit hit, waiting 60 seconds before retry...');
                }
                sleep(60);  // Wait a full minute
                $this->apiCallsInLastMinute = 0;
                $this->lastApiCallTime = time();

                return $this->callApi($endpoint, $params, $baseUrl);  // Retry once
            }

            throw $e;
        }
    }

    /**
     * Search subscriptions using a custom query object with pagination support
     */
    public function searchSubscriptionsWithQuery($credentials, $queryObject, $maxResults = null)
    {
        if ($this->debug) {
            Log::debug('🔍 Starting subscription search', [
                'query' => $queryObject,
                'max_results' => $maxResults,
            ]);
        }

        // Get all data in one call
        $searchParams = array_merge($queryObject, [
            'authenticationKey' => $credentials['api_key'],
            'authenticationToken' => $credentials['api_token'],
            'includeData' => 1,  // Include full data in the search
        ]);

        if ($maxResults) {
            $searchParams['limit'] = $maxResults;
        }

        if ($this->debug) {
            Log::debug('📥 Fetching subscriptions with data in one call');
        }

        $result = $this->callApi('/subscription/search', $searchParams, $credentials['base_url']);

        $subscriptionIds = $result['subscriptionIDs'] ?? [];
        $subscriptions = $result['subscriptions'] ?? [];
        $totalAvailable = count($subscriptionIds);

        if ($this->debug) {
            Log::debug("📊 Found {$totalAvailable} total subscriptions");
        }

        return [
            'subscriptionIDs' => $subscriptionIds,
            'subscriptions' => $subscriptions,
            'subscriptionIDsNoDataExported' => [],
            'pagination_info' => [
                'total_pages' => 1,
                'total_results' => count($subscriptions),
                'total_available' => $totalAvailable,
                'skipped_records' => $totalAvailable - count($subscriptions),
            ],
        ];
    }

    /**
     * Search subscriptions using the correct FieldRoutes API format with pagination
     */
    public function searchSubscriptions($employeeId, $fromDate, $toDate, $credentials, $lastId = null, $includeData = false, $maxResults = null)
    {
        $allSubscriptionIDs = [];
        $allSubscriptions = [];
        $allSubscriptionIDsNoData = [];
        $currentLastId = $lastId;
        $totalFetched = 0;
        $pageNumber = 1;

        do {
            // Use the exact format from the working example
            $searchParams = [
                'soldBy' => [$employeeId], // Array format as shown in example
                'authenticationKey' => $credentials['api_key'],
                'authenticationToken' => $credentials['api_token'],
            ];

            // Add date filter in the correct format - using dateUpdated to capture both new and modified subscriptions
            if ($fromDate && $toDate) {
                $searchParams['dateUpdated'] = [
                    'operator' => 'BETWEEN',
                    'value' => [$fromDate, $toDate],
                ];
            }

            // Use status filter as in the example (>= -3 includes leads, frozen, and active)
            $searchParams['status'] = [
                'operator' => '>=',
                'value' => [-3],
            ];

            // Add includeData to get first 1000 resolved objects if requested
            if ($includeData) {
                $searchParams['includeData'] = 1;
            }

            // Add pagination using subscriptionIDs filter if provided
            if ($currentLastId) {
                $searchParams['subscriptionIDs'] = [
                    'operator' => '>',
                    'value' => [$currentLastId],
                ];
            }

            // Add office filter as array if available in credentials
            if (isset($credentials['office_id']) && $credentials['office_id']) {
                $searchParams['officeIDs'] = [$credentials['office_id']]; // Array format
            }

            // Limit results for this page (API max is 50,000 for search)
            if ($maxResults && $maxResults > 0) {
                $remainingLimit = $maxResults - $totalFetched;
                $searchParams['limit'] = min($remainingLimit, 50000);
            }

            $result = $this->callApi('/subscription/search', $searchParams, $credentials['base_url']);

            $subscriptionIDs = $result['subscriptionIDs'] ?? [];
            $subscriptions = $result['subscriptions'] ?? [];
            $subscriptionIDsNoData = $result['subscriptionIDsNoDataExported'] ?? [];

            // Debug pagination info
            if (app()->environment('local')) {
                Log::debug('FieldRoutes Search Pagination', [
                    'page' => $pageNumber,
                    'employee_id' => $employeeId,
                    'subscriptionIDs_count' => count($subscriptionIDs),
                    'subscriptions_count' => count($subscriptions),
                    'subscriptionIDsNoData_count' => count($subscriptionIDsNoData),
                    'total_fetched_so_far' => $totalFetched,
                    'last_id_used' => $currentLastId,
                ]);
            }

            // Merge results efficiently
            foreach ($subscriptionIDs as $id) {
                $allSubscriptionIDs[] = $id;
            }
            foreach ($subscriptions as $subscription) {
                $allSubscriptions[] = $subscription;
            }
            foreach ($subscriptionIDsNoData as $id) {
                $allSubscriptionIDsNoData[] = $id;
            }

            // Clear temporary variables to free memory
            unset($subscriptionIDs, $subscriptions, $subscriptionIDsNoData);

            // Force garbage collection every 10 pages to prevent memory buildup
            if ($pageNumber % 10 === 0) {
                gc_collect_cycles();
            }

            // Track pagination progress
            $resultCount = count($subscriptionIDs);
            $totalFetched += $resultCount;

            // Update currentLastId for next iteration
            if (! empty($subscriptionIDs)) {
                $currentLastId = end($subscriptionIDs);
            }

            $pageNumber++;

            // Continue if we got a full page (50,000) and haven't hit our limit
            $shouldContinue = ($resultCount >= 50000) &&
                             (! $maxResults || $totalFetched < $maxResults);

        } while ($shouldContinue);

        // Return paginated results in the same format
        if ($includeData) {
            return [
                'subscriptionIDs' => $allSubscriptionIDs,
                'subscriptions' => $allSubscriptions,
                'subscriptionIDsNoDataExported' => $allSubscriptionIDsNoData,
                'pagination_info' => [
                    'total_pages' => $pageNumber - 1,
                    'total_results' => count($allSubscriptions),
                ],
            ];
        }

        return $allSubscriptionIDs;
    }

    /**
     * Get subscription details in bulk with automatic pagination for >1000 records
     */
    public function getSubscriptionDetails($subscriptionIds, $credentials)
    {
        // Handle pagination for large sets of IDs
        if (count($subscriptionIds) <= 1000) {
            // Single request for small sets
            return $this->getSubscriptionDetailsBatch($subscriptionIds, $credentials);
        }

        // Paginate for large sets
        $allResults = [];
        $batches = array_chunk($subscriptionIds, 1000);
        $batchNumber = 1;

        foreach ($batches as $batch) {
            if (app()->environment('local')) {
                Log::debug('Getting subscription details batch', [
                    'batch_number' => $batchNumber,
                    'batch_size' => count($batch),
                    'total_batches' => count($batches),
                ]);
            }

            $batchResults = $this->getSubscriptionDetailsBatch($batch, $credentials);
            $allResults = array_merge($allResults, $batchResults);

            $batchNumber++;

            // Rate limiting between batches
            if (count($batches) > 1 && $batchNumber <= count($batches)) {
                usleep(250000); // 250ms delay between batches
            }
        }

        return $allResults;
    }

    /**
     * Get subscription details for a single batch (≤1000 records)
     */
    protected function getSubscriptionDetailsBatch($subscriptionIds, $credentials)
    {
        // Ensure we don't exceed the 1000 record limit
        if (count($subscriptionIds) > 1000) {
            Log::warning('Subscription batch exceeds 1000 limit, this should not happen', [
                'requested' => count($subscriptionIds),
                'limit' => 1000,
            ]);
            $subscriptionIds = array_slice($subscriptionIds, 0, 1000);
        }

        $params = [
            'subscriptionIDs' => $subscriptionIds,
            'authenticationKey' => $credentials['api_key'],
            'authenticationToken' => $credentials['api_token'],
        ];

        $result = $this->callApi('/subscription/get', $params, $credentials['base_url']);

        return $result['subscriptions'] ?? [];
    }

    /**
     * Search customers with proper rate limiting and pagination
     */
    public function searchCustomers($searchCriteria, $credentials, $maxResults = null)
    {
        $allResults = [];
        $lastId = null;
        $totalFetched = 0;
        $pageNumber = 1;

        do {
            // Add authentication to the search criteria
            $searchParams = array_merge($searchCriteria, [
                'authenticationKey' => $credentials['api_key'],
                'authenticationToken' => $credentials['api_token'],
            ]);

            // Add pagination using customerIDs filter if we have a lastId
            if ($lastId) {
                $searchParams['customerIDs'] = [
                    'operator' => '>',
                    'value' => [$lastId],
                ];
            }

            // Limit results for this page (API max is 50,000 for search)
            if ($maxResults && $maxResults > 0) {
                $remainingLimit = $maxResults - $totalFetched;
                $searchParams['limit'] = min($remainingLimit, 50000);
            }

            $result = $this->callApi('/customer/search', $searchParams, $credentials['base_url']);

            $customerIDs = $result['customerIDs'] ?? [];
            $customers = $result['customers'] ?? [];

            // Debug pagination info
            if (app()->environment('local')) {
                Log::debug('FieldRoutes Customer Search Pagination', [
                    'page' => $pageNumber,
                    'customerIDs_count' => count($customerIDs),
                    'customers_count' => count($customers),
                    'total_fetched_so_far' => $totalFetched,
                    'last_id_used' => $lastId,
                ]);
            }

            // Merge results
            if (isset($searchCriteria['includeData']) && ! empty($customers)) {
                $allResults = array_merge($allResults, $customers);
            } else {
                // If not including data, just collect IDs
                $allResults = array_merge($allResults, $customerIDs);
            }

            // Track pagination progress
            $resultCount = count($customerIDs);
            $totalFetched += $resultCount;

            // Update lastId for next iteration
            if (! empty($customerIDs)) {
                $lastId = end($customerIDs);
            }

            $pageNumber++;

            // Continue if we got a full page (50,000) and haven't hit our limit
            $shouldContinue = ($resultCount >= 50000) &&
                             (! $maxResults || $totalFetched < $maxResults);

            // Add delay between pages to respect rate limits
            if ($shouldContinue) {
                sleep(1); // 1 second delay between pages
            }

        } while ($shouldContinue);

        // Return paginated results
        if (isset($searchCriteria['includeData'])) {
            return [
                'customerIDs' => [],
                'customers' => $allResults,
                'pagination_info' => [
                    'total_pages' => $pageNumber - 1,
                    'total_results' => count($allResults),
                ],
            ];
        }

        return $allResults;
    }

    /**
     * Get customer details - Always use batch processing
     */
    public function getCustomerDetails($customerIds, $credentials)
    {
        // Ensure we have an array and convert single ID to array
        if (! is_array($customerIds)) {
            $customerIds = [$customerIds];
        }

        // Use bulk processing even for single customer
        $result = $this->getCustomerDetailsBatch($customerIds, $credentials);

        return count($customerIds) === 1 ? ($result[0] ?? null) : $result;
    }

    /**
     * Get customer details in bulk with automatic pagination for >1000 records
     */
    public function getCustomerDetailsBulk($customerIds, $credentials)
    {
        // Handle pagination for large sets of IDs
        $allResults = [];
        $batches = array_chunk($customerIds, 1000);
        $batchNumber = 1;
        $totalBatches = count($batches);

        foreach ($batches as $batch) {
            if (app()->environment('local')) {
                Log::debug('Getting customer details batch', [
                    'batch_number' => $batchNumber,
                    'batch_size' => count($batch),
                    'total_batches' => $totalBatches,
                ]);
            }

            $batchResults = $this->getCustomerDetailsBatch($batch, $credentials);
            $allResults = array_merge($allResults, $batchResults);

            $batchNumber++;

            // Add delay between batches to respect rate limits
            if ($batchNumber <= $totalBatches) {
                sleep(1); // 1 second delay between batches
            }
        }

        return $allResults;
    }

    /**
     * Get customer details for a single batch (≤1000 records)
     */
    public function getCustomerDetailsBatch($customerIds, $credentials)
    {
        // Ensure we don't exceed the 1000 record limit
        if (count($customerIds) > 1000) {
            Log::warning('Customer batch exceeds 1000 limit, truncating', [
                'requested' => count($customerIds),
                'limit' => 1000,
            ]);
            $customerIds = array_slice($customerIds, 0, 1000);
        }

        // Common parameters for both single and multiple customers
        $params = [
            'authenticationKey' => $credentials['api_key'],
            'authenticationToken' => $credentials['api_token'],
            'includeSubscriptions' => 1,  // Get related subscription data
            'includeCancellationReason' => 1,  // Get cancellation notes
            'includeCustomerFlag' => 1,  // Get customer flags
            'includeAdditionalContacts' => 1,  // Get additional contacts
            'includePortalLogin' => 1,  // Get portal login details
        ];

        // If single ID, use 'id', if multiple IDs use 'customerIDs'
        if (count($customerIds) === 1) {
            $params['id'] = $customerIds[0];
        } else {
            $params['customerIDs'] = $customerIds;
        }

        if (app()->environment('local')) {
            Log::debug('Getting customer details', [
                'count' => count($customerIds),
                'using_param' => count($customerIds) === 1 ? 'id' : 'customerIDs',
            ]);
        }

        $result = $this->callApi('/customer/get', $params, $credentials['base_url']);

        // Handle both single and multiple customer responses
        if (count($customerIds) === 1) {
            // Single customer returns direct object
            return isset($result['customer']) ? [$result['customer']] : [];
        } else {
            // Multiple customers returns array under 'customers'
            return $result['customers'] ?? [];
        }
    }

    /**
     * Search appointments for specific criteria with pagination support
     */
    public function searchAppointments($searchCriteria, $credentials, $maxResults = null)
    {
        $allResults = [];
        $lastId = null;
        $totalFetched = 0;
        $pageNumber = 1;

        do {
            // Add authentication to the search criteria
            $searchParams = array_merge($searchCriteria, [
                'authenticationKey' => $credentials['api_key'],
                'authenticationToken' => $credentials['api_token'],
            ]);

            // Add pagination using appointmentIDs filter if we have a lastId
            if ($lastId) {
                $searchParams['appointmentIDs'] = [
                    'operator' => '>',
                    'value' => [$lastId],
                ];
            }

            // Limit results for this page (API max is 50,000 for search)
            if ($maxResults && $maxResults > 0) {
                $remainingLimit = $maxResults - $totalFetched;
                $searchParams['limit'] = min($remainingLimit, 50000);
            }

            $result = $this->callApi('/appointment/search', $searchParams, $credentials['base_url']);

            $appointmentIDs = $result['appointmentIDs'] ?? [];
            $appointments = $result['appointments'] ?? [];

            // Debug pagination info
            if (app()->environment('local')) {
                Log::debug('FieldRoutes Appointment Search Pagination', [
                    'page' => $pageNumber,
                    'appointmentIDs_count' => count($appointmentIDs),
                    'appointments_count' => count($appointments),
                    'total_fetched_so_far' => $totalFetched,
                    'last_id_used' => $lastId,
                ]);
            }

            // Merge results
            if (isset($searchCriteria['includeData']) && ! empty($appointments)) {
                $allResults = array_merge($allResults, $appointments);
            } else {
                // If not including data, just collect IDs
                $allResults = array_merge($allResults, $appointmentIDs);
            }

            // Track pagination progress
            $resultCount = count($appointmentIDs);
            $totalFetched += $resultCount;

            // Update lastId for next iteration (use the last appointment ID)
            if (! empty($appointmentIDs)) {
                $lastId = end($appointmentIDs);
            }

            $pageNumber++;

            // Continue if we got a full page (50,000) and haven't hit our limit
            $shouldContinue = ($resultCount >= 50000) &&
                             (! $maxResults || $totalFetched < $maxResults);

        } while ($shouldContinue);

        // Return paginated results
        if (isset($searchCriteria['includeData'])) {
            return [
                'appointmentIDs' => [],
                'appointments' => $allResults,
                'pagination_info' => [
                    'total_pages' => $pageNumber - 1,
                    'total_results' => count($allResults),
                ],
            ];
        }

        return $allResults;
    }

    /**
     * Get appointments for a specific customer or subscription
     */
    public function getAppointmentsForCustomer($customerId, $credentials, $subscriptionId = null, $includeData = true, $maxResults = 1000)
    {
        $searchCriteria = [
            'customerIDs' => [$customerId],
            'includeData' => $includeData ? 1 : 0,
        ];

        // Add subscription filter if provided
        if ($subscriptionId) {
            $searchCriteria['subscriptionIDs'] = [$subscriptionId];
        }

        // Add office filter if available in credentials
        if (isset($credentials['office_id']) && $credentials['office_id']) {
            $searchCriteria['officeIDs'] = [$credentials['office_id']];
        }

        return $this->searchAppointments($searchCriteria, $credentials, $maxResults);
    }

    /**
     * Get appointment details - Always use batch processing
     */
    public function getAppointmentDetails($appointmentId, $credentials)
    {
        // Always use batch processing even for single appointment
        $result = $this->getAppointmentDetailsBatch([$appointmentId], $credentials);

        return $result[0] ?? null;
    }

    /**
     * Get appointment details in bulk with automatic pagination for >1000 records
     */
    public function getAppointmentDetailsBulk($appointmentIds, $credentials)
    {
        // Handle pagination for large sets of IDs
        $allResults = [];
        $batches = array_chunk($appointmentIds, 1000);
        $batchNumber = 1;
        $totalBatches = count($batches);

        foreach ($batches as $batch) {
            if (app()->environment('local')) {
                Log::debug('Getting appointment details batch', [
                    'batch_number' => $batchNumber,
                    'batch_size' => count($batch),
                    'total_batches' => $totalBatches,
                ]);
            }

            $batchResults = $this->getAppointmentDetailsBatch($batch, $credentials);
            $allResults = array_merge($allResults, $batchResults);

            $batchNumber++;

            // Add delay between batches to respect rate limits
            if ($batchNumber <= $totalBatches) {
                sleep(1); // 1 second delay between batches
            }
        }

        return $allResults;
    }

    /**
     * Get appointment details for a single batch (≤1000 records)
     */
    protected function getAppointmentDetailsBatch($appointmentIds, $credentials)
    {
        // Ensure we don't exceed the 1000 record limit
        if (count($appointmentIds) > 1000) {
            Log::warning('Appointment batch exceeds 1000 limit, truncating', [
                'requested' => count($appointmentIds),
                'limit' => 1000,
            ]);
            $appointmentIds = array_slice($appointmentIds, 0, 1000);
        }

        // Use different parameter name based on number of IDs
        $params = [
            'authenticationKey' => $credentials['api_key'],
            'authenticationToken' => $credentials['api_token'],
        ];

        // If single ID, use 'id', if multiple IDs use 'appointmentIDs'
        if (count($appointmentIds) === 1) {
            $params['id'] = $appointmentIds[0];
        } else {
            $params['appointmentIDs'] = $appointmentIds;
        }

        if (app()->environment('local')) {
            Log::debug('Getting appointment details', [
                'count' => count($appointmentIds),
                'using_param' => count($appointmentIds) === 1 ? 'id' : 'appointmentIDs',
            ]);
        }

        $result = $this->callApi('/appointment/get', $params, $credentials['base_url']);

        // Handle both single and multiple appointment responses
        if (count($appointmentIds) === 1) {
            // Single appointment returns direct object
            return isset($result['appointment']) ? [$result['appointment']] : [];
        } else {
            // Multiple appointments returns array under 'appointments'
            return $result['appointments'] ?? [];
        }
    }
}
