<?php

namespace App\Http\Controllers\API\V2\Position;

use App\Http\Controllers\API\V2\Sales\BaseController;
use App\Events\PositionUpdateProgress;
use App\Jobs\EmploymentPackage\ApplyHistoryOnUsersV2Job;
use App\Jobs\Sales\ProcessRecalculatesOpenSales;
use App\Jobs\ProcessPositionUpdateJob;
use App\Models\AdditionalPayFrequency;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\CostCenter;
use App\Models\FrequencyType;
use App\Models\MonthlyPayFrequency;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\OnboardingEmployees;
use App\Models\PayrollDeductions;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionHirePermission;
use App\Models\PositionOverride;
use App\Models\PositionPayFrequency;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionsDeductionLimit;
use App\Models\PositionTier;
use App\Models\PositionTierOverride;
use App\Models\PositionWage;
use App\Models\SalesMaster;
use App\Models\TiersPositionCommission;
use App\Models\TiersPositionOverrides;
use App\Models\TiersPositionUpfront;
use App\Models\User;
use App\Models\UserAgreementHistory;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use App\Models\UserDepartmentHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use App\Models\Wage;
use App\Models\WeeklyPayFrequency;
use App\Services\PositionCacheService;
use Carbon\Carbon;
use App\Helpers\CustomSalesFieldHelper;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
// Enhanced imports for commission recalculation
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

class PositionController extends BaseController
{
    /**
     * Performance-optimized logging with environment checks
     * Production: Only errors/warnings to prevent I/O bottleneck
     * Local/Testing: Full logging for debugging
     */
    private function logConditional($level, $message, $context = [])
    {
        // Production: Only critical logs (errors, warnings)
        // Local/Testing: All logs for debugging
        if (in_array($level, ['error', 'warning', 'critical']) ||
            app()->environment(['local', 'testing'])) {

            // Validate log level and call appropriate method
            match ($level) {
                'emergency' => Log::emergency($message, $context),
                'alert' => Log::alert($message, $context),
                'critical' => Log::critical($message, $context),
                'error' => Log::error($message, $context),
                'warning' => Log::warning($message, $context),
                'notice' => Log::notice($message, $context),
                'info' => Log::info($message, $context),
                'debug' => Log::debug($message, $context),
                default => Log::error("Invalid log level '{$level}': {$message}", $context)
            };
        }
    }

    /**
     * Forward propagation feature flag
     * Controls whether forward propagation is enabled for position products
     */
    private const FORWARD_PROPAGATION_ENABLED = true;

    public function index(Request $request): JsonResponse
    {
        try {
            $positionId = [1];
            if (! in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
                $positionId = [1, 2, 3];
            }

            $query = Positions::with('childPositionsNew', 'group', 'payFrequency.frequencyType', 'positionDepartmentDetail')
                ->leftJoin('position_products', 'position_products.position_id', '=', 'positions.id')
                ->leftJoin('users', 'users.sub_position_id', '=', 'positions.id')
                ->leftJoin('products', 'products.id', '=', 'position_products.product_id')
                ->leftJoin('position_pay_frequencies', 'position_pay_frequencies.position_id', '=', 'positions.id')
                ->leftJoin('departments', 'departments.id', '=', 'positions.department_id')
                ->leftJoin('frequency_types', 'frequency_types.id', '=', 'position_pay_frequencies.frequency_type_id')
                ->leftJoin('group_masters', 'group_masters.id', '=', 'positions.group_id')
                ->leftJoin('position_tiers', 'position_tiers.position_id', '=', 'positions.id')
                ->select(
                    'positions.*',
                    'departments.name as department_name',
                    'frequency_types.name as freq_name',
                    'group_masters.name as group_name',
                    'position_pay_frequencies.frequency_type_id as freq_id',
                    DB::raw('COUNT(DISTINCT CASE WHEN position_products.deleted_at IS NULL AND position_products.effective_date IS NULL THEN position_products.product_id END) as product_count'),
                    DB::raw('COUNT(CASE WHEN users.dismiss = 0 THEN users.position_id END) as peoples_count'),
                    DB::raw('CASE WHEN position_tiers.id IS NOT NULL AND position_tiers.status = 1 THEN 1 ELSE 0 END as tiers_status')
                );
            if ($request->filled('pay_frequency_filter')) {
                $query->where('frequency_types.id', $request->pay_frequency_filter);
            }
            if ($request->filled('department')) {
                // $query->where('departments.name', $request->department);
                $query->where('positions.department_id', $request->department);
            }
            if ($request->filled('override_settelement')) {
                $query->join('position_reconciliations', 'position_reconciliations.position_id', 'positions.id')
                    ->where('position_reconciliations.override_settlement', $request->override_settelement);
            }
            if ($request->filled('worker_type')) {
                $query->where('positions.worker_type', $request->worker_type);
            }
            if ($request->filled('permission_group')) {
                $query->where('group_masters.id', $request->permission_group);
            }
            if ($request->filled('eligible_products')) {
                $query->where('products.id', $request->eligible_products);
            }
            if ($request->filled('search_filter')) {
                $searchTerm = $request->input('search_filter');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('positions.position_name', 'LIKE', '%'.$searchTerm.'%');
                });
            }
            $query->whereNotIn('positions.id', $positionId);
            $query->where('position_name', '!=', 'Super Admin');
            $query->groupBy('positions.id', 'departments.name', 'frequency_types.name', 'group_masters.name', 'position_pay_frequencies.frequency_type_id');
            $positionData = $query->paginate($request->input('per_page', $request->input('perpage', 10)));

            $response_data = [];
            $positions = $positionData->getCollection();
            foreach ($positions as $position) {
                $data = $this->recursionPosition($position);
                if (is_array($data) && count($data) != 0) {
                    foreach ($data as $data) {
                        $check = collect($response_data)->where('id', $data['id'])->values();
                        if (count($check) == 0) {
                            $response_data[] = $data;
                        }
                    }
                }
            }
            $data = $positionData->toArray();
            $data['data'] = $response_data;

