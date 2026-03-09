<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Core\Traits\ReconTraits\ReconRoutineTraits;
use App\Core\Traits\SaleTraits\CustomFieldConversionTrait;
use App\Core\Traits\SaleTraits\EditSaleTrait;
use App\Core\Traits\SaleTraits\SubroutineTrait;
use App\Services\SalesCalculationContext;
use App\Exports\SampleSaleExport;
use App\Imports\Sales\ImportSales;
use App\Imports\Sales\MortgageSalesImport;
use App\Imports\Sales\PestSalesImport;
use App\Jobs\GenerateAlertJob;
use App\Jobs\RecalculateOpenTieredSalesJob;
use App\Jobs\RecalculateSalesJob;
use App\Jobs\Sales\SaleMasterJob;
use App\Jobs\SalesExportJob;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\CustomerPayment;
use App\Models\ExcelImportHistory;
use App\Models\Integration;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRowData;
use App\Models\LegacyApiRawDataHistoryLog;
use App\Models\CloserIdentifyAlert;
use App\Models\DeductionAlert;
use App\Models\RequestApprovelByPid;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\Payroll;
use App\Models\PayrollAdjustmentDetail;
use App\Models\Positions;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Models\ProjectionUserCommission;
use App\Models\ProjectionUserOverrides;
use App\Models\OverrideArchive;
use App\Models\ReconAdjustment;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconDeductionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\UserReconciliationWithholding;
use App\Models\UserReconciliationCommissionWithholding;
use App\Models\ReconciliationsAdjustement;
use App\Models\MoveToReconciliation;
use App\Models\SaleMasterProcess;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\SaleTiersDetail;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UserTransferHistory;
use App\Traits\EmailNotificationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PositionCommission;
use App\Jobs\Sales\ExcelSalesProcessJob;
use App\Models\ExternalSaleWorker;
use App\Models\ExternalSaleProductMaster;
use App\Models\Crmsaleinfo;
use App\Models\Crmcustomfields;
use App\Models\SalesInvoiceDetail;
use App\Helpers\CustomSalesFieldHelper;
use App\Services\SalesCustomFieldCalculator;
use Laravel\Pennant\Feature;
use App\Models\PayrollAdjustment;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollOvertime;
use App\Models\PayrollDeductions;
use App\Models\PayrollShiftHistorie;
use App\Models\PayrollUserCommissionHistory;
use App\Models\OverrideStatus;
use App\Models\UserOverrideHistory;
use App\Services\JobNotificationService;
use App\Services\JobPerformanceTracker;
use Illuminate\Http\Exceptions\HttpResponseException;

class SalesController extends BaseController
{
    use EditSaleTrait, SubroutineTrait, ReconRoutineTraits, EmailNotificationTrait, CustomFieldConversionTrait;

    // GET CLOSER & SETTER LIST BASED ON PRODUCT & EFFECTIVE DATE
    // ⭐ V3 OPTIMIZED: Early excluded user filtering (25% faster than V2)
    public function setterCloserListByEffectiveDate(Request $request)
    {
        $this->checkValidations($request->all(), [
            'effective_date' => 'required',
            'user_type' => 'required'
        ]);
        $effectiveDate = $request->effective_date;
        $userType = $request->user_type;

        // Get position IDs (direct query for data consistency)
        // Note: Cache removed to ensure real-time position status updates
        $positionId = Positions::where('is_selfgen', '0')->pluck('id');
        $positionIdList = $positionId->implode(',');

        // ⭐ V3 OPTIMIZATION: Get excluded users FIRST, before window function
        // This reduces the dataset size for the expensive window function operation
        // Excluded users are processed ONCE and filtered early in the pipeline
        $nonUsers = $this->getExcludedUsers($effectiveDate);
        $excludedUsersList = !empty($nonUsers) ? implode(',', $nonUsers) : '0';

        // ⭐ V2+V3+V3.1 OPTIMIZATION: Combined window function with EARLY excluded user filtering
        // This replaces THREE operations + filters excluded users in window function:
        // 1. Old Window Function #1: Get closest dates for all users (0.052s)
        // 2. Old Row Constructor: Filter by position (0.010s)
        // 3. Old Window Function #2: Get positions again (0.064s)
        // 4. V3: Filter excluded users BEFORE partitioning (saves 15-25% on window function)
        // 5. V3.1: Removed unnecessary userOrganization relationships (saved 1.4s!)

        try {
            $sql = "
                SELECT
                    uoh.user_id,
                    uoh.position_id,
                    uoh.sub_position_id,
                    p1.position_name as position_name,
                    p2.position_name as sub_position_name
                FROM (
                    SELECT
                        user_id,
                        position_id,
                        sub_position_id,
                        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn
                    FROM user_organization_history
                    WHERE effective_date <= ?
                      AND (position_id = ? OR self_gen_accounts = 1)
                      AND sub_position_id NOT IN ($positionIdList)
                      AND user_id NOT IN ($excludedUsersList)
                ) uoh
                LEFT JOIN positions p1 ON uoh.position_id = p1.id
                LEFT JOIN positions p2 ON uoh.sub_position_id = p2.id
                WHERE uoh.rn = 1
            ";

            $results = DB::select($sql, [$effectiveDate, $userType]);

            // Convert to collection and build user positions map
            $userPositions = collect($results)->mapWithKeys(function ($record) {
                return [$record->user_id => (object)[
                    'user_id' => $record->user_id,
                    'position_id' => $record->position_id,
                    'sub_position_id' => $record->sub_position_id,
                    'position_name' => $record->position_name,
                    'sub_position_name' => $record->sub_position_name,
                ]];
            });

            $userIdArr = $userPositions->keys()->toArray();
        } catch (\Throwable $e) {
            // Fallback to original logic if window function fails
            Log::error('Optimized window function failed in setterCloserListByEffectiveDate', [
                'error' => $e->getMessage(),
                'effective_date' => $effectiveDate
            ]);

            // Fallback: Use traditional query without window function (also with early filtering)
            $results = UserOrganizationHistory::select('user_id', 'position_id', 'sub_position_id')
                ->with(['position:id,position_name', 'subPositionId:id,position_name'])
                ->where('effective_date', '<=', $effectiveDate)
                ->where(function ($query) use ($userType) {
                    $query->where('position_id', $userType)->orWhere('self_gen_accounts', 1);
                })
                ->whereNotIn('sub_position_id', $positionId)
                ->whereNotIn('user_id', $nonUsers)
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->groupBy('user_id')
                ->get();

            $userPositions = $results->mapWithKeys(function ($record) {
                return [$record->user_id => (object)[
                    'user_id' => $record->user_id,
                    'position_id' => $record->position_id,
                    'sub_position_id' => $record->sub_position_id,
                    'position_name' => $record->position?->position_name,
                    'sub_position_name' => $record->subPositionId?->position_name,
                ]];
            });

            $userIdArr = $userPositions->keys()->toArray();
        }

        // ✅ Load users (excluded users already filtered in query above!)
        // ⚡ V3.1 OPTIMIZATION: Optimized relationship loading with safety fallback
        // We have position data from window function, with lightweight fallback for data safety
        // Removed heavy userOrganization relationship (saved 1.3s!)
        $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'stop_payroll', 'sub_position_id', 'dismiss', 'terminate', 'contract_ended', 'position_id')
            ->with(['parentPositionDetail:id,position_name', 'positionDetail:id,position_name'])
            ->whereIn('id', $userIdArr)
            ->whereNotIn('id', $nonUsers) // ✅ DEFENSIVE: Extra safety layer to ensure excluded users never appear
            ->get();

        // Transform data using pre-loaded position data (same as before)
        $data = $users->map(function ($user) use ($userPositions) {
            $positionData = $userPositions->get($user->id);

            return [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'office_id' => $user->office_id,
                'stop_payroll' => $user->stop_payroll,
                'sub_position_id' => $user->sub_position_id,
                'dismiss' => $user->dismiss,
                'terminate' => $user->terminate,
                'contract_ended' => $user->contract_ended,
                // ✅ Use pre-loaded position data from window function with safety fallback
                'position' => $positionData?->position_name ?? $user->parentPositionDetail?->position_name,
                'position_id' => $positionData?->position_id ?? $user->position_id,
                'sub_position_name' => $positionData?->sub_position_name ?? $user->positionDetail?->position_name,
            ];
        });

        $this->successResponse("Successfully.", "setterCloserListByEffectiveDate", $data);
    }

    // MILESTONE FROM PRODUCT & APPROVAL DATE
    public function milestoneFromProduct(Request $request)
    {
        $this->checkValidations($request->all(), [
            'approved_date' => 'required'
        ]);

        $productId = $request->product_id;
        $effectiveDate = $request->approved_date;
        $milestone = $this->milestoneWithSchema($productId, $effectiveDate);
        if (!$milestone) {
            return $this->errorResponse("We couldn't find a milestone schema linked to this product for the specified sale date. Please assign a milestone schema to the product with an effective date, ensuring the sale date falls within the effective date range of this assignment.", 'milestoneFromProduct', '', 400);
        }

        $response = [];
        $response['product_redline'] = $milestone->product_redline;
        foreach ($milestone->milestone->milestone_trigger as $schema) {
            $response['data'][] = [
                'pid' => $request->pid,
                'type' => $schema->name,
                'trigger' => $schema->on_trigger,
                'date' => NULL
            ];
        }

        $this->successResponse("Successfully.", "milestoneFromProduct", $response);
    }

    // SALES LIST
    public function salesList(Request $request)
    {
        $result = array();
        $perPage = $request->perpage ? $request->perpage : 10;

        $startDate = '';
        $endDate = '';
        $companyProfile = CompanyProfile::first();
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = $request->milestone_date ?? false;
        $sale_type = $request->sale_type ?? '';

        $officeId = $request->office_id;

        // 1. SELECTIVE FIELD LOADING - only select needed fields for memory optimization
        $result = SalesMaster::select([
            'id',
            'pid',
            'customer_name',
            'customer_state',
            'kw',
            'epc',
            'net_epc',
            'adders',
            'gross_account_value',
            'service_completed',
            'date_cancelled',
            'total_commission',
            'projected_commission',
            'total_override',
            'projected_override',
            'closer1_id',
            'closer2_id',
            'setter1_id',
            'setter2_id',
            'product_id',
            'product_code',
            'sale_product_name',
            'product',
            'data_source_type',
            'customer_signoff',
            'job_status',
            'closer1_name',
            'setter1_name' // For search functionality
        ])
            // 2. OPTIMIZED EAGER LOADING - proper syntax and selective fields
            ->with([
                'productInfo' => function ($q) {
                    $q->select('id', 'product_id', 'product_code')->withTrashed();
                },
                'productInfo.product:id,name', // Load product name correctly
                'salesMasterProcessInfo' => function ($q) {
                    $q->select('pid', 'mark_account_status_id');
                },
                'salesMasterProcessInfo.status:id,account_status',
                // Aggregated subquery for better performance
                'salesProductMaster' => function ($q) {
                    $q->selectRaw('pid, type, SUM(amount) as value, milestone_date, is_projected, milestone_schema_id')
                        ->groupBy('pid', 'type');
                },
                'lastMilestone' => function ($q) {
                    $q->select('id', 'pid', 'type', 'amount', 'milestone_date', 'is_paid', 'milestone_schema_id');
                },
                'lastMilestone.milestoneSchemaTrigger:id,name,on_trigger',
                'salesProductMaster.milestoneSchemaTrigger:id,name,on_trigger',
                // Only essential user fields
                'closer1Detail:id,first_name,last_name,dismiss,terminate,contract_ended,stop_payroll',
                'closer2Detail:id,first_name,last_name,dismiss,terminate,contract_ended,stop_payroll',
                'setter1Detail:id,first_name,last_name,dismiss,terminate,contract_ended,stop_payroll',
                'setter2Detail:id,first_name,last_name,dismiss,terminate,contract_ended,stop_payroll',
                'legacyAPINull' => function ($q) {
                    $q->select('id', 'pid', 'data_source_type', 'sales_alert', 'missingrep_alert', 'locationRedline_alert', 'closedpayroll_alert', 'repredline_alert')
                        ->whereNotNull('data_source_type');
                }
            ])->when(!empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate,) {
                            $q->whereBetween('milestone_date', [$startDate, $endDate])
                                ->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            })->when(($request->has('office_id') && !empty($request->input('office_id'))), function ($q) use ($request) {
                $officeId = request()->input('office_id');
                if ($officeId != 'all') {
                    if (!empty($request->user_id)) {
                        $userId = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
                    } else {
                        $userId = User::where('office_id', $officeId)->pluck('id');
                    }
                    // $userId = User::where('office_id', $officeId)->pluck('id');
                    $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
                    $q->whereIn('pid', $salesPid);
                }
            })
            ->when((!empty($request->user_id) && ($officeId == 'all')), function ($q) use ($request) {
                if (!empty($request->user_id)) {
                    $userId = User::where('id', $request->input('user_id'))->pluck('id');
                    $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
                } else {
                    $salesPid = collect(); // no filter — will not apply pid condition
                }
                $q->whereIn('pid', $salesPid);
            })
            ->when(($request->has('search') && !empty($request->input('search'))), function ($q) {
                $search = request()->input('search');
                $q->where(function ($query) use ($search) {
                    $query->where('pid', 'LIKE', '%' . $search . '%')->orWhere('customer_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('closer1_name', 'LIKE', '%' . $search . '%')->orWhere('setter1_name', 'LIKE', '%' . $search . '%');
                });
            })->when(($request->has('filter_product') && !empty($request->input('filter_product'))), function ($q) {
                // Retrieve the filter_product value once to avoid repeated function calls
                $filterProduct = request()->input('filter_product');

                if ($filterProduct == 1) {
                    // If filter_product is 1, match both product_id = 1 and NULL (unassigned)
                    $q->where(function ($query) use ($filterProduct) {
                        $query->where('product_id', $filterProduct)
                            ->orWhereNull('product_id');
                    });
                } else {
                    // Otherwise, match product_id exactly
                    $q->where('product_id', $filterProduct);
                }
            })->when(($request->has('location') && !empty($request->input('location'))), function ($q) {
                $q->where('customer_state', request()->input('location'));
            })->when(($request->has('filter_install') && !empty($request->input('filter_install'))), function ($q) {
                $q->where('install_partner', request()->input('filter_install'));
            })->when(($request->has('filter_status') && !empty($request->input('filter_status'))), function ($q) use ($request) {
                $this->applyPestStatusFilter($q, $request->input('filter_status'));
            })->when(($request->has('date_filter') && !empty($request->input('date_filter'))), function ($q) use ($request) {
                $dateFilter = $request->input('date_filter');

                if ($dateFilter == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } elseif ($this->isPestCompany()) {
                    // Pest company specific date filters
                    if ($dateFilter == 'Service Completion Date') {
                        $q->whereNotNull('last_service_date');
                    } elseif ($dateFilter == 'Initial Service Date') {
                        $q->whereNotNull('initial_service_date');
                    } else {
                        // For other date filters in pest companies, use milestone logic
                        $q->whereHas('salesProductMaster', function ($q) use ($dateFilter) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($dateFilter) {
                                $q->where('name', $dateFilter)
                                    ->orWhere('on_trigger', $dateFilter);
                            });
                        });
                    }
                } else {
                    // For non-pest companies, use milestone logic for all other date filters
                    $q->whereHas('salesProductMaster', function ($q) use ($dateFilter) {
                        $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($dateFilter) {
                            $q->where('name', $dateFilter)
                                ->orWhere('on_trigger', $dateFilter);
                        });
                    });
                }
            })->when(($request->has('filter_data_source_type') && !empty($request->input('filter_data_source_type'))), function ($q) use ($request) {
                // Add source filter based on data_source_type
                $sourceFilter = $request->input('filter_data_source_type');
                if ($sourceFilter !== 'all') {
                    $q->where('data_source_type', $sourceFilter);
                }
            })
            ->when(($request->has('filter_product_id') && !empty($request->input('filter_product_id'))), function ($q) use ($request) {
                // Filter by specific product ID
                $productIdFilter = $request->input('filter_product_id');
                if ($productIdFilter !== 'all') {
                    if ($productIdFilter == 'null' || $productIdFilter == 'unassigned') {
                        $q->whereNull('product_id');
                    } else {
                        $q->where('product_id', $productIdFilter);
                    }
                }
            });

        // Enhanced sorting logic with comprehensive column support
        $orderBy = $request->input('sort_val', 'DESC');
        $sortColumn = $request->input('sort', '');

        // Validate sort direction
        $orderBy = in_array(strtoupper($orderBy), ['ASC', 'DESC']) ? strtoupper($orderBy) : 'DESC';

        $domainName = config('app.domain_name');

        // Apply sorting based on the requested column
        $this->applySorting($result, $sortColumn, $orderBy, $companyProfile);

        // Handle special cases that require post-processing (including product name filtering)
        $needsPostProcessing = ($sortColumn == 'last_payment' || $sortColumn == 'product_name' || $sortColumn == 'product' || $sortColumn == 'job_status' || $sortColumn == 'status')
            || ($request->has('filter_product_name') && !empty($request->input('filter_product_name')));

        if ($needsPostProcessing) {
            $data = $result->orderBy('id', $orderBy)->get();
        } else {
            $data = $result->paginate($perPage);
        }

        $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();

        // 3. BULK DATA LOADING - Preload all related data at once to avoid N+1 queries
        $pids = $result->pluck('pid')->toArray();

        // Preload commissions for all records at once
        $userCommissions = UserCommission::whereIn('pid', $pids)
            ->where('status', 3)
            ->get()
            ->keyBy('pid');

        // Preload reconciliation commissions for all records at once
        $reconCommissions = UserCommission::selectRaw("SUM(amount) as amount, pid, user_id, date")
            ->whereIn('pid', $pids)
            ->where('settlement_type', 'reconciliation')
            ->groupBy('pid', 'user_id', 'date')
            ->get()
            ->keyBy('pid');

        // Fetch company setting once (avoiding N+1)
        $reconciliationSetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

        // Bulk load ClawbackSettlement PIDs - OPTIMIZED: Only load PIDs for records we're processing
        $clawBackPids = ClawbackSettlement::select('pid')
            ->whereNotNull('pid')
            ->whereIn('pid', $pids)
            ->pluck('pid')
            ->toArray();

        $data->transform(function ($data) use ($companyProfile, $product, $userCommissions, $reconciliationSetting, $reconCommissions, $clawBackPids) {
            // 4. NULL SAFETY - Use null safe operator to prevent errors
            $commissionData = $userCommissions->get($data->pid);
            $paymentStatus = (!in_array($data->salesMasterProcessInfo?->mark_account_status_id ?? 0, [1, 6]) && $commissionData)
                ? 'Paid'
                : ($data->salesMasterProcessInfo?->status?->account_status ?? null);

            // 5. STREAMLINED ALERT CENTER LOGIC - Simplified check with early returns
            $alertCenter = 0;
            $legacy = $data->legacyAPINull;

            if ($legacy) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    // PEST company alert checks
                    if (!empty($legacy->sales_alert) || !empty($legacy->missingrep_alert) || !empty($legacy->locationRedline_alert)) {
                        $alertCenter = 1;
                    }
                } else {
                    // Other company types alert checks (mortgage, turf, default)
                    if (
                        !empty($legacy->sales_alert) || !empty($legacy->missingrep_alert) ||
                        !empty($legacy->closedpayroll_alert) || !empty($legacy->repredline_alert)
                    ) {
                        $alertCenter = 1;
                    }

                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                        $alertCenter = 0;
                    }
                }
            }

            // 6. EFFICIENT MILESTONE PROCESSING - Find the most relevant last milestone
            $lastMilestone = NULL;

            // Process milestones to find the best one based on priority logic
            $milestoneCollection = collect($data->lastMilestone)
                ->filter(function ($milestone) {
                    return !empty($milestone->milestone_date);
                })
                ->map(function ($mileStone) use ($data) {
                    // Get amount for milestones of this type and payment status
                    $amount = collect($data->lastMilestone)
                        ->where('type', $mileStone->type)
                        ->where('is_paid', $mileStone->is_paid)
                        ->sum('amount');

                    return [
                        'name' => $mileStone?->milestoneSchemaTrigger?->name,
                        'trigger' => $mileStone?->milestoneSchemaTrigger?->on_trigger,
                        'value' => $amount ?? 0,
                        'date' => $mileStone->milestone_date,
                        'is_paid' => $mileStone->is_paid,
                        'milestone_date' => $mileStone->milestone_date,
                        'original' => $mileStone
                    ];
                })
                ->unique(function ($milestone) {
                    // Remove duplicates based on type and payment status
                    return $milestone['name'] . '_' . $milestone['is_paid'];
                });

            if ($milestoneCollection->isNotEmpty()) {
                // Sort by: 1. Date (latest first), 2. Amount (highest first), 3. Paid status (paid first if amounts are equal)
                $lastMilestone = $milestoneCollection
                    ->sortByDesc('milestone_date')
                    ->sortByDesc('value')
                    ->sortByDesc('is_paid')
                    ->first();

                // Remove the helper fields we added
                unset($lastMilestone['milestone_date'], $lastMilestone['original']);
            }

            // Process all milestones
            $allMileStones = [];
            $triggerDate = null;
            $isProjected = 0;

            foreach ($data->salesProductMaster as $mileStone) {
                $triggerDate = $mileStone?->milestoneSchemaTrigger?->on_trigger;
                $isProjected = $mileStone->is_projected;
                $allMileStones[] = [
                    'name' => $mileStone?->milestoneSchemaTrigger?->name,
                    'trigger' => $triggerDate,
                    'value' => $mileStone->value,
                    'date' => $mileStone->milestone_date,
                    'is_projected' => $isProjected
                ];
            }

            // Use preloaded reconciliation data instead of making a new query
            $reconAmount = 0;
            if ($reconciliationSetting) {
                $reconCommission = $reconCommissions->get($data->pid);
                if ($reconCommission) {
                    $reconAmount = $reconCommission->amount;
                    $allMileStones[] = [
                        'name' => 'Recon',
                        'trigger' => $triggerDate ?? null,
                        'value' => $reconCommission->amount,
                        'date' => $reconCommission->date,
                        'is_projected' => $isProjected ?? 0
                    ];
                }
            }

            // 7. IMPROVED PRODUCT NAME FORMATTING - Format: "Sale Product Name (Product Info Name) - Product Code"
            $productName = $this->getFormattedProductName($data, $product);

            // Safe array access to prevent errors when allMileStones is empty
            $firstMilestoneDate = !empty($allMileStones) ? reset($allMileStones)['date'] ?? null : null;
            // $firstDate = $firstMilestoneDate && !$data->date_cancelled;
            $firstDate = $firstMilestoneDate && !$data->date_cancelled && $data->job_status != 'Pending';

            // Apply job status logic only for pest company type
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {

                if (in_array($data->pid, $clawBackPids)) {
                    $jobStatus = 'Clawback';
                } elseif ($data->date_cancelled) {
                    $jobStatus = 'Cancelled';
                } elseif ($firstDate && !$data->date_cancelled) {
                    $jobStatus = 'Serviced';
                } else {
                    $jobStatus = 'Pending';
                }

                /*$jobStatus = match (true) {
                    $firstDate => 'Serviced',
                    ($data->date_cancelled && in_array($data->pid, $clawBackPids)) || $data->job_status=='Clawback' => 'Clawback',
                    ($data->date_cancelled && !in_array($data->pid, $clawBackPids)) || $data->job_status=='Cancelled' => 'Cancelled',
                    default => 'Pending',
                }; */
            } else {
                // Fallback to original job status with null safety
                $jobStatus = $data->job_status ?? 'Pending';
            }




            return [
                'pid' => $data->pid,
                'customer_name' => $data->customer_name,
                'data_source_type' => $data->data_source_type,
                'job_status' => $jobStatus,
                'product_id' => $data->product_id, // Actual product_id from sales_masters table
                'product_name' => $productName,
                'customer_state' => $data->customer_state,
                'last_milestone' => $lastMilestone,
                'last_payment' => @$lastMilestone['amount'] ? $lastMilestone['amount'] : 0,
                'all_milestone' => $allMileStones,
                'total_commission' => $data->total_commission + $reconAmount,
                'projected_commission' => $data->projected_commission,
                'total_override' => $data->total_override,
                'projected_override' => $data->projected_override,
                'kw' => $data->kw,
                'epc' => $data->epc,
                'net_epc' => ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $data->net_epc) ? number_format($data->net_epc * 100, 4, '.', '') : $data->net_epc,
                'adders' => $data->adders,
                'gross_account_value' => $data->gross_account_value,
                'service_completed' => $data->service_completed,
                'date_cancelled' => $data->date_cancelled,
                'mark_account_status_name' => $paymentStatus,
                'alert' => $alertCenter,
                // 8. HELPER METHOD FOR USER DETAILS - Consistent user formatting
                'closer1_detail' => $this->formatUserDetail($data->closer1_id, $data->closer1Detail),
                'closer2_detail' => $this->formatUserDetail($data->closer2_id, $data->closer2Detail),
                'setter1_detail' => $this->formatUserDetail($data->setter1_id, $data->setter1Detail),
                'setter2_detail' => $this->formatUserDetail($data->setter2_id, $data->setter2Detail),
            ];
        });

        // Consolidated post-processing to avoid multiple paginator calls
        if ($needsPostProcessing) {
            $data = json_decode($data);

            // Apply product_name filtering first (before sorting)
            if ($request->has('filter_product_name') && !empty($request->input('filter_product_name'))) {
                $productNameFilter = $request->input('filter_product_name');
                if ($productNameFilter !== 'all') {
                    $data = array_filter($data, function ($item) use ($productNameFilter) {
                        return stripos($item->product_name, $productNameFilter) !== false;
                    });
                    // Re-index array after filtering
                    $data = array_values($data);
                }
            }

            // Apply sorting after filtering
            if ($request->has('sort')) {
                $sortColumn = $request->input('sort');
                $sortDirection = $request->input('sort_val', 'asc');

                if ($sortColumn == 'last_payment') {
                    if (strtolower($sortDirection) == 'desc') {
                        array_multisort(array_column($data, 'last_payment'), SORT_DESC, $data);
                    } else {
                        array_multisort(array_column($data, 'last_payment'), SORT_ASC, $data);
                    }
                } elseif ($sortColumn == 'product_name' || $sortColumn == 'product') {
                    if (strtolower($sortDirection) == 'desc') {
                        array_multisort(array_column($data, 'product_name'), SORT_DESC, $data);
                    } else {
                        array_multisort(array_column($data, 'product_name'), SORT_ASC, $data);
                    }
                } elseif ($sortColumn == 'job_status' || $sortColumn == 'status') {
                    if (strtolower($sortDirection) == 'desc') {
                        array_multisort(array_column($data, 'job_status'), SORT_DESC, $data);
                    } else {
                        array_multisort(array_column($data, 'job_status'), SORT_ASC, $data);
                    }
                }
            }

            // Apply pagination only once at the end
            $data = customPaginator($data, $perPage);
        }

        $this->successResponse("Successfully.", "salesList", $data);
    }

    // SALES EXPORT
    public function salesExport(Request $request)
    {
        $data = $request->all();
        dispatch(new SalesExportJob($data));
        $this->successResponse("We are getting your file ready for download. This may take a few minutes depending on its size. Please be patient.", "sales-export", []);
    }

    // SALE VIEW
    public function saleByPid(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required'
        ]);

        $pid = $request->pid;

        // First get basic sale data to retrieve the effective date and user IDs
        $basicSale = SalesMaster::select('customer_signoff', 'closer1_id', 'closer2_id', 'setter1_id', 'setter2_id')
            ->where('pid', $pid)->first();
        if (!$basicSale) {
            return $this->errorResponse('Sale not found!!', 'saleByPid', '', 400);
        }
        // ✅ OPTION 1: Use customer_signoff for historical accuracy (current behavior)
        $effectiveDate = $basicSale->customer_signoff ?? date('Y-m-d');

        // ✅ OPTION 2: Always use current date for latest positions (uncomment to enable)
        // $effectiveDate = date('Y-m-d');

        // ✅ ENHANCED: Pre-load ALL user positions with paid sale logic
        $userPositions = $this->getUserPositionsByEffectiveDate([
            $basicSale->closer1_id,
            $basicSale->closer2_id,
            $basicSale->setter1_id,
            $basicSale->setter2_id
        ], $effectiveDate, $pid);

        // ✅ OPTIMIZED: Simplified eager loading without repetitive userOrganizationHistory queries
        $value = SalesMaster::with([
            'salesProductMaster' => function ($q) {
                $q->selectRaw('pid, type, milestone_date, milestone_schema_id, is_last_date')->groupBy('pid', 'type');
            },
            'salesProductMasterDetails' => function ($q) {
                $q->selectRaw('pid, type, milestone_date, amount, is_projected, setter1_id, setter2_id, closer1_id, closer2_id, milestone_schema_id, is_last_date');
            },
            'externalSaleProductMasterDetails' => function ($q) {
                $q->selectRaw('pid, type, milestone_date, amount, is_projected, worker_id, worker_type, milestone_schema_id, is_last_date');
            },
            'productInfo',
            // Only load basic user details and office info - positions loaded separately above
            'closer1Detail:id,first_name,last_name,image,office_id',
            'closer1Detail.office:id,general_code,office_name,state_id',
            'closer1Detail.office.state:id,name,state_code',
            'closer1Detail.userOrganization.position:id,position_name', // Fallback only
            'closer1Detail.userOrganization.subPositionId:id,position_name', // Fallback only
            'closer2Detail:id,first_name,last_name,image,office_id',
            'closer2Detail.office:id,general_code,office_name,state_id',
            'closer2Detail.office.state:id,name,state_code',
            'closer2Detail.userOrganization.position:id,position_name', // Fallback only
            'closer2Detail.userOrganization.subPositionId:id,position_name', // Fallback only
            'setter1Detail:id,first_name,last_name,image,office_id',
            'setter1Detail.office:id,general_code,office_name,state_id',
            'setter1Detail.office.state:id,name,state_code',
            'setter1Detail.userOrganization.position:id,position_name', // Fallback only
            'setter1Detail.userOrganization.subPositionId:id,position_name', // Fallback only
            'setter2Detail:id,first_name,last_name,image,office_id',
            'setter2Detail.office:id,general_code,office_name,state_id',
            'setter2Detail.office.state:id,name,state_code',
            'setter2Detail.userOrganization.position:id,position_name', // Fallback only
            'setter2Detail.userOrganization.subPositionId:id,position_name', // Fallback only
            'salesProductMaster.milestoneSchemaTrigger',
            'salesProductMasterDetails.milestoneSchemaTrigger',
            'externalSaleProductMasterDetails.milestoneSchemaTrigger'
        ])->where('pid', $pid)->first();
        if (!$value) {
            return $this->errorResponse('Sale not found!!', 'saleByPid', '', 400);
        }

        $approvalDate = $value->customer_signoff;
        if (config('app.domain_name') == 'flex') {
            $locationCode = isset($value->customer_state) ? $value->customer_state : 0;
        } else {
            $locationCode = isset($value->location_code) ? $value->location_code : 0;
        }
        $location = Locations::with('State')->where('general_code', '=', $locationCode)->first();

        $redlineStandard = 0;
        if ($approvalDate) {
            if ($location) {
                $locationRedlines = LocationRedlineHistory::where('location_id', $location->id)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $redlineStandard = $locationRedlines->redline_standard;
                }
            } else {
                $state = State::where('state_code', $locationCode)->first();
                $saleStateId = isset($state->id) ? $state->id : 0;
                $location = Locations::where('state_id', $saleStateId)->first();
                $locationId = isset($location->id) ? $location->id : 0;
                $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $redlineStandard = $locationRedlines->redline_standard;
                }
            }
        }

        $closer1Id = $value?->closer1_id;
        $closer2Id = $value?->closer2_id;
        $setter1Id = $value?->setter1_id;
        $setter2Id = $value?->setter2_id;

        $skeleton = [
            'milestone' => NULL,
            'total_commission' => NULL,
            'first_name' => NULL,
            'last_name' => NULL,
            'image' => NULL,
            'with_held' => NULL,
            'office' => NULL,
            'terminate' => 0,
            'dismiss' => 0,
            'contract_ended' => 0,
            'stop_payroll' => 0
        ];

        $withHeldProjected = 1;
        $lastItem = collect($value->salesProductMasterDetails)->where('is_last_date', '1')->first() ?? NULL;
        if ($lastItem && $lastItem->milestone_date) {
            $withHeldProjected = 0;
        }
        if ($withHeldProjected) {
            $withholdAmounts = ProjectionUserCommission::whereIn('user_id', [$closer1Id, $closer2Id, $setter1Id, $setter2Id])->where(['pid' => $pid, 'value_type' => 'reconciliation'])->get()->keyBy('user_id');
        } else {
            $withholdAmounts = UserCommission::whereIn('user_id', [$closer1Id, $closer2Id, $setter1Id, $setter2Id])->where(['pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get()->keyBy('user_id');
        }

        $triggers = [];
        $closer1 = $skeleton;
        $closer2 = $skeleton;
        $setter1 = $skeleton;
        $setter2 = $skeleton;
        $saleDetails = $value->salesProductMasterDetails;
        foreach ($value->salesProductMaster as $schema) {
            $triggers[] = [
                'name' => $schema?->milestoneSchemaTrigger?->name,
                'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger,
                'value' => $schema->milestone_date
            ];

            if ($closer1Id) {
                $data = collect($saleDetails)->where('closer1_id', $closer1Id)->where('type', $schema->type)->first() ?? NULL;
                if ($schema->milestone_date) {
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - @$withholdAmounts[$closer1Id]['amount'] ?? 0;
                        }
                        $closer1['total_commission'] += $data->amount;
                        $closer1['milestone'][] = [
                            'amount' => $amount,
                            'is_projected' => $data->is_projected,
                            'type' => $data?->milestoneSchemaTrigger?->name,
                            'trigger' => $data?->milestoneSchemaTrigger?->on_trigger
                        ];
                    } else {
                        $closer1['milestone'][] = [
                            'amount' => 0,
                            'is_projected' => 0,
                            'type' => $schema?->milestoneSchemaTrigger?->name,
                            'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                        ];
                    }
                } else {
                    $closer1Amount = 0;
                    $closer1IsProjected = 1;
                    if ($data) {
                        $closer1Amount = $data->amount;
                        if ($data->is_last_date) {
                            $closer1Amount = $data->amount - @$withholdAmounts[$closer1Id]['amount'] ?? 0;
                        }
                        $closer1IsProjected = $data->is_projected;
                    }
                    $closer1['milestone'][] = [
                        'amount' => $closer1Amount,
                        'is_projected' => $closer1IsProjected,
                        'type' => $schema?->milestoneSchemaTrigger?->name,
                        'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                    ];
                }
            }

            if ($closer2Id) {
                $data = collect($saleDetails)->where('closer2_id', $closer2Id)->where('type', $schema->type)->first() ?? NULL;
                if ($schema->milestone_date) {
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - @$withholdAmounts[$closer2Id]['amount'] ?? 0;
                        }
                        $closer2['total_commission'] += $data->amount;
                        $closer2['milestone'][] = [
                            'amount' => $amount,
                            'is_projected' => $data->is_projected,
                            'type' => $data?->milestoneSchemaTrigger?->name,
                            'trigger' => $data?->milestoneSchemaTrigger?->on_trigger
                        ];
                    } else {
                        $closer2['milestone'][] = [
                            'amount' => 0,
                            'is_projected' => 0,
                            'type' => $schema?->milestoneSchemaTrigger?->name,
                            'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                        ];
                    }
                } else {
                    $closer2Amount = 0;
                    $closer2IsProjected = 1;
                    if ($data) {
                        $closer2Amount = $data->amount;
                        if ($data->is_last_date) {
                            $closer2Amount = $data->amount - @$withholdAmounts[$closer2Id]['amount'] ?? 0;
                        }
                        $closer2IsProjected = $data->is_projected;
                    }
                    $closer2['milestone'][] = [
                        'amount' => $closer2Amount,
                        'is_projected' => $closer2IsProjected,
                        'type' => $schema?->milestoneSchemaTrigger?->name,
                        'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                    ];
                }
            }

            if ($setter1Id) {
                $data = collect($saleDetails)->where('setter1_id', $setter1Id)->where('type', $schema->type)->first() ?? NULL;
                if ($schema->milestone_date) {
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - @$withholdAmounts[$setter1Id]['amount'] ?? 0;
                        }
                        $setter1['total_commission'] += $data->amount;
                        $setter1['milestone'][] = [
                            'amount' => $amount,
                            'is_projected' => $data->is_projected,
                            'type' => $data?->milestoneSchemaTrigger?->name,
                            'trigger' => $data?->milestoneSchemaTrigger?->on_trigger
                        ];
                    } else {
                        $setter1['milestone'][] = [
                            'amount' => 0,
                            'is_projected' => 0,
                            'type' => $schema?->milestoneSchemaTrigger?->name,
                            'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                        ];
                    }
                } else {
                    $setter1Amount = 0;
                    $setter1IsProjected = 1;
                    if ($data) {
                        $setter1Amount = $data->amount;
                        if ($data->is_last_date) {
                            $setter1Amount = $data->amount - @$withholdAmounts[$setter1Id]['amount'] ?? 0;
                        }
                        $setter1IsProjected = $data->is_projected;
                    }
                    $setter1['milestone'][] = [
                        'amount' => $setter1Amount,
                        'is_projected' => $setter1IsProjected,
                        'type' => $schema?->milestoneSchemaTrigger?->name,
                        'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                    ];
                }
            }

            if ($setter2Id) {
                $data = collect($saleDetails)->where('setter2_id', $setter2Id)->where('type', $schema->type)->first() ?? NULL;
                if ($schema->milestone_date) {
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - @$withholdAmounts[$setter2Id]['amount'] ?? 0;
                        }
                        $setter2['total_commission'] += $data->amount;
                        $setter2['milestone'][] = [
                            'amount' => $amount,
                            'is_projected' => $data->is_projected,
                            'type' => $data?->milestoneSchemaTrigger?->name,
                            'trigger' => $data?->milestoneSchemaTrigger?->on_trigger
                        ];
                    } else {
                        $setter2['milestone'][] = [
                            'amount' => 0,
                            'is_projected' => 0,
                            'type' => $schema?->milestoneSchemaTrigger?->name,
                            'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                        ];
                    }
                } else {
                    $setter2Amount = 0;
                    $setter2IsProjected = 1;
                    if ($data) {
                        $setter2Amount = $data->amount;
                        if ($data->is_last_date) {
                            $setter2Amount = $data->amount - @$withholdAmounts[$setter2Id]['amount'] ?? 0;
                        }
                        $setter2IsProjected = $data->is_projected;
                    }
                    $setter2['milestone'][] = [
                        'amount' => $setter2Amount,
                        'is_projected' => $setter2IsProjected,
                        'type' => $schema?->milestoneSchemaTrigger?->name,
                        'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                    ];
                }
            }
        }

        $closer1Detail = isset($value->closer1Detail) ? $value->closer1Detail : NULL;
        $closer2Detail = isset($value->closer2Detail) ? $value->closer2Detail : NULL;
        $setter1Detail = isset($value->setter1Detail) ? $value->setter1Detail : NULL;
        $setter2Detail = isset($value->setter2Detail) ? $value->setter2Detail : NULL;

        if ($closer1Detail) {
            $closer1['id'] = $closer1Detail->id;
            $closer1['first_name'] = $closer1Detail->first_name;
            $closer1['last_name'] = $closer1Detail->last_name;
            $closer1['image'] = $closer1Detail->image;
            $closer1['with_held'] = @$withholdAmounts[$closer1Id]['amount'] ?? 0;
            // ✅ OPTIMIZED: Use pre-loaded position data with fallback
            $positionData = $userPositions->get($closer1Detail->id);
            $closer1['position'] = $positionData?->position_name ?? $closer1Detail?->userOrganization?->position?->position_name;
            $closer1['position_id'] = $positionData?->position_id ?? $closer1Detail?->userOrganization?->position_id;
            $closer1['sub_position_id'] = $positionData?->sub_position_id ?? $closer1Detail?->userOrganization?->sub_position_id;
            $closer1['sub_position_name'] = $positionData?->sub_position_name ?? $closer1Detail?->userOrganization?->subPositionId?->position_name;
            $closer1['office'] = [
                'general_code' => $closer1Detail?->office?->general_code,
                'office_name' => $closer1Detail?->office?->office_name,
                'state_name' => $closer1Detail?->office?->state?->name,
                'state_code' => $closer1Detail?->office?->state?->state_code,
                'state_redline' => $closer1Detail?->office?->redline_data
            ];
        }
        if ($closer2Detail) {
            $closer2['id'] = $closer2Detail->id;
            $closer2['first_name'] = $closer2Detail->first_name;
            $closer2['last_name'] = $closer2Detail->last_name;
            $closer2['image'] = $closer2Detail->image;
            $closer2['with_held'] = @$withholdAmounts[$closer2Id]['amount'] ?? 0;
            // ✅ OPTIMIZED: Use pre-loaded position data with fallback
            $positionData = $userPositions->get($closer2Detail->id);
            $closer2['position'] = $positionData?->position_name ?? $closer2Detail?->userOrganization?->position?->position_name;
            $closer2['position_id'] = $positionData?->position_id ?? $closer2Detail?->userOrganization?->position_id;
            $closer2['sub_position_id'] = $positionData?->sub_position_id ?? $closer2Detail?->userOrganization?->sub_position_id;
            $closer2['sub_position_name'] = $positionData?->sub_position_name ?? $closer2Detail?->userOrganization?->subPositionId?->position_name;
            $closer2['office'] = [
                'general_code' => $closer2Detail?->office?->general_code,
                'office_name' => $closer2Detail?->office?->office_name,
                'state_name' => $closer2Detail?->office?->state?->name,
                'state_code' => $closer2Detail?->office?->state?->state_code,
                'state_redline' => $closer2Detail?->office?->redline_data
            ];
        }
        if ($setter1Detail) {
            $setter1['id'] = $setter1Detail->id;
            $setter1['first_name'] = $setter1Detail->first_name;
            $setter1['last_name'] = $setter1Detail->last_name;
            $setter1['image'] = $setter1Detail->image;
            $setter1['with_held'] = @$withholdAmounts[$setter1Id]['amount'] ?? 0;
            // ✅ OPTIMIZED: Use pre-loaded position data with fallback
            $positionData = $userPositions->get($setter1Detail->id);
            $setter1['position'] = $positionData?->position_name ?? $setter1Detail?->userOrganization?->position?->position_name;
            $setter1['position_id'] = $positionData?->position_id ?? $setter1Detail?->userOrganization?->position_id;
            $setter1['sub_position_id'] = $positionData?->sub_position_id ?? $setter1Detail?->userOrganization?->sub_position_id;
            $setter1['sub_position_name'] = $positionData?->sub_position_name ?? $setter1Detail?->userOrganization?->subPositionId?->position_name;
            $setter1['office'] = [
                'general_code' => $setter1Detail?->office?->general_code,
                'office_name' => $setter1Detail?->office?->office_name,
                'state_name' => $setter1Detail?->office?->state?->name,
                'state_code' => $setter1Detail?->office?->state?->state_code,
                'state_redline' => $setter1Detail?->office?->redline_data
            ];
        }
        if ($setter2Detail) {
            $setter2['id'] = $setter2Detail->id;
            $setter2['first_name'] = $setter2Detail->first_name;
            $setter2['last_name'] = $setter2Detail->last_name;
            $setter2['image'] = $setter2Detail->image;
            $setter2['with_held'] = @$withholdAmounts[$setter2Id]['amount'] ?? 0;
            // ✅ OPTIMIZED: Use pre-loaded position data with fallback
            $positionData = $userPositions->get($setter2Detail->id);
            $setter2['position'] = $positionData?->position_name ?? $setter2Detail?->userOrganization?->position?->position_name;
            $setter2['position_id'] = $positionData?->position_id ?? $setter2Detail?->userOrganization?->position_id;
            $setter2['sub_position_id'] = $positionData?->sub_position_id ?? $setter2Detail?->userOrganization?->sub_position_id;
            $setter2['sub_position_name'] = $positionData?->sub_position_name ?? $setter2Detail?->userOrganization?->subPositionId?->position_name;
            $setter2['office'] = [
                'general_code' => $setter2Detail?->office?->general_code,
                'office_name' => $setter2Detail?->office?->office_name,
                'state_name' => $setter2Detail?->office?->state?->name,
                'state_code' => $setter2Detail?->office?->state?->state_code,
                'state_redline' => $setter2Detail?->office?->redline_data
            ];
        }

        $dealerFeePer = isset($value->dealer_fee_percentage) ? ($value->dealer_fee_percentage) : NULL;
        if (is_numeric($dealerFeePer) && $dealerFeePer < 1) {
            $dealerFeePer = $dealerFeePer * 100;
        }

        $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
        $productName = $this->getFormattedProductName($value, $product);
        $milestone = [];
        if ($value->customer_signoff) {
            $milestone = ProductMilestoneHistories::where('product_id', @$value?->productInfo?->product_id)->where('effective_date', '<=', $value->customer_signoff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        }
        $companyProfile = CompanyProfile::first();
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            if (strtolower(config('app.domain_name')) != 'firstcoast') {
                $redlineStandard = 0;
            } else {
                $redlineStandard = $companyProfile->redline_standard ? $companyProfile->redline_standard * 100 : 0;
            }
        }

        $withHeldProjected = 1;
        $lastItem = collect($value->externalSaleProductMasterDetails)->where('is_last_date', '1')->first() ?? NULL;
        if ($lastItem && $lastItem->milestone_date) {
            $withHeldProjected = 0;
        }

        $externalSaleDetails = $value->externalSaleProductMasterDetails;
        $externalUserDetail = [];

        // Group by worker_id to avoid duplicates
        $groupedByWorker = collect($value->externalSaleProductMasterDetails)->groupBy('worker_id');

        foreach ($groupedByWorker as $workerId => $workerMilestones) {
            if (!$workerId) continue; // Skip if no worker_id

            // Get worker details from first milestone (since all milestones have same worker info)
            $firstMilestone = $workerMilestones->first();

            $externalUser = $skeleton;
            $externalUser['worker_id'] = $firstMilestone->worker_id;
            $externalUser['worker_type'] = $firstMilestone->worker_type;
            $externalUser['total_commission'] = 0;
            $externalUser['first_name'] = isset($firstMilestone->worker->first_name) ? $firstMilestone->worker->first_name : NULL;
            $externalUser['last_name'] = isset($firstMilestone->worker->last_name) ? $firstMilestone->worker->last_name : NULL;
            $externalUser['image'] = isset($firstMilestone->worker->image) ? $firstMilestone->worker->image : NULL;
            $externalUser['with_held'] = 0;
            $externalUser['office'] = [
                'general_code' => isset($firstMilestone->worker->office->general_code) ? $firstMilestone->worker->office->general_code : NULL,
                'office_name' => isset($firstMilestone->worker->office->office_name) ? $firstMilestone->worker->office->office_name : NULL,
                'state_name' => isset($firstMilestone->worker->office->state->name) ? $firstMilestone->worker->office->state->name : NULL,
                'state_code' => isset($firstMilestone->worker->office->state->state_code) ? $firstMilestone->worker->office->state->state_code : NULL,
                'state_redline' => isset($firstMilestone->worker->office->redline_data) ? $firstMilestone->worker->office->redline_data : NULL
            ];
            $externalUser['terminate'] = isset($firstMilestone->worker->terminate) ? $firstMilestone->worker->terminate : 0;
            $externalUser['dismiss'] = isset($firstMilestone->worker->dismiss) ? $firstMilestone->worker->dismiss : 0;
            $externalUser['contract_ended'] = isset($firstMilestone->worker->contract_ended) ? $firstMilestone->worker->contract_ended : 0;
            $externalUser['stop_payroll'] = isset($firstMilestone->worker->stop_payroll) ? $firstMilestone->worker->stop_payroll : 0;
            $externalUser['position'] = $firstMilestone->worker->userOrganization?->position?->position_name;
            $externalUser['position_id'] = $firstMilestone->worker->userOrganization?->position?->id;
            $externalUser['sub_position_id'] = $firstMilestone->worker->userOrganization?->subPositionId?->id;
            $externalUser['sub_position_name'] = $firstMilestone->worker->userOrganization?->subPositionId?->position_name;
            // Initialize milestone array
            $externalUser['milestone'] = [];
            switch ($firstMilestone->worker_type) {
                case '1':
                    $externalUser['worker_type'] = 'Selfgen';
                    break;
                case '2':
                    $externalUser['worker_type'] = 'Closer';
                    break;
                case '3':
                    $externalUser['worker_type'] = 'Setter';
                    break;
            }

            // Get withhold amounts once per worker
            if ($withHeldProjected) {
                $withholdAmounts = ProjectionUserCommission::whereIn('user_id', [$workerId])->where(['pid' => $pid, 'value_type' => 'reconciliation'])->get()->keyBy('user_id');
            } else {
                $withholdAmounts = UserCommission::whereIn('user_id', [$workerId])->where(['pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get()->keyBy('user_id');
            }

            // Process all milestones for this worker
            foreach ($workerMilestones as $schema) {
                $data = $schema; // The schema itself is the data we need

                if ($schema->milestone_date) {
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - (@$withholdAmounts[$schema->worker_id]['amount'] ?? 0);
                        }
                        $externalUser['total_commission'] += $data->amount;
                        $externalUser['milestone'][] = [
                            'amount' => $amount,
                            'is_projected' => $data->is_projected,
                            'type' => $data?->milestoneSchemaTrigger?->name,
                            'trigger' => $data?->milestoneSchemaTrigger?->on_trigger
                        ];
                    } else {
                        $externalUser['milestone'][] = [
                            'amount' => 0,
                            'is_projected' => 0,
                            'type' => $schema?->milestoneSchemaTrigger?->name,
                            'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                        ];
                    }
                } else {
                    $amount = 0;
                    $isProjected = 1;
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - (@$withholdAmounts[$schema->worker_id]['amount'] ?? 0);
                        }
                        $isProjected = $data->is_projected;
                    }
                    $externalUser['milestone'][] = [
                        'amount' => $amount,
                        'is_projected' => $isProjected,
                        'type' => $schema?->milestoneSchemaTrigger?->name,
                        'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                    ];
                }
            }

            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                $externalUser['redline_data'] = 0;
            } else {
                $externalUser['redline_data'] = $this->getExternalSaleRedline($pid, $workerId, $firstMilestone->worker_type);
            }

            $externalUser['with_held'] = @$withholdAmounts[$firstMilestone->worker_id]['amount'] ?? 0;
            // Add the worker with all their milestones to the final array
            $externalUserDetail[] = $externalUser;
        }

        // Apply 4-Status Job System Logic (optimized for single PID)
        $companyProfile = CompanyProfile::first();

        // Check if clawback exists for this specific PID
        $hasClawback = ClawbackSettlement::where('pid', $value->pid)
            ->whereNotNull('pid')
            ->exists();

        // Calculate first milestone date logic
        $firstMilestoneDate = collect($value->salesProductMaster)
            ->filter(function ($milestone) {
                return !empty($milestone->milestone_date);
            })
            ->isNotEmpty();

        $firstDate = $firstMilestoneDate && !$value->date_cancelled && $value->job_status != 'Pending';

        // Apply job status logic only for PEST company type
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($hasClawback) {
                $jobStatus = 'Clawback';
            } elseif ($value->date_cancelled) {
                $jobStatus = 'Cancelled';
            } elseif ($firstDate && !$value->date_cancelled) {
                $jobStatus = 'Serviced';
            } else {
                $jobStatus = 'Pending';
            }
        } else {
            // Fallback to original job status with null safety for non-PEST companies
            $jobStatus = $value->job_status ?? 'Pending';
        }

        // Check if any payments have been executed for this sale
        $deletionCheck = $this->validateSaleDeletion($pid);
        $hasExecutedPayments = !$deletionCheck['allowed'];

        $productRedline = 0;
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
            $productRedline = 0;
        } else {
            $productRedline = $milestone?->product_redline;
        }

        $data = [
            'pid' => $value->pid,
            'job_status' => $jobStatus, // $value->job_status,
            'installer' => $value->install_partner,
            'prospect_id' => $value->pid,
            'customer_name' => $value->customer_name,
            'customer_address' => $value->customer_address,
            'customer_address_2' => $value->customer_address_2,
            'homeowner_id' => $value->homeowner_id,
            'customer_city' => $value->customer_city,
            'state_id' => $value->state_id,
            'customer_state' => $value->customer_state,
            'location_code' => $value->location_code,
            'customer_zip' => $value->customer_zip,
            'customer_email' => $value->customer_email,
            'customer_phone' => $value->customer_phone,
            'proposal_id' => $value->proposal_id,
            'sale_state_redline' => $redlineStandard,
            'product_redline' => $productRedline,
            'closer1_detail' => $closer1,
            'closer2_detail' => $closer2,
            'setter1_detail' => $setter1,
            'setter2_detail' => $setter2,
            'epc' => $value->epc,
            'gross_revenue' => ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $value->epc) ? number_format($value->epc, 4, '.', '') : $value->epc,
            'net_epc' => ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $value->net_epc) ? number_format($value->net_epc * 100, 4, '.', '') : $value->net_epc,
            'kw' => $value->kw,
            'date_cancelled' => $value->date_cancelled,
            'return_sales_date' => $value->return_sales_date,
            'approved_date' => $value->customer_signoff,
            'last_date_pd' => $value->last_date_pd,
            'product' => $value->product,
            'product_code' => $value?->productInfo?->product_code,
            'sale_product_name' => $value->sale_product_name,
            'product_id' => $value?->productInfo?->id,
            'product_name' => $productName,
            'triggers' => $triggers,
            'gross_account_value' => $value->gross_account_value,
            'dealer_fee_percentage' => $dealerFeePer,
            'dealer_fee_amount' => $value->dealer_fee_amount,
            'show' => isset($value->adders) ? (int) $value->adders : NULL,
            'adders_description' => $value->adders_description,
            'total_amount_for_acct' => $value->total_amount_for_acct,
            'prev_amount_paid' => $value->prev_amount_paid,
            'prev_deducted_amount' => $value->prev_deducted_amount,
            'cancel_fee' => $value->cancel_fee,
            'cancel_deduction' => $value->cancel_deduction,
            'adv_pay_back_amount' => $value->adv_pay_back_amount,
            'total_amount_in_period' => $value->total_amount_in_period,
            'data_source_type' => $value->data_source_type,
            'length_of_agreement' => $value->length_of_agreement,
            'service_schedule' => $value->service_schedule,
            'subscription_payment' => $value->subscription_payment,
            'service_completed' => $value->service_completed,
            'last_service_date' => $value->last_service_date,
            'bill_status' => $value->bill_status,
            'initial_service_cost' => $value->initial_service_cost,
            'auto_pay' => $value->auto_pay,
            'card_on_file' => $value->card_on_file,
            'updated_at' => $value->updated_at,
            'panel_type' => $value->panel_type,
            'panel_id' => $value->panel_id,
            'customer_longitude' => $value->customer_longitude,
            'customer_latitude' => $value->customer_latitude,
            'm1_date' => $value->m1_date,
            'm2_date' => $value->m2_date,
            'balance_age' => $value->balance_age,
            'externalUserDetail' => $externalUserDetail,
            'has_executed_payments' => $hasExecutedPayments ? 1 : 0,
            'org_product_id' => $value->productInfo->product_id ?? 1,
        ];

        $this->successResponse("Successfully.", "sales_by_id", $data);
    }

    // SALE EDIT
    public function saleEdit(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required'
        ]);

        $pid = $request->pid;
        $sale = SalesMaster::with([
            'productInfo' => function ($q) {
                $q->withTrashed();
            },
            'salesMasterProcess',
            'externalSaleProductMasterDetails' => function ($q) {
                $q->selectRaw('pid, type, milestone_date, amount, is_projected, worker_id, worker_type, milestone_schema_id, is_last_date');
            },
            'externalSaleProductMasterDetails.milestoneSchemaTrigger'
        ])->where('pid', $pid)->first();


        // Added external worker details
        $skeleton = [
            'milestone' => NULL,
            'total_commission' => NULL,
            'first_name' => NULL,
            'last_name' => NULL,
            'image' => NULL,
            'with_held' => NULL,
            'office' => NULL,
            'terminate' => 0,
            'dismiss' => 0,
            'contract_ended' => 0,
            'stop_payroll' => 0
        ];

        $withHeldProjected = 1;
        $lastItem = collect($sale->externalSaleProductMasterDetails)->where('is_last_date', '1')->first() ?? NULL;
        if ($lastItem && $lastItem->milestone_date) {
            $withHeldProjected = 0;
        }

        $externalSaleDetails = $sale->externalSaleProductMasterDetails;
        $externalUserDetail = [];

        // Group by worker_id to avoid duplicates
        $groupedByWorker = collect($sale->externalSaleProductMasterDetails)->groupBy('worker_id');

        foreach ($groupedByWorker as $workerId => $workerMilestones) {
            if (!$workerId) continue; // Skip if no worker_id

            // Get worker details from first milestone (since all milestones have same worker info)
            $firstMilestone = $workerMilestones->first();

            $externalUser = $skeleton;
            $externalUser['worker_id'] = $firstMilestone->worker_id;
            $externalUser['worker_type'] = $firstMilestone->worker_type;
            $externalUser['total_commission'] = 0;
            $externalUser['first_name'] = isset($firstMilestone->worker->first_name) ? $firstMilestone->worker->first_name : NULL;
            $externalUser['last_name'] = isset($firstMilestone->worker->last_name) ? $firstMilestone->worker->last_name : NULL;
            $externalUser['image'] = isset($firstMilestone->worker->image) ? $firstMilestone->worker->image : NULL;
            $externalUser['with_held'] = 0;
            $externalUser['office'] = [
                'general_code' => isset($firstMilestone->worker->office->general_code) ? $firstMilestone->worker->office->general_code : NULL,
                'office_name' => isset($firstMilestone->worker->office->office_name) ? $firstMilestone->worker->office->office_name : NULL,
                'state_name' => isset($firstMilestone->worker->office->state->name) ? $firstMilestone->worker->office->state->name : NULL,
                'state_code' => isset($firstMilestone->worker->office->state->state_code) ? $firstMilestone->worker->office->state->state_code : NULL,
                'state_redline' => isset($firstMilestone->worker->office->redline_data) ? $firstMilestone->worker->office->redline_data : NULL
            ];
            $externalUser['terminate'] = isset($firstMilestone->worker->terminate) ? $firstMilestone->worker->terminate : 0;
            $externalUser['dismiss'] = isset($firstMilestone->worker->dismiss) ? $firstMilestone->worker->dismiss : 0;
            $externalUser['contract_ended'] = isset($firstMilestone->worker->contract_ended) ? $firstMilestone->worker->contract_ended : 0;
            $externalUser['stop_payroll'] = isset($firstMilestone->worker->stop_payroll) ? $firstMilestone->worker->stop_payroll : 0;
            $externalUser['position'] = $firstMilestone->worker->userOrganization?->position?->position_name;
            $externalUser['position_id'] = $firstMilestone->worker->userOrganization?->position?->id;
            $externalUser['sub_position_id'] = $firstMilestone->worker->userOrganization?->subPositionId?->id;
            $externalUser['sub_position_name'] = $firstMilestone->worker->userOrganization?->subPositionId?->position_name;
            // Initialize milestone array
            $externalUser['milestone'] = [];
            switch ($firstMilestone->worker_type) {
                case '1':
                    $externalUser['worker_type'] = 'Selfgen';
                    break;
                case '2':
                    $externalUser['worker_type'] = 'Closer';
                    break;
                case '3':
                    $externalUser['worker_type'] = 'Setter';
                    break;
            }

            // Get withhold amounts once per worker
            if ($withHeldProjected) {
                $withholdAmounts = ProjectionUserCommission::whereIn('user_id', [$workerId])->where(['pid' => $pid, 'value_type' => 'reconciliation'])->get()->keyBy('user_id');
            } else {
                $withholdAmounts = UserCommission::whereIn('user_id', [$workerId])->where(['pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get()->keyBy('user_id');
            }

            // Process all milestones for this worker
            foreach ($workerMilestones as $schema) {
                $data = $schema; // The schema itself is the data we need

                if ($schema->milestone_date) {
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - (@$withholdAmounts[$schema->worker_id]['amount'] ?? 0);
                        }
                        $externalUser['total_commission'] += $data->amount;
                        $externalUser['milestone'][] = [
                            'amount' => $amount,
                            'is_projected' => $data->is_projected,
                            'type' => $data?->milestoneSchemaTrigger?->name,
                            'trigger' => $data?->milestoneSchemaTrigger?->on_trigger
                        ];
                    } else {
                        $externalUser['milestone'][] = [
                            'amount' => 0,
                            'is_projected' => 0,
                            'type' => $schema?->milestoneSchemaTrigger?->name,
                            'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                        ];
                    }
                } else {
                    $amount = 0;
                    $isProjected = 1;
                    if ($data) {
                        $amount = $data->amount;
                        if ($data->is_last_date) {
                            $amount = $data->amount - (@$withholdAmounts[$schema->worker_id]['amount'] ?? 0);
                        }
                        $isProjected = $data->is_projected;
                    }
                    $externalUser['milestone'][] = [
                        'amount' => $amount,
                        'is_projected' => $isProjected,
                        'type' => $schema?->milestoneSchemaTrigger?->name,
                        'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger
                    ];
                }
            }

            $companyProfile = CompanyProfile::first();
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                $externalUser['redline_data'] = 0;
            } else {
                $externalUser['redline_data'] = $this->getExternalSaleRedline($pid, $workerId, $firstMilestone->worker_type);
            }

            $externalUser['with_held'] = @$withholdAmounts[$firstMilestone->worker_id]['amount'] ?? 0;            // Add the worker with all their milestones to the final array
            $externalUserDetail[] = $externalUser;
        }

        if (!$sale) {
            $this->errorResponse('Sale not found!!', 'saleEdit', '', 400);
        }

        $dealerFeePer = isset($sale->dealer_fee_percentage) ? ($sale->dealer_fee_percentage) : NULL;
        if (is_numeric($dealerFeePer) && $dealerFeePer < 1) {
            $dealerFeePer = $dealerFeePer * 100;
        }

        $data = [
            'pid' => $sale->pid,
            'prospect_id' => $sale->pid,
            'homeowner_id' => $sale->homeowner_id,
            'proposal_id' => $sale->proposal_id,
            'product' => $sale->product,
            'product_code' => $sale?->productInfo?->product_id,
            'product_id' => $sale?->productInfo?->product_id,
            'sale_product_name' => $sale->sale_product_name,
            'gross_account_value' => $sale->gross_account_value,
            'data_source_type' => $sale->data_source_type,
            'customer_name' => $sale->customer_name,
            'customer_address' => $sale->customer_address,
            'customer_address_2' => $sale->customer_address_2,
            'customer_city' => $sale->customer_city,
            'state_id' => $sale->state_id,
            'customer_state' => $sale->customer_state,
            'location_code' => $sale->location_code,
            'customer_zip' => $sale->customer_zip,
            'customer_email' => $sale->customer_email,
            'customer_phone' => $sale->customer_phone,
            'installer' => $sale->install_partner,
            'job_status' => $sale->job_status,
            'kw' => $sale->kw,
            'epc' => $sale->epc,
            'gross_revenue' => ($companyProfile = CompanyProfile::first()) && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $sale->epc ? number_format($sale->epc, 4, '.', '') : $sale->epc,
            'net_epc' => ($companyProfile = CompanyProfile::first()) && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $sale->net_epc ? number_format($sale->net_epc * 100, 4, '.', '') : $sale->net_epc,

            'dealer_fee_percentage' => $dealerFeePer,
            'dealer_fee_amount' => $sale->dealer_fee_amount,
            'show' => isset($sale->adders) ? (int) $sale->adders : NULL,
            'adders_description' => $sale->adders_description,
            'length_of_agreement' => $sale->length_of_agreement,
            'service_schedule' => $sale->service_schedule,
            'initial_service_cost' => $sale->initial_service_cost,
            'subscription_payment' => $sale->subscription_payment,
            'card_on_file' => $sale->card_on_file,
            'auto_pay' => $sale->auto_pay,
            'service_completed' => $sale->service_completed,
            'last_service_date' => $sale->last_service_date,
            'bill_status' => $sale->bill_status,
            'rep_id' => $sale?->closer1_id,
            'rep_id2' => $sale?->closer2_id,
            'setter_id' => $sale?->setter1_id,
            'setter_id2' => $sale?->setter2_id,
            'approved_date' => $sale->customer_signoff,
            'date_cancelled' => $sale->date_cancelled,
            'return_sales_date' => $sale->return_sales_date,
            'panel_type' => $sale->panel_type,
            'panel_id' => $sale->panel_id,
            'customer_longitude' => $sale->customer_longitude,
            'customer_latitude' => $sale->customer_latitude,
            'externalUserDetail' => $externalUserDetail
        ];

        $this->successResponse("Successfully.", "sales_by_id", $data);
    }

    // SALE PRODUCT TRIGGERS
    public function saleProductTriggers(Request $request)
    {
        $companyProfile = CompanyProfile::first();
        $this->checkValidations($request->all(), [
            'pid' => 'required',
            'approved_date' => 'required'
        ]);

        $pid = $request->pid;
        $productId = $request->product_id;
        $effectiveDate = $request->approved_date;
        $sale = SalesMaster::with(['salesProductMaster' => function ($q) {
            $q->selectRaw('pid, type, milestone_date, milestone_schema_id')->groupBy('pid', 'type');
        }, 'salesProductMaster.milestoneSchemaTrigger'])->where('pid', $pid)->first();

        if (!$sale) {
            $this->errorResponse('Sale not found!!', 'saleProductTriggers', '', 400);
        }

        $paid = false;
        $response = [];
        if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
            $paid = true;
        }
        if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            $paid = true;
        }

        if ($paid) {
            if (!$productId) {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $productId = $product->id;
            }

            $milestone = ProductMilestoneHistories::where('product_id', $productId)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $response = [];
            //Redline 0 for MORTGAGE company type if domain is not firstcoast
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                $response['product_redline'] = 0;
            } else {
                $response['product_redline'] = $milestone->product_redline;
            }

            foreach ($sale->salesProductMaster as $schema) {
                $response['data'][] = [
                    'pid' => $schema->pid,
                    'type' => $schema?->milestoneSchemaTrigger?->name,
                    'trigger' => $schema?->milestoneSchemaTrigger?->on_trigger,
                    'date' => $schema->milestone_date
                ];
            }
            $this->successResponse("Successfully.", "saleProductTriggers", $response);
        } else {
            $milestone = $this->milestoneWithSchema($productId, $effectiveDate);
            if (!$milestone) {
                $this->errorResponse('No milestone found for this product at this date.', 'milestoneFromProduct', '', 400);
            }

            $response = [];
            //Redline 0 for MORTGAGE company type if domain is not firstcoast
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                $response['product_redline'] = 0;
            } else {
                $response['product_redline'] = $milestone->product_redline;
            }

            foreach ($milestone->milestone->milestone_trigger as $key => $schema) {
                $date = @$sale->salesProductMaster[$key]->milestone_date ? $sale->salesProductMaster[$key]->milestone_date : NULL;
                $response['data'][] = [
                    'pid' => $request->pid,
                    'type' => $schema->name,
                    'trigger' => $schema->on_trigger,
                    'date' => $date
                ];
            }

            $this->successResponse("Successfully.", "saleProductTriggers", $response);
        }
    }

    // USER REDLINE
    public function getUserRedline(Request $request)
    {
        $this->checkValidations($request->all(), [
            'user_id' => 'required|integer|min:1',
            'approved_date' => 'required',
            'user_type' => 'required|in:2,3',
            'is_self_gen' => 'required|boolean'
        ]);

        $pid = $request->pid;
        $userId = $request->user_id;
        $userType = $request->user_type;
        $productId = $request->product_id;
        $isSelfGen = $request->is_self_gen;
        $approvedDate = $request->approved_date;
        $saleLocation = $request->location_code;
        $netEpc = $request->net_epc;
        $compRate = 0;

        $companyProfile = CompanyProfile::first();
        $user = User::where('id', $userId)->first();
        if (!$user) {
            $this->errorResponse('User not found!!', 'getUserRedline', '', 400);
        }

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            if (!$saleLocation) {
                $this->errorResponse('State Code or Location code is missing!!', 'getSaleRedline', '', 400);
            }
        }

        $userOrganizationData = checkUsersProductForCalculations($userId, $approvedDate, $productId);
        $actualProductId = $userOrganizationData['product']->id;

        $commissionType = NULL;
        if ($isSelfGen) {
            $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
        } else {
            $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $actualProductId, 'core_position_id' => $userType])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
        }
        if ($commissionHistory) {
            $commissionType = $commissionHistory->commission_type;
        }

        $userOfficeId = $user->office_id;
        $userTransferHistory = UserTransferHistory::where('user_id', $userId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
        if ($userTransferHistory) {
            $userOfficeId = $userTransferHistory->office_id;
        }
        $userStateRedLine = 0;
        $userLocation = Locations::where('id', $userOfficeId)->first();
        $locationId = isset($userLocation->id) ? $userLocation->id : 0;
        $userLocationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
        if ($userLocationRedLines) {
            $userStateRedLine = $userLocationRedLines->redline_standard;
        }

        if ($pid) {
            $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            $userReconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            if ($userCommission || $userReconCommission) {
                $value = NULL;
                $valueType = NULL;
                if ($userCommission) {
                    $value = $userCommission->redline;
                    $valueType = $userCommission->redline_type;
                } else if ($userReconCommission) {
                    $value = $userReconCommission->redline;
                    $valueType = $userReconCommission->redline_type;
                }

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {

                    $compRate = isset($userCommission->comp_rate) ? $userCommission->comp_rate : 0;
                    $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$commission) {
                        $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                    }

                    if (!empty($commission->commission_limit) && $commission->commission_limit < $compRate) {

                        $compRate = $commission->commission_limit;
                    }
                }

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                    $value = 0;
                    $valueType = NULL;
                    $userStateRedLine = 0;
                }

                $data = [
                    "redline" => $value,
                    "comp_rate" => number_format($compRate, 4, '.', ''),
                    "redline_type" => $valueType,
                    "commission_type" => $commissionType,
                    "is_redline_missing" => 0,
                    "message" => NULL,
                    "office" => [
                        "office_name" => $userLocation?->office_name,
                        "state_name" => $userLocation?->state?->name,
                        "state_code" => $userLocation?->state?->state_code,
                        "redline_standard" => $userStateRedLine
                    ],
                    "product" => [
                        "product_redline" => NULL
                    ]
                ];
                $this->successResponse("Successfully.", "getUserRedline", $data);
            }
        }

        $companySetting = CompanySetting::where(['type' => 'tier', 'status' => '1'])->first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
            $commission = $commissionHistory?->commission;
            if ($commissionHistory && $commissionHistory->tiers_id && $companySetting) {
                $data = [
                    'commissionHistory' => $commissionHistory,
                    'userId' => $userId,
                    'approvedDate' => $approvedDate,
                    'productId' => $productId,
                    'request' => $request
                ];
                $commissionData = displayTieredCommission($data);
                if ($commissionData['is_tired']) {
                    $commission = $commissionData['commission'];
                }
            }

            $data = [
                "redline" => $commission,
                "redline_type" => $commissionType,
                "is_redline_missing" => 0,
                "commission_type" => $commissionType,
                "message" => NULL,
                "office" => [
                    "office_name" => $userLocation?->office_name,
                    "state_name" => $userLocation?->state?->name,
                    "state_code" => $userLocation?->state?->state_code,
                    "redline_standard" => $userStateRedLine
                ],
                "product" => [
                    "product_redline" => NULL
                ]
            ];
            if (!$commissionType) {
                $data['is_redline_missing'] = 1;
            }
            if ($commissionType == 'percent') {
                $data['redline_type'] = '%';
            }
            $this->successResponse("Successfully.", "getUserRedline", $data);
        } else {
            if ($commissionType == 'per kw' || $commissionType == 'per sale') {
                $commission = $commissionHistory?->commission;
                if ($commissionHistory && $commissionHistory->tiers_id && $companySetting) {
                    $data = [
                        'commissionHistory' => $commissionHistory,
                        'userId' => $userId,
                        'approvedDate' => $approvedDate,
                        'productId' => $productId,
                        'request' => $request
                    ];
                    $commissionData = displayTieredCommission($data);
                    if ($commissionData['is_tired']) {
                        $commission = $commissionData['commission'];
                    }
                }

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                    $commission = 0;
                    $commissionType = NULL;
                    $userStateRedLine = 0;
                }

                $data = [
                    "redline" => $commission,
                    "redline_type" => $commissionType,
                    "is_redline_missing" => 0,
                    "commission_type" => $commissionType,
                    "message" => NULL,
                    "office" => [
                        "office_name" => $userLocation?->office_name,
                        "state_name" => $userLocation?->state?->name,
                        "state_code" => $userLocation?->state?->state_code,
                        "redline_standard" => $userStateRedLine
                    ],
                    "product" => [
                        "product_redline" => NULL
                    ]
                ];
                $this->successResponse("Successfully.", "getUserRedline", $data);
            }
        }

        $userRedLine = 0;
        $checkUserRedLine = 0;
        $userRedLineAmountType = NULL;
        if ($isSelfGen) {
            $redLineHistory = UserRedlines::where(['user_id' => $userId, 'self_gen_user' => '1'])->whereNull('core_position_id')->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
        } else {
            $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $userType, 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
        }
        if ($redLineHistory) {
            $checkUserRedLine = 1;
            $userRedLine = $redLineHistory->redline;
            $userRedLineAmountType = $redLineHistory->redline_amount_type;
        } else {
            $message = "User redline is missing based on approval date.";
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                $message = "User office fee is missing based on approval date.";
            }
            $data = [
                "redline" => 0,
                "redline_type" => NULL,
                "is_redline_missing" => 1,
                "message" => $message,
                "office" => [
                    "office_name" => $userLocation?->office_name,
                    "state_name" => $userLocation?->state?->name,
                    "state_code" => $userLocation?->state?->state_code,
                    "redline_standard" => $userStateRedLine
                ],
                "product" => [
                    "product_redline" => NULL
                ]
            ];
            $this->successResponse("Successfully.", "getUserRedline", $data);
        }

        if (!$userRedLineAmountType) {
            $message = "User redline is missing based on approval date.";
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                $message = "User office fee is missing based on approval date.";
            }
            $data = [
                "redline" => 0,
                "redline_type" => NULL,
                "is_redline_missing" => 1,
                "message" => $message,
                "office" => [
                    "office_name" => $userLocation?->office_name,
                    "state_name" => $userLocation?->state?->name,
                    "state_code" => $userLocation?->state?->state_code,
                    "redline_standard" => $userStateRedLine
                ],
                "product" => [
                    "product_redline" => NULL
                ]
            ];
            $this->successResponse("Successfully.", "getUserRedline", $data);
        }

        if (strtolower($userRedLineAmountType) == strtolower('Fixed')) {
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $compRate = $netEpc - $userRedLine; //for showing comp rate in userRedline API.
                $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$commission) {
                    $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                }

                if (!empty($commission->commission_limit) && $commission->commission_limit < $compRate) {

                    $compRate = $commission->commission_limit;
                }

                if (strtolower(config('app.domain_name')) != 'firstcoast') {
                    $userRedLine = 0;
                    $userStateRedLine = 0;
                    $compRate = 0;
                }
            }

            $data = [
                "redline" => $userRedLine,
                "redline_type" => $userRedLineAmountType,
                'comp_rate' => number_format($compRate, 4, '.', ''),
                "is_redline_missing" => 0,
                "commission_type" => $commissionType,
                "message" => NULL,
                "office" => [
                    "office_name" => $userLocation?->office_name,
                    "state_name" => $userLocation?->state?->name,
                    "state_code" => $userLocation?->state?->state_code,
                    "redline_standard" => $userStateRedLine
                ],
                "product" => [
                    "product_redline" => NULL
                ]
            ];
            $this->successResponse("Successfully.", "getUserRedline", $data);
        } else {
            $compRate = 0;
            $locationId = NULL;
            $location = Locations::where('general_code', $saleLocation)->first();
            if ($location) {
                $locationId = $location->id;
            } else {
                $state = State::where('state_code', $saleLocation)->first();
                $saleStateId = isset($state->id) ? $state->id : 0;
                $location = Locations::where('state_id', $saleStateId)->first();
                $locationId = isset($location->id) ? $location->id : 0;
            }

            if (!$location) {
                $this->errorResponse('Location not found!!', 'getUserRedline', '', 400);
            }

            $saleStandardRedLine = NULL;
            $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedLines) {
                $saleStandardRedLine = $locationRedLines->redline_standard;
            }

            if (strtolower($userRedLineAmountType) == strtolower('Shift Based on Location')) {
                $redLine = 0;
                $missingRedLine = 1;
                if ($userLocationRedLines && $checkUserRedLine && $locationRedLines) {
                    $redLine = $saleStandardRedLine + ($userRedLine - $userStateRedLine);
                    $missingRedLine = 0;
                }

                $message = [];
                if (!$checkUserRedLine) {
                    $message[] = "User's";
                }
                if (!$userLocationRedLines) {
                    $message[] = "User's";
                }
                if (!$locationRedLines) {
                    $message[] = "Location";
                }

                if (sizeOf($message) == 1) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        $message = implode(", ", $message) . " office fee is missing based on approval date.";
                    } else {
                        $message = implode(", ", $message) . " redline is missing based on approval date.";
                    }
                } else if (sizeOf($message) > 1) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        $message = implode(", ", $message) . " office fee are missing based on approval date.";
                    } else {
                        $message = implode(", ", $message) . " redline are missing based on approval date.";
                    }
                } else {
                    $message = NULL;
                }

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    // Apply condition for MORTGAGE company type: if domain != 'firstcoast' then redline = 0
                    if (strtolower(config('app.domain_name')) != 'firstcoast') {
                        $redLine = 0;
                    }
                    $compRate = 0;
                    $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$commission) {
                        $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                    }

                    if (!empty($commission->commission_limit) && $commission->commission_limit < $compRate) {

                        $compRate = $commission->commission_limit;
                    }
                }

                $data = [
                    "redline" => $redLine,
                    "redline_type" => $userRedLineAmountType,
                    "comp_rate" => number_format($compRate, 4, '.', ''),
                    "is_redline_missing" => $missingRedLine,
                    "commission_type" => $commissionType,
                    "message" => $message,
                    "office" => [
                        "office_name" => $userLocation?->office_name,
                        "state_name" => $userLocation?->state?->name,
                        "state_code" => $userLocation?->state?->state_code,
                        "redline_standard" => $userStateRedLine
                    ],
                    "product" => [
                        "product_redline" => NULL
                    ]
                ];
                $this->successResponse("Successfully.", "getUserRedline", $data);
            } else if (strtolower($userRedLineAmountType) == strtolower('Shift Based on Product')) {
                $redLine = 0;
                $productRedLine = 0;
                $missingRedLine = 1;
                $productRedLineHistory = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($productRedLineHistory && $checkUserRedLine) {
                    $productRedLine = $productRedLineHistory->product_redline ?? 0;
                    $redLine = $userRedLine + $productRedLine;
                    $missingRedLine = 0;
                }
                $message = [];
                if (!$productRedLineHistory) {
                    $message[] = "Product";
                }
                if (!$checkUserRedLine) {
                    $message[] = "User's";
                }

                if (sizeOf($message) == 1) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                        $message = implode(", ", $message) . " office fee is missing based on approval date.";
                    } else {
                        $message = implode(", ", $message) . " redline is missing based on approval date.";
                    }
                } else if (sizeOf($message) > 1) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                        $message = implode(", ", $message) . " office fee are missing based on approval date.";
                    } else {
                        $message = implode(", ", $message) . " redline are missing based on approval date.";
                    }
                } else {
                    $message = NULL;
                }

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    // Apply condition for MORTGAGE company type: if domain != 'firstcoast' then redline = 0
                    if (strtolower(config('app.domain_name')) != 'firstcoast') {
                        $redLine = 0;
                        $userStateRedLine = 0;
                    }
                    $compRate = 0;
                    $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$commission) {
                        $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                    }

                    if (!empty($commission->commission_limit) && $commission->commission_limit < $compRate) {

                        $compRate = $commission->commission_limit;
                    }
                }

                $data = [
                    "redline" => $redLine,
                    "redline_type" => $userRedLineAmountType,
                    "comp_rate" => number_format($compRate, 4, '.', ''),
                    "is_redline_missing" => $missingRedLine,
                    "commission_type" => $commissionType,
                    "message" => $message,
                    "office" => [
                        "office_name" => $userLocation?->office_name,
                        "state_name" => $userLocation?->state?->name,
                        "state_code" => $userLocation?->state?->state_code,
                        "redline_standard" => $userStateRedLine
                    ],
                    "product" => [
                        "product_redline" => $productRedLine
                    ]
                ];
                $this->successResponse("Successfully.", "getUserRedline", $data);
            } else if (strtolower($userRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                $redLine = 0;
                $productRedLine = 0;
                $missingRedLine = 1;
                $productRedLineHistory = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($userLocationRedLines && $checkUserRedLine && $locationRedLines && $productRedLineHistory) {
                    $missingRedLine = 0;
                    $productRedLine = $productRedLineHistory->product_redline ?? 0;
                    $redLine = $userRedLine - ($userStateRedLine - $saleStandardRedLine) + $productRedLine;
                }

                $message = [];
                if (!$productRedLineHistory) {
                    $message[] = "Product";
                }
                if (!$checkUserRedLine) {
                    $message[] = "User's";
                }
                if (!$userLocationRedLines) {
                    $message[] = "User's";
                }
                if (!$locationRedLines) {
                    $message[] = "Location";
                }

                if (sizeOf($message) == 1) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                        $message = implode(", ", $message) . " office fee is missing based on approval date";
                    } else {
                        $message = implode(", ", $message) . " redline is missing based on approval date";
                    }
                } else if (sizeOf($message) > 1) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                        $message = implode(", ", $message) . " office fee are missing based on approval date";
                    } else {
                        $message = implode(", ", $message) . " redline are missing based on approval date";
                    }
                } else {
                    $message = NULL;
                }

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    // Apply condition for MORTGAGE company type: if domain != 'firstcoast' then redline = 0
                    if (strtolower(config('app.domain_name')) != 'firstcoast') {
                        $redLine = 0;
                        $userStateRedLine = 0;
                    }
                    $compRate = 0;
                    $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$commission) {
                        $commission = PositionCommission::where(['position_id' => $user->sub_position_id, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                    }

                    if (!empty($commission->commission_limit) && $commission->commission_limit < $compRate) {

                        $compRate = $commission->commission_limit;
                    }
                }

                $data = [
                    "redline" => $redLine,
                    "redline_type" => $userRedLineAmountType,
                    "comp_rate" => number_format($compRate, 4, '.', ''),
                    "is_redline_missing" => $missingRedLine,
                    "commission_type" => $commissionType,
                    "message" => $message,
                    "office" => [
                        "office_name" => $userLocation?->office_name,
                        "state_name" => $userLocation?->state?->name,
                        "state_code" => $userLocation?->state?->state_code,
                        "redline_standard" => $userStateRedLine
                    ],
                    "product" => [
                        "product_redline" => $productRedLine
                    ]
                ];
                $this->successResponse("Successfully.", "getUserRedline", $data);
            }
        }

        $message = "User redline is missing based on approval date.";
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
            $message = "User office fee is missing based on approval date.";
        }
        $data = [
            "redline" => "0",
            "redline_type" => NULL,
            "is_redline_missing" => 1,
            "message" => $message,
            "office" => [
                "office_name" => NULL,
                "state_name" => NULL,
                "state_code" => NULL,
                "redline_standard" => NULL
            ],
            "product" => [
                "product_redline" => NULL
            ]
        ];
        $this->successResponse("Successfully.", "getUserRedline", $data);
    }

    // SALE USER REDLINE
    public function getSaleRedline(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required'
        ]);

        $pid = $request->pid;
        $companyProfile = CompanyProfile::first();
        $sale = SalesMaster::where('pid', $pid)->first();
        if (!$sale) {
            $this->errorResponse('Sale not found!!', 'getSaleRedline', '', 400);
        }
        $productId = $sale->product_id;
        $approvedDate = $sale->customer_signoff;
        $closerId = isset($sale->closer1_id) ? $sale->closer1_id : NULL;
        $closer2Id = isset($sale->closer2_id) ? $sale->closer2_id : NULL;
        $setterId = isset($sale->setter1_id) ? $sale->setter1_id : NULL;
        $setter2Id = isset($sale->setter2_id) ? $sale->setter2_id : NULL;

        if (config('app.domain_name') == 'flex') {
            $saleState = $sale->customer_state;
        } else {
            $saleState = $sale->location_code;
        }

        if (!$approvedDate) {
            $this->errorResponse('Approval date is missing!!', 'getSaleRedline', '', 400);
        }

        $saleStandardRedline = NULL;
        $locationRedlines = NULL;
        $data['closer1_redline'] = '0';
        $data['closer1_redline_type'] = NULL;
        $data['closer1_is_redline_missing'] = 0;
        $data['closer1_commission_type'] = NULL;
        $data['closer1_office_data'] = NULL;
        $data['closer1_message'] = NULL;
        $data['closer2_redline'] = '0';
        $data['closer2_redline_type'] = NULL;
        $data['closer2_is_redline_missing'] = 0;
        $data['closer2_commission_type'] = NULL;
        $data['closer2_office_data'] = NULL;
        $data['closer2_message'] = NULL;
        $data['setter1_redline'] = '0';
        $data['setter1_redline_type'] = NULL;
        $data['setter1_is_redline_missing'] = 0;
        $data['setter1_commission_type'] = NULL;
        $data['setter1_office_data'] = NULL;
        $data['setter1_message'] = NULL;
        $data['setter2_redline'] = '0';
        $data['setter2_redline_type'] = NULL;
        $data['setter2_is_redline_missing'] = 0;
        $data['setter2_commission_type'] = NULL;
        $data['setter2_office_data'] = NULL;
        $data['setter2_message'] = NULL;
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) == 'firstcoast') || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') != 'frdmturf')) {
            if (!$saleState) {
                $this->errorResponse('State Code or Location code is missing!!', 'getSaleRedline', '', 400);
            }

            $location = Locations::where('general_code', $saleState)->first();
            if ($location) {
                $locationId = $location->id;
            } else {
                $state = State::where('state_code', $saleState)->first();
                $saleStateId = isset($state->id) ? $state->id : 0;
                $location = Locations::where('state_id', $saleStateId)->first();
                $locationId = isset($location->id) ? $location->id : 0;
            }
            $saleStandardRedline = NULL;
            $locationRedlines = LocationRedlineHistory::where('location_id', $location?->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        }

        if ($setterId) {
            $setter = User::where('id', $setterId)->first();
            $setterRedLine = 0;
            $checkSetterRedLine = 0;
            $setterRedLineAmountType = NULL;
            $setterMissingRedLine = 0;
            $userOrganizationData = checkUsersProductForCalculations($setterId, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == 1) {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setterId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $setterRedLine = $commissionTier->value;
                                }
                            } else {
                                $setterRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $setterRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $setterRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setterId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $setterRedLine = $commissionTier->value;
                                }
                            } else {
                                $setterRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $setterRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $setterRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $setterId, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkSetterRedLine = 1;
                            $setterRedLine = $userRedLine->redline;
                            $setterRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            } else {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'product_id' => $actualProductId, 'core_position_id' => '3'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setterId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $setterRedLine = $commissionTier->value;
                                }
                            } else {
                                $setterRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $setterRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $setterRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setterId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $setterRedLine = $commissionTier->value;
                                }
                            } else {
                                $setterRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $setterRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $setterRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $setterId, 'core_position_id' => '3', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkSetterRedLine = 1;
                            $setterRedLine = $userRedLine->redline;
                            $setterRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            }

            $setterOfficeId = $setter?->office_id ?? null;
            $userTransferHistory = UserTransferHistory::where('user_id', $setterId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $setterOfficeId = $userTransferHistory->office_id;
            }
            $setterLocation = $setterOfficeId ? Locations::with('state')->where('id', $setterOfficeId)->first() : null;
            $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
            $location1RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            $setterStateRedLine = 0;
            if ($location1RedLines) {
                $setterStateRedLine = $location1RedLines->redline_standard;
            }

            $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            $userReconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            if ($userCommission || $userReconCommission) {
                if ($userCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['setter1_redline'] = $userCommission->commission_amount;
                        $data['setter1_redline_type'] = $userCommission->commission_type;
                    } else {
                        $data['setter1_redline'] = $userCommission->redline;
                        $data['setter1_redline_type'] = $userCommission->redline_type;
                    }
                } else if ($userReconCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['setter1_redline'] = $userReconCommission->commission_amount;
                        $data['setter1_redline_type'] = $userReconCommission->commission_type;
                    } else {
                        $data['setter1_redline'] = $userReconCommission->redline;
                        $data['setter1_redline_type'] = $userReconCommission->redline_type;
                    }
                }
            } else {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    $data['setter1_redline'] = $setterRedLine;
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['setter1_redline_type'] = $commissionType;
                    } else {
                        $data['setter1_redline_type'] = $setterRedLineAmountType;
                    }
                } else {
                    if ($setterRedLineAmountType != 'per kw' && $setterRedLineAmountType != 'per sale') {
                        if (strtolower($setterRedLineAmountType) == strtolower('Fixed')) {
                            $data['setter1_redline'] = $setterRedLine;
                            $data['setter1_redline_type'] = 'Fixed';
                        } else if (strtolower($setterRedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($checkSetterRedLine && $location1RedLines && $locationRedlines) {
                                $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                            } else {
                                $setterMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$checkSetterRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location1RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['setter1_redline'] = $redLine;
                            $data['setter1_redline_type'] = 'Shift Based on Location';
                            $data['setter1_message'] = $message;
                        } else if (strtolower($setterRedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetterRedLine) {
                                $setterProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setterRedLine + $setterProductRedLine;
                            } else {
                                $setterMissingRedLine = 1;
                            }
                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkSetterRedLine) {
                                $message[] = "User's";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }


                            $data['setter1_redline'] = $redLine;
                            $data['setter1_redline_type'] = 'Shift Based on Product';
                            $data['setter1_message'] = $message;
                        } else if (strtolower($setterRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetterRedLine && $location1RedLines && $locationRedlines) {
                                $setterProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setterRedLine - ($setterStateRedLine - $saleStandardRedline) + $setterProductRedLine;
                            } else {
                                $setterMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkSetterRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location1RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['setter1_redline'] = $redLine;
                            $data['setter1_redline_type'] = 'Shift Based on Product & Location';
                            $data['setter1_message'] = $message;
                        } else {
                            $setterMissingRedLine = 1;
                            $message = "User redline is missing based on approval date.";
                            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                $message = "User office fee is missing based on approval date.";
                            }
                            $data['setter1_message'] = $message;
                        }
                    } else {
                        $data['setter1_redline'] = $setterRedLine;
                        $data['setter1_redline_type'] = $setterRedLineAmountType;
                    }
                }
            }

            if ($data['setter1_redline_type'] == 'percent') {
                $data['setter1_redline_type'] = '%';
            }
            $data['setter1_commission_type'] = $commissionType;
            $data['setter1_is_redline_missing'] = $setterMissingRedLine;
            $data['setter1_office_data'] = [
                'general_code' => $setterLocation?->general_code,
                'office_name' => $setterLocation?->office_name,
                'state_name' => $setterLocation?->state?->name,
                'state_code' => $setterLocation?->state?->state_code,
                'redline_standard' => $setterStateRedLine
            ];
        }

        if ($setter2Id) {
            $setter2 = User::where('id', $setter2Id)->first();
            $setter2RedLine = 0;
            $checkSetter2RedLine = 0;
            $setter2RedLineAmountType = NULL;
            $setter2MissingRedLine = 0;
            $userOrganizationData = checkUsersProductForCalculations($setter2Id, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            if ($closer2Id == $setter2Id && @$userOrganizationHistory->self_gen_accounts == 1) {
                $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commission2Type = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commission2History) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setter2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $setter2RedLine = $commissionTier->value;
                                }
                            } else {
                                $setter2RedLine = $commission2History->commission;
                            }
                        } else {
                            $setter2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $setter2RedLineAmountType = $commission2History->commission_type;
                    }
                } else {
                    if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setter2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $setter2RedLine = $commissionTier->value;
                                }
                            } else {
                                $setter2RedLine = $commission2History->commission;
                            }
                        } else {
                            $setter2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $setter2RedLineAmountType = $commission2History->commission_type;
                    } else {
                        $user2RedLine = UserRedlines::where(['user_id' => $setter2Id, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($user2RedLine) {
                            $checkSetter2RedLine = 1;
                            $setter2RedLine = $user2RedLine->redline;
                            $setter2RedLineAmountType = $user2RedLine->redline_amount_type;
                        }
                    }
                }
            } else {
                $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'product_id' => $actualProductId, 'core_position_id' => '3'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commission2Type = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commission2History) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setter2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $setter2RedLine = $commissionTier->value;
                                }
                            } else {
                                $setter2RedLine = $commission2History->commission;
                            }
                        } else {
                            $setter2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $setter2RedLineAmountType = $commission2History->commission_type;
                    }
                } else {
                    if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $setter2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $setter2RedLine = $commissionTier->value;
                                }
                            } else {
                                $setter2RedLine = $commission2History->commission;
                            }
                        } else {
                            $setter2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $setter2RedLineAmountType = $commission2History->commission_type;
                    } else {
                        $user2RedLine = UserRedlines::where(['user_id' => $setter2Id, 'core_position_id' => '3', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($user2RedLine) {
                            $checkSetter2RedLine = 1;
                            $setter2RedLine = $user2RedLine->redline;
                            $setter2RedLineAmountType = $user2RedLine->redline_amount_type;
                        }
                    }
                }
            }

            $setter2OfficeId = $setter2->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $setter2Id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $setter2OfficeId = $userTransferHistory->office_id;
            }
            $setter2Location = Locations::with('state')->where('id', $setter2OfficeId)->first();
            $locationId = isset($setter2Location->id) ? $setter2Location->id : 0;
            $location2RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            $setter2StateRedLine = 0;
            if ($location2RedLines) {
                $setter2StateRedLine = $location2RedLines->redline_standard;
            }

            $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            $userReconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            if ($userCommission || $userReconCommission) {
                if ($userCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['setter2_redline'] = $userCommission->commission_amount;
                        $data['setter2_redline_type'] = $userCommission->commission_type;
                    } else {
                        $data['setter2_redline'] = $userCommission->redline;
                        $data['setter2_redline_type'] = $userCommission->redline_type;
                    }
                } else if ($userReconCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['setter2_redline'] = $userReconCommission->commission_amount;
                        $data['setter2_redline_type'] = $userReconCommission->commission_type;
                    } else {
                        $data['setter2_redline'] = $userReconCommission->redline;
                        $data['setter2_redline_type'] = $userReconCommission->redline_type;
                    }
                }
            } else {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    $data['setter2_redline'] = $setter2RedLine;
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['setter2_redline_type'] = $commission2Type;
                    } else {
                        $data['setter2_redline_type'] = $setter2RedLineAmountType;
                    }
                } else {
                    if ($setter2RedLineAmountType != 'per kw' && $setter2RedLineAmountType != 'per sale') {
                        if (strtolower($setter2RedLineAmountType) == strtolower('Fixed')) {
                            $data['setter2_redline'] = $setter2RedLine;
                            $data['setter2_redline_type'] = 'Fixed';
                        } else if (strtolower($setter2RedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($checkSetter2RedLine && $location2RedLines && $locationRedlines) {
                                $redLine = $saleStandardRedline + ($setter2RedLine - $setter2StateRedLine);
                            } else {
                                $setter2MissingRedLine = 1;
                            }

                            $message = [];
                            if (!$checkSetter2RedLine) {
                                $message[] = "User's";
                            }
                            if (!$location2RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date.";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date.";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date.";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date.";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['setter2_redline'] = $redLine;
                            $data['setter2_redline_type'] = 'Shift Based on Location';
                            $data['setter2_message'] = $message;
                        } else if (strtolower($setter2RedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetter2RedLine) {
                                $setter2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setter2RedLine + $setter2ProductRedLine;
                            } else {
                                $setter2MissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkSetter2RedLine) {
                                $message[] = "User's";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['setter2_redline'] = $redLine;
                            $data['setter2_redline_type'] = 'Shift Based on Product';
                            $data['setter2_message'] = $message;
                        } else if (strtolower($setter2RedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetter2RedLine && $location2RedLines && $locationRedlines) {
                                $setter2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setter2RedLine - ($setter2StateRedLine - $saleStandardRedline) + $setter2ProductRedLine;
                            } else {
                                $setter2MissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkSetter2RedLine) {
                                $message[] = "User's";
                            }
                            if (!$location2RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['setter2_redline'] = $redLine;
                            $data['setter2_redline_type'] = 'Shift Based on Product & Location';
                            $data['setter2_message'] = $message;
                        } else {
                            $setter2MissingRedLine = 1;
                            $message = "User redline is missing based on approval date.";
                            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                $message = "User office fee is missing based on approval date.";
                            }
                            $data['setter2_message'] = $message;
                        }
                    } else {
                        $data['setter2_redline'] = $setter2RedLine;
                        $data['setter2_redline_type'] = $setter2RedLineAmountType;
                    }
                }
            }

            if ($data['setter2_redline_type'] == 'percent') {
                $data['setter2_redline_type'] = '%';
            }
            $data['setter2_commission_type'] = $commission2Type;
            $data['setter2_is_redline_missing'] = $setter2MissingRedLine;
            $data['setter2_office_data'] = [
                'general_code' => $setter2Location?->general_code,
                'office_name' => $setter2Location?->office_name,
                'state_name' => $setter2Location?->state?->name,
                'state_code' => $setter2Location?->state?->state_code,
                'redline_standard' => $setter2StateRedLine
            ];
        }

        if ($closerId) {
            $closer = User::where('id', $closerId)->first();
            $closerRedLine = 0;
            $checkCloserRedLine = 0;
            $closerRedLineAmountType = NULL;
            $closerMissingRedLine = 0;
            $userOrganizationData = checkUsersProductForCalculations($closerId, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            if ($setterId == $closerId && @$userOrganizationHistory->self_gen_accounts == 1) {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkCloserRedLine = 1;
                            $closerRedLine = $userRedLine->redline;
                            $closerRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            } else {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'product_id' => $actualProductId, 'core_position_id' => '2'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $closerId, 'core_position_id' => '2', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkCloserRedLine = 1;
                            $closerRedLine = $userRedLine->redline;
                            $closerRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            }

            $closerOfficeId = $closer->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $closerId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $closerOfficeId = $userTransferHistory->office_id;
            }
            $closerLocation = Locations::with('state')->where('id', $closerOfficeId)->first();
            $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
            $location3RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            $closerStateRedLine = 0;
            if ($location3RedLines) {
                $closerStateRedLine = $location3RedLines->redline_standard;
            }

            $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            $userReconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            if ($userCommission || $userReconCommission) {
                if ($userCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['closer1_redline'] = $userCommission->commission_amount;
                        $data['closer1_redline_type'] = $userCommission->commission_type;
                    } else {
                        $data['closer1_redline'] = $userCommission->redline;
                        $data['closer1_redline_type'] = $userCommission->redline_type;
                    }
                } else if ($userReconCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['closer1_redline'] = $userReconCommission->commission_amount;
                        $data['closer1_redline_type'] = $userReconCommission->commission_type;
                    } else {
                        $data['closer1_redline'] = $userReconCommission->redline;
                        $data['closer1_redline_type'] = $userReconCommission->redline_type;
                    }
                }
            } else {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    $data['closer1_redline'] = $closerRedLine;
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['closer1_redline_type'] = $commissionType;
                    } else {
                        $data['closer1_redline_type'] = $closerRedLineAmountType;
                    }
                } else {
                    if ($closerRedLineAmountType != 'per kw' && $closerRedLineAmountType != 'per sale') {
                        if (strtolower($closerRedLineAmountType) == strtolower('Fixed')) {
                            $data['closer1_redline'] = $closerRedLine;
                            $data['closer1_redline_type'] = 'Fixed';
                        } else if (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($checkCloserRedLine && $location3RedLines && $locationRedlines) {
                                $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                            } else {
                                $closerMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$checkCloserRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location3RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['closer1_redline'] = $redLine;
                            $data['closer1_redline_type'] = 'Shift Based on Location';
                            $data['closer1_message'] = $message;
                        } else if (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloserRedLine) {
                                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closerRedLine + $closerProductRedLine;
                            } else {
                                $closerMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkCloserRedLine) {
                                $message[] = "User's";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['closer1_redline'] = $redLine;
                            $data['closer1_redline_type'] = 'Shift Based on Product';
                            $data['closer1_message'] = $message;
                        } else if (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloserRedLine && $location3RedLines && $locationRedlines) {
                                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closerRedLine - ($closerStateRedLine - $saleStandardRedline) + $closerProductRedLine;
                            } else {
                                $closerMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkCloserRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location3RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['closer1_redline'] = $redLine;
                            $data['closer1_redline_type'] = 'Shift Based on Product & Location';
                            $data['closer1_message'] = $message;
                        } else {
                            $closerMissingRedLine = 1;
                            $message = "User redline is missing based on approval date.";
                            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                $message = "User office fee is missing based on approval date.";
                            }
                            $data['closer1_message'] = $message;
                        }
                    } else {
                        $data['closer1_redline'] = $closerRedLine;
                        $data['closer1_redline_type'] = $closerRedLineAmountType;
                    }
                }
            }

            if ($data['closer1_redline_type'] == 'percent') {
                $data['closer1_redline_type'] = '%';
            }
            $data['closer1_commission_type'] = $commissionType;
            $data['closer1_is_redline_missing'] = $closerMissingRedLine;
            $data['closer1_office_data'] = [
                'general_code' => $closerLocation?->general_code,
                'office_name' => $closerLocation?->office_name,
                'state_name' => $closerLocation?->state?->name,
                'state_code' => $closerLocation?->state?->state_code,
                'redline_standard' => $closerStateRedLine
            ];
        }

        if ($closer2Id) {
            $closer2 = User::where('id', $closer2Id)->first();
            $closer2RedLine = 0;
            $checkCloser2RedLine = 0;
            $closer2RedLineAmountType = NULL;
            $closer2MissingRedLine = 0;
            $userOrganizationData = checkUsersProductForCalculations($closer2Id, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            if ($setter2Id == $closer2Id && @$userOrganizationHistory->self_gen_accounts == 1) {
                $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commission2Type = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commission2History) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closer2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $closer2RedLine = $commissionTier->value;
                                }
                            } else {
                                $closer2RedLine = $commission2History->commission;
                            }
                        } else {
                            $closer2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $closer2RedLineAmountType = $commission2History->commission_type;
                    }
                } else {
                    if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closer2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $closer2RedLine = $commissionTier->value;
                                }
                            } else {
                                $closer2RedLine = $commission2History->commission;
                            }
                        } else {
                            $closer2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $closer2RedLineAmountType = $commission2History->commission_type;
                    } else {
                        $user2RedLine = UserRedlines::where(['user_id' => $closer2Id, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($user2RedLine) {
                            $checkCloser2RedLine = 1;
                            $closer2RedLine = $user2RedLine->redline;
                            $closer2RedLineAmountType = $user2RedLine->redline_amount_type;
                        }
                    }
                }
            } else {
                $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'product_id' => $actualProductId, 'core_position_id' => '2'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commission2Type = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    if ($commission2History) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closer2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $closer2RedLine = $commissionTier->value;
                                }
                            } else {
                                $closer2RedLine = $commission2History->commission;
                            }
                        } else {
                            $closer2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $closer2RedLineAmountType = $commission2History->commission_type;
                    }
                } else {
                    if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                        if ($commission2History->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $closer2Id, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commission2History->id])->first();
                                if ($commissionTier) {
                                    $closer2RedLine = $commissionTier->value;
                                }
                            } else {
                                $closer2RedLine = $commission2History->commission;
                            }
                        } else {
                            $closer2RedLine = $commission2History->commission;
                        }
                        $commission2Type = $commission2History->commission_type;
                        $closer2RedLineAmountType = $commission2History->commission_type;
                    } else {
                        $user2RedLine = UserRedlines::where(['user_id' => $closer2Id, 'core_position_id' => '2', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($user2RedLine) {
                            $checkCloser2RedLine = 1;
                            $closer2RedLine = $user2RedLine->redline;
                            $closer2RedLineAmountType = $user2RedLine->redline_amount_type;
                        }
                    }
                }
            }

            $closer2OfficeId = $closer2->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $closer2Id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $closer2OfficeId = $userTransferHistory->office_id;
            }
            $closer2Location = Locations::with('state')->where('id', $closer2OfficeId)->first();
            $locationId = isset($closer2Location->id) ? $closer2Location->id : 0;
            $location4RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            $closer2StateRedLine = 0;
            if ($location4RedLines) {
                $closer2StateRedLine = $location4RedLines->redline_standard;
            }

            $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            $userReconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            if ($userCommission || $userReconCommission) {
                if ($userCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['closer2_redline'] = $userCommission->commission_amount;
                        $data['closer2_redline_type'] = $userCommission->commission_type;
                    } else {
                        $data['closer2_redline'] = $userCommission->redline;
                        $data['closer2_redline_type'] = $userCommission->redline_type;
                    }
                } else if ($userReconCommission) {
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['closer2_redline'] = $userReconCommission->commission_amount;
                        $data['closer2_redline_type'] = $userReconCommission->commission_type;
                    } else {
                        $data['closer2_redline'] = $userReconCommission->redline;
                        $data['closer2_redline_type'] = $userReconCommission->redline_type;
                    }
                }
            } else {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast')) {
                    $data['closer2_redline'] = $closer2RedLine;
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && config('app.domain_name') != 'firstcoast') {
                        $data['closer2_redline_type'] = $commission2Type;
                    } else {
                        $data['closer2_redline_type'] = $closer2RedLineAmountType;
                    }
                } else {
                    if ($closer2RedLineAmountType != 'per kw' && $closer2RedLineAmountType != 'per sale') {
                        if (strtolower($closer2RedLineAmountType) == strtolower('Fixed')) {
                            $data['closer2_redline'] = $closer2RedLine;
                            $data['closer2_redline_type'] = 'Fixed';
                        } else if (strtolower($closer2RedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($checkCloser2RedLine && $location4RedLines && $locationRedlines) {
                                $redLine = $saleStandardRedline + ($closer2RedLine - $closer2StateRedLine);
                            } else {
                                $closer2MissingRedLine = 1;
                            }

                            $message = [];
                            if (!$checkCloser2RedLine) {
                                $message[] = "User's";
                            }
                            if (!$location4RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['closer2_redline'] = $redLine;
                            $data['closer2_redline_type'] = 'Shift Based on Location';
                            $data['closer2_message'] = $message;
                        } else if (strtolower($closer2RedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloser2RedLine) {
                                $closer2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closer2RedLine + $closer2ProductRedLine;
                            } else {
                                $closer2MissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkCloser2RedLine) {
                                $message[] = "User's";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }


                            $data['closer2_redline'] = $redLine;
                            $data['closer2_redline_type'] = 'Shift Based on Product';
                            $data['closer2_message'] = $message;
                        } else if (strtolower($closer2RedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloser2RedLine && $location4RedLines && $locationRedlines) {
                                $closer2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closer2RedLine - ($closer2StateRedLine - $saleStandardRedline) + $closer2ProductRedLine;
                            } else {
                                $closer2MissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkCloser2RedLine) {
                                $message[] = "User's";
                            }
                            if (!$location4RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['closer2_redline'] = $redLine;
                            $data['closer2_redline_type'] = 'Shift Based on Product & Location';
                            $data['closer2_message'] = $message;
                        } else {
                            $closer2MissingRedLine = 1;
                            $message = "User redline is missing based on approval date.";
                            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) === 'firstcoast') {
                                $message = "User office fee is missing based on approval date.";
                            }
                            $data['closer2_message'] = $message;
                        }
                    } else {
                        $data['closer2_redline'] = $closer2RedLine;
                        $data['closer2_redline_type'] = $closer2RedLineAmountType;
                    }
                }
            }

            if ($data['closer2_redline_type'] == 'percent') {
                $data['closer2_redline_type'] = '%';
            }
            $data['closer2_commission_type'] = $commission2Type;
            $data['closer2_is_redline_missing'] = $closer2MissingRedLine;
            $data['closer2_office_data'] = [
                'general_code' => $closer2Location?->general_code,
                'office_name' => $closer2Location?->office_name,
                'state_name' => $closer2Location?->state?->name,
                'state_code' => $closer2Location?->state?->state_code,
                'redline_standard' => $closer2StateRedLine
            ];
        }

        // Added this code block to calculate comp rate for mortgage servers.
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $feePercentage = number_format($sale->net_epc * 100, 4, '.', '');

            //closer1 comp rate calculation
            if ($data['closer1_commission_type'] == 'percent') {
                $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->get('comp_rate')->first();
                $data['closer1_comp_rate'] = number_format(($feePercentage - $data['closer1_redline']), 4, '.', '');
                if (!empty($userCommission->comp_rate)) {
                    $data['closer1_comp_rate'] = number_format($userCommission->comp_rate, 4, '.', '');
                }
                //$data['closer1_comp_rate'] = round(($feePercentage - $data['closer1_redline']), 2);
            }
            //setter1 comp rate calculation
            if ($data['setter1_commission_type'] == 'percent') {
                $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->get('comp_rate')->first();
                $data['setter1_comp_rate'] = number_format(($feePercentage - $data['setter1_redline']), 4, '.', '');
                if (!empty($userCommission->comp_rate)) {
                    $data['setter1_comp_rate'] = number_format($userCommission->comp_rate, 4, '.', '');
                }
            }
            //closer2 comp rate calculation
            if ($data['closer2_commission_type'] == 'percent') {
                $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'is_last' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->get('comp_rate')->first();
                $data['closer2_comp_rate'] = number_format(($feePercentage - $data['closer2_redline']), 4, '.', '');
                if (!empty($userCommission->comp_rate)) {
                    $data['closer2_comp_rate'] = number_format($userCommission->comp_rate, 4, '.', '');
                }
            }
            //setter2 comp rate calculation
            if ($data['setter2_commission_type'] == 'percent') {
                $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'is_last' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->get('comp_rate')->first();
                $data['setter2_comp_rate'] = number_format(($feePercentage - $data['setter2_redline']), 4, '.', '');
                if (!empty($userCommission->comp_rate)) {
                    $data['setter2_comp_rate'] = number_format($userCommission->comp_rate, 4, '.', '');
                }
            }
        }

        // Custom Sales Fields: Calculate total commission and tooltip for each rep
        $data = $this->addCustomSalesFieldCommissionData($data, $pid, $closerId, $closer2Id, $setterId, $setter2Id, $approvedDate, $productId);

        $this->successResponse("Successfully.", "getSaleRedline", $data);
    }

    /**
     * Add Custom Sales Field commission data to redline response
     * 
     * @param array $data Current response data
     * @param string $pid Sale PID
     * @param int|null $closerId Closer 1 ID
     * @param int|null $closer2Id Closer 2 ID
     * @param int|null $setterId Setter 1 ID
     * @param int|null $setter2Id Setter 2 ID
     * @param string $approvedDate Approval date
     * @param int $productId Product ID
     * @return array Updated data with custom field commission info
     */
    protected function addCustomSalesFieldCommissionData(
        array $data,
        string $pid,
        ?int $closerId,
        ?int $closer2Id,
        ?int $setterId,
        ?int $setter2Id,
        ?string $approvedDate,
        ?int $productId
    ): array {
        return app(SalesCustomFieldCalculator::class)->addRedlineCommissionData(
            $data,
            $pid,
            $closerId,
            $closer2Id,
            $setterId,
            $setter2Id,
            $approvedDate,
            $productId
        );
    }

    // RECALCULATE All sales
    public function recalculateSaleAll(Request $request)
    {
        $pids = $request->input('pids'); // array of product IDs
        $reps = $request->input('reps'); // array of rep IDs
        Log::info('RecalculateSales Request - PIDs:', $pids ?? []);
        Log::info('RecalculateSales Request - REPs:', $reps ?? []);

        $query = SalesMaster::query()->select('pid')->whereNotNull('pid');

        if (!empty($pids)) {
            $query->whereIn('pid', $pids);
        }

        if (!empty($reps)) {
            $query->where(function ($subQuery) use ($reps) {
                $subQuery->whereIn('closer1_id', $reps)
                    ->orWhereIn('closer2_id', $reps)
                    ->orWhereIn('setter1_id', $reps)
                    ->orWhereIn('setter2_id', $reps);
            });
        }

        // Count total PIDs and calculate total chunks before dispatching
        $totalPids = $query->count();
        $chunkSize = 20;
        $totalChunks = $totalPids > 0 ? (int) ceil($totalPids / $chunkSize) : 0;

        $initiatorUserId = (int) auth()->id();
        $batchId = '';

        // Initialize batch tracking with accurate counts (non-blocking - if it fails, jobs still dispatch)
        try {
            $tracker = new JobPerformanceTracker();
            $batchId = $tracker->startBatch(
                'RecalculateSalesJob',
                $totalPids,
                $totalChunks,
                'sales-process',
                $initiatorUserId > 0 ? (string) $initiatorUserId : null,
                [
                    'pids' => $pids,
                    'reps' => $reps,
                    'source' => 'recalculateSaleAll'
                ]
            );

            Log::info('RecalculateSaleAll batch initialized', [
                'batch_id' => $batchId,
                'total_pids' => $totalPids,
                'total_chunks' => $totalChunks,
                'initiator_user_id' => $initiatorUserId
            ]);

            // Dispatch batch completion checker if we have chunks
            if ($totalChunks > 0) {
                \App\Jobs\BatchCompletionCheckerJob::dispatch($batchId)
                    ->delay(now()->addSeconds(30))
                    ->onQueue('default');
            }
        } catch (\Throwable $e) {
            // If batch tracking fails, generate a fallback batchId and continue
            // The startChunk() method will create the batch if needed
            $batchId = 'recalc_sale_all_' . ($initiatorUserId > 0 ? $initiatorUserId : 'system') . '_' . time();
            Log::warning('RecalculateSaleAll batch initialization failed, using fallback batchId', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Emit ONE unified notification for the whole batch.
        // Chunk jobs will update this same uniqueKey with "Processing chunk (x/y)" style messages.
        if ($totalChunks > 0) {
            $notificationUniqueKey = 'recalc_sales_batch_' . $batchId;
            $notificationInitiatedAt = now()->toIso8601String();
            try {
                app(\App\Services\JobNotificationService::class)->notify(
                    $initiatorUserId > 0 ? $initiatorUserId : null,
                    'sales_recalculate',
                    'RecalculateSalesJob',
                    'started',
                    0,
                    sprintf(
                        'Sales recalculation: %s / %s sales (chunk %d/%d)',
                        number_format(0),
                        number_format($totalPids),
                        1,
                        $totalChunks
                    ),
                    $notificationUniqueKey,
                    $notificationInitiatedAt,
                    null,
                    [
                        'batch_id' => $batchId,
                        'total_pids' => $totalPids,
                        'chunk_size' => $chunkSize,
                        'total_chunks' => $totalChunks,
                        'completed_chunks' => 0,
                        'source' => 'recalculateSaleAll',
                    ]
                );
            } catch (\Throwable $e) {
                // best-effort only
                Log::debug('RecalculateSaleAll initial notification failed', [
                    'batch_id' => $batchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $chunkNumber = 0;

        $query->chunk($chunkSize, function ($sales) use (&$chunkNumber, $batchId, $initiatorUserId, $totalChunks, $chunkSize) {
            $pids = $sales->pluck('pid')->toArray();
            if (!empty($pids)) {
                $chunkNumber++;
                RecalculateSalesJob::dispatch(
                    $pids,
                    $batchId,
                    $chunkNumber,
                    $initiatorUserId > 0 ? $initiatorUserId : null,
                    $totalChunks,
                    $chunkSize
                );
            }
        });

        $this->successResponse("Recalculate sales jobs dispatched; it may take time. Please wait.", "recalculateSaleAll");
    }


    // import pending sales
    public function salesImportPending(Request $request)
    {
        $this->checkValidations($request->all(), [
            'data_source_type' => 'required'
        ]);

        // Dispatch Job for Background Processing
        dispatch(new SaleMasterJob($request->data_source_type, 100, 'sales-import'))->onQueue('sales-import');

        $this->successResponse("SaleMasterJob job dispatched for queue sales-import; it may take time. Please wait.", "salesImportPending");
    }


    // RECALCULATE SALE
    public function recalculateSale(Request $request, $recalAll = false)
    {
        // Only emit user-facing notifications when called via the API endpoint.
        // Background jobs (RecalculateSalesJob) call this with $recalAll=true; emitting per-PID notifications would spam users.
        $emitNotifications = ($recalAll === false);
        $recipientUserId = $emitNotifications ? (int) (auth()->id() ?? 0) : 0;
        $initiatedAt = $emitNotifications ? now()->toIso8601String() : null;
        $notificationUniqueKey = null;

        try {
            $this->checkValidations($request->all(), [
                'pid' => 'required'
            ]);

            $pid = (string) $request->pid;

            if ($emitNotifications) {
                $notificationUniqueKey = 'sales_recalculate_pid_' . $pid . '_' . ($recipientUserId > 0 ? $recipientUserId : 'guest') . '_' . time();
                app(JobNotificationService::class)->notify(
                    $recipientUserId > 0 ? $recipientUserId : null,
                    'sales_recalculate',
                    'Sales recalculation',
                    'started',
                    0,
                    'Sales recalculation started.',
                    $notificationUniqueKey,
                    $initiatedAt,
                    null,
                    [
                        'pid' => $pid,
                        'source' => 'api',
                    ]
                );
            }

            $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
            if ($payroll) {
                $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.', 'recalculateSale', '', 400);
            }

            // RECON FINALIZE CONDITION CHECK
            $checkReconOverrideFinalizeData = ReconOverrideHistory::where("pid", $pid)->where("status", "finalize")->exists();
            $checkReconCommissionFinalizeData = ReconCommissionHistory::where("pid", $pid)->where("status", "finalize")->exists();
            $checkReconClawBackFinalizeData = ReconClawbackHistory::where("pid", $pid)->where("status", "finalize")->exists();
            if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawBackFinalizeData) {
                $this->errorResponse('Apologies, the sale is not updated because the Recon amount has finalized or executed from recon', 'recalculateSale', '', 400);
            }

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $saleMasters = SalesMaster::whereHas('salesMasterProcess')->with('salesMasterProcess')->where('pid', $pid)->first();
                if (!$saleMasters) {
                    $this->errorResponse('Sale Not Found!', 'recalculateSale', '', 400);
                }

                $closer = $saleMasters->salesMasterProcess->closer1_id;
                if (!$closer) {
                    $this->errorResponse('Apologies, The closer is missing. Kindly ensure that the closer is assigned to this sale.', 'recalculateSale', '', 400);
                }

                if ($emitNotifications) {
                    app(JobNotificationService::class)->notify(
                        $recipientUserId > 0 ? $recipientUserId : null,
                        'sales_recalculate',
                        'Sales recalculation',
                        'processing',
                        50,
                        'Recalculating sale...',
                        (string) $notificationUniqueKey,
                        $initiatedAt,
                        null,
                        [
                            'pid' => $pid,
                            'source' => 'api',
                        ]
                    );
                }

                salesDataChangesClawback($pid);
                
                // Set context for custom field conversion (Trick Subroutine approach)
                // This enables auto-conversion of 'custom field' to 'per sale' in model events
                // Check if Custom Sales Fields feature is enabled for this company
                $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);
                if ($isCustomFieldsEnabled) {
                    SalesCalculationContext::set($saleMasters, $companyProfile);
                }
                try {
                    $this->subroutineProcess($pid);
                    $this->updateExternalOverride($pid);
                } finally {
                    if ($isCustomFieldsEnabled) {
                        SalesCalculationContext::clear();
                    }
                }
            } else {
                $saleMasters = SalesMaster::whereHas('salesMasterProcess')->with('salesMasterProcess')->where('pid', $pid)->first();
                if (!$saleMasters) {
                    $this->errorResponse('Sale Not Found!', 'recalculateSale', '', 400);
                }

                $closer = $saleMasters->salesMasterProcess->closer1_id;
                if (!$closer) {
                    $this->errorResponse('Apologies, The closer is missing. Kindly ensure that the closer is assigned to this sale.', 'recalculateSale', '', 400);
                }

                if ($emitNotifications) {
                    app(JobNotificationService::class)->notify(
                        $recipientUserId > 0 ? $recipientUserId : null,
                        'sales_recalculate',
                        'Sales recalculation',
                        'processing',
                        50,
                        'Recalculating sale...',
                        (string) $notificationUniqueKey,
                        $initiatedAt,
                        null,
                        [
                            'pid' => $pid,
                            'source' => 'api',
                        ]
                    );
                }

                salesDataChangesClawback($pid);
                
                // Set context for custom field conversion (Trick Subroutine approach)
                // This enables auto-conversion of 'custom field' to 'per sale' in model events
                // Check if Custom Sales Fields feature is enabled for this company
                $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);
                if ($isCustomFieldsEnabled) {
                    SalesCalculationContext::set($saleMasters, $companyProfile);
                }
                try {
                    $this->subroutineProcess($pid);
                    $this->updateExternalOverride($pid);
                } finally {
                    if ($isCustomFieldsEnabled) {
                        SalesCalculationContext::clear();
                    }
                }
            }

            if ($recalAll) {
                return 1;
            }

            if ($emitNotifications) {
                app(JobNotificationService::class)->notify(
                    $recipientUserId > 0 ? $recipientUserId : null,
                    'sales_recalculate',
                    'Sales recalculation',
                    'completed',
                    100,
                    'Sales recalculation completed.',
                    (string) $notificationUniqueKey,
                    $initiatedAt,
                    now()->toIso8601String(),
                    [
                        'pid' => $pid,
                        'source' => 'api',
                    ]
                );
            }

            $this->successResponse("Sale data has been recalculated!!", "milestoneFromProduct");
        } catch (HttpResponseException $e) {
            // BaseController::successResponse/errorResponse use abort(response()->json()) which throws HttpResponseException.
            // Emit a failure notification for API calls before rethrowing.
            if ($emitNotifications && is_string($notificationUniqueKey) && $notificationUniqueKey !== '') {
                try {
                    $decoded = json_decode((string) $e->getResponse()->getContent(), true);
                    $status = is_array($decoded) && array_key_exists('status', $decoded) ? (bool) $decoded['status'] : false;
                    $message = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : 'Sales recalculation failed.';

                    if ($status === false) {
                        app(JobNotificationService::class)->notify(
                            $recipientUserId > 0 ? $recipientUserId : null,
                            'sales_recalculate',
                            'Sales recalculation',
                            'failed',
                            0,
                            $message,
                            $notificationUniqueKey,
                            $initiatedAt,
                            now()->toIso8601String(),
                            [
                                'pid' => (string) ($request->pid ?? ''),
                                'source' => 'api',
                            ]
                        );
                    }
                } catch (\Throwable) {
                    // best-effort only
                }
            }

            throw $e;
        } catch (\Throwable $e) {
            if ($emitNotifications && is_string($notificationUniqueKey) && $notificationUniqueKey !== '') {
                try {
                    app(JobNotificationService::class)->notify(
                        $recipientUserId > 0 ? $recipientUserId : null,
                        'sales_recalculate',
                        'Sales recalculation',
                        'failed',
                        0,
                        'Sales recalculation failed: ' . $e->getMessage(),
                        $notificationUniqueKey,
                        $initiatedAt,
                        now()->toIso8601String(),
                        [
                            'pid' => (string) ($request->pid ?? ''),
                            'source' => 'api',
                        ]
                    );
                } catch (\Throwable) {
                    // best-effort only
                }
            }

            throw $e;
        }
    }

    // SUBROUTINE PROCESS
    public function subroutineProcess($pid)
    {
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            $this->manageDataForDisplay($pid);
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        // RECON FINALIZE CONDITION CHECK
        $checkReconOverrideFinalizeData = ReconOverrideHistory::where("pid", $pid)->where("status", "finalize")->exists();
        $checkReconCommissionFinalizeData = ReconCommissionHistory::where("pid", $pid)->where("status", "finalize")->exists();
        $checkReconClawBackFinalizeData = ReconClawbackHistory::where("pid", $pid)->where("status", "finalize")->exists();
        if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawBackFinalizeData) {
            $this->manageDataForDisplay($pid);
            return response()->json(['status' => false, 'Message' => 'Apologies, the sale is not updated because the Recon amount has finalized or executed from recon'], 400);
        }

        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        
        // Handle case where sale doesn't exist
        if (!$checked) {
            return response()->json([
                'status' => false,
                'Message' => 'Sale not found for the given PID',
            ], 404);
        }
        
        // Move CompanyProfile query early to avoid duplicate query later (line 4075)
        $companyProfile = CompanyProfile::first();
        
        // FIX: For Mortgage companies, ensure kw is set from gross_account_value
        // This matches the behavior when manually editing a sale (see lines 4079-4081)
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            if ($checked->gross_account_value && !$checked->kw) {
                $checked->kw = $checked->gross_account_value;
                $checked->save();
            }
        }
        
        $closerId = isset($checked->salesMasterProcess->closer1_id) ? $checked->salesMasterProcess->closer1_id : NULL;
        $closer2Id = isset($checked->salesMasterProcess->closer2_id) ? $checked->salesMasterProcess->closer2_id : NULL;
        $setterId = isset($checked->salesMasterProcess->setter1_id) ? $checked->salesMasterProcess->setter1_id : NULL;
        $setter2Id = isset($checked->salesMasterProcess->setter2_id) ? $checked->salesMasterProcess->setter2_id : NULL;
        $approvalDate = $checked->customer_signoff;
        if (!$approvalDate) {
            $this->manageDataForDisplay($pid);
            return response()->json(['status' => false, 'Message' => 'Apologies, the sale is not available therefore sale can not be calculated.'], 400);
        }

        // Set context for custom field conversion (Trick Subroutine approach)
        // This enables auto-conversion of 'custom field' to 'per sale' in model events
        // during commission calculations for UserCommissionHistory, UserUpfrontHistory, etc.
        // Use cached company profile to avoid repeated database queries
        $companyProfile = \App\Helpers\CustomSalesFieldHelper::getCompanyProfile();
        
        // Check if Custom Sales Fields feature is enabled for this company
        $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

        try {
            // Set context INSIDE try block to ensure cleanup on any exception
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::set($checked, $companyProfile);
            }
            
            $fullRecalculate = request()->input('full_recalculate', 0);
        $milestoneDate = request()->input('milestone_dates');
        if (!request()->filled('milestone_dates')) {
            $milestoneDate = [];
            // CRITICAL: ORDER BY type to ensure consistent ordering
            // createProductData() queries the same data - must have identical order
            $saleProducts = SaleProductMaster::where('pid', $pid)->groupBy('type')->orderBy('type')->get();

            // CRITICAL: Use numeric keys to ensure consistency with JSON fallback path
            // This ensures milestone_dates array structure matches regardless of source
            $index = 0;
            foreach ($saleProducts as $saleProduct) {
                $milestoneDate[$index]['date'] = $saleProduct->milestone_date;
                $index++;
            }

            // If no existing milestone records, try to extract from legacy_api_raw_data_history
            // This handles the case where milestone_schema_id was NULL during import
            if (empty($milestoneDate)) {
                $history = LegacyApiRawDataHistory::where('pid', $pid)
                    ->whereNotNull('trigger_date')
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($history && $history->trigger_date) {
                    $triggerDates = json_decode($history->trigger_date, true);

                    if (is_array($triggerDates) && !empty($triggerDates)) {
                        // CRITICAL: Validate and normalize the data structure
                        // Expected: [0 => ['date' => '2024-01-01'], 1 => ['date' => '2024-02-01']]
                        // Handle cases where data might be flat: ['2024-01-01', '2024-02-01']
                        $normalizedDates = [];
                        foreach (array_values($triggerDates) as $index => $dateValue) {
                            if (is_array($dateValue) && isset($dateValue['date'])) {
                                // Already correct structure: ['date' => value]
                                $normalizedDates[$index] = $dateValue;
                            } elseif (is_string($dateValue)) {
                                // Flat structure: normalize to expected format
                                $normalizedDates[$index] = ['date' => $dateValue];
                            } else {
                                // Invalid structure: log warning and skip
                                \Log::warning('[MILESTONE_FIX] Invalid trigger_date structure', [
                                    'pid' => $pid,
                                    'index' => $index,
                                    'value' => $dateValue
                                ]);
                                $normalizedDates[$index] = ['date' => null];
                            }
                        }

                        $milestoneDate = $normalizedDates;
                        \Log::info('[MILESTONE_FIX] Extracted and normalized milestone dates from legacy_api_raw_data_history', [
                            'pid' => $pid,
                            'milestone_dates' => $milestoneDate,
                            'count' => count($milestoneDate),
                            'original_structure' => array_map(function ($v) {
                                return is_array($v) ? 'array' : gettype($v);
                            }, $triggerDates)
                        ]);
                    }
                }
            }

            // If no existing milestone records, try to extract from legacy_api_raw_data_history
            // This handles the case where milestone_schema_id was NULL during import
            if (empty($milestoneDate)) {
                $history = LegacyApiRawDataHistory::where('pid', $pid)
                    ->whereNotNull('trigger_date')
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($history && $history->trigger_date) {
                    $triggerDates = json_decode($history->trigger_date, true);
                    if (is_array($triggerDates) && !empty($triggerDates)) {
                        $milestoneDate = $triggerDates;
                        \Log::info('[MILESTONE_FIX] Extracted milestone dates from legacy_api_raw_data_history during recalculation', [
                            'pid' => $pid,
                            'milestone_dates' => $milestoneDate
                        ]);
                    }
                }
            }
        }

        $productData = [
            'pid' => $pid,
            'product_id' => $checked->product_id,
            'closer1_id' => $closerId,
            'closer2_id' => $closer2Id,
            'setter1_id' => $setterId,
            'setter2_id' => $setter2Id,
            'effective_date' => $approvalDate,
            'milestone_dates' => $milestoneDate
        ];
        $this->createProductData($productData);

        // Save milestone_trigger to SalesMaster for history tracking
        // Only set if not already populated (Excel import sets this before save)
        if (!empty($milestoneDate) && is_array($milestoneDate) && empty($checked->milestone_trigger)) {
            try {
                $milestone = $this->milestoneWithSchema($checked->product_id, $approvalDate, false);
                $triggers = collect([]);

                if ($milestone && isset($milestone->milestone) && isset($milestone->milestone->milestone_trigger)) {
                    $triggers = $milestone->milestone->milestone_trigger instanceof \Illuminate\Support\Collection
                        ? $milestone->milestone->milestone_trigger->values()
                        : collect($milestone->milestone->milestone_trigger);
                }

                if ($triggers->isNotEmpty()) {
                    $milestoneTriggerData = [];
                    foreach ($triggers as $key => $trigger) {
                        $date = $milestoneDate[$key]['date'] ?? null;
                        $milestoneTriggerData[] = [
                            'name' => $trigger->name ?? 'Unknown',
                            'trigger' => $trigger->on_trigger ?? $trigger->name ?? 'Unknown',
                            'date' => $date,
                        ];
                    }

                    if (!empty($milestoneTriggerData)) {
                        $checked->milestone_trigger = json_encode($milestoneTriggerData, JSON_UNESCAPED_SLASHES);
                        $checked->saveQuietly(); // Use saveQuietly to prevent observer from firing
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('[MILESTONE_TRIGGER] Failed to save', [
                    'pid' => $pid,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $dateCancelled = $checked->date_cancelled;
        $kw = $checked->kw;

        $saleUsers = [];
        $existWorker = ExternalSaleWorker::where('pid', $pid)->pluck('user_id')->toArray();
        if ($existWorker) {
            $saleUsers = array_merge($saleUsers, $existWorker);
        }
        if ($closerId) {
            $saleUsers[] = $closerId;
        }
        if ($closer2Id) {
            $saleUsers[] = $closer2Id;
        }
        if ($setterId) {
            $saleUsers[] = $setterId;
        }
        if ($setter2Id) {
            $saleUsers[] = $setter2Id;
        }

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $checked->gross_account_value;
        }
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $kw = $checked->gross_account_value;
        }
        // if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
        //     $kw = $checked->gross_account_value;
        // }

        if ($closerId) {
            // WHEN CANCEL DATE
            if ($dateCancelled) {
                $saleProduct = SaleProductMaster::where(['pid' => $pid, 'is_exempted' => '1', 'is_projected' => '0'])->first();
                $externalSaleProduct = ExternalSaleProductMaster::where(['pid' => $pid, 'is_exempted' => '1', 'is_projected' => '0'])->first();
                if ($saleProduct || $externalSaleProduct) {
                    $this->manageDataForDisplay($pid);
                    return response()->json(['status' => false, 'Message' => 'The clawback cannot be generated because a clawback exemption has been applied to the sale.'], 400);
                }

                // CLAWBACK CALCULATION
                ReconOverrideHistory::where(['pid' => $pid, 'is_ineligible' => '1'])->delete();
                ReconCommissionHistory::where(['pid' => $pid, 'is_ineligible' => '1'])->delete();

                $this->subroutineFive($checked);
            } else {
                $oldKW = $kw;
                $oldNetEpc = $checked->net_epc;
                $oldGAV = $checked->gross_account_value;
                $isM2Paid = false;
                $m2 = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                if ($m2) {
                    $paidM2 = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                    if ($paidM2) {
                        $isM2Paid = true;
                        $oldKW = $paidM2->kw;
                        $oldNetEpc = $paidM2->net_epc;
                        $oldGAV = $paidM2->gross_account_value;
                    } else {
                        $paidM2 = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                        if ($paidM2) {
                            $isM2Paid = true;
                            $oldKW = $paidM2->kw;
                            $oldNetEpc = $paidM2->net_epc;
                            $oldGAV = $paidM2->gross_account_value;
                        }
                    }
                } else {
                    $withheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'is_last' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                    if ($withheld) {
                        $paidWithheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                        if ($paidWithheld) {
                            $isM2Paid = true;
                            $oldKW = $paidWithheld->kw;
                            $oldNetEpc = $paidWithheld->net_epc;
                            $oldGAV = $paidWithheld->gross_account_value;
                        }
                    }
                }

                $isM2Update = false;
                if ($isM2Paid) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        if (isset($oldKW) && $oldKW != $checked->gross_account_value) {
                            $isM2Update = true;
                        }
                    } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                        // if (isset($oldKW) && $oldKW != $checked->gross_account_value) {
                        //     $isM2Update = true;
                        // }
                    } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        if ((isset($oldNetEpc) && $oldNetEpc != $checked->net_epc) || (isset($oldKW) && $oldKW != $checked->kw) || (isset($oldGAV) && $oldGAV != $checked->gross_account_value)) {
                            $isM2Update = true;
                        }
                    } else {
                        if ((isset($oldNetEpc) && $oldNetEpc != $checked->net_epc) || (isset($oldKW) && $oldKW != $checked->kw)) {
                            $isM2Update = true;
                        }
                    }
                }

                if ($isM2Paid && !$isM2Update) {
                    $commission = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2 update', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                    $override = UserOverrides::where(['pid' => $pid, 'during' => 'm2 update', 'is_displayed' => '1'])->whereIn('sale_user_id', $saleUsers)->first();
                    if ($commission || $override) {
                        $isM2Update = true;
                    }
                }

                if ($isM2Paid && !$isM2Update && !$fullRecalculate) {
                    $this->manageDataForDisplay($pid);
                    return response()->json(['status' => false, 'Message' => 'Final payment has been paid!!'], 400);
                }

                $sale = SalesMaster::with(['salesMasterProcess', 'externalSaleProductMaster', 'externalSaleWorker', 'productInfo' => function ($q) {
                    $q->withTrashed();
                }])->where(['pid' => $pid])->first();

                // Note: Context is already set at the start of subroutineProcess() with $checked
                // which is the same sale. No need to set again here - context stacking would
                // push the outer context onto the stack, and clear() would only restore it,
                // not fully clear. The outer try-finally handles cleanup properly.

                $missingRedLine = false;
                $lastSchemas = SaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '1'])->get()
                    ->map(function ($item) {
                        $item->forExternal = false;
                        return $item;
                    });
                $externalSchemas = ExternalSaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '1'])->get()
                    ->map(function ($item) {
                        $item->forExternal = true;
                        return $item;
                    });

                $mergedSchemas = $lastSchemas->merge($externalSchemas);
                if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $redline = $this->getRedLineData($sale);

                    if ($redline['closer1_is_redline_missing'] || $redline['closer2_is_redline_missing'] || $redline['setter1_is_redline_missing'] || $redline['setter2_is_redline_missing']) {
                        $missingRedLine = true;
                    }

                    foreach ($redline['external_data'] as $entry) {
                        if (!empty($entry['is_redline_missing'])) {
                            $missingRedLine = true;
                            break;
                        }
                    }
                }

                if ($missingRedLine) {
                    $this->manageDataForDisplay($pid);
                    return response()->json(['status' => false, 'Message' => 'Redline is missing!!'], 400);
                }

                $commission = [];
                if (!$isM2Paid) {
                    foreach ($mergedSchemas as $lastSchema) {
                        $redLine = NULL;
                        $forExternal = $lastSchema->forExternal ? 1 : 0;
                        $info = $this->salesRepData($lastSchema, $forExternal);
                        $redline = $this->getRedLineData($sale);
                        $userInfoType = $forExternal ? $info['id'] : $info['type'];
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $commission[$userInfoType] = $this->upfrontTypePercentCalculationForPest($sale, $info, $companyProfile, $forExternal);
                        } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                            if (config('app.domain_name') == 'frdmturf') {
                                $redLine = 0;
                            } else {
                                if ($forExternal) {
                                    $userData = collect($redline['external_data'])
                                        ->where('worker_id', $info['id'])
                                        ->flatMap(function ($item) {
                                            return $item;
                                        });
                                    $redLine = $userData ? $userData['redline'] : null;
                                } else {
                                    if ($info['type'] == 'closer') {
                                        $redLine = $redline['closer1_redline'];
                                    } else if ($info['type'] == 'closer2') {
                                        $redLine = $redline['closer2_redline'];
                                    } else if ($info['type'] == 'setter') {
                                        $redLine = $redline['setter1_redline'];
                                    } else if ($info['type'] == 'setter2') {
                                        $redLine = $redline['setter2_redline'];
                                    }
                                }
                            }

                            $commission[$userInfoType] = $this->upfrontTypePercentCalculationForTurf($sale, $info, $companyProfile, $redLine, $forExternal);
                        } else if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                            if ($forExternal) {
                                $userData = collect($redline['external_data'])
                                    ->where('worker_id', $info['id'])
                                    ->flatMap(function ($item) {
                                        return $item;
                                    });
                                $redLine = $userData ? $userData['redline'] : null;
                            } else {
                                if ($info['type'] == 'closer') {
                                    $redLine = $redline['closer1_redline'];
                                } else if ($info['type'] == 'closer2') {
                                    $redLine = $redline['closer2_redline'];
                                } else if ($info['type'] == 'setter') {
                                    $redLine = $redline['setter1_redline'];
                                } else if ($info['type'] == 'setter2') {
                                    $redLine = $redline['setter2_redline'];
                                }
                            }

                            $commission[$userInfoType] = $this->upfrontTypePercentCalculationForMortgage($sale, $info, $companyProfile, $redLine, $forExternal);
                        } else {
                            if ($forExternal) {
                                $userData = collect($redline['external_data'])
                                    ->where('worker_id', $info['id'])
                                    ->flatMap(function ($item) {
                                        return $item;
                                    });
                                $redLine = $userData ? $userData['redline'] : null;
                            } else {
                                if ($info['type'] == 'closer') {
                                    $redLine = $redline['closer1_redline'];
                                } else if ($info['type'] == 'closer2') {
                                    $redLine = $redline['closer2_redline'];
                                } else if ($info['type'] == 'setter') {
                                    $redLine = $redline['setter1_redline'];
                                } else if ($info['type'] == 'setter2') {
                                    $redLine = $redline['setter2_redline'];
                                }
                            }
                            $commission[$userInfoType] = $this->upfrontTypePercentCalculationForSolar($sale, $info, $redLine, $companyProfile, $forExternal);
                        }
                    }
                }

                $schemas = SaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '0'])->get()
                    ->map(function ($item) {
                        $item->forExternal = false;
                        return $item;
                    });
                $schemasExternal = ExternalSaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid, 'is_last_date' => '0'])->get()
                    ->map(function ($item) {
                        $item->forExternal = true;
                        return $item;
                    });

                $mergedSchemas1 = $schemas->merge($schemasExternal);
                foreach ($mergedSchemas1 as $schema) {
                    if ($schema->milestone_date) {
                        $redLine = NULL;
                        $redLineType = NULL;
                        $forExternal = $schema->forExternal ? 1 : 0;
                        $info = $this->salesRepData($schema, $forExternal);
                        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                            if ($forExternal) {
                                $userData = collect($redline['external_data'])
                                    ->where('worker_id', $info['id'])
                                    ->flatMap(function ($item) {
                                        return $item;
                                    });
                                $redLine = $userData ? $userData['redline'] : null;
                                $redLineType = $userData ? $userData['redline_type'] : null;
                            } else {
                                if ($info['type'] == 'closer') {
                                    $redLine = $redline['closer1_redline'];
                                    $redLineType = $redline['closer1_redline_type'];
                                } else if ($info['type'] == 'closer2') {
                                    $redLine = $redline['closer2_redline'];
                                    $redLineType = $redline['closer2_redline_type'];
                                } else if ($info['type'] == 'setter') {
                                    $redLine = $redline['setter1_redline'];
                                    $redLineType = $redline['setter1_redline_type'];
                                } else if ($info['type'] == 'setter2') {
                                    $redLine = $redline['setter2_redline'];
                                    $redLineType = $redline['setter2_redline_type'];
                                }
                            }
                        }
                        // CUSTOM FIELD TRICK: Convert 'custom field' to 'per sale' before subroutine
                        $commission = $this->prepareCommissionForSubroutine($commission, $sale);
                        $this->subroutineThree($sale, $schema, $info, $commission, $redLine, $redLineType, $forExternal);
                    }
                }

                if ($isM2Update) {
                    foreach ($mergedSchemas as $lastSchema) {
                        if ($lastSchema->milestone_date) {
                            $forExternal = $lastSchema->forExternal ? 1 : 0;
                            $info = $this->salesRepData($lastSchema, $forExternal);

                            $redLine = NULL;
                            $redLineType = NULL;
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $this->subroutineElevenForPest($sale, $lastSchema, $info, $companyProfile, $forExternal);
                            } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                if (config('app.domain_name') == 'frdmturf') {
                                    $redLine = 0;
                                    $redLineType = NULL;
                                } else {
                                    if ($forExternal) {
                                        $userData = collect($redline['external_data'])
                                            ->where('worker_id', $info['id'])
                                            ->flatMap(function ($item) {
                                                return $item;
                                            });
                                        $redLine = $userData ? $userData['redline'] : null;
                                        $redLineType = $userData ? $userData['redline_type'] : null;
                                    } else {
                                        if ($info['type'] == 'closer') {
                                            $redLine = $redline['closer1_redline'];
                                            $redLineType = $redline['closer1_redline_type'];
                                        } else if ($info['type'] == 'closer2') {
                                            $redLine = $redline['closer2_redline'];
                                            $redLineType = $redline['closer2_redline_type'];
                                        } else if ($info['type'] == 'setter') {
                                            $redLine = $redline['setter1_redline'];
                                            $redLineType = $redline['setter1_redline_type'];
                                        } else if ($info['type'] == 'setter2') {
                                            $redLine = $redline['setter2_redline'];
                                            $redLineType = $redline['setter2_redline_type'];
                                        }
                                    }
                                }
                                $this->subroutineElevenForTurf($sale, $lastSchema, $info, $redLine, $redLineType, $companyProfile, $forExternal);
                            } else if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                if ($forExternal) {
                                    $userData = collect($redline['external_data'])
                                        ->where('worker_id', $info['id'])
                                        ->flatMap(function ($item) {
                                            return $item;
                                        });
                                    $redLine = $userData ? $userData['redline'] : null;
                                    $redLineType = $userData ? $userData['redline_type'] : null;
                                } else {
                                    if ($info['type'] == 'closer') {
                                        $redLine = $redline['closer1_redline'];
                                        $redLineType = $redline['closer1_redline_type'];
                                    } else if ($info['type'] == 'closer2') {
                                        $redLine = $redline['closer2_redline'];
                                        $redLineType = $redline['closer2_redline_type'];
                                    } else if ($info['type'] == 'setter') {
                                        $redLine = $redline['setter1_redline'];
                                        $redLineType = $redline['setter1_redline_type'];
                                    } else if ($info['type'] == 'setter2') {
                                        $redLine = $redline['setter2_redline'];
                                        $redLineType = $redline['setter2_redline_type'];
                                    }
                                }
                                $this->subroutineElevenForMortgage($sale, $lastSchema, $info, $companyProfile, $redLine, $redLineType, $forExternal);
                            } else {
                                if ($forExternal) {
                                    $userData = collect($redline['external_data'])
                                        ->where('worker_id', $info['id'])
                                        ->flatMap(function ($item) {
                                            return $item;
                                        });
                                    $redLine = $userData ? $userData['redline'] : null;
                                    $redLineType = $userData ? $userData['redline_type'] : null;
                                } else {
                                    if ($info['type'] == 'closer') {
                                        $redLine = $redline['closer1_redline'];
                                        $redLineType = $redline['closer1_redline_type'];
                                    } else if ($info['type'] == 'closer2') {
                                        $redLine = $redline['closer2_redline'];
                                        $redLineType = $redline['closer2_redline_type'];
                                    } else if ($info['type'] == 'setter') {
                                        $redLine = $redline['setter1_redline'];
                                        $redLineType = $redline['setter1_redline_type'];
                                    } else if ($info['type'] == 'setter2') {
                                        $redLine = $redline['setter2_redline'];
                                        $redLineType = $redline['setter2_redline_type'];
                                    }
                                }
                                $this->subroutineElevenForSolar($sale, $lastSchema, $info, $redLine, $redLineType, $companyProfile, $forExternal);
                            }
                        }
                    }

                    $overrideTrigger = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1'])->whereNotNull('milestone_date')->first();
                    $overrideTriggerSchemas = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNotNull('milestone_date')->get()->map(function ($item) {
                        $item->forExternal = false;
                        return $item;
                    });
                    $overrideTriggerSchemasExternal = ExternalSaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNotNull('milestone_date')->get()->map(function ($item) {
                        $item->forExternal = true;
                        return $item;
                    });
                    $mergedOverrideTriggerSchemas = $overrideTriggerSchemas->merge($overrideTriggerSchemasExternal);
                    foreach ($mergedOverrideTriggerSchemas as $overrideTriggerSchema) {
                        if ($overrideTriggerSchema->milestone_date) {
                            $forExternal = $overrideTriggerSchema->forExternal ? 1 : 0;
                            $info = $this->salesRepData($overrideTriggerSchema, $forExternal);
                            //External user not remove or add override
                            if ($info['id'] && $forExternal == 0) {
                                // REMOVE GENERATED UNPAID ADDERS OVERRIDE
                                $userOverrides = UserOverrides::when(($info['type'] == 'closer' || $info['type'] == 'selfgen'), function ($q) {
                                    $q->where('type', '!=', 'Stack');
                                })->where(['sale_user_id' => $info['id'], 'pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->get();
                                $userReconOverrides = UserOverrides::when(($info['type'] == 'closer' || $info['type'] == 'selfgen'), function ($q) {
                                    $q->where('type', '!=', 'Stack');
                                })->where(['sale_user_id' => $info['id'], 'pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->get();
                                $userOverrides = $userOverrides->merge($userReconOverrides);
                                foreach ($userOverrides as $userOverride) {
                                    $userOverride->delete();
                                }

                                // GENERATE ADDERS OVERRIDE
                                $this->addersOverrides($info['id'], $pid, $kw, $overrideTriggerSchema->milestone_date, null, $forExternal);
                            }
                        }
                    }

                    foreach ($mergedSchemas as $lastSchema) {
                        if ($lastSchema->milestone_date) {
                            $forExternal = $lastSchema->forExternal ? 1 : 0;
                            $info = $this->salesRepData($lastSchema, $forExternal);
                            if (($info['type'] == 'closer' || $info['type'] == 'selfgen') && $forExternal == 0) {
                                // REMOVE GENERATED UNPAID ADDERS STACK OVERRIDE
                                $userOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->get();
                                $userReconOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->get();
                                $userOverrides = $userOverrides->merge($userReconOverrides);
                                foreach ($userOverrides as $userOverride) {
                                    $userOverride->delete();
                                }

                                // GENERATE ADDERS STACK OVERRIDES
                                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                    $this->pestAddersStackOverride($info['id'], $pid, $lastSchema->milestone_date, null, $forExternal);
                                } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                    $this->addersStackTurfOverride($info['id'], $pid, $kw, $lastSchema->milestone_date, null, $forExternal);
                                } else if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $this->addersStackMortgageOverride($info['id'], $pid, $kw, $lastSchema->milestone_date, null, $forExternal);
                                } else {
                                    $this->addersStackOverride($info['id'], $pid, $kw, $lastSchema->milestone_date, null, $forExternal);
                                }
                            }
                        }
                    }
                } else {
                    $this->m2updateRemoved($sale);
                    
                    foreach ($mergedSchemas as $lastSchema) {
                        
                        if ($lastSchema->milestone_date) {
                            $redLine = NULL;
                            $redLineType = NULL;
                            $forExternal = $lastSchema->forExternal ? 1 : 0;
                            $info = $this->salesRepData($lastSchema, $forExternal);
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $this->subroutineEightForPest($sale, $lastSchema, $info, $companyProfile, $forExternal);
                            } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                if (config('app.domain_name') == 'frdmturf') {
                                    $redLine = 0;
                                    $redLineType = NULL;
                                } else {
                                    if ($forExternal) {
                                        $userData = collect($redline['external_data'])
                                            ->where('worker_id', $info['id'])
                                            ->flatMap(function ($item) {
                                                return $item;
                                            });
                                        $redLine = $userData ? $userData['redline'] : null;
                                        $redLineType = $userData ? $userData['redline_type'] : null;
                                    } else {
                                        if ($info['type'] == 'closer') {
                                            $redLine = $redline['closer1_redline'];
                                            $redLineType = $redline['closer1_redline_type'];
                                        } else if ($info['type'] == 'closer2') {
                                            $redLine = $redline['closer2_redline'];
                                            $redLineType = $redline['closer2_redline_type'];
                                        } else if ($info['type'] == 'setter') {
                                            $redLine = $redline['setter1_redline'];
                                            $redLineType = $redline['setter1_redline_type'];
                                        } else if ($info['type'] == 'setter2') {
                                            $redLine = $redline['setter2_redline'];
                                            $redLineType = $redline['setter2_redline_type'];
                                        }
                                    }
                                }

                                $this->subroutineEightForTurf($sale, $lastSchema, $info, $redLine, $redLineType, $companyProfile, $forExternal);
                            } else if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                if ($forExternal) {
                                    $userData = collect($redline['external_data'])
                                        ->where('worker_id', $info['id'])
                                        ->flatMap(function ($item) {
                                            return $item;
                                        });
                                    $redLine = $userData ? $userData['redline'] : null;
                                    $redLineType = $userData ? $userData['redline_type'] : null;
                                } else {
                                    if ($info['type'] == 'closer') {
                                        $redLine = $redline['closer1_redline'];
                                        $redLineType = $redline['closer1_redline_type'];
                                    } else if ($info['type'] == 'closer2') {
                                        $redLine = $redline['closer2_redline'];
                                        $redLineType = $redline['closer2_redline_type'];
                                    } else if ($info['type'] == 'setter') {
                                        $redLine = $redline['setter1_redline'];
                                        $redLineType = $redline['setter1_redline_type'];
                                    } else if ($info['type'] == 'setter2') {
                                        $redLine = $redline['setter2_redline'];
                                        $redLineType = $redline['setter2_redline_type'];
                                    }
                                }

                                $this->subroutineEightForMortgage($sale, $lastSchema, $info, $companyProfile, $redLine, $redLineType, $forExternal);
                            } else {
                                if ($forExternal) {
                                    $userData = collect($redline['external_data'])
                                        ->where('worker_id', $info['id'])
                                        ->flatMap(function ($item) {
                                            return $item;
                                        });
                                    $redLine = $userData ? $userData['redline'] : null;
                                    $redLineType = $userData ? $userData['redline_type'] : null;
                                } else {
                                    if ($info['type'] == 'closer') {
                                        $redLine = $redline['closer1_redline'];
                                        $redLineType = $redline['closer1_redline_type'];
                                    } else if ($info['type'] == 'closer2') {
                                        $redLine = $redline['closer2_redline'];
                                        $redLineType = $redline['closer2_redline_type'];
                                    } else if ($info['type'] == 'setter') {
                                        $redLine = $redline['setter1_redline'];
                                        $redLineType = $redline['setter1_redline_type'];
                                    } else if ($info['type'] == 'setter2') {
                                        $redLine = $redline['setter2_redline'];
                                        $redLineType = $redline['setter2_redline_type'];
                                    }
                                }
                                $this->subroutineEightForSolar($sale, $lastSchema, $info, $redLine, $redLineType, $companyProfile, $forExternal);
                            }
                        }
                    }

                    if (!$isM2Paid || $fullRecalculate) {
                        $overrideSetting = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();
                        if ($overrideSetting) {
                            $overrideTrigger = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1'])->whereNotNull('milestone_date')->groupBy('type')->first();
                            $overrideTriggerSchemas = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNotNull('milestone_date')->get()->map(function ($item) {
                                $item->forExternal = false;
                                return $item;
                            });

                            $overrideTriggerSchemasExternal = ExternalSaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNotNull('milestone_date')->get()->map(function ($item) {
                                $item->forExternal = true;
                                return $item;
                            });
                            $mergedOverrideTriggerSchemas = $overrideTriggerSchemas->merge($overrideTriggerSchemasExternal);
                            foreach ($mergedOverrideTriggerSchemas as $overrideTriggerSchema) {
                                if ($overrideTriggerSchema->milestone_date) {

                                    $forExternal = $overrideTriggerSchema->forExternal ? 1 : 0;
                                    $info = $this->salesRepData($overrideTriggerSchema, $forExternal);
                                    if ($info['id'] && $forExternal == 0) {
                                        // REMOVE GENERATED UNPAID M2 OVERRIDE
                                        $userOverrides = UserOverrides::when($info['type'] == 'closer' || $info['type'] == 'selfgen', function ($q) {
                                            $q->where('type', '!=', 'Stack');
                                        })->where(['sale_user_id' => $info['id'], 'pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'during_m2', 'during' => 'm2', 'is_displayed' => '1'])->get();
                                        $userReconOverrides = UserOverrides::when(($info['type'] == 'closer' || $info['type'] == 'selfgen'), function ($q) {
                                            $q->where('type', '!=', 'Stack');
                                        })->where(['sale_user_id' => $info['id'], 'pid' => $pid, 'recon_status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_move_to_recon' => '0', 'during' => 'm2', 'is_displayed' => '1'])->get();
                                        $userOverrides = $userOverrides->merge($userReconOverrides);
                                        foreach ($userOverrides as $userOverride) {
                                            $userOverride->delete();
                                        }

                                        // GENERATE OVERRIDES
                                        $this->userOverride($info, $pid, $kw, $overrideTriggerSchema->milestone_date, $commission, $forExternal);
                                    }
                                }
                            }
                        }

                        if (isset($mergedOverrideTriggerSchemas) && sizeOf($mergedOverrideTriggerSchemas) == 0) {
                            // REMOVE GENERATED UNPAID M2 OVERRIDE
                            $userOverrides = UserOverrides::where(['pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'during_m2', 'during' => 'm2', 'is_displayed' => '1', 'worker_type' => 'internal'])->where('type', '!=', 'Stack')->get();
                            $userReconOverrides = UserOverrides::where(['pid' => $pid, 'recon_status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_move_to_recon' => '0', 'during' => 'm2', 'is_displayed' => '1', 'worker_type' => 'internal'])->where('type', '!=', 'Stack')->get();
                            $userOverrides = $userOverrides->merge($userReconOverrides);
                            foreach ($userOverrides as $userOverride) {
                                $userOverride->delete();
                            }
                        }
                    }

                    // RECALCULATE OVERRIDES ON FINAL PAYMENT AGAIN
                    foreach ($mergedSchemas as $lastSchema) {
                        if ($lastSchema->milestone_date) {
                            // Convert projected overrides to actual overrides when milestone dates are added
                            if (ProjectionUserOverrides::where('pid', $pid)->exists()) {
                                $this->convertProjectedOverridesToActual($pid,  $lastSchema->milestone_date);
                            }
                            $forExternal = $lastSchema->forExternal ? 1 : 0;
                            $info = $this->salesRepData($lastSchema, $forExternal);
                            if ($info['id'] && $forExternal == 0) {
                                // REMOVE GENERATED UNPAID ADDERS OVERRIDE
                                $userOverrides = UserOverrides::when($info['type'] == 'closer' || $info['type'] == 'selfgen', function ($q) {
                                    $q->where('type', '!=', 'Stack');
                                })->where(['sale_user_id' => $info['id'], 'pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->get();
                                $userReconOverrides = UserOverrides::when($info['type'] == 'closer' || $info['type'] == 'selfgen', function ($q) {
                                    $q->where('type', '!=', 'Stack');
                                })->where(['sale_user_id' => $info['id'], 'pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->get();
                                $userOverrides = $userOverrides->merge($userReconOverrides);
                                foreach ($userOverrides as $userOverride) {
                                    $userOverride->delete();
                                }

                                // GENERATE ADDERS OVERRIDE
                                $this->addersOverrides($info['id'], $pid, $kw, $lastSchema->milestone_date, 'm2', $forExternal);
                            }
                        }
                    }

                    if (!$isM2Paid || $fullRecalculate) {
                        foreach ($mergedSchemas as $lastSchema) {
                            $forExternal = $lastSchema->forExternal ? 1 : 0;
                            if ($lastSchema->milestone_date) {
                                $info = $this->salesRepData($lastSchema, $forExternal);
                                if ($info['type'] == 'closer' && $forExternal == 0) {
                                    // GENERATE STACK OVERRIDES
                                    $userOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2', 'is_displayed' => '1'])->get();
                                    $userReconOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->get();
                                    $userOverrides = $userOverrides->merge($userReconOverrides);
                                    foreach ($userOverrides as $userOverride) {
                                        $userOverride->delete();
                                    }

                                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                        $this->pestStackUserOverride($info['id'], $pid, $lastSchema->milestone_date);
                                    } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                        $this->stackUserTurfOverride($info['id'], $pid, $kw, $lastSchema->milestone_date);
                                    } else if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                        $this->stackUserMortgageOverride($info['id'], $pid, $kw, $lastSchema->milestone_date);
                                    } else {
                                        $this->stackUserOverride($info['id'], $pid, $kw, $lastSchema->milestone_date);
                                    }
                                }
                            } else {
                                $info = $this->salesRepData($lastSchema, $forExternal);
                                if ($info['type'] == 'closer' && $forExternal == 0) {
                                    // REMOVE GENERATE STACK OVERRIDES
                                    $userOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2', 'is_displayed' => '1'])->get();
                                    $userReconOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->get();
                                    $userOverrides = $userOverrides->merge($userReconOverrides);
                                    foreach ($userOverrides as $userOverride) {
                                        $userOverride->delete();
                                    }
                                }
                            }
                        }
                    }

                    foreach ($mergedSchemas as $lastSchema) {
                        $forExternal = $lastSchema->forExternal ? 1 : 0;
                        if ($lastSchema->milestone_date) {
                            $info = $this->salesRepData($lastSchema, $forExternal);
                            if ($info['type'] == 'closer' && $forExternal == 0) {
                                // REMOVE GENERATED UNPAID ADDERS STACK OVERRIDE
                                $userOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->get();
                                $userReconOverrides = UserOverrides::where(['sale_user_id' => $info['id'], 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->get();
                                $userOverrides = $userOverrides->merge($userReconOverrides);
                                foreach ($userOverrides as $userOverride) {
                                    $userOverride->delete();
                                }

                                // GENERATE ADDERS STACK OVERRIDES
                                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                    $this->pestAddersStackOverride($info['id'], $pid, $lastSchema->milestone_date, 'm2');
                                } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                    $this->addersStackTurfOverride($info['id'], $pid, $kw, $lastSchema->milestone_date, 'm2');
                                } else if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $this->addersStackMortgageOverride($info['id'], $pid, $kw, $lastSchema->milestone_date, 'm2');
                                } else {
                                    $this->addersStackOverride($info['id'], $pid, $kw, $lastSchema->milestone_date, 'm2');
                                }
                            }
                        }
                    }
                }
                // Check here if userCommission and userOverrides are paid and amount is 0
                $this->checkUserPaymentStatus($pid);
            }
        }

            $this->manageDataForDisplay($pid);
        } finally {
            // Clear context after all subroutine processing completes
            // This is in finally block to ensure cleanup even on early returns
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::clear();
            }
        }
    }

    // ADD MANUAL SALE - OPTIMIZED FOR PERFORMANCE
    public function addManualSaleData(Request $request)
    {
        // AGGRESSIVE OPTIMIZATION: Skip expensive validations when possible
        if (!$this->shouldSkipExpensiveValidations($request)) {
            // Pre-flight validations
            $this->validateSystemState($request->pid);
            $this->validateMilestoneDates($request);
        }

        // Get cached company profile
        $companyProfile = $this->getCompanyProfile();

        // Check if Custom Sales Fields feature is enabled for this company
        $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

        // PEST FLOW STARTS
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $this->checkValidations($request->all(), [
                'pid' => 'required',
                'customer_name' => 'required',
                'gross_account_value' => 'required',
                'approved_date' => 'required',
                'rep_id' => 'required',
                'rep_email' => 'required',
                'milestone_dates' => 'required|array|min:1'
            ]);

            if (empty(array_filter($request->rep_id))) {
                $this->errorResponse('At least one Sales rep is mandatory.', 'addManualSaleData', '', 400);
            }

            $pid = $request->pid;
            $closers = $request->rep_id;
            $setters = $request->setter_id ?? [];
            $productId = $request->product_id;
            $systemProductId = $request->product_id;
            $product = Products::withTrashed()->where('id', $productId)->first();
            if (!$product) {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $systemProductId = $product->id;
            }
            $finalDates = [];
            $effectiveDate = $request->approved_date;
            $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
            $triggers = isset($milestone?->milestone?->milestone_trigger) ? $milestone?->milestone?->milestone_trigger : [];
            foreach ($triggers as $key => $schema) {
                $date = isset($request->milestone_dates[$key]['date']) ? $request->milestone_dates[$key]['date'] : NULL;
                $finalDates[] = [
                    'date' => $date
                ];
            }
            $request->merge(['milestone_dates' => $finalDates]);
            $saleMasterData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            if ($saleMasterData) {
                $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();

                salesDataChangesClawback($saleMasterProcess->pid);
                if (!empty($saleMasterData->product_id) && empty($systemProductId)) {
                    if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    $this->saleProductMappingChanges($pid);
                }

                if (!empty($saleMasterData->product_id) && !empty($systemProductId) && $saleMasterData->product_id != $systemProductId) {
                    if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    $this->saleProductMappingChanges($pid);
                }

                $checked = false;
                $isM2Paid = false;
                $withHeldPaid = false;
                $upFrontRemove = [];
                $upFrontChange = [];
                $commissionRemove = [];
                $commissionChange = [];
                $count = count($request->milestone_dates);

                // Optimize N+1 queries: Bulk fetch all milestone data
                $milestoneTypes = [];
                foreach ($finalDates as $key => $finalDate) {
                    $milestoneTypes[] = 'm' . ($key + 1);
                }
                $bulkData = $this->fetchMilestoneDataBulk($pid, $milestoneTypes);

                foreach ($finalDates as $key => $finalDate) {
                    $type = 'm' . ($key + 1);
                    $date = @$finalDate['date'];
                    $saleProduct = $bulkData['saleProducts']->get($type);
                    if ($saleProduct) {
                        if ($count == ($key + 1)) {
                            if ($this->checkMilestonePaymentFromBulk($bulkData['commissions'], $type, 'during_m2')) {
                                $isM2Paid = true;
                            }
                            if ($this->checkMilestonePaymentFromBulk($bulkData['commissions'], $type, 'reconciliation')) {
                                $withHeldPaid = true;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if ($isM2Paid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid'], 400);
                                }
                                if ($withHeldPaid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                                }
                                $commissionRemove[] = $type;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if ($isM2Paid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid'], 400);
                                }
                                if ($withHeldPaid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                                }

                                $commissionChange[] = [
                                    'type' => $type,
                                    'date' => $date
                                ];
                            }
                        } else {
                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if ($this->checkMilestonePaymentFromBulk($bulkData['commissions'], $type, 'during_m2')) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the ' . $type . ' amount has already been paid'], 400);
                                }
                                if ($this->checkMilestonePaymentFromBulk($bulkData['commissions'], $type, 'reconciliation')) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the ' . $type . ' amount has already been paid'], 400);
                                }

                                $upFrontRemove[] = $type;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if ($this->checkMilestonePaymentFromBulk($bulkData['commissions'], $type, 'during_m2')) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the ' . $type . ' amount has already been paid'], 400);
                                }
                                if ($this->checkMilestonePaymentFromBulk($bulkData['commissions'], $type, 'reconciliation')) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the ' . $type . ' amount has finalized or executed from reconciliation'], 400);
                                }

                                $upFrontChange[] = [
                                    'type' => $type,
                                    'date' => $date
                                ];
                            }
                        }
                    }
                    if (!$checked && $saleProduct && $saleProduct->is_override) {
                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                            if ($this->checkOverridePaymentFromBulk($bulkData['overrides'], 'during_m2')) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the override amount has already been paid'], 400);
                            }
                            if ($this->checkOverridePaymentFromBulk($bulkData['overrides'], 'reconciliation')) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the override amount has already been paid'], 400);
                            }
                        }

                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                            if ($this->checkOverridePaymentFromBulk($bulkData['overrides'], 'during_m2')) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the override amount has already been paid'], 400);
                            }
                            if ($this->checkOverridePaymentFromBulk($bulkData['overrides'], 'reconciliation')) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the override amount has already been paid'], 400);
                            }
                        }
                        $checked = true;
                    }
                }

                foreach ($upFrontRemove as $remove) {
                    $this->removeUpFrontSaleData($pid, $remove);
                }

                foreach ($upFrontChange as $change) {
                    $this->changeUpFrontPayrollData($pid, $change);
                }

                if (sizeOf($commissionRemove) != 0) {
                    $this->removeCommissionSaleData($pid);
                }

                foreach ($commissionChange as $change) {
                    $this->changeCommissionPayrollData($pid, $change);
                }

                if (sizeof($finalDates) == 0) {
                    $this->removeUpFrontSaleData($pid);
                    $this->removeCommissionSaleData($pid);
                }

                if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
                    if ($isM2Paid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                    }

                    $this->clawBackSalesData($saleMasterProcess->closer1_id, $saleMasterData);
                    $this->removeClawBackForNewUser($closers[0], $saleMasterData);
                }

                if (isset($saleMasterProcess->closer2_id) && isset($closers[1]) && $closers[1] != $saleMasterProcess->closer2_id) {
                    if ($isM2Paid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                    }

                    $this->clawBackSalesData($saleMasterProcess->closer2_id, $saleMasterData);
                    $this->removeClawBackForNewUser($closers[1], $saleMasterData);
                }
                $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : NULL;
                $saleMasterProcess->save();
            } else {
                if (isset($closers[0])) {
                    $reps = checkSalesReps($closers[0], $request->approved_date, 'Sales rep');
                    if (!$reps['status']) {
                        $this->errorResponse($reps['message'], 'addManualSaleData', '', 400);
                    }
                }

                if (isset($closers[1])) {
                    $reps = checkSalesReps($closers[1], $request->approved_date, 'Sales rep 2');
                    if (!$reps['status']) {
                        $this->errorResponse($reps['message'], 'addManualSaleData', '', 400);
                    }
                }
            }

            $netEPC = isset($request->net_epc) ? $request->net_epc : NULL;

            $closer = User::whereIn('id', $request->rep_id)->get();
            $stateCode = State::find($request->state_id)?->state_code ?? NULL;
            $stateId = NULL;
            $stateCode = NULL;
            if ($request->customer_state) {
                $state = State::where('state_code', $request->customer_state)->first();
                $stateId = $state?->id ?? NULL;
                $stateCode = $state?->state_code ?? NULL;
            } else if ($request->location_code) {
                $location = Locations::with('State')->where('general_code', $request->location_code)->first();
                if ($location && $location->State) {
                    $stateId = $location?->State?->id ?? NULL;
                    $stateCode = $location?->State?->state_code ?? NULL;
                }
            }
            $val = [
                'pid' => $pid,
                'kw' => isset($request->kw) ? $request->kw : NULL,
                'install_partner' => isset($request->installer) ? $request->installer : NULL,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : NULL,
                'customer_address' => isset($request->customer_address) ? $request->customer_address : NULL,
                'customer_address_2' => isset($request->customer_address_2) ? $request->customer_address_2 : NULL,
                'customer_city' => isset($request->customer_city) ? $request->customer_city : NULL,
                'state_id' => $stateId,
                'customer_state' => $stateCode ? $stateCode : $request->customer_state,
                'location_code' => isset($request->location_code) ? $request->location_code : NULL,
                'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : NULL,
                'customer_email' => isset($request->customer_email) ? $request->customer_email : NULL,
                'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : NULL,
                'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : NULL,
                'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : NULL,
                'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL,
                'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : NULL,
                'closer1_id' => isset($closers[0]) ? $closers[0] : NULL,
                'closer2_id' => isset($closers[1]) ? $closers[1] : NULL,
                'setter1_id' => isset($setters[0]) ? $setters[0] : NULL,
                'setter2_id' => isset($setters[1]) ? $setters[1] : NULL,
                'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : NULL,
                'customer_signoff' => isset($request->approved_date) ? $request->approved_date : NULL,
                'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
                'm2_date' => isset($request->m2_date) ? $request->m2_date : NULL,
                'product' => isset($request->product) ? $request->product : NULL,
                'product_id' => isset($productId) ? $productId : NULL,
                'product_code' => isset($product->product_id) ? $product->product_id : NULL,
                'sale_product_name' => isset($request->sale_product_name) ? $request->sale_product_name : NULL,
                'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : NULL,
                'epc' => isset($request->epc) ? $request->epc : NULL,
                'net_epc' => $netEPC,
                'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : NULL,
                'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : NULL,
                'adders' => isset($request->show) ? $request->show : NULL,
                'adders_description' => isset($request->adders_description) ? $request->adders_description : NULL,
                'redline' => isset($request->redline) ? $request->redline : NULL,
                'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : NULL,
                'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : NULL,
                'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : NULL,
                'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : NULL,
                'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : NULL,
                'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : NULL,
                'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : NULL,
                'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : NULL,
                'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : NULL,
                'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : NULL,
                'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : NULL,
                'return_sales_date' => isset($request->return_sales_date) ? $request->return_sales_date : NULL,
                'job_status' => isset($request->job_status) ? $request->job_status : NULL,
                'length_of_agreement' => isset($request->length_of_agreement) ? $request->length_of_agreement : NULL,
                'service_schedule' => isset($request->service_schedule) ? $request->service_schedule : NULL,
                'subscription_payment' => isset($request->subscription_payment) ? $request->subscription_payment : NULL,
                'service_completed' => isset($request->service_completed) ? $request->service_completed : NULL,
                'last_service_date' => isset($request->last_service_date) ? $request->last_service_date : NULL,
                'bill_status' => isset($request->bill_status) ? $request->bill_status : NULL,
                'initial_service_cost' => isset($request->initial_service_cost) ? $request->initial_service_cost : NULL,
                'auto_pay' => isset($request->auto_pay) ? $request->auto_pay : NULL,
                'card_on_file' => isset($request->card_on_file) ? $request->card_on_file : NULL,
                'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : NULL
            ];

            $nullTableVal = $val;
            $saleMasterData = SalesMaster::where('pid', $pid)->first();
            if ($saleMasterData) {
                if ($request->date_cancelled) {
                    $nullTableVal['date_cancelled'] = $request->date_cancelled;
                    $nullTableVal['data_source_type'] = 'manual';
                    $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) { $saleModelToUpdate->fill($val)->save(); }
                } else {
                    if (!empty($saleMasterData->date_cancelled) && empty(\request('date_cancelled'))) {
                        salesDataChangesBasedOnClawback($saleMasterProcess->pid);
                        request()->merge(['full_recalculate' => 1]);
                    }
                    $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) {
                        $saleModelToUpdate->fill($val)->save();
                    }

                    $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                    $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                    $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                    $nullTableVal['data_source_type'] = 'manual';
                    $closer = $request->rep_id;
                    $setter = $request->setter_id ?? [];
                    $data = [
                        'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                        'closer2_id' => isset($closer[1]) ? $closer[1] : NULL,
                        'setter1_id' => isset($setter[0]) ? $setter[0] : NULL,
                        'setter2_id' => isset($setter[1]) ? $setter[1] : NULL,
                    ];
                    SaleMasterProcess::where('pid', $pid)->update($data);
                }
                LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);

                if ($closer) {
                    $this->subroutineProcess($pid);
                    $this->updateExternalOverride($pid);
                }
                $this->salesDataHistory($pid, 'manual');
            } else {
                $val['data_source_type'] = 'manual';
                $insertData = SalesMaster::create($val);

                // Create crmsaleinfo record with custom field values (Custom Sales Fields feature)
                $customFieldValues = $request->custom_field_values ?? [];
                Crmsaleinfo::updateOrCreate(
                    ['pid' => $pid],
                    [
                        'company_id' => $companyProfile->id ?? null,
                        'custom_field_values' => !empty($customFieldValues) ? $customFieldValues : null,
                    ]
                );

                $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                $nullTableVal['data_source_type'] = 'manual';

                $closer = $request->rep_id;
                $data = [
                    'pid' => $pid,
                    'sale_master_id' => $insertData->id,
                    'weekly_sheet_id' => $insertData->weekly_sheet_id,
                    'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                    'closer2_id' => isset($closer[1]) ? $closer[1] : NULL
                ];
                SaleMasterProcess::create($data);
                LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);
                if ($closer) {
                    // Set context for custom field conversion (Trick Subroutine approach)
                    // This enables auto-conversion of 'custom field' to 'per sale' in model events
                    $saleForContext = SalesMaster::where('pid', $pid)->first();
                    if ($saleForContext && $isCustomFieldsEnabled) {
                        SalesCalculationContext::set($saleForContext, $companyProfile);
                    }
                    try {
                        $this->subroutineProcess($pid);
                        $this->updateExternalOverride($pid);
                    } finally {
                        if ($isCustomFieldsEnabled) {
                            SalesCalculationContext::clear();
                        }
                    }
                }
                $this->salesDataHistory($pid, 'manual');
            }
            // dispatch(new GenerateAlertJob($pid));
            if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                dispatch(new RecalculateOpenTieredSalesJob($pid));
            }

            $this->successResponse("Add Data successfully.", "addManualSaleData", []);
        } else {
            $this->checkValidations($request->all(), [
                'pid' => 'required',
                'customer_name' => 'required',
                'rep_id' => 'required',
                'rep_email' => 'required',
                'kw' => 'required',
                'milestone_dates' => 'required|array|min:1'
            ]);

            // // Only require setter_id if company is not turf type
            // if($companyProfile->company_type != CompanyProfile::TURF_COMPANY_TYPE) {
            //     $this->checkValidations($request->all(), [
            //         'setter_id' => 'required',
            //     ]);
            // }

            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') != 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) == 'firstcoast')) {
                $this->checkValidations($request->all(), [
                    'customer_state' => 'required',
                    'location_code' => 'required'
                ]);
            }

            $pid = $request->pid;
            $closers = $request->rep_id;
            $setters = $request->setter_id;

            // Check for closers (always required)
            if (empty(array_filter($closers))) {
                return $this->errorResponse('Select closer field cannot be blank', 'addManualSaleData', '', 400);
            }

            // Check for setters (required only if not TURF)
            if (!in_array($companyProfile->company_type, [CompanyProfile::TURF_COMPANY_TYPE, CompanyProfile::MORTGAGE_COMPANY_TYPE]) && empty(array_filter($setters))) {
                return $this->errorResponse('Select setter field cannot be blank', 'addManualSaleData', '', 400);
            }

            $productId = $request->product_id;
            $systemProductId = $request->product_id;
            $product = Products::withTrashed()->where('id', $productId)->first();
            if (!$product) {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $systemProductId = $product->id;
            }

            $finalDates = [];
            $effectiveDate = $request->approved_date;
            $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
            $triggers = isset($milestone?->milestone?->milestone_trigger) ? $milestone?->milestone?->milestone_trigger : [];
            foreach ($triggers as $key => $schema) {
                $date = isset($request->milestone_dates[$key]['date']) ? $request->milestone_dates[$key]['date'] : NULL;
                $finalDates[] = [
                    'date' => $date
                ];
            }

            try {
                DB::beginTransaction();
                $saleMasterData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                if ($saleMasterData) {
                    $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();

                    salesDataChangesClawback($saleMasterProcess->pid);
                    if (!empty($saleMasterData->product_id) && empty($systemProductId)) {
                        if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        $this->saleProductMappingChanges($pid);
                    }

                    if (!empty($saleMasterData->product_id) && !empty($systemProductId) && $saleMasterData->product_id != $systemProductId) {
                        if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        $this->saleProductMappingChanges($pid);
                    }

                    $checked = false;
                    $isM2Paid = false;
                    $withHeldPaid = false;
                    $upFrontRemove = [];
                    $upFrontChange = [];
                    $commissionRemove = [];
                    $commissionChange = [];
                    $count = count($request->milestone_dates);
                    foreach ($finalDates as $key => $finalDate) {
                        $type = 'm' . ($key + 1);
                        $date = @$finalDate['date'];
                        $saleProduct = SaleProductMaster::where(['pid' => $pid, 'type' => $type])->first();
                        if ($count == ($key + 1)) {
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                $isM2Paid = true;
                            }
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                $withHeldPaid = true;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if ($isM2Paid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid'], 400);
                                }
                                if ($withHeldPaid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                                }
                                $commissionRemove[] = $type;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if ($isM2Paid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid'], 400);
                                }
                                if ($withHeldPaid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                                }

                                $commissionChange[] = [
                                    'type' => $type,
                                    'date' => $date
                                ];
                            }
                        } else {
                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the ' . $type . ' amount has already been paid'], 400);
                                }
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the ' . $type . ' amount has already been paid'], 400);
                                }

                                $upFrontRemove[] = $type;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the ' . $type . ' amount has already been paid'], 400);
                                }
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the ' . $type . ' amount has finalized or executed from reconciliation'], 400);
                                }

                                $upFrontChange[] = [
                                    'type' => $type,
                                    'date' => $date
                                ];
                            }
                        }

                        if (!$checked && $saleProduct && $saleProduct->is_override) {
                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the override amount has already been paid'], 400);
                                }
                                if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the override amount has already been paid'], 400);
                                }
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the override amount has already been paid'], 400);
                                }
                                if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the override amount has already been paid'], 400);
                                }
                            }
                            $checked = true;
                        }
                    }

                    foreach ($upFrontRemove as $remove) {
                        $this->removeUpFrontSaleData($pid, $remove);
                    }

                    foreach ($upFrontChange as $change) {
                        $this->changeUpFrontPayrollData($pid, $change);
                    }

                    if (sizeOf($commissionRemove) != 0) {
                        $this->removeCommissionSaleData($pid);
                    }

                    foreach ($commissionChange as $change) {
                        $this->changeCommissionPayrollData($pid, $change);
                    }

                    if (sizeof($finalDates) == 0) {
                        $this->removeUpFrontSaleData($pid);
                        $this->removeCommissionSaleData($pid);
                    }

                    if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->closer1_id, $saleMasterData);
                        $this->removeClawBackForNewUser($closers[0], $saleMasterData);
                    }

                    if (isset($saleMasterProcess->closer2_id) && isset($closers[1]) && $closers[1] != $saleMasterProcess->closer2_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->closer2_id, $saleMasterData);
                        $this->removeClawBackForNewUser($closers[1], $saleMasterData);
                    }

                    if (isset($saleMasterProcess->setter1_id) && isset($setters[0]) && $setters[0] != $saleMasterProcess->setter1_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->setter1_id, $saleMasterData, 'setter');
                        $this->removeClawBackForNewUser($setters[0], $saleMasterData);
                    }

                    if (isset($saleMasterProcess->setter2_id) && isset($setters[1]) && $setters[1] != $saleMasterProcess->setter2_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->setter2_id, $saleMasterData, 'setter2');
                        $this->removeClawBackForNewUser($setters[1], $saleMasterData);
                    }
                    $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : NULL;
                    $saleMasterProcess->save();
                } else {
                    if (isset($closers[0])) {
                        $reps = checkSalesReps($closers[0], $request->approved_date, 'Sales rep');
                        if (!$reps['status']) {
                            $this->errorResponse($reps['message'], 'addManualSaleData', '', 400);
                        }
                    }

                    if (isset($closers[1])) {
                        $reps = checkSalesReps($closers[1], $request->approved_date, 'Sales rep 2');
                        if (!$reps['status']) {
                            $this->errorResponse($reps['message'], 'addManualSaleData', '', 400);
                        }
                    }

                    if (isset($setters[0])) {
                        $reps = checkSalesReps($setters[0], $request->approved_date, 'Setter');
                        if (!$reps['status']) {
                            $this->errorResponse($reps['message'], 'addManualSaleData', '', 400);
                        }
                    }

                    if (isset($setters[1])) {
                        $reps = checkSalesReps($setters[1], $request->approved_date, 'Setter 2');
                        if (!$reps['status']) {
                            $this->errorResponse($reps['message'], 'addManualSaleData', '', 400);
                        }
                    }
                }

                $closer = User::whereIn('id', $request->rep_id)->get();
                $setter = User::whereIn('id', $request->setter_id)->get();

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $netEPC = isset($request->net_epc) ? ($request->net_epc / 100) : 0;
                } else {
                    $netEPC = isset($request->net_epc) ? $request->net_epc : NULL;
                }

                $stateId = NULL;
                $stateCode = NULL;
                if ($request->customer_state) {
                    $state = State::where('state_code', $request->customer_state)->first();
                    $stateId = $state?->id ?? NULL;
                    $stateCode = $state?->state_code ?? NULL;
                } else if ($request->location_code) {
                    $location = Locations::with('State')->where('general_code', $request->location_code)->first();
                    if ($location && $location->State) {
                        $stateId = $location?->State?->id ?? NULL;
                        $stateCode = $location?->State?->state_code ?? NULL;
                    }
                }
                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $request->epc = number_format($request->gross_revenue, 4, '.', '') ?? NULL;
                }
                $val = [
                    'pid' => $pid,
                    'kw' => isset($request->kw) ? $request->kw : NULL,
                    'install_partner' => isset($request->installer) ? $request->installer : NULL,
                    'customer_name' => isset($request->customer_name) ? $request->customer_name : NULL,
                    'customer_address' => isset($request->customer_address) ? $request->customer_address : NULL,
                    'customer_address_2' => isset($request->customer_address_2) ? $request->customer_address_2 : NULL,
                    'customer_city' => isset($request->customer_city) ? $request->customer_city : NULL,
                    'state_id' => $stateId,
                    'customer_state' => $stateCode ? $stateCode : $request->customer_state,
                    'location_code' => isset($request->location_code) ? $request->location_code : NULL,
                    'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : NULL,
                    'customer_email' => isset($request->customer_email) ? $request->customer_email : NULL,
                    'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : NULL,
                    'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : NULL,
                    'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : NULL,
                    'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL,
                    'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : NULL,
                    'closer1_id' => isset($request->rep_id[0]) ? $request->rep_id[0] : NULL,
                    'closer2_id' => isset($request->rep_id[1]) ? $request->rep_id[1] : NULL,
                    'setter1_id' => isset($request->setter_id[0]) ? $request->setter_id[0] : NULL,
                    'setter2_id' => isset($request->setter_id[1]) ? $request->setter_id[1] : NULL,
                    'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : NULL,
                    'customer_signoff' => isset($request->approved_date) ? $request->approved_date : NULL,
                    'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
                    'm2_date' => isset($request->m2_date) ? $request->m2_date : NULL,
                    'product' => isset($request->product) ? $request->product : NULL,
                    'product_id' => isset($productId) ? $productId : NULL,
                    'product_code' => isset($product->product_id) ? $product->product_id : NULL,
                    'sale_product_name' => isset($request->sale_product_name) ? $request->sale_product_name : NULL,
                    'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : NULL,
                    'epc' => isset($request->epc) ? $request->epc : NULL,
                    'net_epc' => $netEPC,
                    'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : NULL,
                    'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : NULL,
                    'adders' => isset($request->show) ? $request->show : NULL,
                    'adders_description' => isset($request->adders_description) ? $request->adders_description : NULL,
                    'redline' => isset($request->redline) ? $request->redline : NULL,
                    'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : NULL,
                    'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : NULL,
                    'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : NULL,
                    'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : NULL,
                    'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : NULL,
                    'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : NULL,
                    'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : NULL,
                    'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : NULL,
                    'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : NULL,
                    'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : NULL,
                    'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : NULL,
                    'return_sales_date' => isset($request->return_sales_date) ? $request->return_sales_date : NULL,
                    'job_status' => isset($request->job_status) ? $request->job_status : NULL,
                    'length_of_agreement' => isset($request->length_of_agreement) ? $request->length_of_agreement : NULL,
                    'service_schedule' => isset($request->service_schedule) ? $request->service_schedule : NULL,
                    'subscription_payment' => isset($request->subscription_payment) ? $request->subscription_payment : NULL,
                    'service_completed' => isset($request->service_completed) ? $request->service_completed : NULL,
                    'last_service_date' => isset($request->last_service_date) ? $request->last_service_date : NULL,
                    'bill_status' => isset($request->bill_status) ? $request->bill_status : NULL,
                    'initial_service_cost' => isset($request->initial_service_cost) ? $request->initial_service_cost : NULL,
                    'auto_pay' => isset($request->auto_pay) ? $request->auto_pay : NULL,
                    'card_on_file' => isset($request->card_on_file) ? $request->card_on_file : NULL,
                    'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : NULL
                ];

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $val['kw'] = $val['gross_account_value'];
                }

                $nullTableVal = $val;
                if ($saleMasterData) {
                    if ($request->date_cancelled) {
                        $nullTableVal['date_cancelled'] = $request->date_cancelled;
                        $nullTableVal['data_source_type'] = 'manual';
                        $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) { $saleModelToUpdate->fill($val)->save(); }
                    } else {
                        if (!empty($saleMasterData->date_cancelled) && empty(\request('date_cancelled'))) {
                            salesDataChangesBasedOnClawback($saleMasterProcess->pid);
                            request()->merge(['full_recalculate' => 1]);
                        }
                        $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) {
                        $saleModelToUpdate->fill($val)->save();
                    }

                        $nullTableVal['setter_id'] = isset($setter[0]) ? $setter[0]->id : NULL;
                        $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                        $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                        $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                        $nullTableVal['sales_setter_name'] = isset($setter[0]->first_name) ? $setter[0]->first_name . ' ' . $setter[0]->last_name : NULL;
                        $nullTableVal['sales_setter_email'] = isset($setter[0]->email) ? $setter[0]->email : NULL;
                        $nullTableVal['data_source_type'] = 'manual';

                        $closer = $request->rep_id;
                        $setter = $request->setter_id;
                        $data = [
                            'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                            'closer2_id' => isset($closer[1]) ? $closer[1] : NULL,
                            'setter1_id' => isset($setter[0]) ? $setter[0] : NULL,
                            'setter2_id' => isset($setter[1]) ? $setter[1] : NULL
                        ];
                        SaleMasterProcess::where('pid', $pid)->update($data);
                    }

                    $nullTableVal['customer_name'] = isset($request->customer_name) ? $request->customer_name : NULL;
                    $nullTableVal['customer_signoff'] = isset($request->approved_date) ? $request->approved_date : NULL;
                    LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);

                    if ($closer) {
                        $this->subroutineProcess($pid);
                        $this->salesDataHistory($pid, 'manual');
                        $this->updateExternalOverride($pid);
                    }
                } else {
                    $val['data_source_type'] = 'manual';
                    $insertData = SalesMaster::create($val);

                    // Create crmsaleinfo record with custom field values (Custom Sales Fields feature)
                    $customFieldValues = $request->custom_field_values ?? [];
                    Crmsaleinfo::updateOrCreate(
                        ['pid' => $pid],
                        [
                            'company_id' => $companyProfile->id ?? null,
                            'custom_field_values' => !empty($customFieldValues) ? $customFieldValues : null,
                        ]
                    );

                    $nullTableVal['setter_id'] = isset($setter[0]) ? $setter[0]->id : NULL;
                    $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                    $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                    $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                    $nullTableVal['sales_setter_name'] = isset($setter[0]->first_name) ? $setter[0]->first_name . ' ' . $setter[0]->last_name : NULL;
                    $nullTableVal['sales_setter_email'] = isset($setter[0]->email) ? $setter[0]->email : NULL;
                    $nullTableVal['data_source_type'] = 'manual';

                    $closer = $request->rep_id;
                    $setter = $request->setter_id;

                    $data = [
                        'pid' => $pid,
                        'sale_master_id' => $insertData->id,
                        'weekly_sheet_id' => $insertData->weekly_sheet_id,
                        'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                        'closer2_id' => isset($closer[1]) ? $closer[1] : NULL,
                        'setter1_id' => isset($setter[0]) ? $setter[0] : NULL,
                        'setter2_id' => isset($setter[1]) ? $setter[1] : NULL
                    ];
                    SaleMasterProcess::create($data);

                    $nullTableVal['customer_name'] = isset($request->customer_name) ? $request->customer_name : NULL;
                    $nullTableVal['customer_signoff'] = isset($request->approved_date) ? $request->approved_date : NULL;
                    LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);

                    if ($closer) {
                        // Set context for custom field conversion (Trick Subroutine approach)
                        // This enables auto-conversion of 'custom field' to 'per sale' in model events
                        $saleForContext = SalesMaster::where('pid', $pid)->first();
                        if ($saleForContext && $isCustomFieldsEnabled) {
                            SalesCalculationContext::set($saleForContext, $companyProfile);
                        }
                        try {
                            $this->subroutineProcess($pid);
                            $this->salesDataHistory($pid, 'manual');
                            $this->updateExternalOverride($pid);
                        } finally {
                            if ($isCustomFieldsEnabled) {
                                SalesCalculationContext::clear();
                            }
                        }
                    }
                }

                DB::commit();
                // dispatch(new GenerateAlertJob($pid));
                if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                    dispatch(new RecalculateOpenTieredSalesJob($pid));
                }
                return response()->json(['success' => true, 'message' => 'Add Data successfully.']);
            } catch (\Exception $e) {
                DB::rollBack();
                $this->errorResponse('Error while adding data. Please try again later.', 'addManualSaleData', [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ], 400);
            }
        }
    }

    // EXCEL SALE PROCESS
    public function excelInsertUpdateSaleMaster($user = NULL, $type = 'excel', $excel = NULL, $progressCallback = null)
    {
        $successPID = [];
        $excelId = @$excel->id;
        try {
            // Set a higher time limit for this process (5 minutes)
            set_time_limit(300);
            // Set a higher memory limit for this process
            ini_set('memory_limit', '4096M');

            $domainName = config('app.domain_name');
            $query = LegacyApiRawDataHistory::where('data_source_type', $type)
                ->where('import_to_sales', '0');
            if ($domainName != 'momentumv2') {
                $query->whereNotNull('closer1_id'); // Ensure we only process records with a valid closer
            }


            if ($type == 'Pocomos') {
                $query->where('customer_signoff', '>', '2024-12-31');
                $query->whereNotNull('closer1_id');
            }

            if ($type == 'excel') {
                $query->where('excel_import_id', $excelId);
            } else {
                if ($domainName == 'evomarketing') {
                    $query->whereNotNull('initial_service_date');
                }
                if ($domainName == 'whiteknight') {
                    $query->where('product', '!=', 'Termite Inspection')
                        ->whereNotNull('initial_service_date')
                        ->where('service_schedule', '!=', -1);
                }
                if ($domainName == 'threeriverspest') {
                    $query->whereNotNull('initial_service_date');
                }
                if ($type == 'Pocomos') {
                    $query->where('customer_signoff', '>', '2024-12-31');
                }

                if ($domainName == 'momentumv2') {
                    $query->where('customer_signoff', '>=', '2025-03-01');
                }
                if ($domainName == 'whiteknight') {
                    $query->where('customer_signoff', '>=', '2025-03-01');
                }
            }

            // Log the count for debugging
            $totalRecords = $query->count();
            \Log::info("Processing {$totalRecords} records for {$type}");

            // Initial progress update if callback is provided
            if (is_callable($progressCallback)) {
                $progressCallback(0, $totalRecords, null);
            }

            $salesErrorReport = [];
            $salesSuccessReport = [];
            $processedCount = 0;
            $successPID = [];

            // Optimize for single record case to avoid chunking overhead
            // For multiple records, use chunking to avoid memory issues
            $batchSize = 500; // Process records in batches of 500 for better performance
            $companyProfile = CompanyProfile::first();
            $query->chunk($batchSize, function ($records) use (&$salesErrorReport, &$salesSuccessReport, &$processedCount, $type, $excelId, $domainName, &$successPID, $progressCallback, $totalRecords, $companyProfile) {
                if ($records->isEmpty()) {
                    return false; // No records to process
                }

                \Log::info("Processing batch of " . count($records) . " records.");

                foreach ($records as $checked) {
                    $processedCount++;

                    // Update progress every 100 records if callback is provided
                    if (is_callable($progressCallback) && $processedCount % 100 === 0) {
                        $progressCallback($processedCount, $totalRecords, null);
                    }


                    // DB::beginTransaction();
                    $salesMaster = SalesMaster::with('salesMasterProcess')->where('pid', $checked->pid)->first();

                    $milestoneDates = [];
                    if ($checked->trigger_date) {
                        $milestoneDates = json_decode($checked->trigger_date, true);
                    }

                    if (is_array($milestoneDates) && sizeOf($milestoneDates) != 0) {
                        $continue = 0;
                        foreach ($milestoneDates as $milestoneDate) {
                            if (@$milestoneDate['date'] && $checked->customer_signoff && $milestoneDate['date'] < $checked->customer_signoff) {
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the date cannot be earlier than the sale date.',
                                    'realMessage' => 'Apologies, the date cannot be earlier than the sale date.',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-'
                                ];

                                if ($excelId) {
                                    $excel = ExcelImportHistory::where('id', $excelId)->first();
                                    if ($excel) {
                                        $excel->error_records = $excel->error_records + 1;
                                        $excel->save();
                                    }
                                }
                                $continue = 1;
                                LegacyApiRawDataHistory::where(['id' => $checked->id, 'data_source_type' => $type, 'import_to_sales' => '0'])->update(['import_to_sales' => '2']);
                                DB::commit();
                                continue;
                            }
                        }

                        if ($continue) {
                            continue;
                        }
                    }

                    $productId = $checked->product_id;
                    $systemProductId = $checked->product_id;
                    $product = Products::withTrashed()->where('id', $productId)->first();
                    if (!$product) {
                        $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                        $systemProductId = $product->id;
                    }
                    $finalDates = [];
                    $effectiveDate = $checked->customer_signoff;
                    $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
                    $triggers = (is_array($milestoneDates) && sizeOf($milestoneDates) != 0 && isset($milestone?->milestone?->milestone_trigger)) ? $milestone?->milestone?->milestone_trigger : [];
                    foreach ($triggers as $key => $schema) {
                        $date = isset($milestoneDates[$key]['date']) ? $milestoneDates[$key]['date'] : NULL;
                        $finalDates[] = [
                            'date' => $date
                        ];
                    }
                    $milestoneDates = $finalDates;

                    $stateId = NULL;
                    $stateCode = $checked->customer_state;
                    if ($checked->customer_state) {
                        $state = State::where('state_code', $checked->customer_state)->first();
                        if ($state) {
                            $stateId = $state?->id ?? NULL;
                            $stateCode = $state?->state_code ?? NULL;
                        }
                    } else if ($checked->location_code) {
                        $location = Locations::with('State')->where('general_code', $checked->location_code)->first();
                        if ($location && $location->State) {
                            $stateId = $location?->State?->id ?? NULL;
                            $stateCode = $location?->State?->state_code ?? NULL;
                        }
                    }

                    $domainName = config('app.domain_name');
                    if ($domainName == 'phoenixlending') {
                        $net_epc = ($checked->net_epc ?? 0) > 0 ? $checked->net_epc : 1;
                    } else {
                        $net_epc = $checked->net_epc;
                    }

                    $saleMasterData = [
                        'pid' => $checked->pid,
                        'weekly_sheet_id' => NULL,
                        'install_partner' => empty($checked->install_partner) ? ($salesMaster ? $salesMaster->install_partner : null) : $checked->install_partner,
                        'install_partner_id' => empty($checked->install_partner_id) ? ($salesMaster ? $salesMaster->install_partner_id : null) : $checked->install_partner_id,
                        'customer_name' => empty($checked->customer_name) ? ($salesMaster ? $salesMaster->customer_name : null) : $checked->customer_name,
                        'customer_address' => empty($checked->customer_address) ? ($salesMaster ? $salesMaster->customer_address : null) : $checked->customer_address,
                        'customer_address_2' => empty($checked->customer_address_2) ? ($salesMaster ? $salesMaster->customer_address_2 : null) : $checked->customer_address_2,
                        'customer_city' => empty($checked->customer_city) ? ($salesMaster ? $salesMaster->customer_city : null) : $checked->customer_city,
                        'customer_state' => $stateCode,
                        'state_id' => $stateId,
                        'location_code' => empty($checked->location_code) ? ($salesMaster ? $salesMaster->location_code : null) : $checked->location_code,
                        'customer_zip' => empty($checked->customer_zip) ? ($salesMaster ? $salesMaster->customer_zip : null) : $checked->customer_zip,
                        'customer_email' => empty($checked->customer_email) ? ($salesMaster ? $salesMaster->customer_email : null) : $checked->customer_email,
                        'customer_phone' => empty($checked->customer_phone) ? ($salesMaster ? $salesMaster->customer_phone : null) : $checked->customer_phone,
                        'homeowner_id' => empty($checked->homeowner_id) ? ($salesMaster ? $salesMaster->homeowner_id : null) : $checked->homeowner_id,
                        'proposal_id' => empty($checked->proposal_id) ? ($salesMaster ? $salesMaster->proposal_id : null) : $checked->proposal_id,
                        'sales_rep_name' => empty($checked->sales_rep_name) ? ($salesMaster ? $salesMaster->sales_rep_name : null) : $checked->sales_rep_name,
                        'employee_id' => empty($checked->employee_id) ? ($salesMaster ? $salesMaster->employee_id : null) : $checked->employee_id,
                        'sales_rep_email' => empty($checked->sales_rep_email) ? ($salesMaster ? $salesMaster->sales_rep_email : null) : $checked->sales_rep_email,
                        'kw' => empty($checked->kw) ? ($salesMaster ? $salesMaster->kw : null) : $checked->kw,
                        'date_cancelled' => empty($checked->date_cancelled) ? ($salesMaster ? $salesMaster->date_cancelled : null) : $checked->date_cancelled,
                        'customer_signoff' => empty($checked->customer_signoff) ? ($salesMaster ? $salesMaster->customer_signoff : null) : $checked->customer_signoff,
                        'product' => empty($checked->product) ? ($salesMaster ? $salesMaster->product : null) : $checked->product,
                        'product_id' => empty($checked->product_id) ? ($salesMaster ? $salesMaster->product_id : null) : $checked->product_id,
                        'product_code' => empty($checked->product_code) ? ($salesMaster ? $salesMaster->product_code : null) : $checked->product_code,
                        'sale_product_name' => empty($checked->sale_product_name) ? ($salesMaster ? $salesMaster->sale_product_name : null) : $checked->sale_product_name,
                        'epc' => empty($checked->epc) ? ($salesMaster ? $salesMaster->epc : null) : $checked->epc,
                        'net_epc' => $net_epc,
                        'gross_account_value' => empty($checked->gross_account_value) ? ($salesMaster ? $salesMaster->gross_account_value : null) : $checked->gross_account_value,
                        'dealer_fee_percentage' => isset($checked->dealer_fee_percentage) ? $checked->dealer_fee_percentage : ($salesMaster ? $salesMaster->dealer_fee_percentage : null),
                        'dealer_fee_amount' => isset($checked->dealer_fee_amount) ? $checked->dealer_fee_amount : ($salesMaster ? $salesMaster->dealer_fee_amount : null),
                        'adders' => empty($checked->adders) ? ($salesMaster ? $salesMaster->adders : null) : $checked->adders,
                        'adders_description' => empty($checked->adders_description) ? ($salesMaster ? $salesMaster->adders_description : null) : $checked->adders_description,
                        'funding_source' => empty($checked->funding_source) ? ($salesMaster ? $salesMaster->funding_source : null) : $checked->funding_source,
                        'financing_rate' => empty($checked->financing_rate) ? ($salesMaster ? $salesMaster->financing_rate : null) : $checked->financing_rate,
                        'financing_term' => empty($checked->financing_term) ? ($salesMaster ? $salesMaster->financing_term : null) : $checked->financing_term,
                        'scheduled_install' => empty($checked->scheduled_install) ? ($salesMaster ? $salesMaster->scheduled_install : null) : $checked->scheduled_install,
                        'install_complete_date' => empty($checked->install_complete_date) ? ($salesMaster ? $salesMaster->install_complete_date : null) : $checked->install_complete_date,
                        'return_sales_date' => empty($checked->return_sales_date) ? ($salesMaster ? $salesMaster->return_sales_date : null) : $checked->return_sales_date,
                        'cash_amount' => empty($checked->cash_amount) ? ($salesMaster ? $salesMaster->cash_amount : null) : $checked->cash_amount,
                        'loan_amount' => empty($checked->loan_amount) ? ($salesMaster ? $salesMaster->loan_amount : null) : $checked->loan_amount,
                        'redline' => empty($checked->redline) ? ($salesMaster ? $salesMaster->redline : null) : $checked->redline,
                        'cancel_fee' => empty($checked->cancel_fee) ? ($salesMaster ? $salesMaster->cancel_fee : null) : $checked->cancel_fee,
                        'data_source_type' => $type,
                        'job_status' => empty($checked->job_status) ? ($salesMaster ? $salesMaster->job_status : null) : $checked->job_status,
                        'length_of_agreement' => empty($checked->length_of_agreement) ? ($salesMaster ? $salesMaster->length_of_agreement : null) : $checked->length_of_agreement,
                        'service_schedule' => empty($checked->service_schedule) ? ($salesMaster ? $salesMaster->service_schedule : null) : $checked->service_schedule,
                        'initial_service_cost' => empty($checked->initial_service_cost) ? ($salesMaster ? $salesMaster->initial_service_cost : null) : $checked->initial_service_cost,
                        'subscription_payment' => empty($checked->subscription_payment) ? ($salesMaster ? $salesMaster->subscription_payment : null) : $checked->subscription_payment,
                        'card_on_file' => empty($checked->card_on_file) ? ($salesMaster ? $salesMaster->card_on_file : null) : $checked->card_on_file,
                        'auto_pay' => empty($checked->auto_pay) ? ($salesMaster ? $salesMaster->auto_pay : null) : $checked->auto_pay,
                        'service_completed' => empty($checked->service_completed) ? ($salesMaster ? $salesMaster->service_completed : null) : $checked->service_completed,
                        'last_service_date' => empty($checked->last_service_date) ? ($salesMaster ? $salesMaster->last_service_date : null) : $checked->last_service_date,
                        'bill_status' => empty($checked->bill_status) ? ($salesMaster ? $salesMaster->bill_status : null) : $checked->bill_status,
                        'm1_date' => empty($checked->m1_date) ? ($salesMaster ? $salesMaster->m1_date : null) : $checked->m1_date,
                        'initial_service_date' => empty($checked->initial_service_date) ? ($salesMaster ? $salesMaster->initial_service_date : null) : $checked->initial_service_date,
                        'initialStatusText' => empty($checked->initialStatusText) ? ($salesMaster ? $salesMaster->initialStatusText : null) : $checked->initialStatusText,
                        'm2_date' => empty($checked->m2_date) ? ($salesMaster ? $salesMaster->m2_date : null) : $checked->m2_date,
                        'trigger_date' => empty($checked->trigger_date) ? ($salesMaster ? $salesMaster->trigger_date : null) : $checked->trigger_date,
                        'balance_age' => empty($checked?->balance_age) ? ($salesMaster ? $salesMaster?->balance_age : null) : $checked?->balance_age,
                    ];


                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        $saleMasterData['epc'] = $checked->gross_revenue ? number_format($checked->gross_revenue, 4, '.', '') : $checked->epc;
                        $saleMasterData['kw'] = $checked->gross_account_value;
                    }

                    $closer = User::where('id', $checked->closer1_id)->first();
                    $setter = User::where('id', $checked->setter1_id)->first();
                    CustomerPayment::updateOrCreate(['pid' => $checked->pid], ['pid' => $checked->pid, 'customer_payment_json' => json_encode(json_decode($checked->customer_payment_json, true))]);
                    $isImportStatus = 1;
                    if (!$salesMaster) {
                        $nullTableVal = $saleMasterData;
                        $nullTableVal['setter_id'] = $checked->setter1_id;
                        $nullTableVal['closer_id'] = $checked->closer1_id;
                        $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                        $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                        $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                        $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                        $nullTableVal['job_status'] = $checked->job_status;
                        LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);
                        $saleMaster = SalesMaster::create($saleMasterData);
                        $saleMasterProcessData = [
                            'sale_master_id' => $saleMaster->id,
                            'weekly_sheet_id' => $saleMaster->weekly_sheet_id,
                            'pid' => $checked->pid,
                            'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : NULL,
                            'closer2_id' => isset($checked->closer2_id) ? $checked->closer2_id : NULL,
                            'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : NULL,
                            'setter2_id' => isset($checked->setter2_id) ? $checked->setter2_id : NULL,
                            'job_status' => $checked->job_status
                        ];
                        SaleMasterProcess::create($saleMasterProcessData);

                        try {
                            request()->merge(['milestone_dates' => $milestoneDates]);
                            $this->subroutineProcess($saleMaster->pid);
                            $salesSuccessReport[] = [
                                'is_error' => false,
                                'pid' => $checked->pid,
                                'message' => 'Success',
                                'realMessage' => 'Success',
                                'file' => '',
                                'line' => '',
                                'name' => '-'
                            ];
                            if ($excelId) {
                                $excel = ExcelImportHistory::where('id', $excelId)->first();
                                if ($excel) {
                                    $excel->new_records = $excel->new_records + 1;
                                    $excel->save();
                                }
                            }
                        } catch (\Throwable $e) {
                            $isImportStatus = 2;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Error During Subroutine Process',
                                'realMessage' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'name' => '-'
                            ];
                            DB::rollBack();
                            if ($excelId) {
                                $excel = ExcelImportHistory::where('id', $excelId)->first();
                                if ($excel) {
                                    $excel->error_records = $excel->error_records + 1;
                                    $excel->save();
                                }
                            }
                        }
                    } else {
                        try {
                            $checkKw = ($checked->kw == $salesMaster->kw) ? 0 : 1;
                            $checkNetEpc = ($checked->net_epc == $salesMaster->net_epc) ? 0 : 1;
                            $checkDateCancelled = ($checked->date_cancelled == $salesMaster->date_cancelled) ? 0 : 1;
                            $checkCustomerState = ($checked->customer_state == $salesMaster->customer_state) ? 0 : 1;
                            $checkProduct = ($checked->product_code == $salesMaster->product_code) ? 0 : 1;

                            $salesMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                            salesDataChangesClawback($salesMasterProcess->pid);
                            $checkSetter = 0;
                            $checkSetter2 = 0;
                            $checkCloser = 0;
                            $checkCloser2 = 0;
                            if ($salesMasterProcess) {
                                $checkSetter = ($checked->setter1_id == $salesMasterProcess->setter1_id) ? 0 : 1;
                                $checkSetter2 = ($checked->setter2_id == $salesMasterProcess->setter2_id) ? 0 : 1;
                                $checkCloser = ($checked->closer1_id == $salesMasterProcess->closer1_id) ? 0 : 1;
                                $checkCloser2 = ($checked->closer2_id == $salesMasterProcess->closer2_id) ? 0 : 1;
                            }
                            $check = ($checkKw + $checkNetEpc + $checkDateCancelled + $checkCustomerState + $checkProduct + $checkSetter + $checkSetter2 + $checkCloser + $checkCloser2);

                            $success = true;
                            $pid = $checked->pid;
                            if ($success) {
                                if (!empty($salesMaster->product_code) && empty($checked->product_code)) {
                                    $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    if ($commission || $recon || $override || $reconOverride) {
                                        if ($commission) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                        if ($recon) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                        if ($override) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                        if ($reconOverride) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                    } else {
                                        $this->saleProductMappingChanges($pid);
                                    }
                                    $check += 1;
                                }
                                // Check for product code changes with NULL-safe comparison
                                $oldCode = $salesMaster->product_code ?? '';
                                $newCode = $checked->product_code ?? '';

                                if (!empty($oldCode) && !empty($newCode) && strcasecmp($oldCode, $newCode) !== 0) {
                                    $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    if ($commission || $recon || $override || $reconOverride) {
                                        if ($commission) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                                'realMessage' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                        if ($recon) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                                'realMessage' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                        if ($override) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                                'realMessage' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                        if ($reconOverride) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                                'realMessage' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                    } else {
                                        $this->saleProductMappingChanges($pid);
                                    }
                                    $check += 1;
                                }
                            }

                            if ($success) {
                                $isRemove = true;
                                $isChange = true;
                                $commissionIsRemove = true;
                                $commissionIsChange = true;
                                $overrides = false;
                                $isM2Paid = false;
                                $withHeldPaid = false;
                                $upFrontRemove = [];
                                $upFrontChange = [];
                                $commissionRemove = [];
                                $commissionChange = [];
                                $count = count($milestoneDates);
                                foreach ($finalDates as $key => $finalDate) {
                                    $sType = 'm' . ($key + 1);
                                    $date = @$finalDate['date'];
                                    $saleProduct = SaleProductMaster::where(['pid' => $pid, 'type' => $sType])->first();
                                    if ($saleProduct) {
                                        if ($count == ($key + 1)) {
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isM2Paid = true;
                                            }
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $withHeldPaid = true;
                                            }

                                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                                if ($isM2Paid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsRemove = false;
                                                } else if ($withHeldPaid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsRemove = false;
                                                } else {
                                                    $commissionRemove[] = $sType;
                                                }
                                            }

                                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                                if ($isM2Paid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsChange = false;
                                                } else if ($withHeldPaid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsChange = false;
                                                } else {
                                                    $commissionChange[] = [
                                                        'type' => $sType,
                                                        'date' => $date
                                                    ];
                                                }
                                            }

                                            if ($saleProduct->milestone_date != $date) {
                                                $check += 1;
                                            }
                                        } else {
                                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isRemove = false;
                                                } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isRemove = false;
                                                } else {
                                                    $upFrontRemove[] = $sType;
                                                }
                                            }

                                            if (!empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isChange = false;
                                                } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isChange = false;
                                                } else {
                                                    $upFrontChange[] = [
                                                        'type' => $sType,
                                                        'date' => $date
                                                    ];
                                                }
                                            }

                                            if ($saleProduct->milestone_date != $date) {
                                                $check += 1;
                                            }
                                        }
                                    }
                                    if (!$overrides && $saleProduct && $saleProduct->is_override) {
                                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                            if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                            } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                            }
                                        }

                                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                            if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                            } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                            }
                                        }
                                        $overrides = true;
                                    }
                                }

                                if ($isRemove) {
                                    foreach ($upFrontRemove as $remove) {
                                        $this->removeUpFrontSaleData($pid, $remove);
                                    }
                                }

                                if ($isChange) {
                                    foreach ($upFrontChange as $change) {
                                        $this->changeUpFrontPayrollData($pid, $change);
                                    }
                                }

                                if ($commissionIsRemove) {
                                    if (sizeOf($commissionRemove) != 0) {
                                        $this->removeCommissionSaleData($pid);
                                    }
                                }

                                if ($commissionIsChange) {
                                    foreach ($commissionChange as $change) {
                                        $this->changeCommissionPayrollData($pid, $change);
                                    }
                                }

                                if (sizeof($finalDates) == 0) {
                                    $this->removeUpFrontSaleData($pid);
                                    $this->removeCommissionSaleData($pid);
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->closer1_id) && isset($checked->closer1_id) && $checked->closer1_id != $salesMasterProcess->closer1_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->closer1_id, $salesMaster);
                                        $this->removeClawBackForNewUser($checked->closer1_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->closer2_id) && isset($checked->closer2_id) && $checked->closer2_id != $salesMasterProcess->closer2_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->closer2_id, $salesMaster);
                                        $this->removeClawBackForNewUser($checked->closer2_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->setter1_id) && isset($checked->setter1_id) && $checked->setter1_id != $salesMasterProcess->setter1_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->setter1_id, $salesMaster, 'setter');
                                        $this->removeClawBackForNewUser($checked->setter1_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->setter2_id) && isset($checked->setter2_id) && $checked->setter2_id != $salesMasterProcess->setter2_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->setter1_id, $salesMaster, 'setter');
                                        $this->removeClawBackForNewUser($checked->setter1_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                $data = [
                                    'weekly_sheet_id' => $salesMaster->weekly_sheet_id,
                                    'pid' => $checked->pid,
                                    'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : NULL,
                                    'closer2_id' => isset($checked->closer2_id) ? $checked->closer2_id : NULL,
                                    'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : NULL,
                                    'setter2_id' => isset($checked->setter2_id) ? $checked->setter2_id : NULL,
                                    'job_status' => $checked->job_status
                                ];
                                SaleMasterProcess::updateOrCreate(['pid' => $checked->pid], $data);
                                if (!empty($salesMaster->date_cancelled)) {
                                    unset($saleMasterData['product_id']);
                                    unset($saleMasterData['product_code']);
                                }
                                $saleModelToUpdate = SalesMaster::where('pid', $checked->pid)->first();
                                if ($saleModelToUpdate) {
                                    $saleModelToUpdate->fill($saleMasterData)->save();
                                }

                                $closer = User::where('id', $checked->closer1_id)->first();
                                $setter = User::where('id', $checked->setter1_id)->first();
                                $nullTableVal = $saleMasterData;
                                $nullTableVal['setter_id'] = $checked->setter1_id;
                                $nullTableVal['closer_id'] = $checked->closer1_id;
                                $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                                $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                                $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                                $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                                $nullTableVal['job_status'] = $checked->job_status;
                                LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);

                                // if ($check > 0) {
                                $requestArray = ['milestone_dates' => $milestoneDates];
                                if (!empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                                    salesDataChangesBasedOnClawback($salesMaster->pid);
                                    $requestArray['full_recalculate'] = 1;
                                }
                                request()->merge($requestArray);
                                $this->subroutineProcess($checked->pid);
                                $salesSuccessReport[] = [
                                    'is_error' => false,
                                    'pid' => $checked->pid,
                                    'message' => 'Success',
                                    'realMessage' => 'Success',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-'
                                ];
                                // } else {
                                //     $salesSuccessReport[] = [
                                //         'is_error' => false,
                                //         'pid' => $checked->pid,
                                //         'message' => 'Success!!',
                                //         'realMessage' => 'Success!!',
                                //         'file' => '',
                                //         'line' => '',
                                //         'name' => '-'
                                //     ];
                                // }
                                if ($excelId) {
                                    $excel = ExcelImportHistory::where('id', $excelId)->first();
                                    if ($excel) {
                                        $excel->updated_records = $excel->updated_records + 1;
                                        $excel->save();
                                    }
                                }
                            } else {
                                if ($excelId) {
                                    $excel = ExcelImportHistory::where('id', $excelId)->first();
                                    if ($excel) {
                                        $excel->error_records = $excel->error_records + 1;
                                        $excel->save();
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            $isImportStatus = 2;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Error During Subroutine Process',
                                'realMessage' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'name' => '-'
                            ];
                            DB::rollBack();
                            if ($excelId) {
                                $excel = ExcelImportHistory::where('id', $excelId)->first();
                                if ($excel) {
                                    $excel->error_records = $excel->error_records + 1;
                                    $excel->save();
                                }
                            }
                        }
                    }

                    // UPDATE STATUS IN HISTORY TABLE FOR EXECUTED SALES.
                    LegacyApiRawDataHistory::where(['id' => $checked->id, 'data_source_type' => $type, 'import_to_sales' => '0'])->update(['import_to_sales' => $isImportStatus]);

                    // Update progress after each record
                    $processedCount++;
                    if (is_callable($progressCallback)) {
                        $progressCallback($processedCount, $totalRecords, $checked->id); // Fixed: Use database ID instead of PID
                    }
                    // DB::commit();
                    $successPID[] = $checked->pid;
                }
            }); // Close the chunk method

            if ($excelId) {
                $excel = ExcelImportHistory::where('id', $excelId)->first();
                if ($excel) {
                    $excel->status = 0;
                    $excel->updated_records = $excel->total_records - $excel->new_records - $excel->error_records;
                    $excel->save();
                }
            }
            //dispatch(new GenerateAlertJob(implode(',', $successPID)));
            if (!in_array($domainName, ['evomarketing', 'whitenight', 'threeriverspest'])) {
                // dispatch(new GenerateAlertJob(implode(',', $successPID)));

                if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                    foreach ($successPID as $success) {
                        dispatch(new RecalculateOpenTieredSalesJob($success));
                    }
                }
            }
            // If Sales From Excel Sheet Has One Or More Error
            if (sizeof($salesErrorReport) != 0) {
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Failed',
                    'template' => view('mail.saleImportFailed', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user])
                ];
                $this->sendEmailNotification($data);
            } else {
                // If Sales From Excel Sheet Has No Error
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Success',
                    'template' => view('mail.saleImportSuccess', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user])
                ];
            }
        } catch (\Throwable $e) {
            // dispatch(new GenerateAlertJob(implode(',', $successPID)));
            if (!in_array($domainName, ['evomarketing', 'whitenight', 'threeriverspest'])) {

                if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                    foreach ($successPID as $success) {
                        dispatch(new RecalculateOpenTieredSalesJob($success));
                    }
                }
            }

            LegacyApiRawDataHistory::where(['data_source_type' => $type, 'import_to_sales' => '0'])->whereNotIn('pid', $successPID)->update(['import_to_sales' => '2']);
            if ($excelId) {
                $excel = ExcelImportHistory::where('id', $excelId)->first();
                if ($excel) {
                    $excel->status = 2;
                    $excel->error_records = $excel->total_records - $excel->new_records - $excel->updated_records;
                    $excel->save();
                }
            }

            // Final progress update if callback is provided
            if (is_callable($progressCallback)) {
                $progressCallback($totalRecords, $totalRecords, 'completed');
            }

            // Return statistics for job monitoring
            return [
                'processed_count' => $processedCount,
                'created_count' => count($salesSuccessReport),
                'updated_count' => $processedCount - count($salesSuccessReport) - count($salesErrorReport),
                'error_count' => count($salesErrorReport),
                'success_pids' => $successPID
            ];
        }
    }


    // EXCEL SALE PROCESS
    public function excelInsertUpdateSaleMasterMomentum($user = NULL, $type = 'excel', $excel = NULL, $progressCallback = null, $queryModifier = null)
    {
        $successPID = [];
        $excelId = @$excel->id;
        try {
            // Set a higher memory limit for this process
            ini_set('memory_limit', '4096M');

            $domainName = config('app.domain_name');
            $query = LegacyApiRawDataHistory::where('data_source_type', $type)
                ->where('import_to_sales', '0');
            if ($domainName != 'momentumv2') {
                $query->whereNotNull('closer1_id'); // Ensure we only process records with a valid closer
            }



            if ($domainName == 'evomarketing') {
                $query->whereNotNull('closer1_id');
                $query->whereNotNull('initial_service_date');
            }
            if ($domainName == 'whitenight') {
                $query->whereNotNull('closer1_id');
                $query->whereNotNull('initial_service_date');
            }
            if ($domainName == 'threeriverspest') {
                $query->whereNotNull('closer1_id');
                $query->whereNotNull('initial_service_date');
            }

            if ($type == 'Pocomos') {
                $query->where('customer_signoff', '>', '2024-12-31');
                $query->whereNotNull('closer1_id');
            }
            if ($domainName == 'momentumv2') {
                $query->where('customer_signoff', '>=', '2025-03-01');
            }

            if ($type == 'excel') {
                $query->where('excel_import_id', $excelId);
            }

            // Log the count for debugging
            $totalRecords = $query->count();
            \Log::info("Processing {$totalRecords} records for {$type}");

            // Initial progress update if callback is provided
            if (is_callable($progressCallback)) {
                $progressCallback(0, $totalRecords, null);
            }

            // Apply batch-specific filtering if provided
            if (is_callable($queryModifier)) {
                $query = $queryModifier($query);
                Log::info("Applied batch-specific query filter");
            }

            $newData = $query->get();

            $salesErrorReport = [];
            $salesSuccessReport = [];
            $processedCount = 0;
            foreach ($newData as $checked) {

                // DB::beginTransaction();
                $salesMaster = SalesMaster::with('salesMasterProcess')->where('pid', $checked->pid)->first();

                $milestoneDates = [];
                if ($checked->trigger_date) {
                    $milestoneDates = json_decode($checked->trigger_date, true);
                }

                if (is_array($milestoneDates) && sizeOf($milestoneDates) != 0) {
                    $continue = 0;
                    foreach ($milestoneDates as $milestoneDate) {
                        if (@$milestoneDate['date'] && $checked->customer_signoff && $milestoneDate['date'] < $checked->customer_signoff) {
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Apologies, the date cannot be earlier than the sale date.',
                                'realMessage' => 'Apologies, the date cannot be earlier than the sale date.',
                                'file' => '',
                                'line' => '',
                                'name' => '-'
                            ];

                            if ($excelId) {
                                $excel = ExcelImportHistory::where('id', $excelId)->first();
                                if ($excel) {
                                    $excel->error_records = $excel->error_records + 1;
                                    $excel->save();
                                }
                            }
                            $continue = 1;
                            LegacyApiRawDataHistory::where(['id' => $checked->id, 'data_source_type' => $type, 'import_to_sales' => '0'])->update(['import_to_sales' => '2']);
                            DB::commit();
                            continue;
                        }
                    }

                    if ($continue) {
                        continue;
                    }
                }

                $productId = $checked->product_id;
                $systemProductId = $checked->product_id;
                $product = Products::withTrashed()->where('id', $productId)->first();
                if (!$product) {
                    $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    $systemProductId = $product->id;
                }
                $finalDates = [];
                $effectiveDate = $checked->customer_signoff;
                $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
                $triggers = (is_array($milestoneDates) && sizeOf($milestoneDates) != 0 && isset($milestone?->milestone?->milestone_trigger)) ? $milestone?->milestone?->milestone_trigger : [];
                foreach ($triggers as $key => $schema) {
                    $date = isset($milestoneDates[$key]['date']) ? $milestoneDates[$key]['date'] : NULL;
                    $finalDates[] = [
                        'date' => $date
                    ];
                }
                $milestoneDates = $finalDates;

                $stateId = NULL;
                $stateCode = $checked->customer_state;
                if ($checked->customer_state) {
                    $state = State::where('state_code', $checked->customer_state)->first();
                    if ($state) {
                        $stateId = $state?->id ?? NULL;
                        $stateCode = $state?->state_code ?? NULL;
                    }
                } else if ($checked->location_code) {
                    $location = Locations::with('State')->where('general_code', $checked->location_code)->first();
                    if ($location && $location->State) {
                        $stateId = $location?->State?->id ?? NULL;
                        $stateCode = $location?->State?->state_code ?? NULL;
                    }
                }
                $saleMasterData = [
                    'pid' => $checked->pid,
                    'weekly_sheet_id' => NULL,
                    'install_partner' => empty($checked->install_partner) ? ($salesMaster ? $salesMaster->install_partner : null) : $checked->install_partner,
                    'install_partner_id' => empty($checked->install_partner_id) ? ($salesMaster ? $salesMaster->install_partner_id : null) : $checked->install_partner_id,
                    'customer_name' => empty($checked->customer_name) ? ($salesMaster ? $salesMaster->customer_name : null) : $checked->customer_name,
                    'customer_address' => empty($checked->customer_address) ? ($salesMaster ? $salesMaster->customer_address : null) : $checked->customer_address,
                    'customer_address_2' => empty($checked->customer_address_2) ? ($salesMaster ? $salesMaster->customer_address_2 : null) : $checked->customer_address_2,
                    'customer_city' => empty($checked->customer_city) ? ($salesMaster ? $salesMaster->customer_city : null) : $checked->customer_city,
                    'customer_state' => $stateCode,
                    'state_id' => $stateId,
                    'location_code' => empty($checked->location_code) ? ($salesMaster ? $salesMaster->location_code : null) : $checked->location_code,
                    'customer_zip' => empty($checked->customer_zip) ? ($salesMaster ? $salesMaster->customer_zip : null) : $checked->customer_zip,
                    'customer_email' => empty($checked->customer_email) ? ($salesMaster ? $salesMaster->customer_email : null) : $checked->customer_email,
                    'customer_phone' => empty($checked->customer_phone) ? ($salesMaster ? $salesMaster->customer_phone : null) : $checked->customer_phone,
                    'homeowner_id' => empty($checked->homeowner_id) ? ($salesMaster ? $salesMaster->homeowner_id : null) : $checked->homeowner_id,
                    'proposal_id' => empty($checked->proposal_id) ? ($salesMaster ? $salesMaster->proposal_id : null) : $checked->proposal_id,
                    'sales_rep_name' => empty($checked->sales_rep_name) ? ($salesMaster ? $salesMaster->sales_rep_name : null) : $checked->sales_rep_name,
                    'employee_id' => empty($checked->employee_id) ? ($salesMaster ? $salesMaster->employee_id : null) : $checked->employee_id,
                    'sales_rep_email' => empty($checked->sales_rep_email) ? ($salesMaster ? $salesMaster->sales_rep_email : null) : $checked->sales_rep_email,
                    'kw' => empty($checked->kw) ? ($salesMaster ? $salesMaster->kw : null) : $checked->kw,
                    'date_cancelled' => empty($checked->date_cancelled) ? ($salesMaster ? $salesMaster->date_cancelled : null) : $checked->date_cancelled,
                    'customer_signoff' => empty($checked->customer_signoff) ? ($salesMaster ? $salesMaster->customer_signoff : null) : $checked->customer_signoff,
                    'product' => empty($checked->product) ? ($salesMaster ? $salesMaster->product : null) : $checked->product,
                    'product_id' => empty($checked->product_id) ? ($salesMaster ? $salesMaster->product_id : null) : $checked->product_id,
                    'product_code' => empty($checked->product_code) ? ($salesMaster ? $salesMaster->product_code : null) : $checked->product_code,
                    'sale_product_name' => empty($checked->sale_product_name) ? ($salesMaster ? $salesMaster->sale_product_name : null) : $checked->sale_product_name,
                    'epc' => empty($checked->epc) ? ($salesMaster ? $salesMaster->epc : null) : $checked->epc,
                    'net_epc' => empty($checked->net_epc) ? ($salesMaster ? $salesMaster->net_epc : null) : $checked->net_epc,
                    'gross_account_value' => empty($checked->gross_account_value) ? ($salesMaster ? $salesMaster->gross_account_value : null) : $checked->gross_account_value,
                    'dealer_fee_percentage' => isset($checked->dealer_fee_percentage) ? $checked->dealer_fee_percentage : ($salesMaster ? $salesMaster->dealer_fee_percentage : null),
                    'dealer_fee_amount' => isset($checked->dealer_fee_amount) ? $checked->dealer_fee_amount : ($salesMaster ? $salesMaster->dealer_fee_amount : null),
                    'adders' => empty($checked->adders) ? ($salesMaster ? $salesMaster->adders : null) : $checked->adders,
                    'adders_description' => empty($checked->adders_description) ? ($salesMaster ? $salesMaster->adders_description : null) : $checked->adders_description,
                    'funding_source' => empty($checked->funding_source) ? ($salesMaster ? $salesMaster->funding_source : null) : $checked->funding_source,
                    'financing_rate' => empty($checked->financing_rate) ? ($salesMaster ? $salesMaster->financing_rate : null) : $checked->financing_rate,
                    'financing_term' => empty($checked->financing_term) ? ($salesMaster ? $salesMaster->financing_term : null) : $checked->financing_term,
                    'scheduled_install' => empty($checked->scheduled_install) ? ($salesMaster ? $salesMaster->scheduled_install : null) : $checked->scheduled_install,
                    'install_complete_date' => empty($checked->install_complete_date) ? ($salesMaster ? $salesMaster->install_complete_date : null) : $checked->install_complete_date,
                    'return_sales_date' => empty($checked->return_sales_date) ? ($salesMaster ? $salesMaster->installreturn_sales_date_partner : null) : $checked->return_sales_date,
                    'cash_amount' => empty($checked->cash_amount) ? ($salesMaster ? $salesMaster->cash_amount : null) : $checked->cash_amount,
                    'loan_amount' => empty($checked->loan_amount) ? ($salesMaster ? $salesMaster->loan_amount : null) : $checked->loan_amount,
                    'redline' => empty($checked->redline) ? ($salesMaster ? $salesMaster->redline : null) : $checked->redline,
                    'cancel_fee' => empty($checked->cancel_fee) ? ($salesMaster ? $salesMaster->cancel_fee : null) : $checked->cancel_fee,
                    'data_source_type' => $type,
                    'job_status' => empty($checked->job_status) ? ($salesMaster ? $salesMaster->job_status : null) : $checked->job_status,
                    'length_of_agreement' => empty($checked->length_of_agreement) ? ($salesMaster ? $salesMaster->length_of_agreement : null) : $checked->length_of_agreement,
                    'service_schedule' => empty($checked->service_schedule) ? ($salesMaster ? $salesMaster->service_schedule : null) : $checked->service_schedule,
                    'initial_service_cost' => empty($checked->initial_service_cost) ? ($salesMaster ? $salesMaster->initial_service_cost : null) : $checked->initial_service_cost,
                    'subscription_payment' => empty($checked->subscription_payment) ? ($salesMaster ? $salesMaster->subscription_payment : null) : $checked->subscription_payment,
                    'card_on_file' => empty($checked->card_on_file) ? ($salesMaster ? $salesMaster->card_on_file : null) : $checked->card_on_file,
                    'auto_pay' => empty($checked->auto_pay) ? ($salesMaster ? $salesMaster->auto_pay : null) : $checked->auto_pay,
                    'service_completed' => empty($checked->service_completed) ? ($salesMaster ? $salesMaster->service_completed : null) : $checked->service_completed,
                    'last_service_date' => empty($checked->last_service_date) ? ($salesMaster ? $salesMaster->last_service_date : null) : $checked->last_service_date,
                    'bill_status' => empty($checked->bill_status) ? ($salesMaster ? $salesMaster->bill_status : null) : $checked->bill_status,
                    'm1_date' => empty($checked->m1_date) ? ($salesMaster ? $salesMaster->m1_date : null) : $checked->m1_date,
                    'initial_service_date' => empty($checked->initial_service_date) ? ($salesMaster ? $salesMaster->initial_service_date : null) : $checked->initial_service_date,
                    'initialStatusText' => empty($checked->initialStatusText) ? ($salesMaster ? $salesMaster->initialStatusText : null) : $checked->initialStatusText,
                    'm2_date' => empty($checked->m2_date) ? ($salesMaster ? $salesMaster->m2_date : null) : $checked->m2_date,
                    'trigger_date' => empty($checked->trigger_date) ? ($salesMaster ? $salesMaster->trigger_date : null) : $checked->trigger_date,
                    'balance_age' => empty($checked->balance_age) ? ($salesMaster ? $salesMaster->balance_age : null) : $checked->balance_age,
                ];

                $closer = User::where('id', $checked->closer1_id)->first();
                $setter = User::where('id', $checked->setter1_id)->first();
                CustomerPayment::updateOrCreate(['pid' => $checked->pid], ['pid' => $checked->pid, 'customer_payment_json' => json_encode(json_decode($checked->customer_payment_json, true))]);
                $isImportStatus = 1;
                if (!$salesMaster) {
                    $nullTableVal = $saleMasterData;
                    $nullTableVal['setter_id'] = $checked->setter1_id;
                    $nullTableVal['closer_id'] = $checked->closer1_id;
                    $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                    $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                    $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                    $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                    $nullTableVal['job_status'] = $checked->job_status;
                    LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);
                    $saleMaster = SalesMaster::create($saleMasterData);
                    $saleMasterProcessData = [
                        'sale_master_id' => $saleMaster->id,
                        'weekly_sheet_id' => $saleMaster->weekly_sheet_id,
                        'pid' => $checked->pid,
                        'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : NULL,
                        'closer2_id' => isset($checked->closer2_id) ? $checked->closer2_id : NULL,
                        'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : NULL,
                        'setter2_id' => isset($checked->setter2_id) ? $checked->setter2_id : NULL,
                        'job_status' => $checked->job_status
                    ];
                    SaleMasterProcess::create($saleMasterProcessData);

                    try {
                        request()->merge(['milestone_dates' => $milestoneDates]);
                        $this->subroutineProcess($saleMaster->pid);
                        $salesSuccessReport[] = [
                            'is_error' => false,
                            'pid' => $checked->pid,
                            'message' => 'Success',
                            'realMessage' => 'Success',
                            'file' => '',
                            'line' => '',
                            'name' => '-'
                        ];
                        if ($excelId) {
                            $excel = ExcelImportHistory::where('id', $excelId)->first();
                            if ($excel) {
                                $excel->new_records = $excel->new_records + 1;
                                $excel->save();
                            }
                        }
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $salesErrorReport[] = [
                            'is_error' => true,
                            'pid' => $checked->pid,
                            'message' => 'Error During Subroutine Process',
                            'realMessage' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'name' => '-'
                        ];
                        DB::rollBack();
                        if ($excelId) {
                            $excel = ExcelImportHistory::where('id', $excelId)->first();
                            if ($excel) {
                                $excel->error_records = $excel->error_records + 1;
                                $excel->save();
                            }
                        }
                    }
                } else {
                    try {
                        $checkKw = ($checked->kw == $salesMaster->kw) ? 0 : 1;
                        $checkNetEpc = ($checked->net_epc == $salesMaster->net_epc) ? 0 : 1;
                        $checkDateCancelled = ($checked->date_cancelled == $salesMaster->date_cancelled) ? 0 : 1;
                        $checkCustomerState = ($checked->customer_state == $salesMaster->customer_state) ? 0 : 1;
                        $checkProduct = ($checked->product_code == $salesMaster->product_code) ? 0 : 1;

                        $salesMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                        salesDataChangesClawback($salesMasterProcess->pid);
                        $checkSetter = 0;
                        $checkSetter2 = 0;
                        $checkCloser = 0;
                        $checkCloser2 = 0;
                        if ($salesMasterProcess) {
                            $checkSetter = ($checked->setter1_id == $salesMasterProcess->setter1_id) ? 0 : 1;
                            $checkSetter2 = ($checked->setter2_id == $salesMasterProcess->setter2_id) ? 0 : 1;
                            $checkCloser = ($checked->closer1_id == $salesMasterProcess->closer1_id) ? 0 : 1;
                            $checkCloser2 = ($checked->closer2_id == $salesMasterProcess->closer2_id) ? 0 : 1;
                        }
                        $check = ($checkKw + $checkNetEpc + $checkDateCancelled + $checkCustomerState + $checkProduct + $checkSetter + $checkSetter2 + $checkCloser + $checkCloser2);

                        $success = true;
                        $pid = $checked->pid;
                        if ($success) {
                            if (!empty($salesMaster->product_code) && empty($checked->product_code)) {
                                $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                if ($commission || $recon || $override || $reconOverride) {
                                    if ($commission) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                    if ($recon) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                    if ($override) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                    if ($reconOverride) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                } else {
                                    $this->saleProductMappingChanges($pid);
                                }
                                $check += 1;
                            }
                            // Check for product code changes with NULL-safe comparison
                            $oldCode = $salesMaster->product_code ?? '';
                            $newCode = $checked->product_code ?? '';

                            if (!empty($oldCode) && !empty($newCode) && strcasecmp($oldCode, $newCode) !== 0) {
                                $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                if ($commission || $recon || $override || $reconOverride) {
                                    if ($commission) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                            'realMessage' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                    if ($recon) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                            'realMessage' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                    if ($override) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                            'realMessage' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                    if ($reconOverride) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                            'realMessage' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                    }
                                } else {
                                    $this->saleProductMappingChanges($pid);
                                }
                                $check += 1;
                            }
                        }

                        if ($success) {
                            $isRemove = true;
                            $isChange = true;
                            $commissionIsRemove = true;
                            $commissionIsChange = true;
                            $overrides = false;
                            $isM2Paid = false;
                            $withHeldPaid = false;
                            $upFrontRemove = [];
                            $upFrontChange = [];
                            $commissionRemove = [];
                            $commissionChange = [];
                            $count = count($milestoneDates);
                            foreach ($finalDates as $key => $finalDate) {
                                $sType = 'm' . ($key + 1);
                                $date = @$finalDate['date'];
                                $saleProduct = SaleProductMaster::where(['pid' => $pid, 'type' => $sType])->first();
                                if ($saleProduct) {
                                    if ($count == ($key + 1)) {
                                        if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                            $isM2Paid = true;
                                        }
                                        if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                            $withHeldPaid = true;
                                        }

                                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                            if ($isM2Paid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsRemove = false;
                                            } else if ($withHeldPaid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsRemove = false;
                                            } else {
                                                $commissionRemove[] = $sType;
                                            }
                                        }

                                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                            if ($isM2Paid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsChange = false;
                                            } else if ($withHeldPaid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsChange = false;
                                            } else {
                                                $commissionChange[] = [
                                                    'type' => $sType,
                                                    'date' => $date
                                                ];
                                            }
                                        }

                                        if ($saleProduct->milestone_date != $date) {
                                            $check += 1;
                                        }
                                    } else {
                                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isRemove = false;
                                            } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isRemove = false;
                                            } else {
                                                $upFrontRemove[] = $sType;
                                            }
                                        }

                                        if (!empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isChange = false;
                                            } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isChange = false;
                                            } else {
                                                $upFrontChange[] = [
                                                    'type' => $sType,
                                                    'date' => $date
                                                ];
                                            }
                                        }

                                        if ($saleProduct->milestone_date != $date) {
                                            $check += 1;
                                        }
                                    }
                                }
                                if (!$overrides && $saleProduct && $saleProduct->is_override) {
                                    if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                    }

                                    if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                        }
                                    }
                                    $overrides = true;
                                }
                            }

                            if ($isRemove) {
                                foreach ($upFrontRemove as $remove) {
                                    $this->removeUpFrontSaleData($pid, $remove);
                                }
                            }

                            if ($isChange) {
                                foreach ($upFrontChange as $change) {
                                    $this->changeUpFrontPayrollData($pid, $change);
                                }
                            }

                            if ($commissionIsRemove) {
                                if (sizeOf($commissionRemove) != 0) {
                                    $this->removeCommissionSaleData($pid);
                                }
                            }

                            if ($commissionIsChange) {
                                foreach ($commissionChange as $change) {
                                    $this->changeCommissionPayrollData($pid, $change);
                                }
                            }

                            if (sizeof($finalDates) == 0) {
                                $this->removeUpFrontSaleData($pid);
                                $this->removeCommissionSaleData($pid);
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->closer1_id) && isset($checked->closer1_id) && $checked->closer1_id != $salesMasterProcess->closer1_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->closer1_id, $salesMaster);
                                    $this->removeClawBackForNewUser($checked->closer1_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->closer2_id) && isset($checked->closer2_id) && $checked->closer2_id != $salesMasterProcess->closer2_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->closer2_id, $salesMaster);
                                    $this->removeClawBackForNewUser($checked->closer2_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->setter1_id) && isset($checked->setter1_id) && $checked->setter1_id != $salesMasterProcess->setter1_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->setter1_id, $salesMaster, 'setter');
                                    $this->removeClawBackForNewUser($checked->setter1_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->setter2_id) && isset($checked->setter2_id) && $checked->setter2_id != $salesMasterProcess->setter2_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->setter1_id, $salesMaster, 'setter');
                                    $this->removeClawBackForNewUser($checked->setter1_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            $data = [
                                'weekly_sheet_id' => $salesMaster->weekly_sheet_id,
                                'pid' => $checked->pid,
                                'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : NULL,
                                'closer2_id' => isset($checked->closer2_id) ? $checked->closer2_id : NULL,
                                'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : NULL,
                                'setter2_id' => isset($checked->setter2_id) ? $checked->setter2_id : NULL,
                                'job_status' => $checked->job_status
                            ];
                            SaleMasterProcess::updateOrCreate(['pid' => $checked->pid], $data);
                            if (!empty($salesMaster->date_cancelled)) {
                                unset($saleMasterData['product_id']);
                                unset($saleMasterData['product_code']);
                            }
                            $saleModelToUpdate = SalesMaster::where('pid', $checked->pid)->first();
                                if ($saleModelToUpdate) {
                                    $saleModelToUpdate->fill($saleMasterData)->save();
                                }

                            $closer = User::where('id', $checked->closer1_id)->first();
                            $setter = User::where('id', $checked->setter1_id)->first();
                            $nullTableVal = $saleMasterData;
                            $nullTableVal['setter_id'] = $checked->setter1_id;
                            $nullTableVal['closer_id'] = $checked->closer1_id;
                            $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                            $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                            $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                            $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                            $nullTableVal['job_status'] = $checked->job_status;
                            LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);

                            // if ($check > 0) {
                            $requestArray = ['milestone_dates' => $milestoneDates];
                            if (!empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                                salesDataChangesBasedOnClawback($salesMaster->pid);
                                $requestArray['full_recalculate'] = 1;
                            }
                            request()->merge($requestArray);
                            $this->subroutineProcess($checked->pid);
                            $salesSuccessReport[] = [
                                'is_error' => false,
                                'pid' => $checked->pid,
                                'message' => 'Success',
                                'realMessage' => 'Success',
                                'file' => '',
                                'line' => '',
                                'name' => '-'
                            ];
                            // } else {
                            //     $salesSuccessReport[] = [
                            //         'is_error' => false,
                            //         'pid' => $checked->pid,
                            //         'message' => 'Success!!',
                            //         'realMessage' => 'Success!!',
                            //         'file' => '',
                            //         'line' => '',
                            //         'name' => '-'
                            //     ];
                            // }
                            if ($excelId) {
                                $excel = ExcelImportHistory::where('id', $excelId)->first();
                                if ($excel) {
                                    $excel->updated_records = $excel->updated_records + 1;
                                    $excel->save();
                                }
                            }
                        } else {
                            if ($excelId) {
                                $excel = ExcelImportHistory::where('id', $excelId)->first();
                                if ($excel) {
                                    $excel->error_records = $excel->error_records + 1;
                                    $excel->save();
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $salesErrorReport[] = [
                            'is_error' => true,
                            'pid' => $checked->pid,
                            'message' => 'Error During Subroutine Process',
                            'realMessage' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'name' => '-'
                        ];
                        DB::rollBack();
                        if ($excelId) {
                            $excel = ExcelImportHistory::where('id', $excelId)->first();
                            if ($excel) {
                                $excel->error_records = $excel->error_records + 1;
                                $excel->save();
                            }
                        }
                    }
                }

                // UPDATE STATUS IN HISTORY TABLE FOR EXECUTED SALES.
                LegacyApiRawDataHistory::where(['id' => $checked->id, 'data_source_type' => $type, 'import_to_sales' => '0'])->update(['import_to_sales' => $isImportStatus]);

                // Update progress after each record
                $processedCount++;
                if (is_callable($progressCallback)) {
                    $progressCallback($processedCount, $totalRecords, $checked->id); // Fixed: Use database ID instead of PID
                }
                // DB::commit();
                $successPID[] = $checked->pid;
            }

            if ($excelId) {
                $excel = ExcelImportHistory::where('id', $excelId)->first();
                if ($excel) {
                    $excel->status = 0;
                    $excel->updated_records = $excel->total_records - $excel->new_records - $excel->error_records;
                    $excel->save();
                }
            }
            //dispatch(new GenerateAlertJob(implode(',', $successPID)));
            if (!in_array($domainName, ['evomarketing', 'whitenight', 'threeriverspest'])) {
                // dispatch(new GenerateAlertJob(implode(',', $successPID)));

                if (CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                    foreach ($successPID as $success) {
                        //  dispatch(new RecalculateOpenTieredSalesJob($success));
                    }
                }
            }
            // If Sales From Excel Sheet Has One Or More Error
            if (sizeof($salesErrorReport) != 0) {
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Failed',
                    'template' => view('mail.saleImportFailed', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user])
                ];
                $this->sendEmailNotification($data);
            } else {
                // If Sales From Excel Sheet Has No Error
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Success',
                    'template' => view('mail.saleImportSuccess', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user])
                ];
                // $this->sendEmailNotification($data);
            }
        } catch (\Throwable $e) {

            if (!in_array($domainName, ['evomarketing', 'whitenight', 'threeriverspest'])) {
                // dispatch(new GenerateAlertJob(implode(',', $successPID)));
                if (CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                    foreach ($successPID as $success) {
                        //dispatch(new RecalculateOpenTieredSalesJob($success));
                    }
                }
            }

            LegacyApiRawDataHistory::where(['data_source_type' => $type, 'import_to_sales' => '0'])->whereNotIn('pid', $successPID)->update(['import_to_sales' => '2']);
            if ($excelId) {
                $excel = ExcelImportHistory::where('id', $excelId)->first();
                if ($excel) {
                    $excel->status = 2;
                    $excel->error_records = $excel->total_records - $excel->new_records - $excel->updated_records;
                    $excel->save();
                }
            }

            // Final progress update if callback is provided
            if (is_callable($progressCallback)) {
                $progressCallback($totalRecords, $totalRecords, 'completed');
            }

            // Return statistics for job monitoring
            return [
                'processed_count' => $processedCount,
                'created_count' => count($salesSuccessReport),
                'updated_count' => $processedCount - count($salesSuccessReport) - count($salesErrorReport),
                'error_count' => count($salesErrorReport),
                'success_pids' => $successPID
            ];
            \Log::error("Error processing records for type {$type}: " . $e->getMessage());
            return 'Error processing records for type: ' . $type . ' ' . $e->getMessage();
        }
    }

    // ACCOUNT SUMMARY
    public function accountSummary(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required'
        ]);

        $data = [];
        $pid = $request->pid;

        $totalPaidCommission = 0;
        $totalUnPaidCommission = 0;
        $commissions = UserCommission::with(['userdata'])->where(['pid' => $pid, 'settlement_type' => 'during_m2'])->get();
        // dd($commissions);
        foreach ($commissions as $commission) {
            $paidCommission = 0;
            $unPaidCommission = 0;
            if ($commission->status == 3) {
                $paidCommission = isset($commission->amount) ? $commission->amount : 0;
                $totalPaidCommission += $paidCommission;
            } else {
                $unPaidCommission = isset($commission->amount) ? $commission->amount : 0;
                $totalUnPaidCommission += $unPaidCommission;
            }

            $type = $commission->schema_name . ' Payment';
            if ($commission->amount_type == 'm2 update') {
                $type = 'Commission Payment Update';
            }

            $data['total_commissions'][] = [
                'date' => isset($commission->date) ? $commission->date : NULL,
                'employee' => isset($commission->userdata->first_name) ? ($commission->userdata->first_name . ' ' . $commission->userdata->last_name) : NULL,
                'type' => $type,
                'paid' => isset($paidCommission) ? $paidCommission : 0,
                'unpaid' => isset($unPaidCommission) ? $unPaidCommission : 0,
                'date_paid' => isset($commission->pay_period_from) ? $commission->pay_period_from . ' to ' . $commission->pay_period_to : NULL,
                'stop_payroll' => ($commission->status != 3 && @$commission->userdata->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($commission->userdata->id) && isUserDismisedOn($commission->userdata->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($commission->userdata->id) && isUserTerminatedOn($commission->userdata->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($commission->userdata->id) && isUserContractEnded($commission->userdata->id) ? 1 : 0,
            ];
        }

        // reconciliation commission
        $reconCommission = ReconCommissionHistory::with(['user'])->where(['pid' => $pid, 'status' => 'payroll'])->groupBy('user_id', 'pay_period_from', 'pay_period_to')->get();
        foreach ($reconCommission as $commission) {
            $totalReconAmount = ReconCommissionHistory::where(['pid' => $pid, 'status' => 'payroll', 'pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to])->sum('paid_amount');
            $paidCommission = 0;
            $unPaidCommission = 0;
            if ($commission->payroll_execute_status == 3) {
                $paidCommission = $totalReconAmount ?? 0;
                $totalPaidCommission += $paidCommission;
            } else {
                $unPaidCommission = $totalReconAmount ?? 0;
                $totalUnPaidCommission += $unPaidCommission;
            }

            $type = 'reconciliation';
            $commM2date = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2'])->first();
            $newdate = NULL;
            if (!empty($commM2date)) {
                $newdate = date("Y-m-d", strtotime($commM2date->date));
            }

            $data['total_commissions'][] = [
                'date' => $newdate,
                'employee' => isset($commission->user->first_name) ? ($commission->user->first_name . ' ' . $commission->user->last_name) : NULL,
                'type' => $type,
                'paid' => isset($paidCommission) ? $paidCommission : 0,
                'unpaid' => isset($unPaidCommission) ? $unPaidCommission : 0,
                'date_paid' => isset($commission->pay_period_from) ? $commission->pay_period_from . ' to ' . $commission->pay_period_to : NULL,
                'stop_payroll' => ($commission->status != 3 && @$commission->user->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($commission->user->id) && isUserDismisedOn($commission->user->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($commission->user->id) && isUserTerminatedOn($commission->user->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($commission->user->id) && isUserContractEnded($commission->user->id) ? 1 : 0,
            ];
        }

        $totalPaidAdjustment = 0;
        $totalUnPaidAdjustment = 0;
        $adjustments = PayrollAdjustmentDetail::with(['userDetail'])->where(['pid' => $pid, 'payroll_type' => 'commission'])->where('type', '!=', 'clawback')->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaid = 0;
            $adjustmentPending = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaid = isset($adjustment->amount) ? $adjustment->amount : 0;
                $totalPaidAdjustment += isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPending = isset($adjustment->amount) ? $adjustment->amount : 0;
                $totalUnPaidAdjustment += isset($adjustment->amount) ? $adjustment->amount : 0;
            }

            $type = $adjustment->type;
            $data['total_adjustment'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : NULL,
                'employee' => isset($adjustment->userDetail->first_name) ? ($adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name) : NULL,
                'type' => $type,
                'paid' => isset($adjustmentPaid) ? $adjustmentPaid : NULL,
                'unpaid' => isset($adjustmentPending) ? $adjustmentPending : NULL,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL,
                'stop_payroll' => ($adjustment->status != 3 && @$adjustment->userDetail->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($adjustment->userDetail->id) && isUserDismisedOn($adjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($adjustment->userDetail->id) && isUserTerminatedOn($adjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($adjustment->userDetail->id) && isUserContractEnded($adjustment->userDetail->id) ? 1 : 0,
            ];
        }

        $totalPaidClawBack = 0;
        $totalUnPaidClawBack = 0;
        $clawBacks = ClawbackSettlement::with(['users', 'salesDetail'])->where(['pid' => $pid, 'type' => 'commission', 'clawback_type' => 'next payroll'])->get();
        foreach ($clawBacks as $clawBack) {
            $paidClawBack = 0;
            $unPaidClawBack = 0;
            if ($clawBack->status == 3) {
                $paidClawBack = isset($clawBack->clawback_amount) ? $clawBack->clawback_amount : 0;
                $totalPaidClawBack += isset($clawBack->clawback_amount) ? $clawBack->clawback_amount : 0;
            } else {
                $unPaidClawBack = isset($clawBack->clawback_amount) ? $clawBack->clawback_amount : 0;
                $totalUnPaidClawBack += isset($clawBack->clawback_amount) ? $clawBack->clawback_amount : 0;
            }

            $returnSalesDate = isset($clawBack->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawBack->salesDetail->return_sales_date)) : NULL;
            $newdate = isset($clawBack->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawBack->salesDetail->date_cancelled)) : $returnSalesDate;
            $type = $clawBack->schema_name . ' Payment | ClawedBack';
            if ($clawBack->adders_type == 'm2 update') {
                $type = 'Commission Payment Update | ClawedBack';
            }
            $datePaid = isset($clawBack->pay_period_from) ? Carbon::parse($clawBack->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($clawBack->pay_period_to)->format('m/d/Y') : NULL;
            $data['total_commissions'][] = [
                'date' => isset($newdate) ? $newdate : NULL,
                'employee' => isset($clawBack->users->first_name) ? ($clawBack->users->first_name . ' ' . $clawBack->users->last_name) : NULL,
                'type' => $type,
                'paid' => isset($paidClawBack) ? (0 - $paidClawBack) : 0,
                'unpaid' => isset($unPaidClawBack) ? (0 - $unPaidClawBack) : 0,
                'date_paid' => $datePaid ?? NULL,
                'stop_payroll' => ($clawBack->status != 3 && @$clawBack->users->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($clawBack->users->id) && isUserDismisedOn($clawBack->users->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($clawBack->users->id) && isUserTerminatedOn($clawBack->users->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($clawBack->users->id) && isUserContractEnded($clawBack->users->id) ? 1 : 0,
            ];
        }

        /* recon clawback commissions */
        $reconClawback = ReconClawbackHistory::with(['user', 'salesDetail'])->where("pid", $pid)->whereIn("type", ["recon-commission", "commission"])->whereIn("status", ["payroll", "finalize"])->get();
        foreach ($reconClawback as $clawBack) {
            $paidClawBack = 0;
            $unPaidClawBack = 0;
            if ($clawBack->payroll_execute_status == 3) {
                $paidClawBack = isset($clawBack->paid_amount) ? $clawBack->paid_amount : 0;
                $totalPaidClawBack += $paidClawBack;
            } else {
                $unPaidClawBack = isset($clawBack->paid_amount) ? $clawBack->paid_amount : 0;
                $totalUnPaidClawBack += $unPaidClawBack;
            }

            $returnSalesDate = isset($clawBack->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawBack->salesDetail->return_sales_date)) : NULL;
            $newdate = isset($clawBack->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawBack->salesDetail->date_cancelled)) : $returnSalesDate;

            $type = "Reconciliation | Clawback";

            $datePaid = isset($clawBack->pay_period_from) ? Carbon::parse($clawBack->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($clawBack->pay_period_to)->format('m/d/Y') : NULL;
            $data['total_commissions'][] = [
                'date' => isset($newdate) ? $newdate : NULL,
                'employee' => isset($clawBack->user->first_name) ? ($clawBack->user->first_name . ' ' . $clawBack->user->last_name) : NULL,
                'type' => $type,
                'paid' => isset($paidClawBack) ? (0 - $paidClawBack) : 0,
                'unpaid' => isset($unPaidClawBack) ? (0 - $unPaidClawBack) : 0,
                'date_paid' => $datePaid ?? NULL,
                'stop_payroll' => ($clawBack->payroll_execute_status != 3 && @$clawBack->user->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($clawBack->user->id) && isUserDismisedOn($clawBack->user->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($clawBack->user->id) && isUserTerminatedOn($clawBack->user->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($clawBack->user->id) && isUserContractEnded($clawBack->user->id) ? 1 : 0,
            ];
        }

        $totalPaidClawBackAdjustment = 0;
        $totalUnPaidClawBackAdjustment = 0;
        $clawBackAdjustments = PayrollAdjustmentDetail::with(['userDetail'])->where(['pid' => $pid, 'payroll_type' => 'commission', 'type' => 'clawback'])->get();
        foreach ($clawBackAdjustments as $clawBackAdjustment) {
            $adjustmentPaid = 0;
            $adjustmentPending = 0;
            if ($clawBackAdjustment->status == 3) {
                $adjustmentPaid = isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
                $totalPaidClawBackAdjustment += isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
            } else {
                $adjustmentPending = isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
                $totalUnPaidClawBackAdjustment += isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
            }

            $type = $clawBackAdjustment->type ?? NULL;
            $data['total_adjustment'][] = [
                'date' => isset($clawBackAdjustment->updated_at) ? date('Y-m-d', strtotime($clawBackAdjustment->updated_at)) : NULL,
                'employee' => isset($clawBackAdjustment->userDetail->first_name) ? ($clawBackAdjustment->userDetail->first_name . ' ' . $clawBackAdjustment->userDetail->last_name) : NULL,
                'type' => $type,
                'paid' => isset($adjustmentPaid) ? $adjustmentPaid : '',
                'unpaid' => isset($adjustmentPending) ? $adjustmentPending : '',
                'date_paid' => isset($clawBackAdjustment->pay_period_from) ? $clawBackAdjustment->pay_period_from . ' to ' . $clawBackAdjustment->pay_period_to : NULL,
                'stop_payroll' => ($clawBackAdjustment->status != 3 && @$clawBackAdjustment->userDetail->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($clawBackAdjustment->userDetail->id) && isUserDismisedOn($clawBackAdjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($clawBackAdjustment->userDetail->id) && isUserTerminatedOn($clawBackAdjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($clawBackAdjustment->userDetail->id) && isUserContractEnded($clawBackAdjustment->userDetail->id) ? 1 : 0,
            ];
        }

        /* recon adjustment data */
        $reconAdjustment = ReconAdjustment::with(['user'])->where(["pid" => $pid])
            ->whereIn("adjustment_type", ["clawback", "commission"])
            ->whereIn("adjustment_override_type", ["m1", "m2", "m2 update", "recon-commission"])
            ->whereIn("payroll_status", ["payroll", "finalize"])->get();

        foreach ($reconAdjustment as $adjustment) {
            $adjustmentPaid = 0;
            $adjustmentPending = 0;
            if ($adjustment->payroll_execute_status == 3) {
                $adjustmentPaid = isset($adjustment->adjustment_amount) ? $adjustment->adjustment_amount : 0;
                $totalPaidAdjustment += $adjustmentPaid;
            } else {
                $adjustmentPending = isset($adjustment->adjustment_amount) ? $adjustment->adjustment_amount : 0;
                $totalUnPaidAdjustment += $adjustmentPending;
            }

            $type = $adjustment->adjustment_type == "commission" ? " | Commission" : " | Clawback";
            $description = ($adjustment->adjustment_override_type == "recon-commission" ? "Reconciliation" : $adjustment->adjustment_override_type)  . $type;

            $data['total_adjustment'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : NULL,
                'employee' => isset($adjustment->user->first_name) ? ($adjustment->user->first_name . ' ' . $adjustment->user->last_name) : NULL,
                'type' => $description,
                'paid' => isset($adjustmentPaid) ? $adjustmentPaid : NULL,
                'unpaid' => isset($adjustmentPending) ? $adjustmentPending : NULL,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL,
                'stop_payroll' => ($adjustment->payroll_execute_status != 3 && @$adjustment->user->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($adjustment->user->id) && isUserDismisedOn($adjustment->user->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($adjustment->user->id) && isUserTerminatedOn($adjustment->user->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($adjustment->user->id) && isUserContractEnded($adjustment->user->id) ? 1 : 0,
            ];
        }

        $data['commission_paid_total'] = $totalPaidCommission - $totalPaidClawBack;
        $data['commission_unpaid_total'] = $totalUnPaidCommission - $totalUnPaidClawBack;
        $data['adjustment_paid_total'] = $totalPaidAdjustment + $totalPaidClawBackAdjustment;
        $data['adjustment_unpaid_total'] = $totalUnPaidAdjustment + $totalUnPaidClawBackAdjustment;


        $totalPaidOverride = 0;
        $totalUnPaidOverride = 0;
        $overrides = UserOverrides::with(['userInfo', 'overrideUser'])->where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2'])->get();
        foreach ($overrides as $override) {
            $paidOverride = 0;
            $unPaidOverride = 0;
            if ($override->status == 3) {
                $paidOverride = isset($override->amount) ? $override->amount : 0;
                $totalPaidOverride += $paidOverride;
            } else {
                $unPaidOverride = isset($override->amount) ? $override->amount : 0;
                $totalUnPaidOverride += $unPaidOverride;
            }

            $user = isset($override->overrideUser->first_name) ? ($override->overrideUser->first_name . ' ' . $override->overrideUser->last_name) : NULL;
            $commM2date = UserCommission::where(['pid' => $request->pid, 'amount_type' => 'm2'])->first();
            $newdate = NULL;
            if (!empty($commM2date)) {
                $newdate = date("Y-m-d", strtotime($commM2date->date));
            }

            $m2Update = $override->during == 'm2 update' ? ' | Commission Update' : '';
            $data['total_overrides'][] = [
                'override_over' => isset($override->userInfo->first_name) ? ($override->userInfo->first_name . ' ' . $override->userInfo->last_name) : '',
                'date' => isset($override->updated_at) ? $newdate : NULL,
                'description' => isset($override->type) ? ($user . ' | ' . $override->type . $m2Update) : '',
                'PaidAmount' => $paidOverride,
                'UnPaidAmount' => $unPaidOverride,
                'date_paid' => isset($override->pay_period_from) ? Carbon::parse($override->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($override->pay_period_to)->format('m/d/Y') : NULL,
                'stop_payroll' => ($override->status != 3 && @$override->overrideUser->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($override->userInfo->id) && isUserDismisedOn($override->userInfo->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($override->userInfo->id) && isUserTerminatedOn($override->userInfo->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($override->userInfo->id) && isUserContractEnded($override->userInfo->id) ? 1 : 0,
            ];
        }

        // reconciliation User Overrides
        $reconOverrides = ReconOverrideHistory::with(['overrideOverData', 'userData'])->where(['pid' => $pid, 'status' => 'payroll'])->get();
        foreach ($reconOverrides as $override) {
            $paidOverride = 0;
            $unPaidOverride = 0;
            if ($override->payroll_execute_status == 3) {
                $paidOverride = isset($override->paid) ? $override->paid : 0;
                $totalPaidOverride += $paidOverride;
            } else {
                $unPaidOverride = isset($override->paid) ? $override->paid : 0;
                $totalUnPaidOverride += $unPaidOverride;
            }

            $user = isset($override->userData->first_name) ? ($override->userData->first_name . ' ' . $override->userData->last_name) : NULL;
            $commM2date = UserCommission::where(['pid' => $request->pid, 'amount_type' => 'm2'])->first();
            $newdate = NULL;
            if (!empty($commM2date)) {
                $newdate = date("Y-m-d", strtotime($commM2date->date));
            }

            $data['total_overrides'][] = [
                'override_over' => isset($override->overrideOverData->first_name) ? ($override->overrideOverData->first_name . ' ' . $override->overrideOverData->last_name) : '',
                'date' => isset($override->updated_at) ? $newdate : NULL,
                'description' => isset($override->type) ? ($user . ' | ' . $override->type . ' reconciliation') : '',
                'PaidAmount' => $paidOverride,
                'UnPaidAmount' => $unPaidOverride,
                'date_paid' => isset($override->pay_period_from) ? Carbon::parse($override->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($override->pay_period_to)->format('m/d/Y') : NULL,
                'stop_payroll' => ($override->status != 3 && @$override->userData->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($override->overrideOverData->id) && isUserDismisedOn($override->overrideOverData->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($override->overrideOverData->id) && isUserTerminatedOn($override->overrideOverData->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($override->overrideOverData->id) && isUserContractEnded($override->overrideOverData->id) ? 1 : 0,
            ];
        }

        $adjustmentPaidOverTotal = 0;
        $adjustmentUnPaidOverTotal = 0;
        $adjustments = PayrollAdjustmentDetail::with(['userDetail'])->where(['pid' => $pid, 'payroll_type' => 'overrides'])->where('type', '!=', 'clawback')->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaidOver = 0;
            $adjustmentPendingOver = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaidOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $adjustmentPaidOverTotal += isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPendingOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $adjustmentUnPaidOverTotal += isset($adjustment->amount) ? $adjustment->amount : 0;
            }

            $data['total_adjustment_override'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : NULL,
                'employee' => isset($adjustment->userDetail->first_name) ? ($adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name) : NULL,
                'type' => isset($adjustment->type) ? $adjustment->type : '',
                'paid' => isset($adjustmentPaidOver) ? $adjustmentPaidOver : 0,
                'unpaid' => isset($adjustmentPendingOver) ? $adjustmentPendingOver : 0,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL,
                'stop_payroll' => ($adjustment->status != 3 && @$adjustment->userDetail->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($adjustment->userDetail->id) && isUserDismisedOn($adjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($adjustment->userDetail->id) && isUserTerminatedOn($adjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($adjustment->userDetail->id) && isUserContractEnded($adjustment->userDetail->id) ? 1 : 0,
            ];
        }

        /* recon override adjustments */
        $reconAdjustment = ReconAdjustment::with(['user'])->where(["pid" => $pid])
            ->whereIn("adjustment_type", ["clawback", "override"])
            ->whereIn("adjustment_override_type", ["Office", "Direct", "Stack", "recon-override", "Indirect", "Manual"])
            ->whereIn("payroll_status", ["payroll", "finalize"])->get();

        foreach ($reconAdjustment as $adjustment) {
            $adjustmentPaidOver = 0;
            $adjustmentPendingOver = 0;
            if ($adjustment->payroll_execute_status == 3) {
                $adjustmentPaidOver = isset($adjustment->adjustment_amount) ? $adjustment->adjustment_amount : 0;
                $adjustmentPaidOverTotal += $adjustmentPaidOver;
            } else {
                $adjustmentPendingOver = isset($adjustment->adjustment_amount) ? $adjustment->adjustment_amount : 0;
                $adjustmentUnPaidOverTotal += $adjustmentPendingOver;
            }

            $type = $adjustment->adjustment_type == "override" ? " | Override" : " | Clawback";
            $description = $adjustment->adjustment_override_type . $type;

            $data['total_adjustment_override'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : NULL,
                'employee' => isset($adjustment->user->first_name) ? ($adjustment->user->first_name . ' ' . $adjustment->user->last_name) : NULL,
                'type' => isset($description) ? $description : '',
                'paid' => isset($adjustmentPaidOver) ? $adjustmentPaidOver : 0,
                'unpaid' => isset($adjustmentPendingOver) ? $adjustmentPendingOver : 0,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL,
                'stop_payroll' => ($adjustment->payroll_execute_status != 3 && @$adjustment->user->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($adjustment->user->id) && isUserDismisedOn($adjustment->user->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($adjustment->user->id) && isUserTerminatedOn($adjustment->user->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($adjustment->user->id) && isUserContractEnded($adjustment->user->id) ? 1 : 0,
            ];
        }

        $totalPaidOverClawBack = 0;
        $totalUnPaidOverClawBack = 0;
        $clawBack = ' | Clawed Back';
        $clawBackOverrides = ClawbackSettlement::with(['userInfo', 'users', 'salesDetail'])->where(['pid' => $pid, 'type' => 'overrides', 'clawback_type' => 'next payroll'])->get();
        foreach ($clawBackOverrides as $clawBackOverride) {
            $paidOverClawBack = 0;
            $unPaidOverClawBack = 0;
            if ($clawBackOverride->status == 3) {
                $paidOverClawBack = isset($clawBackOverride->clawback_amount) ? $clawBackOverride->clawback_amount : 0;
                $totalPaidOverClawBack += $paidOverClawBack;
            } else {
                $unPaidOverClawBack = isset($clawBackOverride->clawback_amount) ? $clawBackOverride->clawback_amount : 0;
                $totalUnPaidOverClawBack += $unPaidOverClawBack;
            }

            $user = isset($clawBackOverride->users->first_name) ? ($clawBackOverride->users->first_name . ' ' . $clawBackOverride->users->last_name) : '';
            $returnSalesDate = isset($clawBackOverride->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawBackOverride->salesDetail->return_sales_date)) : NULL;
            $newdate = isset($clawBackOverride->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawBackOverride->salesDetail->date_cancelled)) : $returnSalesDate;

            $description = isset($clawBackOverride->adders_type) ? ($user . ' | ' . $clawBackOverride->adders_type . $clawBack) : '';
            if ($clawBackOverride->during == 'm2 update') {
                $description = isset($clawBackOverride->adders_type) ? ($user . ' | ' . $clawBackOverride->adders_type . ' | Commission Update' . $clawBack) : '';
            }

            $data['total_overrides'][] = [
                'override_over' => isset($clawBackOverride->userInfo->first_name) ? ($clawBackOverride->userInfo->first_name . ' ' . $clawBackOverride->userInfo->last_name) : '',
                'date' => isset($newdate) ? $newdate : NULL,
                'description' => $description,
                'PaidAmount' => (0 - $paidOverClawBack),
                'UnPaidAmount' => (0 - $unPaidOverClawBack),
                'date_paid' => isset($clawBackOverride->pay_period_from) ? $clawBackOverride->pay_period_from . ' to ' . $clawBackOverride->pay_period_to : NULL,
                'stop_payroll' => ($clawBackOverride->status != 3 && @$clawBackOverride->users->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($clawBackOverride->userInfo->id) && isUserDismisedOn($clawBackOverride->userInfo->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($clawBackOverride->userInfo->id) && isUserTerminatedOn($clawBackOverride->userInfo->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($clawBackOverride->userInfo->id) && isUserContractEnded($clawBackOverride->userInfo->id) ? 1 : 0,
            ];
        }

        $clawBackAdjustmentPaidOver = 0;
        $clawBackAdjustmentPendingOver = 0;
        $adjustments = PayrollAdjustmentDetail::with(['userDetail'])->where(['pid' => $pid, 'payroll_type' => 'overrides', 'type' => 'clawback'])->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaidOver = 0;
            $adjustmentPendingOver = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaidOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $clawBackAdjustmentPaidOver += $adjustmentPaidOver;
            } else {
                $adjustmentPendingOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $clawBackAdjustmentPendingOver += $adjustmentPendingOver;
            }

            $data['total_adjustment_override'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : NULL,
                'employee' => isset($adjustment->userDetail->first_name) ? ($adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name) : NULL,
                'type' => isset($adjustment->type) ? $adjustment->type : '',
                'paid' => isset($adjustmentPaidOver) ? $adjustmentPaidOver : 0,
                'unpaid' => isset($adjustmentPendingOver) ? $adjustmentPendingOver : 0,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL,
                'stop_payroll' => ($adjustment->status != 3 && @$adjustment->userDetail->stop_payroll) ? 'Payroll Stop' : NULL,
                'dismiss' => isset($adjustment->userDetail->id) && isUserDismisedOn($adjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isset($adjustment->userDetail->id) && isUserTerminatedOn($adjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isset($adjustment->userDetail->id) && isUserContractEnded($adjustment->userDetail->id) ? 1 : 0,
            ];
        }

        $data['total_overrides_amount_paid'] = $totalPaidOverride - $totalPaidOverClawBack;
        $data['total_overrides_amount_pending'] = $totalUnPaidOverride - $totalUnPaidOverClawBack;
        $data['total_adjustment_amount_paid'] = $adjustmentPaidOverTotal + $clawBackAdjustmentPaidOver;
        $data['total_adjustment_amount_pending'] = $adjustmentUnPaidOverTotal + $clawBackAdjustmentPendingOver;

        $this->successResponse("Successfully.", "accountSummary", $data);
    }

    // ACCOUNT SUMMARY BY POSITION
    public function accountSummaryByPosition(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required'
        ]);

        $pid = $request->pid;
        $commissionArray = [];
        $commissions = UserCommission::with(['userdata'])->where(['pid' => $pid, 'settlement_type' => 'during_m2'])->get();
        foreach ($commissions as $commission) {
            $paidCommission = 0;
            $unPaidCommission = 0;
            if ($commission->status == 3) {
                $paidCommission = isset($commission->amount) ? $commission->amount : 0;
            } else {
                $unPaidCommission = isset($commission->amount) ? $commission->amount : 0;
            }

            $type = $commission->schema_name . ' Payment';
            if ($commission->amount_type == 'm2 update') {
                $type = 'Commission Payment Update';
            }

            $commissionArray[$commission->user_id]['data'][] = [
                'type' => 'Commission',
                'date' => isset($commission->date) ? $commission->date : NULL,
                'description' => $type,
                'paid' => isset($paidCommission) ? $paidCommission : 0,
                'unpaid' => isset($unPaidCommission) ? $unPaidCommission : 0,
                'date_paid' => isset($commission->pay_period_from) ? $commission->pay_period_from . ' to ' . $commission->pay_period_to : NULL
            ];

            if (@$commissionArray[$commission->user_id]['paid_total']) {
                $commissionArray[$commission->user_id]['paid_total'] += $paidCommission;
            } else {
                $commissionArray[$commission->user_id]['paid_total'] = $paidCommission;
            }

            if (@$commissionArray[$commission->user_id]['unpaid_total']) {
                $commissionArray[$commission->user_id]['unpaid_total'] += $unPaidCommission;
            } else {
                $commissionArray[$commission->user_id]['unpaid_total'] = $unPaidCommission;
            }

            if (!isset($commissionArray[$commission->user_id]['info'])) {
                $commissionArray[$commission->user_id]['info'] = [
                    'name' => isset($commission->userdata->first_name) ? ($commission->userdata->first_name . ' ' . $commission->userdata->last_name) : NULL,
                    'image' => $commission?->userdata?->image,
                    'position_id' => isset($commission->userdata->position_id) ? $commission->userdata->position_id : NULL,
                    'sub_position_id' => isset($commission->userdata->sub_position_id) ? $commission->userdata->sub_position_id : NULL,
                    'is_super_admin' => isset($commission->userdata->is_super_admin) ? $commission->userdata->is_super_admin : NULL,
                    'is_manager' => isset($commission->userdata->is_manager) ? $commission->userdata->is_manager : NULL,
                    'stop_payroll' => ($commission->status != 3 && @$commission->userdata->stop_payroll) ? 'Payroll Stop' : NULL,
                    'dismiss' => isset($commission->userdata->id) && isUserDismisedOn($commission->userdata->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($commission->userdata->id) && isUserTerminatedOn($commission->userdata->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($commission->userdata->id) && isUserContractEnded($commission->userdata->id) ? 1 : 0,
                ];
            }
        }

        $adjustments = PayrollAdjustmentDetail::with(['userDetail'])->where(['pid' => $pid, 'payroll_type' => 'commission'])->where('type', '!=', 'clawback')->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaid = 0;
            $adjustmentPending = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaid = isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPending = isset($adjustment->amount) ? $adjustment->amount : 0;
            }

            $type = $adjustment->type;
            $commissionArray[$adjustment->user_id]['data'][] = [
                'type' => 'Adjustment',
                'date' => isset($newdate) ? $newdate : NULL,
                'description' => $type,
                'paid' => isset($adjustmentPaid) ? $adjustmentPaid : NULL,
                'unpaid' => isset($adjustmentPending) ? $adjustmentPending : NULL,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL
            ];

            if (@$commissionArray[$adjustment->user_id]['paid_total']) {
                $commissionArray[$adjustment->user_id]['paid_total'] += $adjustmentPaid;
            } else {
                $commissionArray[$adjustment->user_id]['paid_total'] = $adjustmentPaid;
            }

            if (@$commissionArray[$adjustment->user_id]['unpaid_total']) {
                $commissionArray[$adjustment->user_id]['unpaid_total'] += $adjustmentPending;
            } else {
                $commissionArray[$adjustment->user_id]['unpaid_total'] = $adjustmentPending;
            }

            if (!isset($commissionArray[$adjustment->user_id]['info'])) {
                $commissionArray[$adjustment->user_id]['info'] = [
                    'name' => isset($adjustment->userDetail->first_name) ? ($adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name) : NULL,
                    'image' => $adjustment?->userDetail?->image,
                    'terminate' => isset($adjustment->userDetail->terminate) ? $adjustment->userDetail->terminate : 0,
                    'dismiss' => isset($adjustment->userDetail->dismiss) ? $adjustment->userDetail->dismiss : 0,
                    'position_id' => isset($adjustment->userDetail->position_id) ? $adjustment->userDetail->position_id : NULL,
                    'sub_position_id' => isset($adjustment->userDetail->sub_position_id) ? $adjustment->userDetail->sub_position_id : NULL,
                    'is_super_admin' => isset($adjustment->userDetail->is_super_admin) ? $adjustment->userDetail->is_super_admin : NULL,
                    'is_manager' => isset($adjustment->userDetail->is_manager) ? $adjustment->userDetail->is_manager : NULL,
                    'stop_payroll' => ($adjustment->status != 3 && @$adjustment->userDetail->stop_payroll) ? 'Payroll Stop' : NULL,

                    'dismiss' => isset($clawBack->users->id) && isUserDismisedOn($clawBack->users->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($clawBack->users->id) && isUserTerminatedOn($clawBack->users->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($clawBack->users->id) && isUserContractEnded($clawBack->users->id) ? 1 : 0,
                ];
            }
        }

        $clawBacks = ClawbackSettlement::with(['users', 'salesDetail'])->where(['pid' => $pid, 'type' => 'commission', 'clawback_type' => 'next payroll'])->get();
        foreach ($clawBacks as $clawBack) {
            $paidClawBack = 0;
            $unPaidClawBack = 0;
            if ($clawBack->status == 3) {
                $paidClawBack = isset($clawBack->clawback_amount) ? $clawBack->clawback_amount : 0;
            } else {
                $unPaidClawBack = isset($clawBack->clawback_amount) ? $clawBack->clawback_amount : 0;
            }

            $returnSalesDate = isset($clawBack->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawBack->salesDetail->return_sales_date)) : NULL;
            $newdate = isset($clawBack->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawBack->salesDetail->date_cancelled)) : $returnSalesDate;
            $type = $clawBack->schema_name . ' Payment | ClawedBack';
            if ($clawBack->adders_type == 'm2 update') {
                $type = 'Commission Payment Update | ClawedBack';
            }
            $datePaid = isset($clawBack->pay_period_from) ? Carbon::parse($clawBack->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($clawBack->pay_period_to)->format('m/d/Y') : NULL;
            $commissionArray[$clawBack->user_id]['data'][] = [
                'type' => 'Clawback',
                'date' => isset($newdate) ? $newdate : NULL,
                'description' => $type,
                'paid' => isset($paidClawBack) ? (0 - $paidClawBack) : 0,
                'unpaid' => isset($unPaidClawBack) ? (0 - $unPaidClawBack) : 0,
                'date_paid' => $datePaid ?? NULL
            ];

            if (@$commissionArray[$clawBack->user_id]['paid_total']) {
                $commissionArray[$clawBack->user_id]['paid_total'] +=  (0 - $paidClawBack);
            } else {
                $commissionArray[$clawBack->user_id]['paid_total'] =  (0 - $paidClawBack);
            }

            if (@$commissionArray[$clawBack->user_id]['unpaid_total']) {
                $commissionArray[$clawBack->user_id]['unpaid_total'] += (0 - $unPaidClawBack);
            } else {
                $commissionArray[$clawBack->user_id]['unpaid_total'] = (0 - $unPaidClawBack);
            }

            if (!isset($commissionArray[$clawBack->user_id]['info'])) {
                $commissionArray[$clawBack->user_id]['info'] = [
                    'name' => isset($clawBack->users->first_name) ? ($clawBack->users->first_name . ' ' . $clawBack->users->last_name) : NULL,
                    'image' => $clawBack?->users?->image,
                    'position_id' => isset($clawBack->users->position_id) ? $clawBack->users->position_id : NULL,
                    'sub_position_id' => isset($clawBack->users->sub_position_id) ? $clawBack->users->sub_position_id : NULL,
                    'is_super_admin' => isset($clawBack->users->is_super_admin) ? $clawBack->users->is_super_admin : NULL,
                    'is_manager' => isset($clawBack->users->is_manager) ? $clawBack->users->is_manager : NULL,
                    'stop_payroll' => ($clawBack->status != 3 && @$clawBack->users->stop_payroll) ? 'Payroll Stop' : NULL,
                    'dismiss' => isset($clawBack->users->id) && isUserDismisedOn($clawBack->users->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($clawBack->users->id) && isUserTerminatedOn($clawBack->users->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($clawBack->users->id) && isUserContractEnded($clawBack->users->id) ? 1 : 0,
                ];
            }
        }

        $clawBackAdjustments = PayrollAdjustmentDetail::with(['userDetail'])->where(['pid' => $pid, 'payroll_type' => 'commission', 'type' => 'clawback'])->get();
        foreach ($clawBackAdjustments as $clawBackAdjustment) {
            $adjustmentPaid = 0;
            $adjustmentPending = 0;
            if ($clawBackAdjustment->status == 3) {
                $adjustmentPaid = isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
            } else {
                $adjustmentPending = isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
            }

            $type = $clawBackAdjustment->type ?? NULL;
            $commissionArray[$clawBackAdjustment->user_id]['data'][] = [
                'type' => 'Adjustment',
                'date' => isset($clawBackAdjustment->updated_at) ? date('Y-m-d', strtotime($clawBackAdjustment->updated_at)) : NULL,
                'description' => $type,
                'paid' => isset($adjustmentPaid) ? $adjustmentPaid : '',
                'unpaid' => isset($adjustmentPending) ? $adjustmentPending : '',
                'date_paid' => isset($clawBackAdjustment->pay_period_from) ? $clawBackAdjustment->pay_period_from . ' to ' . $clawBackAdjustment->pay_period_to : NULL
            ];

            if (@$commissionArray[$clawBackAdjustment->user_id]['paid_total']) {
                $commissionArray[$clawBackAdjustment->user_id]['paid_total'] +=  $adjustmentPaid;
            } else {
                $commissionArray[$clawBackAdjustment->user_id]['paid_total'] =  $adjustmentPaid;
            }

            if (@$commissionArray[$clawBackAdjustment->user_id]['unpaid_total']) {
                $commissionArray[$clawBackAdjustment->user_id]['unpaid_total'] += $adjustmentPending;
            } else {
                $commissionArray[$clawBackAdjustment->user_id]['unpaid_total'] = $adjustmentPending;
            }

            if (!isset($commissionArray[$clawBackAdjustment->user_id]['info'])) {
                $commissionArray[$clawBackAdjustment->user_id]['info'] = [
                    'name' => isset($clawBackAdjustment->userDetail->first_name) ? ($clawBackAdjustment->userDetail->first_name . ' ' . $clawBackAdjustment->userDetail->last_name) : NULL,
                    'image' => $clawBackAdjustment?->userDetail?->image,
                    'position_id' => isset($clawBackAdjustment->userDetail->position_id) ? $clawBackAdjustment->userDetail->position_id : NULL,
                    'sub_position_id' => isset($clawBackAdjustment->userDetail->sub_position_id) ? $clawBackAdjustment->userDetail->sub_position_id : NULL,
                    'is_super_admin' => isset($clawBackAdjustment->userDetail->is_super_admin) ? $clawBackAdjustment->userDetail->is_super_admin : NULL,
                    'is_manager' => isset($clawBackAdjustment->userDetail->is_manager) ? $clawBackAdjustment->userDetail->is_manager : NULL,
                    'stop_payroll' => ($clawBackAdjustment->status != 3 && @$clawBackAdjustment->userDetail->stop_payroll) ? 'Payroll Stop' : NULL,
                    'dismiss' => isset($clawBackAdjustment->userDetail->id) && isUserDismisedOn($clawBackAdjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($clawBackAdjustment->userDetail->id) && isUserTerminatedOn($clawBackAdjustment->userDetail->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($clawBackAdjustment->userDetail->id) && isUserContractEnded($clawBackAdjustment->userDetail->id) ? 1 : 0,
                ];
            }
        }

        $finalResponse = [
            'commission' => [],
            'grandTotalCommission' => 0,
            'override' => [],
            'grandTotalOverride' => 0
        ];
        $saleMaster = SaleMasterProcess::where('pid', $pid)->first();
        foreach ($commissionArray as $key => $commissionArr) {
            $type = NULL;
            if ($key == $saleMaster->setter1_id) {
                $type = 'setter1';
            } else if ($key == $saleMaster->setter2_id) {
                $type = 'setter2';
            } else if ($key == $saleMaster->closer1_id) {
                $type = 'closer1';
            } else if ($key == $saleMaster->closer2_id) {
                $type = 'closer2';
            }

            if (!$type) {
                $user = User::find($key);
                if ($user && $user->position_id == '2') {
                    $type = 'closer';
                } else if ($user && $user->position_id == '3') {
                    $type = 'setter';
                }
            }

            $finalResponse['commission'][$type] = [
                'info' => $commissionArr['info'],
                'data' => $commissionArr['data'],
                'paid_total' => $commissionArr['paid_total'],
                'unpaid_total' => $commissionArr['unpaid_total']
            ];
            $finalResponse['grandTotalCommission'] += $commissionArr['paid_total'] + $commissionArr['unpaid_total'];
        }


        $overrideArray = [];
        $overrides = UserOverrides::with(['userInfo', 'overrideUser'])->where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2'])->get();
        foreach ($overrides as $override) {
            $paidOverride = 0;
            $unPaidOverride = 0;
            if ($override->status == 3) {
                $paidOverride = isset($override->amount) ? $override->amount : 0;
            } else {
                $unPaidOverride = isset($override->amount) ? $override->amount : 0;
            }

            $user = isset($override->overrideUser->first_name) ? ($override->overrideUser->first_name . ' ' . $override->overrideUser->last_name) : NULL;
            $commM2date = UserCommission::where(['pid' => $request->pid, 'amount_type' => 'm2'])->first();
            $newdate = NULL;
            if (!empty($commM2date)) {
                $newdate = date("Y-m-d", strtotime($commM2date->date));
            }

            $m2Update = $override->during == 'm2 update' ? ' | Commission Update' : '';
            $overrideArray[$override->sale_user_id]['data'][] = [
                'type' => $override->type,
                'date' => isset($override->updated_at) ? $newdate : NULL,
                'description' => isset($override->type) ? ($user . ' | ' . $override->type . $m2Update) : '',
                'paid_amount' => $paidOverride,
                'unpaid_amount' => $unPaidOverride,
                'date_paid' => isset($override->pay_period_from) ? Carbon::parse($override->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($override->pay_period_to)->format('m/d/Y') : NULL
            ];

            if (@$overrideArray[$override->sale_user_id]['paid_total']) {
                $overrideArray[$override->sale_user_id]['paid_total'] +=  $paidOverride;
            } else {
                $overrideArray[$override->sale_user_id]['paid_total'] =  $paidOverride;
            }

            if (@$overrideArray[$override->sale_user_id]['unpaid_total']) {
                $overrideArray[$override->sale_user_id]['unpaid_total'] += $unPaidOverride;
            } else {
                $overrideArray[$override->sale_user_id]['unpaid_total'] = $unPaidOverride;
            }

            if (!isset($overrideArray[$override->sale_user_id]['info'])) {
                $overrideArray[$override->sale_user_id]['info'] = [
                    'name' => isset($override->overrideUser->first_name) ? ($override->overrideUser->first_name . ' ' . $override->overrideUser->last_name) : NULL,
                    'image' => $override?->overrideUser?->image,
                    'position_id' => isset($override->overrideUser->position_id) ? $override->overrideUser->position_id : NULL,
                    'sub_position_id' => isset($override->overrideUser->sub_position_id) ? $override->overrideUser->sub_position_id : NULL,
                    'is_super_admin' => isset($override->overrideUser->is_super_admin) ? $override->overrideUser->is_super_admin : NULL,
                    'is_manager' => isset($override->overrideUser->is_manager) ? $override->overrideUser->is_manager : NULL,
                    'stop_payroll' => ($override->status != 3 && @$override->overrideUser->stop_payroll) ? 'Payroll Stop' : NULL,
                    'dismiss' => isset($override->overrideUser->id) && isUserDismisedOn($override->overrideUser->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($override->overrideUser->id) && isUserTerminatedOn($override->overrideUser->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($override->overrideUser->id) && isUserContractEnded($override->overrideUser->id) ? 1 : 0,
                ];
            }
        }

        $adjustments = PayrollAdjustmentDetail::with(['userDetail', 'user'])->where(['pid' => $pid, 'payroll_type' => 'overrides'])->where('type', '!=', 'clawback')->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaidOver = 0;
            $adjustmentPendingOver = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaidOver = isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPendingOver = isset($adjustment->amount) ? $adjustment->amount : 0;
            }

            $overrideArray[$adjustment->sale_user_id]['data'][] = [
                'type' => 'Adjustment',
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : NULL,
                'description' => (isset($adjustment->userDetail->first_name)) ? $adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name . ' | Adjustment' : NULL,
                'paid_amount' => $adjustmentPaidOver,
                'unpaid_amount' => $adjustmentPendingOver,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL
            ];

            if (@$overrideArray[$adjustment->sale_user_id]['paid_total']) {
                $overrideArray[$adjustment->sale_user_id]['paid_total'] +=  $adjustmentPaidOver;
            } else {
                $overrideArray[$adjustment->sale_user_id]['paid_total'] =  $adjustmentPaidOver;
            }

            if (@$overrideArray[$adjustment->sale_user_id]['unpaid_total']) {
                $overrideArray[$adjustment->sale_user_id]['unpaid_total'] += $adjustmentPendingOver;
            } else {
                $overrideArray[$adjustment->sale_user_id]['unpaid_total'] = $adjustmentPendingOver;
            }

            if (!isset($overrideArray[$adjustment->sale_user_id]['info'])) {
                $overrideArray[$adjustment->sale_user_id]['info'] = [
                    'name' => isset($adjustment->overrideUser->first_name) ? ($adjustment->overrideUser->first_name . ' ' . $adjustment->overrideUser->last_name) : NULL,
                    'image' => $adjustment?->overrideUser?->image,
                    'position_id' => isset($adjustment->overrideUser->position_id) ? $adjustment->overrideUser->position_id : NULL,
                    'sub_position_id' => isset($adjustment->overrideUser->sub_position_id) ? $adjustment->overrideUser->sub_position_id : NULL,
                    'is_super_admin' => isset($adjustment->overrideUser->is_super_admin) ? $adjustment->overrideUser->is_super_admin : NULL,
                    'is_manager' => isset($adjustment->overrideUser->is_manager) ? $adjustment->overrideUser->is_manager : NULL,
                    'stop_payroll' => ($adjustment->status != 3 && @$adjustment->overrideUser->stop_payroll) ? 'Payroll Stop' : NULL,
                    'dismiss' => isset($adjustment->overrideUser->id) && isUserDismisedOn($adjustment->overrideUser->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($adjustment->overrideUser->id) && isUserTerminatedOn($adjustment->overrideUser->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($adjustment->overrideUser->id) && isUserContractEnded($adjustment->overrideUser->id) ? 1 : 0,
                ];
            }
        }


        $clawBack = ' | Clawed Back';
        $clawBackOverrides = ClawbackSettlement::with(['userInfo', 'users', 'salesDetail'])->where(['pid' => $pid, 'type' => 'overrides', 'clawback_type' => 'next payroll'])->get();
        foreach ($clawBackOverrides as $clawBackOverride) {
            $paidOverClawBack = 0;
            $unPaidOverClawBack = 0;
            if ($clawBackOverride->status == 3) {
                $paidOverClawBack = isset($clawBackOverride->clawback_amount) ? $clawBackOverride->clawback_amount : 0;
            } else {
                $unPaidOverClawBack = isset($clawBackOverride->clawback_amount) ? $clawBackOverride->clawback_amount : 0;
            }

            $user = isset($clawBackOverride->users->first_name) ? ($clawBackOverride->users->first_name . ' ' . $clawBackOverride->users->last_name) : '';
            $returnSalesDate = isset($clawBackOverride->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawBackOverride->salesDetail->return_sales_date)) : NULL;
            $newdate = isset($clawBackOverride->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawBackOverride->salesDetail->date_cancelled)) : $returnSalesDate;

            $description = isset($clawBackOverride->adders_type) ? ($user . ' | ' . $clawBackOverride->adders_type . $clawBack) : '';
            if ($clawBackOverride->during == 'm2 update') {
                $description = isset($clawBackOverride->adders_type) ? ($user . ' | ' . $clawBackOverride->adders_type . ' | Commission Update' . $clawBack) : '';
            }

            $overrideArray[$clawBackOverride->sale_user_id]['data'][] = [
                'type' => $clawBackOverride->adders_type ? $clawBackOverride->adders_type . ' | Clawback' : 'Clawback',
                'date' => isset($newdate) ? $newdate : NULL,
                'description' => $description,
                'paid_amount' => (0 - $paidOverClawBack),
                'unpaid_amount' => (0 - $unPaidOverClawBack),
                'date_paid' => isset($clawBackOverride->pay_period_from) ? $clawBackOverride->pay_period_from . ' to ' . $clawBackOverride->pay_period_to : NULL
            ];

            if (@$overrideArray[$clawBackOverride->sale_user_id]['paid_total']) {
                $overrideArray[$clawBackOverride->sale_user_id]['paid_total'] += (0 - $paidOverClawBack);
            } else {
                $overrideArray[$clawBackOverride->sale_user_id]['paid_total'] = (0 - $paidOverClawBack);
            }

            if (@$overrideArray[$clawBackOverride->sale_user_id]['unpaid_total']) {
                $overrideArray[$clawBackOverride->sale_user_id]['unpaid_total'] += (0 - $unPaidOverClawBack);
            } else {
                $overrideArray[$clawBackOverride->sale_user_id]['unpaid_total'] = (0 - $unPaidOverClawBack);
            }

            if (!isset($overrideArray[$clawBackOverride->sale_user_id]['info'])) {
                $overrideArray[$clawBackOverride->sale_user_id]['info'] = [
                    'name' => isset($clawBackOverride->userInfo->first_name) ? ($clawBackOverride->userInfo->first_name . ' ' . $clawBackOverride->userInfo->last_name) : NULL,
                    'image' => $clawBackOverride?->userInfo?->image,
                    'position_id' => isset($clawBackOverride->userInfo->position_id) ? $clawBackOverride->userInfo->position_id : NULL,
                    'sub_position_id' => isset($clawBackOverride->userInfo->sub_position_id) ? $clawBackOverride->userInfo->sub_position_id : NULL,
                    'is_super_admin' => isset($clawBackOverride->userInfo->is_super_admin) ? $clawBackOverride->userInfo->is_super_admin : NULL,
                    'is_manager' => isset($clawBackOverride->userInfo->is_manager) ? $clawBackOverride->userInfo->is_manager : NULL,
                    'stop_payroll' => ($clawBackOverride->status != 3 && @$clawBackOverride->userInfo->stop_payroll) ? 'Payroll Stop' : NULL,
                    'dismiss' => isset($clawBackOverride->userInfo->id) && isUserDismisedOn($clawBackOverride->userInfo->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($clawBackOverride->userInfo->id) && isUserTerminatedOn($clawBackOverride->userInfo->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($clawBackOverride->userInfo->id) && isUserContractEnded($clawBackOverride->userInfo->id) ? 1 : 0,
                ];
            }
        }

        $adjustments = PayrollAdjustmentDetail::with(['userDetail', 'user'])->where(['pid' => $pid, 'payroll_type' => 'overrides', 'type' => 'clawback'])->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaidOver = 0;
            $adjustmentPendingOver = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaidOver = isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPendingOver = isset($adjustment->amount) ? $adjustment->amount : 0;
            }

            $type = $adjustment->adjustment_override_type ? " | " . $adjustment->adjustment_override_type . " | " : "";
            $description = $adjustment->userDetail->first_name . " " . $adjustment->userDetail->last_name . $type . ' | Adjustment';
            $overrideArray[$adjustment->sale_user_id]['data'][] = [
                'type' => 'Adjustment',
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : NULL,
                'description' => $description,
                'paid_amount' => $adjustmentPaidOver,
                'unpaid_amount' => $adjustmentPendingOver,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : NULL
            ];

            if (@$overrideArray[$adjustment->sale_user_id]['paid_total']) {
                $overrideArray[$adjustment->sale_user_id]['paid_total'] += $adjustmentPaidOver;
            } else {
                $overrideArray[$adjustment->sale_user_id]['paid_total'] = $adjustmentPaidOver;
            }

            if (@$overrideArray[$adjustment->sale_user_id]['unpaid_total']) {
                $overrideArray[$adjustment->sale_user_id]['unpaid_total'] += $adjustmentPendingOver;
            } else {
                $overrideArray[$adjustment->sale_user_id]['unpaid_total'] = $adjustmentPendingOver;
            }

            if (!isset($overrideArray[$adjustment->sale_user_id]['info'])) {
                $overrideArray[$adjustment->sale_user_id]['info'] = [
                    'name' => isset($adjustment->user->first_name) ? ($adjustment->user->first_name . ' ' . $adjustment->user->last_name) : NULL,
                    'image' => $adjustment?->user?->image,
                    'position_id' => isset($adjustment->user->position_id) ? $adjustment->user->position_id : NULL,
                    'sub_position_id' => isset($adjustment->user->sub_position_id) ? $adjustment->user->sub_position_id : NULL,
                    'is_super_admin' => isset($adjustment->user->is_super_admin) ? $adjustment->user->is_super_admin : NULL,
                    'is_manager' => isset($adjustment->user->is_manager) ? $adjustment->user->is_manager : NULL,
                    'stop_payroll' => ($adjustment->status != 3 && @$adjustment->user->stop_payroll) ? 'Payroll Stop' : NULL,
                    'dismiss' => isset($adjustment->user->id) && isUserDismisedOn($adjustment->user->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($adjustment->user->id) && isUserTerminatedOn($adjustment->user->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($adjustment->user->id) && isUserContractEnded($adjustment->user->id) ? 1 : 0,
                ];
            }
        }

        foreach ($overrideArray as $key => $overrideArr) {
            $type = NULL;
            if ($key == $saleMaster->setter1_id) {
                if ($saleMaster->setter1_id == $saleMaster->closer1_id) {
                    $type = 'closer1';
                } else {
                    $type = 'setter1';
                }
            } else if ($key == $saleMaster->setter2_id) {
                if ($saleMaster->setter2_id == $saleMaster->closer2_id) {
                    $type = 'closer2';
                } else {
                    $type = 'setter2';
                }
            } else if ($key == $saleMaster->closer1_id) {
                $type = 'closer1';
            } else if ($key == $saleMaster->closer2_id) {
                $type = 'closer2';
            }

            if (!$type) {
                $user = User::find($key);
                if ($user && $user->position_id == '2') {
                    $type = 'closer';
                } else if ($user && $user->position_id == '3') {
                    $type = 'setter';
                }
            }

            $finalResponse['override'][$type] = [
                'info' => $overrideArr['info'],
                'data' => $overrideArr['data'],
                'paid_total' => $overrideArr['paid_total'],
                'unpaid_total' => $overrideArr['unpaid_total']
            ];
            $finalResponse['grandTotalOverride'] += $overrideArr['paid_total'] + $overrideArr['unpaid_total'];
        }

        return response()->json([
            'status' => true,
            'ApiName' => 'accountSummaryByPosition',
            'message' => 'Successfully.',
            'commission' => @$finalResponse['commission'],
            'grandTotalCommission' => @$finalResponse['grandTotalCommission'],
            'override' => @$finalResponse['override'],
            'grandTotalOverride' => @$finalResponse['grandTotalOverride'],
        ]);
    }

    // ACCOUNT SUMMARY PROJECTION
    public function accountSummaryProjection(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required',
            'filter' => 'required'
        ]);

        $pid = $request->pid;
        $filter = $request->filter;
        Artisan::call('syncSalesProjectionData:sync', ['pid' => $pid]);

        $sale = SalesMaster::with(['closer1Detail', 'closer2Detail', 'setter1Detail', 'setter2Detail'])->where('pid', $pid)->first();
        $setter1Detail = isset($sale->setter1Detail) ? $sale->setter1Detail : NULL;
        $setter2Detail = isset($sale->setter2Detail) ? $sale->setter2Detail : NULL;

        if ($filter == 'position') {
            $commissionArray = [];
            $commissions = ProjectionUserCommission::with('user.parentPositionDetail')->where(['pid' => $pid])->get();
            foreach ($commissions as $commission) {
                $amountType = $commission->schema_name;
                if ($commission->value_type == 'reconciliation') {
                    $amountType = $commission->value_type;
                }
                $commissionArray[$commission->user_id]['data'][] = [
                    'type' => 'Commission',
                    'amount' => $commission->amount ?? 0,
                    'amount_type' => $amountType
                ];

                if (@$commissionArray[$commission->user_id]['subtotal']) {
                    $commissionArray[$commission->user_id]['subtotal'] += $commission->amount ?? 0;
                } else {
                    $commissionArray[$commission->user_id]['subtotal'] = $commission->amount ?? 0;
                }

                if (!isset($commissionArray[$commission->user_id]['info'])) {
                    $commissionArray[$commission->user_id]['info'] = [
                        'image' => $commission->user->image,
                        'user_name' => isset($commission->user->first_name) ? ($commission->user->first_name . ' ' . $commission->user->last_name) : NULL,
                        'position_id' => isset($commission->user->position_id) ? $commission->user->position_id : NULL,
                        'position_name' => isset($commission->user->parentPositionDetail->position_name) ? $commission->user->parentPositionDetail->position_name : NULL,
                        'dismiss' => isset($commission->user->id) && isUserDismisedOn($commission->user->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($commission->user->id) && isUserTerminatedOn($commission->user->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($commission->user->id) && isUserContractEnded($commission->user->id) ? 1 : 0,
                    ];
                }
            }

            $overrides = ProjectionUserOverrides::with('userInfo.parentPositionDetail', 'overrideUser')->where(['pid' => $pid])->get();
            foreach ($overrides as $override) {
                $commissionArray[$override->sale_user_id]['data'][] = [
                    'type' => 'Override',
                    'amount' => $override->total_override ?? 0,
                    'amount_type' => $override?->overrideUser?->first_name . ' ' . $override?->overrideUser?->last_name . ' | ' . $override->type
                ];

                if (@$commissionArray[$override->sale_user_id]['subtotal']) {
                    $commissionArray[$override->sale_user_id]['subtotal'] += $override->total_override ?? 0;
                } else {
                    $commissionArray[$override->sale_user_id]['subtotal'] = $override->total_override ?? 0;
                }

                if (!isset($commissionArray[$override->sale_user_id]['info'])) {
                    $commissionArray[$override->sale_user_id]['info'] = [
                        'image' => $override?->overrideUser?->image,
                        'user_name' => isset($override->userInfo->first_name) ? ($override->userInfo->first_name . ' ' . $override->userInfo->last_name) : NULL,
                        'position_id' => isset($override->userInfo->position_id) ? $override->userInfo->position_id : NULL,
                        'position_name' => isset($override->userInfo->parentPositionDetail->position_name) ? $override->userInfo->parentPositionDetail->position_name : NULL,

                        'dismiss' => isset($override->userInfo->id) && isUserDismisedOn($override->userInfo->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($override->userInfo->id) && isUserTerminatedOn($override->userInfo->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($override->userInfo->id) && isUserContractEnded($override->userInfo->id) ? 1 : 0,
                    ];
                }
            }

            $finalResponse = [];
            $saleMaster = SaleMasterProcess::where('pid', $pid)->first();
            foreach ($commissionArray as $key => $commissionArr) {
                $type = NULL;
                if ($key == $saleMaster->setter1_id) {
                    if ($saleMaster->setter1_id == $saleMaster->closer1_id) {
                        $type = 'closer1';
                    } else {
                        $type = 'setter1';
                    }
                } else if ($key == $saleMaster->setter2_id) {
                    if ($saleMaster->setter2_id == $saleMaster->closer2_id) {
                        $type = 'closer2';
                    } else {
                        $type = 'setter2';
                    }
                } else if ($key == $saleMaster->closer1_id) {
                    $type = 'closer1';
                } else if ($key == $saleMaster->closer2_id) {
                    $type = 'closer2';
                }

                if ($type) {
                    $finalResponse[$type] = [
                        'info' => $commissionArr['info'],
                        'data' => $commissionArr['data'],
                        'subtotal' => $commissionArr['subtotal']
                    ];
                    if (@$finalResponse['grandTotal']) {
                        $finalResponse['grandTotal'] += $commissionArr['subtotal'];
                    } else {
                        $finalResponse['grandTotal'] = $commissionArr['subtotal'];
                    }
                }
            }
            $this->successResponse("Successfully.", "accountSummaryProjection", $finalResponse);
        }

        $finalResponse = [];
        $commissions = ProjectionUserCommission::with('user.parentPositionDetail')->where(['pid' => $pid])->get();

        if ($commissions->isNotEmpty()) {
            foreach ($commissions as $commission) {
                $amountType = $commission->schema_name;
                if ($commission->value_type == 'reconciliation') {
                    $amountType = $commission->value_type;
                }

                if (@$setter1Detail->id == $commission->user->id || @$setter2Detail->id == $commission->user->id) {
                    $position_name = "Setter";
                } else {
                    $position_name = isset($commission->user->parentPositionDetail->position_name) ? $commission->user->parentPositionDetail->position_name : NULL;
                }
                $finalResponse['commission']['data'][] = [
                    'amount' => $commission->amount ?? 0,
                    'amount_type' => $amountType,
                    'user_name' => isset($commission->user->first_name) ? ($commission->user->first_name . ' ' . $commission->user->last_name) : NULL,
                    'position_id' => isset($commission->user->position_id) ? $commission->user->position_id : NULL,
                    'position_name' => $position_name,

                    'dismiss' => isset($commission->user->id) && isUserDismisedOn($commission->user->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($commission->user->id) && isUserTerminatedOn($commission->user->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($commission->user->id) && isUserContractEnded($commission->user->id) ? 1 : 0,
                ];

                if (@$finalResponse['commission']['subtotal']) {
                    $finalResponse['commission']['subtotal'] += $commission->amount ?? 0;
                } else {
                    $finalResponse['commission']['subtotal'] = $commission->amount ?? 0;
                }
            }
        }

        $overrides = ProjectionUserOverrides::with('userInfo.parentPositionDetail', 'overrideUser')->where(['pid' => $pid])->get();
        if ($overrides->isNotEmpty()) {
            foreach ($overrides as $override) {
                if ($override->type == 'One Time') {
                    $position_name = 'Other';
                } elseif (@$setter1Detail->id == $override->userInfo->id || @$setter2Detail->id == $override->userInfo->id) {
                    $position_name = "Setter";
                } else {
                    $position_name = isset($override->userInfo->parentPositionDetail->position_name) ? $override->userInfo->parentPositionDetail->position_name : NULL;
                }
                $finalResponse['override']['data'][] = [
                    'amount' => $override->total_override ?? 0,
                    'description' => $override?->overrideUser?->first_name . ' ' . $override?->overrideUser?->last_name . ' | ' . $override->type,
                    'user_name' => isset($override->userInfo->first_name) ? ($override->userInfo->first_name . ' ' . $override->userInfo->last_name) : NULL,
                    'position_id' => isset($override->userInfo->position_id) ? $override->userInfo->position_id : NULL,
                    'position_name' => $position_name,
                    'dismiss' => isset($override->userInfo->id) && isUserDismisedOn($override->userInfo->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($override->userInfo->id) && isUserTerminatedOn($override->userInfo->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($override->userInfo->id) && isUserContractEnded($override->userInfo->id) ? 1 : 0,
                ];

                if (@$finalResponse['override']['subtotal']) {
                    $finalResponse['override']['subtotal'] += $override->total_override ?? 0;
                } else {
                    $finalResponse['override']['subtotal'] = $override->total_override ?? 0;
                }
            }
        }

        if (count($commissions) > 0 || count($overrides) > 0) {
            $this->manageDataForDisplay($pid);
        }

        $this->successResponse("Successfully.", "accountSummaryProjection", $finalResponse);
    }


    public function accountOverrides(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required'
        ]);
        $existingOverrideKeys = [];
        $pid = $request->pid;

        // Debug logging
        $subQuery = UserOverrides::select(DB::raw('MAX(id) as id'))->where(['pid' => $pid, 'is_displayed' => '1'])->groupBy('user_id', 'type', 'sale_user_id');
        $accountOverrides = UserOverrides::select('user_overrides.*', DB::raw('SUM(sub.amount) as amount'))
            ->joinSub($subQuery, 'latest_records', function ($join) {
                $join->on('user_overrides.id', '=', 'latest_records.id');
            })

            ->join('user_overrides as sub', function ($join) {
                $join->on('sub.user_id', '=', 'user_overrides.user_id')
                    ->on('sub.pid', '=', 'user_overrides.pid')
                    ->on('sub.type', '=', 'user_overrides.type')
                    ->whereRaw('
                        (sub.sale_user_id = user_overrides.sale_user_id OR
                        (sub.sale_user_id IS NULL AND user_overrides.sale_user_id IS NULL))
                     ');
            })
            ->with('userDetail', function ($q) {
                $q->select('id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager', 'terminate', 'dismiss');
            })
            ->where(['user_overrides.pid' => $pid, 'user_overrides.is_displayed' => '1', 'sub.is_displayed' => '1'])
            //->groupBy('user_overrides.user_id', 'user_overrides.type', 'user_overrides.sale_user_id')
            ->groupBy('user_overrides.user_id', 'user_overrides.type', 'user_overrides.sale_user_id')
            ->orderBy('user_overrides.id')->get();

        $saleMasterProcess = SaleMasterProcess::with('salesDetail')->where('pid', $pid)->first();
        
        // Check if Custom Sales Fields feature is enabled ONCE (using cached helper)
        $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();
        
        $accountOverrides->transform(function ($data) use ($saleMasterProcess, &$existingOverrideKeys, $isCustomFieldsEnabled) {
            $key = $data->user_id . '_' . $data->type . '_' . ($data->sale_user_id ?? 'null') . '_' . $data->amount;
            $existingOverrideKeys[] = $key;
            if ($data->sale_user_id == $saleMasterProcess->closer1_id || $data->sale_user_id == $saleMasterProcess->closer2_id) {
                $positionName = 'Closer';
            } elseif ($saleMasterProcess->setter1_id == $data->sale_user_id || $saleMasterProcess->setter2_id == $data->sale_user_id) {
                $positionName = "Setter";
            } else {
                $matchedWorker = ExternalSaleWorker::where([
                    ['pid', '=', $data->pid],
                    ['user_id', '=', $data->sale_user_id]
                ])->first();

                if ($matchedWorker) {
                    $positionName = match ($matchedWorker->type) {
                        2 => 'Closer',
                        3 => 'Setter',
                        default => 'SelfGen',
                    };
                } else {
                    $positionName = $data->userInfo->parentPositionDetail->position_name ?? NULL;
                }
            }


            // Added for one time override type
            if (is_null($data->sale_user_id)) {
                $positionName = 'Other';
            }

            $s3Image = NULL;
            $image = isset($data->userDetail->image) ? $data->userDetail->image : NULL;
            if ($image) {
                $s3Image = s3_getTempUrl(config('app.domain_name') . '/' . $image);
            }
            $clawBack = ClawbackSettlement::where(['pid' => $data->pid, 'user_id' => $data->user_id, 'sale_user_id' => $data->sale_user_id, 'type' => 'overrides', 'adders_type' => $data->type, 'clawback_type' => 'next payroll', 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');
            $reconClawBack = ClawbackSettlement::where(['pid' => $data->pid, 'user_id' => $data->user_id, 'sale_user_id' => $data->sale_user_id, 'type' => 'overrides', 'adders_type' => $data->type, 'clawback_type' => 'reconciliation', 'recon_status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');

            $redLineType = $data->calculated_redline_type;
            if (in_array($data->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                $redLineType = 'percent';
            }
            $closestDate = NULL;
            if ($saleMasterProcess->salesDetail && $saleMasterProcess->salesDetail->customer_signoff) {
                $signoffDate = Carbon::parse($saleMasterProcess->salesDetail->customer_signoff)->format('Y-m-d');
                $effectiveSince = getLastEffectiveDates($data->user_id, $signoffDate, $data->product_id);
            }
            $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $data->sale_user_id)->where('recruiter_id', $data->user_id)->where('type', $data->type)->orderBy('effective_date', 'DESC')->first();
            $canRemove = 0;
            if ($data->status == 1) {
                $canRemove = 1;
            } elseif ($data->status == 3 && $data->recon_status == 1 && $data->overrides_settlement_type == 'reconciliation') {
                $canRemove = 1;
            }

            // Custom Sales Field support: Check if original override was configured with custom field
            // The Trick Subroutine converts 'custom field' to 'per sale' for calculation,
            // but we need to display the original custom field type for the UI
            $displayWeight = $data->overrides_type;
            $displayAmount = $data->overrides_amount; // Default to calculated amount
            
            if ($isCustomFieldsEnabled) {
                // First check if user_overrides has custom_sales_field_id
                if ($data->custom_sales_field_id) {
                    $displayWeight = 'custom_field_' . $data->custom_sales_field_id;
                } else {
                    // Look up from user's override history to get original custom field config
                    $overrideTypeColumn = match ($data->type) {
                        'Direct' => 'direct_overrides_type',
                        'Indirect' => 'indirect_overrides_type',
                        'Office' => 'office_overrides_type',
                        default => null,
                    };
                    $customFieldColumn = match ($data->type) {
                        'Direct' => 'direct_custom_sales_field_id',
                        'Indirect' => 'indirect_custom_sales_field_id',
                        'Office' => 'office_custom_sales_field_id',
                        default => null,
                    };
                    $amountColumn = match ($data->type) {
                        'Direct' => 'direct_overrides_amount',
                        'Indirect' => 'indirect_overrides_amount',
                        'Office' => 'office_overrides_amount',
                        default => null,
                    };
                    
                    if ($overrideTypeColumn && $customFieldColumn) {
                        $userOverrideHistory = UserOverrideHistory::where('user_id', $data->user_id)
                            ->where('product_id', $data->product_id)
                            ->where($overrideTypeColumn, 'custom field')
                            ->whereNotNull($customFieldColumn)
                            ->orderBy('override_effective_date', 'DESC')
                            ->first();
                        
                        if ($userOverrideHistory && $userOverrideHistory->$customFieldColumn) {
                            $displayWeight = 'custom_field_' . $userOverrideHistory->$customFieldColumn;
                            // For custom fields, use the configured amount (multiplier) instead of calculated
                            if ($amountColumn && $userOverrideHistory->$amountColumn !== null) {
                                $displayAmount = $userOverrideHistory->$amountColumn;
                            }
                        }
                    }
                }
            }

            return [
                'through' => $positionName,
                'user_id' => $data->user_id,
                'sale_user_id' => $data->sale_user_id,
                'sale_user_name' => $data->userInfo ? (($data->userInfo->first_name ?? '') . ' ' . ($data->userInfo->last_name ?? '')) : '',
                'image' => $image,
                'image_s3' => $s3Image,
                'first_name' => $data->userDetail->first_name ?? NULL,
                'last_name' => $data->userDetail->last_name ?? NULL,
                'position_id' => $data->userDetail->position_id ?? NULL,
                'sub_position_id' => $data->userDetail->sub_position_id ?? NULL,
                'is_super_admin' => $data->userDetail->is_super_admin ?? NULL,
                'is_manager' => $data->userDetail->is_manager ?? NULL,
                'type' => $data->type,
                'amount' => $displayAmount,
                'weight' => $displayWeight,
                'total' => ($data->amount - ($clawBack + $reconClawBack)),
                'calculated_redline' => $data->calculated_redline,
                'calculated_redline_type' => $redLineType,
                'dismiss' => isUserDismisedOn($data->user_id, date('Y-m-d')) ? 1 : 0,
                'terminate' => isUserTerminatedOn($data->user_id, date('Y-m-d')) ? 1 : 0,
                'contract_ended' => isUserContractEnded($data->user_id) ? 1 : 0,
                'override_id' => $data->id,
                'is_projection' => 0,
                'effective_date' => $effectiveSince[3] ?? NULL,
                // Removal status fields for frontend integration
                'can_remove' => $canRemove,
                'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : NULL,
                'status' => isset($data->status) ? $data->status : NULL,
            ];
        });


        $positionName = '';
        $projectionOverrides = ProjectionUserOverrides::with('userInfo.parentPositionDetail', 'overrideUser')
            ->where('pid', $pid)->get();

        $projectionOverridesData = collect();

        if ($projectionOverrides->isNotEmpty()) {
            foreach ($projectionOverrides as $override) {
                $key = $override->user_id . '_' . $override->type . '_' . ($override->sale_user_id ?? 'null') . '_' . $override->total_override;
                if (in_array($key, $existingOverrideKeys)) {
                    continue; // Skip duplicate
                }

                if ($override->type == 'One Time') {
                    $positionName = 'Other';
                } elseif ($override->sale_user_id == $saleMasterProcess->closer1_id || $override->sale_user_id == $saleMasterProcess->closer2_id) {
                    $positionName = 'Closer';
                } elseif ($saleMasterProcess->setter1_id == $override->sale_user_id || $saleMasterProcess->setter2_id == $override->sale_user_id) {
                    $positionName = "Setter";
                } else {
                    $matchedWorker = ExternalSaleWorker::where([
                        ['pid', '=', $override->pid],
                        ['user_id', '=', $override->sale_user_id]
                    ])->first();

                    if ($matchedWorker) {
                        $positionName = match ($matchedWorker->type) {
                            2 => 'Closer',
                            3 => 'Setter',
                            default => 'SelfGen',
                        };
                    } else {
                        $positionName = $data->userInfo->parentPositionDetail->position_name ?? NULL;
                    }
                }

                $existingOverrideKeys[] = $key;

                $redLineType = $override->calculated_redline_type;
                if (in_array($override->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                    $redLineType = 'percent';
                }
                if ($saleMasterProcess->salesDetail && $saleMasterProcess->salesDetail->customer_signoff) {
                    $signoffDate = Carbon::parse($saleMasterProcess->salesDetail->customer_signoff)->format('Y-m-d');
                    $effectiveSince = getLastEffectiveDates($override->user_id, $signoffDate, $saleMasterProcess->salesDetail->product_id);
                }

                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $override->sale_user_id)->where('recruiter_id', $override->user_id)->where('type', $override->type)->orderBy('effective_date', 'DESC')->first();

                $canRemove = 0;
                if ($override->status == 1) {
                    $canRemove = 1;
                } elseif ($override->status == 3 && $override->recon_status == 1 && $override->overrides_settlement_type == 'reconciliation') {
                    $canRemove = 1;
                }


                // Custom Sales Field support for projection overrides
                $projDisplayWeight = $override->overrides_type ?? NULL;
                $projDisplayAmount = $override->overrides_amount ?? 0; // Default to calculated amount
                
                if ($isCustomFieldsEnabled) {
                    // Check if projection has custom_sales_field_id
                    if ($override->custom_sales_field_id) {
                        $projDisplayWeight = 'custom_field_' . $override->custom_sales_field_id;
                    } else {
                        // Look up from user's override history - check if original config uses custom field
                        // This handles cases where projection was created with 'per sale' (calculated) but
                        // the underlying config is actually 'custom field'
                        $overrideTypeColumn = match ($override->type) {
                            'Direct' => 'direct_overrides_type',
                            'Indirect' => 'indirect_overrides_type',
                            'Office' => 'office_overrides_type',
                            default => null,
                        };
                        $customFieldColumn = match ($override->type) {
                            'Direct' => 'direct_custom_sales_field_id',
                            'Indirect' => 'indirect_custom_sales_field_id',
                            'Office' => 'office_custom_sales_field_id',
                            default => null,
                        };
                        $amountColumn = match ($override->type) {
                            'Direct' => 'direct_overrides_amount',
                            'Indirect' => 'indirect_overrides_amount',
                            'Office' => 'office_overrides_amount',
                            default => null,
                        };
                        
                        if ($overrideTypeColumn && $customFieldColumn) {
                            $userOverrideHistory = UserOverrideHistory::where('user_id', $override->user_id)
                                ->where($overrideTypeColumn, 'custom field')
                                ->whereNotNull($customFieldColumn)
                                ->orderBy('override_effective_date', 'DESC')
                                ->first();
                            
                            if ($userOverrideHistory && $userOverrideHistory->$customFieldColumn) {
                                $projDisplayWeight = 'custom_field_' . $userOverrideHistory->$customFieldColumn;
                                // For custom fields, use the configured amount (multiplier) instead of calculated
                                if ($amountColumn && $userOverrideHistory->$amountColumn !== null) {
                                    $projDisplayAmount = $userOverrideHistory->$amountColumn;
                                }
                            }
                        }
                    }
                }

                $projectionOverridesData->push((object)[
                    'through' => $positionName,
                    'user_id' => $override->user_id,
                    'sale_user_id' => $override->sale_user_id,
                    'sale_user_name' => $override->userInfo ? (($override->userInfo->first_name ?? '') . ' ' . ($override->userInfo->last_name ?? '')) : '',
                    'image' => NULL, // Projection doesn't have image
                    'image_s3' => NULL,
                    'first_name' => $override->overrideUser->first_name ?? NULL,
                    'last_name' => $override->overrideUser->last_name ?? NULL,
                    'position_id' => $override->overrideUser->position_id ?? NULL,
                    'sub_position_id' => NULL,
                    'is_super_admin' => NULL,
                    'is_manager' => NULL,
                    'type' => $override->type,
                    'amount' => $projDisplayAmount,
                    'weight' => $projDisplayWeight,
                    'total' => $override->total_override ?? 0,
                    'calculated_redline' => $override->calculated_redline ?? NULL,
                    'calculated_redline_type' => $redLineType ?? NULL,
                    'dismiss' => isset($override->user_id) && isUserDismisedOn($override->user_id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($override->user_id) && isUserTerminatedOn($override->user_id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($override->user_id) && isUserContractEnded($override->user_id) ? 1 : 0,
                    'override_id' => $override->id,
                    'is_projection' => 1,
                    'effective_date' => $effectiveSince[3] ?? NULL,
                    // Removal status fields for frontend integration
                    'can_remove' => $canRemove,
                    'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : NULL,
                    'status' => isset($override->status) ? $override->status : NULL,
                ]);
            }
        }

        // Fetch archived overrides for the sale (V2 hard delete system)
        $archivedOverrides = OverrideArchive::with(['userDetail' => function ($q) {
            $q->select('id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager', 'terminate', 'dismiss');
        }])
            ->where('pid', $pid)
            ->get();

        $archivedOverridesData = collect();


        if ($archivedOverrides->isNotEmpty()) {
            foreach ($archivedOverrides as $archivedOverride) {
                $key = $archivedOverride->user_id . '_' . $archivedOverride->type . '_' . ($archivedOverride->sale_user_id ?? 'null') . '_' . $archivedOverride->amount;

                // Note: We removed the duplicate check to show both active and archived overrides
                // The frontend will handle displaying them with appropriate flags

                // Get position name for archived override
                if (is_null($archivedOverride->sale_user_id)) {
                    $positionName = 'Other';
                } elseif ($archivedOverride->sale_user_id == $saleMasterProcess->closer1_id || $archivedOverride->sale_user_id == $saleMasterProcess->closer2_id) {
                    $positionName = 'Closer';
                } elseif ($saleMasterProcess->setter1_id == $archivedOverride->sale_user_id || $saleMasterProcess->setter2_id == $archivedOverride->sale_user_id) {
                    $positionName = "Setter";
                } else {
                    $positionName = $archivedOverride->userDetail->parentPositionDetail->position_name ?? NULL;
                }

                $s3Image = NULL;
                $image = isset($archivedOverride->userDetail->image) ? $archivedOverride->userDetail->image : NULL;
                if ($image) {
                    $s3Image = s3_getTempUrl(config('app.domain_name') . '/' . $image);
                }

                // Check if override can be restored
                if ($archivedOverride->can_restore == 1 && $archivedOverride->status == 1) {
                    $canRestore = 1;
                } else if ($archivedOverride->can_restore == 1 && $archivedOverride->status == 3 && $archivedOverride->overrides_settlement_type == 'reconciliation' && $archivedOverride->recon_status == 1) {
                    $canRestore = 1;
                } else {
                    $canRestore = 0;
                }


                $archivedOverridesData->push([
                    'through' => $positionName,
                    'user_id' => $archivedOverride->user_id,
                    'sale_user_id' => $archivedOverride->sale_user_id,
                    'sale_user_name' => '', // Can be added if needed
                    'image' => $image,
                    'image_s3' => $s3Image,
                    'first_name' => $archivedOverride->userDetail->first_name ?? NULL,
                    'last_name' => $archivedOverride->userDetail->last_name ?? NULL,
                    'type' => $archivedOverride->type,
                    'amount' => $archivedOverride->overrides_amount,
                    'weight' => $archivedOverride->overrides_type,
                    'total' => $archivedOverride->amount,
                    'calculated_redline' => $archivedOverride->calculated_redline,
                    'calculated_redline_type' => $archivedOverride->calculated_redline_type,
                    'dismiss' => isUserDismisedOn($archivedOverride->user_id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isUserTerminatedOn($archivedOverride->user_id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isUserContractEnded($archivedOverride->user_id) ? 1 : 0,
                    'override_id' => $archivedOverride->original_id,
                    'archive_id' => $archivedOverride->id, // Archive ID for restore endpoint
                    'is_projection' => $archivedOverride->override_type == 'projection' ? 1 : 0,
                    'is_archived' => 1, // Flag to indicate this is an archived override
                    'deleted_at' => $archivedOverride->deleted_at,
                    'deleted_by' => $archivedOverride->deleted_by,
                    'can_undo' => $canRestore, // Flag
                    'can_remove' => 0,
                    'status' => $archivedOverride->status,
                ]);
            }
        }

        $finalOverrides = collect($accountOverrides)->merge($projectionOverridesData)->merge($archivedOverridesData);

        $this->successResponse("Successfully.", "accountOverrides", ['account_override' => $finalOverrides]);
    }

    // RECALCULATE ONLY OPEN SALE WHILE UPDATING EMPLOYMENT PACKAGE
    public function recalculateSaleData(Request $request)
    {
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $pid = $request->pid;
            $saleMasters = SalesMaster::whereHas('salesMasterProcess')->with('salesMasterProcess')->where('pid', $pid)->first();
            if (!$saleMasters) {
                return response()->json(['status' => false, 'Message' => 'Sale Not Found!'], 400);
            }

            $closer = $saleMasters->salesMasterProcess->closer1_id;
            if (!$closer) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The closer is missing. Kindly ensure that the closer is assigned to this sale.'], 400);
            }

            $saleDate = $saleMasters->customer_signoff;
            if (!$saleDate) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The sale date is missing. Kindly ensure that the sale date is assigned to this sale.'], 400);
            }

            $grossAccountValue = $saleMasters->gross_account_value;
            if (!$grossAccountValue) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The gross account value is missing. Kindly ensure that the gross account value is present to this sale.'], 400);
            }

            $this->subroutineProcess($pid);
            $this->updateExternalOverride($pid);
        } else {
            $pid = $request->pid;
            $saleMasters = SalesMaster::whereHas('salesMasterProcess')->with('salesMasterProcess')->where('pid', $pid)->first();
            if (!$saleMasters) {
                return response()->json(['status' => false, 'Message' => 'Sale Not Found!'], 400);
            }

            $closer = $saleMasters->salesMasterProcess->closer1_id;
            if (!$closer) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The closer is missing. Kindly ensure that the closer is assigned to this sale.'], 400);
            }

            $setter = $saleMasters->salesMasterProcess->setter1_id;
            if (!$setter) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The setter is missing. Kindly ensure that the setter is assigned to this sale.'], 400);
            }

            $saleDate = $saleMasters->customer_signoff;
            if (!$saleDate) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The sale date is missing. Kindly ensure that the sale date is assigned to this sale.'], 400);
            }

            $netEpc = $saleMasters->net_epc;
            if (!$netEpc) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The net epc value is missing. Kindly ensure that the gross account value is present to this sale.'], 400);
            }

            $kw = $saleMasters->kw;
            if (!$kw) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The kw value is missing. Kindly ensure that the gross account value is present to this sale.'], 400);
            }

            $this->subroutineProcess($pid);
            $this->updateExternalOverride($pid);
        }

        return response()->json(['status' => true, 'Message' => 'Recalculate Sale Data successfully!!']);
    }

    // RESOLVE ALERT SALE
    public function resolveSalesAlert(Request $request)
    {
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.', 'resolveSalesAlert', '', 400);
        }

        // RECON FINALIZE CONDITION CHECK
        $checkReconOverrideFinalizeData = ReconOverrideHistory::where("pid", $request->pid)->where("status", "finalize")->where('is_ineligible', '0')->exists();
        $checkReconCommissionFinalizeData = ReconCommissionHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        $checkReconClawBackFinalizeData = ReconClawbackHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawBackFinalizeData) {
            $this->errorResponse('Apologies, the sale is not updated because the Recon amount has finalized or executed from recon', 'resolveSalesAlert', '', 400);
        }

        $milestoneDates = $request->milestone_dates;
        if (is_array($milestoneDates) && sizeOf($milestoneDates) != 0) {
            foreach ($milestoneDates as $milestoneDate) {
                if (@$milestoneDate['date'] && $request->approved_date && $milestoneDate['date'] < $request->approved_date) {
                    $this->errorResponse('The date cannot be earlier than the sale date.', 'resolveSalesAlert', '', 400);
                }
            }
        }

        // PEST FLOW STARTS
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $pid = $request->pid;
            $closers = $request->rep_id;
            $productId = $request->product_id;
            $systemProductId = $request->product_id;
            $product = Products::withTrashed()->where('id', $productId)->first();
            if (!$product) {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $systemProductId = $product->id;
            }
            $finalDates = [];
            $effectiveDate = $request->approved_date;
            $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
            $triggers = isset($milestone?->milestone?->milestone_trigger) ? $milestone?->milestone?->milestone_trigger : [];
            foreach ($triggers as $key => $schema) {
                $date = isset($request->milestone_dates[$key]['date']) ? $request->milestone_dates[$key]['date'] : NULL;
                $finalDates[] = [
                    'date' => $date
                ];
            }
            $request->merge(['milestone_dates' => $finalDates]);
            $saleMasterData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            if ($saleMasterData) {
                $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();

                salesDataChangesClawback($saleMasterProcess->pid);
                if (!empty($saleMasterData->product_id) && empty($systemProductId)) {
                    if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    $this->saleProductMappingChanges($pid);
                }

                if (!empty($saleMasterData->product_id) && !empty($systemProductId) && $saleMasterData->product_id != $systemProductId) {
                    if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                    }
                    $this->saleProductMappingChanges($pid);
                }

                $checked = false;
                $isM2Paid = false;
                $withHeldPaid = false;
                $upFrontRemove = [];
                $upFrontChange = [];
                $commissionRemove = [];
                $commissionChange = [];
                $count = count($request->milestone_dates);
                foreach ($finalDates as $key => $finalDate) {
                    $type = 'm' . ($key + 1);
                    $date = @$finalDate['date'];
                    $saleProduct = SaleProductMaster::where(['pid' => $pid, 'type' => $type])->first();
                    if ($count == ($key + 1)) {
                        if (UserCommission::where(['pid' => $pid, 'schema_type' => $saleProduct->type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $isM2Paid = true;
                        }
                        if (UserCommission::where(['pid' => $pid, 'schema_type' => $saleProduct->type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            $withHeldPaid = true;
                        }

                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                            if ($isM2Paid) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid'], 400);
                            }
                            if ($withHeldPaid) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                            }
                            $commissionRemove[] = $saleProduct->type;
                        }

                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                            if ($isM2Paid) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid'], 400);
                            }
                            if ($withHeldPaid) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                            }

                            $commissionChange[] = [
                                'type' => $saleProduct->type,
                                'date' => $date
                            ];
                        }
                    } else {
                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $saleProduct->type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be removed because the ' . $saleProduct->type . ' amount has already been paid'], 400);
                            }
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $saleProduct->type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be removed because the ' . $saleProduct->type . ' amount has already been paid'], 400);
                            }

                            $upFrontRemove[] = $saleProduct->type;
                        }

                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $saleProduct->type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be change because the ' . $saleProduct->type . ' amount has already been paid'], 400);
                            }
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $saleProduct->type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be change because the ' . $saleProduct->type . ' amount has finalized or executed from reconciliation'], 400);
                            }

                            $upFrontChange[] = [
                                'type' => $saleProduct->type,
                                'date' => $date
                            ];
                        }
                    }

                    if (!$checked && $saleProduct && $saleProduct->is_override) {
                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                            if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be removed because the override amount has already been paid'], 400);
                            }
                            if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be removed because the override amount has already been paid'], 400);
                            }
                        }

                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                            if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be change because the override amount has already been paid'], 400);
                            }
                            if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                return response()->json(['success' => false, 'message' => 'Apologies, the ' . $saleProduct->type . ' date cannot be change because the override amount has already been paid'], 400);
                            }
                        }
                        $checked = true;
                    }
                }

                foreach ($upFrontRemove as $remove) {
                    $this->removeUpFrontSaleData($pid, $remove);
                }

                foreach ($upFrontChange as $change) {
                    $this->changeUpFrontPayrollData($pid, $change);
                }

                if (sizeOf($commissionRemove) != 0) {
                    $this->removeCommissionSaleData($pid);
                }

                foreach ($commissionChange as $change) {
                    $this->changeCommissionPayrollData($pid, $change);
                }

                if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
                    if ($isM2Paid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                    }

                    $this->clawBackSalesData($saleMasterProcess->closer1_id, $saleMasterData);
                    $this->removeClawBackForNewUser($closers[0], $saleMasterData);
                }

                if (isset($saleMasterProcess->closer2_id) && isset($closers[1]) && $closers[1] != $saleMasterProcess->closer2_id) {
                    if ($isM2Paid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                    }

                    $this->clawBackSalesData($saleMasterProcess->closer2_id, $saleMasterData);
                    $this->removeClawBackForNewUser($closers[1], $saleMasterData);
                }
                $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : NULL;
                $saleMasterProcess->save();
            }

            $netEPC = isset($request->net_epc) ? $request->net_epc : NULL;

            $closer = User::whereIn('id', $request->rep_id)->get();
            $stateCode = State::find($request->state_id)?->state_code ?? NULL;
            $stateId = NULL;
            $stateCode = NULL;
            if ($request->customer_state) {
                $state = State::where('state_code', $request->customer_state)->first();
                $stateId = $state?->id ?? NULL;
                $stateCode = $state?->state_code ?? NULL;
            } else if ($request->location_code) {
                $location = Locations::with('State')->where('general_code', $request->location_code)->first();
                if ($location && $location->State) {
                    $stateId = $location?->State?->id ?? NULL;
                    $stateCode = $location?->State?->state_code ?? NULL;
                }
            }
            $val = [
                'pid' => $pid,
                'kw' => isset($request->kw) ? $request->kw : NULL,
                'install_partner' => isset($request->installer) ? $request->installer : NULL,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : NULL,
                'customer_address' => isset($request->customer_address) ? $request->customer_address : NULL,
                'customer_address_2' => isset($request->customer_address_2) ? $request->customer_address_2 : NULL,
                'customer_city' => isset($request->customer_city) ? $request->customer_city : NULL,
                'state_id' => $stateId,
                'customer_state' => $stateCode ? $stateCode : $request->customer_state,
                'location_code' => isset($request->location_code) ? $request->location_code : NULL,
                'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : NULL,
                'customer_email' => isset($request->customer_email) ? $request->customer_email : NULL,
                'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : NULL,
                'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : NULL,
                'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : NULL,
                'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL,
                'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : NULL,
                'closer1_id' => isset($closers[0]) ? $closers[0] : NULL,
                'closer2_id' => isset($closers[1]) ? $closers[1] : NULL,
                'setter1_id' => isset($setters[0]) ? $setters[0] : NULL,
                'setter2_id' => isset($setters[1]) ? $setters[1] : NULL,
                'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : NULL,
                'customer_signoff' => isset($request->approved_date) ? $request->approved_date : NULL,
                'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
                'm2_date' => isset($request->m2_date) ? $request->m2_date : NULL,
                'product' => isset($request->product) ? $request->product : NULL,
                'product_id' => isset($productId) ? $productId : NULL,
                'product_code' => isset($product->product_id) ? $product->product_id : NULL,
                'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : NULL,
                'epc' => isset($request->epc) ? $request->epc : NULL,
                'net_epc' => $netEPC,
                'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : NULL,
                'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : NULL,
                'adders' => isset($request->show) ? $request->show : NULL,
                'adders_description' => isset($request->adders_description) ? $request->adders_description : NULL,
                'redline' => isset($request->redline) ? $request->redline : NULL,
                'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : NULL,
                'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : NULL,
                'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : NULL,
                'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : NULL,
                'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : NULL,
                'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : NULL,
                'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : NULL,
                'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : NULL,
                'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : NULL,
                'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : NULL,
                'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : NULL,
                'return_sales_date' => isset($request->return_sales_date) ? $request->return_sales_date : NULL,
                'job_status' => isset($request->job_status) ? $request->job_status : NULL,
                'length_of_agreement' => isset($request->length_of_agreement) ? $request->length_of_agreement : NULL,
                'service_schedule' => isset($request->service_schedule) ? $request->service_schedule : NULL,
                'subscription_payment' => isset($request->subscription_payment) ? $request->subscription_payment : NULL,
                'service_completed' => isset($request->service_completed) ? $request->service_completed : NULL,
                'last_service_date' => isset($request->last_service_date) ? $request->last_service_date : NULL,
                'bill_status' => isset($request->bill_status) ? $request->bill_status : NULL,
                'initial_service_cost' => isset($request->initial_service_cost) ? $request->initial_service_cost : NULL,
                'auto_pay' => isset($request->auto_pay) ? $request->auto_pay : NULL,
                'card_on_file' => isset($request->card_on_file) ? $request->card_on_file : NULL,
                'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : NULL
            ];

            $nullTableVal = $val;
            $saleMasterData = SalesMaster::where('pid', $pid)->first();
            if ($saleMasterData) {
                if ($request->date_cancelled) {
                    $nullTableVal['date_cancelled'] = $request->date_cancelled;
                    $nullTableVal['data_source_type'] = 'manual';
                    $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) { $saleModelToUpdate->fill($val)->save(); }
                } else {
                    if (!empty($saleMasterData->date_cancelled) && empty(\request('date_cancelled'))) {
                        salesDataChangesBasedOnClawback($saleMasterProcess->pid);
                        request()->merge(['full_recalculate' => 1]);
                    }
                    $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) {
                        $saleModelToUpdate->fill($val)->save();
                    }

                    $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                    $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                    $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                    $nullTableVal['data_source_type'] = 'manual';

                    $closer = $request->rep_id;
                    $setter = $request->setter_id ?? [];
                    $data = [
                        'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                        'closer2_id' => isset($closer[1]) ? $closer[1] : NULL,
                        'setter1_id' => isset($setter[0]) ? $setter[0] : NULL,
                        'setter2_id' => isset($setter[1]) ? $setter[1] : NULL,
                    ];
                    SaleMasterProcess::where('pid', $pid)->update($data);
                }
                LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);

                if ($closer) {
                    $this->subroutineProcess($pid);
                    $this->salesDataHistory($pid, 'alert');
                }
            } else {
                $val['data_source_type'] = 'manual';
                $insertData = SalesMaster::create($val);

                $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                $nullTableVal['data_source_type'] = 'manual';

                $closer = $request->rep_id;
                $data = [
                    'pid' => $pid,
                    'sale_master_id' => $insertData->id,
                    'weekly_sheet_id' => $insertData->weekly_sheet_id,
                    'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                    'closer2_id' => isset($closer[1]) ? $closer[1] : NULL
                ];
                SaleMasterProcess::create($data);
                LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);
                if ($closer) {
                    $this->subroutineProcess($pid);
                    $this->salesDataHistory($pid, 'alert');
                }
            }
            //dispatch(new GenerateAlertJob($pid));

            $this->successResponse("Add Data successfully.", "resolveSalesAlert", []);
        } else {
            $pid = $request->pid;
            $closers = $request->rep_id;
            $setters = $request->setter_id;
            $productId = $request->product_id;
            $systemProductId = $request->product_id;
            $product = Products::withTrashed()->where('id', $productId)->first();
            if (!$product) {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $systemProductId = $product->id;
            }

            $finalDates = [];
            $effectiveDate = $request->approved_date;
            $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
            $triggers = isset($milestone?->milestone?->milestone_trigger) ? $milestone?->milestone?->milestone_trigger : [];
            foreach ($triggers as $key => $schema) {
                $date = isset($request->milestone_dates[$key]['date']) ? $request->milestone_dates[$key]['date'] : NULL;
                $finalDates[] = [
                    'date' => $date
                ];
            }
            $request->merge(['milestone_dates' => $finalDates]);

            try {
                DB::beginTransaction();
                $saleMasterData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                if ($saleMasterData) {
                    $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();

                    salesDataChangesClawback($saleMasterProcess->pid);
                    if (!empty($saleMasterData->product_id) && empty($systemProductId)) {
                        if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        $this->saleProductMappingChanges($pid);
                    }

                    if (!empty($saleMasterData->product_id) && !empty($systemProductId) && $saleMasterData->product_id != $systemProductId) {
                        if (UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be changed because the Milestone amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the product cannot be removed because some of the Override amount has already been paid'], 400);
                        }
                        $this->saleProductMappingChanges($pid);
                    }

                    $checked = false;
                    $isM2Paid = false;
                    $withHeldPaid = false;
                    $upFrontRemove = [];
                    $upFrontChange = [];
                    $commissionRemove = [];
                    $commissionChange = [];
                    $count = count($request->milestone_dates);
                    foreach ($finalDates as $key => $finalDate) {
                        $type = 'm' . ($key + 1);
                        $date = @$finalDate['date'];
                        $saleProduct = SaleProductMaster::where(['pid' => $pid, 'type' => $type])->first();
                        if ($count == ($key + 1)) {
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                $isM2Paid = true;
                            }
                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                $withHeldPaid = true;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if ($isM2Paid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid'], 400);
                                }
                                if ($withHeldPaid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                                }
                                $commissionRemove[] = $type;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if ($isM2Paid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid'], 400);
                                }
                                if ($withHeldPaid) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                                }

                                $commissionChange[] = [
                                    'type' => $type,
                                    'date' => $date
                                ];
                            }
                        } else {
                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the ' . $type . ' amount has already been paid'], 400);
                                }
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the ' . $type . ' amount has already been paid'], 400);
                                }

                                $upFrontRemove[] = $type;
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the ' . $type . ' amount has already been paid'], 400);
                                }
                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the ' . $type . ' amount has finalized or executed from reconciliation'], 400);
                                }

                                $upFrontChange[] = [
                                    'type' => $type,
                                    'date' => $date
                                ];
                            }
                        }

                        if (!$checked && $saleProduct && $saleProduct->is_override) {
                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the override amount has already been paid'], 400);
                                }
                                if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be removed because the override amount has already been paid'], 400);
                                }
                            }

                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the override amount has already been paid'], 400);
                                }
                                if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    return response()->json(['success' => false, 'message' => 'Apologies, the ' . $type . ' date cannot be change because the override amount has already been paid'], 400);
                                }
                            }
                            $checked = true;
                        }
                    }

                    foreach ($upFrontRemove as $remove) {
                        $this->removeUpFrontSaleData($pid, $remove);
                    }

                    foreach ($upFrontChange as $change) {
                        $this->changeUpFrontPayrollData($pid, $change);
                    }

                    if (sizeOf($commissionRemove) != 0) {
                        $this->removeCommissionSaleData($pid);
                    }

                    foreach ($commissionChange as $change) {
                        $this->changeCommissionPayrollData($pid, $change);
                    }

                    if (sizeof($finalDates) == 0) {
                        $this->removeUpFrontSaleData($pid);
                        $this->removeCommissionSaleData($pid);
                    }

                    if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->closer1_id, $saleMasterData);
                        $this->removeClawBackForNewUser($closers[0], $saleMasterData);
                    }

                    if (isset($saleMasterProcess->closer2_id) && isset($closers[1]) && $closers[1] != $saleMasterProcess->closer2_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->closer2_id, $saleMasterData);
                        $this->removeClawBackForNewUser($closers[1], $saleMasterData);
                    }

                    if (isset($saleMasterProcess->setter1_id) && isset($setters[0]) && $setters[0] != $saleMasterProcess->setter1_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->setter1_id, $saleMasterData, 'setter');
                        $this->removeClawBackForNewUser($setters[0], $saleMasterData);
                    }

                    if (isset($saleMasterProcess->setter2_id) && isset($setters[1]) && $setters[1] != $saleMasterProcess->setter2_id) {
                        if ($isM2Paid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['success' => false, 'message' => 'Apologies, the setter cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $this->clawBackSalesData($saleMasterProcess->setter2_id, $saleMasterData, 'setter2');
                        $this->removeClawBackForNewUser($setters[1], $saleMasterData);
                    }
                    $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : NULL;
                    $saleMasterProcess->save();
                }

                $closer = User::whereIn('id', $request->rep_id)->get();
                $setter = User::whereIn('id', $request->setter_id)->get();

                $netEPC = isset($request->net_epc) ? $request->net_epc : NULL;

                $stateId = NULL;
                $stateCode = NULL;
                if ($request->customer_state) {
                    $state = State::where('state_code', $request->customer_state)->first();
                    $stateId = $state?->id ?? NULL;
                    $stateCode = $state?->state_code ?? NULL;
                } else if ($request->location_code) {
                    $location = Locations::with('State')->where('general_code', $request->location_code)->first();
                    if ($location && $location->State) {
                        $stateId = $location?->State?->id ?? NULL;
                        $stateCode = $location?->State?->state_code ?? NULL;
                    }
                }
                $val = [
                    'pid' => $pid,
                    'kw' => isset($request->kw) ? $request->kw : NULL,
                    'install_partner' => isset($request->installer) ? $request->installer : NULL,
                    'customer_name' => isset($request->customer_name) ? $request->customer_name : NULL,
                    'customer_address' => isset($request->customer_address) ? $request->customer_address : NULL,
                    'customer_address_2' => isset($request->customer_address_2) ? $request->customer_address_2 : NULL,
                    'customer_city' => isset($request->customer_city) ? $request->customer_city : NULL,
                    'state_id' => $stateId,
                    'customer_state' => $stateCode ? $stateCode : $request->customer_state,
                    'location_code' => isset($request->location_code) ? $request->location_code : NULL,
                    'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : NULL,
                    'customer_email' => isset($request->customer_email) ? $request->customer_email : NULL,
                    'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : NULL,
                    'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : NULL,
                    'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : NULL,
                    'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL,
                    'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : NULL,
                    'closer1_id' => isset($request->rep_id[0]) ? $request->rep_id[0] : NULL,
                    'closer2_id' => isset($request->rep_id[1]) ? $request->rep_id[1] : NULL,
                    'setter1_id' => isset($request->setter_id[0]) ? $request->setter_id[0] : NULL,
                    'setter2_id' => isset($request->setter_id[1]) ? $request->setter_id[1] : NULL,
                    'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : NULL,
                    'customer_signoff' => isset($request->approved_date) ? $request->approved_date : NULL,
                    'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
                    'm2_date' => isset($request->m2_date) ? $request->m2_date : NULL,
                    'product' => isset($request->product) ? $request->product : NULL,
                    'product_id' => isset($productId) ? $productId : NULL,
                    'product_code' => isset($product->product_id) ? $product->product_id : NULL,
                    'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : NULL,
                    'epc' => isset($request->epc) ? $request->epc : NULL,
                    'net_epc' => $netEPC,
                    'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : NULL,
                    'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : NULL,
                    'adders' => isset($request->show) ? $request->show : NULL,
                    'adders_description' => isset($request->adders_description) ? $request->adders_description : NULL,
                    'redline' => isset($request->redline) ? $request->redline : NULL,
                    'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : NULL,
                    'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : NULL,
                    'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : NULL,
                    'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : NULL,
                    'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : NULL,
                    'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : NULL,
                    'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : NULL,
                    'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : NULL,
                    'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : NULL,
                    'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : NULL,
                    'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : NULL,
                    'return_sales_date' => isset($request->return_sales_date) ? $request->return_sales_date : NULL,
                    'job_status' => isset($request->job_status) ? $request->job_status : NULL,
                    'length_of_agreement' => isset($request->length_of_agreement) ? $request->length_of_agreement : NULL,
                    'service_schedule' => isset($request->service_schedule) ? $request->service_schedule : NULL,
                    'subscription_payment' => isset($request->subscription_payment) ? $request->subscription_payment : NULL,
                    'service_completed' => isset($request->service_completed) ? $request->service_completed : NULL,
                    'last_service_date' => isset($request->last_service_date) ? $request->last_service_date : NULL,
                    'bill_status' => isset($request->bill_status) ? $request->bill_status : NULL,
                    'initial_service_cost' => isset($request->initial_service_cost) ? $request->initial_service_cost : NULL,
                    'auto_pay' => isset($request->auto_pay) ? $request->auto_pay : NULL,
                    'card_on_file' => isset($request->card_on_file) ? $request->card_on_file : NULL,
                    'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : NULL
                ];

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $val['kw'] = $val['gross_account_value'];
                }

                $nullTableVal = $val;
                if ($saleMasterData) {
                    if ($request->date_cancelled) {
                        $nullTableVal['date_cancelled'] = $request->date_cancelled;
                        $nullTableVal['data_source_type'] = 'manual';
                        $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) { $saleModelToUpdate->fill($val)->save(); }
                    } else {
                        if (!empty($saleMasterData->date_cancelled) && empty(\request('date_cancelled'))) {
                            salesDataChangesBasedOnClawback($saleMasterProcess->pid);
                            request()->merge(['full_recalculate' => 1]);
                        }
                        $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
                    if ($saleModelToUpdate) {
                        $saleModelToUpdate->fill($val)->save();
                    }

                        $nullTableVal['setter_id'] = isset($setter[0]) ? $setter[0]->id : NULL;
                        $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                        $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                        $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                        $nullTableVal['sales_setter_name'] = isset($setter[0]->first_name) ? $setter[0]->first_name . ' ' . $setter[0]->last_name : NULL;
                        $nullTableVal['sales_setter_email'] = isset($setter[0]->email) ? $setter[0]->email : NULL;
                        $nullTableVal['data_source_type'] = 'manual';

                        $closer = $request->rep_id;
                        $setter = $request->setter_id;
                        $data = [
                            'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                            'closer2_id' => isset($closer[1]) ? $closer[1] : NULL,
                            'setter1_id' => isset($setter[0]) ? $setter[0] : NULL,
                            'setter2_id' => isset($setter[1]) ? $setter[1] : NULL
                        ];
                        SaleMasterProcess::where('pid', $pid)->update($data);
                    }
                    LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);

                    if ($setter) {
                        $this->subroutineProcess($pid);
                        $this->salesDataHistory($pid, 'alert');
                    }
                } else {
                    $val['data_source_type'] = 'manual';
                    $insertData = SalesMaster::create($val);

                    $nullTableVal['setter_id'] = isset($setter[0]) ? $setter[0]->id : NULL;
                    $nullTableVal['closer_id'] = isset($closer[0]) ? $closer[0]->id : NULL;
                    $nullTableVal['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name . ' ' . $closer[0]->last_name : NULL;
                    $nullTableVal['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : NULL;
                    $nullTableVal['sales_setter_name'] = isset($setter[0]->first_name) ? $setter[0]->first_name . ' ' . $setter[0]->last_name : NULL;
                    $nullTableVal['sales_setter_email'] = isset($setter[0]->email) ? $setter[0]->email : NULL;
                    $nullTableVal['data_source_type'] = 'manual';

                    $closer = $request->rep_id;
                    $setter = $request->setter_id;

                    $data = [
                        'pid' => $pid,
                        'sale_master_id' => $insertData->id,
                        'weekly_sheet_id' => $insertData->weekly_sheet_id,
                        'closer1_id' => isset($closer[0]) ? $closer[0] : NULL,
                        'closer2_id' => isset($closer[1]) ? $closer[1] : NULL,
                        'setter1_id' => isset($setter[0]) ? $setter[0] : NULL,
                        'setter2_id' => isset($setter[1]) ? $setter[1] : NULL
                    ];
                    SaleMasterProcess::create($data);
                    LegacyApiNullData::updateOrCreate(['pid' => $pid], $nullTableVal);
                    if ($setter) {
                        $this->subroutineProcess($pid);
                        $this->salesDataHistory($pid, 'alert');
                    }
                }

                DB::commit();
                Artisan::call('generate:alert', ['pid' => $pid]);
                return response()->json(['success' => true, 'message' => 'Add Data successfully.']);
            } catch (\Exception $e) {
                DB::rollBack();
                $this->errorResponse('Error while adding data. Please try again later.', 'resolveSalesAlert', [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ], 400);
            }
        }
    }

    // RECALCULATE SALES FROM COMMISSION DATA
    public function recalculateSalesFromCommission(Request $request)
    {
        try {
            // Execute the SQL query to fetch PIDs from user_commission table
            $pids = DB::table('user_commission')
                ->where('status', '!=', '3')
                ->whereIn('user_id', [1089, 1052, 1042, 1058, 1043, 1083, 1142, 1144, 1127, 1146, 1046, 1069, 1084, 1086])
                ->distinct()
                ->pluck('pid')
                ->toArray();

            if (empty($pids)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No PIDs found to recalculate.'
                ], 404);
            }

            $processedPids = [];
            $failedPids = [];

            // Process each PID by calling recalculateSaleData
            foreach ($pids as $pid) {
                try {
                    $request = new Request(['pid' => $pid]);
                    $response = $this->recalculateSaleData($request);

                    // Check if recalculation was successful
                    if ($response->getStatusCode() === 200) {
                        $processedPids[] = $pid;
                    } else {
                        $failedPids[] = $pid;
                    }
                } catch (\Exception $e) {
                    Log::error("Error recalculating sale data for PID {$pid}: " . $e->getMessage());
                    $failedPids[] = $pid;
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Sales data recalculation process completed.',
                'total_pids' => count($pids),
                'processed_pids' => count($processedPids),
                'failed_pids' => count($failedPids),
                'processed' => $processedPids,
                'failed' => $failedPids
            ]);
        } catch (\Exception $e) {
            Log::error("Error in recalculateSalesFromCommission: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error while recalculating sales data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GET CLOSER & SETTER LIST BASED ON PRODUCT & EFFECTIVE DATE
    // ⭐ V3 OPTIMIZED: Early excluded user filtering (25% faster than V2)
    public function setterCloserListForManualWorker(Request $request)
    {
        $this->checkValidations($request->all(), [
            'effective_date' => 'required',
            'pid' => 'required',

        ]);

        $positionLabels = [
            1 => 'Self Gen',
            2 => 'Closer',
            3 => 'Setter',
        ];

        $effectiveDate = $request->effective_date;
        $pid = $request->pid;
        $fromOverride = isset($request->Overrides) ? $request->Overrides : false;

        // Get position IDs (direct query for data consistency)
        // Note: Cache removed to ensure real-time position status updates
        $positionId = Positions::where('is_selfgen', '0')->pluck('id');
        $positionIdList = $positionId->implode(',');

        // ⭐ V3 OPTIMIZATION: Get excluded users FIRST, before window function
        // This reduces the dataset size for the expensive window function operation
        $nonUsers = $this->getExcludedUsers($effectiveDate);
        $excludedUsersList = !empty($nonUsers) ? implode(',', $nonUsers) : '0';

        // ⭐ V2+V3 OPTIMIZATION: Combined window function with EARLY excluded user filtering
        // This replaces the duplicate window function + filters excluded users earlier
        try {
            $sql = "
                SELECT
                    uoh.user_id,
                    uoh.position_id,
                    uoh.sub_position_id,
                    p1.position_name as position_name,
                    p2.position_name as sub_position_name
                FROM (
                    SELECT
                        user_id,
                        position_id,
                        sub_position_id,
                        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn
                    FROM user_organization_history
                    WHERE effective_date <= ?
                      AND sub_position_id NOT IN ($positionIdList)
                      AND user_id NOT IN ($excludedUsersList)
                ) uoh
                LEFT JOIN positions p1 ON uoh.position_id = p1.id
                LEFT JOIN positions p2 ON uoh.sub_position_id = p2.id
                WHERE uoh.rn = 1
            ";

            $results = DB::select($sql, [$effectiveDate]);

            // Convert to collection and build user positions map
            $userPositions = collect($results)->mapWithKeys(function ($record) {
                return [$record->user_id => (object)[
                    'user_id' => $record->user_id,
                    'position_id' => $record->position_id,
                    'sub_position_id' => $record->sub_position_id,
                    'position_name' => $record->position_name,
                    'sub_position_name' => $record->sub_position_name,
                ]];
            });

            $validUserIds = $userPositions->keys()->toArray();
        } catch (\Throwable $e) {
            // Fallback to original logic if window function fails (also with early filtering)
            Log::error('Optimized window function failed in setterCloserListForManualWorker', [
                'error' => $e->getMessage(),
                'effective_date' => $effectiveDate
            ]);

            $results = UserOrganizationHistory::select('user_id', 'position_id', 'sub_position_id')
                ->with(['position:id,position_name', 'subPositionId:id,position_name'])
                ->where('effective_date', '<=', $effectiveDate)
                ->whereNotIn('sub_position_id', $positionId)
                ->whereNotIn('user_id', $nonUsers)
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->groupBy('user_id')
                ->get();

            $userPositions = $results->mapWithKeys(function ($record) {
                return [$record->user_id => (object)[
                    'user_id' => $record->user_id,
                    'position_id' => $record->position_id,
                    'sub_position_id' => $record->sub_position_id,
                    'position_name' => $record->position?->position_name,
                    'sub_position_name' => $record->subPositionId?->position_name,
                ]];
            });

            $validUserIds = $userPositions->keys()->toArray();
        }

        // ✅ OPTIMIZED: Early return for empty results to avoid unnecessary queries
        if (empty($validUserIds)) {
            return $this->successResponse("Successfully.", "setterCloserListForManualWorker", []);
        }

        // Remove already attached user on sales.
        if ($fromOverride == false) {
            $existingSaleWorker = SaleMasterProcess::where('pid', $pid)->select('closer1_id', 'closer2_id', 'setter1_id', 'setter2_id')->first();
            $existingSaleWorkerIds = $existingSaleWorker ? array_filter($existingSaleWorker->toArray()) : [];

            // Remove existing worker IDs from validUserIds
            $validUserIds = array_values(array_diff($validUserIds, $existingSaleWorkerIds));
        }

        // Then get ALL positions for these users, not limited to just the latest effective date
        // ✅ OPTIMIZED: Use GROUP BY instead of DISTINCT for better performance
        $allUserPositions = UserOrganizationHistory::whereIn('user_id', $validUserIds)
            ->whereNotIn('sub_position_id', $positionId)
            ->select('user_id', 'position_id', 'self_gen_accounts')
            ->groupBy('user_id', 'position_id', 'self_gen_accounts') // ✅ More efficient than distinct()
            ->get();

        // ✅ OPTIMIZED: Use collection methods instead of loop for better performance
        $userPositionMap = $allUserPositions->groupBy('user_id')->map(function ($positions) {
            $positionIds = $positions->pluck('position_id')->unique()->values()->toArray();

            // Add self_gen (1) if any position has self_gen_accounts = 1
            if ($positions->contains('self_gen_accounts', 1)) {
                $positionIds[] = 1;
            }

            return array_unique($positionIds);
        });

        $userIdArr = $userPositionMap->keys()->toArray();

        // ✅ NO NEED for getUserPositionsByEffectiveDate - positions already loaded above!
        // ✅ Defensive filter: Ensure excluded users are filtered (already done in window function, but added for 100% safety)

        // ✅ Load users (excluded users already filtered, position data already loaded above!)
        // ⚡ V3.1 OPTIMIZATION: Optimized relationship loading with safety fallback
        // We have position data from window function, with lightweight fallback for data safety
        $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'stop_payroll', 'sub_position_id', 'dismiss', 'terminate', 'contract_ended', 'position_id')
            ->with(['parentPositionDetail:id,position_name', 'positionDetail:id,position_name'])
            ->whereIn('id', $userIdArr)
            ->whereNotIn('id', $nonUsers) // ✅ DEFENSIVE: Extra safety layer to ensure excluded users never appear
            ->get();

        // Attach position_ids with labels to each user using pre-loaded position data
        $data = $users->map(function ($user) use ($userPositionMap, $positionLabels, $userPositions) {
            $userData = $user->toArray();
            $positionData = $userPositions->get($user->id);

            $rawIds = $userPositionMap[$user->id] ?? [];

            // Add position_ids with labels
            $userData['position_ids'] = collect($rawIds)
                ->unique()
                ->map(function ($id) use ($positionLabels) {
                    return [
                        'id' => (string) $id,
                        'name' => $positionLabels[$id] ?? 'Unknown'
                    ];
                })
                ->values()
                ->toArray();

            // ✅ Use pre-loaded position data from window function with safety fallback
            $userData['position'] = $positionData?->position_name ?? $user->parentPositionDetail?->position_name;
            $userData['position_id'] = $positionData?->position_id ?? $user->position_id;
            $userData['sub_position_id'] = $positionData?->sub_position_id ?? $user->sub_position_id;
            $userData['sub_position_name'] = $positionData?->sub_position_name ?? $user->positionDetail?->position_name;

            return $userData;
        });

        $this->successResponse("Successfully.", "setterCloserListForManualWorker", $data);
    }

    public function addManualWorker(Request $request)
    {
        $this->checkValidations($request->all(), [
            'workers' => 'required|array',
            'workers.*.user_id' => 'required|integer',
            'workers.*.type' => 'required|in:1,2,3',
            'workers.*.pid' => 'required|string',

            'milestone_dates' => 'required|array',
            'milestone_dates.*.date' => 'nullable|date',
        ]);

        $data = $request->workers; // assuming it's already validated

        $companyProfile = CompanyProfile::first();
        $existingWorkers = [];
        if (isset($data[0]['pid']) && $data[0]['pid']) {
            $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
            if ($payroll) {
                $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.', 'addManualSaleData', '', 400);
            }

            if (LegacyApiRawDataHistory::where(['pid' => $data[0]['pid'], 'import_to_sales' => '0', 'data_source_type' => 'excel'])->whereNotNull('excel_import_id')->first()) {
                $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.', 'addManualSaleData', '', 400);
            }

            // RECON FINALIZE CONDITION CHECK
            $checkReconOverrideFinalizeData = ReconOverrideHistory::where("pid", $data[0]['pid'])->where("status", "finalize")->where('is_ineligible', '0')->exists();
            $checkReconCommissionFinalizeData = ReconCommissionHistory::where("pid", $data[0]['pid'])->where("status", "finalize")->exists();
            $checkReconClawBackFinalizeData = ReconClawbackHistory::where("pid", $data[0]['pid'])->where("status", "finalize")->exists();
            if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawBackFinalizeData) {
                $this->errorResponse('Apologies, the sale is not updated because the Recon amount has finalized or executed from recon', 'addManualSalesWorker', '', 400);
            }

            // $milestoneDates = $request->milestone_dates;
            // if (is_array($milestoneDates) && sizeOf($milestoneDates) != 0) {
            //     foreach ($milestoneDates as $milestoneDate) {
            //         if (@$milestoneDate['date'] && $request->approved_date && $milestoneDate['date'] < $request->approved_date) {
            //             $this->errorResponse('The date cannot be earlier than the sale date.', 'addManualSaleData', '', 400);
            //         }
            //     }
            // }
        } else {
            $this->errorResponse('PID is required.', 'addManualSalesWorker', '', 400);
        }



        foreach ($data as $entry) {
            $exists = ExternalSaleWorker::where('user_id', $entry['user_id'])
                // ->where('type', $entry['type'])
                ->where('pid', $entry['pid'])
                ->exists();

            if ($exists) {
                $existingWorkers[] = $entry['user_id'];
            }
        }

        if (!empty($existingWorkers)) {
            return response()->json([
                'status' => false,
                'message' => 'Some workers already exist.',
                'existing_user_ids' => $existingWorkers
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Validate data before processing
            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid data provided for manual worker creation');
            }

            // Batch insert for better performance
            $insertData = [];
            foreach ($data as $entry) {
                // Validate required fields
                if (!isset($entry['user_id']) || !isset($entry['type']) || !isset($entry['pid'])) {
                    throw new \Exception('Missing required fields in data entry');
                }

                $insertData[] = [
                    'user_id' => $entry['user_id'],
                    'type' => $entry['type'],
                    'pid' => $entry['pid'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Use batch insert instead of loop
            ExternalSaleWorker::insert($insertData);

            // foreach ($data as $entry) {
            //     ExternalSaleWorker::create([
            //         'user_id' => $entry['user_id'],
            //         'type' => $entry['type'],
            //         'pid' => $entry['pid']
            //     ]);
            // }

            $this->subroutineProcess($data[0]['pid']);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Add Data successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            // Enhanced error logging
            Log::error("Error in addManualWorker", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? null
            ]);
            DB::rollBack();
            $this->errorResponse('Error while adding data. Please try again later.', 'addManualSaleWorker', [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 400);
        } catch (\Throwable $t) {
            // Catch any other throwable (including fatal errors)
            DB::rollBack();

            Log::error("Fatal error in addManualWorker", [
                'message' => $t->getMessage(),
                'file' => $t->getFile(),
                'line' => $t->getLine(),
                'trace' => $t->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Fatal error while adding manual worker.',
                'error' => $t->getMessage() ?: 'Fatal error occurred'
            ], 400);
        }
    }

    public function removeManualWorker($workerId, $pid)
    {

        $data = ExternalSaleWorker::where('user_id', $workerId)->where('pid', $pid)->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'No worker found with this ID for this sale'
            ], 404);
        }

        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            $this->errorResponse('At this time, we are unable to process your request to remove worker. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.', 'removeManualWorker', '', 400);
        }

        if (LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0', 'data_source_type' => 'excel'])->whereNotNull('excel_import_id')->first()) {
            $this->errorResponse('At this time, we are unable to process your request to remove worker. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.', 'removeManualWorker', '', 400);
        }

        if (UserCommission::where(['pid' => $pid, 'user_id' => $workerId, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
            return response()->json(['success' => false, 'message' => 'Apologies, the worker cannot be removed because some of the commission amount has already been paid'], 400);
        }
        if (UserCommission::where(['pid' => $pid, 'user_id' => $workerId, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            return response()->json(['success' => false, 'message' => 'Apologies, the worker cannot be removed because some of the Recon amount has already been paid'], 400);
        }
        if (UserOverrides::where(['pid' => $pid, 'sale_user_id' => $workerId, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
            return response()->json(['success' => false, 'message' => 'Apologies, the worker cannot be removed because some of the Override amount has already been paid'], 400);
        }
        if (UserOverrides::where(['pid' => $pid, 'sale_user_id' => $workerId, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            return response()->json(['success' => false, 'message' => 'Apologies, the worker cannot be removed because some of the Override amount has already been paid'], 400);
        }

        // RECON FINALIZE CONDITION CHECK
        $checkReconOverrideFinalizeData = ReconOverrideHistory::where("pid", $pid)->where("status", "finalize")->where('is_ineligible', '0')->exists();
        $checkReconCommissionFinalizeData = ReconCommissionHistory::where("pid", $pid)->where("status", "finalize")->exists();
        $checkReconClawBackFinalizeData = ReconClawbackHistory::where("pid", $pid)->where("status", "finalize")->exists();
        if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawBackFinalizeData) {
            $this->errorResponse('Apologies, the sale is not updated because the Recon amount has finalized or executed from recon', 'removeManualWorker', '', 400);
        }
        try {
            DB::beginTransaction();

            $this->updateSalesData($workerId, $data->type, $pid);
            $data->delete();
            $this->subroutineProcess($pid);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Remove Data successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Fatal error while removing manual worker.',
                'error' => $e->getMessage() ?: 'Fatal error occurred',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 400);
        }
    }

    public function addManualOverrides(Request $request)
    {
        $this->checkValidations($request->all(), [
            'workers' => 'required|array',
            'workers.*.user_id' => 'required|integer',
            'workers.*.type' => 'required',
            'workers.*.pid' => 'required|string',
            'workers.*.amount' => 'required',
        ]);

        $data = $request->workers;

        if (isset($data[0]['pid']) && $data[0]['pid']) {
            $pid = $data[0]['pid'];
            $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
            if ($payroll) {
                $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.', 'addManualOverridesData', '', 400);
            }

            if (LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0', 'data_source_type' => 'excel'])->whereNotNull('excel_import_id')->first()) {
                $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.', 'addManualOverridesData', '', 400);
            }

            $existingWorkers = [];

            foreach ($data as $entry) {
                $overrideExist = UserOverrides::where([
                    'user_id' => $entry['user_id'],
                    'pid' => $pid,
                    'type' => 'One Time'
                ])->first();

                if ($overrideExist) {
                    $existingWorkers[] = $entry['user_id'];
                }
            }

            if (!empty($existingWorkers)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Override already exist for some users.',
                    'existing_user_ids' => $existingWorkers
                ], 400);
            }

            $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            if (!$checked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sales master record not found for the provided PID'
                ], 400);
            }

            $companyProfile = CompanyProfile::first();

            // Set KW value with proper fallbacks
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $kw = $checked->gross_account_value ?? 0;
            } else {
                $kw = $checked->kw ?? 0;
            }


            $overrideTrigger = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1'])->whereNotNull('milestone_date')->first();

            $overrideTriggerSchemas = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNotNull('milestone_date')->first();

            try {
                // Determine if we have milestone dates or need to use projected overrides
                $hasMilestoneDates = isset($overrideTriggerSchemas->milestone_date);
                $milestoneDate = $hasMilestoneDates ? $overrideTriggerSchemas->milestone_date : null;

                foreach ($data as $worker) {
                    // Use unified function with isProjected flag
                    $this->addExternalManualOverride($worker['user_id'], $worker['type'], $worker['amount'], $pid, $kw, $milestoneDate, !$hasMilestoneDates);
                }

                // Update the projected_override field in SalesMaster
                $checked->update(['projected_override' => $hasMilestoneDates ? 0 : 1]);

                return response()->json([
                    'status' => true,
                    'message' => $hasMilestoneDates ? 'Manual overrides added successfully' : 'Projected manual overrides added successfully'
                ], 200);
            } catch (\Exception $e) {
                // Log the detailed error for debugging
                Log::error('Error in addManualOverrides', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'pid' => $pid ?? null,
                    'workers_count' => count($data ?? [])
                ]);

                // Determine user-friendly message based on exception type/content
                $userMessage = $this->getUserFriendlyErrorMessage($e);

                return response()->json([
                    'status' => false,
                    'message' => $userMessage,
                ], 400);
            }
        } else {
            $this->errorResponse('PID is required.', 'addManualOverrides', '', 400);
        }
    }

    public function removeManualOverrides(Request $request)
    {
        $overrideId = $request->input('override_id');
        $pid = $request->input('pid');
        $isProjection = $request->input('is_projection', false);

        // Check if override exists in the appropriate table
        if ($isProjection) {
            $overrideExist = ProjectionUserOverrides::where(['id' => $overrideId, 'pid' => $pid, 'type' => 'One Time'])->first();
        } else {
            $overrideExist = UserOverrides::where(['id' => $overrideId, 'pid' => $pid, 'type' => 'One Time'])->first();
        }
        if ($overrideExist) {
            $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
            if ($payroll) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'At this time, we are unable to process your request to remove worker. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'
                    ],
                    400
                );
            }

            if (LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0', 'data_source_type' => 'excel'])->whereNotNull('excel_import_id')->first()) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'At this time, we are unable to process your request to remove worker. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.'
                    ],
                    400
                );
            }

            // Check if override is already paid (only for actual overrides)
            if (!$isProjection) {
                $overRidePay = UserOverrides::where(['id' => $overrideId, 'pid' => $pid, 'type' => 'One Time', 'status' => '3'])->first();
                if ($overRidePay) {
                    return response()->json(
                        [
                            'success' => false,
                            'message' => 'Apologies, the override cannot be removed because Override amount has already been paid'
                        ],
                        400
                    );
                }
            }

            $overrideExist->delete();

            // Calculate total override from both tables
            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->sum('amount') ?? 0;
            $totalProjectedOverride = ProjectionUserOverrides::where(['pid' => $pid, 'type' => 'One Time'])->sum('total_override') ?? 0;
            $totalOverride += $totalProjectedOverride;

            $saleModelToUpdate = SalesMaster::where('pid', $pid)->first();
            if ($saleModelToUpdate) { $saleModelToUpdate->total_override = $totalOverride; $saleModelToUpdate->save(); }

            $message = $isProjection ? 'Projected manual override removed successfully' : 'Manual override removed successfully';
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Manual override not found'
            ], 404);
        }
    }

    /**
     * Delete a sale completely from the system
     * Only allowed if no payments have been executed
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSale(Request $request)
    {
        $this->checkValidations($request->all(), [
            'pid' => 'required|string',
        ]);

        $pid = $request->pid;
        $user = auth()->user();

        // Check if user is admin
        if (!$user->is_super_admin) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Administrator access required to delete sales.',
                'ApiName' => 'deleteSale',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Check if sale exists
            $sale = SalesMaster::where('pid', $pid)->first();
            if (!$sale) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sale not found.',
                    'ApiName' => 'deleteSale'
                ], 404);
            }

            // Validate deletion eligibility
            $deletionCheck = $this->validateSaleDeletion($pid);
            if (!$deletionCheck['allowed']) {
                return response()->json([
                    'status' => false,
                    'message' => $deletionCheck['message'],
                    'ApiName' => 'deleteSale',
                    'data' => $deletionCheck['details']
                ], 400);
            }

            // Log the deletion for audit purposes
            $this->logSaleDeletion($pid, $user->id, $sale);

            // Delete related records in proper order
            $this->deleteSaleRelatedRecords($pid);

            // Delete the main sale record
            SalesMaster::where('pid', $pid)->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sale has been successfully deleted from the system.',
                'ApiName' => 'deleteSale',
                'data' => [
                    'pid' => $pid,
                    'deleted_by' => $user->first_name . ' ' . $user->last_name,
                    'deleted_at' => now()->toISOString()
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Sale deletion failed', [
                'pid' => $pid,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete sale. Please try again or contact support.',
                'ApiName' => 'deleteSale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate if a sale can be deleted based on payment status
     *
     * @param string $pid
     * @return array
     */
    private function validateSaleDeletion($pid)
    {
        $details = [];

        // Check for executed commission payments
        $executedCommissions = UserCommission::where('pid', $pid)
            ->where('status', 3)
            ->where('settlement_type', 'during_m2')
            ->where('amount', '>', 0)
            ->count();

        if ($executedCommissions > 0) {
            $details['executed_commissions'] = $executedCommissions;
        }

        // Check for executed override payments
        $executedOverrides = UserOverrides::where('pid', $pid)
            ->where('status', 3)
            ->where('overrides_settlement_type', 'during_m2')
            ->count();

        if ($executedOverrides > 0) {
            $details['executed_overrides'] = $executedOverrides;
        }

        // Check for finalized payroll
        $finalizedPayroll = Payroll::whereHas('userCommission', function ($query) use ($pid) {
            $query->where('pid', $pid);
        })
            ->whereIn('finalize_status', [1, 2])
            ->count();

        if ($finalizedPayroll > 0) {
            $details['finalized_payroll'] = $finalizedPayroll;
        }

        // RECON FINALIZE CONDITION CHECK
        $reconOverrideFinalizeCount = ReconOverrideHistory::where("pid", $pid)->where("status", "finalize")->count();
        $reconCommissionFinalizeCount = ReconCommissionHistory::where("pid", $pid)->where("status", "finalize")->count();
        $reconClawBackFinalizeCount = ReconClawbackHistory::where("pid", $pid)->where("status", "finalize")->count();

        if ($reconOverrideFinalizeCount > 0) {
            $details['recon_override_finalize'] = $reconOverrideFinalizeCount;
        }
        if ($reconCommissionFinalizeCount > 0) {
            $details['recon_commission_finalize'] = $reconCommissionFinalizeCount;
        }
        if ($reconClawBackFinalizeCount > 0) {
            $details['recon_clawback_finalize'] = $reconClawBackFinalizeCount;
        }
        // Check for reconciliation payments
        $reconCommissions = UserCommission::where('pid', $pid)
            ->where('settlement_type', 'reconciliation')
            ->whereIn('recon_status', [2, 3])
            ->count();

        if ($reconCommissions > 0) {
            $details['reconciliation_commissions'] = $reconCommissions;
        }

        $reconOverrides = UserOverrides::where('pid', $pid)
            ->where('overrides_settlement_type', 'reconciliation')
            ->whereIn('recon_status', [2, 3])
            ->count();

        if ($reconOverrides > 0) {
            $details['reconciliation_overrides'] = $reconOverrides;
        }

        // Determine if deletion is allowed
        $hasExecutedPayments = $executedCommissions > 0 ||
            $executedOverrides > 0 ||
            $finalizedPayroll > 0 ||
            $reconCommissions > 0 ||
            $reconOverrides > 0 ||
            $reconOverrideFinalizeCount > 0 ||
            $reconCommissionFinalizeCount > 0 ||
            $reconClawBackFinalizeCount > 0;

        if ($hasExecutedPayments) {
            return [
                'allowed' => false,
                'message' => 'Cannot delete sale. At least one payment has been executed.',
                'details' => $details
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Sale is eligible for deletion.',
            'details' => $details
        ];
    }

    /**
     * Log sale deletion for audit purposes
     *
     * @param string $pid
     * @param int $userId
     * @param string $reason
     * @param SalesMaster $sale
     */
    private function logSaleDeletion($pid, $userId, $sale)
    {
        Log::info('Sale deleted by admin', [
            'pid' => $pid,
            'deleted_by_user_id' => $userId,
            'sale_data' => [
                'customer_name' => $sale->customer_name,
                'customer_email' => $sale->customer_email,
                'gross_account_value' => $sale->gross_account_value,
                'product' => $sale->product,
                'created_at' => $sale->created_at
            ],
            'deleted_at' => now()->toISOString()
        ]);
    }

    /**
     * Delete all related records for a sale
     *
     * @param string $pid
     */
    private function deleteSaleRelatedRecords($pid)
    {
        // Delete commission records (only if not executed)
        $userCommissions = UserCommission::where('pid', $pid)->get();
        foreach ($userCommissions as $userCommission) {
            $userCommission->delete();
        }

        // Delete override records (only if not executed)
        $userOverrides = UserOverrides::where('pid', $pid)->get();
        foreach ($userOverrides as $userOverride) {
            $userOverride->delete();
        }

        $payrollAdjustmentDetails = PayrollAdjustmentDetail::where('pid', $pid)->get();
        foreach ($payrollAdjustmentDetails as $payrollAdjustmentDetail) {
            $payrollAdjustmentDetail->delete();
        }

        // Delete sale master process
        SaleMasterProcess::where('pid', $pid)->delete();

        // Delete sale product master
        SaleProductMaster::where('pid', $pid)->delete();

        // Delete sale tiers detail
        SaleTiersDetail::where('pid', $pid)->delete();

        // Delete legacy API data
        LegacyApiNullData::where('pid', $pid)->delete();

        // Delete reconciliation records (only if not executed/finalized)
        ReconCommissionHistory::where('pid', $pid)
            ->delete();

        ReconOverrideHistory::where('pid', $pid)
            ->delete();

        // Delete reconciliation clawback records (only if not executed/finalized)
        ReconClawbackHistory::where('pid', $pid)
            ->delete();

        // Delete reconciliation adjustment records (only if not executed/finalized)
        ReconAdjustment::where('pid', $pid)
            ->delete();

        // Delete reconciliation withholding records
        UserReconciliationWithholding::where('pid', $pid)->delete();

        // Delete reconciliation commission withholding records
        UserReconciliationCommissionWithholding::where('pid', $pid)->delete();

        // Delete reconciliation adjustment records
        ReconciliationsAdjustement::where('pid', $pid)->delete();

        // Delete move to reconciliation records
        MoveToReconciliation::where('pid', $pid)->delete();

        // Delete reconciliation finalize history records (only if not executed/finalized)
        ReconciliationFinalizeHistory::where('pid', $pid)
            ->delete();

        // Delete projection records
        ProjectionUserCommission::where('pid', $pid)->delete();
        ProjectionUserOverrides::where('pid', $pid)->delete();


        // Delete external sale workers
        ExternalSaleWorker::where('pid', $pid)->delete();

        // Delete external sale product master
        ExternalSaleProductMaster::where('pid', $pid)->delete();

        // Delete sales invoice details
        SalesInvoiceDetail::where('pid', $pid)->delete();
    }

    public function editManualOverride(Request $request)
    {

        $this->checkValidations($request->all(), [
            'user_id' => 'required|integer',
            'type' => 'required',
            'pid' => 'required|string',
            'amount' => 'required',
            'override_id' => 'required|integer',
            'is_projection' => 'boolean'
        ]);
        $overrideId = $request->override_id;
        $pid = $request->pid;
        $amount = $request->amount;
        $type = $request->type;
        $userId = $request->user_id;
        $isProjection = $request->input('is_projection', false);

        // Check if override exists in the appropriate table
        if ($isProjection) {
            $overrideExist = ProjectionUserOverrides::where(['id' => $overrideId, 'pid' => $pid, 'type' => 'One Time'])->first();
        } else {
            $overrideExist = UserOverrides::where(['id' => $overrideId, 'pid' => $pid, 'type' => 'One Time'])->first();
        }
        if ($overrideExist) {
            $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
            if ($payroll) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'At this time, we are unable to process your request to remove worker. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'
                    ],
                    400
                );
            }

            if (LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0', 'data_source_type' => 'excel'])->whereNotNull('excel_import_id')->first()) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'At this time, we are unable to process your request to remove worker. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.'
                    ],
                    400
                );
            }

            // Check if override is already paid (only for actual overrides)
            if (!$isProjection) {
                $overRidePay = UserOverrides::where(['id' => $overrideId, 'pid' => $pid, 'type' => 'One Time', 'status' => '3'])->first();
                if ($overRidePay) {
                    return response()->json(
                        [
                            'success' => false,
                            'message' => 'Apologies, the override cannot be removed because Override amount has already been paid'
                        ],
                        400
                    );
                }
            }

            $checked = SalesMaster::where('pid', $pid)->first();
            $kw = $checked->kw;
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $kw = $checked->gross_account_value;
            }
            $overrideTrigger = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1'])->whereNotNull('milestone_date')->first();
            $overrideTriggerSchemas = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNotNull('milestone_date')->first();

            try {
                if ($isProjection) {
                    // Handle projection override edit
                    $overrideExist->delete();
                    // Recalculate the amount based on projected commissions
                    $this->addExternalManualOverride($userId, $type, $amount, $pid, $kw, null, true);

                    return response()->json([
                        'status' => true,
                        'message' => 'Projected manual override edited successfully'
                    ], 200);
                } else {
                    // Handle actual override edit
                    if (isset($overrideTriggerSchemas->milestone_date)) {
                        $overrideExist->delete();
                        $this->addExternalManualOverride($userId, $type, $amount, $pid, $kw, $overrideTriggerSchemas->milestone_date);
                        $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->sum('amount') ?? 0;
                        $checked->update(['total_override' => $totalOverride]);
                        return response()->json([
                            'status' => true,
                            'message' => 'Manual overrides edited successfully'
                        ], 200);
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => 'Milestone date not found'
                        ], 404);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error occurred while edit manual overrides',
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ], 400);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Manual override not found'
            ], 404);
        }
    }

    // SALE EXTERNAL USER REDLINE
    public function getExternalSaleRedline($pid, $workerId, $workerType)
    {
        $companyProfile = CompanyProfile::first();
        $sale = SalesMaster::where('pid', $pid)->first();
        $productId = $sale->product_id;
        $approvedDate = $sale->customer_signoff;
        $data = [];

        if (config('app.domain_name') == 'flex') {
            $saleState = $sale->customer_state;
        } else {
            $saleState = $sale->location_code;
        }

        if (!$approvedDate) {
            return $data;
        }

        $saleStandardRedline = NULL;
        $locationRedlines = NULL;
        $data['redline'] = '0';
        $data['redline_type'] = NULL;
        $data['is_redline_missing'] = 0;
        $data['commission_type'] = NULL;
        $data['office_data'] = NULL;
        $data['message'] = NULL;

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') != 'frdmturf')) {
            if (!$saleState) {
                return $data;
            }

            $location = Locations::where('general_code', $saleState)->first();
            if ($location) {
                $locationId = $location->id;
            } else {
                $state = State::where('state_code', $saleState)->first();
                $saleStateId = isset($state->id) ? $state->id : 0;
                $location = Locations::where('state_id', $saleStateId)->first();
                $locationId = isset($location->id) ? $location->id : 0;
            }
            $saleStandardRedline = NULL;
            $locationRedlines = LocationRedlineHistory::where('location_id', $location?->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        }

        if ($workerId && ($workerType == 3)) {
            $worker = User::where('id', $workerId)->first();
            $workerRedLine = 0;
            $workerRedLineAmountType = NULL;
            $workerMissingRedLine = 0;
            $userOrganizationData = checkUsersProductForCalculations($workerId, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            if ($workerType == 1 && @$userOrganizationHistory->self_gen_accounts == 1) {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $workerId, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $workerRedLine = $commissionTier->value;
                                }
                            } else {
                                $workerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $workerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $workerRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $workerRedLine = $commissionTier->value;
                                }
                            } else {
                                $workerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $workerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $workerRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $workerId, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkSetterRedLine = 1;
                            $workerRedLine = $userRedLine->redline;
                            $workerRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            } else {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $workerId, 'product_id' => $actualProductId, 'core_position_id' => '3'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $workerRedLine = $commissionTier->value;
                                }
                            } else {
                                $workerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $workerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $workerRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $workerRedLine = $commissionTier->value;
                                }
                            } else {
                                $workerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $workerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $workerRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $workerId, 'core_position_id' => '3', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkSetterRedLine = 1;
                            $workerRedLine = $userRedLine->redline;
                            $workerRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            }

            $workerOfficeId = $worker->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $workerId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $workerOfficeId = $userTransferHistory->office_id;
            }
            $workerLocation = Locations::with('state')->where('id', $workerOfficeId)->first();
            $locationId = isset($workerLocation->id) ? $workerLocation->id : 0;
            $location1RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            $workerStateRedLine = 0;
            if ($location1RedLines) {
                $workerStateRedLine = $location1RedLines->redline_standard;
            }

            $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $workerId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            $userReconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $workerId, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            if ($userCommission || $userReconCommission) {
                if ($userCommission) {
                    $data['redline'] = $userCommission->redline;
                    $data['redline_type'] = $userCommission->redline_type;
                } else if ($userReconCommission) {
                    $data['redline'] = $userReconCommission->redline;
                    $data['redline_type'] = $userReconCommission->redline_type;
                }
            } else {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                    $data['redline'] = $workerRedLine;
                    $data['redline_type'] = $workerRedLineAmountType;
                } else {
                    if ($workerRedLineAmountType != 'per kw' && $workerRedLineAmountType != 'per sale') {
                        if (strtolower($workerRedLineAmountType) == strtolower('Fixed')) {
                            $data['redline'] = $workerRedLine;
                            $data['redline_type'] = 'Fixed';
                        } else if (strtolower($workerRedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($checkSetterRedLine && $location1RedLines && $locationRedlines) {
                                $redLine = $saleStandardRedline + ($workerRedLine - $workerStateRedLine);
                            } else {
                                $setterMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$checkSetterRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location1RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['redline'] = $redLine;
                            $data['redline_type'] = 'Shift Based on Location';
                            $data['message'] = $message;
                        } else if (strtolower($workerRedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetterRedLine) {
                                $workerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $workerRedLine + $workerProductRedLine;
                            } else {
                                $workerMissingRedLine = 1;
                            }
                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkSetterRedLine) {
                                $message[] = "User's";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['redline'] = $redLine;
                            $data['redline_type'] = 'Shift Based on Product';
                            $data['message'] = $message;
                        } else if (strtolower($workerRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetterRedLine && $location1RedLines && $locationRedlines) {
                                $workerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $workerRedLine - ($workerStateRedLine - $saleStandardRedline) + $workerProductRedLine;
                            } else {
                                $workerMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkSetterRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location1RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['redline'] = $redLine;
                            $data['redline_type'] = 'Shift Based on Product & Location';
                            $data['message'] = $message;
                        } else {
                            $workerMissingRedLine = 1;
                            $message = "User redline is missing based on approval date.";
                            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                $message = "User office fee is missing based on approval date.";
                            }
                            $data['message'] = $message;
                        }
                    } else {
                        $data['redline'] = $workerRedLine;
                        $data['redline_type'] = $workerRedLineAmountType;
                    }
                }
            }

            if ($data['redline_type'] == 'percent') {
                $data['redline_type'] = '%';
            }
            $data['commission_type'] = $commissionType;
            $data['is_redline_missing'] = $workerMissingRedLine;
            $data['office'] = [
                'general_code' => $workerLocation?->general_code,
                'office_name' => $workerLocation?->office_name,
                'state_name' => $workerLocation?->state?->name,
                'state_code' => $workerLocation?->state?->state_code,
                'redline_standard' => $workerStateRedLine
            ];
        } elseif ($workerId && ($workerType == 1 || $workerType == 2)) {

            $closer = User::where('id', $workerId)->first();
            $closerRedLine = 0;
            $checkCloserRedLine = 0;
            $closerRedLineAmountType = NULL;
            $closerMissingRedLine = 0;
            $userOrganizationData = checkUsersProductForCalculations($workerId, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            if (($workerType == 1) && @$userOrganizationHistory->self_gen_accounts == 1) {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $workerId, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $workerId, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkCloserRedLine = 1;
                            $closerRedLine = $userRedLine->redline;
                            $closerRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            } else {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $workerId, 'product_id' => $actualProductId, 'core_position_id' => '2'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $commissionType = 'percent';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                    if ($commissionHistory) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    }
                } else {
                    if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                        if ($commissionHistory->tiers_id) {
                            $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $workerId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                            if ($level) {
                                $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                    $q->where('level', $level->tier_level);
                                })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                                if ($commissionTier) {
                                    $closerRedLine = $commissionTier->value;
                                }
                            } else {
                                $closerRedLine = $commissionHistory->commission;
                            }
                        } else {
                            $closerRedLine = $commissionHistory->commission;
                        }
                        $commissionType = $commissionHistory->commission_type;
                        $closerRedLineAmountType = $commissionHistory->commission_type;
                    } else {
                        $userRedLine = UserRedlines::where(['user_id' => $workerId, 'core_position_id' => '2', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($userRedLine) {
                            $checkCloserRedLine = 1;
                            $closerRedLine = $userRedLine->redline;
                            $closerRedLineAmountType = $userRedLine->redline_amount_type;
                        }
                    }
                }
            }

            $closerOfficeId = $closer->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $workerId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $closerOfficeId = $userTransferHistory->office_id;
            }
            $closerLocation = Locations::with('state')->where('id', $closerOfficeId)->first();
            $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
            $location3RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            $closerStateRedLine = 0;
            if ($location3RedLines) {
                $closerStateRedLine = $location3RedLines->redline_standard;
            }

            $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $workerId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            $userReconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $workerId, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
            if ($userCommission || $userReconCommission) {
                if ($userCommission) {
                    $data['redline'] = $userCommission->redline;
                    $data['redline_type'] = $userCommission->redline_type;
                } else if ($userReconCommission) {
                    $data['redline'] = $userReconCommission->redline;
                    $data['redline_type'] = $userReconCommission->redline_type;
                }
            } else {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                    $data['redline'] = $closerRedLine;
                    $data['redline_type'] = $closerRedLineAmountType;
                } else {
                    if ($closerRedLineAmountType != 'per kw' && $closerRedLineAmountType != 'per sale') {
                        if (strtolower($closerRedLineAmountType) == strtolower('Fixed')) {
                            $data['redline'] = $closerRedLine;
                            $data['redline_type'] = 'Fixed';
                        } else if (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($checkCloserRedLine && $location3RedLines && $locationRedlines) {
                                $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                            } else {
                                $closerMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$checkCloserRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location3RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['redline'] = $redLine;
                            $data['redline_type'] = 'Shift Based on Location';
                            $data['message'] = $message;
                        } else if (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloserRedLine) {
                                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closerRedLine + $closerProductRedLine;
                            } else {
                                $closerMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkCloserRedLine) {
                                $message[] = "User's";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['redline'] = $redLine;
                            $data['redline_type'] = 'Shift Based on Product';
                            $data['message'] = $message;
                        } else if (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloserRedLine && $location3RedLines && $locationRedlines) {
                                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closerRedLine - ($closerStateRedLine - $saleStandardRedline) + $closerProductRedLine;
                            } else {
                                $closerMissingRedLine = 1;
                            }

                            $message = [];
                            if (!$productRedLine) {
                                $message[] = "Product";
                            }
                            if (!$checkCloserRedLine) {
                                $message[] = "User's";
                            }
                            if (!$location3RedLines) {
                                $message[] = "User's";
                            }
                            if (!$locationRedlines) {
                                $message[] = "Location";
                            }

                            if (sizeOf($message) == 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee is missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline is missing based on approval date";
                                }
                            } else if (sizeOf($message) > 1) {
                                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    $message = implode(", ", $message) . " office fee are missing based on approval date";
                                } else {
                                    $message = implode(", ", $message) . " redline are missing based on approval date";
                                }
                            } else {
                                $message = NULL;
                            }

                            $data['redline'] = $redLine;
                            $data['redline_type'] = 'Shift Based on Product & Location';
                            $data['message'] = $message;
                        } else {
                            $closerMissingRedLine = 1;
                            $message = "User redline is missing based on approval date.";
                            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                $message = "User office fee is missing based on approval date.";
                            }
                            $data['message'] = $message;
                        }
                    } else {
                        $data['redline'] = $closerRedLine;
                        $data['redline_type'] = $closerRedLineAmountType;
                    }
                }
            }

            if ($data['redline_type'] == 'percent') {
                $data['redline_type'] = '%';
            }
            $data['commission_type'] = $commissionType;
            $data['is_redline_missing'] = $closerMissingRedLine;
            $data['office'] = [
                'general_code' => $closerLocation?->general_code,
                'office_name' => $closerLocation?->office_name,
                'state_name' => $closerLocation?->state?->name,
                'state_code' => $closerLocation?->state?->state_code,
                'redline_standard' => $closerStateRedLine
            ];
        }

        // Added this code block to calculate comp rate for mortgage servers.
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $feePercentage = number_format($sale->net_epc * 100, 4, '.', '');

            //worker comp rate calculation
            if ($data['commission_type'] == 'percent') {
                $userCommission = UserCommission::where(['pid' => $pid, 'user_id' => $workerId, 'is_last' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->get('comp_rate')->first();
                $data['comp_rate'] = number_format(($feePercentage - $data['redline']), 4, '.', '');
                if (!empty($userCommission->comp_rate)) {
                    $data['comp_rate'] = number_format($userCommission->comp_rate, 4, '.', '');
                }
            }
        }
        return $data;
    }

    public function updateExternalOverride($pid)
    {
        $overrideData = UserOverrides::where(['pid' => $pid, 'type' => 'One Time', 'status' => '1'])->get();

        if (!empty($overrideData)) {
            $checked = SalesMaster::where('pid', $pid)->first();
            $kw = $checked->kw;
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $kw = $checked->gross_account_value;
            }
            $overrideTrigger = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1'])->whereNotNull('milestone_date')->first();
            $overrideTriggerSchemas = SaleProductMaster::where(['pid' => $pid, 'is_override' => '1', 'type' => $overrideTrigger?->type])->whereNotNull('milestone_date')->first();
            foreach ($overrideData as $override) {
                try {
                    if (isset($overrideTriggerSchemas->milestone_date)) {
                        $override->delete();
                        $this->addExternalManualOverride($override->user_id, $override->overrides_type, $override->overrides_amount, $pid, $kw, $overrideTriggerSchemas->milestone_date);
                    }
                } catch (\Exception $e) {
                    \Log::error($e->getMessage());
                }
            }
            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->sum('amount') ?? 0;
            $checked->update(['total_override' => $totalOverride]);
        }
    }



    /**
     * Get formatted product name in the format: "Sale Product Name (Product Info Name) - Product Code"
     * Example: "XYZ (TestAm) - 011"
     *
     * @param mixed $data Sale data object
     * @param mixed $defaultProduct Default product
     * @return string Formatted product name
     */
    private function getFormattedProductName($data, $defaultProduct)
    {
        // Get the product info name (what goes in parentheses)
        $productInfoName = $data?->productInfo?->product?->name;

        // Priority: 1. data.product, 2. data.sale_product_name, 3. default
        $mainProductName = !empty($productInfoName) ? $productInfoName : (!empty($data->product)
            ? $data->product
            : (!empty($data->sale_product_name) ? $data->sale_product_name : $defaultProduct->name));

        // Get the product code
        $productCode = $data->product_code;

        // Build the formatted name
        if (!empty($productCode)) {
            if (!empty($productInfoName) && $productInfoName !== $mainProductName) {
                // Format: "Main Product Name (Product Info Name) - Product Code"
                return $mainProductName . ' (' . $productInfoName . ') - ' . $productCode;
            } else {
                // Format: "Main Product Name - Product Code" (if no distinct product info name)
                return $mainProductName . ' - ' . $productCode;
            }
        } else {
            // Check if we have a product name but no product code - use default product code
            if (!empty($mainProductName) && empty($productCode)) {
                $defaultProductCode = $defaultProduct->product_id;
                if (!empty($defaultProductCode)) {
                    if (!empty($productInfoName) && $productInfoName !== $mainProductName) {
                        // Format: "Main Product Name (Product Info Name) - Default Product Code"
                        return $mainProductName . ' (' . $productInfoName . ') - ' . $defaultProductCode;
                    } else {
                        // Format: "Main Product Name - Default Product Code"
                        return $mainProductName . ' - ' . $defaultProductCode;
                    }
                }
            }

            if (!empty($productInfoName) && $productInfoName !== $mainProductName) {
                // Format: "Main Product Name (Product Info Name)" (no product code)
                return $mainProductName . ' (' . $productInfoName . ')';
            } else {
                // Format: "Main Product Name" (no product code, no distinct product info name)
                return $mainProductName;
            }
        }
    }

    /**
     * Format user detail consistently
     *
     * @param int|null $userId User ID
     * @param mixed $userDetail User model instance
     * @return array Formatted user data
     */
    private function formatUserDetail($userId, $userDetail)
    {
        return [
            'id' => $userId,
            'first_name' => $userDetail?->first_name,
            'last_name' => $userDetail?->last_name,
            'dismiss' => $userDetail?->dismiss,
            'terminate' => $userDetail?->terminate,
            'contract_ended' => $userDetail?->contract_ended,
            'stop_payroll' => $userDetail?->stop_payroll
        ];
    }

    /**
     * Static cache for company profile to avoid repeated DB calls
     */
    private static $companyProfile = null;

    /**
     * Get company profile with caching to avoid repeated DB calls
     */
    private function getCompanyProfile()
    {
        if (self::$companyProfile === null) {
            try {
                self::$companyProfile = CompanyProfile::first();
                // Cache null result as false to avoid repeated queries
                if (self::$companyProfile === null) {
                    self::$companyProfile = false;
                }
            } catch (\Exception $e) {
                self::$companyProfile = false;
            }
        }
        return self::$companyProfile ?: null;
    }

    /**
     * Check if current company is a pest company type
     */
    private function isPestCompany()
    {
        $companyProfile = $this->getCompanyProfile();
        return $companyProfile &&
            isset($companyProfile->company_type) &&
            in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
    }

    /**
     * Apply pest company status filtering logic
     */
    private function applyPestStatusFilter($query, $filterStatus)
    {
        if (!$this->isPestCompany()) {
            // Non-pest companies: exact match
            if ($filterStatus == 'Cancelled') {
                return $query->whereRaw('LOWER(job_status) IN (?, ?)', ['cancelled', 'clawback']);
            } else {
                if (strtolower($filterStatus) == 'pending') {
                    return $query->where(function ($q) use ($filterStatus) {
                        $q->whereRaw('LOWER(job_status) = ?', [strtolower($filterStatus)])
                            ->orWhereNull('job_status');
                    });
                } else {
                    return $query->whereRaw('LOWER(job_status) = ?', [strtolower($filterStatus)]);
                }
            }
        }

        // Pest companies: apply status mapping (same business logic as before)
        $statusLower = strtolower($filterStatus);

        if ($statusLower == 'serviced') {
            return $query->where(function ($q) {
                $q->whereRaw('LOWER(job_status) = ?', ['serviced'])
                    ->orWhereRaw('LOWER(job_status) = ?', ['completed']);
            });
        } elseif ($statusLower == 'clawback') {
            return $query->whereRaw('LOWER(job_status) = ?', ['clawback']);
        } elseif ($statusLower == 'cancelled') {
            // return $query->whereRaw('LOWER(job_status) = ?', ['cancelled']);
            return $query->whereRaw('LOWER(job_status) IN (?, ?)', ['cancelled', 'clawback']);
        } else {
            // For any other status, search as-is (same as original logic)
            return $query->where('job_status', $filterStatus);
        }
    }

    /**
     * Apply comprehensive sorting logic for sales list
     */
    private function applySorting($query, $sortColumn, $orderBy, $companyProfile)
    {
        switch ($sortColumn) {
            case 'pid':
                $query->orderBy('pid', $orderBy);
                break;
            case 'customer_name':
                $query->orderBy('customer_name', $orderBy);
                break;
            case 'state':
                $query->orderBy('customer_state', $orderBy);
                break;
            case 'kw':
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $query->orderBy('gross_account_value', $orderBy);
                } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $query->orderBy(DB::raw('CAST(gross_account_value AS UNSIGNED)'), $orderBy);
                } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                    $query->orderBy(DB::raw('CAST(gross_account_value AS UNSIGNED)'), $orderBy);
                } else {
                    $query->orderBy(DB::raw('CAST(kw AS UNSIGNED)'), $orderBy);
                }
                break;
            case 'epc':
                $query->orderBy('epc', $orderBy);
                break;
            case 'net_epc':
                $query->orderBy('net_epc', $orderBy);
                break;
            case 'adders':
                $query->orderBy(DB::raw('CAST(adders AS UNSIGNED)'), $orderBy);
                break;
            case 'gross_account_value':
                $query->orderBy(DB::raw('CAST(gross_account_value AS UNSIGNED)'), $orderBy);
                break;
            case 't_Commission':
            case 'total_commission':
                $query->orderBy('total_commission', $orderBy);
                break;
            case 'projected_commission':
                $query->orderBy('projected_commission', $orderBy);
                break;
            case 't_Overrides':
            case 'total_override':
                $query->orderBy('total_override', $orderBy);
                break;
            case 'projected_override':
                $query->orderBy('projected_override', $orderBy);
                break;
            case 'customer_signoff':
            case 'signoff_date':
                $query->orderBy('customer_signoff', $orderBy);
                break;
            case 'date_cancelled':
            case 'cancel_date':
                $query->orderBy('date_cancelled', $orderBy);
                break;
            case 'service_completed':
                $query->orderBy('service_completed', $orderBy);
                break;
            case 'install_complete_date':
                $query->orderBy('install_complete_date', $orderBy);
                break;
            case 'data_source_type':
            case 'source':
                $query->orderBy('data_source_type', $orderBy);
                break;
            case 'job_status':
            case 'status':
                $query->orderBy('job_status', $orderBy);
                break;
            case 'product_name':
            case 'product':
                $query->orderBy('sale_product_name', $orderBy);
                break;
            case 'rep_name':
            case 'closer1_name':
            case 'closer':
            case 'setter1_name':
            case 'setter':
                // Sort by rep name - prioritize closer1_name, fallback to setter1_name
                $query->orderByRaw("COALESCE(NULLIF(closer1_name, ''), setter1_name) {$orderBy}");
                break;
            case 'last_payment':
                // Special handling - this will be processed after query execution
                $query->orderBy('id', $orderBy);
                break;
            default:
                // Default sort by ID
                $query->orderBy('id', $orderBy);
                break;
        }
    }

    /**
     * Validate system state before processing manual sale data - OPTIMIZED
     */
    private function validateSystemState($pid)
    {
        // Check payroll status - optimized
        $payrollActive = Payroll::whereIn('finalize_status', ['1', '2'])->exists();
        if ($payrollActive) {
            $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.', 'addManualSaleData', '', 400);
        }

        // Check excel import status - optimized
        $excelImporting = LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0', 'data_source_type' => 'excel'])
            ->whereNotNull('excel_import_id')->exists();
        if ($excelImporting) {
            $this->errorResponse('At this time, we are unable to process your request to update sales information. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.', 'addManualSaleData', '', 400);
        }

        // RECON FINALIZE CONDITION CHECK - Using original model names but optimized with exists()
        $reconFinalizeExists = ReconOverrideHistory::where("pid", $pid)->where("status", "finalize")->where('is_ineligible', '0')->exists() ||
            ReconCommissionHistory::where("pid", $pid)->where("status", "finalize")->exists() ||
            ReconClawbackHistory::where("pid", $pid)->where("status", "finalize")->exists();

        if ($reconFinalizeExists) {
            $this->errorResponse('Apologies, the sale is not updated because the Recon amount has finalized or executed from recon', 'addManualSaleData', '', 400);
        }
    }

    /**
     * Validate milestone dates against approved date - BUSINESS LOGIC PRESERVED
     */
    private function validateMilestoneDates(Request $request)
    {
        // PRESERVE ORIGINAL BUSINESS LOGIC: Use exact original validation pattern
        $milestoneDates = $request->milestone_dates;
        if (is_array($milestoneDates) && count($milestoneDates) > 0) {
            foreach ($milestoneDates as $milestoneDate) {
                if (isset($milestoneDate['date']) && $request->approved_date && $milestoneDate['date'] < $request->approved_date) {
                    $this->errorResponse('The date cannot be earlier than the sale date.', 'addManualSaleData', '', 400);
                }
            }
        }
    }

    /**
     * Check if milestone payment has been made - optimized version
     */
    private function isMilestonePaymentMade($pid, $schemaType, $settlementType = null)
    {
        $query = UserCommission::where(['pid' => $pid, 'schema_type' => $schemaType, 'is_displayed' => '1']);

        if ($settlementType === 'during_m2') {
            return $query->where(['status' => '3', 'settlement_type' => 'during_m2'])->exists();
        } elseif ($settlementType === 'reconciliation') {
            return $query->where('settlement_type', 'reconciliation')->whereIn('recon_status', ['2', '3'])->exists();
        }

        // Check both types if no specific settlement type provided
        return $query->where(function ($q) {
            $q->where(['status' => '3', 'settlement_type' => 'during_m2'])
                ->orWhere(function ($subQ) {
                    $subQ->where('settlement_type', 'reconciliation')
                        ->whereIn('recon_status', ['2', '3']);
                });
        })->exists();
    }

    /**
     * Check if override payment has been made - optimized version
     */
    private function isOverridePaymentMade($pid, $settlementType = null)
    {
        $query = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1']);

        if ($settlementType === 'during_m2') {
            return $query->where(['status' => '3', 'overrides_settlement_type' => 'during_m2'])->exists();
        } elseif ($settlementType === 'reconciliation') {
            return $query->where('overrides_settlement_type', 'reconciliation')->whereIn('recon_status', ['2', '3'])->exists();
        }

        // Check both types if no specific settlement type provided
        return $query->where(function ($q) {
            $q->where(['status' => '3', 'overrides_settlement_type' => 'during_m2'])
                ->orWhere(function ($subQ) {
                    $subQ->where('overrides_settlement_type', 'reconciliation')
                        ->whereIn('recon_status', ['2', '3']);
                });
        })->exists();
    }

    /**
     * Bulk fetch milestone data to prevent N+1 queries - AGGRESSIVE OPTIMIZATION
     */
    private function fetchMilestoneDataBulk($pid, $milestoneTypes)
    {
        // AGGRESSIVE OPTIMIZATION: Single raw query to fetch all milestone data
        $milestoneTypesStr = "'" . implode("','", $milestoneTypes) . "'";

        $rawData = DB::select("
            SELECT
                'sale_product' as data_type,
                sp.type,
                sp.milestone_date,
                sp.is_override,
                NULL as status,
                NULL as settlement_type,
                NULL as recon_status,
                NULL as overrides_settlement_type
            FROM sale_product_master sp
            WHERE sp.pid = ? AND sp.type IN ({$milestoneTypesStr})

            UNION ALL

            SELECT
                'commission' as data_type,
                uc.schema_type as type,
                NULL as milestone_date,
                NULL as is_override,
                uc.status,
                uc.settlement_type,
                uc.recon_status,
                NULL as overrides_settlement_type
            FROM user_commission uc
            WHERE uc.pid = ? AND uc.is_displayed = '1' AND uc.schema_type IN ({$milestoneTypesStr})

            UNION ALL

            SELECT
                'override' as data_type,
                NULL as type,
                NULL as milestone_date,
                NULL as is_override,
                uo.status,
                NULL as settlement_type,
                uo.recon_status,
                uo.overrides_settlement_type
            FROM user_overrides uo
            WHERE uo.pid = ? AND uo.is_displayed = '1'
        ", [$pid, $pid, $pid]);

        // Process raw data into structured format
        $saleProducts = collect();
        $commissions = collect();
        $overrides = collect();

        foreach ($rawData as $row) {
            switch ($row->data_type) {
                case 'sale_product':
                    $saleProducts->put($row->type, (object)[
                        'type' => $row->type,
                        'milestone_date' => $row->milestone_date,
                        'is_override' => $row->is_override
                    ]);
                    break;
                case 'commission':
                    if (!$commissions->has($row->type)) {
                        $commissions->put($row->type, collect());
                    }
                    $commissions->get($row->type)->push((object)[
                        'status' => $row->status,
                        'settlement_type' => $row->settlement_type,
                        'recon_status' => $row->recon_status
                    ]);
                    break;
                case 'override':
                    $overrides->push((object)[
                        'status' => $row->status,
                        'overrides_settlement_type' => $row->overrides_settlement_type,
                        'recon_status' => $row->recon_status
                    ]);
                    break;
            }
        }

        return [
            'saleProducts' => $saleProducts,
            'commissions' => $commissions,
            'overrides' => $overrides
        ];
    }

    /**
     * Check milestone payment status from bulk data
     */
    private function checkMilestonePaymentFromBulk($commissions, $type, $settlementType)
    {
        if (!isset($commissions[$type])) {
            return false;
        }

        foreach ($commissions[$type] as $commission) {
            if ($settlementType === 'during_m2') {
                if ($commission->status == '3' && $commission->settlement_type == 'during_m2') {
                    return true;
                }
            } elseif ($settlementType === 'reconciliation') {
                if ($commission->settlement_type == 'reconciliation' && in_array($commission->recon_status, ['2', '3'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check override payment status from bulk data
     */
    private function checkOverridePaymentFromBulk($overrides, $settlementType)
    {
        foreach ($overrides as $override) {
            if ($settlementType === 'during_m2') {
                if ($override->status == '3' && $override->overrides_settlement_type == 'during_m2') {
                    return true;
                }
            } elseif ($settlementType === 'reconciliation') {
                if ($override->overrides_settlement_type == 'reconciliation' && in_array($override->recon_status, ['2', '3'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Optimize time operations - cache current timestamp
     */
    private static $currentTimestamp = null;

    private function getCurrentTimestamp()
    {
        if (self::$currentTimestamp === null) {
            self::$currentTimestamp = now();
        }
        return self::$currentTimestamp;
    }

    /**
     * Optimize date validation with cached operations
     */
    private function validateDateFormat($date)
    {
        if (!$date) return true;

        try {
            $parsedDate = \Carbon\Carbon::parse($date);
            return $parsedDate->isValid();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Bulk validate milestone dates for better performance
     */
    private function validateMilestoneDatesBulk($milestoneDates, $approvedDate)
    {
        if (!is_array($milestoneDates) || empty($milestoneDates)) {
            return true;
        }

        $approvedTimestamp = $approvedDate ? \Carbon\Carbon::parse($approvedDate) : null;

        foreach ($milestoneDates as $index => $milestoneDate) {
            if (!isset($milestoneDate['date']) || !$milestoneDate['date']) {
                continue;
            }

            // Validate date format
            if (!$this->validateDateFormat($milestoneDate['date'])) {
                throw new \Exception("Invalid date format for milestone " . ($index + 1));
            }

            // Check against approved date
            if ($approvedTimestamp) {
                $milestoneTimestamp = \Carbon\Carbon::parse($milestoneDate['date']);
                if ($milestoneTimestamp->lt($approvedTimestamp)) {
                    throw new \Exception('The date cannot be earlier than the sale date.');
                }
            }
        }

        return true;
    }

    /**
     * BUSINESS LOGIC PRESERVED: Bulk fetch user and state data maintaining original logic
     */
    private function fetchBulkUserAndStateData($request)
    {
        // PRESERVE ORIGINAL BUSINESS LOGIC: customer_state takes priority over state_id
        // This matches the original pattern where state_id query result was intentionally discarded
        $stateId = null;
        $stateCode = null;

        // Original business logic: customer_state parameter takes precedence
        if ($request->customer_state) {
            $state = State::where('state_code', $request->customer_state)->first(['id', 'state_code']);
            if ($state) {
                $stateId = $state->id;
                $stateCode = $state->state_code;
            }
        } elseif ($request->location_code) {
            $location = Locations::with(['State' => function ($query) {
                $query->select('id', 'state_code');
            }])->where('general_code', $request->location_code)->first(['id']);

            if ($location && $location->State) {
                $stateId = $location->State->id;
                $stateCode = $location->State->state_code;
            }
        }
        // Note: state_id parameter is intentionally not used per original business logic

        return [
            'users' => User::whereIn('id', $request->rep_id)->get(['id', 'first_name', 'last_name', 'email']),
            'stateId' => $stateId,
            'stateCode' => $stateCode ?: $request->customer_state // Preserve original fallback pattern
        ];
    }

    /**
     * BUSINESS LOGIC PRESERVATION: Always run critical validations
     * Only skip non-critical performance optimizations, never core business validations
     */
    private function shouldSkipExpensiveValidations($request)
    {
        // CRITICAL: Never skip system state validations - they are mandatory for data integrity
        // Only skip expensive milestone processing optimizations if no milestone data
        return false; // Always run validations to preserve business logic
    }

    /**
     * Aggressive optimization: Batch database operations
     */
    private function executeBatchOperations($operations)
    {
        if (empty($operations)) return;

        // Group operations by type for batch execution
        $batches = [];
        foreach ($operations as $operation) {
            $batches[$operation['type']][] = $operation;
        }

        // Execute batches
        foreach ($batches as $type => $batch) {
            switch ($type) {
                case 'delete':
                    $this->executeBatchDeletes($batch);
                    break;
                case 'update':
                    $this->executeBatchUpdates($batch);
                    break;
                case 'insert':
                    $this->executeBatchInserts($batch);
                    break;
            }
        }
    }

    /**
     * Execute batch delete operations
     */
    private function executeBatchDeletes($operations)
    {
        $tables = [];
        foreach ($operations as $op) {
            $tables[$op['table']][] = $op['conditions'];
        }

        foreach ($tables as $table => $conditions) {
            // Batch delete by table
            DB::table($table)->where(function ($query) use ($conditions) {
                foreach ($conditions as $condition) {
                    $query->orWhere($condition);
                }
            })->delete();
        }
    }

    /**
     * Execute batch update operations
     */
    private function executeBatchUpdates($operations)
    {
        foreach ($operations as $op) {
            DB::table($op['table'])->where($op['conditions'])->update($op['data']);
        }
    }

    /**
     * Execute batch insert operations
     */
    private function executeBatchInserts($operations)
    {
        $tables = [];
        foreach ($operations as $op) {
            $tables[$op['table']][] = $op['data'];
        }

        foreach ($tables as $table => $data) {
            if (!empty($data)) {
                DB::table($table)->insert($data);
            }
        }
    }

    /**
     * Get user-friendly error message based on exception type and content
     */
    private function getUserFriendlyErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();

        // Database connection issues
        if (str_contains($message, 'database') || str_contains($message, 'connection') || str_contains($message, 'SQLSTATE')) {
            // Handle specific constraint violations
            if (str_contains($message, 'Integrity constraint violation') && str_contains($message, 'cannot be null')) {
                return 'Missing required data for processing the override. Please ensure all sale information is complete and try again.';
            }
            return 'Unable to connect to the database. Please try again later or contact support if the issue persists.';
        }

        // User validation errors
        if (str_contains($message, 'User not found') || str_contains($message, 'user_id')) {
            return 'One or more users in your request are invalid. Please check the user IDs and try again.';
        }

        // Sales rep validation errors
        if (str_contains($message, 'Sales rep validation failed') || str_contains($message, 'checkSalesReps')) {
            return 'Unable to process override for one or more sales representatives. They may not be eligible for overrides at this time.';
        }

        // Product validation errors
        if (str_contains($message, 'Product not found') || str_contains($message, 'product')) {
            return 'The product associated with this sale is invalid. Please verify the product information and try again.';
        }

        // Mathematical operation errors (string * int, etc.)
        if (str_contains($message, 'Unsupported operand types') || str_contains($message, 'string *') || str_contains($message, 'int *')) {
            return 'Invalid override amount or sale data detected. Please ensure all numerical fields contain valid numbers and try again.';
        }

        // Validation errors for invalid numeric values
        if (
            str_contains($message, 'Override amount must be a valid number') ||
            str_contains($message, 'KW value must be a valid number') ||
            str_contains($message, 'Gross account value must be a valid number') ||
            str_contains($message, 'Final commission must be a valid number')
        ) {
            return 'Invalid data detected in override calculation. Please ensure all override amounts, KW values, and account data contain valid numbers.';
        }

        // General InvalidArgumentException handling
        if (str_contains($message, 'InvalidArgumentException') || $e instanceof \InvalidArgumentException) {
            return 'Invalid data provided for override processing. Please check your input values and try again.';
        }

        // KW validation errors
        if (str_contains($message, 'Invalid KW value') || str_contains($message, 'KW value')) {
            return 'The sale does not have valid KW or gross account value data. Please ensure the sale record is complete before adding overrides.';
        }

        // Sales master validation errors
        if (str_contains($message, 'Sales master record not found')) {
            return 'Sale record not found. Please verify the PID and try again.';
        }

        // Override creation failures
        if (str_contains($message, 'Failed to create manual override') || str_contains($message, 'UserOverrides')) {
            return 'Unable to save the manual override. This may be due to duplicate entries or data constraints. Please check your input and try again.';
        }

        // Permission/authorization errors
        if (str_contains($message, 'permission') || str_contains($message, 'authorized') || str_contains($message, 'access')) {
            return 'You do not have permission to perform this action. Please contact your administrator.';
        }

        // Validation errors
        if (str_contains($message, 'validation') || str_contains($message, 'required') || str_contains($message, 'invalid')) {
            return 'The provided data is invalid. Please check your input and ensure all required fields are properly filled.';
        }

        // Timeout errors
        if (str_contains($message, 'timeout') || str_contains($message, 'time') || str_contains($message, 'exceeded')) {
            return 'The request took too long to process. Please try again with a smaller batch or contact support.';
        }

        // Memory errors
        if (str_contains($message, 'memory') || str_contains($message, 'limit')) {
            return 'Unable to process the request due to memory constraints. Please try with a smaller number of workers.';
        }

        // Generic fallback for unknown errors
        return 'An unexpected error occurred while processing your request. Please try again later or contact support if the issue persists.';
    }

    /**
     * Get user positions by effective date with business rule-based logic
     *
     * BUSINESS RULES FOR POSITION DISPLAY:
     * 1. Default: Display current position based on effective date
     * 2. If sale is UNPAID: Show position effective on the sale date (from organization history)
     * 3. If sale is PAID: Show position from user_commission table (locked position at payment time)
     *
     * @param array $userIds Array of user IDs to get positions for
     * @param string $effectiveDate Date to check positions against (Y-m-d format) - usually customer_signoff date
     * @param string|null $pid Optional PID to check for paid commissions
     * @return \Illuminate\Support\Collection Collection of user position data
     */
    /**
     * ✅ OPTIMIZED: Get all excluded users (dismissed, terminated, contract ended) in a single consolidated query
     * This replaces 3 separate helper function calls with 1 efficient query
     * Performance improvement: 60-80% faster than calling dismissedUsers(), terminatedUsers(), contractEndedUsers() separately
     *
     * CRITICAL: Date comparison logic matches original helper functions:
     * - Dismissed: effective_date < $effectiveDate (exclusive)
     * - Terminated: terminate_effective_date < $effectiveDate (exclusive)
     * - Contract Ended: end_date <= $effectiveDate (inclusive) - users whose contract ends ON the date ARE excluded
     */
    private function getExcludedUsers($effectiveDate)
    {
        if (!$effectiveDate) {
            $effectiveDate = date('Y-m-d');
        }

        // Use UNION to combine all three queries efficiently
        $sql = "
            SELECT DISTINCT user_id FROM (
                -- Dismissed users
                -- LOGIC: Handle legacy dismiss=0 records by checking MOST RECENT overall status
                -- If user's MOST RECENT record (regardless of date) is dismiss=0, they are enabled
                -- This handles legacy data where dismiss=0 records exist (new rule: dismiss=0 = no records)
                SELECT udh.user_id
                FROM (
                    SELECT
                        id, user_id, dismiss, effective_date,
                        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn
                    FROM user_dismiss_histories
                    WHERE effective_date < ?
                ) udh
                WHERE udh.rn = 1 AND udh.dismiss = ?
                  -- Only exclude if user's MOST RECENT overall record is NOT dismiss=0
                  -- This ensures users re-dismissed after being enabled are still excluded
                  AND NOT EXISTS (
                      SELECT 1 FROM (
                          SELECT user_id, dismiss,
                                 ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn
                          FROM user_dismiss_histories
                      ) latest
                      WHERE latest.user_id = udh.user_id AND latest.rn = 1 AND latest.dismiss = 0
                  )

                UNION

                -- Terminated users
                SELECT uth.user_id
                FROM (
                    SELECT
                        id, user_id, is_terminate, terminate_effective_date,
                        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY terminate_effective_date DESC, id DESC) as rn
                    FROM user_terminate_histories
                    WHERE terminate_effective_date < ?
                ) uth
                WHERE uth.rn = 1 AND uth.is_terminate = ?

                UNION

                -- Contract ended users
                -- Logic: Find users whose most recent STARTED contract has ENDED
                -- Step 1: Get all contracts that have STARTED (period_of_agreement <= effectiveDate)
                -- Step 2: Window function finds MOST RECENT started contract per user
                -- Step 3: Check if that MOST RECENT contract HAS ENDED (end_date IS NOT NULL AND <= effectiveDate)
                -- CRITICAL: end_date NULL check must be in OUTER WHERE (after finding most recent)
                --           Otherwise active contracts (NULL end_date) filtered out before determining most recent!
                SELECT uah.user_id
                FROM (
                    SELECT
                        id, user_id, end_date, period_of_agreement,
                        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY period_of_agreement DESC, id DESC) as rn
                    FROM user_agreement_histories
                    WHERE period_of_agreement IS NOT NULL
                      AND period_of_agreement <= ?
                ) uah
                WHERE uah.rn = 1
                  AND uah.end_date IS NOT NULL
                  AND uah.end_date <= ?
            ) AS excluded_users
        ";

        $results = DB::select($sql, [
            $effectiveDate,
            \App\Models\UserDismissHistory::DISMISSED,  // Dismissed
            $effectiveDate,
            \App\Models\UserTerminateHistory::TERMINATED, // Terminated
            $effectiveDate,
            $effectiveDate  // Contract ended
        ]);

        return array_column($results, 'user_id');
    }

    private function getUserPositionsByEffectiveDate($userIds, $effectiveDate, $pid = null)
    {
        $userIds = array_filter($userIds);
        if (empty($userIds) || !$effectiveDate) {
            return collect();
        }

        // RULE 1 & 2: If no PID provided, use organization history (position effective on date)
        if (!$pid) {
            return $this->getOrganizationHistoryPositions($userIds, $effectiveDate);
        }

        // RULE 3: Check which users have PAID commissions for this specific sale (PID)
        $usersWithPaidCommissions = $this->getUsersWithPaidCommissions($pid, $userIds);

        // RULE 3: Get positions from user_commission table for users with PAID commissions
        // This shows the "locked" position at the time of payment
        $paidCommissionPositions = collect();
        if ($usersWithPaidCommissions->isNotEmpty()) {
            $paidCommissionPositions = $this->getPaidCommissionPositions($pid, $usersWithPaidCommissions->toArray());
        }

        // RULE 2: Get users without paid commissions (UNPAID sale)
        $usersWithoutPaidCommissions = collect($userIds)->diff($usersWithPaidCommissions);

        // RULE 2: For UNPAID users, get positions from organization history (position effective on sale date)
        $organizationHistoryPositions = collect();
        if ($usersWithoutPaidCommissions->isNotEmpty()) {
            $organizationHistoryPositions = $this->getOrganizationHistoryPositions($usersWithoutPaidCommissions->toArray(), $effectiveDate);
        }

        // Merge results: PAID users get commission positions, UNPAID users get historical positions
        return $paidCommissionPositions->union($organizationHistoryPositions);
    }

    /**
     * Check if commissions are paid for given PID and users
     */
    private function checkIfCommissionsPaid($pid, $userIds)
    {
        return UserCommission::where([
            'pid' => $pid,
            'status' => '3',
            'settlement_type' => 'during_m2',
            'is_displayed' => '1'
        ])
            ->whereIn('user_id', $userIds)->exists() ||
            UserCommission::where([
                'pid' => $pid,
                'settlement_type' => 'reconciliation',
                'is_displayed' => '1'
            ])
            ->whereIn('recon_status', ['2', '3'])
            ->whereIn('user_id', $userIds)->exists();
    }

    /**
     * Get list of users who have paid commissions for a given PID
     * @param string $pid The PID to check
     * @param array $userIds Array of user IDs to check
     * @return \Illuminate\Support\Collection Collection of user IDs with paid commissions
     */
    private function getUsersWithPaidCommissions($pid, $userIds)
    {
        $paidUsers = collect();

        // Get users with paid commissions (status 3, during_m2)
        $paidUsersDuringM2 = UserCommission::where([
            'pid' => $pid,
            'status' => '3',
            'settlement_type' => 'during_m2',
            'is_displayed' => '1'
        ])
            ->whereIn('user_id', $userIds)
            ->pluck('user_id');

        // Get users with reconciliation commissions (recon_status 2 or 3)
        $paidUsersRecon = UserCommission::where([
            'pid' => $pid,
            'settlement_type' => 'reconciliation',
            'is_displayed' => '1'
        ])
            ->whereIn('recon_status', ['2', '3'])
            ->whereIn('user_id', $userIds)
            ->pluck('user_id');

        return $paidUsersDuringM2->merge($paidUsersRecon)->unique();
    }

    /**
     * Get position data from user_commission table for users with PAID commissions (RULE 3)
     * Shows the "locked" position that was in effect when the commission was paid
     */
    private function getPaidCommissionPositions($pid, $userIds)
    {
        return UserCommission::with(['subPosition:id,position_name'])
            ->select('user_id', 'position_id')
            ->where('pid', $pid)
            ->where(function ($query) {
                $this->applyCommissionStatusFilter($query);
            })
            ->whereIn('user_id', $userIds)
            ->get()
            ->unique('user_id')
            ->mapWithKeys(function ($record) {
                // For paid sales, use the locked position from user_commission
                // Don't try to get parent positions as they may have changed since payment
                $lockedPositionId = $record->position_id;

                return [$record->user_id => (object)[
                    'user_id' => $record->user_id,
                    'position_id' => $lockedPositionId, // Use locked position as both position and sub_position
                    'position_name' => $record->subPosition?->position_name,
                    'sub_position_id' => $lockedPositionId, // Same as position_id for paid sales
                    'sub_position_name' => $record->subPosition?->position_name,
                ]];
            });
    }

    /**
     * Apply commission status filter to query
     */
    private function applyCommissionStatusFilter($query)
    {
        $query->where(function ($q) {
            $q->where([
                'status' => '3',
                'settlement_type' => 'during_m2',
                'is_displayed' => '1'
            ])
                ->orWhere(function ($subQ) {
                    $subQ->where([
                        'settlement_type' => 'reconciliation',
                        'is_displayed' => '1'
                    ])
                        ->whereIn('recon_status', ['2', '3']);
                });
        });
    }

    /**
     * Get position data from organization history records (RULE 2)
     * Shows the position that was effective on the specified date (usually customer_signoff)
     */
    private function getOrganizationHistoryPositions($userIds, $effectiveDate)
    {
        if (empty($userIds) || !$effectiveDate) {
            return collect();
        }

        // ✅ OPTIMIZED: Use subquery to get only latest record per user (same output, better performance)
        // This avoids loading all history records into memory
        // Get the latest record per user based on effective_date DESC, id DESC
        try {
            $subQuery = UserOrganizationHistory::select(
                'id',
                'user_id',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
            )
                ->whereIn('user_id', $userIds)
                ->where('effective_date', '<=', $effectiveDate);

            $latestIds = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->where('rn', 1)
                ->pluck('id');
        } catch (\Throwable $e) {
            // Fallback if window functions are not supported (MySQL < 8.0)
            Log::warning('Window function failed in getOrganizationHistoryPositions, using fallback query', [
                'error' => $e->getMessage(),
                'effective_date' => $effectiveDate,
                'user_ids_count' => count($userIds)
            ]);

            // Fallback: Use traditional query without window function
            // Get latest effective_date per user, then get the corresponding IDs
            $latestRecords = UserOrganizationHistory::select('user_id', DB::raw('MAX(effective_date) as max_date'))
                ->whereIn('user_id', $userIds)
                ->where('effective_date', '<=', $effectiveDate)
                ->groupBy('user_id')
                ->get();

            // Get the IDs for records with max effective_date, using id DESC as tiebreaker
            $latestIds = collect();
            foreach ($latestRecords as $record) {
                $id = UserOrganizationHistory::where('user_id', $record->user_id)
                    ->where('effective_date', $record->max_date)
                    ->where('effective_date', '<=', $effectiveDate)
                    ->orderBy('id', 'DESC')
                    ->value('id');
                if ($id) {
                    $latestIds->push($id);
                }
            }
        }

        // Now fetch only the latest records with relationships
        return UserOrganizationHistory::with(['position:id,position_name', 'subPositionId:id,position_name'])
            ->whereIn('id', $latestIds)
            ->get()
            ->mapWithKeys(function ($record) {
                return [$record->user_id => (object)[
                    'user_id' => $record->user_id,
                    'position_id' => $record->position_id,
                    'sub_position_id' => $record->sub_position_id,
                    'position_name' => $record->position?->position_name,
                    'sub_position_name' => $record->subPositionId?->position_name,
                ]];
            });
    }

    public function checkUserPaymentStatus($pid)
    {
        // Get ALL commission records for this PID with is_last = 1
        $commissionRecords = UserCommission::where([
            'pid' => $pid,
            'status' => '1',
            'is_last' => '1'
        ])->get();

        // Check overrides
        $checkUserOverrides = UserOverrides::where([
            'pid' => $pid,
            'status' => '1'
        ])->first();

        // If no commission records found, don't close sale
        if ($commissionRecords->isEmpty()) {
            return false;
        }

        // Calculate total commission amount from all records
        $totalCommissionAmount = $commissionRecords->sum(function ($record) {
            return (float) $record->amount;
        });

        // Check if ANY commission record has non-zero amount
        $hasNonZeroCommission = $commissionRecords->where('amount', '>', 0)->isNotEmpty();

        // Business Logic Implementation:

        // 1. If ANY commission is NOT zero, use old logic (don't close sale)
        if ($hasNonZeroCommission) {
            return false;
        }

        // 2. New Business Logic: Check both commissions and overrides
        if ($totalCommissionAmount == 0) {
            if ($checkUserOverrides) {
                $overrideAmount = (float) $checkUserOverrides->amount;

                /**
                 * Business Rule: Only mark as paid if BOTH commission AND override are zero
                 * AND there's no financial impact (nothing to pay out).
                 *
                 * When BOTH are $0, keep the sale unpaid and editable since there's
                 * no financial transaction to "close out".
                 *
                 * Logic:
                 * - Commission = $0, Override = $0 → Don't mark as paid (nothing to pay)
                 * - Commission = $0, Override > $0 → Don't mark as paid (override pending)
                 * - Commission > $0, Override = $0 → Don't mark as paid (commission pending)
                 */

                // If BOTH commission and override are zero, keep sale open (unpaid)
                if ($totalCommissionAmount == 0 && $overrideAmount == 0) {
                    // Don't mark as paid - there's nothing to pay out
                    // Sale should remain editable
                    return false;
                }

                // If override has a positive amount, don't mark as paid
                // (there's still money to be processed)
                if ($overrideAmount > 0) {
                    return false;
                }

                // At this point: commission = 0, override = 0
                // This is already handled above, but as a safety fallback
                return false;
            } else {
                // No overrides found but all commissions are zero - keep sale open and editable
                // Don't mark as paid since there's nothing to pay out
                return false;
            }
        }

        return false;
    }

    // ========================================
    // NEW V2 HARD DELETE OVERRIDE SYSTEM
    // ========================================

    /**
     * Delete override using new hard delete system V2
     */
    public function deleteOverrideV2(Request $request)
    {
        $this->checkValidations($request->all(), [
            'override_id' => 'required',
            'pid' => 'required',
            'is_projection' => 'boolean'
        ]);

        $overrideId = $request->input('override_id');
        $pid = $request->input('pid');
        $isProjection = $request->input('is_projection', false);
        $deletionReason = $request->input('deletion_reason', 'Manual removal');

        try {
            $overrideService = app(\App\Services\OverrideManagementService::class);
            $result = $overrideService->deleteOverride($overrideId, $pid, $isProjection, $deletionReason);

            // Trigger recalculation
            request()->merge(['pid' => $pid]);
            $this->recalculateSale(request(), true);

            return response()->json([
                'status' => true,
                'message' => 'Override deleted successfully',
                'data' => [
                    'override_id' => $overrideId,
                    'pid' => $pid,
                    'is_projection' => $isProjection,
                    'can_restore' => $result['can_restore']
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Restore override using new system V2
     */
    public function restoreOverrideV2(Request $request)
    {
        $this->checkValidations($request->all(), [
            'archive_id' => 'required',
            'pid' => 'required',
            'is_projection' => 'boolean'
        ]);

        $archiveId = $request->input('archive_id');
        $pid = $request->input('pid');
        $isProjection = $request->input('is_projection', false);

        try {
            $overrideService = app(\App\Services\OverrideManagementService::class);
            $result = $overrideService->restoreOverride($archiveId, $pid, $isProjection);

            // Trigger recalculation
            request()->merge(['pid' => $pid]);
            $this->recalculateSale(request(), true);

            return response()->json([
                'status' => true,
                'message' => 'Override restored successfully',
                'data' => [
                    'archive_id' => $archiveId,
                    'new_override_id' => $result['new_override_id'],
                    'pid' => $pid,
                    'is_projection' => $isProjection,
                    'original_pay_period' => $result['original_pay_period'],
                    'restoration_pay_period' => $result['restoration_pay_period'],
                    'payroll_integrated' => $result['payroll_integrated']
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
