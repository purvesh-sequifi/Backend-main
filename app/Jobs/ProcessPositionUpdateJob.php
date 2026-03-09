<?php

namespace App\Jobs;

use App\Events\PositionUpdateProgress;
use App\Http\Controllers\API\V2\Position\PositionController;
use App\Models\OnboardingEmployees;
use App\Models\Positions;
use App\Models\PositionProduct;
use App\Models\UserDepartmentHistory;
use App\Models\UserOrganizationHistory;
use App\Services\NotificationService;
use App\Services\AwsRoleMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class ProcessPositionUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;          // Retry up to 3 times
    public $timeout = 7200;     // 2 hours maximum execution time
    public $maxExceptions = 3;  // Allow up to 3 exceptions before failing
    public $backoff = [30, 60, 120]; // Progressive retry delays (30s, 60s, 120s)

    protected $positionId;
    protected $requestData;
    protected $authUserId;
    protected $differences;
    protected $differences2;
    protected $tierChanged;
    protected $oldDepartmentId;
    protected $effectiveDate;
    protected $initiatedAt;
    protected $uniqueKey;

    public function __construct(
        int $positionId,
        array $requestData,
        int $authUserId,
        array $differences = [],
        array $differences2 = [],
        bool $tierChanged = false,
        ?int $oldDepartmentId = null,
        ?string $effectiveDate = null,
        ?string $initiatedAt = null,
        ?string $uniqueKey = null
    ) {
        $this->positionId = $positionId;
        $this->requestData = $requestData;
        $this->authUserId = $authUserId;
        $this->differences = $differences;
        $this->differences2 = $differences2;
        $this->tierChanged = $tierChanged;
        $this->oldDepartmentId = $oldDepartmentId;
        $this->effectiveDate = $effectiveDate;
        $this->initiatedAt = $initiatedAt ?? now()->toDateTimeString();
        $this->uniqueKey = $uniqueKey ?? $positionId . '_' . time();
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Log::info('ProcessPositionUpdateJob started', [
            'position_id' => $this->positionId,
            'wizard_type' => $this->requestData['wizard_type'] ?? null,
        ]);

        try {
            // Set authenticated user context for the job
            $user = \App\Models\User::find($this->authUserId);
            Auth::setUser($user);

            $positionName = $this->requestData['position_name'] ?? 'Position';

            // Broadcast: Job started (65%)
            try {
                $notificationData = [
                    'positionId' => $this->positionId,
                    'positionName' => $positionName,
                    'status' => 'processing',
                    'progress' => 65,
                    'message' => 'Background job started - processing user data',
                    'updatedBy' => $user->name,
                    'updatedById' => $user->id,
                    'initiatedAt' => $this->initiatedAt,
                    'completedAt' => null,
                    'uniqueKey' => $this->uniqueKey,
                    'timestamp' => now()->toISOString()
                ];

                broadcast(new PositionUpdateProgress($notificationData));

                // 🆕 STORE IN REDIS (Non-blocking, graceful failure)
                try {
                    $stored = app(NotificationService::class)->storeNotification($user->id, 'position_update', $notificationData);
                    \Log::info('Redis storage result', [
                        'success' => $stored,
                        'user_id' => $user->id,
                        'type' => 'position_update',
                        'unique_key' => $notificationData['uniqueKey']
                    ]);
                } catch (\Exception $redisError) {
                    // Log error but don't fail job
                    \Log::error('Redis storage failed', [
                        'error' => $redisError->getMessage(),
                        'user_id' => $user->id ?? 'unknown'
                    ]);
                }
            } catch (\Exception $e) {
                \Log::debug('Pusher broadcast failed', ['error' => $e->getMessage()]);
            }

            $controller = app(PositionController::class);
            $request = new \Illuminate\Http\Request();
            $request->replace($this->requestData);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            $originalEffectiveDate = $this->effectiveDate ?? ($this->requestData['effective_date'] ?? date('Y-m-d'));
            $departmentId = $this->requestData['department_id'] ?? null;

            // ⏱️ Performance tracking
            $perfStart = microtime(true);
            $perfTimings = [];

            if ($this->tierChanged) {
                $stepStart = microtime(true);
                $this->callProtectedMethod($controller, 'updatePosition', [$this->positionId, $request]);
                $perfTimings['updatePosition'] = round((microtime(true) - $stepStart) * 1000) . 'ms';
                \Log::info('⏱️ updatePosition completed', ['duration' => $perfTimings['updatePosition']]);

                if (count($this->differences) != 0) {
                    $stepStart = microtime(true);
                    $this->callProtectedMethod($controller, 'updateUserHistories', [$this->positionId, $request, $this->differences]);
                    $perfTimings['updateUserHistories'] = round((microtime(true) - $stepStart) * 1000) . 'ms';
                    \Log::info('⏱️ updateUserHistories completed', ['duration' => $perfTimings['updateUserHistories'], 'differences_count' => count($this->differences)]);
                }

                $isBackdatedCheck = $this->callProtectedMethod($controller, 'isBackdatedEffectiveDate', [
                    $this->positionId,
                    $this->requestData['product_id'] ?? [],
                    $originalEffectiveDate
                ]);

                if (($this->requestData['wizard_type'] ?? null) === 'all_users' && $isBackdatedCheck) {
                    $this->callProtectedMethod($controller, 'createBackdatedUserOrganizationHistories', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ]);
                    // 🔧 CRITICAL FIX: Pass removed products ($differences2) to prevent validatePropagationCompleteness from re-adding them
                    $this->callProtectedMethod($controller, 'propagateBackdatedProductsToSubsequentDates', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $this->differences2  // Pass removed products to exclude from propagation validation
                    ]);
                    $this->callProtectedMethod($controller, 'createBackdatedCommissionAndUpfrontSetup', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $request
                    ]);
                }

                // 🔧 CRITICAL FIX: Skip propagateExistingProductsToNewDate when products are removed
                // This method gets products from old dates and propagates them forward, which re-adds removed products
                // The controller already created the correct product list for the new effective date
                if (count($this->differences2) == 0 &&
                    ($this->requestData['wizard_type'] ?? null) === 'all_users' &&
                    $this->callProtectedMethod($controller, 'isForwardPropagationRequired', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ])) {
                    // 🔧 CRITICAL FIX: Pass removed products to prevent validatePropagationCompleteness from re-adding them
                    $this->callProtectedMethod($controller, 'propagateExistingProductsToNewDate', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $this->differences2  // Pass removed products
                    ]);
                    $this->callProtectedMethod($controller, 'createForwardPropagationUserOrganizationHistories', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ]);
                    $this->callProtectedMethod($controller, 'createForwardPropagationCommissionAndUpfrontSetup', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $request
                    ]);
                }

            } else {
                $isBackdated = ($this->requestData['wizard_type'] ?? null) === 'all_users' &&
                    $this->callProtectedMethod($controller, 'isBackdatedEffectiveDate', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ]);


                if (count($this->differences) != 0) {
                    $this->callProtectedMethod($controller, 'updatePosition', [$this->positionId, $request]);
                    $this->callProtectedMethod($controller, 'updateUserHistories', [$this->positionId, $request, $this->differences]);

                    $isBackdatedCheck = $this->callProtectedMethod($controller, 'isBackdatedEffectiveDate', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ]);

                    if (($this->requestData['wizard_type'] ?? null) === 'all_users' && $isBackdatedCheck) {
                        $newProductIds = array_values($this->differences);
                        $this->callProtectedMethod($controller, 'createBackdatedUserOrganizationHistories', [
                            $this->positionId,
                            $newProductIds,
                            $originalEffectiveDate
                        ]);
                        // 🔧 CRITICAL FIX: Pass removed products ($differences2) to prevent validatePropagationCompleteness from re-adding them
                        $this->callProtectedMethod($controller, 'propagateBackdatedProductsToSubsequentDates', [
                            $this->positionId,
                            $newProductIds,
                            $originalEffectiveDate,
                            $this->differences2  // Pass removed products to exclude from propagation validation
                        ]);
                        $this->callProtectedMethod($controller, 'createBackdatedCommissionAndUpfrontSetup', [
                            $this->positionId,
                            $newProductIds,
                            $originalEffectiveDate,
                            $request
                        ]);
                    }

                    $newProductIds = array_values($this->differences);
                    if (($this->requestData['wizard_type'] ?? null) === 'all_users' &&
                        $this->callProtectedMethod($controller, 'isForwardPropagationRequired', [
                            $this->positionId,
                            $newProductIds,
                            $originalEffectiveDate
                        ])) {
                        // 🔧 CRITICAL FIX: Pass removed products to prevent validatePropagationCompleteness from re-adding them
                        $this->callProtectedMethod($controller, 'propagateExistingProductsToNewDate', [
                            $this->positionId,
                            $newProductIds,
                            $originalEffectiveDate,
                            $this->differences2  // Pass removed products
                        ]);
                        $this->callProtectedMethod($controller, 'createForwardPropagationUserOrganizationHistories', [
                            $this->positionId,
                            $newProductIds,
                            $originalEffectiveDate
                        ]);
                        $this->callProtectedMethod($controller, 'createForwardPropagationCommissionAndUpfrontSetup', [
                            $this->positionId,
                            $newProductIds,
                            $originalEffectiveDate,
                            $request
                        ]);
                    }

                } elseif (count($this->differences2) != 0) {

                    // Products being removed - need to call updatePosition to handle deletion
                    $this->callProtectedMethod($controller, 'updatePosition', [$this->positionId, $request]);

                    // 🔧 CRITICAL FIX #4: Use $originalEffectiveDate (request date) instead of tier effective_date
                    // The tier effective_date points to OLD date (2025-12-18) which still has removed products (95, 99)
                    // This causes updateOrganizationData to fetch 69 products from old date instead of 67 from new date
                    // Result: validatePropagationCompleteness sees old products and auto-adds them back!
                    $this->callProtectedMethod($controller, 'updateOrganizationData', [$this->positionId, $originalEffectiveDate]);

                    // 🚨 CRITICAL FIX: When products are removed (differences2), DON'T call propagateBackdatedProductsToSubsequentDates
                    // because validatePropagationCompleteness will see removed products in old dates and auto-add them back!
                    // The deletion in the controller already handled removing products from current AND future dates.

                    if (($this->requestData['wizard_type'] ?? null) === 'all_users' &&
                        $this->callProtectedMethod($controller, 'isBackdatedEffectiveDate', [
                            $this->positionId,
                            $this->requestData['product_id'] ?? [],
                            $originalEffectiveDate
                        ])) {
                        $this->callProtectedMethod($controller, 'createBackdatedUserOrganizationHistories', [
                            $this->positionId,
                            $this->requestData['product_id'] ?? [],
                            $originalEffectiveDate
                        ]);

                        // 🔧 SKIP propagateBackdatedProductsToSubsequentDates when products are removed
                        // The controller already deleted removed products from all future dates
                        // Calling propagation here would trigger validatePropagationCompleteness which re-adds them

                        $this->callProtectedMethod($controller, 'createBackdatedCommissionAndUpfrontSetup', [
                            $this->positionId,
                            $this->requestData['product_id'] ?? [],
                            $originalEffectiveDate,
                            $request
                        ]);
                    }


                    // 🔧 CRITICAL FIX #3: Skip forward propagation when products are removed (differences2 scenario)
                    // Forward propagation calls validatePropagationCompleteness which re-adds removed products!
                    // The controller already deleted removed products from all future dates.
                    // Commenting out this block prevents validatePropagationCompleteness from auto-adding them back.

                    // DISABLED: Forward propagation for differences2 scenario to prevent product re-addition
                    /*
                    if (($this->requestData['wizard_type'] ?? null) === 'all_users' &&
                        $this->callProtectedMethod($controller, 'isForwardPropagationRequired', [
                            $this->positionId,
                            $this->requestData['product_id'] ?? [],
                            $originalEffectiveDate
                        ])) {

                        $this->callProtectedMethod($controller, 'propagateExistingProductsToNewDate', [
                            $this->positionId,
                            $this->requestData['product_id'] ?? [],
                            $originalEffectiveDate
                        ]);
                        $this->callProtectedMethod($controller, 'createForwardPropagationUserOrganizationHistories', [
                            $this->positionId,
                            $this->requestData['product_id'] ?? [],
                            $originalEffectiveDate
                        ]);
                        $this->callProtectedMethod($controller, 'createForwardPropagationCommissionAndUpfrontSetup', [
                            $this->positionId,
                            $this->requestData['product_id'] ?? [],
                            $originalEffectiveDate,
                            $request
                        ]);
                    }
                    */

                } elseif ($isBackdated) {
                    $this->callProtectedMethod($controller, 'createBackdatedUserOrganizationHistories', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ]);
                    // 🔧 CRITICAL FIX: Pass removed products ($differences2) to prevent validatePropagationCompleteness from re-adding them
                    $this->callProtectedMethod($controller, 'propagateBackdatedProductsToSubsequentDates', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $this->differences2  // Pass removed products to exclude from propagation validation
                    ]);
                    $this->callProtectedMethod($controller, 'createBackdatedCommissionAndUpfrontSetup', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $request
                    ]);
                }

                // 🔧 CRITICAL FIX #5: Skip forward propagation for differences2 scenario (GLOBAL check)
                // This block runs AFTER all elseif scenarios, so it affects differences2 too!
                // When products are removed, forward propagation causes validatePropagationCompleteness to re-add them
                if (count($this->differences2) == 0 &&
                    ($this->requestData['wizard_type'] ?? null) === 'all_users' &&
                    $this->callProtectedMethod($controller, 'isForwardPropagationRequired', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ])) {
                    // 🔧 CRITICAL FIX: Pass removed products to prevent validatePropagationCompleteness from re-adding them
                    $this->callProtectedMethod($controller, 'propagateExistingProductsToNewDate', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $this->differences2  // Pass removed products
                    ]);
                    $this->callProtectedMethod($controller, 'createForwardPropagationUserOrganizationHistories', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate
                    ]);
                    $this->callProtectedMethod($controller, 'createForwardPropagationCommissionAndUpfrontSetup', [
                        $this->positionId,
                        $this->requestData['product_id'] ?? [],
                        $originalEffectiveDate,
                        $request
                    ]);
                }
            }

            if ($this->oldDepartmentId !== null && $this->oldDepartmentId != $departmentId) {
                Log::info('Processing department change', ['position_id' => $this->positionId]);

                $date = $originalEffectiveDate;
                OnboardingEmployees::where('sub_position_id', $this->positionId)
                    ->update(['department_id' => $departmentId]);

                $subQuery = UserOrganizationHistory::select(
                    'id', 'user_id', 'effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
                )->where('effective_date', '<=', $date);

                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())
                    ->select('user_id', 'effective_date')
                    ->where('rn', 1)->get();

                $closestDates = $results->map(function ($result) {
                    return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
                });

                $userIds = UserOrganizationHistory::where(function ($query) use ($closestDates) {
                    foreach ($closestDates as $closestDate) {
                        $query->orWhere(function ($q) use ($closestDate) {
                            $q->where('user_id', $closestDate['user_id'])
                                ->where('effective_date', $closestDate['effective_date']);
                        });
                    }
                })->where('sub_position_id', $this->positionId)
                  ->groupBy('user_id')->pluck('user_id')->toArray();

                foreach ($userIds as $userId) {
                    $futureHistories = UserDepartmentHistory::where('user_id', $userId)
                        ->where('effective_date', '>=', $date)
                        ->get();

                    if ($futureHistories->isNotEmpty()) {
                        foreach ($futureHistories as $history) {
                            $history->department_id = $departmentId;
                            $history->save();
                        }
                        continue;
                    }

                    UserDepartmentHistory::updateOrCreate(
                        ['user_id' => $userId, 'effective_date' => $date],
                        ['department_id' => $departmentId, 'updater_id' => $this->authUserId]
                    );
                }

                if (!empty($userIds)) {
                    $this->callProtectedMethod($controller, 'dispatchHistorySyncWithChunking', [
                        $userIds, $this->authUserId, 'department_change'
                    ]);
                }
            }

            if (($this->requestData['wizard_type'] ?? null) === 'all_users' &&
                Carbon::parse($originalEffectiveDate)->lt(Carbon::today())) {
                $this->callProtectedMethod($controller, 'triggerCommissionRecalculation', [
                    $this->positionId,
                    $this->requestData['product_id'] ?? [],
                    $originalEffectiveDate
                ]);
            }


            // 🔧 CRITICAL FIX: Trigger sales recalculation AFTER user histories are created
            // This ensures checkUsersProductForCalculations() finds the correct product assignments
            $salesRecalcTriggered = false;

            if ((count($this->differences) > 0 || count($this->differences2) > 0) &&
                $this->effectiveDate &&
                ($this->requestData['wizard_type'] === 'all_users' || $this->requestData['wizard_type'] === 'selective_users')) {

                $salesRecalcTriggered = true;
                $affectedProductIds = array_merge(
                    array_values($this->differences),   // Added products
                    array_values($this->differences2)   // Removed products
                );

                \Log::info('🔄 JOB: Triggering sales recalculation after user histories created', [
                    'position_id' => $this->positionId,
                    'added_products' => $this->differences,
                    'removed_products' => $this->differences2,
                    'affected_products' => $affectedProductIds
                ]);

                // Store context for ProcessRecalculatesOpenSales to send final 100% broadcast
                $contextData = [
                    'position_name' => $positionName,
                    'updated_by' => $user->name,
                    'updated_by_id' => $user->id,
                    'initiated_at' => $this->initiatedAt,
                    'unique_key' => $this->uniqueKey
                ];

                \Cache::put('position_update_context_' . $this->positionId, $contextData, 600); // 10 minutes

                \Log::info('📦 Stored position context in cache for sales job', [
                    'position_id' => $this->positionId,
                    'cache_key' => 'position_update_context_' . $this->positionId,
                    'data' => $contextData
                ]);

                // Call the protected method from controller and capture return value
                $wasDispatched = $this->callProtectedMethod(
                    $controller,
                    'triggerCommissionRecalculation',
                    [$this->positionId, array_unique($affectedProductIds), $this->effectiveDate]
                );

                // If dispatch was skipped (locked), don't wait for 100% from sales job
                if (!$wasDispatched) {
                    \Log::warning('Sales recalc was skipped - will send 100% now instead of 95%', [
                        'position_id' => $this->positionId,
                        'reason' => 'duplicate_lock_active'
                    ]);
                    $salesRecalcTriggered = false;  // Override - treat as if not triggered
                }
            }

            // Calculate completion details
            $completedAt = now()->toDateTimeString();
            $duration = \Carbon\Carbon::parse($this->initiatedAt)->diffInSeconds($completedAt);
            $durationFormatted = gmdate('i:s', $duration);

            // Decide: Send 95% or 100% based on whether sales recalc was triggered
            if ($salesRecalcTriggered) {
                // Send 95% - Sales recalculation will send final 100%
                \Log::info('Sending 95% broadcast - sales recalculation in progress', [
                    'position_id' => $this->positionId
                ]);

                try {
                    $notificationData = [
                        'positionId' => $this->positionId,
                        'positionName' => $positionName,
                        'status' => 'processing',
                        'progress' => 95,
                        'message' => "Position '{$positionName}' updated. Recalculating affected sales in background...",
                        'updatedBy' => $user->name,
                        'updatedById' => $user->id,
                        'initiatedAt' => $this->initiatedAt,
                        'completedAt' => null,
                        'uniqueKey' => $this->uniqueKey,
                        'timestamp' => now()->toISOString()
                    ];

                    broadcast(new PositionUpdateProgress($notificationData));

                    // 🆕 STORE IN REDIS (Non-blocking, graceful failure)
                    try {
                        app(NotificationService::class)->storeNotification($user->id, 'position_update', $notificationData);
                    } catch (\Exception $redisError) {
                        // Silent failure - Redis storage is optional enhancement
                    }

                    \Log::info('95% broadcast sent - waiting for sales recalculation', [
                        'position_id' => $this->positionId
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Pusher 95% broadcast failed', [
                        'error' => $e->getMessage()
                    ]);
                }

            } else {
                // No sales recalc - send final 100%
                \Log::info('Sending 100% broadcast - no sales recalculation needed', [
                    'position_id' => $this->positionId
                ]);

                try {
                    // Format user-friendly dates and duration
                    $startedDate = \Carbon\Carbon::parse($this->initiatedAt)->format('M d, Y g:i A');  // "Dec 26, 2025 11:57 AM"
                    $completedDate = now()->format('M d, Y g:i A');  // "Dec 26, 2025 11:59 AM"
                    $durationFormatted = gmdate('i\m s\s', $duration);  // "1m 18s"
                    
                    $notificationData = [
                        'positionId' => $this->positionId,
                        'positionName' => $positionName,
                        'status' => 'completed',
                        'progress' => 100,
                        'message' => "Position '{$positionName}' updated by {$user->name}. Started at {$startedDate}, completed at {$completedDate}. Duration: {$durationFormatted}",
                        'updatedBy' => $user->name,
                        'updatedById' => $user->id,
                        'initiatedAt' => $this->initiatedAt,
                        'completedAt' => $completedAt,
                        'uniqueKey' => $this->uniqueKey,
                        'salesRecalculated' => 0,
                        'salesList' => [],
                        'timestamp' => now()->toISOString()
                    ];

                    broadcast(new PositionUpdateProgress($notificationData));

                    // 🆕 STORE IN REDIS (Non-blocking, graceful failure)
                    try {
                        app(NotificationService::class)->storeNotification($user->id, 'position_update', $notificationData);
                    } catch (\Exception $redisError) {
                        // Silent failure - Redis storage is optional enhancement
                    }

                    \Log::info('100% broadcast sent successfully', [
                        'position_id' => $this->positionId
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Pusher 100% broadcast failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('ProcessPositionUpdateJob completed', ['position_id' => $this->positionId]);
            $this->sendSuccessNotification();

        } catch (Exception $e) {
            Log::error('ProcessPositionUpdateJob failed', [
                'position_id' => $this->positionId,
                'error' => $e->getMessage(),
            ]);
            $this->sendFailureNotification($e);
            throw $e;
        }
    }

    private function callProtectedMethod($object, $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    private function sendSuccessNotification(): void
    {
        try {
            // Skip email if in development or email testing is enabled (testing mode)
            if (config('app.env') === 'local' || config('mail.email_testing') === true) {
                Log::info('Email notification skipped (development/testing mode)', [
                    'position_id' => $this->positionId,
                    'notification_type' => 'success'
                ]);
                return;
            }

            $position = Positions::find($this->positionId);
            $user = \App\Models\User::find($this->authUserId);

            if ($user && $user->email && $position) {
                // Use AWS SES with IAM Role (same as offer letters)
                $mailService = app(AwsRoleMailService::class);
                $sent = $mailService->send(
                    $user->email,
                    "Position '{$position->position_name}' Updated Successfully",  // Include position name in subject
                    'emails.position-update-success',
                    [
                        'userName' => $user->first_name . ' ' . $user->last_name,
                        'positionName' => $position->position_name,
                        'completedAt' => now()->format('M d, Y g:i A'),
                        'updatedBy' => $user->first_name . ' ' . $user->last_name
                    ]
                );

                if ($sent) {
                    Log::info('Success email sent via SES', [
                        'position_id' => $this->positionId,
                        'recipient' => $user->email,
                        'mailer' => 'ses-role'
                    ]);
                }
            }
        } catch (\Swift_TransportException $e) {
            // SMTP transport errors (e.g., authentication failures)
            Log::warning('SMTP transport error sending success email', [
                'position_id' => $this->positionId,
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'note' => 'Email sending failed but job will continue'
            ]);
        } catch (\Exception $e) {
            // Catch all other exceptions (SES, network, etc.)
            Log::warning('Failed to send success email', [
                'position_id' => $this->positionId,
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'note' => 'Email sending failed but job will continue'
            ]);
        }
    }

    private function sendFailureNotification(Exception $exception): void
    {
        try {
            // Skip email if in development or email testing is enabled (testing mode)
            if (config('app.env') === 'local' || config('mail.email_testing') === true) {
                Log::info('Email notification skipped (development/testing mode)', [
                    'position_id' => $this->positionId,
                    'notification_type' => 'failure'
                ]);
                return;
            }

            $position = Positions::find($this->positionId);
            $user = \App\Models\User::find($this->authUserId);

            if ($user && $user->email && $position) {
                // Use AWS SES with IAM Role (same as offer letters)
                $mailService = app(AwsRoleMailService::class);
                $sent = $mailService->send(
                    $user->email,
                    "Position '{$position->position_name}' Update Failed",  // Include position name in subject
                    'emails.position-update-failure',
                    [
                        'userName' => $user->first_name . ' ' . $user->last_name,
                        'positionName' => $position->position_name,
                        'errorMessage' => $exception->getMessage(),
                        'failedAt' => now()->format('M d, Y g:i A')
                    ]
                );

                if ($sent) {
                    Log::info('Failure email sent via SES', [
                        'position_id' => $this->positionId,
                        'recipient' => $user->email,
                        'mailer' => 'ses-role'
                    ]);
                }
            }
        } catch (\Swift_TransportException $e) {
            // SMTP transport errors (e.g., authentication failures)
            Log::warning('SMTP transport error sending failure email', [
                'position_id' => $this->positionId,
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'note' => 'Email sending failed but job will continue'
            ]);
        } catch (\Exception $e) {
            // Catch all other exceptions (SES, network, etc.)
            Log::warning('Failed to send failure email', [
                'position_id' => $this->positionId,
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'note' => 'Email sending failed but job will continue'
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPositionUpdateJob failed permanently', [
            'position_id' => $this->positionId,
            'error' => $exception->getMessage(),
            'error_line' => $exception->getLine(),
            'error_file' => $exception->getFile(),
            'attempts' => $this->attempts(),
        ]);

        // Broadcast failure to frontend (when Pusher is implemented)
        try {
            $user = \App\Models\User::find($this->authUserId);
            $positionName = $this->requestData['position_name'] ?? 'Position';

            // Determine failure type
            $failureType = 'unknown';
            if ($exception instanceof \Illuminate\Queue\MaxAttemptsExceededException) {
                $failureType = 'max_attempts';
                $message = "Position update exceeded maximum retry attempts (tried {$this->tries} times)";
            } elseif ($exception instanceof \Illuminate\Queue\TimeOutException) {
                $failureType = 'timeout';
                $message = 'Position update exceeded maximum execution time (2 hours)';
            } else {
                $failureType = 'exception';
                $message = "Position update failed: " . $exception->getMessage();
            }

            broadcast(new PositionUpdateProgress([
                'positionId' => $this->positionId,
                'positionName' => $positionName,
                'status' => 'failed',
                'progress' => 0,
                'message' => $message,
                'updatedBy' => $user->name ?? 'System',
                'updatedById' => $user->id ?? 0,
                'initiatedAt' => $this->initiatedAt,
                'completedAt' => now()->toDateTimeString(),
                'uniqueKey' => $this->uniqueKey
            ]));

        } catch (\Exception $e) {
            Log::error('Failed to broadcast job failure', [
                'position_id' => $this->positionId,
                'broadcast_error' => $e->getMessage()
            ]);
        }

        $this->sendFailureNotification($exception);
    }
}