            return response()->json([
                'ApiName' => 'position product api',
                'status' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'products',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Optimized version of the position index method
     * Route: GET /api/v2/position/position
     *
     * Key optimizations:
     * - Separated complex queries into efficient separate queries
     * - Added caching with 5-minute TTL
     * - Optimized eager loading with minimal relationships
     * - Performance monitoring with execution time logging - commented
     * - Smart cache invalidation on data changes
     *
     * Expected performance improvement: 60-90% faster than original
     */
    public function indexOptimized(Request $request)
    {
        try {
            /*$startTime = microtime(true);
            // Performance optimization: Removed verbose logging to eliminate I/O bottleneck
            */ // for testing

            // Use PositionCacheService to handle all cache operations with versioning
            $result = PositionCacheService::remember($request, function () use ($request) {
                return $this->getOptimizedPositionsData($request);
            });

            /*$executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            $wasFromCache = Cache::has($cacheKey) ? 'yes' : 'no';

            // Performance optimization: Removed verbose completion logging
            */ // for testing

            return $result;
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'position product api optimized',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get optimized positions data
     */
    private function getOptimizedPositionsData(Request $request)
    {
        $positionId = [1];
        if (! in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
            $positionId = [1, 2, 3];
        }

        // Base query with minimal joins
        $query = Positions::select([
            'positions.id',
            'positions.position_name',
            'positions.department_id',
            'positions.group_id',
            'positions.worker_type',
            'positions.setup_status',
            'positions.created_at',
            'positions.updated_at',
        ])
            ->with([
                'childPositionsNew',
                'positionDepartmentDetail:id,name',
                'group:id,name',
                'payFrequency.frequencyType:id,name',
            ])
            ->whereNotIn('positions.id', $positionId)
            ->where('positions.position_name', '!=', 'Super Admin');

        // Apply filters efficiently
        $this->applyFiltersOptimized($query, $request);

        $positionsData = $query->paginate($request->input('perpage', 10));

        $response_data = [];
        if ($positionsData->count() > 0) {
            // Collect all position IDs including child positions
            $allPositionIds = $this->collectAllPositionIds($positionsData->getCollection());

            // Get product details and counts together
            $productResult = $this->getBulkProductDetailsWithCounts($allPositionIds);
            $productDetailsMap = $productResult['details'];
            $productCounts = $productResult['counts'];

            // Product counts are reused from above, eliminating duplicate query
            // Get remaining aggregated data (people counts and tiers only)
            $aggregatedData = $this->getAggregatedCounts($allPositionIds, $productCounts);

            // Process positions with hierarchy (matching original API logic)
            $positions = $positionsData->getCollection();
            foreach ($positions as $position) {
                $data = $this->recursionPositionOptimized($position, $aggregatedData, $productDetailsMap);
                if (is_array($data) && count($data) != 0) {
                    foreach ($data as $positionData) {
                        $check = collect($response_data)->where('id', $positionData['id'])->values();
                        if (count($check) == 0) {
                            $response_data[] = $positionData;
                        }
                    }
                }
            }
        }

        // Replace the paginated data with processed response data
        $dataArray = $positionsData->toArray();
        $dataArray['data'] = $response_data;

        return response()->json([
            'ApiName' => 'position product api optimized',
            'status' => true,
            'data' => $dataArray,
        ]);
    }

    /**
     * Apply filters to the optimized query
     */
    private function applyFiltersOptimized($query, Request $request)
    {
        if ($request->filled('department')) {
            $query->where('positions.department_id', $request->department);
        }

        if ($request->filled('permission_group')) {
            $query->where('positions.group_id', $request->permission_group);
        }

        if ($request->filled('worker_type')) {
            $query->where('positions.worker_type', $request->worker_type);
        }

        if ($request->filled('search_filter')) {
            $searchTerm = $request->input('search_filter');
            $query->where('positions.position_name', 'LIKE', '%'.$searchTerm.'%');
        }

        // For filters that require joins, we'll handle them separately
        if ($request->filled('pay_frequency_filter')) {
            $query->whereHas('payFrequency.frequencyType', function ($q) use ($request) {
                $q->where('frequency_types.id', $request->pay_frequency_filter);
            });
        }

        if ($request->filled('override_settelement')) {
            $query->whereHas('reconciliation', function ($q) use ($request) {
                $q->where('override_settlement', $request->override_settelement);
            });
        }

        if ($request->filled('eligible_products')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_id', $request->eligible_products)
                    ->whereNull('deleted_at');
            });
        }
    }

    /**
     * Get aggregated counts efficiently (optimized version - reuses product counts)
     */
    private function getAggregatedCounts(array $positionIds, array $productCounts): array
    {
        $effectiveDate = date('Y-m-d');

        // Get people counts
        $peopleCounts = DB::table('users')
            ->select('sub_position_id', DB::raw('COUNT(*) as count'))
            ->whereIn('sub_position_id', $positionIds)
            ->where('dismiss', 0)
            ->groupBy('sub_position_id')
            ->pluck('count', 'sub_position_id')
            ->toArray();

        // Get tier status
        $tiersStatus = DB::table('position_tiers')
            ->whereIn('position_id', $positionIds)
            ->where('status', 1)
            ->where('effective_date', '<=', $effectiveDate)
            ->pluck('position_id')
            ->flip()
            ->map(fn () => 1)
            ->toArray();

        return [
            'products' => $productCounts,  // Reused from product details fetch
            'people' => $peopleCounts,
            'tiers' => $tiersStatus,
        ];
    }

    /**
     * Format position data efficiently
     */
    private function formatOptimizedPosition($position, array $aggregatedData, array $productDetailsMap = []): array
    {
        // Get product details from the bulk fetch map
        $productDetails = $productDetailsMap[$position->id] ?? [];

        return [
            'id' => $position->id,
            'status' => $position->setup_status,
            'position' => isset($position->position_name) ? $position->position_name : null,
            'worker_type' => isset($position->worker_type) ? $position->worker_type : null,
            'people' => $aggregatedData['people'][$position->id] ?? 0,
            'group_id' => isset($position->group->id) ? $position->group->id : null,
            'group_name' => isset($position->group->name) ? $position->group->name : null,
            'frequency_type_id' => isset($position->payFrequency->frequencyType->id) ? $position->payFrequency->frequencyType->id : null,
            'pay_frequency' => isset($position->payFrequency->frequencyType->name) ? $position->payFrequency->frequencyType->name : null,
            'department_id' => isset($position->positionDepartmentDetail->id) ? $position->positionDepartmentDetail->id : null,
            'Department' => isset($position->positionDepartmentDetail->name) ? $position->positionDepartmentDetail->name : null,
            'Product_count' => $aggregatedData['products'][$position->id] ?? 0,
            'product_details' => $productDetails,
            'tiers_status' => $aggregatedData['tiers'][$position->id] ?? 0,
        ];
    }

    /**
     * Get bulk product details and counts for multiple positions (optimized to reduce queries)
     */
    private function getBulkProductDetailsWithCounts(array $positionIds): array
    {
        $effectiveDate = date('Y-m-d');
        $productDetailsMap = [];
        $productCounts = [];

        // Initialize empty arrays for all positions
        foreach ($positionIds as $positionId) {
            $productDetailsMap[$positionId] = [];
            $productCounts[$positionId] = 0;
        }

        // Process each position individually to match original positionFormatting logic exactly
        foreach ($positionIds as $positionId) {
            $productData = [];

            // Step 1: Find the latest effective date for this position (matching original logic line 554)
            $latestEffectiveProduct = PositionProduct::where('position_id', $positionId)
                ->where('effective_date', '<=', $effectiveDate)
                ->whereNull('deleted_at')
                ->orderBy('effective_date', 'DESC')
                ->first();

            if ($latestEffectiveProduct) {
                // Step 2: Get ALL products with that specific effective date (matching original logic line 556)
                $positionProducts = PositionProduct::with('productName')
                    ->where('position_id', $positionId)
                    ->where('effective_date', $latestEffectiveProduct->effective_date)
                    ->whereNull('deleted_at')
                    ->get();
            } else {
                // Step 3: Get ALL products with NULL effective_date (matching original logic line 558)
                $positionProducts = PositionProduct::with('productName')
                    ->where('position_id', $positionId)
                    ->whereNull('effective_date')
                    ->whereNull('deleted_at')
                    ->get();
            }

            // Format products exactly like original method
            foreach ($positionProducts as $product) {
                $productData[] = [
                    'id' => $product->product_id,
                    'name' => $product->product->name,
                    'product_id' => $product->product->product_id,
                    'milestone_schema_id' => $product->product->milestone_schema_id,
                    'clawback_exempt_on_ms_trigger_id' => $product->product->clawback_exempt_on_ms_trigger_id,
                    'effective_date' => $product->product->effective_date,
                    'status' => $product->product->status,
                ];
            }

            $productDetailsMap[$positionId] = $productData;
            $productCounts[$positionId] = count($productData);
        }

        return [
            'details' => $productDetailsMap,
            'counts' => $productCounts,
        ];
    }

    /**
     * Collect all position IDs including child positions recursively
     */
    private function collectAllPositionIds($positions): array
    {
        $allIds = [];

        foreach ($positions as $position) {
            $allIds[] = $position->id;
            $allIds = array_merge($allIds, $this->collectChildPositionIds($position));
        }

        return array_unique($allIds);
    }

    /**
     * Recursively collect child position IDs
     */
    private function collectChildPositionIds($position): array
    {
        $childIds = [];

        if (isset($position->childPositionsNew) && $position->childPositionsNew->count() > 0) {
            foreach ($position->childPositionsNew as $child) {
                $childIds[] = $child->id;
                $childIds = array_merge($childIds, $this->collectChildPositionIds($child));
            }
        }

        return $childIds;
    }

    /**
     * Optimized recursion for processing position hierarchy
     */
    protected function recursionPositionOptimized($position, array $aggregatedData, array $productDetailsMap, &$data = [])
    {
        $childPositions = $position->childPositionsNew;
        if (count($childPositions) != 0) {
            $data[] = $this->formatOptimizedPosition($position, $aggregatedData, $productDetailsMap);
            foreach ($childPositions as $child) {
                $this->recursionPositionOptimized($child, $aggregatedData, $productDetailsMap, $data);
            }
        } else {
            $data[] = $this->formatOptimizedPosition($position, $aggregatedData, $productDetailsMap);
        }

        return $data;
    }

    /**
     * Retrieve users by office ID with optimized queries, optionally filtered by position ID
     *
     * @param  int  $id  Office ID (use 0 to get all users without office filter)
     * @param  int|null  $position_id  Position ID (optional)
     */
    public function usersByOfficeID(Request $request, int $id, ?int $position_id = null): \Illuminate\Http\JsonResponse
    {
        try {
            // Validate input parameters (allow 0 for getting all users)
            if ($id === null || $id === '') {
                $this->logConditional('warning', 'Invalid parameters for usersByOfficeID', [
                    'office_id' => $id,
                    'position_id' => $position_id,
                    'user_id' => auth()->user()->id ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid office ID',
                    'message' => 'Please provide valid office ID',
                ], 400);
            }

            // Optimized query with eager loading to prevent N+1 queries
            $query = User::where([
                'dismiss' => 0,
                'terminate' => 0,
            ]);

            // Filter by office_id only if $id is not 0 (0 means get all users)
            if ($id != 0) {
                $query->where('office_id', $id);
            }

            // Add position filter only if position_id is provided
            if ($position_id !== null) {
                $query->where('sub_position_id', $position_id);
            }

            $users = $query->with(['positionDetail'])
                ->orderBy('first_name', 'ASC')
                ->get();

            $filtered_data = [];

            if ($users->count() > 0) {
                // Get all user organization histories in a single query
                $user_ids = $users->pluck('id')->toArray();
                $orgHistoryQuery = UserOrganizationHistory::whereIn('user_id', $user_ids);

                // Add position filter only if position_id is provided
                if ($position_id !== null) {
                    $orgHistoryQuery->where('sub_position_id', $position_id);
                }

                $organization_histories = $orgHistoryQuery
                    ->with(['subposition:id,position_name']) // Eager load position relationship
                    ->get()
                    ->keyBy('user_id'); // Key by user_id for efficient lookup

                foreach ($users as $user) {
                    $user_organization_history = $organization_histories->get($user->id);

                    // Only include users where UserOrganizationHistory exists
                    if ($user_organization_history) {
                        $filtered_data[] = [
                            'id' => $user->id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'email' => $user->email,
                            'image' => $user->image,
                            'image_s3' => null, // Will be populated below
                            'sub_position_name' => $user_organization_history->subposition?->position_name ?? '',
                        ];
                    }
                }
            }

            // Process image URLs for filtered data
            foreach ($filtered_data as $key => $user_data) {
                if (isset($user_data['image']) && $user_data['image'] != null) {
                    $filtered_data[$key]['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$user_data['image']);
                } else {
                    $filtered_data[$key]['image_s3'] = null;
                }
            }
            // Dynamic message based on filtering
            $message = $id == 0 ? 'All users retrieved successfully' : "Users for office ID {$id} retrieved successfully";

            // Performance: Removed verbose info logging
            return response()->json([
                'success' => true,
                'ApiName' => 'usersByOfficeID',
                'status' => true,
                'message' => $message,
                'data' => $filtered_data,
            ], 200);

        } catch (Exception $e) {
            $this->logConditional('error', 'Failed to retrieve users by office'.($position_id ? ' and position' : ''), [
                'office_id' => $id,
                'position_id' => $position_id,
                'error' => $e->getMessage(),
                'user_id' => auth()->user()->id ?? null,
                'operation' => 'users_by_office_id',
            ]);

            return response()->json([
                'success' => false,
                'ApiName' => 'usersByOfficeID',
                'status' => false,
                'error' => 'Failed to retrieve users',
                'message' => 'Please try again later',
            ], 500);
        }
    }

    protected function recursionPosition($position, &$data = [])
    {
        $childPositions = $position->childPositionsNew;
        if (count($childPositions) != 0) {
            $data[] = $this->positionFormatting($position);
            foreach ($childPositions as $child) {
                $this->recursionPosition($child, $data);
            }
        } else {
            $data[] = $this->positionFormatting($position);
        }

        return $data;
    }

    protected function positionFormatting($position)
    {
        $effectiveDate = date('Y-m-d');
        $positionProducts = PositionProduct::where(['position_id' => $position->id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
        if ($positionProducts) {
            $positionProducts = PositionProduct::with('productName')->where(['position_id' => $position->id, 'effective_date' => $positionProducts->effective_date])->get();
        } else {
            $positionProducts = PositionProduct::with('productName')->where(['position_id' => $position->id])->whereNull('effective_date')->get();
        }
        $productData = [];
        $productCount = 0;
        foreach ($positionProducts as $products) {
            $productData[] = [
                'id' => $products->product_id,
                'name' => $products->product->name,
                'product_id' => $products->product->product_id,
                'milestone_schema_id' => $products->product->milestone_schema_id,
                'clawback_exempt_on_ms_trigger_id' => $products->product->clawback_exempt_on_ms_trigger_id,
                'effective_date' => $products->product->effective_date,
                'status' => $products->product->status,
            ];
            $productCount = $productCount + 1;
        }

        $peoplesCount = User::where(['sub_position_id' => $position->id, 'dismiss' => 0])->count();
        $effectiveDate = null;
        $positionTier = PositionTier::where('position_id', $position->id)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if ($positionTier) {
            $effectiveDate = $positionTier->effective_date;
        }

        $positionTiers = PositionTier::where(['position_id' => $position->id, 'status' => 1, 'effective_date' => $effectiveDate])->exists() ? 1 : 0;

        return [
            'id' => $position->id,
            'status' => $position->setup_status,
            'position' => isset($position->position_name) ? $position->position_name : null,
            'worker_type' => isset($position->worker_type) ? $position->worker_type : null,
            'people' => $peoplesCount,
            'group_id' => isset($position->group->id) ? $position->group->id : null,
            'group_name' => isset($position->group->name) ? $position->group->name : null,
            'frequency_type_id' => isset($position->payFrequency->frequencyType->id) ? $position->payFrequency->frequencyType->id : null,
            'pay_frequency' => isset($position->payFrequency->frequencyType->name) ? $position->payFrequency->frequencyType->name : null,
            'department_id' => isset($position->positionDepartmentDetail->id) ? $position->positionDepartmentDetail->id : null,
            'Department' => isset($position->positionDepartmentDetail->name) ? $position->positionDepartmentDetail->name : null,
            'Product_count' => $productCount,
            'product_details' => $productData,
            'tiers_status' => $positionTiers,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $departmentId = $request->input('department_id');
            $validationArr = [
                'position_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('positions', 'position_name')
                        ->where(function ($query) use ($departmentId) {
                            $query->where('department_id', $departmentId);
                        })
                        ->whereNotIn('id', [1, 2, 3]),
                ],
                'product_id' => 'required|array|min:1',
                'worker_type' => 'required',
                'pay_frequency' => 'required',
                'permission_group_id' => 'required',
                'department_id' => 'required',
                'main_role' => 'required',
            ];

            $validationArr['offer_letter_template_id'] = 'nullable|array';

            $validator = validator::make($request->all(), $validationArr);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            if (! in_array(config('app.domain_name'), ['hawx', 'hawxw2'])) {
                if (isset($request->offer_letter_template_id) && count($request->offer_letter_template_id) > 1) {
                    throw new Exception('This server is not supporting multiple offer letter for a position');
                }
            }

            $parentId = 2;
            if ($request->main_role == 3) {
                $parentId = 3;
            }

            $positionDataForCreate = [
                'position_name' => $request->position_name,
                'worker_type' => $request->worker_type,
                'department_id' => $request->department_id,
                'parent_id' => $parentId,
                'org_parent_id' => $request->parent_position_id,
                'group_id' => $request->permission_group_id,
                'is_selfgen' => $request->main_role,
                'can_act_as_both_setter_and_closer' => $request->main_role,
            ];
            if ($request->filled('offer_letter_template_id')) {
                if (isset($request->offer_letter_template_id[0])) {
                    $positionDataForCreate['offer_letter_template_id'] = $request->offer_letter_template_id[0];
                }
            }

            if (isset($positionDataForCreate['offer_letter_template_id']) && ! $positionDataForCreate['offer_letter_template_id']) {
                unset($positionDataForCreate['offer_letter_template_id']);
            }

            $data = Positions::create($positionDataForCreate);
            $positionId = $data->id;
            Positions::where('id', $positionId)->update(['order_by' => $positionId]);

            foreach ($request->product_id as $product) {
                PositionProduct::create([
                    'position_id' => $positionId,
                    'product_id' => $product,
                ]);
            }

            Wage::create([
                'position_id' => $positionId,
            ]);

            PositionPayFrequency::create([
                'position_id' => $positionId,
                'frequency_type_id' => $request->pay_frequency,
            ]);

            if ($request->filled('offer_letter_template_id')) {

                NewSequiDocsTemplatePermission::where([
                    'position_id' => $positionId,
                    'position_type' => 'receipient',
                    'category_id' => 1,
                ])->delete();

                foreach ($request['offer_letter_template_id'] as $template_id) {
                    NewSequiDocsTemplatePermission::create([
                        'template_id' => $template_id,
                        'category_id' => 1,
                        'position_id' => $positionId,
                        'position_type' => 'receipient',
                    ]);
                }
            }

            $companySettingTiers = CompanySetting::where(['type' => 'tier', 'status' => '1'])->first();
            if ($companySettingTiers) {
                $statuses = [
                    'commission' => [
                        'status' => $request->tiers_commission_status,
                        'schema_id' => $request->tiers_commission_schema_id,
                        'advancement' => $request->tier_commission_advancement,
                        'type' => 'commission',
                    ],
                    'upfront' => [
                        'status' => $request->tiers_upfront_status,
                        'schema_id' => $request->tiers_upfront_schema_id,
                        'advancement' => $request->tier_upfront_advancement,
                        'type' => 'upfront',
                    ],
                    'override' => [
                        'status' => $request->tiers_override_status,
                        'schema_id' => $request->tiers_override_schema_id,
                        'advancement' => $request->tier_override_advancement,
                        'type' => 'override',
                    ],
                ];

                foreach ($statuses as $data) {
                    $status = 0;
                    if ($data['status'] == 1) {
                        $status = 1;
                    }
                    PositionTier::create([
                        'position_id' => $positionId,
                        'tiers_schema_id' => $data['schema_id'],
                        'tier_advancement' => $data['advancement'],
                        'status' => $status,
                        'type' => $data['type'],
                    ]);
                }
            }

            DB::commit();

            // Clear positions cache after successful creation
            PositionCacheService::clear();

            return response()->json([
                'ApiName' => 'add-position',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => ['id' => $positionId],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'api/v2/position/add-position',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function edit($id): JsonResponse
    {
        try {
            $position = Positions::with(['payFrequency', 'allAssociatedOfferLettersWithTemplate'])->withCount('peoples')->find($id);
            if (! $position) {
                return response()->json([
                    'status' => false,
                    'ApiName' => 'edit-position-products',
                    'message' => 'Position not found!!',
                ], 400);
            }

            // Initialize tier data with default values
            $tiersData = [
                'tiers_commission_status' => false,
                'tiers_commission_schema_id' => null,
                'tier_commission_advancement' => null,
                'tier_commission_type' => 'commission',
                'tiers_upfront_status' => false,
                'tiers_upfront_schema_id' => null,
                'tier_upfront_advancement' => null,
                'tier_upfront_type' => 'upfront',
                'tiers_override_status' => false,
                'tiers_override_schema_id' => null,
                'tier_override_advancement' => null,
                'tier_override_type' => 'override',
            ];

            // Dynamically set values for available tiers
            $effectiveDate = null;
            $positionTier = PositionTier::where('position_id', $position->id)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($positionTier) {
                $effectiveDate = $positionTier->effective_date;
            }
            $positionTiers = PositionTier::where(['position_id' => $position->id, 'status' => 1, 'effective_date' => $effectiveDate])->get();
            foreach ($positionTiers ?? [] as $tier) {
                $keyPrefix = "tiers_{$tier->type}";

                $tiersData["{$keyPrefix}_schema_id"] = $tier->tiers_schema_id ?? null;
                $tiersData["tier_{$tier->type}_advancement"] = $tier->tier_advancement ?? null;
                $tiersData["tier_{$tier->type}_type"] = $tier->type ?? null;
            }

            // Set status dynamically based on schema presence
            $tiersData['tiers_commission_status'] = ! empty($tiersData['tiers_commission_schema_id']);
            $tiersData['tiers_upfront_status'] = ! empty($tiersData['tiers_upfront_schema_id']);
            $tiersData['tiers_override_status'] = ! empty($tiersData['tiers_override_schema_id']);

            $effectiveDate = date('Y-m-d');
            $positionProducts = PositionProduct::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if ($positionProducts) {
                $positionProducts = PositionProduct::with('productName')->where(['position_id' => $id, 'effective_date' => $positionProducts->effective_date])->get();
            } else {
                $positionProducts = PositionProduct::with('productName')->where(['position_id' => $id])->whereNull('effective_date')->get();
            }

            $data = array_merge([
                'id' => $position->id,
                'position_name' => $position->position_name,
                'worker_type' => $position->worker_type,
                'pay_frequency' => $position?->payFrequency?->frequency_type_id,
                'main_role' => $position->is_selfgen,
                'permission_group_id' => $position->group_id,
                'department_id' => $position->department_id,
                'parent_position_id' => $position->org_parent_id,
                'offer_letter_template_id' => $position->offer_letter_template_id,
                'product' => $positionProducts,
                'people' => $position->peoples_count,
                'offer_letter' => $position->allAssociatedOfferLettersWithTemplate ?? [],
            ], $tiersData);

            return response()->json([
                'ApiName' => 'edit-position -products',
                'status' => true,
                'data' => $data,
                'message' => 'Position data retrieved successfully!',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'edit-position -products',
                'status' => false,
                'message' => $e->getMessage().' at line '.$e->getLine(),
            ], 400);
        }
    }

    public function update(Request $request, $id)
    {

        // Performance optimization: Increase memory and execution time limits
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        try {

            DB::beginTransaction();
            $departmentId = $request->input('department_id');
            $validationArr = [
                'position_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('positions', 'position_name')
                        ->where(function ($query) use ($departmentId) {
                            $query->where('department_id', $departmentId);
                        })->whereNotIn('id', [1, 2, 3])->ignore($id),
                ],
                'wizard_type' => 'required|in:only_new_users,all_users,selective_users',
                'effective_date' => 'required_if:wizard_type,all_users',
                'product_id' => 'required|array|min:1',
                'permission_group_id' => 'required',
                'department_id' => 'required',
                'commission' => 'nullable|array',
                'commission.*.product_id' => 'required',
                // 'commission.*.to_all_users' => 'required|in:0,1',
                'commission.*.to_all_users' => 'nullable|in:0,1|required_if:to_all_users,1',
                'commission.*.commission_status' => 'required|in:0,1',
                'commission.*.data' => 'required_if:commission.*.commission_status,1',
                'upfront' => 'nullable|array',
                'upfront.*.product_id' => 'required',
                // 'upfront.*.to_all_users' => 'required|in:0,1',
                'upfront.*.to_all_users' => 'nullable|in:0,1|required_if:to_all_users,1',
                'upfront.*.upfront_status' => 'required|in:0,1',
                'upfront.*.data' => 'required_if:upfront.*.upfront_status,1',
                'overrides' => 'nullable|array',
                'overrides.*.product_id' => 'required',
                // 'overrides.*.to_all_users' => 'required|in:0,1',
                'overrides.*.to_all_users' => 'nullable|in:0,1|required_if:to_all_users,1',
                'overrides.*.status' => 'required|in:0,1',
                'overrides.*.override' => 'required_if:overrides.*.override,1',
                'settlement' => 'nullable|array',
                'settlement.*.product_id' => 'required',
                // 'settlement.*.to_all_users' => 'required|in:0,1',
                'settlement.*.to_all_users' => 'nullable|in:0,1|required_if:to_all_users,1',
                'settlement.*.status' => 'required|in:0,1',
            ];

            if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {
                $validationArr['offer_letter_template_id'] = 'nullable|array';
            }

            $validator = validator::make($request->all(), $validationArr);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }


            // Initialize Pusher tracking variables
            $user = auth()->user();
            $initiatedAt = now()->toIso8601String();  // ISO 8601 with timezone
            $uniqueKey = $id . '_' . time();

            // Broadcast: Update started (0%)
            try {
                broadcast(new PositionUpdateProgress([
                    'positionId' => (int)$id,
                    'positionName' => $request->position_name,
                    'status' => 'started',
                    'progress' => 0,
                    'message' => "Position '{$request->position_name}' update initiated by {$user->name}",
                    'updatedBy' => $user->name,
                    'updatedById' => $user->id,
                    'initiatedAt' => $initiatedAt,
                    'completedAt' => null,
                    'uniqueKey' => $uniqueKey
                ]));
            } catch (\Exception $e) {
                // Silently fail if Pusher is not configured
                \Log::debug('Pusher broadcast failed (may not be configured)', ['error' => $e->getMessage()]);
            }

            $position = Positions::where('id', $id)->first();
            if (! $position) {
                return response()->json([
                    'ApiName' => 'add-position',
                    'status' => false,
                    'message' => 'Position not found!!',
                    'data' => [],
                ], 400);
            }

            $request['effective_date'] = $request->effective_date ? $request->effective_date : date('Y-m-d');
            $effectiveDate = $request->effective_date;
            
            // Security: Block past dates for all_users mode
            if ($request->wizard_type === 'all_users' && $effectiveDate && Carbon::parse($effectiveDate)->lt(Carbon::today())) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'position-update',
                    'status' => false,
                    'message' => 'Backdating not allowed for security reasons. Please use today\'s date or future dates.',
                    'data' => []
                ], 403);
            }
            
            $currentProduct = PositionProduct::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if ($currentProduct) {
                $currentProduct = PositionProduct::where(['position_id' => $id, 'effective_date' => $currentProduct->effective_date])->pluck('product_id')->toArray();
            } else {
                $currentProduct = PositionProduct::where(['position_id' => $id])->whereNull('effective_date')->pluck('product_id')->toArray();
            }
            $requestProduct = $request->product_id;
            $differences = array_diff($requestProduct, $currentProduct);
            $differences2 = array_diff($currentProduct, $requestProduct);


            // Broadcast: Differences calculated (30%)
            try {
                broadcast(new PositionUpdateProgress([
                    'positionId' => (int)$id,
                    'positionName' => $request->position_name,
                    'status' => 'processing',
                    'progress' => 30,
                    'message' => "Processing " . count($differences) . " new and " . count($differences2) . " removed products",
                    'updatedBy' => $user->name,
                    'updatedById' => $user->id,
                    'initiatedAt' => $initiatedAt,
                    'completedAt' => null,
                    'uniqueKey' => $uniqueKey
                ]));
            } catch (\Exception $e) {
                \Log::debug('Pusher broadcast failed', ['error' => $e->getMessage()]);
            }

            // Product differences calculated for position update

            $statuses = [
                [
                    'status' => $request->tiers_commission_status ?? null,
                    'schema_id' => $request->tiers_commission_schema_id ?? null,
                    'advancement' => $request->tier_commission_advancement ?? null,
                    'type' => 'commission',
                    'effective_date' => $effective_date ?? date('Y-m-d'),
                ],
                [
                    'status' => $request->tiers_upfront_status ?? null,
                    'schema_id' => $request->tiers_upfront_schema_id ?? null,
                    'advancement' => $request->tier_upfront_advancement ?? null,
                    'type' => 'upfront',
                    'effective_date' => $effective_date ?? date('Y-m-d'),
                ],
                [
                    'status' => $request->tiers_override_status ?? null,
                    'schema_id' => $request->tiers_override_schema_id ?? null,
                    'advancement' => $request->tier_override_advancement ?? null,
                    'type' => 'override',
                    'effective_date' => $effective_date ?? date('Y-m-d'),
                ],
            ];

            // Processing position update request

            $reqTiers = [];
            foreach ($statuses as $data) {
                $status = 0;
                if ($data['status'] == 1) {
                    $reqTiers[] = $data['schema_id'];
                }
            }


            if (count($differences) != 0) {
                // 🔧 CRITICAL FIX: When products change, detect removed products and delete from future dates
                $currentProduct = PositionProduct::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
                if ($currentProduct) {
                    $existingProducts = PositionProduct::where(['position_id' => $id, 'effective_date' => $currentProduct->effective_date])->pluck('product_id')->toArray();
                } else {
                    $existingProducts = PositionProduct::where(['position_id' => $id])->whereNull('effective_date')->pluck('product_id')->toArray();
                }
                $removedProducts = array_diff($existingProducts, $request->product_id);

                // Delete from position_products (only if products were removed)
                if (!empty($removedProducts)) {

                    // Delete removed products from current AND all future effective dates
                    // 🔧 CRITICAL FIX: Use array_values() because array_diff() preserves keys
                    $deletedCount = PositionProduct::where('position_id', $id)
                        ->where('effective_date', '>=', $effectiveDate)
                        ->whereIn('product_id', array_values($removedProducts))
                        ->delete();

                }
                
                // 🔧 CRITICAL: Delete user_organization_history for ALL products >= effective_date
                // This is THE key table checked by checkUsersProductForCalculations()
                // When products are removed, sales recalc won't find them → uses DEFAULT product
                // We delete ALL (not just removed) because updateUserHistories() will recreate correct set
                // Other history tables (commission/upfront/override/withheld) are UPDATED, not deleted (audit trail!)
                
                // Get all users in this position
                $userIds = User::where('sub_position_id', $id)->pluck('id')->toArray();
                
                if (!empty($userIds)) {
                    // Delete ONLY user_organization_history >= effective_date
                    // This table determines which product to use for commission calculation
                    $organizationDeleted = UserOrganizationHistory::whereIn('user_id', $userIds)
                        ->where('sub_position_id', $id)
                        ->where('effective_date', '>=', $effectiveDate)
                        ->delete();
                    
                    \Log::info('🗑️ [Path A] Deleted user_organization_history >= effective_date', [
                        'position_id' => $id,
                        'effective_date' => $effectiveDate,
                        'removed_products' => array_values($removedProducts),
                        'user_count' => count($userIds),
                        'organization_rows' => $organizationDeleted,
                        'note' => 'updateUserHistories() will recreate. Other tables (commission/upfront/override/withheld) are UPDATED not deleted (preserves audit trail)'
                    ]);
                }

                // Delete current effective date products to reset
                PositionProduct::where(['position_id' => $id, 'effective_date' => $effectiveDate])->delete();

                // 🔧 FIX: DISABLE chronological filtering for explicit user requests
                // When user explicitly requests products, they should ALL be added to the requested effective date
                // Chronological filtering should only apply to automatic propagation
                $chronologicallyValidProducts = $request->product_id;
                $chronologicalFilteringDetails = [];

                /* CHRONOLOGICAL FILTERING DISABLED - User explicitly requested these products
                foreach ($request->product_id as $product) {
                    // 🔧 CRITICAL FIX: Check if product exists with NULL effective_date (original products)
                    $hasNullEffectiveDate = PositionProduct::where('position_id', $id)
                        ->where('product_id', $product)
                        ->whereNull('effective_date')
                        ->exists();

                    // Check earliest dated effective date (excluding NULL)
                    $earliestProductDate = PositionProduct::where('position_id', $id)
                        ->where('product_id', $product)
                        ->whereNotNull('effective_date')
                        ->min('effective_date');

                    $isNewProduct = in_array($product, $differences);
                    $shouldInclude = false;
                    $reason = '';

                    // Include product if:
                    // 1. It exists with NULL effective_date (original products - ALWAYS include)
                    // 2. It has no previous effective date (new products with no history)
                    // 3. Its earliest effective date is <= current effective date
                    if ($hasNullEffectiveDate) {
                        $shouldInclude = true;
                        $reason = 'original_product_with_null_effective_date';
                    } elseif (! $earliestProductDate) {
                        $shouldInclude = true;
                        $reason = 'no_previous_effective_date';
                    } elseif ($earliestProductDate <= $effectiveDate) {
                        $shouldInclude = true;
                        $reason = 'earliest_date_valid';
                    } else {
                        $shouldInclude = false;
                        $reason = $isNewProduct ? 'new_product_but_chronologically_invalid' : 'future_product_filtered_out';
                    }

                    if ($shouldInclude) {
                        $chronologicallyValidProducts[] = $product;
                    }

                    $chronologicalFilteringDetails[] = [
                        'product_id' => $product,
                        'has_null_effective_date' => $hasNullEffectiveDate,
                        'earliest_effective_date' => $earliestProductDate,
                        'is_new_product' => $isNewProduct,
                        'should_include' => $shouldInclude,
                        'reason' => $reason,
                    ];
                }
                */  // End of disabled chronological filtering

                foreach ($chronologicallyValidProducts as $product) {
                    PositionProduct::create([
                        'position_id' => $id,
                        'product_id' => $product,
                        'effective_date' => $effectiveDate,
                    ]);
                }


                // Broadcast: Products created (50%)
                try {
                    broadcast(new PositionUpdateProgress([
                        'positionId' => (int)$id,
                        'positionName' => $request->position_name,
                        'status' => 'processing',
                        'progress' => 50,
                        'message' => "Products updated in database (" . count($chronologicallyValidProducts) . " products on {$effectiveDate})",
                        'updatedBy' => $user->name,
                        'updatedById' => $user->id,
                        'initiatedAt' => $initiatedAt,
                        'completedAt' => null,
                        'uniqueKey' => $uniqueKey
                    ]));
                } catch (\Exception $e) {
                    \Log::debug('Pusher broadcast failed', ['error' => $e->getMessage()]);
                }

                // Chronological filtering applied to prevent future products on past dates

                // Note: Backdated UserOrganizationHistory creation moved to after updateUserHistories for proper flow
            } elseif (count($differences2) != 0) {

                // 🔧 CRITICAL FIX: When products are removed, delete them from current AND all future effective dates
                // This prevents validatePropagationCompleteness from auto-adding them back
                // 🚨 CRITICAL: Use array_values() because array_diff() returns associative array with preserved keys
                $deletedCount = PositionProduct::where('position_id', $id)
                    ->where('effective_date', '>=', $effectiveDate)
                    ->whereIn('product_id', array_values($differences2))
                    ->delete();

                
                // 🔧 CRITICAL: Delete user_organization_history for ALL products >= effective_date (Path B)
                // This is THE key table checked by checkUsersProductForCalculations()
                // When products are removed, sales recalc won't find them → uses DEFAULT product
                // We delete ALL (not just removed) because updateUserHistories() will recreate correct set
                // Other history tables (commission/upfront/override/withheld) are UPDATED, not deleted (audit trail!)
                
                // Get all users in this position
                $userIds = User::where('sub_position_id', $id)->pluck('id')->toArray();
                
                if (!empty($userIds)) {
                    // Delete ONLY user_organization_history >= effective_date
                    // This table determines which product to use for commission calculation
                    $organizationDeleted = UserOrganizationHistory::whereIn('user_id', $userIds)
                        ->where('sub_position_id', $id)
                        ->where('effective_date', '>=', $effectiveDate)
                        ->delete();
                    
                    \Log::info('🗑️ [Path B] Deleted user_organization_history >= effective_date', [
                        'position_id' => $id,
                        'effective_date' => $effectiveDate,
                        'removed_products' => array_values($differences2),
                        'user_count' => count($userIds),
                        'organization_rows' => $organizationDeleted,
                        'note' => 'updateUserHistories() will recreate. Other tables (commission/upfront/override/withheld) are UPDATED not deleted (preserves audit trail)'
                    ]);
                }

                // Also delete current effective date products to reset
                PositionProduct::where(['position_id' => $id, 'effective_date' => $effectiveDate])->delete();

                // 🔧 FIX: DISABLE chronological filtering for explicit user requests (Path 2)
                $chronologicallyValidProducts = $request->product_id;
                $chronologicalFilteringDetails = [];

                /* CHRONOLOGICAL FILTERING DISABLED - User explicitly requested these products (Path 2)
                foreach ($request->product_id as $product) {
                    // 🔧 CRITICAL FIX: Check if product exists with NULL effective_date (original products)
                    $hasNullEffectiveDate = PositionProduct::where('position_id', $id)
                        ->where('product_id', $product)
                        ->whereNull('effective_date')
                        ->exists();

                    // Check earliest dated effective date (excluding NULL)
                    $earliestProductDate = PositionProduct::where('position_id', $id)
                        ->where('product_id', $product)
                        ->whereNotNull('effective_date')
                        ->min('effective_date');

                    $shouldInclude = false;
                    $reason = '';

                    // Include product if:
                    // 1. It exists with NULL effective_date (original products - ALWAYS include)
                    // 2. It has no previous effective date (new products with no history)
                    // 3. Its earliest effective date is <= current effective date
                    if ($hasNullEffectiveDate) {
                        $shouldInclude = true;
                        $reason = 'original_product_with_null_effective_date';
                    } elseif (! $earliestProductDate) {
                        $shouldInclude = true;
                        $reason = 'no_previous_effective_date';
                    } elseif ($earliestProductDate <= $effectiveDate) {
                        $shouldInclude = true;
                        $reason = 'earliest_date_valid';
                    } else {
                        $shouldInclude = false;
                        $reason = 'future_product_filtered_out';
                    }

                    if ($shouldInclude) {
                        $chronologicallyValidProducts[] = $product;
                    }

                    $chronologicalFilteringDetails[] = [
                        'product_id' => $product,
                        'has_null_effective_date' => $hasNullEffectiveDate,
                        'earliest_effective_date' => $earliestProductDate,
                        'should_include' => $shouldInclude,
                        'reason' => $reason,
                    ];
                }
                */  // End of disabled chronological filtering (Path 2)

                foreach ($chronologicallyValidProducts as $product) {
                    PositionProduct::create([
                        'position_id' => $id,
                        'product_id' => $product,
                        'effective_date' => $effectiveDate,
                    ]);
                }


                // Chronological filtering disabled for explicit user requests

                // Note: Backdated UserOrganizationHistory creation moved to after updateUserHistories for proper flow
            } else {
                // 🚨 EMERGENCY FIX: Handle case where neither differences nor differences2 are triggered
                // This can happen when the differences calculation has edge cases
                // Ensure that position_products are always created for the requested effective date
                // No differences detected - emergency position product creation

                // 🔧 CRITICAL FIX: Check for removed products even in emergency scenario
                $currentProduct = PositionProduct::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
                if ($currentProduct) {
                    $existingProducts = PositionProduct::where(['position_id' => $id, 'effective_date' => $currentProduct->effective_date])->pluck('product_id')->toArray();
                } else {
                    $existingProducts = PositionProduct::where(['position_id' => $id])->whereNull('effective_date')->pluck('product_id')->toArray();
                }
                $removedProducts = array_diff($existingProducts, $request->product_id);

                if (!empty($removedProducts)) {
                    // Delete removed products from current AND all future effective dates
                    // 🔧 CRITICAL FIX: Use array_values() because array_diff() preserves keys
                    PositionProduct::where('position_id', $id)
                        ->where('effective_date', '>=', $effectiveDate)
                        ->whereIn('product_id', array_values($removedProducts))
                        ->delete();
                }

                // Delete existing records for this effective date
                PositionProduct::where(['position_id' => $id, 'effective_date' => $effectiveDate])->delete();

                // 🔧 FIX: DISABLE chronological filtering for explicit user requests (Path 3)
                $chronologicallyValidProducts = $request->product_id;
                $chronologicalFilteringDetails = [];

                /* CHRONOLOGICAL FILTERING DISABLED - User explicitly requested these products (Path 3)
                foreach ($request->product_id as $product) {
                    $earliestProductDate = PositionProduct::where('position_id', $id)
                        ->where('product_id', $product)
                        ->whereNotNull('effective_date')
                        ->min('effective_date');

                    $shouldInclude = false;
                    $reason = '';

                    if (! $earliestProductDate) {
                        $shouldInclude = true;
                        $reason = 'no_previous_effective_date';
                    } elseif ($earliestProductDate <= $effectiveDate) {
                        $shouldInclude = true;
                        $reason = 'earliest_date_valid';
                    } else {
                        $shouldInclude = false;
                        $reason = 'future_product_filtered_out';
                    }

                    if ($shouldInclude) {
                        $chronologicallyValidProducts[] = $product;
                    }

                    $chronologicalFilteringDetails[] = [
                        'product_id' => $product,
                        'earliest_effective_date' => $earliestProductDate,
                        'should_include' => $shouldInclude,
                        'reason' => $reason,
                    ];
                }
                */  // End of disabled chronological filtering (Path 3)

                foreach ($chronologicallyValidProducts as $product) {
                    PositionProduct::create([
                        'position_id' => $id,
                        'product_id' => $product,
                        'effective_date' => $effectiveDate,
                    ]);
                }

                // Emergency position product creation completed - Chronological filtering disabled
            }

            $tierChanged = false;
            $originalEffectiveDate = $effectiveDate; // Preserve the original effective date from request
            $effectiveDate = null;
            $positionTier = PositionTier::where('position_id', $position->id)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($positionTier) {
                $effectiveDate = $positionTier->effective_date;
            }
            $positionTiers = PositionTier::where(['position_id' => $position->id, 'effective_date' => $effectiveDate])->get();
            if (count($positionTiers) == 0) {
                if ($request->tiers_commission_status || $request->tiers_upfront_status || $request->tiers_override_status) {
                    $tierChanged = true;
                }
            } else {
                foreach ($positionTiers as $positionTier) {
                    if ($positionTier->type == 'commission') {
                        if ($positionTier->tiers_schema_id != $request->tiers_commission_schema_id || $positionTier->status != $request->tiers_commission_status || $positionTier->tier_advancement != $request->tier_commission_advancement) {
                            $tierChanged = true;
                        }
                    } elseif ($positionTier->type == 'upfront') {
                        if ($positionTier->tiers_schema_id != $request->tiers_upfront_schema_id || $positionTier->status != $request->tiers_upfront_status || $positionTier->tier_advancement != $request->tier_upfront_advancement) {
                            $tierChanged = true;
                        }
                    } elseif (trim($positionTier->type) == 'override') {
                        if ($positionTier->tiers_schema_id != $request->tiers_override_schema_id || $positionTier->status != $request->tiers_override_status || $positionTier->tier_advancement != $request->tier_override_advancement) {
                            $tierChanged = true;
                        }
                    }
                }
            }

            if ($tierChanged) {
                foreach ($statuses as $data) {
                    $status = 0;
                    $schemaId = null;
                    $advancement = null;
                    if ($data['status'] == 1) {
                        $status = 1;
                        $schemaId = $data['schema_id'] ?? null;
                        $advancement = $data['advancement'] ?? null;
                    }

                    PositionTier::updateOrCreate(['position_id' => $id, 'type' => $data['type'], 'effective_date' => $data['effective_date']], [
                        'tiers_schema_id' => $schemaId ?? null,
                        'tier_advancement' => $advancement ?? null,
                        'status' => $status,
                    ]);
                }
            }

            // ============================================
            // 🚀 HOTFIX: Store data for background processing
            // ============================================
            $oldDepartmentId = $position->department_id;
            $position->position_name = $request->position_name;
            $position->department_id = $request->department_id;
            $position->org_parent_id = $request->parent_position_id;
            $position->group_id = $request->permission_group_id;
            if (isset($request->offer_letter_template_id[0]) && ! in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {
                $position->offer_letter_template_id = $request->offer_letter_template_id[0];
            }
            $position->save();

            if ($request->filled('offer_letter_template_id')) {
                if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {

                    NewSequiDocsTemplatePermission::where([
                        'position_id' => $id,
                        'position_type' => 'receipient',
                        'category_id' => 1,
                    ])->delete();

                    foreach ($request['offer_letter_template_id'] as $template_id) {
                        NewSequiDocsTemplatePermission::create([
                            'template_id' => $template_id,
                            'category_id' => 1,
                            'position_id' => $id,
                            'position_type' => 'receipient',
                        ]);
                    }
                } else {
                    NewSequiDocsTemplatePermission::updateOrCreate(['position_id' => $id, 'position_type' => 'receipient', 'category_id' => 1], [
                        'template_id' => $position->offer_letter_template_id,
                        'category_id' => 1,
                        'position_id' => $id,
                        'position_type' => 'receipient',
                    ]);
                }
            } else {
                NewSequiDocsTemplatePermission::where(['position_id' => $id, 'position_type' => 'receipient', 'category_id' => 1])->delete();
            }


            // Broadcast: Before commit (60%)
            try {
                broadcast(new PositionUpdateProgress([
                    'positionId' => (int)$id,
                    'positionName' => $request->position_name,
                    'status' => 'processing',
                    'progress' => 60,
                    'message' => 'Synchronous operations completed, background job queued',
                    'updatedBy' => $user->name,
                    'updatedById' => $user->id,
                    'initiatedAt' => $initiatedAt,
                    'completedAt' => null,
                    'uniqueKey' => $uniqueKey
                ]));
            } catch (\Exception $e) {
                \Log::debug('Pusher broadcast failed', ['error' => $e->getMessage()]);
            }

            // Note: Sales recalculation moved to ProcessPositionUpdateJob
            // It now runs AFTER user histories are created (not before)
            // This ensures checkUsersProductForCalculations finds the correct product assignments

            // Commit fast synchronous changes
            DB::commit();

            // Clear positions cache
            PositionCacheService::clear();

            // ============================================
            // 🚀 DISPATCH BACKGROUND JOB for heavy operations
            // ============================================
            ProcessPositionUpdateJob::dispatch(
                $id,
                $request->all(),
                auth()->user()->id,
                $differences ?? [],
                $differences2 ?? [],
                $tierChanged ?? false,
                $oldDepartmentId,
                $originalEffectiveDate,
                $initiatedAt,  // Pass initiated timestamp
                $uniqueKey     // Pass unique key for tracking
            );

            // Get estimated user count for message
            $userCount = 0;
            try {
                $userCount = DB::table('user_organization_history')
                    ->where('sub_position_id', $id)
                    ->distinct('user_id')
                    ->count('user_id');
            } catch (\Exception $e) {
                // Ignore count errors
            }

            return response()->json([
                'ApiName' => 'update-position',
                'status' => true,
                'message' => 'Position configuration saved! Processing updates for ' . $userCount . ' users in the background. This may take approximately 30-60 minutes. Check the notification bell 🔔 for progress updates.',
                'data' => [
                    'position_id' => $id,
                    'processing_status' => 'background',
                    'estimated_users' => $userCount,
                ],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            // Broadcast failure event so frontend knows update failed
            try {
                if (isset($user) && isset($uniqueKey)) {
                    broadcast(new PositionUpdateProgress([
                        'positionId' => (int)$id,
                        'positionName' => $request->position_name ?? 'Position',
                        'status' => 'failed',
                        'progress' => 0,
                        'message' => 'Position update failed: ' . $e->getMessage(),
                        'updatedBy' => $user->name ?? 'System',
                        'updatedById' => $user->id ?? 0,
                        'initiatedAt' => $initiatedAt ?? now()->toDateTimeString(),
                        'completedAt' => now()->toDateTimeString(),
                        'uniqueKey' => $uniqueKey ?? $id . '_' . time()
                    ]));
                }
            } catch (\Exception $broadcastError) {
                \Log::debug('Failed to broadcast error', ['error' => $broadcastError->getMessage()]);
            }

            return response()->json([
                'ApiName' => 'add-position -products',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    /**
     * Trigger commission recalculation for backdated product assignments
     */
    private function triggerCommissionRecalculation(int $positionId, array $productIds, string $effectiveDate): bool
    {
        try {
            // Get users assigned to this position as of the effective date
            $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);

            if (empty($users)) {
                return false;  // No users, no dispatch
            }

            $userIds = collect($users)->pluck('id')->toArray();

            // Get paid sales to exclude from recalculation
            // Use subquery to avoid large whereIn and check correct paid status based on settlement_type
            $paidSales = UserCommission::where(function($q) {
                    // For during_m2: status = 3 is paid
                    $q->where(function($q) {
                        $q->where('settlement_type', 'during_m2')
                          ->where('status', 3);
                    })
                    // For reconciliation: status = 3 AND recon_status = 3 is paid
                    ->orWhere(function($q) {
                        $q->where('settlement_type', 'reconciliation')
                          ->where('status', 3)
                          ->where('recon_status', 3);
                    });
                })
                ->whereExists(function($query) use ($positionId) {
                    // Only get sales from users in this position (avoids large whereIn)
                    $query->select(DB::raw(1))
                          ->from('users')
                          ->whereColumn('users.id', 'user_commission.user_id')
                          ->where('users.sub_position_id', $positionId);
                })
                ->pluck('pid')
                ->unique()
                ->toArray();

            // Find affected sales from the effective date forward
            // Filter by SPECIFIC products that were changed (not all sales)
            $affectedSales = SalesMaster::select('sale_masters.pid')
                ->join('products', 'sale_masters.product_code', '=', 'products.product_id')
                ->whereIn('products.id', $productIds)  // ✅ CRITICAL: Filter by specific products
                ->whereHas('salesMasterProcessInfo', function ($q) use ($userIds) {
                    $q->where(function ($q) use ($userIds) {
                        $q->whereIn('closer1_id', $userIds)
                            ->orWhereIn('setter1_id', $userIds)
                            ->orWhereIn('closer2_id', $userIds)
                            ->orWhereIn('setter2_id', $userIds);
                    });
                })
                ->where('sale_masters.customer_signoff', '>=', $effectiveDate)
                ->whereNotIn('sale_masters.pid', $paidSales)
                ->whereNull('sale_masters.date_cancelled')
                ->pluck('sale_masters.pid')
                ->toArray();

            if (! empty($affectedSales)) {
                // Create unique lock key per position update (not just sales list)
                // This allows same sales to be recalculated in subsequent position updates
                // while still preventing duplicate dispatches within same update
                $sortedSales = $affectedSales;
                sort($sortedSales);
                $timestamp = time();
                $lockKey = 'settlement_recalc_' . $positionId . '_' . $effectiveDate . '_' . $timestamp;

                // Short lock (5 minutes) - just enough to prevent duplicate dispatches within same update
                if (Cache::add($lockKey, true, 300)) {
                    // Dispatch the recalculation job
                    ProcessRecalculatesOpenSales::dispatch($affectedSales, [
                        'position_id' => $positionId,
                        'effective_date' => $effectiveDate,
                        'trigger' => 'backdated_settlement_change',
                        // Ensure the "who clicked it" user sees the notification (in addition to super-admin defaults).
                        'user_id' => auth()->id(),
                        'recipient_user_ids' => array_values(array_unique(array_filter([(int) auth()->id()]))),
                    ])->onQueue('sales-process');

                    \Log::info('Dispatched ProcessRecalculatesOpenSales for backdated settlement', [
                        'position_id' => $positionId,
                        'effective_date' => $effectiveDate,
                        'sales_count' => count($affectedSales),
                        'lock_key' => $lockKey,
                    ]);
                    
                    return true;  // Successfully dispatched
                } else {
                    \Log::info('Skipped duplicate ProcessRecalculatesOpenSales dispatch (already processing)', [
                        'position_id' => $positionId,
                        'effective_date' => $effectiveDate,
                        'sales_count' => count($affectedSales),
                        'lock_key' => $lockKey,
                    ]);
                    
                    return false;  // Skipped due to lock
                }
            }
            
            return false;  // No sales found

        } catch (Exception $e) {
            // Log error but don't throw to avoid breaking the settlement update
            \Log::error('Error triggering commission recalculation for backdated settlement', [
                'position_id' => $positionId,
                'effective_date' => $effectiveDate,
                'error' => $e->getMessage(),
            ]);
            
            return false;  // Error occurred
        }
    }

    /**
     * Dispatch ApplyHistoryOnUsersV2Job with chunking for scalability
     */
    private function dispatchHistorySyncWithChunking(array $userIds, int $updaterId, string $context = 'position_update'): void
    {
        if (empty($userIds)) {
            return;
        }

        // Add safety limits
        $maxUsers = config('jobs.history_sync.max_users_per_request', 10000);
        if (count($userIds) > $maxUsers) {
            // History sync request exceeds maximum user limit
            throw new \Exception('Too many users to process ('.count($userIds)."). Maximum allowed: {$maxUsers}. Please contact system administrator for bulk processing.");
        }

        // Process users in chunks to prevent memory/timeout issues
        $chunkSize = config('jobs.history_sync.chunk_size', 50);
        $delaySeconds = config('jobs.history_sync.chunk_delay_seconds', 30);
        $chunks = array_chunk($userIds, $chunkSize);

        foreach ($chunks as $index => $chunk) {
            ApplyHistoryOnUsersV2Job::dispatch(
                implode(',', $chunk),
                $updaterId
            )->delay(now()->addSeconds($index * $delaySeconds));
        }

    }

    protected function updatePosition($id, Request $request)
    {
        $effectiveDate = $request->effective_date; // 01-07-2025
        if (! empty($request->commission)) {
            foreach ($request->commission as $commissions) {
                $positionCommissions = PositionCommission::where(['position_id' => $id, 'product_id' => $commissions['product_id'], 'effective_date' => $effectiveDate])->pluck('id');
                if ($positionCommissions) {
                    PositionCommission::whereIn('id', $positionCommissions)->delete();
                    TiersPositionCommission::whereIn('position_commission_id', $positionCommissions)->delete();
                }
                if ($commissions['commission_status'] == 1) {
                    foreach ($commissions['data'] as $commission) {
                        $createCommission = PositionCommission::create([
                            'position_id' => $id,
                            'core_position_id' => $commission['core_position_id'],
                            'product_id' => $commissions['product_id'],
                            'self_gen_user' => $commission['self_gen_user'],
                            'commission_limit' => @$commission['commission_limit'] ?? null,
                            'commission_limit_type' => @$commission['commission_limit_type'] ?? null,
                            'commission_parentage' => $commission['commission_parentage'],
                            'commission_amount_type' => $commission['commission_amount_type'],
                            'commission_status' => @$commissions['commission_status'] ?? 0,
                            'commission_parentag_hiring_locked' => @$commission['commission_parentag_hiring_locked'] ?? 0,
                            'commission_amount_type_locked' => @$commission['commission_amount_type_locked'] ?? 0,
                            'commission_structure_type' => @$commission['commission_structure_type'] ?? 0,
                            'commission_parentag_type_hiring_locked' => @$commission['commission_parentag_type_hiring_locked'] ?? 0,
                            'tiers_id' => @$commission['tiers_id'] ?? null,
                            'tiers_hiring_locked' => @$commission['tiers_hiring_locked'] ?? 0,
                            'effective_date' => $effectiveDate,
                        ]);

                        $lastId = $createCommission->id;
                        $tiers_id = isset($commission['tiers_id']) && $commission['tiers_id'] != '' ? $commission['tiers_id'] : 0;
                        $range = isset($commission['tiers_range']) && $commission['tiers_range'] != '' ? $commission['tiers_range'] : '';
                        if ($tiers_id > 0) {
                            if (is_array($range) && ! empty($range)) {
                                foreach ($range as $rang) {
                                    TiersPositionCommission::create([
                                        'position_id' => $id,
                                        'position_commission_id' => $lastId,
                                        'product_id' => @$commissions['product_id'],
                                        'tiers_schema_id' => @$commission['tiers_id'] ?? null,
                                        'tiers_advancement' => @$commission['tiers_advancement'] ?? null,
                                        'tiers_levels_id' => $rang['id'] ?? null,
                                        'commission_value' => $rang['value'] ?? null,
                                        'commission_type' => $commission['commission_amount_type'],
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    PositionCommission::create([
                        'position_id' => $id,
                        'core_position_id' => null,
                        'product_id' => $commissions['product_id'],
                        'self_gen_user' => 0,
                        'commission_parentage' => 0,
                        'commission_parentag_hiring_locked' => 0,
                        'commission_amount_type' => 'percent',
                        'commission_amount_type_locked' => 0,
                        'commission_structure_type' => null,
                        'commission_parentag_type_hiring_locked' => 0,
                        'commission_status' => @$commissions['commission_status'] ?? 0,
                        'tiers_id' => 0,
                        'tiers_hiring_locked' => 0,
                        'effective_date' => $effectiveDate,
                    ]);
                    PositionCommissionUpfronts::where(['position_id' => $id, 'product_id' => $commissions['product_id'], 'effective_date' => $effectiveDate])->delete();
                    PositionCommissionUpfronts::create([
                        'position_id' => $id,
                        'product_id' => $commissions['product_id'],
                        'upfront_status' => 0,
                        'effective_date' => $effectiveDate,
                        'deductible_from_prior' => '0',
                    ]);
                }
            }
        }

        if (! empty($request->upfront)) {
            foreach ($request->upfront as $upfronts) {
                $positionUpfronts = PositionCommissionUpfronts::where(['position_id' => $id, 'product_id' => $upfronts['product_id'], 'effective_date' => $effectiveDate])->pluck('id');
                if ($positionUpfronts) {
                    PositionCommissionUpfronts::whereIn('id', $positionUpfronts)->delete();
                    TiersPositionUpfront::whereIn('position_upfront_id', $positionUpfronts)->delete();
                }
                if ($upfronts['upfront_status'] == 1) {
                    foreach ($upfronts['data'] as $upfront) {
                        foreach ($upfront['schemas'] as $schema) {
                            $createUpfront = PositionCommissionUpfronts::create([
                                'position_id' => $id,
                                'core_position_id' => $upfront['core_position_id'],
                                'product_id' => $upfronts['product_id'],
                                'milestone_schema_id' => $upfront['milestone_id'],
                                'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                                'self_gen_user' => $upfront['self_gen_user'],
                                'status_id' => $upfronts['upfront_status'],
                                'upfront_ammount' => $schema['upfront_ammount'],
                                'upfront_ammount_locked' => @$schema['upfront_ammount_locked'] ?? 0,
                                'calculated_by' => $schema['calculated_by'],
                                'calculated_locked' => @$schema['calculated_locked'] ?? 0,
                                'upfront_status' => @$upfronts['upfront_status'] ?? 0,
                                'upfront_system' => @$schema['upfront_system'] ?? 'Fixed',
                                'upfront_system_locked' => @$schema['upfront_system_locked'] ?? 0,
                                'upfront_limit' => @$schema['upfront_limit'] ?? null,
                                'upfront_limit_type' => @$schema['upfront_limit_type'] ?? null,
                                'tiers_id' => @$schema['tiers_id'] ?? null,
                                'tiers_hiring_locked' => @$schema['tiers_hiring_locked'] ?? 0,
                                'effective_date' => $effectiveDate,
                                'deductible_from_prior' => isset($schema['deductible_from_prior']) ? (string) $schema['deductible_from_prior'] : '0',
                            ]);

                            $lastId = $createUpfront->id;
                            $tiers_id = isset($schema['tiers_id']) && $schema['tiers_id'] != '' ? $schema['tiers_id'] : 0;
                            $range = isset($schema['tiers_range']) && $schema['tiers_range'] != '' ? $schema['tiers_range'] : '';
                            if ($tiers_id > 0) {
                                if (is_array($range) && ! empty($range)) {
                                    foreach ($range as $rang) {
                                        TiersPositionUpfront::create([
                                            'position_id' => $id,
                                            'position_upfront_id' => $lastId,
                                            'product_id' => @$upfronts['product_id'],
                                            'milestone_schema_id' => $upfront['milestone_id'],
                                            'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                                            'tiers_schema_id' => @$schema['tiers_id'] ?? null,
                                            'tiers_levels_id' => $rang['id'] ?? null,
                                            'upfront_value' => $rang['value'] ?? null,
                                            'upfront_type' => $schema['calculated_by'],
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                } else {
                    PositionCommissionUpfronts::create([
                        'position_id' => $id,
                        'core_position_id' => null,
                        'product_id' => $upfronts['product_id'],
                        'self_gen_user' => 0,
                        'milestone_schema_id' => null,
                        'milestone_schema_trigger_id' => null,
                        'upfront_ammount' => 0,
                        'upfront_ammount_locked' => 0,
                        'calculated_by' => 'per kw',
                        'calculated_locked' => 0,
                        'upfront_status' => 0,
                        'upfront_system' => 'Fixed',
                        'upfront_system_locked' => 0,
                        'upfront_limit' => null,
                        'upfront_limit_type' => null,
                        'tiers_id' => 0,
                        'tiers_hiring_locked' => 0,
                        'effective_date' => $effectiveDate,
                        'deductible_from_prior' => '0',
                    ]);
                }
            }
        }

        if (! empty($request->overrides)) {
            PositionTierOverride::where(['position_id' => $id])->delete();
            foreach ($request->overrides as $overrides) {
                foreach ($overrides['override'] as $override) {
                    $positionOverrides = PositionOverride::where(['position_id' => $id, 'product_id' => $overrides['product_id'], 'override_id' => $override['override_id'], 'effective_date' => $effectiveDate])->pluck('id');
                    if ($positionOverrides) {
                        PositionOverride::whereIn('id', $positionOverrides)->delete();
                        TiersPositionOverrides::whereIn('position_overrides_id', $positionOverrides)->delete();
                    }

                    if ($override['status'] == 1) {
                        $createOverride = PositionOverride::create([
                            'position_id' => $id,
                            'product_id' => $overrides['product_id'],
                            'override_id' => $override['override_id'],
                            'settlement_id' => @$override['settlement_id'] ?? 0,
                            'override_ammount' => $override['override_ammount'],
                            'override_ammount_locked' => @$override['override_ammount_locked'] ?? 0,
                            'type' => $override['type'],
                            'override_type_locked' => $override['override_type_locked'],
                            'status' => $override['status'],
                            'tiers_id' => $override['tiers_id'],
                            'override_limit' => @$override['override_limit'] ?? null,
                            'override_limit_type' => @$override['override_limit_type'] ?? null,
                            'tiers_hiring_locked' => @$override['tiers_hiring_locked'] ?? 0,
                            'effective_date' => $effectiveDate,
                        ]);

                        $lastId = $createOverride->id;
                        $tiersId = isset($override['tiers_id']) && $override['tiers_id'] != '' ? $override['tiers_id'] : 0;
                        $range = isset($override['tiers_range']) && $override['tiers_range'] != '' ? $override['tiers_range'] : '';
                        if ($tiersId > 0) {
                            if (is_array($range) && ! empty($range)) {
                                foreach ($range as $rang) {
                                    TiersPositionOverrides::create([
                                        'position_id' => $id,
                                        'position_overrides_id' => $lastId,
                                        'product_id' => $overrides['product_id'],
                                        'override_id' => $override['override_id'],
                                        'tiers_schema_id' => $tiersId ?? null,
                                        'tiers_levels_id' => $rang['id'] ?? null,
                                        'override_value' => $rang['value'] ?? null,
                                        'override_type' => @$override['type'] ? $override['type'] : null,
                                    ]);
                                }
                            }
                        }
                    } else {
                        $createOverride = PositionOverride::create([
                            'position_id' => $id,
                            'product_id' => $overrides['product_id'],
                            'override_id' => $override['override_id'],
                            'settlement_id' => 0,
                            'override_ammount' => null,
                            'override_ammount_locked' => 0,
                            'type' => null,
                            'override_type_locked' => 0,
                            'status' => $override['status'],
                            'override_limit' => null,
                            'override_limit_type' => null,
                            'tiers_hiring_locked' => 0,
                            'effective_date' => $effectiveDate,
                        ]);
                    }
                }
            }
        }

        if (! empty($request->settlement)) {
            foreach ($request->settlement as $settlement) {
                $positionRecon = PositionReconciliations::where(['position_id' => $id, 'product_id' => $settlement['product_id'], 'effective_date' => $effectiveDate])->pluck('id');
                if ($positionRecon) {
                    PositionReconciliations::whereIn('id', $positionRecon)->delete();
                }
                PositionReconciliations::create([
                    'position_id' => $id,
                    'product_id' => $settlement['product_id'],
                    'commission_withheld' => @$settlement['commission_withheld'],
                    'commission_type' => @$settlement['commission_type'],
                    'commission_withheld_locked' => @$settlement['commission_withheld_locked'] ?? 0,
                    'commission_type_locked' => @$settlement['commission_type_locked'] ?? 0,
                    'maximum_withheld' => @$settlement['maximum_withheld'] ? $settlement['maximum_withheld'] : 0,
                    'override_settlement' => @$settlement['override_settlement'],
                    'clawback_settlement' => @$settlement['clawback_settlement'],
                    'stack_settlement' => @$settlement['stack_settlement'],
                    'tiers_commission_settlement' => @$settlement['tiers_commission_settlement'] ?? null,
                    'tiers_override_settlement' => @$settlement['tiers_override_settlement'] ?? null,
                    'status' => $settlement['status'],
                    'effective_date' => $effectiveDate,
                ]);
            }
        }
    }

    protected function updateUserHistories($id, Request $request, $differences = [])
    {
        $effectiveDate = $request->effective_date ? $request->effective_date : date('Y-m-d');
        $now = now();
        $updaterId = auth()->user()->id;

        // Get position info
        $position = Positions::find($id);
        $parentPosition = $position->parent_id ? $position->parent_id : (($position->id == '2' || $position->id == '3') ? $position->id : 0);

        // Get users for this position
        $userIdArr = [];
        if ($request->wizard_type == 'selective_users') {
            if (! $request->has('selective_users') || ! is_array($request->selective_users)) {
                throw new \Exception('Invalid selective_users format provided');
            }
            $userIdArr = array_map(function($item) {
                return is_array($item) ? ($item['id'] ?? null) : $item;
            }, $request->selective_users);
            $userIdArr = array_filter($userIdArr);
        } else {
            $subQuery = UserOrganizationHistory::select(
                'id', 'user_id', 'effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
            )->where('effective_date', '<=', $effectiveDate);

            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('user_id', 'effective_date')
                ->where('rn', 1)->get();

            $closestDates = $results->pluck('effective_date', 'user_id')->toArray();

            $userIdArr = UserOrganizationHistory::where('sub_position_id', $id)
                ->where(function ($query) use ($closestDates) {
                    foreach ($closestDates as $userId => $date) {
                        $query->orWhere(function ($q) use ($userId, $date) {
                            $q->where('user_id', $userId)->where('effective_date', $date);
                        });
                    }
                })
                ->groupBy('user_id')
                ->pluck('user_id')
                ->toArray();
        }

        // Get position products - Use request data to avoid race conditions with concurrent updates
        // CRITICAL: Don't re-read position_products table - it may have changed by other users
        $positionProducts = [];
        
        if ($request->wizard_type == 'selective_users') {
            // For selective users: Only create histories for NEW products (differences)
            // This prevents affecting users who already have existing products
            foreach ($differences as $productId) {
                $positionProducts[] = ['product_id' => $productId];
            }
            \Log::info('📋 [Selective Users] Using differences for user history creation', [
                'position_id' => $id,
                'new_products' => $differences,
                'product_count' => count($positionProducts)
            ]);
        } else {
            // For all_users: Use ALL products from request payload (frozen at update time)
            foreach ($request->product_id as $productId) {
                $positionProducts[] = ['product_id' => $productId];
            }
            \Log::info('📋 [All Users] Using request payload for user history creation (avoid race conditions)', [
                'position_id' => $id,
                'product_count' => count($positionProducts),
                'product_ids' => $request->product_id,
                'note' => 'Prevents concurrent updates from interfering'
            ]);
        }

        if (empty($userIdArr)) {
            return;
        }

        // === BATCH OPTIMIZATION: Pre-fetch existing records ===
        $existingOrgHistories = UserOrganizationHistory::where('sub_position_id', $id)
            ->whereIn('user_id', $userIdArr)
            ->where('effective_date', $effectiveDate)
            ->get()
            ->keyBy(function($item) {
                return $item->user_id . '_' . $item->product_id;
            });

        $existingCommissionHistories = UserCommissionHistory::where('sub_position_id', $id)
            ->whereIn('user_id', $userIdArr)
            ->where('commission_effective_date', $effectiveDate)
            ->get()
            ->keyBy(function($item) {
                return $item->user_id . '_' . $item->product_id . '_' . $item->core_position_id;
            });

        $existingUpfrontHistories = UserUpfrontHistory::where('sub_position_id', $id)
            ->whereIn('user_id', $userIdArr)
            ->where('upfront_effective_date', $effectiveDate)
            ->get()
            ->keyBy(function($item) {
                return $item->user_id . '_' . $item->product_id . '_' . $item->core_position_id . '_' . $item->milestone_schema_trigger_id;
            });

        $existingOverrideHistories = UserOverrideHistory::whereIn('user_id', $userIdArr)
            ->where('override_effective_date', $effectiveDate)
            ->get()
            ->keyBy(function($item) {
                return $item->user_id . '_' . $item->product_id;
            });

        // === Collect batch operations ===
        $orgHistoryInserts = [];
        $orgHistoryUpdates = [];
        $commissionHistoryInserts = [];
        $commissionHistoryUpdates = [];
        $upfrontHistoryInserts = [];
        $upfrontHistoryUpdates = [];
        $overrideHistoryInserts = [];
        $overrideHistoryUpdates = [];

        foreach ($userIdArr as $userId) {
            // UserOrganizationHistory
            foreach ($positionProducts as $positionProduct) {
                $productId = $positionProduct['product_id'];
                $key = $userId . '_' . $productId;

                $data = [
                    'self_gen_accounts' => ($position?->is_selfgen == 1 ? 1 : 0),
                    'updater_id' => $updaterId,
                    'position_id' => $position->parent_id,
                    'updated_at' => $now,
                ];

                if (isset($existingOrgHistories[$key])) {
                    $orgHistoryUpdates[] = ['id' => $existingOrgHistories[$key]->id, 'data' => $data];
                } else {
                    $orgHistoryInserts[] = array_merge($data, [
                        'user_id' => $userId,
                        'sub_position_id' => $id,
                        'product_id' => $productId,
                        'effective_date' => $effectiveDate,
                        'created_at' => $now,
                    ]);
                }
            }

            // UserCommissionHistory
            if (! empty($request->commission)) {
                foreach ($request->commission as $commissions) {
                    if ($commissions['to_all_users'] == '1' || $request->wizard_type == 'selective_users') {
                        foreach ($commissions['data'] as $commission) {
                            $key = $userId . '_' . $commissions['product_id'] . '_' . $commission['core_position_id'];

                            $data = [
                                'position_id' => $parentPosition,
                                'sub_position_id' => $id,
                                'updater_id' => $updaterId,
                                'self_gen_user' => $commission['self_gen_user'],
                                'commission' => $commission['commission_parentage'],
                                'commission_type' => $commission['commission_amount_type'],
                                'tiers_id' => $commission['tiers_id'],
                                'updated_at' => $now,
                            ];

                            if (isset($existingCommissionHistories[$key])) {
                                $commissionHistoryUpdates[] = [
                                    'id' => $existingCommissionHistories[$key]->id,
                                    'data' => $data,
                                    'tiers_id' => $commission['tiers_id'],
                                    'tiers_range' => $commission['tiers_range'] ?? [],
                                    'user_id' => $userId,
                                    'commission_amount_type' => $commission['commission_amount_type'],
                                ];
                            } else {
                                $commissionHistoryInserts[] = array_merge($data, [
                                    'user_id' => $userId,
                                    'product_id' => $commissions['product_id'],
                                    'core_position_id' => $commission['core_position_id'],
                                    'commission_effective_date' => $effectiveDate,
                                    'created_at' => $now,
                                    'tiers_id' => $commission['tiers_id'],
                                    'tiers_range' => $commission['tiers_range'] ?? [],
                                    'commission_amount_type' => $commission['commission_amount_type'],
                                ]);
                            }
                        }
                    }
                }
            }

            // UserUpfrontHistory
            if (! empty($request->upfront)) {
                foreach ($request->upfront as $upfronts) {
                    if ($upfronts['to_all_users'] == '1' || $request->wizard_type == 'selective_users') {
                        foreach ($upfronts['data'] as $upfront) {
                            foreach ($upfront['schemas'] as $schema) {
                                $key = $userId . '_' . $upfronts['product_id'] . '_' . $upfront['core_position_id'] . '_' . $schema['milestone_schema_trigger_id'];

                                $data = [
                                    'position_id' => $parentPosition,
                                    'sub_position_id' => $id,
                                    'milestone_schema_id' => $upfront['milestone_id'],
                                    'self_gen_user' => $upfront['self_gen_user'],
                                    'updater_id' => $updaterId,
                                    'upfront_pay_amount' => $schema['upfront_ammount'],
                                    'upfront_sale_type' => $schema['calculated_by'],
                                    'tiers_id' => $schema['tiers_id'],
                                    'updated_at' => $now,
                                ];

                                if (isset($existingUpfrontHistories[$key])) {
                                    $upfrontHistoryUpdates[] = [
                                        'id' => $existingUpfrontHistories[$key]->id,
                                        'data' => $data,
                                        'tiers_id' => $schema['tiers_id'],
                                        'tiers_range' => $schema['tiers_range'] ?? [],
                                        'user_id' => $userId,
                                        'upfront_sale_type' => $schema['calculated_by'],
                                    ];
                                } else {
                                    $upfrontHistoryInserts[] = array_merge($data, [
                                        'user_id' => $userId,
                                        'product_id' => $upfronts['product_id'],
                                        'core_position_id' => $upfront['core_position_id'],
                                        'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                                        'upfront_effective_date' => $effectiveDate,
                                        'created_at' => $now,
                                        'tiers_range' => $schema['tiers_range'] ?? [],
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            // UserOverrideHistory
            if (! empty($request->overrides)) {
                foreach ($request->overrides as $overrides) {
                    if ($overrides['to_all_users'] == '1' || $request->wizard_type == 'selective_users') {
                        $direct = null; $directType = null; $directTier = null; $directTierRange = [];
                        $inDirect = null; $inDirectType = null; $inDirectTier = null; $inDirectTierRange = [];
                        $office = null; $officeType = null; $officeTier = null; $officeTierRange = [];
                        $officeStack = 0;
                        // Custom Sales Field IDs
                        $directCustomFieldId = null; $indirectCustomFieldId = null; $officeCustomFieldId = null;

                        foreach ($overrides['override'] as $override) {
                            if ($override['status']) {
                                if ($override['override_id'] == '1') {
                                    $direct = $override['override_ammount']; $directType = $override['type'];
                                    $directTier = $override['tiers_id']; $directTierRange = $override['tiers_range'] ?? [];
                                    $directCustomFieldId = $override['direct_custom_sales_field_id'] ?? null;
                                } elseif ($override['override_id'] == '2') {
                                    $inDirect = $override['override_ammount']; $inDirectType = $override['type'];
                                    $inDirectTier = $override['tiers_id']; $inDirectTierRange = $override['tiers_range'] ?? [];
                                    $indirectCustomFieldId = $override['indirect_custom_sales_field_id'] ?? null;
                                } elseif ($override['override_id'] == '3') {
                                    $office = $override['override_ammount']; $officeType = $override['type'];
                                    $officeTier = $override['tiers_id']; $officeTierRange = $override['tiers_range'] ?? [];
                                    $officeCustomFieldId = $override['office_custom_sales_field_id'] ?? null;
                                } elseif ($override['override_id'] == '4') {
                                    $officeStack = $override['override_ammount'];
                                }
                            }
                        }

                        $key = $userId . '_' . $overrides['product_id'];
                        $data = [
                            'updater_id' => $updaterId,
                            'direct_overrides_amount' => $direct,
                            'direct_overrides_type' => $directType,
                            'indirect_overrides_amount' => $inDirect,
                            'indirect_overrides_type' => $inDirectType,
                            'office_overrides_amount' => $office,
                            'office_overrides_type' => $officeType,
                            'office_stack_overrides_amount' => $officeStack,
                            'direct_tiers_id' => $directTier,
                            'indirect_tiers_id' => $inDirectTier,
                            'office_tiers_id' => $officeTier,
                            // Custom Sales Field IDs
                            'direct_custom_sales_field_id' => $directCustomFieldId,
                            'indirect_custom_sales_field_id' => $indirectCustomFieldId,
                            'office_custom_sales_field_id' => $officeCustomFieldId,
                            'updated_at' => $now,
                        ];

                        if (isset($existingOverrideHistories[$key])) {
                            $overrideHistoryUpdates[] = [
                                'id' => $existingOverrideHistories[$key]->id,
                                'data' => $data,
                                'user_id' => $userId,
                                'directTier' => $directTier, 'directTierRange' => $directTierRange, 'directType' => $directType,
                                'inDirectTier' => $inDirectTier, 'inDirectTierRange' => $inDirectTierRange, 'inDirectType' => $inDirectType,
                                'officeTier' => $officeTier, 'officeTierRange' => $officeTierRange, 'officeType' => $officeType,
                            ];
                        } else {
                            $overrideHistoryInserts[] = array_merge($data, [
                                'user_id' => $userId,
                                'product_id' => $overrides['product_id'],
                                'override_effective_date' => $effectiveDate,
                                'created_at' => $now,
                                'directTier' => $directTier, 'directTierRange' => $directTierRange, 'directType' => $directType,
                                'inDirectTier' => $inDirectTier, 'inDirectTierRange' => $inDirectTierRange, 'inDirectType' => $inDirectType,
                                'officeTier' => $officeTier, 'officeTierRange' => $officeTierRange, 'officeType' => $officeType,
                            ]);
                        }
                    }
                }
            }
        }

        // === Execute batch operations ===

        // UserOrganizationHistory batch inserts
        if (!empty($orgHistoryInserts)) {
            $chunks = array_chunk($orgHistoryInserts, 500);
            foreach ($chunks as $chunk) {
                UserOrganizationHistory::insert($chunk);
            }
        }
        foreach ($orgHistoryUpdates as $update) {
            UserOrganizationHistory::where('id', $update['id'])->update($update['data']);
        }

        // UserCommissionHistory batch operations
        foreach ($commissionHistoryInserts as $insert) {
            $tiersId = $insert['tiers_id'] ?? null;
            $tiersRange = $insert['tiers_range'] ?? [];
            $commissionAmountType = $insert['commission_amount_type'] ?? 'percent';
            $insertUserId = $insert['user_id'];
            unset($insert['tiers_range'], $insert['commission_amount_type']);

            $record = UserCommissionHistory::create($insert);

            if ($tiersId && !empty($tiersRange)) {
                $rangeInserts = [];
                foreach ($tiersRange as $range) {
                    $rangeInserts[] = [
                        'user_id' => $insertUserId,
                        'user_commission_history_id' => $record->id,
                        'tiers_schema_id' => $tiersId,
                        'tiers_levels_id' => $range['id'] ?? null,
                        'value' => $range['value'] ?? null,
                        'value_type' => $commissionAmountType,
                    ];
                }
                if (!empty($rangeInserts)) {
                    UserCommissionHistoryTiersRange::insert($rangeInserts);
                }
            }
        }
        foreach ($commissionHistoryUpdates as $update) {
            UserCommissionHistory::where('id', $update['id'])->update($update['data']);
            UserCommissionHistoryTiersRange::where('user_commission_history_id', $update['id'])->delete();

            if ($update['tiers_id'] && !empty($update['tiers_range'])) {
                $rangeInserts = [];
                foreach ($update['tiers_range'] as $range) {
                    $rangeInserts[] = [
                        'user_id' => $update['user_id'],
                        'user_commission_history_id' => $update['id'],
                        'tiers_schema_id' => $update['tiers_id'],
                        'tiers_levels_id' => $range['id'] ?? null,
                        'value' => $range['value'] ?? null,
                        'value_type' => $update['commission_amount_type'],
                    ];
                }
                if (!empty($rangeInserts)) {
                    UserCommissionHistoryTiersRange::insert($rangeInserts);
                }
            }
        }

        // UserUpfrontHistory batch operations
        foreach ($upfrontHistoryInserts as $insert) {
            $tiersId = $insert['tiers_id'] ?? null;
            $tiersRange = $insert['tiers_range'] ?? [];
            $upfrontSaleType = $insert['upfront_sale_type'] ?? 'per sale';
            $insertUserId = $insert['user_id'];
            unset($insert['tiers_range']);

            $record = UserUpfrontHistory::create($insert);

            if ($tiersId > 0 && !empty($tiersRange)) {
                $rangeInserts = [];
                foreach ($tiersRange as $range) {
                    $rangeInserts[] = [
                        'user_id' => $insertUserId,
                        'user_upfront_history_id' => $record->id,
                        'tiers_schema_id' => $tiersId,
                        'tiers_levels_id' => $range['id'] ?? null,
                        'value' => $range['value'] ?? null,
                        'value_type' => $upfrontSaleType,
                    ];
                }
                if (!empty($rangeInserts)) {
                    UserUpfrontHistoryTiersRange::insert($rangeInserts);
                }
            }
        }
        foreach ($upfrontHistoryUpdates as $update) {
            UserUpfrontHistory::where('id', $update['id'])->update($update['data']);
            UserUpfrontHistoryTiersRange::where('user_upfront_history_id', $update['id'])->delete();

            if ($update['tiers_id'] > 0 && !empty($update['tiers_range'])) {
                $rangeInserts = [];
                foreach ($update['tiers_range'] as $range) {
                    $rangeInserts[] = [
                        'user_id' => $update['user_id'],
                        'user_upfront_history_id' => $update['id'],
                        'tiers_schema_id' => $update['tiers_id'],
                        'tiers_levels_id' => $range['id'] ?? null,
                        'value' => $range['value'] ?? null,
                        'value_type' => $update['upfront_sale_type'],
                    ];
                }
                if (!empty($rangeInserts)) {
                    UserUpfrontHistoryTiersRange::insert($rangeInserts);
                }
            }
        }

        // UserOverrideHistory batch operations
        foreach ($overrideHistoryInserts as $insert) {
            $directTier = $insert['directTier']; $directTierRange = $insert['directTierRange']; $directType = $insert['directType'];
            $inDirectTier = $insert['inDirectTier']; $inDirectTierRange = $insert['inDirectTierRange']; $inDirectType = $insert['inDirectType'];
            $officeTier = $insert['officeTier']; $officeTierRange = $insert['officeTierRange']; $officeType = $insert['officeType'];
            $insertUserId = $insert['user_id'];
            unset($insert['directTier'], $insert['directTierRange'], $insert['directType']);
            unset($insert['inDirectTier'], $insert['inDirectTierRange'], $insert['inDirectType']);
            unset($insert['officeTier'], $insert['officeTierRange'], $insert['officeType']);

            $record = UserOverrideHistory::create($insert);
            $overrideId = $record->id;

            if ($directTier && !empty($directTierRange)) {
                $rangeInserts = [];
                foreach ($directTierRange as $range) {
                    $rangeInserts[] = ['user_id' => $insertUserId, 'user_override_history_id' => $overrideId, 'tiers_schema_id' => $directTier, 'tiers_levels_id' => $range['id'] ?? null, 'value' => $range['value'] ?? null, 'value_type' => $directType];
                }
                if (!empty($rangeInserts)) UserDirectOverrideHistoryTiersRange::insert($rangeInserts);
            }
            if ($inDirectTier && !empty($inDirectTierRange)) {
                $rangeInserts = [];
                foreach ($inDirectTierRange as $range) {
                    $rangeInserts[] = ['user_id' => $insertUserId, 'user_override_history_id' => $overrideId, 'tiers_schema_id' => $inDirectTier, 'tiers_levels_id' => $range['id'] ?? null, 'value' => $range['value'] ?? null, 'value_type' => $inDirectType];
                }
                if (!empty($rangeInserts)) UserIndirectOverrideHistoryTiersRange::insert($rangeInserts);
            }
            if ($officeTier && !empty($officeTierRange)) {
                $rangeInserts = [];
                foreach ($officeTierRange as $range) {
                    $rangeInserts[] = ['user_id' => $insertUserId, 'user_office_override_history_id' => $overrideId, 'tiers_schema_id' => $officeTier, 'tiers_levels_id' => $range['id'] ?? null, 'value' => $range['value'] ?? null, 'value_type' => $officeType];
                }
                if (!empty($rangeInserts)) UserOfficeOverrideHistoryTiersRange::insert($rangeInserts);
            }
        }
        foreach ($overrideHistoryUpdates as $update) {
            UserOverrideHistory::where('id', $update['id'])->update($update['data']);
            $overrideId = $update['id'];

            UserDirectOverrideHistoryTiersRange::where('user_override_history_id', $overrideId)->delete();
            if ($update['directTier'] && !empty($update['directTierRange'])) {
                $rangeInserts = [];
                foreach ($update['directTierRange'] as $range) {
                    $rangeInserts[] = ['user_id' => $update['user_id'], 'user_override_history_id' => $overrideId, 'tiers_schema_id' => $update['directTier'], 'tiers_levels_id' => $range['id'] ?? null, 'value' => $range['value'] ?? null, 'value_type' => $update['directType']];
                }
                if (!empty($rangeInserts)) UserDirectOverrideHistoryTiersRange::insert($rangeInserts);
            }
            UserIndirectOverrideHistoryTiersRange::where('user_override_history_id', $overrideId)->delete();
            if ($update['inDirectTier'] && !empty($update['inDirectTierRange'])) {
                $rangeInserts = [];
                foreach ($update['inDirectTierRange'] as $range) {
                    $rangeInserts[] = ['user_id' => $update['user_id'], 'user_override_history_id' => $overrideId, 'tiers_schema_id' => $update['inDirectTier'], 'tiers_levels_id' => $range['id'] ?? null, 'value' => $range['value'] ?? null, 'value_type' => $update['inDirectType']];
                }
                if (!empty($rangeInserts)) UserIndirectOverrideHistoryTiersRange::insert($rangeInserts);
            }
            UserOfficeOverrideHistoryTiersRange::where('user_office_override_history_id', $overrideId)->delete();
            if ($update['officeTier'] && !empty($update['officeTierRange'])) {
                $rangeInserts = [];
                foreach ($update['officeTierRange'] as $range) {
                    $rangeInserts[] = ['user_id' => $update['user_id'], 'user_office_override_history_id' => $overrideId, 'tiers_schema_id' => $update['officeTier'], 'tiers_levels_id' => $range['id'] ?? null, 'value' => $range['value'] ?? null, 'value_type' => $update['officeType']];
                }
                if (!empty($rangeInserts)) UserOfficeOverrideHistoryTiersRange::insert($rangeInserts);
            }
        }

        // Settlement/Withheld (keep simple - usually small volume)
        if (! empty($request->settlement)) {
            foreach ($userIdArr as $userId) {
                foreach ($request->settlement as $settlement) {
                    if ($settlement['to_all_users'] == '1' || $request->wizard_type == 'selective_users') {
                        UserWithheldHistory::updateOrCreate(
                            ['user_id' => $userId, 'product_id' => $settlement['product_id'], 'withheld_effective_date' => $effectiveDate],
                            [
                                'updater_id' => $updaterId,
                                'withheld_amount' => $settlement['commission_withheld'] ?? 0,
                                'withheld_type' => $settlement['commission_type'] ?? null,
                                'position_id' => $parentPosition,
                                'sub_position_id' => $id,
                            ]
                        );
                    }
                }
            }
        }
    }

    protected function updateOrganizationData($id, $effectiveDate)
    {

        $effectiveDate ??= date('Y-m-d');
        $subQuery = UserOrganizationHistory::select(
            'id',
            'user_id',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
        )
            ->where('effective_date', '<=', $effectiveDate);

        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())
            ->select('user_id', 'effective_date')
            ->where('rn', 1)->get();

        $closestDates = $results->map(function ($result) {
            return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
        });

        $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
            foreach ($closestDates as $closestDate) {
                $query->orWhere(function ($q) use ($closestDate) {
                    $q->where('user_id', $closestDate['user_id'])
                        ->where('effective_date', $closestDate['effective_date']);
                });
            }
        })->where(function ($query) use ($id) {
            $query->where('sub_position_id', $id);
        })->groupBy('user_id')->pluck('user_id')->toArray();

        $position = Positions::find($id);


        // 🔧 CRITICAL FIX: When in differences2 scenario (products removed), use products from EXACT effective_date
        // NOT from old dates which still have the removed products!
        $positionProducts = PositionProduct::where(['position_id' => $id, 'effective_date' => $effectiveDate])->get();


        foreach ($userIdArr as $userId) {
            foreach ($positionProducts as $positionProduct) {
                UserOrganizationHistory::updateOrCreate(['user_id' => $userId, 'sub_position_id' => $id, 'product_id' => $positionProduct['product_id'], 'effective_date' => $effectiveDate], [
                    'self_gen_accounts' => ($position?->is_selfgen == 1 ? 1 : 0),
                    'updater_id' => auth()->user()->id,
                    'position_id' => $position->parent_id,
                ]);
            }
        }
    }

    public function delete($id): JsonResponse
    {
        if ($id == 1 || $id == 2 || $id == 3) {
            return response()->json(['status' => false, 'message' => 'Core position can not be removed!!'], 400);
        }

        try {
            DB::beginTransaction();

            // Check if position exists
            $position = Positions::find($id);
            if (!$position) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Position not found!!'], 404);
            }

            // Delete related position_hire_permissions records first
            PositionHirePermission::where('position_id', $id)->delete();

            // Delete the position
            $position->delete();

            // Clear positions cache after successful deletion
            PositionCacheService::clear();

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Position deleted successfully!!']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Position deletion failed', [
                'position_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete position. Please try again later.'
            ], 500);
        }
    }

    public function editPositionAll($id)
    {
        try {
            $data = Positions::withoutGlobalScope('notSuperAdmin')->with(['deductionName.costCenter', 'deductionLimit', 'deductionSetting', 'payFrequency.frequencyType'])->where('id', $id)->first();

            if (! $data) {
                return response()->json(['status' => false, 'message' => 'Position id is not available.'], 400);
            }

            $effectiveDate = date('Y-m-d');
            $positionProducts = PositionProduct::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if ($positionProducts) {
                $positionProducts = PositionProduct::with('productName')->where(['position_id' => $id, 'effective_date' => $positionProducts->effective_date])->get();
            } else {
                $positionProducts = PositionProduct::with('productName')->where(['position_id' => $id])->whereNull('effective_date')->get();
            }
            $positionTiers = PositionTier::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->first();
            if ($positionTiers) {
                $positionTiers = PositionTier::where(['position_id' => $id, 'status' => 1, 'effective_date' => $positionTiers->effective_date])->get();
            } else {
                $positionTiers = PositionTier::where(['position_id' => $id, 'status' => 1])->whereNull('effective_date')->get();
            }
            $tierAdvancementCommission = $positionTiers->where('type', 'commission')->first();
            $tierAdvancementUpfront = $positionTiers->where('type', 'upfront')->first();
            $tierAdvancementOverride = $positionTiers->where('type', 'override')->first();

            $upfrontData = [];
            $overrideData = [];
            $settlementData = [];
            $commissionData = [];
            
            // Check if Custom Sales Fields feature is enabled (for display formatting)
            // Using helper for request-scoped caching to avoid multiple DB queries
            $isCustomFieldsEnabledForDisplay = CustomSalesFieldHelper::isFeatureEnabled();
            
            foreach ($positionProducts as $product) {
                $commission = PositionCommission::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($commission) {
                    $commissions = PositionCommission::where(['position_id' => $id, 'product_id' => $product->product_id, 'effective_date' => $commission->effective_date])->get();
                } else {
                    $commissions = PositionCommission::where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->get();
                }

                $commissionData = array_merge($commissionData, $commissions->groupBy('commission_status')->map(fn ($groupByStatus) => [
                    'product_id' => $groupByStatus->first()->product_id,
                    'commission_status' => $groupByStatus->first()->commission_status,
                    'data' => $groupByStatus->map(fn ($item) => [
                        'core_position_id' => $item->core_position_id,
                        'self_gen_user' => $item->self_gen_user,
                        'commission_parentage' => $item->commission_parentage,
                        // Only transform to custom_field_X format when feature is enabled
                        'commission_amount_type' => ($isCustomFieldsEnabledForDisplay && $item->commission_amount_type === 'custom field' && $item->custom_sales_field_id) ? 'custom_field_' . $item->custom_sales_field_id : $item->commission_amount_type,
                        'custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $item->commission_amount_type === 'custom field') ? $item->custom_sales_field_id : null,
                        'commission_parentag_hiring_locked' => $item->commission_parentag_hiring_locked,
                        'commission_amount_type_locked' => $item->commission_amount_type_locked,
                        'commission_structure_type' => $item->commission_structure_type,
                        'commission_parentag_type_hiring_locked' => $item->commission_parentag_type_hiring_locked,
                        'commission_limit' => $item->commission_limit,
                        'commission_limit_type' => $item->commission_limit_type ?? null,
                        'tiers_id' => $item->tiers_id,
                        'tiers_commission_status' => $item->tiersRange && $item->tiersRange->isNotEmpty() ? 1 : 0,
                        'tiers_advancement' => $tierAdvancementCommission?->tier_advancement,
                        'tiers_hiring_locked' => $item->tiers_hiring_locked,
                        'tiers_start_end_date' => $item->tiersRange->first()?->start_end_day,
                        'tiers_range' => $item->tiersRange
                            ->map(function ($range) {
                                return [
                                    'id' => $range->tiers_levels_id,
                                    'value' => $range->commission_value,
                                ];
                            })->values(),
                    ])->values(),
                ])->values()->toArray());

                $upfront = PositionCommissionUpfronts::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($upfront) {
                    $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $id, 'product_id' => $product->product_id, 'effective_date' => $upfront->effective_date])->get();
                } else {
                    $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->get();
                }

                $upfrontData = array_merge($upfrontData, $upFronts->groupBy('upfront_status')->map(fn ($groupByStatus) => [
                    'product_id' => $groupByStatus->first()->product_id,
                    'upfront_status' => $groupByStatus->first()->upfront_status,
                    'data' => $groupByStatus->groupBy('core_position_id')->map(fn ($groupByCorePosition) => [
                        'milestone_id' => $groupByCorePosition->first()->milestone_schema_id,
                        'core_position_id' => $groupByCorePosition->first()->core_position_id,
                        'self_gen_user' => $groupByCorePosition->first()->self_gen_user,
                        'schemas' => $groupByCorePosition->groupBy('milestone_schema_id')
                            ->flatMap(fn ($groupByMilestone) => $groupByMilestone->map(fn ($item) => [
                                'milestone_schema_trigger_id' => $item->milestone_schema_trigger_id,
                                'upfront_ammount' => (string) $item->upfront_ammount,
                                'deductible_from_prior' => $item->deductible_from_prior,
                                'upfront_ammount_locked' => (string) ($item->upfront_ammount_locked ?? 0),
                                // Only transform to custom_field_X format when feature is enabled
                                'calculated_by' => ($isCustomFieldsEnabledForDisplay && $item->calculated_by === 'custom field' && $item->custom_sales_field_id) ? 'custom_field_' . $item->custom_sales_field_id : $item->calculated_by,
                                'custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $item->calculated_by === 'custom field') ? $item->custom_sales_field_id : null,
                                'calculated_locked' => (string) $item->calculated_locked,
                                'upfront_system' => $item->upfront_system,
                                'upfront_system_locked' => (string) $item->upfront_system_locked,
                                'upfront_limit' => $item->upfront_limit,
                                'upfront_limit_type' => $item->upfront_limit_type ?? null,
                                'tiers_id' => $item->tiers_id,
                                'tiers_upfront_status' => $item->tiersRange && $item->tiersRange->isNotEmpty() ? 1 : 0,
                                'tiers_advancement' => $tierAdvancementUpfront?->tier_advancement,
                                'tiers_hiring_locked' => $item->tiers_hiring_locked,
                                'tiers_start_end_date' => $item->tiersRange->first()?->start_end_day,
                                'tiers_range' => $item->tiersRange->map(function ($range) {
                                    return [
                                        'id' => $range->tiers_levels_id,
                                        'value' => $range->upfront_value,
                                    ];
                                })->values(),
                            ])->values())->values(),
                    ])->values(),
                ])->values()->toArray());

                $settlement = PositionReconciliations::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $settlement) {
                    $settlement = PositionReconciliations::where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->first();
                }
                if ($settlement) {
                    $settlementData[] = [
                        'status' => $settlement['status'],
                        'product_id' => $settlement['product_id'],
                        'commission_withheld' => $settlement['commission_withheld'],
                        'commission_type' => $settlement['commission_type'],
                        'maximum_withheld' => (string) $settlement['maximum_withheld'],
                        'override_settlement' => $settlement['override_settlement'],
                        'clawback_settlement' => $settlement['clawback_settlement'],
                        'stack_settlement' => $settlement['stack_settlement'],
                        'tiers_commission_settlement' => $settlement['tiers_commission_settlement'] ?? null,
                        'tiers_override_settlement' => $settlement['tiers_override_settlement'] ?? null,
                    ];
                }

                $override = PositionOverride::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($override) {
                    $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $id, 'product_id' => $product->product_id, 'effective_date' => $override->effective_date])->get();
                } else {
                    $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->get();
                }
                if (count($overrides) != 0) {
                    foreach ($overrides as $item) {
                        $productId = $item['product_id'];
                        if (! isset($groupedData[$productId])) {
                            $groupedData[$productId] = [
                                'product_id' => (string) $productId,
                                'status' => count(collect($overrides)->where('product_id', $productId)->where('status', 1)->values()) != 0 ? 1 : 0,
                                'override' => [],
                            ];
                        }

                        // Determine the custom sales field ID based on override type (only if feature is enabled)
                        // Using cached $isCustomFieldsEnabledForDisplay from outer scope - no DB query needed
                        $customFieldId = null;
                        
                        // Only transform to custom_field_X format when feature is enabled
                        $transformedType = $item['type'];
                        if ($isCustomFieldsEnabledForDisplay && $item['type'] === 'custom field') {
                            switch ($item['override_id']) {
                                case 1: // Direct override
                                    $customFieldId = $item['direct_custom_sales_field_id'] ?? null;
                                    break;
                                case 2: // Indirect override
                                    $customFieldId = $item['indirect_custom_sales_field_id'] ?? null;
                                    break;
                                case 3: // Office override
                                    $customFieldId = $item['office_custom_sales_field_id'] ?? null;
                                    break;
                            }
                            
                            if ($customFieldId) {
                                $transformedType = 'custom_field_' . $customFieldId;
                            }
                        }

                        // Return custom field IDs for the frontend to use
                        $directCustomFieldId = $item['direct_custom_sales_field_id'] ?? null;
                        $indirectCustomFieldId = $item['indirect_custom_sales_field_id'] ?? null;
                        $officeCustomFieldId = $item['office_custom_sales_field_id'] ?? null;

                        $groupedData[$productId]['override'][] = [
                            'override_id' => $item['override_id'],
                            'status' => $item['status'],
                            'override_ammount' => $item['override_ammount'],
                            'override_ammount_locked' => $item['override_ammount_locked'],
                            'override_limit' => $item['override_limit'],
                            'override_limit_type' => $item['override_limit_type'] ?? null,
                            'type' => $transformedType,
                            'custom_sales_field_id' => null, // Legacy field, always null
                            'direct_custom_sales_field_id' => $directCustomFieldId,
                            'indirect_custom_sales_field_id' => $indirectCustomFieldId,
                            'office_custom_sales_field_id' => $officeCustomFieldId,
                            'override_type_locked' => $item['override_type_locked'],
                            'tiers_id' => $item->tiers_id,
                            'tiers_override_status' => $item->tiersRange && $item->tiersRange->isNotEmpty() ? 1 : 0,
                            'tiers_advancement' => $tierAdvancementOverride?->tier_advancement,
                            'tiers_hiring_locked' => $item->tiers_hiring_locked,
                            'tiers_start_end_date' => $item->tiersRange->first()?->start_end_day,
                            'tiers_range' => $item->tiersRange->map(function ($range) {
                                return [
                                    'id' => $range->tiers_levels_id,
                                    'value' => $range->override_value,
                                ];
                            })->values(),
                        ];
                        $overrideData = array_values($groupedData);
                    }
                }
            }

            $deductionData = [];
            $positionDeductionLimit = $data->deductionLimit;
            if (count($data->deductionName) != 0) {
                $deductionData = [
                    'deduction_status' => $positionDeductionLimit->status,
                    'limit_ammount' => $positionDeductionLimit->limit_ammount,
                    'limit' => $positionDeductionLimit->limit,
                    'limit_type' => $positionDeductionLimit->limit_type,
                    'deduction' => $data->deductionName->map(function ($deductionName) {
                        return [
                            'id' => $deductionName->id,
                            'cost_center_id' => $deductionName->cost_center_id,
                            'deduction_type' => $deductionName->deduction_type,
                            'ammount_par_paycheck' => $deductionName->ammount_par_paycheck,
                            'changes_field' => ($deductionName->changes_field) ? $deductionName->changes_field : null,
                            'changes_type' => ($deductionName->changes_type) ? $deductionName->changes_type : null,
                            'pay_period_from' => ($deductionName->pay_period_from) ? $deductionName->pay_period_from : null,
                            'pay_period_to' => ($deductionName->pay_period_to) ? $deductionName->pay_period_to : null,
                        ];
                    }),
                ];
            }

            $parentPosition = $data->parent_id ? $data->parent_id : (($data->id == '2' || $data->id == '3') ? $data->id : 0);
            $positionCommissionDeductionSetting = $data->deductionSetting;

            $positionWage = PositionWage::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $positionWage) {
                $positionWage = PositionWage::where(['position_id' => $id])->whereNull('effective_date')->first();
            }
            $response = [
                'position_id' => $data->id,
                'parent_position_id' => $data->parent_position_id,
                'parent_id' => $parentPosition,
                'main_role' => $data->is_selfgen,
                'position_name' => $data->position_name,
                'worker_type' => $data->worker_type,
                'frequency_name' => $data->payFrequency->frequencyType->name,
                'deduction_status' => isset($positionDeductionLimit->status) ? $positionDeductionLimit->status : 0,
                'deduction_locked' => isset($positionCommissionDeductionSetting->deducation_locked) ? $positionCommissionDeductionSetting->deducation_locked : null,
                'wages_status' => isset($positionWage->wages_status) ? $positionWage->wages_status : 0,
                'pay_type' => isset($positionWage->pay_type) ? $positionWage->pay_type : null,
                'pay_type_lock' => isset($positionWage->pay_type_lock) ? $positionWage->pay_type_lock : null,
                'pay_rate' => isset($positionWage->pay_rate) ? $positionWage->pay_rate : null,
                'pay_rate_type' => isset($positionWage->pay_rate_type) ? $positionWage->pay_rate_type : null,
                'pay_rate_lock' => isset($positionWage->pay_rate_lock) ? $positionWage->pay_rate_lock : null,
                'pto_hours' => isset($positionWage->pto_hours) ? $positionWage->pto_hours : null,
                'pto_hours_lock' => isset($positionWage->pto_hours_lock) ? $positionWage->pto_hours_lock : null,
                'unused_pto_expires' => isset($positionWage->unused_pto_expires) ? $positionWage->unused_pto_expires : null,
                'unused_pto_expires_lock' => isset($positionWage->unused_pto_expires_lock) ? $positionWage->unused_pto_expires_lock : null,
                'expected_weekly_hours' => isset($positionWage->expected_weekly_hours) ? $positionWage->expected_weekly_hours : null,
                'expected_weekly_hours_lock' => isset($positionWage->expected_weekly_hours_lock) ? $positionWage->expected_weekly_hours_lock : null,
                'overtime_rate' => isset($positionWage->overtime_rate) ? $positionWage->overtime_rate : null,
                'overtime_rate_lock' => isset($positionWage->overtime_rate_lock) ? $positionWage->overtime_rate_lock : null,
                'position_status' => $data->setup_status,
                'commission' => $commissionData,
                'upfront' => $upfrontData,
                'settlement' => $settlementData,
                'overrides' => $overrideData,
                'deductions' => $deductionData,
                'product' => $positionProducts,
                'offer_letters' => isset($data->allAssociatedOfferLettersWithTemplate) ? $data->allAssociatedOfferLettersWithTemplate : [],
            ];

            return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $response]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage().' '.$e->getLine()], 400);
        }
    }

    public function dropDownProductByPosition($id): JsonResponse
    {
        try {
            $data = [];
            $positionId = $id;
            $effectiveDate = date('Y-m-d');
            $product = PositionProduct::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if ($product) {
                $products = PositionProduct::with('productDetails.currentProductMilestoneHistories.milestoneSchema.milestone_trigger')->where(['position_id' => $positionId, 'effective_date' => $product->effective_date])->get();
            } else {
                $products = PositionProduct::with('productDetails.currentProductMilestoneHistories.milestoneSchema.milestone_trigger')->where(['position_id' => $positionId])->whereNull('effective_date')->get();
            }
            foreach ($products as $positionProduct) {
                $product = $positionProduct->productDetails;
                if ($product) {
                    $triggers = $product->currentProductMilestoneHistories->milestoneSchema->milestone_trigger->slice(0, $product->currentProductMilestoneHistories->milestoneSchema->milestone_trigger->count() - 1);
                    $data[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'product_id' => $product->product_id,
                        'description' => $product->description,
                        'milestone_schema' => [
                            'id' => $product->currentProductMilestoneHistories->id,
                            'prefix' => $product->currentProductMilestoneHistories->milestoneSchema->prefix,
                            'schema_name' => $product->currentProductMilestoneHistories->milestoneSchema->schema_name,
                            'schema_description' => $product->currentProductMilestoneHistories->milestoneSchema->schema_description,
                            'status' => $product->currentProductMilestoneHistories->milestoneSchema->status,
                            'milestone_trigger' => $triggers,
                        ],
                    ];
                }
            }

            return response()->json([
                'ApiName' => 'drop-down-product-by-position',
                'status' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'drop-down-product-by-position',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function positionProductWise(Request $request)
    {
        try {
            $id = $request->position_id;
            $product_wise_id = $request->product_id;
            $data = Positions::with(['deductionName.costCenter', 'deductionLimit', 'deductionSetting', 'payFrequency.frequencyType'])->where('id', $id)->first();

            if (! $data) {
                return response()->json(['status' => false, 'message' => 'Position id is not available.'], 400);
            }

            $upfrontData = [];
            $overrideData = [];
            $settlementData = [];
            $productDetails = [];
            $commissionData = [];
            $effectiveDate = date('Y-m-d');
            $positionProducts = PositionProduct::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if ($positionProducts) {
                $positionProducts = PositionProduct::with('productName')->where(['position_id' => $id, 'effective_date' => $positionProducts->effective_date])->get();
            } else {
                $positionProducts = PositionProduct::with('productName')->where(['position_id' => $id])->whereNull('effective_date')->get();
            }
            foreach ($positionProducts as $product) {
                $productDetails[$product->product_id]['product_id'] = $product->product_id;
                $productDetails[$product->product_id]['product_status'] = $product->productName->status;

                $productDetails[$product->product_id]['data'][] = [
                    'product_id_' => isset($product->productName->product_id) ? $product->productName->product_id : null,
                    'product_name' => isset($product->productName->name) ? $product->productName->name : null,
                    'description' => isset($product->productName->description) ? $product->productName->description : null,
                    'milestone_schema_id' => isset($product->productName->milestone_schema_id) ? $product->productName->milestone_schema_id : null,
                    'clawback_exempt_on_ms_trigger_id' => isset($product->productName->clawback_exempt_on_ms_trigger_id) ? $product->productName->clawback_exempt_on_ms_trigger_id : null,
                    'effective_date' => isset($product->productName->effective_date) ? $product->productName->effective_date : null,
                ];

                $commission = PositionCommission::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($commission) {
                    $commissions = PositionCommission::where(['position_id' => $id, 'product_id' => $product->product_id, 'effective_date' => $commission->effective_date])->get();
                } else {
                    $commissions = PositionCommission::where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->get();
                }
                foreach ($commissions as $commission) {
                    $commissionData[$product->product_id]['product_id'] = $commission->product_id;
                    $commissionData[$product->product_id]['commission_status'] = $commission->commission_status;

                    $commissionData[$product->product_id]['data'][] = [
                        'commission_parentage' => isset($commission->commission_parentage) ? $commission->commission_parentage : null,
                        'commission_status' => isset($commission->commission_status) ? $commission->commission_status : null,
                        'commission_percentag_hiring_locked' => isset($commission->commission_parentag_hiring_locked) ? $commission->commission_parentag_hiring_locked : null,
                        'commission_amount_type' => isset($commission->commission_amount_type) ? $commission->commission_amount_type : null,
                        'commission_amount_type_locked' => isset($commission->commission_amount_type_locked) ? $commission->commission_amount_type_locked : null,
                        'commission_parentag_type_hiring_locked' => isset($commission->commission_parentag_type_hiring_locked) ? $commission->commission_parentag_type_hiring_locked : null,
                        'commission_structure_type' => isset($commission->commission_structure_type) ? $commission->commission_structure_type : null,
                        'self_gen_user' => isset($commission->self_gen_user) ? $commission->self_gen_user : null,
                    ];
                }

                $upfront = PositionCommissionUpfronts::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($upfront) {
                    $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $id, 'product_id' => $product->product_id, 'effective_date' => $upfront->effective_date])->get();
                } else {
                    $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->get();
                }
                foreach ($upFronts as $upfront) {
                    $upfrontData[$upfront->product_id]['product_id'] = $upfront->product_id;
                    $upfrontData[$upfront->product_id]['upfront_status'] = $upfront->upfront_status;

                    $upfrontData[$upfront->product_id]['data'][] = [
                        'upfront_ammount' => isset($upfront->upfront_ammount) ? $upfront->upfront_ammount : null,
                        'upfront_ammount_locked' => isset($upfront->upfront_ammount_locked) ? $upfront->upfront_ammount_locked : 0,
                        'upfront_status' => isset($upfront->upfront_status) ? $upfront->upfront_status : null,
                        'calculated_by' => isset($upfront->calculated_by) ? $upfront->calculated_by : null,
                        'calculated_locked' => isset($upfront->calculated_locked) ? $upfront->calculated_locked : null,
                        'upfront_system' => isset($upfront->upfront_system) ? $upfront->upfront_system : null,
                        'upfront_system_locked' => isset($upfront->upfront_system_locked) ? $upfront->upfront_system_locked : null,
                        'upfront_limit' => isset($upfront->upfront_limit) ? $upfront->upfront_limit : null,
                        'upfront_limit_type' => isset($upfront->upfront_limit_type) ? $upfront->upfront_limit_type : null,
                        'self_gen_user' => isset($upfront->self_gen_user) ? $upfront->self_gen_user : null,
                        'milestone_name' => isset($upfront->milestoneHistory->milestone->schema_name) ? $upfront->milestoneHistory->milestone->prefix.'-'.$upfront->milestoneHistory->milestone->schema_name : null,
                        'milestone_trigger_name' => isset($upfront->milestoneTrigger->name) ? $upfront->milestoneTrigger->name : null,
                        'milestone_trigger_type' => isset($upfrontData[$upfront->product_id]) ? 'M'.count($upfrontData[$upfront->product_id]) + 1 : 'M1',
                    ];
                }

                $override = PositionOverride::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($override) {
                    $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $id, 'product_id' => $product->product_id, 'effective_date' => $override->effective_date])->get();
                } else {
                    $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->get();
                }
                foreach ($overrides as $override) {
                    $overrideData[$override->product_id]['product_id'] = $override->product_id;
                    if (@$overrideData[$override->product_id]['status']) {
                        //
                    } else {
                        $overrideData[$override->product_id]['status'] = $override->status;
                    }

                    $overrideType = $override->overridesDetail;
                    $overrideType = $overrideType ? $overrideType->overrides_type : 'Unknown';
                    $overrideData[$override->product_id]['data'][] = [
                        'override_id' => $override['override_id'],
                        'status' => $override['status'],
                        'override_ammount' => $override['override_ammount'],
                        'override_ammount_locked' => $override['override_ammount_locked'],
                        'type' => $override['type'],
                        'override_type_locked' => $override['override_type_locked'],
                        'overrides_type' => $overrideType,
                    ];
                }

                $settlement = PositionReconciliations::where(['position_id' => $id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $settlement) {
                    $settlement = PositionReconciliations::where(['position_id' => $id, 'product_id' => $product->product_id])->whereNull('effective_date')->first();
                }
                if ($settlement) {
                    $settlementData[$settlement->product_id]['product_id'] = $settlement->product_id;
                    $settlementData[$settlement->product_id]['status'] = $settlement->status;

                    $settlementData[$settlement->product_id]['data'][] = [
                        'commission_withheld' => isset($settlement->commission_withheld) ? $settlement->commission_withheld : null,
                        'commission_type' => isset($settlement->commission_type) ? $settlement->commission_type : null,
                        'maximum_withheld' => isset($settlement->maximum_withheld) ? $settlement->maximum_withheld : null,
                        'override_settlement' => isset($settlement->override_settlement) ? $settlement->override_settlement : null,
                        'clawback_settlement' => isset($settlement->clawback_settlement) ? $settlement->clawback_settlement : null,
                        'stack_settlement' => isset($settlement->stack_settlement) ? $settlement->stack_settlement : null,
                        'commission_type_locked' => isset($settlement->commission_type_locked) ? $settlement->commission_type_locked : null,
                        'commission_withheld_locked' => isset($settlement->commission_withheld_locked) ? $settlement->commission_withheld_locked : null,
                        'tiers_commission_settlement' => isset($settlement->tiers_commission_settlement) ? $settlement->tiers_commission_settlement : null,
                        'tiers_override_settlement' => isset($settlement->tiers_override_settlement) ? $settlement->tiers_override_settlement : null,
                    ];
                }
            }
            $productDetails = array_values($productDetails);
            $productDetails = array_filter($productDetails, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $productDetails = array_values($productDetails);

            $commissionData = array_values($commissionData);
            $commissionData = array_filter($commissionData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $commissionData = array_values($commissionData);

            $upfrontData = array_values($upfrontData);
            $upfrontData = array_filter($upfrontData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $upfrontData = array_values($upfrontData);

            $overrideData = array_values($overrideData);
            $overrideData = array_filter($overrideData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $overrideData = array_values($overrideData);

            $settlementData = array_values($settlementData);
            $settlementData = array_filter($settlementData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $settlementData = array_values($settlementData);

            $deductionData = [];
            if (count($data->deductionName) != 0) {
                $deductionData = $data->deductionName->map(function ($deductionName) {
                    return [
                        'id' => $deductionName->id,
                        'deduction_setting_id' => $deductionName->deduction_setting_id,
                        'position_id' => $deductionName->position_id,
                        'cost_center_id' => $deductionName->cost_center_id,
                        'deduction_type' => $deductionName->deduction_type,
                        'ammount_par_paycheck' => $deductionName->ammount_par_paycheck,
                        'cost_center_name' => $deductionName->costCenter->name,
                    ];
                })->toArray();
            }
            $deductionData = array_values($deductionData);
            $positionDeductionLimit = $data->deductionLimit;
            $positionCommissionDeductionSetting = $data->deductionSetting;

            $positionWage = PositionWage::where(['position_id' => $id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $positionWage) {
                $positionWage = PositionWage::where(['position_id' => $id])->whereNull('effective_date')->first();
            }
            $response = [
                'id' => $data->id,
                'position_name' => $data->position_name,
                'worker_type' => $data->worker_type,
                'frequency_name' => $data->payFrequency->frequencyType->name,
                'deduction' => $deductionData,
                'deduction_status' => isset($positionDeductionLimit->status) ? $positionDeductionLimit->status : 0,
                'limit_type' => isset($positionDeductionLimit->limit_type) ? $positionDeductionLimit->limit_type : null,
                'limit_ammount' => isset($positionDeductionLimit->limit_ammount) ? $positionDeductionLimit->limit_ammount : null,
                'deduction_locked' => isset($positionCommissionDeductionSetting->deducation_locked) ? $positionCommissionDeductionSetting->deducation_locked : null,
                'wages_status' => isset($positionWage->wages_status) ? $positionWage->wages_status : 0,
                'pay_type' => isset($positionWage->pay_type) ? $positionWage->pay_type : null,
                'pay_type_lock' => isset($positionWage->pay_type_lock) ? $positionWage->pay_type_lock : null,
                'pay_rate' => isset($positionWage->pay_rate) ? $positionWage->pay_rate : null,
                'pay_rate_type' => isset($positionWage->pay_rate_type) ? $positionWage->pay_rate_type : null,
                'pay_rate_lock' => isset($positionWage->pay_rate_lock) ? $positionWage->pay_rate_lock : null,
                'pto_hours' => isset($positionWage->pto_hours) ? $positionWage->pto_hours : null,
                'pto_hours_lock' => isset($positionWage->pto_hours_lock) ? $positionWage->pto_hours_lock : null,
                'unused_pto_expires' => isset($positionWage->unused_pto_expires) ? $positionWage->unused_pto_expires : null,
                'unused_pto_expires_lock' => isset($positionWage->unused_pto_expires_lock) ? $positionWage->unused_pto_expires_lock : null,
                'expected_weekly_hours' => isset($positionWage->expected_weekly_hours) ? $positionWage->expected_weekly_hours : null,
                'expected_weekly_hours_lock' => isset($positionWage->expected_weekly_hours_lock) ? $positionWage->expected_weekly_hours_lock : null,
                'overtime_rate' => isset($positionWage->overtime_rate) ? $positionWage->overtime_rate : null,
                'overtime_rate_lock' => isset($positionWage->overtime_rate_lock) ? $positionWage->overtime_rate_lock : null,
                'commission' => $commissionData,
                'upfront' => $upfrontData,
                'override' => $overrideData,
                'settlement' => $settlementData,
                'products' => $productDetails,
            ];

            return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $response]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage().' '.$e->getLine()], 400);
        }
    }

    // POSITION SETUP

    public function wages(Request $request)
    {
        $this->checkValidations($request->all(), [
            'wizard_type' => 'required|in:only_new_users,all_users,selective_users',
            'effective_date' => 'required_if:wizard_type,all_users,selective_users',
        ]);

        $positionId = $request->position_id;
        if ($request->wages_status == '1') {
            $this->checkValidations($request->all(), [
                'position_id' => 'required',
                'pay_type' => 'required|in:Salary,Hourly',
                'pay_type_lock' => 'required|in:0,1',
                'pay_rate' => 'required|numeric',
                'pay_rate_lock' => 'required|in:0,1',
                'pto_hours' => 'required|numeric',
                'pto_hours_lock' => 'required|in:0,1',
                'unused_pto_expires' => 'required',
                'unused_pto_expires_lock' => 'required|in:0,1',
                'expected_weekly_hours' => 'required|numeric',
                'expected_weekly_hours_lock' => 'required|in:0,1',
                'overtime_rate' => 'required_if:pay_type,Hourly',
                'overtime_rate_lock' => 'required_if:pay_type,Hourly|in:0,1',
                'wages_status' => 'required|in:0,1',
            ]);
        } else {
            $positionWage = PositionWage::where(['position_id' => $positionId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $positionWage) {
                $positionWage = PositionWage::where(['position_id' => $positionId])->whereNull('effective_date')->first();
            }
            if ($positionWage && $positionWage->wages_status) {
                $checkExist = OnboardingEmployees::select('sub_position_id')->where('sub_position_id', $positionId)->union(User::select('sub_position_id')->where('sub_position_id', $positionId))->distinct()->count('sub_position_id');
                if ($checkExist > 0) {
                    $this->errorResponse('This position is already onboarded or hired and cannot be disabled!!', 'add-position-wages', '', 400);
                }
            }
        }

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $effectiveDate = $request->effective_date ?? null;

            // STEP 1: Fetch MOST RECENT position wage to compare (not just same effective_date)
            // This follows the same pattern used in editPositionAll() line 3063-3066
            if ($effectiveDate !== null) {
                $oldWage = PositionWage::where('position_id', $positionId)
                    ->where('effective_date', '<=', $effectiveDate)
                    ->orderBy('effective_date', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                // Fallback to NULL effective_date (template) if no dated record found
                if (!$oldWage) {
                    $oldWage = PositionWage::where('position_id', $positionId)
                        ->whereNull('effective_date')
                        ->first();
                }
            } else {
                // For NULL effective_date (only_new_users): get existing template record
                $oldWage = PositionWage::where('position_id', $positionId)
                    ->whereNull('effective_date')
                    ->first();
            }

            // STEP 2: Check if wages changed
            $wagesChanged = false;
            if ($oldWage) {
                $wagesChanged = (
                    $oldWage->pay_type != ($request->pay_type ?? null) ||
                    $oldWage->pay_rate != ($request->pay_rate ?? null) ||
                    $oldWage->pay_rate_type != ($request->pay_rate_type ?? null) ||
                    $oldWage->expected_weekly_hours != ($request->expected_weekly_hours ?? null) ||
                    $oldWage->overtime_rate != ($request->overtime_rate ?? null) ||
                    $oldWage->pto_hours != ($request->pto_hours ?? null) ||
                    $oldWage->unused_pto_expires != ($request->unused_pto_expires ?? null) ||
                    $oldWage->wages_status != ($request->wages_status ?? 0)
                );
            } else {
                // New record - consider as changed
                $wagesChanged = true;
            }

            // STEP 3: Update PositionWage (always update)
            PositionWage::updateOrCreate(['position_id' => $positionId, 'effective_date' => $effectiveDate], [
                'pay_type' => $request->pay_type ?? null,
                'pay_type_lock' => $request->pay_type_lock ?? 0,
                'pay_rate' => $request->pay_rate ?? null,
                'pay_rate_type' => $request->pay_rate_type ?? null,
                'pay_rate_lock' => $request->pay_rate_lock ?? 0,
                'pto_hours' => $request->pto_hours ?? null,
                'pto_hours_lock' => $request->pto_hours_lock ?? 0,
                'unused_pto_expires' => $request->unused_pto_expires ?? null,
                'unused_pto_expires_lock' => $request->unused_pto_expires_lock ?? 0,
                'expected_weekly_hours' => $request->expected_weekly_hours ?? null,
                'expected_weekly_hours_lock' => $request->expected_weekly_hours_lock ?? 0,
                'overtime_rate' => $request->overtime_rate ?? null,
                'overtime_rate_lock' => $request->overtime_rate_lock ?? 0,
                'wages_status' => $request->wages_status ?? 0,
            ]);

            // STEP 4: Only update user wages if wages changed
            if (($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') && $wagesChanged) {
                if ($request->wizard_type == 'selective_users') {
                    $selectiveUsers = $request['selective_users'];
                    $userIdArr = [];
                    // Note: $selectiveUsers is a raw array from request, so array access is correct here
                    foreach ($selectiveUsers as $userId) {
                        $userIdArr[] = $userId['id'];
                    }
                } else {
                    $subQuery = UserOrganizationHistory::select(
                        'id',
                        'user_id',
                        'effective_date',
                        DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
                    )->where('effective_date', '<=', $effectiveDate);

                    $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                        ->mergeBindings($subQuery->getQuery())
                        ->select('user_id', 'effective_date')
                        ->where('rn', 1)->get();

                    $closestDates = $results->map(function ($result) {
                        return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
                    });

                    $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
                        foreach ($closestDates as $closestDate) {
                            $query->orWhere(function ($q) use ($closestDate) {
                                $q->where('user_id', $closestDate['user_id'])
                                    ->where('effective_date', $closestDate['effective_date']);
                            });
                        }
                    })->where('sub_position_id', $positionId)->pluck('user_id')->toArray();
                }

                $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();

                // OPTIMIZATION: Batch process user wages updates
                $this->batchUpdateUserWages($users, $request, $effectiveDate);

                // SYNC USER HISTORY DATA
                $userIds = $users->pluck('id')->toArray();
                $this->dispatchHistorySyncWithChunking($userIds, auth()->user()->id, 'wage_update');
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            // Clear positions cache to refresh UI immediately
            PositionCacheService::clear();
            $this->successResponse('Add successfully!!', 'add-position-wages');
        } else {
            $this->errorResponse($response['message'], 'add-position-products', '', 500);
        }
    }

    /**
     * Batch update user wages history
     */
    private function batchUpdateUserWages($users, $request, string $effectiveDate): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->toArray();

        // Step 1: Fetch all existing wages history for these users BEFORE the effective date
        $existingWages = UserWagesHistory::whereIn('user_id', $userIds)
            ->where('effective_date', '<', $effectiveDate)
            ->orderBy('user_id')
            ->orderBy('effective_date', 'DESC')
            ->get()
            ->groupBy('user_id')
            ->map(function ($userWages) {
                return $userWages->first(); // Get the most recent record for each user
            });

        // Step 2: Prepare batch data
        $recordsToInsert = [];
        $recordsToUpdate = [];

        foreach ($users as $user) {
            $userId = $user->id;
            $oldWagesDetail = $existingWages->get($userId);

            $newData = [
                'updater_id' => auth()->user()->id,
                'pay_type' => $request->pay_type ?? null,
                'old_pay_type' => $oldWagesDetail->pay_type ?? null,
                'pay_rate' => $request->pay_rate ?? null,
                'old_pay_rate' => $oldWagesDetail->pay_rate ?? null,
                'pay_rate_type' => $request->pay_rate_type ?? null,
                'old_pay_rate_type' => $oldWagesDetail->pay_rate_type ?? null,
                'expected_weekly_hours' => $request->expected_weekly_hours ?? null,
                'old_expected_weekly_hours' => $oldWagesDetail->expected_weekly_hours ?? null,
                'overtime_rate' => $request->overtime_rate ?? null,
                'old_overtime_rate' => $oldWagesDetail->overtime_rate ?? null,
                'pto_hours' => $request->pto_hours ?? null,
                'old_pto_hours' => $oldWagesDetail->pto_hours ?? null,
                'unused_pto_expires' => $request->unused_pto_expires ?? null,
                'old_unused_pto_expires' => $oldWagesDetail->unused_pto_expires ?? null,
                'pto_hours_effective_date' => $effectiveDate,
                'updated_at' => now(),
            ];

            // Check if record exists for this exact effective_date
            $existingRecord = UserWagesHistory::where('user_id', $userId)
                ->where('effective_date', $effectiveDate)
                ->first();

            if ($existingRecord) {
                // Update existing record
                $recordsToUpdate[] = [
                    'id' => $existingRecord->id,
                    'data' => $newData,
                ];
            } else {
                // Insert new record
                $recordsToInsert[] = array_merge([
                    'user_id' => $userId,
                    'effective_date' => $effectiveDate,
                    'created_at' => now(),
                ], $newData);
            }
        }

        // Step 3: Batch insert new records
        if (!empty($recordsToInsert)) {
            UserWagesHistory::insert($recordsToInsert);
        }

        // Step 4: Batch update existing records
        foreach ($recordsToUpdate as $record) {
            UserWagesHistory::where('id', $record['id'])->update($record['data']);
        }
    }

    public function commission(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users,selective_users',
            'effective_date' => 'required_if:wizard_type,all_users,selective_users',
            'commission' => 'required|array|min:1',
            'commission.*.product_id' => 'required|integer',
            'commission.*.commission_status' => 'required|integer|in:0,1',
            'commission.*.data' => 'required_if:commission.*.commission_status,1',
            'commission.*.data.*.self_gen_user' => 'required|boolean|in:0,1',
            'commission.*.data.*.commission_amount_type' => 'required|string',
            'commission.*.data.*.commission_amount_type_locked' => 'required|in:0,1',
            'commission.*.data.*.commission_parentage' => 'required',
            'commission.*.data.*.commission_parentag_hiring_locked' => 'required|in:0,1',
            'commission.*.data.*.commission_structure_type' => 'required|string',
            'commission.*.data.*.commission_parentag_type_hiring_locked' => 'required|in:0,1',
            // 'commission.*.data.*.commission_limit' => 'nullable|integer'
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            $effectiveDate = $request->effective_date ?? null;

            // STEP 1: Fetch MOST RECENT position commissions for comparison (not just same effective_date)
            // This ensures we compare against actual current values, not just the current date
            $oldPositionCommissions = collect();

            if ($effectiveDate !== null) {
                $subQuery = PositionCommission::select(
                    'id',
                    'product_id',
                    'core_position_id',
                    'self_gen_user',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY product_id, core_position_id, self_gen_user ORDER BY effective_date DESC, id DESC) as rn')
                )->where('position_id', $positionId)
                 ->where('effective_date', '<=', $effectiveDate);

                $mostRecentIds = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())
                    ->where('rn', 1)
                    ->pluck('id');

                    $oldPositionCommissions = PositionCommission::whereIn('id', $mostRecentIds)
                    ->get()
                    ->keyBy(function($item) {
                        return $item->product_id . '_' . $item->core_position_id . '_' . $item->self_gen_user;
                    });

                // Fallback to NULL effective_date (template) if no dated records found
                if ($oldPositionCommissions->isEmpty()) {
                    $oldPositionCommissions = PositionCommission::where('position_id', $positionId)
                        ->whereNull('effective_date')
                        ->get()
                        ->keyBy(function($item) {
                            return $item->product_id . '_' . $item->core_position_id . '_' . $item->self_gen_user;
                        });
                }
            } else {
                // For NULL effective_date (only_new_users): get existing template records
                $oldPositionCommissions = PositionCommission::where('position_id', $positionId)
                    ->whereNull('effective_date')
                    ->get()
                    ->keyBy(function($item) {
                        return $item->product_id . '_' . $item->core_position_id . '_' . $item->self_gen_user;
                    });
            }

            // Delete old records for THIS effective_date only
            $positionCommissions = PositionCommission::where(['position_id' => $positionId, 'effective_date' => $effectiveDate])->pluck('id');
            if ($positionCommissions) {
                PositionCommission::whereIn('id', $positionCommissions)->delete();
                TiersPositionCommission::whereIn('position_commission_id', $positionCommissions)->delete();
            }
            if ($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') {
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }

            if ($request->wizard_type == 'selective_users') {
                $users = $request['selective_users'];
                $userIdArr = [];
                // Note: $users is currently a raw array from request, so $user['id'] array access is correct here
                foreach ($users as $user) {
                    $userIdArr[] = $user['id'];
                }

                // Now fetch actual User models - after this point, $users contains Eloquent models
                $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
            }

            $companySettingTiers = CompanySetting::where(['type' => 'tier', 'status' => '1'])->first();
            
            // Check if Custom Sales Fields feature is enabled ONCE before loops (using cached helper)
            $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();

            // Track which users were updated for final sync
            $usersToSync = [];

            foreach ($request->commission as $commission) {
                if ($commission['commission_status'] == 1) {
                    foreach ($commission['data'] as $data) {
                        $tiersAdvancement = isset($data['tiers_advancement']) ? $data['tiers_advancement'] : null;
                        $tiersId = (isset($data['tiers_id']) && ! empty($data['tiers_id'])) ? $data['tiers_id'] : 0;

                        // Custom Sales Field support: Parse custom_field_X format
                        $commissionAmountType = $data['commission_amount_type'];
                        $customSalesFieldId = $data['custom_sales_field_id'] ?? null;

                        // Only parse custom_field_X format if feature is enabled (using cached check from outer scope)
                        if ($isCustomFieldsEnabled) {
                            // If commission_amount_type is in format "custom_field_X", extract the ID
                            if (preg_match('/^custom_field_(\d+)$/', $commissionAmountType, $matches)) {
                                $commissionAmountType = 'custom field';
                                $customSalesFieldId = (int) $matches[1];
                            }
                        }

                        $positionCommission = PositionCommission::create([
                            'position_id' => $positionId,
                            'core_position_id' => $data['core_position_id'],
                            'product_id' => $commission['product_id'],
                            'self_gen_user' => $data['self_gen_user'],
                            'commission_parentage' => $data['commission_parentage'],
                            'commission_parentag_hiring_locked' => $data['commission_parentag_hiring_locked'],
                            'commission_amount_type' => $commissionAmountType,
                            'commission_amount_type_locked' => $data['commission_amount_type_locked'],
                            'commission_structure_type' => $data['commission_structure_type'],
                            'commission_parentag_type_hiring_locked' => $data['commission_parentag_type_hiring_locked'],
                            'commission_status' => $commission['commission_status'],
                            'tiers_id' => $tiersId,
                            'tiers_hiring_locked' => isset($data['tiers_hiring_locked']) ? $data['tiers_hiring_locked'] : 0,
                            'commission_limit' => @$data['commission_limit'] ? $data['commission_limit'] : null,
                            'commission_limit_type' => @$data['commission_limit_type'] ? $data['commission_limit_type'] : null,
                            'effective_date' => $effectiveDate ?? null,
                            'custom_sales_field_id' => $customSalesFieldId,
                        ]);

                        $positionCommissionId = $positionCommission->id;
                        $range = (isset($data['tiers_range']) && ! empty($data['tiers_range'])) ? $data['tiers_range'] : null;

                        // OPTIMIZATION: Batch insert tiers (1 query instead of N)
                        if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                            $tierRecords = [];
                            $now = now();
                            foreach ($range as $rang) {
                                $tierRecords[] = [
                                    'position_id' => $positionId,
                                    'position_commission_id' => $positionCommissionId,
                                    'product_id' => $commission['product_id'],
                                    'tiers_schema_id' => $tiersId,
                                    'tiers_advancement' => $tiersAdvancement,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'commission_value' => $rang['value'] ?? null,
                                    'commission_type' => $data['commission_amount_type'],
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                            if (!empty($tierRecords)) {
                                TiersPositionCommission::insert($tierRecords);
                            }
                        }

                        // STEP 2: Check if commission values changed by comparing old vs new
                        $commissionKey = $commission['product_id'] . '_' . $data['core_position_id'] . '_' . $data['self_gen_user'];
                        $oldCommission = $oldPositionCommissions->get($commissionKey);

                        $commissionChanged = false;
                        if (!$oldCommission) {
                            // New commission record - consider as changed
                            $commissionChanged = true;
                        } else {
                            // Compare critical commission fields
                            $commissionChanged = (
                                $oldCommission->commission_parentage != $data['commission_parentage'] ||
                                $oldCommission->commission_amount_type != $data['commission_amount_type'] ||
                                $oldCommission->self_gen_user != $data['self_gen_user'] ||
                                $oldCommission->tiers_id != $tiersId
                            );
                        }

                        // STEP 3: Only update user commission history if commission changed
                        if ($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') {
                            if ($commissionChanged) {
                                // OPTIMIZATION: Batch process user commission updates (without sync)
                                $this->batchUpdateUserCommissions(
                                    $users,
                                    $request->wizard_type,
                                    $commission['product_id'],
                                    $data,
                                    $positionId,
                                    $effectiveDate,
                                    $tiersId,
                                    $range,
                                    $companySettingTiers,
                                    false // Don't sync yet - will sync once at the end
                                );

                                // Mark users for syncing
                                if (empty($usersToSync)) {
                                    foreach ($users as $user) {
                                        $usersToSync[] = $user->id;
                                    }
                                }
                            }
                            // else: Commission NOT changed - skip user updates
                        }
                    }
                } else {
                    PositionCommission::create([
                        'position_id' => $positionId,
                        'core_position_id' => null,
                        'product_id' => $commission['product_id'],
                        'self_gen_user' => 0,
                        'commission_parentage' => 0,
                        'commission_parentag_hiring_locked' => 0,
                        'commission_amount_type' => 'percent',
                        'commission_amount_type_locked' => 0,
                        'commission_structure_type' => null,
                        'commission_parentag_type_hiring_locked' => 0,
                        'commission_status' => $commission['commission_status'],
                        'tiers_id' => 0, // remove as per new tiers tiers_id
                        'tiers_hiring_locked' => 0,
                        'effective_date' => $effectiveDate,
                    ]);
                    PositionCommissionUpfronts::where(['position_id' => $positionId, 'product_id' => $commission['product_id'], 'effective_date' => $effectiveDate])->delete();
                    PositionCommissionUpfronts::create([
                        'position_id' => $positionId,
                        'product_id' => $commission['product_id'],
                        'upfront_status' => 0,
                        'effective_date' => $effectiveDate,
                        'deductible_from_prior' => '0',
                    ]);
                }
            }

            // OPTIMIZATION: Sync user history only ONCE at the end (not per product)
            if (!empty($usersToSync)) {
                $this->dispatchHistorySyncWithChunking(array_unique($usersToSync), auth()->user()->id, 'commission_update');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            // Clear positions cache to refresh UI immediately
            PositionCacheService::clear();
            $this->successResponse('Add successfully!!', 'add-position-commission');
        } else {
            $this->errorResponse($response['message'], 'add-position-commission', '', 500);
        }
    }

    /**
     * Batch update user commissions to avoid N+1 queries
     * Optimized: Reduces 1000+ queries to ~10 queries for 100 users
     */
    private function batchUpdateUserCommissions(
        $users,
        string $wizardType,
        int $productId,
        array $data,
        int $positionId,
        ?string $effectiveDate,
        int $tiersId,
        ?array $range,
        $companySettingTiers,
        bool $shouldSync = true
    ): void
    {
        // Step 1: Collect user IDs
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = $user->id;
        }

        if (empty($userIds)) {
            return;
        }

        // Step 2: Fetch existing user commission histories (1 query instead of N)
        $existingCommissions = UserCommissionHistory::where([
            'product_id' => $productId,
            'core_position_id' => $data['core_position_id'],
            'commission_effective_date' => $effectiveDate,
        ])->whereIn('user_id', $userIds)
          ->get()
          ->keyBy('user_id');

        // Step 3: Prepare batch data
        $now = now();
        $recordsToInsert = [];
        $recordsToUpdate = [];
        $commissionHistoryIds = [];

        foreach ($userIds as $userId) {
            $commissionData = [
                'position_id' => $positionId,
                'sub_position_id' => $positionId,
                'updater_id' => auth()->user()->id,
                'self_gen_user' => $data['self_gen_user'],
                'commission' => $data['commission_parentage'],
                'commission_type' => $data['commission_amount_type'],
                'tiers_id' => $tiersId,
            ];

            if ($existingCommissions->has($userId)) {
                // Update existing record
                $existing = $existingCommissions->get($userId);
                $recordsToUpdate[] = [
                    'id' => $existing->id,
                    'data' => array_merge($commissionData, ['updated_at' => $now]),
                ];
                $commissionHistoryIds[$userId] = $existing->id;
            } else {
                // Insert new record
                $recordsToInsert[] = array_merge($commissionData, [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'core_position_id' => $data['core_position_id'],
                    'commission_effective_date' => $effectiveDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Step 4: Batch insert new records (1 query)
        if (!empty($recordsToInsert)) {
            UserCommissionHistory::insert($recordsToInsert);

            // Get IDs of newly inserted records (with timestamp filter to prevent race conditions)
            $newCommissions = UserCommissionHistory::where([
                'product_id' => $productId,
                'core_position_id' => $data['core_position_id'],
                'commission_effective_date' => $effectiveDate,
            ])->where('created_at', '>=', $now)
              ->whereIn('user_id', array_column($recordsToInsert, 'user_id'))
              ->get();

            foreach ($newCommissions as $comm) {
                $commissionHistoryIds[$comm->user_id] = $comm->id;
            }
        }

        // Step 5: Batch update existing records (1 query per record, but only if needed)
        foreach ($recordsToUpdate as $record) {
            UserCommissionHistory::where('id', $record['id'])->update($record['data']);
        }

        // Step 6: Handle tier ranges (if enabled)
        if ($companySettingTiers?->status && $tiersId > 0 && is_array($range) && count($range) > 0) {
            // Batch delete old tier ranges (1 query)
            UserCommissionHistoryTiersRange::whereIn('user_commission_history_id', array_values($commissionHistoryIds))->delete();

            // Batch insert new tier ranges (1 query)
            $tierRangeRecords = [];
            foreach ($commissionHistoryIds as $userId => $commissionHistoryId) {
                foreach ($range as $rang) {
                    $tierRangeRecords[] = [
                        'user_id' => $userId,
                        'user_commission_history_id' => $commissionHistoryId,
                        'tiers_schema_id' => $tiersId,
                        'tiers_levels_id' => $rang['id'] ?? null,
                        'value' => $rang['value'] ?? null,
                        'value_type' => $data['commission_amount_type'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($tierRangeRecords)) {
                UserCommissionHistoryTiersRange::insert($tierRangeRecords);
            }
        }

        // Step 7: Dispatch history sync job (only if shouldSync is true)
        if ($shouldSync) {
            $this->dispatchHistorySyncWithChunking($userIds, auth()->user()->id, 'commission_update');
        }
    }

    /**
     * Batch update user upfronts to avoid N+1 queries
     * Optimized: Reduces 1000+ queries to ~10 queries for 100 users
     */
    private function batchUpdateUserUpfronts(
        $users,
        int $productId,
        array $schema,
        array $data,
        int $corePositionId,
        int $positionId,
        ?string $effectiveDate,
        ?int $tiersId,
        ?array $range,
        $companySettingTiers,
        bool $shouldSync = true
    ): void
    {
        // Step 1: Collect user IDs
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = $user->id;
        }

        if (empty($userIds)) {
            return;
        }

        // Step 2: Fetch existing user upfront histories (1 query instead of N)
        $existingUpfronts = UserUpfrontHistory::where([
            'product_id' => $productId,
            'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
            'core_position_id' => $data['core_position_id'],
            'upfront_effective_date' => $effectiveDate,
        ])->whereIn('user_id', $userIds)
          ->get()
          ->keyBy('user_id');

        // Step 3: Prepare batch data
        $now = now();
        $recordsToInsert = [];
        $recordsToUpdate = [];
        $upfrontHistoryIds = [];

        foreach ($userIds as $userId) {
            $upfrontData = [
                'position_id' => $corePositionId,
                'sub_position_id' => $positionId,
                'milestone_schema_id' => $data['milestone_id'],
                'self_gen_user' => $data['self_gen_user'],
                'updater_id' => auth()->user()->id,
                'upfront_pay_amount' => $schema['upfront_ammount'],
                'upfront_sale_type' => $schema['calculated_by'],
                'tiers_id' => $schema['tiers_id'] ?? null,
                'custom_sales_field_id' => $schema['custom_sales_field_id'] ?? null,
            ];

            if ($existingUpfronts->has($userId)) {
                // Update existing record
                $existing = $existingUpfronts->get($userId);
                $recordsToUpdate[] = [
                    'id' => $existing->id,
                    'data' => array_merge($upfrontData, ['updated_at' => $now]),
                ];
                $upfrontHistoryIds[$userId] = $existing->id;
            } else {
                // Insert new record
                $recordsToInsert[] = array_merge($upfrontData, [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                    'core_position_id' => $data['core_position_id'],
                    'upfront_effective_date' => $effectiveDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Step 4: Batch insert new records (1 query)
        if (!empty($recordsToInsert)) {
            UserUpfrontHistory::insert($recordsToInsert);

            // Get IDs of newly inserted records (with timestamp filter to prevent race conditions)
            $newUpfronts = UserUpfrontHistory::where([
                'product_id' => $productId,
                'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                'core_position_id' => $data['core_position_id'],
                'upfront_effective_date' => $effectiveDate,
            ])->where('created_at', '>=', $now)
              ->whereIn('user_id', array_column($recordsToInsert, 'user_id'))
              ->get();

            foreach ($newUpfronts as $upfront) {
                $upfrontHistoryIds[$upfront->user_id] = $upfront->id;
            }
        }

        // Step 5: Batch update existing records
        foreach ($recordsToUpdate as $record) {
            UserUpfrontHistory::where('id', $record['id'])->update($record['data']);
        }

        // Step 6: Handle tier ranges (if enabled)
        if ($companySettingTiers?->status && $tiersId > 0 && is_array($range) && count($range) > 0) {
            // Batch delete old tier ranges (1 query)
            UserUpfrontHistoryTiersRange::whereIn('user_upfront_history_id', array_values($upfrontHistoryIds))->delete();

            // Batch insert new tier ranges (1 query)
            $tierRangeRecords = [];
            foreach ($upfrontHistoryIds as $userId => $upfrontHistoryId) {
                foreach ($range as $rang) {
                    $tierRangeRecords[] = [
                        'user_id' => $userId,
                        'user_upfront_history_id' => $upfrontHistoryId,
                        'tiers_schema_id' => $schema['tiers_id'],
                        'tiers_levels_id' => $rang['id'] ?? null,
                        'value' => $rang['value'] ?? null,
                        'value_type' => $schema['calculated_by'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($tierRangeRecords)) {
                UserUpfrontHistoryTiersRange::insert($tierRangeRecords);
            }
        }

        // Step 7: Dispatch history sync job (only if shouldSync is true)
        if ($shouldSync) {
            $this->dispatchHistorySyncWithChunking($userIds, auth()->user()->id, 'upfront_update');
        }
    }

    /**
     * Batch update user overrides to avoid N+1 queries
     * Optimized: Reduces 1000+ queries to ~10 queries for 100 users
     */
    private function batchUpdateUserOverrides(
        $users,
        int $productId,
        array $overrideArray,
        ?string $effectiveDate,
        $companySettingTiers,
        bool $shouldSync = true
    ): void
    {
        // Step 1: Collect user IDs
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = $user->id;
        }

        if (empty($userIds)) {
            return;
        }

        // Step 2: Fetch existing user override histories (1 query instead of N)
        $existingOverrides = UserOverrideHistory::where([
            'product_id' => $productId,
            'override_effective_date' => $effectiveDate,
        ])->whereIn('user_id', $userIds)
          ->get()
          ->keyBy('user_id');

        // Step 3: Prepare batch data
        $now = now();
        $recordsToInsert = [];
        $recordsToUpdate = [];
        $overrideHistoryIds = [];

        foreach ($userIds as $userId) {
            $overrideData = [
                'updater_id' => auth()->user()->id,
                'direct_overrides_amount' => $overrideArray['direct_overrides_amount'],
                'direct_overrides_type' => $overrideArray['direct_overrides_type'],
                'indirect_overrides_amount' => $overrideArray['indirect_overrides_amount'],
                'indirect_overrides_type' => $overrideArray['indirect_overrides_type'],
                'office_overrides_amount' => $overrideArray['office_overrides_amount'],
                'office_overrides_type' => $overrideArray['office_overrides_type'],
                'office_stack_overrides_amount' => $overrideArray['office_stack_overrides_amount'],
                'direct_tiers_id' => $overrideArray['direct_tiers_id'] ?? null,
                'indirect_tiers_id' => $overrideArray['indirect_tiers_id'] ?? null,
                'office_tiers_id' => $overrideArray['office_tiers_id'] ?? null,
            ];

            if ($existingOverrides->has($userId)) {
                // Update existing record
                $existing = $existingOverrides->get($userId);
                $recordsToUpdate[] = [
                    'id' => $existing->id,
                    'data' => array_merge($overrideData, ['updated_at' => $now]),
                ];
                $overrideHistoryIds[$userId] = $existing->id;
            } else {
                // Insert new record
                $recordsToInsert[] = array_merge($overrideData, [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'override_effective_date' => $effectiveDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Step 4: Batch insert new records (1 query)
        if (!empty($recordsToInsert)) {
            UserOverrideHistory::insert($recordsToInsert);

            // Get IDs of newly inserted records (with timestamp filter to prevent race conditions)
            $newOverrides = UserOverrideHistory::where([
                'product_id' => $productId,
                'override_effective_date' => $effectiveDate,
            ])->where('created_at', '>=', $now)
              ->whereIn('user_id', array_column($recordsToInsert, 'user_id'))
              ->get();

            foreach ($newOverrides as $override) {
                $overrideHistoryIds[$override->user_id] = $override->id;
            }
        }

        // Step 5: Batch update existing records
        foreach ($recordsToUpdate as $record) {
            UserOverrideHistory::where('id', $record['id'])->update($record['data']);
        }

        // Step 6: Handle tier ranges for all three override types (if enabled)
        if ($companySettingTiers?->status) {
            // Batch delete old tier ranges (3 queries)
            UserDirectOverrideHistoryTiersRange::whereIn('user_override_history_id', array_values($overrideHistoryIds))->delete();
            UserIndirectOverrideHistoryTiersRange::whereIn('user_override_history_id', array_values($overrideHistoryIds))->delete();
            UserOfficeOverrideHistoryTiersRange::whereIn('user_office_override_history_id', array_values($overrideHistoryIds))->delete();

            // Batch insert direct override tier ranges
            if ($overrideArray['direct_tiers_id'] > 0 && is_array($overrideArray['direct_tiers_range']) && count($overrideArray['direct_tiers_range']) > 0) {
                $directTierRecords = [];
                foreach ($overrideHistoryIds as $userId => $overrideHistoryId) {
                    foreach ($overrideArray['direct_tiers_range'] as $range) {
                        $directTierRecords[] = [
                            'user_id' => $userId,
                            'user_override_history_id' => $overrideHistoryId,
                            'tiers_schema_id' => $overrideArray['direct_tiers_id'],
                            'tiers_levels_id' => $range['id'] ?? null,
                            'value' => $range['value'] ?? null,
                            'value_type' => $overrideArray['direct_overrides_type'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                if (!empty($directTierRecords)) {
                    UserDirectOverrideHistoryTiersRange::insert($directTierRecords);
                }
            }

            // Batch insert indirect override tier ranges
            if ($overrideArray['indirect_tiers_id'] > 0 && is_array($overrideArray['indirect_tiers_range']) && count($overrideArray['indirect_tiers_range']) > 0) {
                $indirectTierRecords = [];
                foreach ($overrideHistoryIds as $userId => $overrideHistoryId) {
                    foreach ($overrideArray['indirect_tiers_range'] as $range) {
                        $indirectTierRecords[] = [
                            'user_id' => $userId,
                            'user_override_history_id' => $overrideHistoryId,
                            'tiers_schema_id' => $overrideArray['indirect_tiers_id'],
                            'tiers_levels_id' => $range['id'] ?? null,
                            'value' => $range['value'] ?? null,
                            'value_type' => $overrideArray['indirect_overrides_type'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                if (!empty($indirectTierRecords)) {
                    UserIndirectOverrideHistoryTiersRange::insert($indirectTierRecords);
                }
            }

            // Batch insert office override tier ranges
            if ($overrideArray['office_tiers_id'] > 0 && is_array($overrideArray['office_tiers_range']) && count($overrideArray['office_tiers_range']) > 0) {
                $officeTierRecords = [];
                foreach ($overrideHistoryIds as $userId => $overrideHistoryId) {
                    foreach ($overrideArray['office_tiers_range'] as $range) {
                        $officeTierRecords[] = [
                            'user_id' => $userId,
                            'user_office_override_history_id' => $overrideHistoryId,
                            'tiers_schema_id' => $overrideArray['office_tiers_id'],
                            'tiers_levels_id' => $range['id'] ?? null,
                            'value' => $range['value'] ?? null,
                            'value_type' => $overrideArray['office_overrides_type'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                if (!empty($officeTierRecords)) {
                    UserOfficeOverrideHistoryTiersRange::insert($officeTierRecords);
                }
            }
        }

        // Step 7: Dispatch history sync job (only if shouldSync is true)
        if ($shouldSync) {
            $this->dispatchHistorySyncWithChunking($userIds, auth()->user()->id, 'override_update');
        }
    }

    /**
     * Batch update user settlements to avoid N+1 queries
     * Optimized: Reduces 200+ queries to ~10 queries for 100 users
     */
    private function batchUpdateUserSettlements(
        $users,
        int $productId,
        array $settlement,
        int $corePositionId,
        int $positionId,
        ?string $effectiveDate,
        bool $shouldSync = true
    ): void
    {
        // Step 1: Collect user IDs
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = $user->id;
        }

        if (empty($userIds)) {
            return;
        }

        // Step 2: Fetch existing user withheld histories (1 query instead of N)
        $existingWithhelds = UserWithheldHistory::where([
            'product_id' => $productId,
            'withheld_effective_date' => $effectiveDate,
        ])->whereIn('user_id', $userIds)
          ->get()
          ->keyBy('user_id');

        // Step 3: Prepare batch data
        $now = now();
        $recordsToInsert = [];
        $recordsToUpdate = [];

        foreach ($userIds as $userId) {
            $withheldData = [
                'updater_id' => auth()->user()->id,
                'position_id' => $corePositionId,
                'sub_position_id' => $positionId,
                'withheld_amount' => isset($settlement['commission_withheld']) ? $settlement['commission_withheld'] : 0,
                'withheld_type' => isset($settlement['commission_type']) ? $settlement['commission_type'] : null,
            ];

            if ($existingWithhelds->has($userId)) {
                // Update existing record
                $existing = $existingWithhelds->get($userId);
                $recordsToUpdate[] = [
                    'id' => $existing->id,
                    'data' => array_merge($withheldData, ['updated_at' => $now]),
                ];
            } else {
                // Insert new record
                $recordsToInsert[] = array_merge($withheldData, [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'withheld_effective_date' => $effectiveDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Step 4: Batch insert new records (1 query)
        if (!empty($recordsToInsert)) {
            UserWithheldHistory::insert($recordsToInsert);
        }

        // Step 5: Batch update existing records
        foreach ($recordsToUpdate as $record) {
            UserWithheldHistory::where('id', $record['id'])->update($record['data']);
        }

        // Step 6: Dispatch history sync job (only if shouldSync is true)
        if ($shouldSync) {
            $this->dispatchHistorySyncWithChunking($userIds, auth()->user()->id, 'settlement_update');
        }
    }

    public function upfront(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users,selective_users',
            'effective_date' => 'required_if:wizard_type,all_users,selective_users',
            'upfront' => 'required|array|min:1',
            'upfront.*.product_id' => 'required|integer',
            'upfront.*.upfront_status' => 'required|in:0,1',
            'upfront.*.data' => 'required_if:upfront.*.upfront_status,1',
            'upfront.*.data.*.milestone_id' => 'required|integer',
            'upfront.*.data.*.self_gen_user' => 'required|in:0,1',
            'upfront.*.data.*.schemas' => 'required|array|min:1',
            'upfront.*.data.*.schemas.*.milestone_schema_trigger_id' => 'required|integer',
            'upfront.*.data.*.schemas.*.upfront_ammount' => 'required|numeric',
            'upfront.*.data.*.schemas.*.upfront_ammount_locked' => 'required|in:0,1',
            'upfront.*.data.*.schemas.*.calculated_by' => 'required|string',
            'upfront.*.data.*.schemas.*.calculated_locked' => 'required|in:0,1',
            // 'upfront.*.data.*.schemas.*.upfront_limit' => 'nullable|integer'
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            $effectiveDate = $request->effective_date ?? null;

            // STEP 1: Fetch MOST RECENT position upfronts for comparison (not just same effective_date)
            $oldPositionUpfronts = collect();

            if ($effectiveDate !== null) {
                $subQuery = PositionCommissionUpfronts::select(
                    'id',
                    'product_id',
                    'core_position_id',
                    'milestone_schema_trigger_id',
                    'self_gen_user',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY product_id, core_position_id, milestone_schema_trigger_id, self_gen_user ORDER BY effective_date DESC, id DESC) as rn')
                )->where('position_id', $positionId)
                 ->where('effective_date', '<=', $effectiveDate);

                $mostRecentIds = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())
                    ->where('rn', 1)
                    ->pluck('id');

                    $oldPositionUpfronts = PositionCommissionUpfronts::whereIn('id', $mostRecentIds)
                    ->get()
                    ->keyBy(function($item) {
                        return $item->product_id . '_' . $item->core_position_id . '_' . $item->milestone_schema_trigger_id . '_' . $item->self_gen_user;
                    });

                // Fallback to NULL effective_date (template) if no dated records found
                if ($oldPositionUpfronts->isEmpty()) {
                    $oldPositionUpfronts = PositionCommissionUpfronts::where('position_id', $positionId)
                        ->whereNull('effective_date')
                        ->get()
                        ->keyBy(function($item) {
                            return $item->product_id . '_' . $item->core_position_id . '_' . $item->milestone_schema_trigger_id . '_' . $item->self_gen_user;
                        });
                }
            } else {
                // For NULL effective_date (only_new_users): get existing template records
                $oldPositionUpfronts = PositionCommissionUpfronts::where('position_id', $positionId)
                    ->whereNull('effective_date')
                    ->get()
                    ->keyBy(function($item) {
                        return $item->product_id . '_' . $item->core_position_id . '_' . $item->milestone_schema_trigger_id . '_' . $item->self_gen_user;
                    });
            }

            // Delete old records for THIS effective_date only
            $positionUpfronts = PositionCommissionUpfronts::where(['position_id' => $positionId, 'effective_date' => $effectiveDate])->pluck('id');
            if ($positionUpfronts) {
                PositionCommissionUpfronts::whereIn('id', $positionUpfronts)->delete();
                TiersPositionUpfront::whereIn('position_upfront_id', $positionUpfronts)->delete();
            }
            if ($request->wizard_type == 'all_users') {
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }

            if ($request->wizard_type == 'selective_users') {
                $users = $request['selective_users'];
                $userIdArr = [];
                // Note: $users is currently a raw array from request, so $user['id'] array access is correct here
                foreach ($users as $user) {
                    $userIdArr[] = $user['id'];
                }
                // Now fetch actual User models - after this point, $users contains Eloquent models
                $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
            }

            $position = Positions::where('id', $positionId)->first();
            $corePositionId = $position->parent_id ? $position->parent_id : $position->id;
            $companySettingTiers = CompanySetting::where(['type' => 'tier', 'status' => '1'])->first();
            
            // Check if Custom Sales Fields feature is enabled ONCE before loops (using cached helper)
            $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();

            // Track which users were updated for final sync
            $usersToSync = [];

            foreach ($request->upfront as $upfront) {
                $productId = $upfront['product_id'];
                if ($upfront['upfront_status'] == 1) {
                    foreach ($upfront['data'] as $data) {
                        foreach ($data['schemas'] as $schema) {
                            $tiersId = isset($schema['tiers_id']) ? $schema['tiers_id'] : null;
                            $tiersAdvancement = isset($schema['tiers_advancement']) ? $schema['tiers_advancement'] : null;

                            // Custom Sales Field support: Parse custom_field_X format for calculated_by
                            $calculatedBy = $schema['calculated_by'];
                            $customSalesFieldId = $schema['custom_sales_field_id'] ?? null;

                            // Only parse custom_field_X format if feature is enabled (using cached check from outer scope)
                            if ($isCustomFieldsEnabled) {
                                // If calculated_by is in format "custom_field_X", extract the ID
                                if (preg_match('/^custom_field_(\d+)$/', $calculatedBy, $matches)) {
                                    $calculatedBy = 'custom field';
                                    $customSalesFieldId = (int) $matches[1];
                                }
                            }

                            $positionUpFront = PositionCommissionUpfronts::create([
                                'position_id' => $positionId,
                                'core_position_id' => $data['core_position_id'],
                                'product_id' => $productId,
                                'self_gen_user' => $data['self_gen_user'],
                                'milestone_schema_id' => $data['milestone_id'],
                                'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                                'upfront_ammount' => $schema['upfront_ammount'],
                                'upfront_ammount_locked' => $schema['upfront_ammount_locked'] ?? 0,
                                'calculated_by' => $calculatedBy,
                                'calculated_locked' => $schema['calculated_locked'],
                                'upfront_status' => $upfront['upfront_status'],
                                'upfront_system' => @$schema['upfront_system'] ? $schema['upfront_system'] : 'Fixed',
                                'upfront_system_locked' => @$schema['upfront_system_locked'] ? $schema['upfront_system_locked'] : 0,
                                'upfront_limit' => @$schema['upfront_limit'] ? $schema['upfront_limit'] : null,
                                'upfront_limit_type' => @$schema['upfront_limit_type'] ? $schema['upfront_limit_type'] : null,
                                'tiers_id' => $tiersId,
                                'tiers_hiring_locked' => isset($schema['tiers_hiring_locked']) ? $schema['tiers_hiring_locked'] : 0,
                                'tiers_advancement' => $tiersAdvancement,
                                'effective_date' => $effectiveDate ?? null,
                                'deductible_from_prior' => isset($schema['deductible_from_prior']) ? (string) $schema['deductible_from_prior'] : '0',
                                'custom_sales_field_id' => $customSalesFieldId,
                            ]);

                            $positionUpFrontId = $positionUpFront->id;
                            $range = isset($schema['tiers_range']) && ! empty($schema['tiers_range']) ? $schema['tiers_range'] : null;

                            // OPTIMIZATION: Batch insert tiers (1 query instead of N)
                            if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                                $tierRecords = [];
                                $now = now();
                                foreach ($range as $rang) {
                                    $tierRecords[] = [
                                        'position_id' => $positionId,
                                        'position_upfront_id' => $positionUpFrontId,
                                        'product_id' => $productId,
                                        'milestone_schema_id' => $data['milestone_id'],
                                        'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                                        'tiers_schema_id' => $tiersId,
                                        'tiers_levels_id' => $rang['id'] ?? null,
                                        'upfront_value' => $rang['value'] ?? null,
                                        'upfront_type' => $schema['calculated_by'],
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ];
                                }
                                if (!empty($tierRecords)) {
                                    TiersPositionUpfront::insert($tierRecords);
                                }
                            }

                            // STEP 2: Check if upfront values changed by comparing old vs new
                            $upfrontKey = $productId . '_' . $data['core_position_id'] . '_' . $schema['milestone_schema_trigger_id'] . '_' . $data['self_gen_user'];
                            $oldUpfront = $oldPositionUpfronts->get($upfrontKey);

                            $upfrontChanged = false;
                            if (!$oldUpfront) {
                                // New upfront record - consider as changed
                                $upfrontChanged = true;
                            } else {
                                // Compare critical upfront fields (only fields that affect user history)
                                $upfrontChanged = (
                                    $oldUpfront->upfront_ammount != $schema['upfront_ammount'] ||
                                    $oldUpfront->calculated_by != $schema['calculated_by'] ||
                                    $oldUpfront->self_gen_user != $data['self_gen_user'] ||
                                    $oldUpfront->tiers_id != $tiersId ||
                                    $oldUpfront->milestone_schema_id != $data['milestone_id']
                                );
                            }

                            // STEP 3: Only update user upfront history if upfront changed
                            if ($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') {
                                if ($upfrontChanged) {
                                    // OPTIMIZATION: Batch process user upfront updates (without sync)
                                    $this->batchUpdateUserUpfronts(
                                        $users,
                                        $productId,
                                        $schema,
                                        $data,
                                        $corePositionId,
                                        $positionId,
                                        $effectiveDate,
                                        $tiersId,
                                        $range,
                                        $companySettingTiers,
                                        false // Don't sync yet - will sync once at the end
                                    );

                                    // Mark users for syncing
                                    if (empty($usersToSync)) {
                                        foreach ($users as $user) {
                                            $usersToSync[] = $user->id;
                                        }
                                    }
                                }
                                // else: Upfront NOT changed - skip user updates
                            }
                        }
                    }
                } else {
                    PositionCommissionUpfronts::create([
                        'position_id' => $positionId,
                        'core_position_id' => null,
                        'product_id' => $productId,
                        'self_gen_user' => 0,
                        'milestone_schema_id' => null,
                        'milestone_schema_trigger_id' => null,
                        'upfront_ammount' => 0,
                        'upfront_ammount_locked' => 0,
                        'calculated_by' => 'per kw',
                        'calculated_locked' => 0,
                        'upfront_status' => 0,
                        'upfront_system' => 'Fixed',
                        'upfront_system_locked' => 0,
                        'upfront_limit' => null,
                        'upfront_limit_type' => null,
                        'tiers_id' => 0,
                        'tiers_hiring_locked' => 0,
                        'effective_date' => $effectiveDate,
                        'deductible_from_prior' => '0',
                    ]);
                }
            }

            // OPTIMIZATION: Sync user history only ONCE at the end (not per schema/product)
            if (!empty($usersToSync)) {
                $this->dispatchHistorySyncWithChunking(array_unique($usersToSync), auth()->user()->id, 'upfront_update');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            // Clear positions cache to refresh UI immediately
            PositionCacheService::clear();
            $this->successResponse('Add successfully!!', 'add-position-upfront');
        } else {
            $this->errorResponse($response['message'], 'add-position-upfront', '', 500);
        }
    }

    public function deduction(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users,selective_users',
            'effective_date' => 'required_if:wizard_type,all_users,selective_users',
            'deduction_status' => 'required|in:0,1',
            'deduction' => 'required_if:deduction_status,1',
            'deduction.*.cost_center_id' => 'required|integer',
            'deduction.*.deduction_type' => 'required',
            'deduction.*.ammount_par_paycheck' => 'required',
            'deduction.*.pay_period_from' => 'required',
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;

            // STEP 1: Fetch old deductions BEFORE deleting (for comparison)
            $oldDeductions = PositionCommissionDeduction::where('position_id', $positionId)
                ->get()
                ->keyBy('cost_center_id');

            PositionCommissionDeduction::where('position_id', $positionId)->delete();
            PositionCommissionDeductionSetting::updateOrCreate(['position_id' => $positionId], [
                'status' => $request->deduction_status,
            ]);

            $positionPayFrequency = PositionPayFrequency::where('position_id', $positionId)->first();
            $usersToSync = []; // Track users for final sync

            if ($request->deduction_status) {
                $effectiveDate = date('Y-m-d');
                if ($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') {
                    $effectiveDate = $request->effective_date;
                }

                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
                if ($request->wizard_type == 'selective_users') {
                    $users = $request['selective_users'];
                    $userIdArr = [];
                    // Note: $users is currently a raw array from request, so $user['id'] array access is correct here
                    foreach ($users as $user) {
                        $userIdArr[] = $user['id'];
                    }
                    // Now fetch actual User models - after this point, $users contains Eloquent models
                    $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
                }

                foreach ($request->deduction as $deduction) {
                    // STEP 2: Check if deduction changed
                    $oldDeduction = $oldDeductions->get($deduction['cost_center_id']);
                    $deductionChanged = false;

                    if ($oldDeduction) {
                        $deductionChanged = (
                            $oldDeduction->deduction_type != $deduction['deduction_type'] ||
                            $oldDeduction->ammount_par_paycheck != $deduction['ammount_par_paycheck'] ||
                            $oldDeduction->changes_type != ($deduction['changes_type'] ?? null) ||
                            $oldDeduction->changes_field != ($deduction['changes_field'] ?? null) ||
                            $oldDeduction->pay_period_from != ($deduction['pay_period_from'] ?? null) ||
                            $oldDeduction->pay_period_to != ($deduction['pay_period_to'] ?? null)
                        );
                    } else {
                        // New deduction - consider as changed
                        $deductionChanged = true;
                    }

                    // STEP 3: Create position deduction (always)
                    PositionCommissionDeduction::create([
                        'position_id' => $request->position_id,
                        'deduction_setting_id' => 1,
                        'deduction_type' => $deduction['deduction_type'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'ammount_par_paycheck' => $deduction['ammount_par_paycheck'],
                        'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                        'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                        'pay_period_from' => isset($deduction['pay_period_from']) ? $deduction['pay_period_from'] : null,
                        'pay_period_to' => isset($deduction['pay_period_to']) ? $deduction['pay_period_to'] : null,
                        'effective_date' => $effectiveDate ?? null,
                    ]);

                    if ($request->wizard_type == 'only_new_users') {
                        $deduction['ammount_par_paycheck'] = 0;
                    }

                    // STEP 4: Handle UserDeduction (master record)
                    foreach ($users as $user) {
                        $userId = $user->id;
                        $checkUserDeduction = UserDeduction::where(['user_id' => $userId, 'cost_center_id' => $deduction['cost_center_id']])->first();
                        if (! $checkUserDeduction) {
                            $costCenter = CostCenter::select('name')->where('id', $deduction['cost_center_id'])->first();
                            $position = Positions::find($request->position_id);
                            $dataInsert = [
                                'deduction_type' => $deduction['deduction_type'],
                                'cost_center_name' => isset($costCenter->name) ? $costCenter->name : null,
                                'cost_center_id' => $deduction['cost_center_id'],
                                'ammount_par_paycheck' => $deduction['ammount_par_paycheck'],
                                'deduction_setting_id' => isset($deduction['deduction_setting_id']) ? $deduction['deduction_setting_id'] : null,
                                'position_id' => isset($position->parent_id) ? $position->parent_id : $request->position_id,
                                'sub_position_id' => isset($request->position_id) ? $request->position_id : null,
                                'user_id' => $userId,
                                'effective_date' => $effectiveDate,
                            ];
                            UserDeduction::create($dataInsert);
                        }
                    }

                    // STEP 5: Only update user deduction history if deduction changed
                    if (($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') && $deductionChanged) {
                        // OPTIMIZATION: Batch process user deduction updates
                        $this->batchUpdateUserDeductions($users, $deduction, $request, $effectiveDate);

                        // Mark users for syncing
                        if (empty($usersToSync)) {
                            foreach ($users as $user) {
                                $usersToSync[] = $user->id;
                            }
                        }
                    }

                    if ($positionPayFrequency) {
                        if ($positionPayFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                            $weeklyPayFrequency = WeeklyPayFrequency::where('pay_period_from', '<=', $deduction['pay_period_from'])->get();
                            foreach ($weeklyPayFrequency as $weekly) {
                                PayrollDeductions::where([
                                    'pay_period_from' => $weekly->pay_period_from,
                                    'pay_period_to' => $weekly->pay_period_to,
                                    'cost_center_id' => $deduction['cost_center_id'],
                                    'status' => '1',
                                ])->delete();
                            }
                        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                            $monthlyPayFrequency = MonthlyPayFrequency::where('pay_period_from', '<=', $deduction['pay_period_from'])->get();
                            foreach ($monthlyPayFrequency as $monthly) {
                                PayrollDeductions::where([
                                    'pay_period_from' => $monthly->pay_period_from,
                                    'pay_period_to' => $monthly->pay_period_to,
                                    'cost_center_id' => $deduction['cost_center_id'],
                                    'status' => '1',
                                ])->delete();
                            }
                        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                            $additionalFrequency = AdditionalPayFrequency::where('type', '1')->where('pay_period_from', '<=', $deduction['pay_period_from'])->get();
                            foreach ($additionalFrequency as $additional) {
                                PayrollDeductions::where([
                                    'pay_period_from' => $additional->pay_period_from,
                                    'pay_period_to' => $additional->pay_period_to,
                                    'cost_center_id' => $deduction['cost_center_id'],
                                    'status' => '1',
                                ])->delete();
                            }
                        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                            $additionalFrequency = AdditionalPayFrequency::where('type', '2')->where('pay_period_from', '<=', $deduction['pay_period_from'])->get();
                            foreach ($additionalFrequency as $additional) {
                                PayrollDeductions::where([
                                    'pay_period_from' => $additional->pay_period_from,
                                    'pay_period_to' => $additional->pay_period_to,
                                    'cost_center_id' => $deduction['cost_center_id'],
                                    'status' => '1',
                                ])->delete();
                            }
                        }
                    }
                }
            }

            // OPTIMIZATION: Sync user history only ONCE at the end (not per deduction)
            if (!empty($usersToSync)) {
                $this->dispatchHistorySyncWithChunking(array_unique($usersToSync), auth()->user()->id, 'deduction_update');
            }

            PositionsDeductionLimit::updateOrCreate(['position_id' => $request->position_id], [
                'limit_ammount' => $request->limit_ammount,
                'limit' => $request->limit,
                'status' => $request->deduction_status,
                'limit_type' => $request->limit_type,
            ]);

            if (isset($request->position_status) && $request->position_status) {
                Positions::where('id', $request->position_id)->update(['setup_status' => $request->position_status]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            // Clear positions cache to refresh UI immediately
            PositionCacheService::clear();
            $this->successResponse('Saved successfully!!', 'position-deduction');
        } else {
            $this->errorResponse($response['message'], 'position-deduction', '', 500);
        }
    }

    public function removeDeduction($id): JsonResponse
    {
        if (! PositionCommissionDeduction::find($id)) {
            return response()->json([
                'ApiName' => 'remove-deduction',
                'status' => false,
                'message' => 'Deduction not found!!',
            ], 400);
        }

        PositionCommissionDeduction::where('id', $id)->delete();

        return response()->json([
            'ApiName' => 'remove-deduction',
            'status' => true,
            'message' => 'Deduction removed successfully!!',
        ]);
    }

    /**
     * Batch update user deduction history
     */
    private function batchUpdateUserDeductions($users, $deduction, $request, string $effectiveDate): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->toArray();
        $costCenterId = $deduction['cost_center_id'];

        // Step 1: Fetch existing deduction history for this effective date
        $existingHistory = UserDeductionHistory::whereIn('user_id', $userIds)
            ->where('cost_center_id', $costCenterId)
            ->where('effective_date', $effectiveDate)
            ->get()
            ->keyBy('user_id');

        // Step 2: Prepare batch data
        $recordsToInsert = [];
        $recordsToUpdate = [];

        foreach ($users as $user) {
            $userId = $user->id;
            $existingRecord = $existingHistory->get($userId);

            $newData = [
                'updater_id' => auth()->user()->id,
                'limit_value' => isset($request['limit_ammount']) ? $request['limit_ammount'] : null,
                'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                'pay_period_from' => isset($deduction['pay_period_from']) ? $deduction['pay_period_from'] : null,
                'pay_period_to' => isset($deduction['pay_period_to']) ? $deduction['pay_period_to'] : null,
                'updated_at' => now(),
            ];

            if ($existingRecord) {
                // Update existing record
                $recordsToUpdate[] = [
                    'id' => $existingRecord->id,
                    'data' => $newData,
                ];
            } else {
                // Insert new record
                $recordsToInsert[] = array_merge([
                    'user_id' => $userId,
                    'cost_center_id' => $costCenterId,
                    'old_amount_par_paycheque' => null,
                    'sub_position_id' => isset($request->position_id) ? $request->position_id : null,
                    'effective_date' => $effectiveDate,
                    'created_at' => now(),
                ], $newData);
            }
        }

        // Step 3: Batch insert new records
        if (!empty($recordsToInsert)) {
            UserDeductionHistory::insert($recordsToInsert);
        }

        // Step 4: Batch update existing records
        foreach ($recordsToUpdate as $record) {
            UserDeductionHistory::where('id', $record['id'])->update($record['data']);
        }
    }

    public function override(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users,selective_users',
            'effective_date' => 'required_if:wizard_type,all_users,selective_users',
            'overrides' => 'required|array|min:1',
            'overrides.*.product_id' => 'required|integer',
            'overrides.*.status' => 'required|in:0,1',
            'overrides.*.override' => 'required_if:overrides.*.status,1',
            'overrides.*.override.*.override_id' => 'required|integer',
            'overrides.*.override.*.status' => 'required|in:0,1',
            'overrides.*.override.*.override_ammount' => 'required_if:overrides.*.override.*.status,1',
            'overrides.*.override.*.override_ammount_locked' => 'required|in:0,1',
            'overrides.*.override.*.override_type_locked' => 'required|in:0,1',
            // 'overrides.*.override.*.override_limit' => 'nullable|integer'
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            $effectiveDate = $request->effective_date ?? null;

            // STEP 1: Fetch MOST RECENT position overrides for comparison (not just same effective_date)
            $oldPositionOverrides = collect();

            if ($effectiveDate !== null) {
                $subQuery = PositionOverride::select(
                    'id',
                    'product_id',
                    'override_id',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY product_id, override_id ORDER BY effective_date DESC, id DESC) as rn')
                )->where('position_id', $positionId)
                 ->where('effective_date', '<=', $effectiveDate);

                 $mostRecentIds = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                 ->mergeBindings($subQuery->getQuery())
                 ->where('rn', 1)
                 ->pluck('id');

             $oldPositionOverrides = PositionOverride::whereIn('id', $mostRecentIds)
                 ->get()
                 ->keyBy(function($item) {
                     return $item->product_id . '_' . $item->override_id;
                 });

             // Fallback to NULL effective_date (template) if no dated records found
             if ($oldPositionOverrides->isEmpty()) {
                 $oldPositionOverrides = PositionOverride::where('position_id', $positionId)
                     ->whereNull('effective_date')
                     ->get()
                     ->keyBy(function($item) {
                         return $item->product_id . '_' . $item->override_id;
                     });
             }
         } else {
             // For NULL effective_date (only_new_users): get existing template records
             $oldPositionOverrides = PositionOverride::where('position_id', $positionId)
                 ->whereNull('effective_date')
                 ->get()
                 ->keyBy(function($item) {
                     return $item->product_id . '_' . $item->override_id;
                 });
         }

            // Delete old records for THIS effective_date only
            $positionOverrides = PositionOverride::where(['position_id' => $positionId, 'effective_date' => $effectiveDate])->pluck('id');
            if ($positionOverrides) {
                PositionOverride::whereIn('id', $positionOverrides)->delete();
                TiersPositionOverrides::whereIn('position_overrides_id', $positionOverrides)->delete();
            }
            PositionTierOverride::where(['position_id' => $positionId])->delete();
            if ($request->wizard_type == 'all_users') {
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }
            if ($request->wizard_type == 'selective_users') {
                $users = $request['selective_users'];
                $userIdArr = [];
                // Note: $users is currently a raw array from request, so $user['id'] array access is correct here
                foreach ($users as $user) {
                    $userIdArr[] = $user['id'];
                }
                // Now fetch actual User models - after this point, $users contains Eloquent models
                $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
            }

            $position = Positions::where('id', $positionId)->first();
            $corePositionId = $position->parent_id ? $position->parent_id : $position->id;
            $companySettingTiers = CompanySetting::where(['type' => 'tier', 'status' => '1'])->first();
            
            // Check if Custom Sales Fields feature is enabled ONCE before loops (using cached helper)
            $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();

            // Track which users were updated for final sync
            $usersToSync = [];

            foreach ($request->overrides as $overrides) {
                $overrideArray = [
                    'direct_overrides_amount' => null,
                    'direct_overrides_type' => null,
                    'direct_tiers_id' => 0,
                    'direct_tiers_range' => [],
                    'indirect_overrides_amount' => null,
                    'indirect_overrides_type' => null,
                    'indirect_tiers_id' => 0,
                    'indirect_tiers_range' => [],
                    'office_overrides_amount' => null,
                    'office_overrides_type' => null,
                    'office_tiers_id' => 0,
                    'office_tiers_range' => [],
                    'office_stack_overrides_amount' => null,
                ];
                foreach ($overrides['override'] as $override) {
                    $status = $override['status'];
                    if (! $overrides['status']) {
                        $status = 0;
                    }

                    $range = null;
                    $tiersId = null;
                    if ($status == 1) {
                        $tiersId = isset($override['tiers_id']) ? $override['tiers_id'] : 0;

                        // Custom Sales Field support: Parse custom_field_X format for override type
                        $overrideType = @$override['type'] ? $override['type'] : null;
                        $customSalesFieldId = $override['custom_sales_field_id'] ?? null;

                        // Map custom sales field ID to the correct override-type-specific column
                        $overrideTypeSpecificFields = [];

                        // Only parse custom_field_X format if feature is enabled (using cached check from outer scope)
                        if ($isCustomFieldsEnabled) {
                            // If type is in format "custom_field_X", extract the ID
                            if ($overrideType && preg_match('/^custom_field_(\d+)$/', $overrideType, $matches)) {
                                $overrideType = 'custom field';
                                $customSalesFieldId = (int) $matches[1];
                            }

                            if ($customSalesFieldId) {
                                switch ($override['override_id']) {
                                    case 1: // Direct override
                                        $overrideTypeSpecificFields['direct_custom_sales_field_id'] = $customSalesFieldId;
                                        break;
                                    case 2: // Indirect override
                                        $overrideTypeSpecificFields['indirect_custom_sales_field_id'] = $customSalesFieldId;
                                        break;
                                    case 3: // Office override
                                        $overrideTypeSpecificFields['office_custom_sales_field_id'] = $customSalesFieldId;
                                        break;
                                }
                            }
                        }

                        $positionOverride = PositionOverride::create(array_merge([
                            'position_id' => $request->position_id,
                            'product_id' => $overrides['product_id'],
                            'override_id' => $override['override_id'],
                            'status' => $status,
                            'override_ammount' => $override['override_ammount'],
                            'override_ammount_locked' => $override['override_ammount_locked'],
                            'type' => $overrideType,
                            'override_type_locked' => $override['override_type_locked'],
                            'tiers_id' => $tiersId,
                            'tiers_hiring_locked' => isset($overrides['tiers_hiring_locked']) ? $overrides['tiers_hiring_locked'] : 0,
                            'override_limit' => @$override['override_limit'] ? $override['override_limit'] : null,
                            'override_limit_type' => @$override['override_limit_type'] ? $override['override_limit_type'] : null,
                            'effective_date' => $effectiveDate ?? null,
                        ], $overrideTypeSpecificFields));

                        $positionOverrideId = $positionOverride->id;
                        $range = isset($override['tiers_range']) && ! empty($override['tiers_range']) ? $override['tiers_range'] : null;

                        // OPTIMIZATION: Batch insert tiers (1 query instead of N)
                        if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                            $tierRecords = [];
                            $now = now();
                            foreach ($range as $rang) {
                                $tierRecords[] = [
                                    'position_id' => $positionId,
                                    'position_overrides_id' => $positionOverrideId,
                                    'product_id' => $overrides['product_id'],
                                    'override_id' => $override['override_id'],
                                    'tiers_schema_id' => $tiersId,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'override_value' => $rang['value'] ?? null,
                                    'override_type' => @$override['type'] ? $override['type'] : null,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                            if (!empty($tierRecords)) {
                                TiersPositionOverrides::insert($tierRecords);
                            }
                        }
                    } else {
                        PositionOverride::create([
                            'position_id' => $positionId,
                            'product_id' => $overrides['product_id'],
                            'override_id' => $override['override_id'],
                            'settlement_id' => 0,
                            'override_ammount' => null,
                            'override_ammount_locked' => 0,
                            'type' => null,
                            'override_type_locked' => 0,
                            'status' => $status,
                            'override_limit' => null,
                            'override_limit_type' => null,
                            'tiers_hiring_locked' => 0,
                            'effective_date' => $effectiveDate,
                        ]);
                    }

                    if ($override['override_id'] == '1') {
                        $overrideArray['direct_overrides_amount'] = $override['override_ammount'];
                        $overrideArray['direct_overrides_type'] = @$override['type'] ? $override['type'] : null;
                        $overrideArray['direct_tiers_id'] = $tiersId;
                        $overrideArray['direct_tiers_range'] = $range;
                    } elseif ($override['override_id'] == '2') {
                        $overrideArray['indirect_overrides_amount'] = $override['override_ammount'];
                        $overrideArray['indirect_overrides_type'] = @$override['type'] ? $override['type'] : null;
                        $overrideArray['indirect_tiers_id'] = $tiersId;
                        $overrideArray['indirect_tiers_range'] = $range;
                    } elseif ($override['override_id'] == '3') {
                        $overrideArray['office_overrides_amount'] = $override['override_ammount'];
                        $overrideArray['office_overrides_type'] = @$override['type'] ? $override['type'] : null;
                        $overrideArray['office_tiers_id'] = $tiersId;
                        $overrideArray['office_tiers_range'] = $range;
                    } elseif ($override['override_id'] == '4') {
                        $overrideArray['office_stack_overrides_amount'] = $override['override_ammount'];
                    }
                }

                if ($overrides['status']) {
                    PositionTierOverride::create([
                        'position_id' => $positionId,
                        'tier_status' => $overrides['status'],
                    ]);
                }

                // STEP 2: Check if override values changed by comparing old vs new
                $overrideChanged = false;

                // Check all four override types to see if any changed
                foreach ($overrides['override'] as $override) {
                    $overrideKey = $overrides['product_id'] . '_' . $override['override_id'];
                    $oldOverride = $oldPositionOverrides->get($overrideKey);

                    if (!$oldOverride) {
                        // New override record - consider as changed
                        $overrideChanged = true;
                        break;
                    } else {
                        // Compare critical override fields
                        $changed = (
                            $oldOverride->override_ammount != $override['override_ammount'] ||
                            $oldOverride->type != (@$override['type'] ? $override['type'] : null) ||
                            $oldOverride->status != ($override['status'] && $overrides['status'] ? 1 : 0) ||
                            $oldOverride->tiers_id != (isset($override['tiers_id']) ? $override['tiers_id'] : 0)
                        );

                        if ($changed) {
                            $overrideChanged = true;
                            break;
                        }
                    }
                }

                // STEP 3: Only update user override history if override changed
                if ($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') {
                    if ($overrideChanged) {
                        // OPTIMIZATION: Batch process user override updates (without sync)
                        $this->batchUpdateUserOverrides(
                            $users,
                            $overrides['product_id'],
                            $overrideArray,
                            $effectiveDate,
                            $companySettingTiers,
                            false // Don't sync yet - will sync once at the end
                        );

                        // Mark users for syncing
                        if (empty($usersToSync)) {
                            foreach ($users as $user) {
                                $usersToSync[] = $user->id;
                            }
                        }
                    }
                    // else: Override NOT changed - skip user updates
                }
            }

            // OPTIMIZATION: Sync user history only ONCE at the end (not per product)
            if (!empty($usersToSync)) {
                $this->dispatchHistorySyncWithChunking(array_unique($usersToSync), auth()->user()->id, 'override_update');
            }

            if (isset($request->position_status) && $request->position_status) {
                Positions::where('id', $request->position_id)->update(['setup_status' => $request->position_status]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            // Clear positions cache to refresh UI immediately
            PositionCacheService::clear();
            $this->successResponse('Add successfully!!', 'add-position-override');
        } else {
            $this->errorResponse($response['message'], 'add-position-override', '', 500);
        }
    }

    public function settlement(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users,selective_users',
            'effective_date' => 'required_if:wizard_type,all_users,selective_users',
            'settlement' => 'required|array|min:1',
            'settlement.*.product_id' => 'required|integer',
            'settlement.*.status' => 'required|in:0,1',
            'settlement.*.commission_withheld' => 'required_if:settlement.*.status,1',
            'settlement.*.commission_type' => 'required_if:settlement.*.status,1',
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            $effectiveDate = $request->effective_date ?? null;

            // STEP 1: Fetch MOST RECENT position settlements for comparison (not just same effective_date)
            $oldPositionSettlements = collect();

            if ($effectiveDate !== null) {
                $subQuery = PositionReconciliations::select(
                    'id',
                    'product_id',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY effective_date DESC, id DESC) as rn')
                )->where('position_id', $positionId)
                 ->where('effective_date', '<=', $effectiveDate);

                 $mostRecentIds = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                 ->mergeBindings($subQuery->getQuery())
                 ->where('rn', 1)
                 ->pluck('id');

                $oldPositionSettlements = PositionReconciliations::whereIn('id', $mostRecentIds)
                    ->get()
                    ->keyBy('product_id');

                // Fallback to NULL effective_date (template) if no dated records found
                if ($oldPositionSettlements->isEmpty()) {
                    $oldPositionSettlements = PositionReconciliations::where('position_id', $positionId)
                        ->whereNull('effective_date')
                        ->get()
                        ->keyBy('product_id');
                }
            } else {
                // For NULL effective_date (only_new_users): get existing template records
                $oldPositionSettlements = PositionReconciliations::where('position_id', $positionId)
                    ->whereNull('effective_date')
                    ->get()
                    ->keyBy('product_id');
            }

            // Calculate product differences for commission recalculation
            $currentProductIds = $oldPositionSettlements->pluck('product_id')->map(fn($id) => (int) $id)->toArray();
            $requestProductIds = collect($request->settlement)->pluck('product_id')->map(fn($id) => (int) $id)->toArray();
            $differences = array_diff($requestProductIds, $currentProductIds); // Products ADDED
            $differences2 = array_diff($currentProductIds, $requestProductIds); // Products REMOVED

            // Delete old records for THIS effective_date only
            $positionRecon = PositionReconciliations::where(['position_id' => $positionId, 'effective_date' => $effectiveDate])->pluck('id');
            if ($positionRecon) {
                PositionReconciliations::whereIn('id', $positionRecon)->delete();
            }
            if ($request->wizard_type == 'all_users') {
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }
            if ($request->wizard_type == 'selective_users') {
                $users = $request['selective_users'];
                $userIdArr = [];
                foreach ($users as $user) {
                    $userIdArr[] = $user['id'];
                }
                $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
            }

            $position = Positions::where('id', $positionId)->first();
            $corePositionId = $position->parent_id ? $position->parent_id : $position->id;

            // Track which users were updated for final sync
            $usersToSync = [];

            foreach ($request->settlement as $settlement) {
                PositionReconciliations::create([
                    'position_id' => $positionId,
                    'product_id' => $settlement['product_id'],
                    'commission_withheld' => isset($settlement['commission_withheld']) ? $settlement['commission_withheld'] : null,
                    'commission_type' => isset($settlement['commission_type']) ? $settlement['commission_type'] : null,
                    'maximum_withheld' => isset($settlement['maximum_withheld']) && ! empty($settlement['maximum_withheld']) ? $settlement['maximum_withheld'] : null,
                    'override_settlement' => isset($settlement['override_settlement']) && ! empty($settlement['override_settlement']) ? $settlement['override_settlement'] : null,
                    'clawback_settlement' => isset($settlement['clawback_settlement']) && ! empty($settlement['clawback_settlement']) ? $settlement['clawback_settlement'] : null,
                    'stack_settlement' => isset($settlement['stack_settlement']) && ! empty($settlement['stack_settlement']) ? $settlement['stack_settlement'] : null,
                    'status' => $settlement['status'],
                    'tiers_commission_settlement' => @$settlement['tiers_commission_settlement'] ?? null,
                    'tiers_override_settlement' => @$settlement['tiers_override_settlement'] ?? null,
                    'effective_date' => $effectiveDate,
                ]);

                // STEP 2: Check if settlement values changed by comparing old vs new
                $oldSettlement = $oldPositionSettlements->get($settlement['product_id']);

                $settlementChanged = false;
                if (!$oldSettlement) {
                    // New settlement record - consider as changed
                    $settlementChanged = true;
                } else {
                    // Compare critical settlement fields (only fields that affect user withheld history)
                    $settlementChanged = (
                        $oldSettlement->commission_withheld != (isset($settlement['commission_withheld']) ? $settlement['commission_withheld'] : null) ||
                        $oldSettlement->commission_type != (isset($settlement['commission_type']) ? $settlement['commission_type'] : null)
                    );
                }

                // STEP 3: Only update user withheld history if settlement changed
                if ($request->wizard_type == 'all_users' || $request->wizard_type == 'selective_users') {
                    if ($settlementChanged) {
                        // OPTIMIZATION: Batch process user settlement updates (without sync)
                        $this->batchUpdateUserSettlements(
                            $users,
                            $settlement['product_id'],
                            $settlement,
                            $corePositionId,
                            $positionId,
                            $effectiveDate,
                            false // Don't sync yet - will sync once at the end
                        );

                        // Mark users for syncing
                        if (empty($usersToSync)) {
                            foreach ($users as $user) {
                                $usersToSync[] = $user->id;
                            }
                        }
                    }
                    // else: Settlement NOT changed - skip user updates
                }
            }

            // OPTIMIZATION: Sync user history only ONCE at the end (not per product)
            if (!empty($usersToSync)) {
                $this->dispatchHistorySyncWithChunking(array_unique($usersToSync), auth()->user()->id, 'settlement_update');
            }

            if (isset($request->position_status) && $request->position_status) {
                Positions::where('id', $positionId)->update(['setup_status' => $request->position_status]);
            }

            // Trigger commission recalculation when products are added or removed
            // This ensures sales commissions reflect current position configuration
            if (($request->wizard_type === 'all_users' || $request->wizard_type === 'selective_users') && $effectiveDate) {
                
                $shouldRecalculate = false;
                $affectedProductIds = [];
                
                // Trigger 1: Products REMOVED (remove commission from affected sales)
                if (count($differences2) > 0) {
                    $shouldRecalculate = true;
                    $affectedProductIds = array_merge($affectedProductIds, array_values($differences2));
                    \Log::info('Sales recalculation triggered: Products removed', [
                        'position_id' => $positionId,
                        'removed_products' => $differences2
                    ]);
                }
                
                // Trigger 2: Products ADDED (add commission to affected sales)
                if (count($differences) > 0) {
                    $shouldRecalculate = true;
                    $affectedProductIds = array_merge($affectedProductIds, array_values($differences));
                    \Log::info('Sales recalculation triggered: Products added', [
                        'position_id' => $positionId,
                        'added_products' => $differences
                    ]);
                }
                
                // Execute recalculation if triggered
                if ($shouldRecalculate) {
                    // Broadcast: Sales recalculation triggered
                    try {
                        broadcast(new PositionUpdateProgress([
                            'positionId' => (int)$positionId,
                            'positionName' => $request->position_name,
                            'status' => 'processing',
                            'progress' => 55,
                            'message' => 'Triggering sales recalculation for ' . count($affectedProductIds) . ' product(s)',
                            'updatedBy' => $user->name,
                            'updatedById' => $user->id,
                            'initiatedAt' => $initiatedAt,
                            'completedAt' => null,
                            'uniqueKey' => $uniqueKey
                        ]));
                    } catch (\Exception $e) {
                        \Log::debug('Pusher broadcast failed', ['error' => $e->getMessage()]);
                    }
                    
                    $this->triggerCommissionRecalculation($positionId, array_unique($affectedProductIds), $effectiveDate);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            // Clear positions cache to refresh UI immediately
            PositionCacheService::clear();
            $this->successResponse('Add successfully!!', 'add-position-settlement');
        } else {
            $this->errorResponse($response['message'], 'add-position-settlement', '', 500);
        }
    }

    public function positionUserCount(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'effective_date' => 'required',
        ]);

        $positionId = $request->position_id;
        $effectiveDate = $request->effective_date;
        $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
        $this->successResponse('Successfully.', 'user-count', ['user_count' => count($users)]);
    }

    protected function getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate)
    {
        $subQuery = UserOrganizationHistory::select(
            'id',
            'user_id',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
        )->where('effective_date', '<=', $effectiveDate);

        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())
            ->select('user_id', 'effective_date')
            ->where('rn', 1)->get();

        $closestDates = $results->map(function ($result) {
            return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
        });

        $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
            foreach ($closestDates as $closestDate) {
                $query->orWhere(function ($q) use ($closestDate) {
                    $q->where('user_id', $closestDate['user_id'])
                        ->where('effective_date', $closestDate['effective_date']);
                });
            }
        })->where('sub_position_id', $positionId)->pluck('user_id')->toArray();

        return User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
    }

    /**
     * Create UserOrganizationHistory records for all effective dates between backdated date and current date
     * This ensures checkUsersProductForCalculations can find the correct product assignments
     */
    private function createBackdatedUserOrganizationHistories(int $positionId, array $productIds, string $backdatedEffectiveDate): void
    {
        try {
            $currentDate = date('Y-m-d');

            // Handle NULL effective dates (from position creation)
            if ($backdatedEffectiveDate === null || $backdatedEffectiveDate === '') {
                // Skipping backdated user organization history creation for null effective date
                return;
            }

            // Only proceed if this is a backdated assignment
            if (Carbon::parse($backdatedEffectiveDate)->gte(Carbon::today())) {
                // Skipping backdated user organization history creation for future effective date
                return;
            }

            // Get ALL current users in the position (not just users from backdated date)
            $users = User::where('sub_position_id', $positionId)->get();

            if (empty($users)) {
                // No users found for position
                return;
            }

            $position = Positions::find($positionId);

            if (! $position) {
                $this->logConditional('error', '❌ POSITION NOT FOUND', [
                    'position_id' => $positionId,
                    'action' => 'backdated UserOrganizationHistory creation',
                ]);

                return;
            }

            $parentPosition = $position->parent_id ? $position->parent_id : (($position->id == '2' || $position->id == '3') ? $position->id : 0);

            $totalRecordsCreated = 0;
            $totalRecordsSkipped = 0;

            foreach ($users as $user) {
                $userId = $user->id;
                $userRecordsCreated = 0;
                $userRecordsSkipped = 0;

                // CHRONOLOGICAL FIX: Get only dates that should be processed for backdating
                // CRITICAL: Don't process future product dates to prevent chronological violations
                // This resolves the issue where Product 26 (2025-07-01) appeared on 2025-03-15

                // Step 1: Get all effective dates that existed BEFORE the current backdated operation
                $existingDatesBeforeOperation = PositionProduct::where('position_id', $positionId)
                    ->whereNotNull('effective_date')
                    ->where('effective_date', '<', $backdatedEffectiveDate)
                    ->pluck('effective_date')
                    ->unique()
                    ->toArray();

                // Step 2: Only process dates that are >= backdated date AND <= current date
                // But exclude any dates that contain ONLY products added after the backdated date
                $allPositionEffectiveDates = [];

                // Always include the backdated effective date itself (primary operation date)
                $allPositionEffectiveDates[] = $backdatedEffectiveDate;

                // Get dates that should be processed (dates where existing products need to be propagated)
                $datesToCheck = PositionProduct::where('position_id', $positionId)
                    ->whereNotNull('effective_date')
                    ->where('effective_date', '>', $backdatedEffectiveDate)
                    ->where('effective_date', '<=', $currentDate)
                    ->pluck('effective_date')
                    ->unique()
                    ->toArray();

                foreach ($datesToCheck as $dateToCheck) {
                    // CRITICAL FIX: Only include this date if it has products that were explicitly added
                    // on or before the backdated date (excluding NULL effective dates)
                    // This prevents future products from contaminating past dates
                    $hasPreExistingProducts = PositionProduct::where('position_id', $positionId)
                        ->whereNotNull('effective_date') // Exclude NULL effective dates from this check
                        ->where('effective_date', '<=', $backdatedEffectiveDate)
                        ->whereNotIn('product_id', $productIds) // Exclude products being added now
                        ->exists();

                    // ADDITIONAL FIX: Also include dates if there are products with NULL effective dates
                    // This handles the case where original products were created without specific dates
                    $hasNullEffectiveDateProducts = PositionProduct::where('position_id', $positionId)
                        ->whereNull('effective_date')
                        ->exists();

                    if ($hasPreExistingProducts || $hasNullEffectiveDateProducts) {
                        $allPositionEffectiveDates[] = $dateToCheck;
                    }
                }

                $allPositionEffectiveDates = array_unique($allPositionEffectiveDates);

                // Also get any existing user-specific dates that should be processed
                // Apply same chronological filtering to prevent future product contamination
                $userSpecificDates = UserOrganizationHistory::where('user_id', $userId)
                    ->where('sub_position_id', $positionId)
                    ->whereNotNull('effective_date')
                    ->where('effective_date', '>=', $backdatedEffectiveDate)
                    ->where('effective_date', '<=', $currentDate)
                    ->pluck('effective_date')
                    ->unique()
                    ->toArray();

                // Filter user-specific dates to only include chronologically relevant ones
                $filteredUserDates = [];
                foreach ($userSpecificDates as $userDate) {
                    if ($userDate === $backdatedEffectiveDate || in_array($userDate, $allPositionEffectiveDates)) {
                        $filteredUserDates[] = $userDate;
                    }
                }
                $userSpecificDates = $filteredUserDates;

                // Combine both sources for comprehensive coverage
                $existingEffectiveDates = array_unique(array_merge($allPositionEffectiveDates, $userSpecificDates));

                // Always include the backdated effective date
                if (! in_array($backdatedEffectiveDate, $existingEffectiveDates)) {
                    $existingEffectiveDates[] = $backdatedEffectiveDate;
                }

                // Sort the dates chronologically
                sort($existingEffectiveDates);

                // Chronologically filtered effective date discovery completed

                // Processing products for effective dates

                // For each effective date, create UserOrganizationHistory records for ALL products that should exist
                foreach ($existingEffectiveDates as $effectiveDate) {
                    // Get ALL products that should exist on this effective date
                    // CHRONOLOGICAL SAFETY: Only products with effective_date <= $effectiveDate are included
                    // This ensures products only appear on dates on or after their addition date
                    $allProductsForDate = PositionProduct::where('position_id', $positionId)
                        ->where(function ($query) use ($effectiveDate) {
                            $query->whereNull('effective_date')  // Include original assignments
                                ->orWhere('effective_date', '<=', $effectiveDate);  // Include products added up to this date
                        })
                        ->whereNull('deleted_at')
                        ->pluck('product_id')
                        ->unique()
                        ->toArray();
                    // Performance: Removed debug logging
                    foreach ($allProductsForDate as $productId) {
                        // CRITICAL FIX: Use correct effective date based on hire date
                        $correctEffectiveDate = $this->getCorrectEffectiveDate($userId, $effectiveDate);

                        $existingRecord = UserOrganizationHistory::where([
                            'user_id' => $userId,
                            'sub_position_id' => $positionId,
                            'product_id' => $productId,
                            'effective_date' => $correctEffectiveDate,
                        ])->first();

                        if (! $existingRecord) {
                            $newRecord = UserOrganizationHistory::create([
                                'user_id' => $userId,
                                'sub_position_id' => $positionId,
                                'product_id' => $productId,
                                'effective_date' => $correctEffectiveDate, // Use corrected effective date
                                'self_gen_accounts' => ($position?->is_selfgen == 1 ? 1 : 0),
                                'updater_id' => auth()->user()?->id ?? 1,
                                'position_id' => $parentPosition,
                            ]);

                            $userRecordsCreated++;
                            $totalRecordsCreated++;

                            // Created user organization history record

                        } else {
                            $userRecordsSkipped++;
                            $totalRecordsSkipped++;

                            // Record already exists, skipped
                        }
                    }
                }

            }

            // Completed backdated user organization history creation

            // VALIDATE COMPLEX SCENARIO COMPLIANCE
            $this->validateComplexBackdatingScenario($positionId, $backdatedEffectiveDate);

        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN BACKDATED USERORGANIZATIONHISTORY CREATION', [
                'position_id' => $positionId,
                'product_ids' => $productIds,
                'backdated_effective_date' => $backdatedEffectiveDate,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);
            throw $e;
        }
    }

    /**
     * Ensure user organization histories exist for NULL effective date products
     * This fills gaps where hiring process might have missed creating records for NULL products
     * NOTE: With proper hiring fix, this should mostly be a safety net
     */
    private function ensureUserOrganizationHistoriesForNullProducts($positionId, $productIds)
    {
        try {
            // Ensuring user organization histories for null effective date products

            // Get ALL current users in the position
            $users = User::where('sub_position_id', $positionId)->get();

            if (empty($users)) {
                // No users found for position, no safety check needed
                return;
            }

            $position = Positions::find($positionId);
            if (! $position) {
                $this->logConditional('error', '❌ POSITION NOT FOUND', [
                    'position_id' => $positionId,
                    'action' => 'null_effective_date_safety_check',
                ]);

                return;
            }

            $parentPosition = $position->parent_id ? $position->parent_id : (($position->id == '2' || $position->id == '3') ? $position->id : 0);

            $totalRecordsCreated = 0;
            $totalRecordsSkipped = 0;

            foreach ($users as $user) {
                $userId = $user->id;
                $hireDate = $user->hire_date ?? date('Y-m-d');

                // Checking user for missing product histories

                foreach ($productIds as $productId) {
                    // Check if user has ANY user_organization_history record for this product
                    $hasAnyRecord = UserOrganizationHistory::where([
                        'user_id' => $userId,
                        'sub_position_id' => $positionId,
                        'product_id' => $productId,
                    ])->exists();

                    if (! $hasAnyRecord) {
                        // User is missing ALL records for this product - create one with hire date
                        UserOrganizationHistory::create([
                            'user_id' => $userId,
                            'sub_position_id' => $positionId,
                            'product_id' => $productId,
                            'effective_date' => $hireDate,
                            'self_gen_accounts' => ($position?->is_selfgen == 1 ? 1 : 0),
                            'updater_id' => auth()->user()?->id ?? 1,
                            'position_id' => $parentPosition,
                        ]);

                        $totalRecordsCreated++;

                        $this->logConditional('warning', '⚠️ CREATED MISSING USER ORGANIZATION HISTORY (SAFETY NET)', [
                            'user_id' => $userId,
                            'product_id' => $productId,
                            'effective_date' => $hireDate,
                            'position_id' => $positionId,
                            'reason' => 'user_had_no_records_for_null_product',
                        ]);
                    } else {
                        $totalRecordsSkipped++;
                        // Performance: Removed debug logging
                    }
                }
            }
            // Performance: Removed verbose info logging
        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN NULL EFFECTIVE DATE SAFETY CHECK', [
                'position_id' => $positionId,
                'product_ids' => $productIds,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate that complex backdating scenarios work correctly
     * Ensures ALL products have user organization history for ALL relevant effective dates
     */
    private function validateComplexBackdatingScenario($positionId, $effectiveDate = null)
    {
        try {
            // Validating complex backdating scenario

            // Get all effective dates for this position
            $allEffectiveDates = PositionProduct::where('position_id', $positionId)
                ->whereNotNull('effective_date')
                ->pluck('effective_date')
                ->unique()
                ->sort()
                ->toArray();

            if (empty($allEffectiveDates)) {
                // Performance: Removed verbose info logging
                return true;
            }

            $totalValidationErrors = 0;
            $totalRecordsChecked = 0;

            foreach ($allEffectiveDates as $date) {
                // For each date, get ALL products that should exist
                $expectedProducts = PositionProduct::where('position_id', $positionId)
                    ->where(function ($query) use ($date) {
                        $query->whereNull('effective_date')  // Original assignments
                            ->orWhere('effective_date', '<=', $date);  // Products added up to this date
                    })
                    ->whereNull('deleted_at')
                    ->pluck('product_id')
                    ->unique()
                    ->toArray();
                // Performance: Removed debug logging
                // Check if all users have records for all expected products on this date
                $users = User::where('sub_position_id', $positionId)->get();
                foreach ($users as $user) {
                    foreach ($expectedProducts as $productId) {
                        $totalRecordsChecked++;

                        // CRITICAL FIX: Check for records with corrected effective date, not raw date
                        $correctedDateForValidation = $this->getCorrectEffectiveDate($user->id, $date);

                        $exists = UserOrganizationHistory::where([
                            'user_id' => $user->id,
                            'sub_position_id' => $positionId,
                            'product_id' => $productId,
                            'effective_date' => $correctedDateForValidation,
                        ])->exists();

                        if (! $exists) {
                            $totalValidationErrors++;

                            $this->logConditional('error', '❌ MISSING USER ORGANIZATION HISTORY', [
                                'user_id' => $user->id,
                                'user_name' => $user->name,
                                'product_id' => $productId,
                                'requested_effective_date' => $date,
                                'corrected_effective_date' => $correctedDateForValidation,
                                'position_id' => $positionId,
                                'validation_error' => 'missing_user_org_history',
                            ]);

                            // AUTO-FIX: Create missing record with corrected effective date
                            $position = Positions::find($positionId);
                            $parentPosition = $position->parent_id ? $position->parent_id : (($position->id == '2' || $position->id == '3') ? $position->id : 0);

                            // CRITICAL FIX: Apply hire date adjustment for validation auto-fix
                            $correctedEffectiveDate = $this->getCorrectEffectiveDate($user->id, $date);

                            UserOrganizationHistory::create([
                                'user_id' => $user->id,
                                'sub_position_id' => $positionId,
                                'product_id' => $productId,
                                'effective_date' => $correctedEffectiveDate, // Use corrected date instead of raw $date
                                'self_gen_accounts' => ($position?->is_selfgen == 1 ? 1 : 0),
                                'updater_id' => auth()->user()?->id ?? 1,
                                'position_id' => $parentPosition,
                            ]);

                            // Auto-fixed missing user organization history record
                        }
                    }
                }
            }

            // Validation complete

            return $totalValidationErrors === 0;

        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN BACKDATING VALIDATION', [
                'position_id' => $positionId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            return false;
        }
    }

    /**
     * Propagate backdated products to all subsequent effective dates
     * This ensures backdated products are visible in APIs that use the most recent effective date
     */
    private function propagateBackdatedProductsToSubsequentDates($positionId, $productIds, $backdatedEffectiveDate, $removedProductIds = [])
    {
        try {
            // Handle NULL effective dates (from position creation)
            if ($backdatedEffectiveDate === null || $backdatedEffectiveDate === '') {
                // Performance: Removed verbose info logging
                return;
            }

            // Get all subsequent effective dates after the backdated date
            $subsequentDates = PositionProduct::where('position_id', $positionId)
                ->whereNotNull('effective_date')
                ->where('effective_date', '>', $backdatedEffectiveDate)
                ->pluck('effective_date')
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            $totalRecordsCreated = 0;
            $totalRecordsSkipped = 0;
            $totalProductsFiltered = 0;

            foreach ($subsequentDates as $subsequentDate) {
                foreach ($productIds as $productId) {
                    // 🚨 CRITICAL FIX: Apply chronological filtering to prevent future products on past dates
                    // Only propagate products that should chronologically exist on this subsequent date
                    $earliestProductDate = PositionProduct::where('position_id', $positionId)
                        ->where('product_id', $productId)
                        ->whereNotNull('effective_date')
                        ->min('effective_date');

                    // Check if this product should exist on this subsequent date
                    $shouldPropagateProduct = false;
                    $filterReason = '';

                    if (! $earliestProductDate) {
                        // Product has no previous effective date (new product), should be propagated
                        $shouldPropagateProduct = true;
                        $filterReason = 'new_product_with_no_previous_date';
                    } elseif ($earliestProductDate <= $subsequentDate) {
                        // Product's earliest date is before or equal to subsequent date, should be propagated
                        $shouldPropagateProduct = true;
                        $filterReason = 'chronologically_valid';
                    } else {
                        // Product's earliest date is after subsequent date, should NOT be propagated
                        $shouldPropagateProduct = false;
                        $filterReason = 'chronologically_invalid_future_product';
                    }
                    // Performance: Removed debug logging
                    if (! $shouldPropagateProduct) {
                        $totalProductsFiltered++;

                        // Performance: Removed debug logging
                        continue; // Skip this product for this date
                    }

                    $existingProduct = PositionProduct::where([
                        'position_id' => $positionId,
                        'product_id' => $productId,
                        'effective_date' => $subsequentDate,
                    ])->first();

                    if (! $existingProduct) {
                        $newProduct = PositionProduct::create([
                            'position_id' => $positionId,
                            'product_id' => $productId,
                            'effective_date' => $subsequentDate,
                        ]);

                        $totalRecordsCreated++;
                        // Performance: Removed debug logging
                    } else {
                        $totalRecordsSkipped++;
                        // Performance: Removed debug logging
                    }
                }

                // 🔧 CRITICAL FIX: Pass removed products to validatePropagationCompleteness to prevent them from being re-added
                // Validate propagation completeness for this subsequent date
                $this->validatePropagationCompleteness($positionId, $subsequentDate, $removedProductIds);
            }
            // Performance: Removed verbose info logging
            // VALIDATE COMPLEX SCENARIO COMPLIANCE
            $this->validateComplexBackdatingScenario($positionId, $backdatedEffectiveDate);

        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN PRODUCT PROPAGATION', [
                'position_id' => $positionId,
                'product_ids' => $productIds,
                'backdated_effective_date' => $backdatedEffectiveDate,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);
            throw $e;
        }
    }

    /**
     * Create comprehensive commission and upfront setup for backdated products
     * This ensures employment package APIs show commission data for all core position types
     */
    private function createBackdatedCommissionAndUpfrontSetup($positionId, $productIds, $backdatedEffectiveDate, $requestData = null)
    {
        try {
            // Handle NULL effective dates (from position creation)
            if ($backdatedEffectiveDate === null || $backdatedEffectiveDate === '') {
                // Performance: Removed verbose info logging
                return;
            }

            $position = Positions::find($positionId);
            if (! $position) {
                $this->logConditional('error', '❌ POSITION NOT FOUND FOR COMMISSION SETUP', [
                    'position_id' => $positionId,
                ]);

                return;
            }

            // 🚨 CRITICAL FIX: Apply chronological filtering to prevent future products from being processed
            // Only process products that should chronologically exist on the backdated effective date
            $chronologicallyValidProductIds = [];
            $filteredOutProductIds = [];

            foreach ($productIds as $productId) {
                // Check if this product should exist on the backdated effective date
                $earliestProductDate = PositionProduct::where('position_id', $positionId)
                    ->where('product_id', $productId)
                    ->whereNotNull('effective_date')
                    ->min('effective_date');

                $shouldProcess = false;
                $filterReason = '';

                if (! $earliestProductDate) {
                    // Product has no previous effective date (NULL products) - should be processed
                    $shouldProcess = true;
                    $filterReason = 'no_previous_effective_date';
                } elseif ($earliestProductDate <= $backdatedEffectiveDate) {
                    // Product's earliest date is before or equal to backdated date - should be processed
                    $shouldProcess = true;
                    $filterReason = 'chronologically_valid';
                } else {
                    // Product's earliest date is AFTER backdated date - should NOT be processed
                    $shouldProcess = false;
                    $filterReason = 'future_product_filtered_out';
                }

                if ($shouldProcess) {
                    $chronologicallyValidProductIds[] = $productId;
                } else {
                    $filteredOutProductIds[] = $productId;
                }

                // Applied chronological filter for backward propagation
            }

            // Chronological filtering applied to backward propagation

            // Update productIds to only include chronologically valid products
            $productIds = $chronologicallyValidProductIds;

            if (empty($productIds)) {
                // Performance: Removed verbose info logging
                return;
            }
            // Performance: Removed verbose info logging
            $parentPosition = $position->parent_id ? $position->parent_id : (($position->id == '2' || $position->id == '3') ? $position->id : 0);

            foreach ($productIds as $productId) {
                $existingCommissions = PositionCommission::where([
                    'position_id' => $positionId,
                    'product_id' => $productId,
                ])->get();

                $existingUpfronts = PositionCommissionUpfronts::where([
                    'position_id' => $positionId,
                    'product_id' => $productId,
                ])->get();

            }

            // Determine required core_position_id combinations based on position's is_selfgen value
            $corePositions = [];
            if ($position->is_selfgen == '1') {
                $corePositions = [
                    ['core_position_id' => 2, 'self_gen_user' => 0],
                    ['core_position_id' => 3, 'self_gen_user' => 0],
                    ['core_position_id' => null, 'self_gen_user' => 1],
                ];
            } elseif ($position->is_selfgen == '2' || $position->is_selfgen == '3') {
                $corePositions = [
                    ['core_position_id' => $position->is_selfgen, 'self_gen_user' => 0],
                ];
            } elseif ($position->is_selfgen == '0') {
                $corePositions = [
                    ['core_position_id' => 2, 'self_gen_user' => 0],
                ];
            }

            $totalPositionCommissionsCreated = 0;
            $totalPositionUpfrontsCreated = 0;
            $totalUserCommissionsCreated = 0;
            $totalUserUpfrontsCreated = 0;
            $totalUserOverridesCreated = 0;
            $totalUserRedlinesCreated = 0;

            // Step 1: Create Position-level records (PositionCommission & PositionCommissionUpfronts)
            foreach ($productIds as $productId) {
                foreach ($corePositions as $corePos) {
                    $corePositionId = $corePos['core_position_id'];
                    $selfGenUser = $corePos['self_gen_user'];

                    // 🔧 FIXED: Get existing commission configuration to copy values (latest configuration)
                    $existingCommissionConfig = PositionCommission::where([
                        'position_id' => $positionId,
                        'product_id' => $productId,
                        'core_position_id' => $corePositionId,
                    ])->where(function ($query) use ($backdatedEffectiveDate) {
                        $query->whereNull('effective_date')
                            ->orWhere('effective_date', '<=', $backdatedEffectiveDate);
                    })->orderBy('effective_date', 'DESC')->first(); // Get latest configuration up to backdated date

                    // 🔧 FIXED: Get existing upfront configuration to copy values (latest configuration)
                    $existingUpfrontConfig = PositionCommissionUpfronts::where([
                        'position_id' => $positionId,
                        'product_id' => $productId,
                        'core_position_id' => $corePositionId,
                    ])->where(function ($query) use ($backdatedEffectiveDate) {
                        $query->whereNull('effective_date')
                            ->orWhere('effective_date', '<=', $backdatedEffectiveDate);
                    })->orderBy('effective_date', 'DESC')->first(); // Get latest configuration up to backdated date

                    // 🔧 FIXED: Fallback to request data if no existing configuration found
                    if (! $existingCommissionConfig && $requestData && isset($requestData->commission)) {
                        foreach ($requestData->commission as $commission) {
                            if ($commission['product_id'] == $productId) {
                                foreach ($commission['data'] as $data) {
                                    if ($data['core_position_id'] == $corePositionId) {
                                        $existingCommissionConfig = (object) [
                                            'commission_parentage' => $data['commission_parentage'],
                                            'commission_status' => $commission['commission_status'],
                                            'commission_amount_type' => $data['commission_amount_type'],
                                            'commission_structure_type' => $data['commission_structure_type'],
                                            'commission_parentag_hiring_locked' => $data['commission_parentag_hiring_locked'],
                                            'commission_amount_type_locked' => $data['commission_amount_type_locked'],
                                            'commission_parentag_type_hiring_locked' => $data['commission_parentag_type_hiring_locked'],
                                            'tiers_id' => $data['tiers_id'] ?? 0,
                                            'tiers_hiring_locked' => $data['tiers_hiring_locked'] ?? 0,
                                        ];
                                        break 2;
                                    }
                                }
                            }
                        }
                    }

                    if (! $existingUpfrontConfig && $requestData && isset($requestData->upfront)) {
                        foreach ($requestData->upfront as $upfront) {
                            if ($upfront['product_id'] == $productId) {
                                foreach ($upfront['data'] as $data) {
                                    if ($data['core_position_id'] == $corePositionId) {
                                        foreach ($data['schemas'] as $schema) {
                                            $existingUpfrontConfig = (object) [
                                                'upfront_ammount' => $schema['upfront_ammount'],
                                                'upfront_status' => $upfront['upfront_status'],
                                                'calculated_by' => $schema['calculated_by'],
                                                'upfront_system' => 'Fixed',
                                                'upfront_ammount_locked' => $schema['upfront_ammount_locked'] ?? 0,
                                                'calculated_locked' => $schema['calculated_locked'] ?? 0,
                                                'upfront_system_locked' => 0,
                                                'upfront_limit' => $schema['upfront_limit'] ?? null,
                                                'upfront_limit_type' => $schema['upfront_limit_type'] ?? null,
                                                'tiers_id' => $data['tiers_id'] ?? null,
                                                'tiers_hiring_locked' => $data['tiers_hiring_locked'] ?? 0,
                                                'tiers_advancement' => $data['tiers_advancement'] ?? null,
                                                'deductible_from_prior' => '0',
                                                'milestone_schema_id' => $data['milestone_id'] ?? null,
                                                'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'] ?? null,
                                                'status_id' => 1,
                                            ];
                                            break 3;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Create PositionCommission record with copied values
                    $existingCommission = PositionCommission::where([
                        'position_id' => $positionId,
                        'product_id' => $productId,
                        'core_position_id' => $corePositionId,
                        'effective_date' => $backdatedEffectiveDate,
                    ])->first();

                    // 🔧 FIXED: Always create records for ALL core positions, even if no configuration exists
                    if (! $existingCommission) {
                        // 🔧 FIXED: Get commission values from request, database, or defaults
                        $commissionParentage = 0;
                        $commissionStatus = 0;
                        $commissionAmountType = 'percent';
                        $commissionStructureType = 'Fixed';

                        if ($requestData && $this->hasCommissionInRequest($requestData, $productId, $corePositionId)) {
                            // Get values directly from request
                            foreach ($requestData->commission as $commission) {
                                if ($commission['product_id'] == $productId) {
                                    foreach ($commission['data'] as $data) {
                                        if ($data['core_position_id'] == $corePositionId) {
                                            $commissionParentage = $data['commission_parentage'] ?? 0;
                                            $commissionStatus = $commission['commission_status'] ?? 0;
                                            $commissionAmountType = $data['commission_amount_type'] ?? 'percent';
                                            $commissionStructureType = $data['commission_structure_type'] ?? 'Fixed';
                                            break 2;
                                        }
                                    }
                                }
                            }

                        } elseif ($existingCommissionConfig) {
                            // Use existing database configuration
                            $commissionParentage = $existingCommissionConfig->commission_parentage;
                            $commissionStatus = $existingCommissionConfig->commission_status;
                            $commissionAmountType = $existingCommissionConfig->commission_amount_type;
                            $commissionStructureType = $existingCommissionConfig->commission_structure_type;

                        } else {
                            // Use defaults if no configuration found

                        }

                        // 🔧 SIMPLE: Take values directly from request if available
                        $upfrontAmount = 0;
                        $upfrontStatus = 0;
                        $calculatedBy = 'per sale';
                        $upfrontSystem = 'Fixed';

                        if ($requestData && $this->hasUpfrontInRequest($requestData, $productId, $corePositionId)) {
                            // Get values directly from request
                            foreach ($requestData->upfront as $upfront) {
                                if ($upfront['product_id'] == $productId) {
                                    foreach ($upfront['data'] as $data) {
                                        if ($data['core_position_id'] == $corePositionId) {
                                            foreach ($data['schemas'] as $schema) {
                                                $upfrontAmount = $schema['upfront_ammount'] ?? 0;
                                                $upfrontStatus = $upfront['upfront_status'] ?? 0;
                                                $calculatedBy = $schema['calculated_by'] ?? 'per sale';
                                                $upfrontSystem = 'Fixed';
                                                break 3;
                                            }
                                        }
                                    }
                                }
                            }

                        } elseif ($existingUpfrontConfig) {
                            // Use existing database configuration
                            $upfrontAmount = $existingUpfrontConfig->upfront_ammount;
                            $upfrontStatus = $existingUpfrontConfig->upfront_status;
                            $calculatedBy = $existingUpfrontConfig->calculated_by;
                            $upfrontSystem = $existingUpfrontConfig->upfront_system;
                            // Performance: Removed verbose info logging
                        } else {
                            // Use defaults if no configuration found
                            // Performance: Removed verbose info logging
                        }

                        $newCommission = PositionCommission::create([
                            'position_id' => $positionId,
                            'core_position_id' => $corePositionId,
                            'product_id' => $productId,
                            'self_gen_user' => $selfGenUser,
                            'commission_parentage' => $commissionParentage,
                            'commission_parentag_hiring_locked' => $existingCommissionConfig ? $existingCommissionConfig->commission_parentag_hiring_locked : 0,
                            'commission_amount_type' => $commissionAmountType,
                            'commission_amount_type_locked' => $existingCommissionConfig ? $existingCommissionConfig->commission_amount_type_locked : 0,
                            'commission_structure_type' => $commissionStructureType,
                            'commission_parentag_type_hiring_locked' => $existingCommissionConfig ? $existingCommissionConfig->commission_parentag_type_hiring_locked : 0,
                            'commission_status' => $commissionStatus,
                            'tiers_id' => $existingCommissionConfig ? $existingCommissionConfig->tiers_id : 0,
                            'tiers_hiring_locked' => $existingCommissionConfig ? $existingCommissionConfig->tiers_hiring_locked : 0,
                            'effective_date' => $backdatedEffectiveDate,
                        ]);

                        $totalPositionCommissionsCreated++;

                    }

                    // Create PositionCommissionUpfronts record with copied values
                    $existingUpfront = PositionCommissionUpfronts::where([
                        'position_id' => $positionId,
                        'product_id' => $productId,
                        'core_position_id' => $corePositionId,
                        'effective_date' => $backdatedEffectiveDate,
                    ])->first();

                    if (! $existingUpfront) {
                        // 🔧 FIXED: Use actual upfront values instead of hardcoded nulls
                        $upfrontAmount = $existingUpfrontConfig ? $existingUpfrontConfig->upfront_ammount : null;
                        $upfrontStatus = $existingUpfrontConfig ? $existingUpfrontConfig->upfront_status : 0;
                        $calculatedBy = $existingUpfrontConfig ? $existingUpfrontConfig->calculated_by : 'per sale';
                        $upfrontSystem = $existingUpfrontConfig ? $existingUpfrontConfig->upfront_system : 'Fixed';

                        $newUpfront = PositionCommissionUpfronts::create([
                            'position_id' => $positionId,
                            'core_position_id' => $corePositionId,
                            'product_id' => $productId,
                            'milestone_schema_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_id : null,
                            'milestone_schema_trigger_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_trigger_id : null,
                            'self_gen_user' => $selfGenUser,
                            'status_id' => $existingUpfrontConfig ? $existingUpfrontConfig->status_id : 1,
                            'upfront_ammount' => $upfrontAmount,
                            'upfront_ammount_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_ammount_locked : 0,
                            'calculated_by' => $calculatedBy,
                            'calculated_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->calculated_locked : 0,
                            'upfront_status' => $upfrontStatus,
                            'upfront_system' => $upfrontSystem,
                            'upfront_system_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_system_locked : 0,
                            'upfront_limit' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_limit : null,
                            'upfront_limit_type' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_limit_type : null,
                            'tiers_id' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_id : null,
                            'tiers_hiring_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_hiring_locked : 0,
                            'tiers_advancement' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_advancement : null,
                            'effective_date' => $backdatedEffectiveDate,
                            'deductible_from_prior' => $existingUpfrontConfig ? $existingUpfrontConfig->deductible_from_prior : '0',
                        ]);

                        $totalPositionUpfrontsCreated++;

                    }
                }
            }

            // Step 1.5: Create PositionOverride records for backdated products
            $totalPositionOverridesCreated = 0;

            foreach ($productIds as $productId) {
                // Get existing override configurations (with NULL effective dates)
                $existingOverrides = PositionOverride::where([
                    'position_id' => $positionId,
                    'product_id' => $productId,
                ])->whereNull('effective_date')->get();

                foreach ($existingOverrides as $existingOverride) {
                    // Check if override already exists for this backdated effective date
                    $existingOverrideOnDate = PositionOverride::where([
                        'position_id' => $positionId,
                        'product_id' => $productId,
                        'override_id' => $existingOverride->override_id,
                        'effective_date' => $backdatedEffectiveDate,
                    ])->first();

                    if (! $existingOverrideOnDate) {
                        // Create new PositionOverride record with backdated effective date
                        $newPositionOverride = PositionOverride::create([
                            'position_id' => $positionId,
                            'product_id' => $productId,
                            'override_id' => $existingOverride->override_id,
                            'settlement_id' => $existingOverride->settlement_id,
                            'override_ammount' => $existingOverride->override_ammount,
                            'override_ammount_locked' => $existingOverride->override_ammount_locked,
                            'type' => $existingOverride->type,
                            'override_type_locked' => $existingOverride->override_type_locked,
                            'status' => $existingOverride->status,
                            'tiers_id' => $existingOverride->tiers_id,
                            'tiers_hiring_locked' => $existingOverride->tiers_hiring_locked,
                            'override_limit' => $existingOverride->override_limit,
                            'override_limit_type' => $existingOverride->override_limit_type,
                            'effective_date' => $backdatedEffectiveDate,
                        ]);

                        $totalPositionOverridesCreated++;
                        // Performance: Removed debug logging
                    } else {
                        // Performance: Removed debug logging
                    }
                }
            }

            // Step 2: Create User-level records (UserCommissionHistory & UserUpfrontHistory)
            $users = User::where('sub_position_id', $positionId)->get();

            foreach ($users as $user) {
                $userId = $user->id;

                foreach ($productIds as $productId) {
                    foreach ($corePositions as $corePos) {
                        $corePositionId = $corePos['core_position_id'];
                        $selfGenUser = $corePos['self_gen_user'];

                        // 🔧 FIXED: Get existing commission configuration to copy values for user history (latest configuration)
                        $existingCommissionConfig = PositionCommission::where([
                            'position_id' => $positionId,
                            'product_id' => $productId,
                            'core_position_id' => $corePositionId,
                        ])->where(function ($query) use ($backdatedEffectiveDate) {
                            $query->whereNull('effective_date')
                                ->orWhere('effective_date', '<=', $backdatedEffectiveDate);
                        })->orderBy('effective_date', 'DESC')->first(); // Get latest configuration up to backdated date

                        // Create UserCommissionHistory record with copied values
                        // CRITICAL FIX: Use correct effective date for existence check
                        $correctCommissionEffectiveDate = $this->getCorrectEffectiveDate($userId, $backdatedEffectiveDate);

                        $existingUserCommission = UserCommissionHistory::where([
                            'user_id' => $userId,
                            'product_id' => $productId,
                            'core_position_id' => $corePositionId,
                            'commission_effective_date' => $correctCommissionEffectiveDate,
                        ])->first();

                        if (! $existingUserCommission) {
                            // 🔧 SIMPLE: Take values directly from request if available
                            $commissionValue = 0;
                            $commissionType = 'percent';
                            $actionItemStatus = 1;

                            if ($requestData && $this->hasCommissionInRequest($requestData, $productId, $corePositionId)) {
                                // Get values directly from request
                                foreach ($requestData->commission as $commission) {
                                    if ($commission['product_id'] == $productId) {
                                        foreach ($commission['data'] as $data) {
                                            if ($data['core_position_id'] == $corePositionId) {
                                                $commissionValue = $data['commission_parentage'] ?? 0;
                                                $commissionType = $data['commission_amount_type'] ?? 'percent';
                                                $actionItemStatus = $commission['commission_status'] ?? 1;
                                                break 2;
                                            }
                                        }
                                    }
                                }
                                // Performance: Removed verbose info logging
                            } elseif ($existingCommissionConfig) {
                                // Use existing database configuration
                                $commissionValue = $existingCommissionConfig->commission_parentage;
                                $commissionType = $existingCommissionConfig->commission_amount_type ?? 'percent';
                                $actionItemStatus = $existingCommissionConfig->commission_status ?? 1;
                                // Performance: Removed verbose info logging
                            } else {
                                // Use defaults if no configuration found
                                $commissionValue = 0;
                                $commissionType = 'percent';
                                $actionItemStatus = 0;
                                // Performance: Removed verbose info logging
                            }
                            // Performance: Removed verbose info logging
                            // Note: $correctCommissionEffectiveDate already calculated above

                            $newUserCommission = UserCommissionHistory::create([
                                'user_id' => $userId,
                                'updater_id' => auth()->user()?->id ?? 1,
                                'product_id' => $productId,
                                'old_product_id' => null,
                                'self_gen_user' => $selfGenUser,
                                'old_self_gen_user' => null,
                                'commission' => $commissionValue,
                                'commission_type' => $commissionType,
                                'old_commission' => null,
                                'old_commission_type' => null,
                                'commission_effective_date' => $correctCommissionEffectiveDate, // Use corrected effective date
                                'effective_end_date' => null,
                                'position_id' => $parentPosition,
                                'core_position_id' => $corePositionId,
                                'sub_position_id' => $positionId,
                                'action_item_status' => $actionItemStatus,
                                'tiers_id' => $existingCommissionConfig ? $existingCommissionConfig->tiers_id : null,
                                'old_tiers_id' => null,
                            ]);

                            $totalUserCommissionsCreated++;
                            // Performance: Removed verbose info logging
                        }

                        // 🔧 FIXED: Get latest upfront configurations up to backdated date
                        $existingUpfrontConfigs = PositionCommissionUpfronts::where([
                            'position_id' => $positionId,
                            'product_id' => $productId,
                            'core_position_id' => $corePositionId,
                        ])->where(function ($query) use ($backdatedEffectiveDate) {
                            $query->whereNull('effective_date')
                                ->orWhere('effective_date', '<=', $backdatedEffectiveDate);
                        })->orderBy('effective_date', 'DESC')->get();

                        // If no configurations found, try to get any available configuration
                        if ($existingUpfrontConfigs->isEmpty()) {
                            $existingUpfrontConfigs = PositionCommissionUpfronts::where([
                                'position_id' => $positionId,
                                'product_id' => $productId,
                                'core_position_id' => $corePositionId,
                            ])->get();
                        }

                        // Create UserUpfrontHistory records - one for each milestone schema trigger
                        // CRITICAL FIX: Calculate correct effective date before existence check
                        $correctUpfrontEffectiveDate = $this->getCorrectEffectiveDate($userId, $backdatedEffectiveDate);

                        foreach ($existingUpfrontConfigs as $existingUpfrontConfig) {
                            $existingUserUpfront = UserUpfrontHistory::where([
                                'user_id' => $userId,
                                'product_id' => $productId,
                                'core_position_id' => $corePositionId,
                                'milestone_schema_trigger_id' => $existingUpfrontConfig->milestone_schema_trigger_id,
                                'upfront_effective_date' => $correctUpfrontEffectiveDate,
                            ])->first();

                            if (! $existingUserUpfront) {
                                // 🔧 SIMPLE: Take values directly from request if available
                                $upfrontPayAmount = 0;
                                $upfrontSaleType = 'per sale';
                                $actionItemStatus = 1;

                                if ($requestData && $this->hasUpfrontInRequest($requestData, $productId, $corePositionId)) {
                                    // Get values directly from request for this specific milestone schema trigger
                                    $customSalesFieldId = null;
                                    foreach ($requestData->upfront as $upfront) {
                                        if ($upfront['product_id'] == $productId) {
                                            foreach ($upfront['data'] as $data) {
                                                if ($data['core_position_id'] == $corePositionId) {
                                                    foreach ($data['schemas'] as $schema) {
                                                        if (isset($schema['milestone_schema_trigger_id']) && $schema['milestone_schema_trigger_id'] == $existingUpfrontConfig->milestone_schema_trigger_id) {
                                                            $upfrontPayAmount = $schema['upfront_ammount'] ?? 0;
                                                            $upfrontSaleType = $schema['calculated_by'] ?? 'per sale';
                                                            $customSalesFieldId = $schema['custom_sales_field_id'] ?? null;
                                                            $actionItemStatus = $upfront['upfront_status'] ?? 1;
                                                            break 3;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    // Performance: Removed verbose info logging
                                } elseif ($existingUpfrontConfig) {
                                    // Use existing database configuration
                                    $upfrontPayAmount = $existingUpfrontConfig->upfront_ammount ?? 0;
                                    $upfrontSaleType = $existingUpfrontConfig->calculated_by ?? 'per sale';
                                    $customSalesFieldId = $existingUpfrontConfig->custom_sales_field_id ?? null;
                                    $actionItemStatus = $existingUpfrontConfig->upfront_status ?? 1;
                                    // Performance: Removed verbose info logging
                                } else {
                                    // Use defaults if no configuration found
                                    $upfrontPayAmount = 0;
                                    $upfrontSaleType = 'per sale';
                                    $customSalesFieldId = null;
                                    $actionItemStatus = 0;
                                    // Performance: Removed verbose info logging
                                }
                                // Performance: Removed verbose info logging
                                // Note: $correctUpfrontEffectiveDate already calculated above

                                $newUserUpfront = UserUpfrontHistory::create([
                                    'user_id' => $userId,
                                    'updater_id' => auth()->user()?->id ?? 1,
                                    'product_id' => $productId,
                                    'old_product_id' => null,
                                    'milestone_schema_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_id : null,
                                    'old_milestone_schema_id' => null,
                                    'milestone_schema_trigger_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_trigger_id : null,
                                    'old_milestone_schema_trigger_id' => null,
                                    'self_gen_user' => $selfGenUser,
                                    'old_self_gen_user' => null,
                                    'upfront_pay_amount' => $upfrontPayAmount,
                                    'old_upfront_pay_amount' => null,
                                    'upfront_sale_type' => $upfrontSaleType,
                                    'old_upfront_sale_type' => null,
                                    'upfront_effective_date' => $correctUpfrontEffectiveDate, // Use corrected effective date
                                    'effective_end_date' => null,
                                    'position_id' => $parentPosition,
                                    'core_position_id' => $corePositionId,
                                    'sub_position_id' => $positionId,
                                    'action_item_status' => $actionItemStatus,
                                    'tiers_id' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_id : null,
                                    'old_tiers_id' => null,
                                    'custom_sales_field_id' => $customSalesFieldId,
                                ]);

                                $totalUserUpfrontsCreated++;
                                // Performance: Removed verbose info logging
                            }
                        }

                        // Create UserOverrideHistory record with copied values
                        // CRITICAL FIX: Use correct effective date based on hire date
                        $correctOverrideEffectiveDate = $this->getCorrectEffectiveDate($userId, $backdatedEffectiveDate);

                        $existingUserOverride = UserOverrideHistory::where([
                            'user_id' => $userId,
                            'product_id' => $productId,
                            'override_effective_date' => $correctOverrideEffectiveDate,
                        ])->first();

                        if (! $existingUserOverride) {
                            // 🔧 Initialize all override types with default values
                            $directOverrideAmount = 0;
                            $directOverrideType = 'per sale';
                            $indirectOverrideAmount = 0;
                            $indirectOverrideType = 'per sale';
                            $officeOverrideAmount = 0;
                            $officeOverrideType = 'per sale';
                            $officeStackOverrideAmount = 0;
                            $actionItemStatus = 0;
                            // Custom Sales Field IDs
                            $directCustomSalesFieldId = null;
                            $indirectCustomSalesFieldId = null;
                            $officeCustomSalesFieldId = null;

                            // 🔧 Extract override values from request if available
                            if ($requestData && isset($requestData->overrides)) {
                                foreach ($requestData->overrides as $override) {
                                    if ($override['product_id'] == $productId) {
                                        foreach ($override['override'] as $overrideData) {
                                            if ($overrideData['status'] == 1) {
                                                $overrideId = $overrideData['override_id'] ?? null;
                                                $amount = $overrideData['override_ammount'] ?? 0;
                                                $type = $overrideData['type'] ?? 'per sale';

                                                // Map override_id to specific override types (same as normal flow)
                                                if ($overrideId == '1') { // Direct
                                                    $directOverrideAmount = $amount;
                                                    $directOverrideType = $type;
                                                    $directCustomSalesFieldId = $overrideData['direct_custom_sales_field_id'] ?? null;
                                                } elseif ($overrideId == '2') { // Indirect
                                                    $indirectOverrideAmount = $amount;
                                                    $indirectOverrideType = $type;
                                                    $indirectCustomSalesFieldId = $overrideData['indirect_custom_sales_field_id'] ?? null;
                                                } elseif ($overrideId == '3') { // Office
                                                    $officeOverrideAmount = $amount;
                                                    $officeOverrideType = $type;
                                                    $officeCustomSalesFieldId = $overrideData['office_custom_sales_field_id'] ?? null;
                                                } elseif ($overrideId == '4') { // Office Stack
                                                    $officeStackOverrideAmount = $amount;
                                                }
                                                $actionItemStatus = $override['status'] ?? 0;
                                            }
                                        }
                                        break;
                                    }
                                }
                                // Performance: Removed verbose info logging
                            } else {
                                // Use defaults if no configuration found
                                // Performance: Removed verbose info logging
                            }
                            // Performance: Removed verbose info logging
                            $newUserOverride = UserOverrideHistory::create([
                                'user_id' => $userId,
                                'updater_id' => auth()->user()?->id ?? 1,
                                'product_id' => $productId,
                                'old_product_id' => null,
                                // 🔧 REMOVED: self_gen_user, old_self_gen_user (columns don't exist)
                                // 🔧 FIX: Include all override types (not just direct)
                                'direct_overrides_amount' => $directOverrideAmount,
                                'old_direct_overrides_amount' => null,
                                'direct_overrides_type' => $directOverrideType,
                                'old_direct_overrides_type' => null,
                                'indirect_overrides_amount' => $indirectOverrideAmount,
                                'old_indirect_overrides_amount' => null,
                                'indirect_overrides_type' => $indirectOverrideType,
                                'old_indirect_overrides_type' => null,
                                'office_overrides_amount' => $officeOverrideAmount,
                                'old_office_overrides_amount' => null,
                                'office_overrides_type' => $officeOverrideType,
                                'old_office_overrides_type' => null,
                                'office_stack_overrides_amount' => $officeStackOverrideAmount,
                                'override_effective_date' => $correctOverrideEffectiveDate, // Use corrected effective date
                                'effective_end_date' => null,
                                'action_item_status' => $actionItemStatus,
                                'direct_tiers_id' => null,
                                'old_direct_tiers_id' => null,
                                'indirect_tiers_id' => null,
                                'old_indirect_tiers_id' => null,
                                'office_tiers_id' => null,
                                'old_office_tiers_id' => null,
                                // Custom Sales Field IDs
                                'direct_custom_sales_field_id' => $directCustomSalesFieldId,
                                'indirect_custom_sales_field_id' => $indirectCustomSalesFieldId,
                                'office_custom_sales_field_id' => $officeCustomSalesFieldId,
                            ]);

                            $totalUserOverridesCreated++;
                            // Performance: Removed verbose info logging
                        }

                        // CRITICAL FIX: Apply hire date adjustment for redline records
                        $correctRedlineEffectiveDate = $this->getCorrectEffectiveDate($userId, $backdatedEffectiveDate);

                        $existingUserRedline = DB::table('user_redline_histories')->where([
                            'user_id' => $userId,
                            'core_position_id' => $corePositionId,
                            'start_date' => $correctRedlineEffectiveDate,  // Use corrected date
                        ])->whereNull('deleted_at')->whereNull('effective_end_date')->first();

                        if (! $existingUserRedline) {
                            $latestRedline = DB::table('user_redline_histories')
                                ->where('user_id', $userId)
                                ->where('core_position_id', $corePositionId)
                                ->whereNull('deleted_at')
                                ->orderBy('start_date', 'DESC')
                                ->first();

                            if ($latestRedline) {
                                if (Carbon::parse($correctRedlineEffectiveDate)->lt(Carbon::parse($latestRedline->start_date))) {

                                    $conflictingRedlines = DB::table('user_redline_histories')
                                        ->where('user_id', $userId)
                                        ->where('core_position_id', $corePositionId)
                                        ->where('start_date', '>=', $correctRedlineEffectiveDate)
                                        ->where('start_date', '<', date('Y-m-d'))
                                        ->whereNull('deleted_at')
                                        ->get();

                                    foreach ($conflictingRedlines as $conflictingRedline) {

                                        DB::table('user_redline_histories')
                                            ->where('id', $conflictingRedline->id)
                                            ->update(['deleted_at' => now()]);
                                    }

                                    $newRedlineId = DB::table('user_redline_histories')->insertGetId([
                                        'user_id' => $userId,
                                        'updater_id' => auth()->user()?->id ?? 1,
                                        'product_id' => $latestRedline->product_id,
                                        'redline' => $latestRedline->redline,
                                        'redline_type' => $latestRedline->redline_type,
                                        'redline_amount_type' => $latestRedline->redline_amount_type,
                                        'self_gen_user' => $latestRedline->self_gen_user,
                                        'state_id' => $latestRedline->state_id,
                                        'start_date' => $correctRedlineEffectiveDate,  // Use corrected date
                                        'position_type' => $latestRedline->position_type,
                                        'core_position_id' => $corePositionId,
                                        'sub_position_type' => $latestRedline->sub_position_type,
                                        'old_product_id' => $latestRedline->old_product_id,
                                        'old_redline_amount_type' => $latestRedline->old_redline_amount_type,
                                        'old_self_gen_user' => $latestRedline->old_self_gen_user,
                                        'old_redline' => $latestRedline->old_redline,
                                        'old_redline_type' => $latestRedline->old_redline_type,
                                        'withheld_amount' => $latestRedline->withheld_amount,
                                        'withheld_type' => $latestRedline->withheld_type,
                                        'withheld_effective_date' => $latestRedline->withheld_effective_date,
                                        'action_item_status' => $latestRedline->action_item_status,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);

                                    $totalUserRedlinesCreated++;
                                    // Performance: Removed verbose info logging
                                } else {

                                }
                            } else {

                                $newRedlineId = DB::table('user_redline_histories')->insertGetId([
                                    'user_id' => $userId,
                                    'updater_id' => auth()->user()?->id ?? 1,
                                    'product_id' => null,
                                    'redline' => 0,
                                    'redline_type' => 'per watt',
                                    'redline_amount_type' => 'Fixed',
                                    'self_gen_user' => $corePositionId === null ? 1 : 0,
                                    'state_id' => null,
                                    'start_date' => $correctRedlineEffectiveDate,  // Use corrected date
                                    'effective_end_date' => null,
                                    'position_type' => $positionId,
                                    'core_position_id' => $corePositionId,
                                    'sub_position_type' => $positionId,
                                    'old_product_id' => null,
                                    'old_redline_amount_type' => null,
                                    'old_self_gen_user' => null,
                                    'old_redline' => null,
                                    'old_redline_type' => null,
                                    'withheld_amount' => null,
                                    'withheld_type' => null,
                                    'withheld_effective_date' => null,
                                    'action_item_status' => 1,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                $totalUserRedlinesCreated++;
                                // Performance: Removed verbose info logging
                            }
                        } else {
                            // Performance: Removed verbose info logging
                        }
                    }
                }
            }

            // Summary logging for backdated commission and upfront setup
            // Performance: Removed verbose info logging
        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN BACKDATED COMMISSION & UPFRONT SETUP', [
                'position_id' => $positionId,
                'product_ids' => $productIds,
                'backdated_effective_date' => $backdatedEffectiveDate,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if a product has commission configuration in the request data
     */
    private function hasCommissionInRequest($requestData, $productId, $corePositionId)
    {
        if (! $requestData || ! isset($requestData->commission)) {
            return false;
        }

        foreach ($requestData->commission as $commission) {
            if ($commission['product_id'] == $productId) {
                foreach ($commission['data'] as $data) {
                    if ($data['core_position_id'] == $corePositionId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a product has upfront configuration in the request data
     */
    private function hasUpfrontInRequest($requestData, $productId, $corePositionId)
    {
        if (! $requestData || ! isset($requestData->upfront)) {
            return false;
        }

        foreach ($requestData->upfront as $upfront) {
            if ($upfront['product_id'] == $productId) {
                foreach ($upfront['data'] as $data) {
                    if ($data['core_position_id'] == $corePositionId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a product has override configuration in the request data
     */
    private function hasOverrideInRequest($requestData, $productId, $corePositionId)
    {
        if (! $requestData || ! isset($requestData->overrides)) {
            return false;
        }

        foreach ($requestData->overrides as $override) {
            if ($override['product_id'] == $productId) {
                // Overrides are typically applied to all core positions for a product
                return true;
            }
        }

        return false;
    }

    /**
     * Get user's actual hire date from user_agreement_histories table
     * Returns the period_of_agreement date from the latest non-soft-deleted record
     */
    private function getUserHireDate($userId)
    {
        try {
            $latestAgreement = UserAgreementHistory::where('user_id', $userId)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestAgreement && $latestAgreement->period_of_agreement) {
                // Performance: Removed verbose info logging
                return $latestAgreement->period_of_agreement;
            }

            // Fallback to user.hire_date if no agreement history found
            $user = User::find($userId);
            if ($user && $user->hire_date) {
                // Performance: Removed verbose info logging
                return $user->hire_date;
            }

            $this->logConditional('warning', '⚠️ NO HIRE DATE FOUND FOR USER', [
                'user_id' => $userId,
                'checked_sources' => ['user_agreement_histories', 'users.hire_date'],
            ]);

            return null;

        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR GETTING USER HIRE DATE', [
                'user_id' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Determine the correct effective date for user histories
     * If backdate is before hire date, use hire date instead
     */
    private function getCorrectEffectiveDate($userId, $requestedEffectiveDate)
    {
        try {
            $hireDate = $this->getUserHireDate($userId);

            if (! $hireDate || ! $requestedEffectiveDate) {
                // Performance: Removed verbose info logging
                return $requestedEffectiveDate;
            }

            $hireDateCarbon = Carbon::parse($hireDate);
            $requestedDateCarbon = Carbon::parse($requestedEffectiveDate);

            if ($requestedDateCarbon->lt($hireDateCarbon)) {
                // Performance: Removed verbose info logging
                return $hireDate;
            }

            // Performance: Removed verbose info logging
            return $requestedEffectiveDate;

        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR DETERMINING CORRECT EFFECTIVE DATE', [
                'user_id' => $userId,
                'requested_effective_date' => $requestedEffectiveDate,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            return $requestedEffectiveDate; // Fallback to requested date
        }
    }

    /**
     * Check if the effective date is backdated (earlier than existing effective dates)
     * FIXED: Now properly checks position_products table, user hire dates, and handles NULL effective dates
     */
    private function isBackdatedEffectiveDate($positionId, $productIds, $effectiveDate)
    {
        if (empty($productIds)) {
            return false;
        }

        // CRITICAL: Handle NULL effective dates (from position creation)
        if ($effectiveDate === null || $effectiveDate === '') {
            // Performance: Removed verbose info logging
            return false;
        }

        $effectiveDateCarbon = Carbon::parse($effectiveDate);
        // Performance: Removed verbose info logging
        // PRIMARY CHECK: Compare against existing UserOrganizationHistory records
        // This is the most reliable since hiring creates proper histories with hire dates
        $existingUserHistoryDates = UserOrganizationHistory::where('sub_position_id', $positionId)
            ->whereNotNull('effective_date')
            ->where('effective_date', '>', $effectiveDate)
            ->pluck('effective_date')
            ->unique()
            ->toArray();

        if (! empty($existingUserHistoryDates)) {
            // Performance: Removed verbose info logging
            return true;
        }

        // SECONDARY CHECK: Compare against existing position_products
        // This handles cases where products exist but users haven't been hired yet
        $existingProductDates = PositionProduct::where('position_id', $positionId)
            ->whereNotNull('effective_date')
            ->where('effective_date', '>', $effectiveDate)
            ->pluck('effective_date')
            ->unique()
            ->toArray();

        if (! empty($existingProductDates)) {
            // Performance: Removed verbose info logging
            return true;
        }

        // TERTIARY CHECK: Compare against user hire dates
        // This is essential since hiring creates user_organization_history with hire dates
        $users = User::where('sub_position_id', $positionId)->get();
        foreach ($users as $user) {
            if ($user->hire_date && Carbon::parse($user->hire_date)->gt($effectiveDateCarbon)) {
                // Performance: Removed verbose info logging
                return true;
            }
        }

        // Original checks for commission/upfront/redline histories
        foreach ($productIds as $productId) {
            $latestCommission = DB::table('position_commissions')
                ->where('position_id', $positionId)
                ->where('product_id', $productId)
                ->whereNotNull('effective_date')
                ->orderBy('effective_date', 'desc')
                ->first();

            if ($latestCommission && Carbon::parse($latestCommission->effective_date)->gt($effectiveDateCarbon)) {
                // Performance: Removed verbose info logging
                return true;
            }

            $latestUpfront = DB::table('position_commission_upfronts')
                ->where('position_id', $positionId)
                ->where('product_id', $productId)
                ->whereNotNull('effective_date')
                ->orderBy('effective_date', 'desc')
                ->first();

            if ($latestUpfront && Carbon::parse($latestUpfront->effective_date)->gt($effectiveDateCarbon)) {
                // Performance: Removed verbose info logging
                return true;
            }

            $latestRedline = DB::table('user_redline_histories')
                ->where('position_type', $positionId)
                ->whereNotNull('start_date')
                ->orderBy('start_date', 'desc')
                ->first();

            if ($latestRedline && Carbon::parse($latestRedline->start_date)->gt($effectiveDateCarbon)) {
                // Performance: Removed verbose info logging
                return true;
            }
        }

        // Performance: Removed verbose info logging
        return false;
    }

    /**
     * Check if forward propagation is required for existing products
     * This happens when new products are added with effective dates later than existing products
     *
     * @param  int  $positionId  The position ID to check
     * @param  array  $newProductIds  Array of new product IDs being added
     * @param  string  $effectiveDate  The effective date for the new products (Y-m-d format)
     * @return bool True if forward propagation is required, false otherwise
     */
    private function isForwardPropagationRequired(int $positionId, array $newProductIds, string $effectiveDate): bool
    {
        // Validate input parameters
        if (empty($newProductIds) || ! $positionId || ! $effectiveDate) {
            return false;
        }

        $effectiveDateCarbon = Carbon::parse($effectiveDate);

        // Check if there are existing products with earlier effective dates OR NULL effective dates
        $existingProducts = PositionProduct::where('position_id', $positionId)
            ->whereNotIn('product_id', $newProductIds)  // Exclude the new products being added
            ->where(function ($query) use ($effectiveDate) {
                $query->whereNull('effective_date')  // Include original assignments with NULL dates
                    ->orWhere('effective_date', '<', $effectiveDate);  // Include earlier dated assignments
            })
            ->exists();

        if (! $existingProducts) {
            return false;
        }

        // Check if this effective date is new (later than existing dates OR first effective date)
        $latestExistingDate = PositionProduct::where('position_id', $positionId)
            ->whereNotIn('product_id', $newProductIds)  // Exclude new products from comparison
            ->whereNotNull('effective_date')
            ->orderBy('effective_date', 'desc')
            ->value('effective_date');

        // Rule 1: If no existing effective dates (all NULL), propagate to existing products
        if (! $latestExistingDate) {
            // Performance: Removed verbose info logging
            return true;
        }

        // Rule 2: If new effective date is later than existing dates, propagate
        if ($latestExistingDate && Carbon::parse($latestExistingDate)->lt($effectiveDateCarbon)) {
            // Performance: Removed verbose info logging
            return true;
        }

        // Rule 3: If new effective date is between existing dates (middle date), propagate existing products to new date
        $earlierExistingDate = PositionProduct::where('position_id', $positionId)
            ->whereNotIn('product_id', $newProductIds)
            ->whereNotNull('effective_date')
            ->where('effective_date', '<', $effectiveDate)
            ->orderBy('effective_date', 'desc')
            ->value('effective_date');

        if ($earlierExistingDate && $latestExistingDate && Carbon::parse($earlierExistingDate)->lt($effectiveDateCarbon) && Carbon::parse($latestExistingDate)->gt($effectiveDateCarbon)) {
            // Performance: Removed verbose info logging
            return true;
        }

        return false;
    }

    /**
     * Propagate existing products to new effective date
     * When new products are added with a future effective date,
     * all existing products should also get records for that new date
     *
     * @param  int  $positionId  The position ID
     * @param  array  $newProductIds  Array of new product IDs (to exclude from propagation)
     * @param  string  $newEffectiveDate  The new effective date (Y-m-d format)
     */
    private function propagateExistingProductsToNewDate(int $positionId, array $newProductIds, string $newEffectiveDate, array $removedProductIds = [])
    {
        
        // Use nested transaction for additional safety
        return DB::transaction(function () use ($positionId, $newProductIds, $newEffectiveDate, $removedProductIds) {
            try {
                // Performance: Removed verbose info logging
                // 🔧 CRITICAL FIX: Get products from the MOST RECENT effective date before $newEffectiveDate
                // Don't get from ALL old dates, as that includes products removed in previous updates
                
                // 🔧 CRITICAL FIX: Get products from the NEW effective date that was just created by the controller
                // This ensures we propagate the CURRENT state, not old state with products removed in previous updates
                $existingProductIds = PositionProduct::where('position_id', $positionId)
                    ->where('effective_date', $newEffectiveDate)
                    ->whereNull('deleted_at')
                    ->pluck('product_id')
                    ->unique()
                    ->toArray();
                
                // Exclude new products AND removed products from propagation
                $excludeFromPropagation = array_merge($newProductIds, array_values($removedProductIds));
                
                
                $existingProductIds = array_values(array_diff($existingProductIds, $excludeFromPropagation));


                if (empty($existingProductIds)) {
                    // Performance: Removed verbose info logging
                    return;
                }

                $totalRecordsCreated = 0;
                $totalRecordsSkipped = 0;

                // Create PositionProduct records for existing products on the new effective date
                foreach ($existingProductIds as $existingProductId) {
                    $existingProduct = PositionProduct::where([
                        'position_id' => $positionId,
                        'product_id' => $existingProductId,
                        'effective_date' => $newEffectiveDate,
                    ])->first();

                    if (! $existingProduct) {
                        $newProduct = PositionProduct::create([
                            'position_id' => $positionId,
                            'product_id' => $existingProductId,
                            'effective_date' => $newEffectiveDate,
                        ]);

                        $totalRecordsCreated++;
                        // Performance: Removed debug logging
                    } else {
                        $totalRecordsSkipped++;
                        // Performance: Removed debug logging
                    }
                }

                // 🔧 CRITICAL FIX: Exclude BOTH new products AND removed products from validation
                // Validate propagation completeness and auto-fix any missing products
                $excludeProducts = array_merge($newProductIds, $removedProductIds);
                $this->validatePropagationCompleteness($positionId, $newEffectiveDate, $excludeProducts);
                // Performance: Removed verbose info logging
            } catch (Exception $e) {
                $this->logConditional('error', '💥 ERROR IN FORWARD PROPAGATION', [
                    'position_id' => $positionId,
                    'new_effective_date' => $newEffectiveDate,
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'error_class' => get_class($e),
                    'timestamp' => now()->toDateTimeString(),
                ]);
                throw $e;
            }
        }); // End of DB::transaction
    }

    /**
     * Create UserOrganizationHistory records for existing products on new effective date
     * This ensures users have proper product assignments for the new date
     */
    private function createForwardPropagationUserOrganizationHistories($positionId, $newProductIds, $newEffectiveDate)
    {
        try {
            // Performance: Removed verbose info logging
            // Get ALL current users in the position
            $users = User::where('sub_position_id', $positionId)->get();

            if (empty($users)) {
                $this->logConditional('warning', '❌ NO USERS FOUND FOR POSITION', [
                    'position_id' => $positionId,
                    'new_effective_date' => $newEffectiveDate,
                    'action' => 'forward propagation UserOrganizationHistory creation',
                ]);

                return;
            }

            $position = Positions::find($positionId);

            if (! $position) {
                $this->logConditional('error', '❌ POSITION NOT FOUND', [
                    'position_id' => $positionId,
                    'action' => 'forward propagation UserOrganizationHistory creation',
                ]);

                return;
            }

            $parentPosition = $position->parent_id ? $position->parent_id : (($position->id == '2' || $position->id == '3') ? $position->id : 0);

            // Get all existing products (excluding the new ones being added) - FIXED VERSION
            $existingProductIds = PositionProduct::where('position_id', $positionId)
                ->whereNotIn('product_id', $newProductIds)
                ->where(function ($query) use ($newEffectiveDate) {
                    $query->whereNull('effective_date')  // Include original assignments
                        ->orWhere('effective_date', '<', $newEffectiveDate);  // Include earlier dated assignments
                })
                ->whereNull('deleted_at')  // Exclude soft-deleted products
                ->pluck('product_id')
                ->unique()
                ->toArray();
            // Performance: Removed verbose info logging
            if (empty($existingProductIds)) {
                // Performance: Removed verbose info logging
                return;
            }

            $totalRecordsCreated = 0;
            $totalRecordsSkipped = 0;

            foreach ($users as $user) {
                $userId = $user->id;
                $userRecordsCreated = 0;
                $userRecordsSkipped = 0;
                // Performance: Removed verbose info logging
                // Create UserOrganizationHistory records for existing products on new effective date
                foreach ($existingProductIds as $existingProductId) {
                    $existingRecord = UserOrganizationHistory::where([
                        'user_id' => $userId,
                        'sub_position_id' => $positionId,
                        'product_id' => $existingProductId,
                        'effective_date' => $newEffectiveDate,
                    ])->first();

                    if (! $existingRecord) {
                        $newRecord = UserOrganizationHistory::create([
                            'user_id' => $userId,
                            'sub_position_id' => $positionId,
                            'product_id' => $existingProductId,
                            'effective_date' => $newEffectiveDate,
                            'self_gen_accounts' => ($position?->is_selfgen == 1 ? 1 : 0),
                            'updater_id' => auth()->user()?->id ?? 1,
                            'position_id' => $parentPosition,
                        ]);

                        $userRecordsCreated++;
                        $totalRecordsCreated++;
                        // Performance: Removed debug logging
                    } else {
                        $userRecordsSkipped++;
                        $totalRecordsSkipped++;
                        // Performance: Removed debug logging
                    }
                }
                // Performance: Removed debug logging
            }
            // Performance: Removed verbose info logging
            // VALIDATE COMPLEX SCENARIO COMPLIANCE
            $this->validateComplexBackdatingScenario($positionId, $newEffectiveDate);

        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN FORWARD PROPAGATION USER ORGANIZATION HISTORIES', [
                'position_id' => $positionId,
                'new_effective_date' => $newEffectiveDate,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);
            throw $e;
        }
    }

    /**
     * Create comprehensive commission and compensation setup for existing products on new effective date
     * This includes all position-level and user-level records for complete functionality
     */
    private function createForwardPropagationCommissionAndUpfrontSetup($positionId, $newProductIds, $newEffectiveDate, $requestData = null)
    {
        try {
            // Performance: Removed verbose info logging
            $position = Positions::find($positionId);
            if (! $position) {
                $this->logConditional('error', '❌ POSITION NOT FOUND', [
                    'position_id' => $positionId,
                    'action' => 'forward propagation commission setup',
                ]);

                return;
            }

            $parentPosition = $position->parent_id ? $position->parent_id : (($position->id == '2' || $position->id == '3') ? $position->id : 0);

            // Get all existing products (excluding the new ones being added)
            $existingProductIds = PositionProduct::where('position_id', $positionId)
                ->whereNotIn('product_id', $newProductIds)
                ->whereNotNull('effective_date')
                ->where('effective_date', '<', $newEffectiveDate)
                ->pluck('product_id')
                ->unique()
                ->toArray();

            if (empty($existingProductIds)) {
                // Performance: Removed verbose info logging
                return;
            }

            // Determine core position combinations based on is_selfgen value
            $corePositions = [];
            if ($position->is_selfgen == '1') {
                $corePositions = [
                    ['core_position_id' => 2, 'self_gen_user' => 0],
                    ['core_position_id' => 3, 'self_gen_user' => 0],
                    ['core_position_id' => null, 'self_gen_user' => 1],
                ];
            } elseif ($position->is_selfgen == '2' || $position->is_selfgen == '3') {
                $corePositions = [
                    ['core_position_id' => $position->is_selfgen, 'self_gen_user' => 0],
                ];
            } elseif ($position->is_selfgen == '0') {
                $corePositions = [
                    ['core_position_id' => 2, 'self_gen_user' => 0],
                ];
            }

            $totalPositionCommissionsCreated = 0;
            $totalPositionUpfrontsCreated = 0;
            $totalPositionOverridesCreated = 0;
            $totalPositionReconciliationsCreated = 0;
            $totalUserCommissionsCreated = 0;
            $totalUserUpfrontsCreated = 0;
            $totalUserOverridesCreated = 0;
            $totalUserRedlinesCreated = 0;
            $totalUserWithheldCreated = 0;

            // Step 1: Create Position-level records for existing products
            foreach ($existingProductIds as $existingProductId) {
                foreach ($corePositions as $corePos) {
                    $corePositionId = $corePos['core_position_id'];
                    $selfGenUser = $corePos['self_gen_user'];

                    // Create PositionCommission records
                    // 🔧 FIXED: Get existing commission configuration to copy values (latest configuration)
                    $existingCommissionConfig = PositionCommission::where([
                        'position_id' => $positionId,
                        'product_id' => $existingProductId,
                        'core_position_id' => $corePositionId,
                    ])->where(function ($query) use ($newEffectiveDate) {
                        $query->whereNull('effective_date')
                            ->orWhere('effective_date', '<', $newEffectiveDate);
                    })->orderBy('effective_date', 'DESC')->first(); // Get latest configuration before new date
                    // Performance: Removed verbose info logging
                    $existingCommissionOnDate = PositionCommission::where([
                        'position_id' => $positionId,
                        'product_id' => $existingProductId,
                        'core_position_id' => $corePositionId,
                        'effective_date' => $newEffectiveDate,
                    ])->first();

                    // 🔧 FIXED: Always create records for ALL core positions, even if no configuration exists
                    if (! $existingCommissionOnDate) {
                        // 🔧 FIXED: Get commission values from request, database, or defaults (SAME AS BACKDATED METHOD)
                        $commissionParentage = 0;
                        $commissionStatus = 0;
                        $commissionAmountType = 'percent';
                        $commissionStructureType = 'Fixed';

                        if ($requestData && $this->hasCommissionInRequest($requestData, $existingProductId, $corePositionId)) {
                            // Get values directly from request
                            foreach ($requestData->commission as $commission) {
                                if ($commission['product_id'] == $existingProductId) {
                                    foreach ($commission['data'] as $data) {
                                        if ($data['core_position_id'] == $corePositionId) {
                                            $commissionParentage = $data['commission_parentage'] ?? 0;
                                            $commissionStatus = $commission['commission_status'] ?? 0;
                                            $commissionAmountType = $data['commission_amount_type'] ?? 'percent';
                                            $commissionStructureType = $data['commission_structure_type'] ?? 'Fixed';
                                            break 2;
                                        }
                                    }
                                }
                            }
                            // Performance: Removed verbose info logging
                        } elseif ($existingCommissionConfig) {
                            // Use existing database configuration
                            $commissionParentage = $existingCommissionConfig->commission_parentage;
                            $commissionStatus = $existingCommissionConfig->commission_status;
                            $commissionAmountType = $existingCommissionConfig->commission_amount_type;
                            $commissionStructureType = $existingCommissionConfig->commission_structure_type;
                            // Performance: Removed verbose info logging
                        } else {
                            // Use defaults if no configuration found
                            // Performance: Removed verbose info logging
                        }

                        $newPositionCommission = PositionCommission::create([
                            'position_id' => $positionId,
                            'core_position_id' => $corePositionId,
                            'product_id' => $existingProductId,
                            'self_gen_user' => $selfGenUser,
                            'commission_parentage' => $commissionParentage, // 🔧 FIXED: Use correct field name
                            'commission_parentag_hiring_locked' => $existingCommissionConfig ? $existingCommissionConfig->commission_parentag_hiring_locked : 0,
                            'commission_amount_type' => $commissionAmountType,
                            'commission_amount_type_locked' => $existingCommissionConfig ? $existingCommissionConfig->commission_amount_type_locked : 0,
                            'commission_structure_type' => $commissionStructureType,
                            'commission_parentag_type_hiring_locked' => $existingCommissionConfig ? $existingCommissionConfig->commission_parentag_type_hiring_locked : 0,
                            'commission_status' => $commissionStatus,
                            'tiers_id' => $existingCommissionConfig ? $existingCommissionConfig->tiers_id : 0,
                            'tiers_hiring_locked' => $existingCommissionConfig ? $existingCommissionConfig->tiers_hiring_locked : 0,
                            'effective_date' => $newEffectiveDate,
                        ]);
                        $totalPositionCommissionsCreated++;
                        // Performance: Removed debug logging
                    }

                    // Create PositionCommissionUpfronts records
                    // 🔧 FIXED: Get ALL existing upfront configurations to handle multiple upfronts per product
                    // First try to get configurations with NULL effective_date (base configurations)
                    $existingUpfrontConfigs = PositionCommissionUpfronts::where([
                        'position_id' => $positionId,
                        'product_id' => $existingProductId,
                        'core_position_id' => $corePositionId,
                    ])->where(function ($query) use ($newEffectiveDate) {
                        $query->whereNull('effective_date')
                            ->orWhere('effective_date', '<', $newEffectiveDate);
                    })->orderBy('effective_date', 'DESC')->get(); // Get latest configurations before new date

                    // If no configurations found with proper dates, try to get any available configuration
                    if ($existingUpfrontConfigs->isEmpty()) {
                        $existingUpfrontConfigs = PositionCommissionUpfronts::where([
                            'position_id' => $positionId,
                            'product_id' => $existingProductId,
                            'core_position_id' => $corePositionId,
                        ])->get();
                    }

                    // Create PositionCommissionUpfronts records - one for each milestone schema trigger
                    foreach ($existingUpfrontConfigs as $existingUpfrontConfig) {
                        $existingUpfrontOnDate = PositionCommissionUpfronts::where([
                            'position_id' => $positionId,
                            'product_id' => $existingProductId,
                            'core_position_id' => $corePositionId,
                            'milestone_schema_trigger_id' => $existingUpfrontConfig->milestone_schema_trigger_id,
                            'effective_date' => $newEffectiveDate,
                        ])->first();

                        if (! $existingUpfrontOnDate) {
                            // 🔧 FIXED: Get upfront values from request, database, or defaults (SAME AS BACKDATED METHOD)
                            $upfrontAmount = 0;
                            $upfrontStatus = 0;
                            $calculatedBy = 'per sale';
                            $upfrontSystem = 'Fixed';

                            if ($requestData && $this->hasUpfrontInRequest($requestData, $existingProductId, $corePositionId)) {
                                // Get values directly from request for this specific milestone schema trigger
                                foreach ($requestData->upfront as $upfront) {
                                    if ($upfront['product_id'] == $existingProductId) {
                                        foreach ($upfront['data'] as $data) {
                                            if ($data['core_position_id'] == $corePositionId) {
                                                foreach ($data['schemas'] as $schema) {
                                                    if (isset($schema['milestone_schema_trigger_id']) && $schema['milestone_schema_trigger_id'] == $existingUpfrontConfig->milestone_schema_trigger_id) {
                                                        $upfrontAmount = $schema['upfront_ammount'] ?? 0;
                                                        $upfrontStatus = $upfront['upfront_status'] ?? 0;
                                                        $calculatedBy = $schema['calculated_by'] ?? 'per sale';
                                                        $upfrontSystem = 'Fixed';
                                                        break 3;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                // Performance: Removed verbose info logging
                            } elseif ($existingUpfrontConfig) {
                                // Use existing database configuration
                                $upfrontAmount = $existingUpfrontConfig->upfront_ammount ?? 0;
                                $upfrontStatus = $existingUpfrontConfig->upfront_status ?? 0;
                                $calculatedBy = $existingUpfrontConfig->calculated_by ?? 'per sale';
                                $upfrontSystem = $existingUpfrontConfig->upfront_system ?? 'Fixed';
                                // Performance: Removed verbose info logging
                            } else {
                                // Use defaults if no configuration found
                                // Performance: Removed verbose info logging
                            }

                            $newPositionUpfront = PositionCommissionUpfronts::create([
                                'position_id' => $positionId,
                                'core_position_id' => $corePositionId,
                                'product_id' => $existingProductId,
                                'milestone_schema_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_id : null,
                                'milestone_schema_trigger_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_trigger_id : null,
                                'self_gen_user' => $selfGenUser,
                                'status_id' => $existingUpfrontConfig ? $existingUpfrontConfig->status_id : 1,
                                'upfront_ammount' => $upfrontAmount, // 🔧 FIXED: Use correct field name
                                'upfront_ammount_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_ammount_locked : 0,
                                'calculated_by' => $calculatedBy,
                                'calculated_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->calculated_locked : 0,
                                'upfront_status' => $upfrontStatus,
                                'upfront_system' => $upfrontSystem,
                                'upfront_system_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_system_locked : 0,
                                'upfront_limit' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_limit : null,
                                'upfront_limit_type' => $existingUpfrontConfig ? $existingUpfrontConfig->upfront_limit_type : null,
                                'tiers_id' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_id : null,
                                'tiers_hiring_locked' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_hiring_locked : 0,
                                'tiers_advancement' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_advancement : null,
                                'effective_date' => $newEffectiveDate,
                                'deductible_from_prior' => $existingUpfrontConfig ? $existingUpfrontConfig->deductible_from_prior : '0',
                            ]);
                            $totalPositionUpfrontsCreated++;
                            // Performance: Removed debug logging
                        }
                    }

                    // Create PositionOverride records for existing products
                    $existingOverrides = PositionOverride::where([
                        'position_id' => $positionId,
                        'product_id' => $existingProductId,
                    ])->whereNull('effective_date')->get();

                    foreach ($existingOverrides as $existingOverride) {
                        $existingOverrideOnDate = PositionOverride::where([
                            'position_id' => $positionId,
                            'product_id' => $existingProductId,
                            'override_id' => $existingOverride->override_id,
                            'effective_date' => $newEffectiveDate,
                        ])->first();

                        if (! $existingOverrideOnDate) {
                            $newPositionOverride = PositionOverride::create([
                                'position_id' => $positionId,
                                'product_id' => $existingProductId,
                                'override_id' => $existingOverride->override_id,
                                'settlement_id' => $existingOverride->settlement_id,
                                'override_ammount' => $existingOverride->override_ammount,
                                'override_ammount_locked' => $existingOverride->override_ammount_locked,
                                'override_limit' => $existingOverride->override_limit,
                                'override_limit_type' => $existingOverride->override_limit_type,
                                'status' => $existingOverride->status,
                                'type' => $existingOverride->type,
                                'override_type_locked' => $existingOverride->override_type_locked,
                                'tiers_id' => $existingOverride->tiers_id,
                                'tiers_override_status' => $existingOverride->tiers_override_status,
                                'tiers_advancement' => $existingOverride->tiers_advancement,
                                'tiers_hiring_locked' => $existingOverride->tiers_hiring_locked,
                                'tiers_start_end_date' => $existingOverride->tiers_start_end_date,
                                'effective_date' => $newEffectiveDate,
                            ]);
                            $totalPositionOverridesCreated++;
                            // Performance: Removed debug logging
                        }
                    }

                }
            }

            // Step 2: Create User-level records for existing products
            $users = User::where('sub_position_id', $positionId)->get();

            foreach ($users as $user) {
                $userId = $user->id;

                foreach ($existingProductIds as $existingProductId) {
                    foreach ($corePositions as $corePos) {
                        $corePositionId = $corePos['core_position_id'];
                        $selfGenUser = $corePos['self_gen_user'];

                        // Create UserCommissionHistory records
                        // 🔧 FIXED: Get existing commission configuration to copy tiers values
                        $existingCommissionConfig = PositionCommission::where([
                            'position_id' => $positionId,
                            'product_id' => $existingProductId,
                            'core_position_id' => $corePositionId,
                        ])->whereNull('effective_date')->first(); // Get base configuration (no effective date)

                        // CRITICAL FIX: Use correct effective date for existence check
                        $correctCommissionEffectiveDate = $this->getCorrectEffectiveDate($userId, $newEffectiveDate);

                        $existingUserCommission = UserCommissionHistory::where([
                            'user_id' => $userId,
                            'product_id' => $existingProductId,
                            'core_position_id' => $corePositionId,
                            'commission_effective_date' => $correctCommissionEffectiveDate,
                        ])->first();
                        // Performance: Removed verbose info logging
                        if (! $existingUserCommission) {
                            // 🔧 FIXED: Copy commission values from request, latest existing configuration, or defaults (SAME AS BACKDATED METHOD)
                            $commissionValue = 0;
                            $commissionType = 'percent';
                            $actionItemStatus = 1;

                            if ($requestData && $this->hasCommissionInRequest($requestData, $existingProductId, $corePositionId)) {
                                // Get values directly from request
                                foreach ($requestData->commission as $commission) {
                                    if ($commission['product_id'] == $existingProductId) {
                                        foreach ($commission['data'] as $data) {
                                            if ($data['core_position_id'] == $corePositionId) {
                                                $commissionValue = $data['commission_parentage'] ?? 0;
                                                $commissionType = $data['commission_amount_type'] ?? 'percent';
                                                $actionItemStatus = $commission['commission_status'] ?? 1;
                                                break 2;
                                            }
                                        }
                                    }
                                }
                                // Performance: Removed verbose info logging
                            } else {
                                // Try to get the latest user commission configuration first
                                $latestUserCommission = UserCommissionHistory::where([
                                    'user_id' => $userId,
                                    'product_id' => $existingProductId,
                                    'core_position_id' => $corePositionId,
                                ])->whereNotNull('commission_effective_date')
                                    ->orderBy('commission_effective_date', 'DESC')
                                    ->first();

                                if ($latestUserCommission) {
                                    $commissionValue = $latestUserCommission->commission;
                                    $commissionType = $latestUserCommission->commission_type ?? 'percent';
                                    $actionItemStatus = $latestUserCommission->action_item_status ?? 1;
                                    // Performance: Removed verbose info logging
                                } elseif ($existingCommissionConfig) {
                                    $commissionValue = $existingCommissionConfig->commission_parentage ?? 0;
                                    $commissionType = $existingCommissionConfig->commission_amount_type ?? 'percent';
                                    $actionItemStatus = $existingCommissionConfig->commission_status ?? 1;
                                    // Performance: Removed verbose info logging
                                } else {
                                    // Performance: Removed verbose info logging
                                }
                            }

                            $newUserCommission = UserCommissionHistory::create([
                                'user_id' => $userId,
                                'updater_id' => auth()->user()?->id ?? 1,
                                'product_id' => $existingProductId,
                                'old_product_id' => null,
                                'self_gen_user' => $selfGenUser,
                                'old_self_gen_user' => null,
                                'commission' => $commissionValue,
                                'old_commission' => null,
                                'commission_type' => $commissionType,
                                'old_commission_type' => null,
                                'commission_effective_date' => $correctCommissionEffectiveDate, // 🔧 FIXED: Use corrected effective date
                                'effective_end_date' => null,
                                'action_item_status' => $actionItemStatus,
                                'position_id' => $parentPosition,
                                'sub_position_id' => $positionId,
                                'core_position_id' => $corePositionId,
                                'tiers_id' => $existingCommissionConfig ? $existingCommissionConfig->tiers_id : null,
                                'old_tiers_id' => null,
                            ]);
                            $totalUserCommissionsCreated++;
                        }

                        // Create UserUpfrontHistory records
                        // 🔧 FIXED: Get ALL existing upfront configurations to handle multiple upfronts per product
                        // First try to get configurations with NULL effective_date (base configurations)
                        $existingUpfrontConfigs = PositionCommissionUpfronts::where([
                            'position_id' => $positionId,
                            'product_id' => $existingProductId,
                            'core_position_id' => $corePositionId,
                        ])->whereNull('effective_date')->get();

                        // If no NULL effective_date configs found, get the latest effective_date configs
                        if ($existingUpfrontConfigs->isEmpty()) {
                            $latestEffectiveDate = PositionCommissionUpfronts::where([
                                'position_id' => $positionId,
                                'product_id' => $existingProductId,
                                'core_position_id' => $corePositionId,
                            ])->whereNotNull('effective_date')
                                ->max('effective_date');

                            if ($latestEffectiveDate) {
                                $existingUpfrontConfigs = PositionCommissionUpfronts::where([
                                    'position_id' => $positionId,
                                    'product_id' => $existingProductId,
                                    'core_position_id' => $corePositionId,
                                    'effective_date' => $latestEffectiveDate,
                                ])->get();
                                // Performance: Removed verbose info logging
                            }
                        }

                        // Create UserUpfrontHistory records - one for each milestone schema trigger
                        // CRITICAL FIX: Calculate correct effective date before existence check
                        $correctUpfrontEffectiveDate = $this->getCorrectEffectiveDate($userId, $newEffectiveDate);

                        foreach ($existingUpfrontConfigs as $existingUpfrontConfig) {
                            $existingUserUpfront = UserUpfrontHistory::where([
                                'user_id' => $userId,
                                'product_id' => $existingProductId,
                                'core_position_id' => $corePositionId,
                                'milestone_schema_trigger_id' => $existingUpfrontConfig->milestone_schema_trigger_id,
                                'upfront_effective_date' => $correctUpfrontEffectiveDate,
                            ])->first();

                            if (! $existingUserUpfront) {
                                // 🔧 FIXED: Copy upfront values from request, latest existing configuration, or defaults (SAME AS BACKDATED METHOD)
                                $upfrontPayAmount = 0;
                                $upfrontSaleType = 'per sale';
                                $customSalesFieldId = null;
                                $actionItemStatus = 1;

                                if ($requestData && $this->hasUpfrontInRequest($requestData, $existingProductId, $corePositionId)) {
                                    // Get values directly from request for this specific milestone schema trigger
                                    foreach ($requestData->upfront as $upfront) {
                                        if ($upfront['product_id'] == $existingProductId) {
                                            foreach ($upfront['data'] as $data) {
                                                if ($data['core_position_id'] == $corePositionId) {
                                                    foreach ($data['schemas'] as $schema) {
                                                        if (isset($schema['milestone_schema_trigger_id']) && $schema['milestone_schema_trigger_id'] == $existingUpfrontConfig->milestone_schema_trigger_id) {
                                                            $upfrontPayAmount = $schema['upfront_ammount'] ?? 0;
                                                            $upfrontSaleType = $schema['calculated_by'] ?? 'per sale';
                                                            $customSalesFieldId = $schema['custom_sales_field_id'] ?? null;
                                                            $actionItemStatus = $upfront['upfront_status'] ?? 1;
                                                            break 3;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    // Performance: Removed verbose info logging
                                } else {
                                    // Try to get the latest user upfront configuration first
                                    $latestUserUpfront = UserUpfrontHistory::where([
                                        'user_id' => $userId,
                                        'product_id' => $existingProductId,
                                        'core_position_id' => $corePositionId,
                                        'milestone_schema_trigger_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_trigger_id : null,
                                    ])->whereNotNull('upfront_effective_date')
                                        ->orderBy('upfront_effective_date', 'DESC')
                                        ->first();

                                    if ($latestUserUpfront) {
                                        $upfrontPayAmount = $latestUserUpfront->upfront_pay_amount;
                                        $upfrontSaleType = $latestUserUpfront->upfront_sale_type ?? 'per sale';
                                        $customSalesFieldId = $latestUserUpfront->custom_sales_field_id ?? null;
                                        $actionItemStatus = $latestUserUpfront->action_item_status ?? 1;
                                        // Performance: Removed verbose info logging
                                    } elseif ($existingUpfrontConfig) {
                                        $upfrontPayAmount = $existingUpfrontConfig->upfront_ammount ?? 0;
                                        $upfrontSaleType = $existingUpfrontConfig->calculated_by ?? 'per sale';
                                        $customSalesFieldId = $existingUpfrontConfig->custom_sales_field_id ?? null;
                                        $actionItemStatus = $existingUpfrontConfig->upfront_status ?? 1;
                                        // Performance: Removed verbose info logging
                                    } else {
                                        // Performance: Removed verbose info logging
                                        $customSalesFieldId = null;
                                    }
                                }

                                $newUserUpfront = UserUpfrontHistory::create([
                                    'user_id' => $userId,
                                    'updater_id' => auth()->user()?->id ?? 1,
                                    'product_id' => $existingProductId,
                                    'old_product_id' => null,
                                    'milestone_schema_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_id : null,
                                    'old_milestone_schema_id' => null,
                                    'milestone_schema_trigger_id' => $existingUpfrontConfig ? $existingUpfrontConfig->milestone_schema_trigger_id : null,
                                    'old_milestone_schema_trigger_id' => null,
                                    'self_gen_user' => $selfGenUser,
                                    'old_self_gen_user' => null,
                                    'upfront_pay_amount' => $upfrontPayAmount,
                                    'old_upfront_pay_amount' => null,
                                    'upfront_sale_type' => $upfrontSaleType,
                                    'old_upfront_sale_type' => null,
                                    'upfront_effective_date' => $correctUpfrontEffectiveDate, // 🔧 FIXED: Use corrected effective date
                                    'effective_end_date' => null,
                                    'action_item_status' => $actionItemStatus,
                                    'position_id' => $parentPosition,
                                    'sub_position_id' => $positionId,
                                    'core_position_id' => $corePositionId,
                                    'tiers_id' => $existingUpfrontConfig ? $existingUpfrontConfig->tiers_id : null,
                                    'old_tiers_id' => null,
                                    'custom_sales_field_id' => $customSalesFieldId,
                                ]);
                                $totalUserUpfrontsCreated++;
                            }
                        }

                        // Create UserOverrideHistory records with all override types
                        // CRITICAL FIX: Use correct effective date based on hire date
                        $correctOverrideEffectiveDate = $this->getCorrectEffectiveDate($userId, $newEffectiveDate);

                        $existingUserOverride = UserOverrideHistory::where([
                            'user_id' => $userId,
                            'product_id' => $existingProductId,
                            'override_effective_date' => $correctOverrideEffectiveDate,
                        ])->first();

                        if (! $existingUserOverride) {
                            // 🔧 FIXED: Copy override values from latest existing configuration instead of hardcoded 0
                            $directOverridesAmount = 0;
                            $indirectOverridesAmount = 0;
                            $officeOverridesAmount = 0;
                            $officeStackOverridesAmount = 0;
                            $directOverridesType = 'per sale';
                            $indirectOverridesType = 'per sale';
                            $officeOverridesType = 'per sale';
                            $actionItemStatus = 0;
                            $directTiersId = null;
                            $indirectTiersId = null;
                            $officeTiersId = null;
                            // Custom Sales Field IDs
                            $directCustomSalesFieldId = null;
                            $indirectCustomSalesFieldId = null;
                            $officeCustomSalesFieldId = null;

                            // Try to get the latest user override configuration first
                            $latestUserOverride = UserOverrideHistory::where([
                                'user_id' => $userId,
                                'product_id' => $existingProductId,
                            ])->whereNotNull('override_effective_date')
                                ->orderBy('override_effective_date', 'DESC')
                                ->first();

                            if ($latestUserOverride) {
                                $directOverridesAmount = $latestUserOverride->direct_overrides_amount ?? 0;
                                $indirectOverridesAmount = $latestUserOverride->indirect_overrides_amount ?? 0;
                                $officeOverridesAmount = $latestUserOverride->office_overrides_amount ?? 0;
                                $officeStackOverridesAmount = $latestUserOverride->office_stack_overrides_amount ?? 0;
                                $directOverridesType = $latestUserOverride->direct_overrides_type ?? 'per sale';
                                $indirectOverridesType = $latestUserOverride->indirect_overrides_type ?? 'per sale';
                                $officeOverridesType = $latestUserOverride->office_overrides_type ?? 'per sale';
                                $actionItemStatus = $latestUserOverride->action_item_status ?? 1;
                                $directTiersId = $latestUserOverride->direct_tiers_id;
                                $indirectTiersId = $latestUserOverride->indirect_tiers_id;
                                $officeTiersId = $latestUserOverride->office_tiers_id;
                                // Custom Sales Field IDs
                                $directCustomSalesFieldId = $latestUserOverride->direct_custom_sales_field_id ?? null;
                                $indirectCustomSalesFieldId = $latestUserOverride->indirect_custom_sales_field_id ?? null;
                                $officeCustomSalesFieldId = $latestUserOverride->office_custom_sales_field_id ?? null;
                                // Performance: Removed verbose info logging
                            } else {
                                // Performance: Removed verbose info logging
                            }

                            $newUserOverride = UserOverrideHistory::create([
                                'user_id' => $userId,
                                'updater_id' => auth()->user()?->id ?? 1,
                                'product_id' => $existingProductId,
                                'old_product_id' => null,
                                // 🔧 REMOVED: self_gen_user, old_self_gen_user (columns don't exist)
                                'direct_overrides_amount' => $directOverridesAmount,
                                'old_direct_overrides_amount' => null,
                                'direct_overrides_type' => $directOverridesType,
                                'old_direct_overrides_type' => null,
                                'indirect_overrides_amount' => $indirectOverridesAmount,
                                'old_indirect_overrides_amount' => null,
                                'indirect_overrides_type' => $indirectOverridesType,
                                'old_indirect_overrides_type' => null,
                                'office_overrides_amount' => $officeOverridesAmount,
                                'old_office_overrides_amount' => null,
                                'office_overrides_type' => $officeOverridesType,
                                'old_office_overrides_type' => null,
                                'office_stack_overrides_amount' => $officeStackOverridesAmount,
                                'override_effective_date' => $correctOverrideEffectiveDate, // 🔧 FIXED: Use corrected effective date
                                'effective_end_date' => null,
                                'action_item_status' => $actionItemStatus,
                                // 🔧 REMOVED: position_id, sub_position_id, core_position_id (columns don't exist)
                                'direct_tiers_id' => $directTiersId,
                                'old_direct_tiers_id' => null,
                                'indirect_tiers_id' => $indirectTiersId,
                                'old_indirect_tiers_id' => null,
                                'office_tiers_id' => $officeTiersId,
                                'old_office_tiers_id' => null,
                                // Custom Sales Field IDs
                                'direct_custom_sales_field_id' => $directCustomSalesFieldId,
                                'indirect_custom_sales_field_id' => $indirectCustomSalesFieldId,
                                'office_custom_sales_field_id' => $officeCustomSalesFieldId,
                            ]);
                            $totalUserOverridesCreated++;
                        }

                    }
                }
            }
            // Performance: Removed verbose info logging
        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN FORWARD PROPAGATION COMMISSION SETUP', [
                'position_id' => $positionId,
                'new_effective_date' => $newEffectiveDate,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate that propagation is complete for all products that should exist on a given effective date
     * This method ensures no products are missing after forward or backward propagation
     *
     * @param  int  $positionId  The position ID to validate
     * @param  string  $effectiveDate  The effective date to validate (Y-m-d format)
     * @param  array  $excludeProductIds  Products to exclude from validation (optional)
     * @return bool True if propagation is complete, false if issues found
     */
    private function validatePropagationCompleteness(int $positionId, string $effectiveDate, array $excludeProductIds = []): bool
    {
        try {

            // 🔧 CRITICAL FIX: Get expected products from the PREVIOUS effective date only
            // Don't get from ALL old dates, as that includes products removed in previous updates
            $previousEffectiveDate = PositionProduct::where('position_id', $positionId)
                ->where('effective_date', '<', $effectiveDate)
                ->whereNull('deleted_at')
                ->orderBy('effective_date', 'DESC')
                ->value('effective_date');
            
            $expectedProducts = PositionProduct::where('position_id', $positionId)
                ->where(function ($query) use ($previousEffectiveDate) {
                    if ($previousEffectiveDate) {
                        $query->where('effective_date', $previousEffectiveDate);
                    } else {
                        $query->whereNull('effective_date');
                    }
                })
                ->whereNotIn('product_id', $excludeProductIds)
                ->whereNull('deleted_at')
                ->pluck('product_id')
                ->unique()
                ->sort()
                ->values();

            // Get products that actually exist on this effective date
            $actualProducts = PositionProduct::where('position_id', $positionId)
                ->where('effective_date', $effectiveDate)
                ->whereNull('deleted_at')
                ->pluck('product_id')
                ->unique()
                ->sort()
                ->values();

            // Find missing products
            $missingProducts = $expectedProducts->diff($actualProducts);


            if ($missingProducts->isNotEmpty()) {
                $this->logConditional('error', '❌ PROPAGATION INCOMPLETE - MISSING PRODUCTS DETECTED', [
                    'position_id' => $positionId,
                    'effective_date' => $effectiveDate,
                    'missing_products' => $missingProducts->toArray(),
                    'expected_count' => $expectedProducts->count(),
                    'actual_count' => $actualProducts->count(),
                    'expected_products' => $expectedProducts->toArray(),
                    'actual_products' => $actualProducts->toArray(),
                ]);

                // 🔧 CRITICAL FIX: DISABLE auto-fix for missing products
                // When user explicitly updates a position, missing products should NOT be auto-added
                // The controller already created the exact list the user requested
                // Auto-adding causes products removed in previous updates to resurface
                
                // Log the issue but DON'T auto-add
                $recordsCreated = 0;
                foreach ($missingProducts as $missingProduct) {
                }

                /* AUTO-ADD DISABLED - This causes removed products to resurface
                foreach ($missingProducts as $missingProduct) {
                    try {
                        PositionProduct::create([
                            'position_id' => $positionId,
                            'product_id' => $missingProduct,
                            'effective_date' => $effectiveDate,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $recordsCreated++;

                        // Auto-fixed missing product
                    } catch (Exception $e) {
                        $this->logConditional('error', '❌ FAILED TO AUTO-FIX MISSING PRODUCT', [
                            'position_id' => $positionId,
                            'product_id' => $missingProduct,
                            'effective_date' => $effectiveDate,
                            'error_message' => $e->getMessage(),
                            'error_class' => get_class($e),
                        ]);
                    }
                }
                */  // End of disabled auto-add

                // Auto-fix DISABLED - products removed in previous updates should not resurface

                return true; // No auto-fix performed
            }

            // Propagation validation passed

            return true; // All products are properly propagated

        } catch (Exception $e) {
            $this->logConditional('error', '💥 ERROR IN PROPAGATION VALIDATION', [
                'position_id' => $positionId,
                'effective_date' => $effectiveDate,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);

            // Don't throw - validation failure shouldn't break the main flow
            return false;
        }
    }
}
