<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\ProjectionUserCommission;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ProjectionUserOverrides;
use App\Models\UserOverrides;

class SalesReportController extends BaseController
{
    public function salesContracts(Request $request): JsonResponse
    {
        $endDate = '';
        $startDate = '';
        $salesPid = [];
        $officeId = $request->office_id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = $request->milestone_date ?? false;
        $sale_type = $request->sale_type ?? '';

        // Validate office_id exists if not 'all'
        if ($officeId != 'all') {
            $validOffice = \App\Models\Locations::where('id', $officeId)
                ->whereNull('archived_at')
                ->exists();

            if (! $validOffice) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid office ID: {$officeId}. Office not found or archived.",
                    'data' => [],
                ], 400);
            }
        }

        // Build salesPid array with better error handling
        if ($officeId != 'all') {
            if (! empty($request->user_id)) {
                $userId = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
            }

            // Log for debugging
            \Log::info("Office {$officeId} has ".$userId->count().' users');

            if ($userId->isNotEmpty()) {
                $salesPid = SaleMasterProcess::where(function ($query) use ($userId) {
                    $query->whereIn('closer1_id', $userId)
                        ->orWhereIn('closer2_id', $userId)
                        ->orWhereIn('setter1_id', $userId)
                        ->orWhereIn('setter2_id', $userId);
                })->pluck('pid');
            } else {
                $salesPid = collect(); // No users in this office
            }
        } else {
            if (! empty($request->user_id)) {
                $userId = User::where('id', $request->input('user_id'))->pluck('id');
                $salesPid = SaleMasterProcess::where(function ($query) use ($userId) {
                    $query->whereIn('closer1_id', $userId)
                        ->orWhereIn('closer2_id', $userId)
                        ->orWhereIn('setter1_id', $userId)
                        ->orWhereIn('setter2_id', $userId);
                })->pluck('pid');
            } else {
                $salesPid = collect(); // no filter — will not apply pid condition
            }
        }

        // Log for debugging
        \Log::info("Office {$officeId} filtering resulted in ".$salesPid->count().' sales PIDs');

        $companyProfile = CompanyProfile::first();

        // For PEST companies only: Get filtered user IDs and paid commission PIDs
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // Get filtered user IDs for commission calculations
            if ($officeId != 'all') {
                if (! empty($request->user_id)) {
                    $filteredUserIds = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
                } else {
                    $filteredUserIds = User::where('office_id', $officeId)->pluck('id');
                }
            } else {
                if (! empty($request->user_id)) {
                    $filteredUserIds = User::where('id', $request->input('user_id'))->pluck('id');
                } else {
                    $filteredUserIds = collect(); // No specific user filter
                }
            }

            // Get PIDs with paid commissions (status = 3) - CRITICAL for Total Value Serviced calculation
            // Must filter by user_id when office/user filtering is applied
            $paidCommissionQuery = UserCommission::where('status', 3)->where('amount', '>', 0);
            if ($filteredUserIds->isNotEmpty()) {
                $paidCommissionQuery->whereIn('user_id', $filteredUserIds);
            }
            $paidCommissionPids = $paidCommissionQuery->distinct()->pluck('pid');
        } else {
            // For non-PEST companies, we'll calculate $filteredUserIds later for commission calculation only
            $filteredUserIds = collect();
        }

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // GROSS AMOUNT INSTALLED - All sales (no service date or cancellation filtering)
            $totalKwInstalled = SalesMaster::query()
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->when(! empty($request->user_id) && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when(! empty($request->user_id) && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for user with no sales
                })->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })
                ->where(function ($query) {
                    // Exclude deals with trigger_date = '[{"date":null}]'
                    $query->whereNull('trigger_date')
                        ->orWhere('trigger_date', '!=', '[{"date":null}]');
                })
                ->sum('gross_account_value');
            $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();
            
            // TOTAL VALUE PENDING - Sales that HAVE m1_date milestone and are NOT cancelled
            $totalKwPending = SalesMaster::whereNull('date_cancelled')
                ->whereNotNull('m1_date') // Must HAVE m1_date milestone
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->when(! empty($request->user_id) && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when(! empty($request->user_id) && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for user with no sales
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })
                ->sum('gross_account_value');
        } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            // INSTALLED - FIXED OFFICE FILTERING FOR TOTAL VALUE SOLD
            $totalKwInstalled = SalesMaster::whereNull('date_cancelled')
                ->whereHas('salesProductMasterDetails', function ($q) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date');
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->sum('gross_account_value');

            // PENDING - FIXED OFFICE FILTERING FOR TOTAL VALUE SOLD
            $totalKwPending = SalesMaster::whereNull('date_cancelled')
                ->whereHas('salesProductMasterDetails', function ($q) {
                    $q->where('is_last_date', '1')->whereNull('milestone_date');
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->sum('gross_account_value');
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
            // INSTALLED - FIXED OFFICE FILTERING FOR TOTAL VALUE SOLD
            $totalKwInstalled = SalesMaster::whereNull('date_cancelled')
                ->whereHas('salesProductMasterDetails', function ($q) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date');
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->sum('gross_account_value');

            // PENDING - FIXED OFFICE FILTERING FOR TOTAL VALUE SOLD
            $totalKwPending = SalesMaster::whereNull('date_cancelled')
                ->whereHas('salesProductMasterDetails', function ($q) {
                    $q->where('is_last_date', '1')->whereNull('milestone_date');
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->sum('gross_account_value');
        } else {
            // KW INSTALLED - ENSURE OFFICE FILTERING WORKS
            $totalKwInstalled = SalesMaster::whereNull('date_cancelled')
                ->whereHas('salesProductMasterDetails', function ($q) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date');
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->when($officeId == 'all' && ! empty($request->user_id) && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->sum('kw');

            // KW PENDING - ENSURE OFFICE FILTERING WORKS
            $totalKwPending = SalesMaster::whereNull('date_cancelled')
                ->whereHas('salesProductMasterDetails', function ($q) {
                    $q->where('is_last_date', '1')->whereNull('milestone_date');
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all' && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when($officeId != 'all' && $salesPid->isEmpty(), function ($q) {
                    $q->where('id', -1); // Force zero results for empty office
                })
                ->when($officeId == 'all' && ! empty($request->user_id) && $salesPid->isNotEmpty(), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->sum('kw');
        }

        // Calculate total sales PIDs for commission calculation
        if ($officeId != 'all' && $salesPid->isEmpty()) {
            $totalSales = collect([]);
        } else {
            // Include ALL sales (active + cancelled) to match paystub Commission YTD logic
            $totalSales = SalesMaster::query()
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })
                ->when($officeId != 'all', function ($q) use ($salesPid) {
                    if ($salesPid->isNotEmpty()) {
                        $q->whereIn('pid', $salesPid);
                    } else {
                        $q->where('id', 0); // No sales for this office
                    }
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    if ($salesPid->isNotEmpty()) {
                        $q->whereIn('pid', $salesPid);
                    } else {
                        $q->where('id', 0); // No sales for this user
                    }
                })
                ->pluck('pid');
        }

        // Log commission calculation data
        \Log::info("Commission calculation for office {$officeId}: ".$totalSales->count().' sales PIDs');

        // Get filtered user IDs for commission calculations (for non-PEST companies)
        if (!in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($officeId != 'all') {
                if (! empty($request->user_id)) {
                    $filteredUserIds = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
                } else {
                    $filteredUserIds = User::where('office_id', $officeId)->pluck('id');
                }
            } else {
                if (! empty($request->user_id)) {
                    $filteredUserIds = User::where('id', $request->input('user_id'))->pluck('id');
                } else {
                    $filteredUserIds = collect(); // No specific user filter
                }
            }
        }

        // TOTAL PROJECTED OVERRIDES with proper user filtering
        $projectedOverridesQuery = $totalSales->isNotEmpty() ? ProjectionUserOverrides::whereIn('pid', $totalSales) : ProjectionUserOverrides::where('id', 0);
        if (! empty($request->user_id) && $filteredUserIds->isNotEmpty()) {
            $projectedOverridesQuery->whereIn('user_id', $filteredUserIds);
        }
        $projectedOverrides = $projectedOverridesQuery->sum('overrides_amount');

        // TOTAL PROJECTED COMMISSION with proper user filtering (using $filteredUserIds from above)
        $projectedCommissionQuery = $totalSales->isNotEmpty() ? ProjectionUserCommission::whereIn('pid', $totalSales) : ProjectionUserCommission::where('id', 0);
        if (! empty($request->user_id) && $filteredUserIds->isNotEmpty()) {
            $projectedCommissionQuery->whereIn('user_id', $filteredUserIds);
        }
        $projectedCommission = $projectedCommissionQuery->sum('amount');

        // TOTAL PAID COMMISSION with proper user filtering
        $commissionQuery = $totalSales->isNotEmpty() ? UserCommission::whereIn('pid', $totalSales)->where('status', '3') : UserCommission::where('id', 0);
        if (! empty($request->user_id) && $filteredUserIds->isNotEmpty()) {
            $commissionQuery->whereIn('user_id', $filteredUserIds);
        }
        $commission = $commissionQuery->sum('amount');

        $clawBackQuery = $totalSales->isNotEmpty() ? ClawbackSettlement::whereIn('pid', $totalSales)->where(['type' => 'commission', 'status' => '3']) : ClawbackSettlement::where('id', 0);
        if (! empty($request->user_id) && $filteredUserIds->isNotEmpty()) {
            $clawBackQuery->whereIn('user_id', $filteredUserIds);
        }
        $clawBack = $clawBackQuery->sum('clawback_amount');

        // CALCULATED COMMISSIONS & OVERRIDES (only for mortgagestage domain)
        // NOTE: This calculates ALL commissions and overrides for the filtered sales PIDs
        // It does NOT filter by user_id - the PIDs are already filtered based on user involvement
        $calculatedCommissionsAndOverrides = 0;
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            // Total calculated commissions from user_commission table (ALL users for these PIDs)
            $calculatedCommissions = $totalSales->isNotEmpty()
                ? UserCommission::whereIn('pid', $totalSales)->sum('amount')
                : 0;

            // Total calculated overrides from user_overrides table (ALL users for these PIDs)
            $calculatedOverrides = $totalSales->isNotEmpty()
                ? UserOverrides::whereIn('pid', $totalSales)->where('is_displayed', '1')->sum('amount')
                : 0;

            // Total commission clawbacks (ALL users for these PIDs)
            $calculatedCommissionClawback = $totalSales->isNotEmpty()
                ? ClawbackSettlement::whereIn('pid', $totalSales)->where('type', 'commission')->sum('clawback_amount')
                : 0;

            // Total override clawbacks (ALL users for these PIDs)
            $calculatedOverrideClawback = $totalSales->isNotEmpty()
                ? ClawbackSettlement::whereIn('pid', $totalSales)->where('type', 'overrides')->where('is_displayed', '1')->sum('clawback_amount')
                : 0;

            // Calculate total: (commissions - commission_clawbacks) + (overrides - override_clawbacks)
            $calculatedCommissionsAndOverrides = ($calculatedCommissions - $calculatedCommissionClawback) + ($calculatedOverrides - $calculatedOverrideClawback) + ($projectedCommission + $projectedOverrides);
        }

        $data['contracts'] = [
            'total_kw_installed' => $totalKwInstalled,
            'total_kw_pending' => $totalKwPending,
            'paid_comissions' => ($commission - $clawBack),
            'projected_comissions' => $projectedCommission,
            'total_commissions_overrides' => $calculatedCommissionsAndOverrides,
        ];

        $this->successResponse('Successfully.', 'salesContracts', $data);
    }

    public function salesInstallRatio(Request $request)
    {
        $endDate = '';
        $startDate = '';
        $salesPid = [];
        $officeId = $request->office_id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = $request->milestone_date ?? false;
        $sale_type = $request->sale_type ?? '';

        if ($officeId != 'all') {
            if (! empty($request->user_id)) {
                $userId = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
            }
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        } else {
            if (! empty($request->user_id)) {
                $userId = User::where('id', $request->input('user_id'))->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
            } else {
                $salesPid = collect(); // no filter — will not apply pid condition
            }
        }

        $companyProfile = CompanyProfile::first();
        $totalSales = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
        })
            ->when($officeId != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->count();

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // $m2Complete = SalesMaster::whereNull('date_cancelled')
            //     ->when(!empty($startDate), function ($q) use ($startDate, $endDate, $initial_service_date) {
            //         if (!$initial_service_date){
            //             $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            //         }  else {
            //             $q->whereHas('salesProductMaster', function ($q) use ($startDate, $endDate) {
            //                 $q->whereBetween('milestone_date', [$startDate, $endDate])
            //                 ->whereHas('milestoneSchemaTrigger', function ($q) {
            //                     $q->where('name', 'Initial Service Date');
            //                 });
            //             });
            //         }
            //     })
            //     ->when($officeId != 'all' && !empty($salesPid), function ($q) use ($salesPid) {
            //         $q->whereIn('pid', $salesPid);
            //     })
            //     ->when((!empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
            //         $q->whereIn('pid', $salesPid);
            //     })
            //     ->where(function($query) {
            //         $query->whereNotNull('m1_date')
            //               ->orWhereNotNull('m2_date');
            //     })
            //     ->count();
            $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();
            $m2Complete = SalesMaster::whereNull('date_cancelled')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })
                ->when($officeId != 'all' && ! empty($salesPid), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->where(function ($query) {
                    $query->whereNotNull('m1_date')
                        ->orWhereNotNull('m2_date');
                })
                ->count();
        } else {
            $m2Complete = SalesMaster::whereNull('date_cancelled')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all' && ! empty($salesPid), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->where(function ($query) {
                    $query->whereNotNull('m1_date')
                        ->orWhereNotNull('m2_date');
                })
                ->count();
        }

        if ($m2Complete > 0 && $totalSales > 0) {
            $install = round((($m2Complete / $totalSales) * 100), 5);
            $data['install_ratio'] = [
                'install' => $install.'%',
                'uninstall' => round(100 - $install, 5).'%',
                'total' => $totalSales,
                'm2_complete' => $m2Complete,
            ];
        } else {
            $data['install_ratio'] = [
                'install' => '0%',
                'uninstall' => '100%',
            ];
        }

        $this->successResponse('Successfully.', 'salesInstallRatio', $data);
    }

    public function salesBestAverage(Request $request)
    {
        $endDate = '';
        $startDate = '';
        $salesPid = [];
        $officeId = $request->office_id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = $request->milestone_date ?? false;
        $sale_type = $request->sale_type ?? '';

        if ($officeId != 'all') {
            if (! empty($request->user_id)) {
                $userId = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
            }
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        } else {
            if (! empty($request->user_id)) {
                $userId = User::where('id', $request->input('user_id'))->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
            } else {
                $salesPid = collect(); // no filter — will not apply pid condition
            }
        }

        $companyProfile = CompanyProfile::first();
        $totalSales = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $companyProfile, $milestone_date) {
            if (! (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) && $milestone_date)) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            } else {
                $q->whereHas('salesProductMaster', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('milestone_date', [$startDate, $endDate])
                        ->whereHas('milestoneSchemaTrigger', function ($q) {
                            $q->where('name', 'Initial Service Date');
                        });
                });
            }
        })->when($officeId != 'all' && ! empty($salesPid), function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->count();

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $totalKw = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
            })->when($officeId != 'all' && ! empty($salesPid), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->sum('gross_account_value');
            $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthName(customer_signoff) month, sum(cast(gross_account_value as decimal(10, 2))) As kw')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('month')->orderByDesc('gross_account_value')->first();

            $bestWeek = SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(gross_account_value as decimal(10, 2))) As kw,
                STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startWeek,
                addDate(STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endWeek")
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('week')->orderByDesc('gross_account_value')->first();

            $bsDate = isset($bestWeek->startWeek) ? $bestWeek->startWeek : null;
            $beDate = isset($bestWeek->endWeek) ? $bestWeek->endWeek : null;
            $bestWeek['date'] = [$bsDate, $beDate];

            $bestDay = SalesMaster::select(DB::raw('sum(cast(gross_account_value as decimal(10, 2))) As kw'), 'customer_signoff as date')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('customer_signoff')->orderByDesc('gross_account_value')->first();
        } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $totalKw = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($officeId != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->sum('gross_account_value');

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthName(customer_signoff) month, sum(cast(gross_account_value as decimal(10, 2))) As kw')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('month')->orderByDesc('gross_account_value')->first();

            $bestWeek = SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(gross_account_value as decimal(10, 2))) As kw,
                STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startWeek,
                addDate(STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endWeek")
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('week')->orderByDesc('gross_account_value')->first();

            $bsDate = isset($bestWeek->startWeek) ? $bestWeek->startWeek : null;
            $beDate = isset($bestWeek->endWeek) ? $bestWeek->endWeek : null;
            $bestWeek['date'] = [$bsDate, $beDate];

            $bestDay = SalesMaster::select(DB::raw('sum(cast(gross_account_value as decimal(10, 2))) As kw'), 'customer_signoff as date')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('customer_signoff')->orderByDesc('gross_account_value')->first();
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
            $totalKw = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($officeId != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->sum('gross_account_value');

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthName(customer_signoff) month, sum(cast(gross_account_value as decimal(10, 2))) As kw')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('month')->orderByDesc('gross_account_value')->first();

            $bestWeek = SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(gross_account_value as decimal(10, 2))) As kw,
                STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startWeek,
                addDate(STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endWeek")
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('week')->orderByDesc('gross_account_value')->first();

            $bsDate = isset($bestWeek->startWeek) ? $bestWeek->startWeek : null;
            $beDate = isset($bestWeek->endWeek) ? $bestWeek->endWeek : null;
            $bestWeek['date'] = [$bsDate, $beDate];

            $bestDay = SalesMaster::select(DB::raw('sum(cast(gross_account_value as decimal(10, 2))) As kw'), 'customer_signoff as date')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('customer_signoff')->orderByDesc('gross_account_value')->first();
        } else {
            $totalKw = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($officeId != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->sum('kw');

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthName(customer_signoff) month, sum(cast(kw as decimal(10, 2))) As kw')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('month')->orderByDesc('kw')->first();

            $bestWeek = SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(kw as decimal(10, 2))) As kw,
                STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startWeek,
                addDate(STR_TO_DATE(concat(year(customer_signoff), week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endWeek")
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('week')->orderByDesc('kw')->first();

            $bsDate = isset($bestWeek->startWeek) ? $bestWeek->startWeek : null;
            $beDate = isset($bestWeek->endWeek) ? $bestWeek->endWeek : null;
            $bestWeek['date'] = [$bsDate, $beDate];

            $bestDay = SalesMaster::select(DB::raw('sum(cast(kw as decimal(10, 2))) As kw'), 'customer_signoff as date')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('customer_signoff')->orderByDesc('kw')->first();
        }

        $totalReps = User::where('is_super_admin', '!=', 1)->count();
        $data['best_avg'] = [
            'bestDay' => $bestDay,
            'bestWeek' => $bestWeek,
            'bestMonth' => $bestMonth,
            'avg_account_per_rep' => ($totalSales && $totalReps) ? round($totalSales / $totalReps, 3) : 0,
            'avg_kw_per_rep' => ($totalKw && $totalReps) ? round($totalKw / $totalReps, 3) : 0,
        ];

        $this->successResponse('Successfully.', 'salesBestAverage', $data);
    }

    public function salesAccount(Request $request)
    {
        $endDate = '';
        $startDate = '';
        $salesPid = [];
        $officeId = $request->office_id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = $request->milestone_date ?? false;
        $sale_type = $request->sale_type ?? '';

        if ($officeId != 'all') {
            if (! empty($request->user_id)) {
                $userId = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
            }

            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        } else {
            if (! empty($request->user_id)) {
                $userId = User::where('id', $request->input('user_id'))->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
            } else {
                $salesPid = collect(); // no filter — will not apply pid condition
            }
        }

        $companyProfile = CompanyProfile::first();

        // For PEST companies only: Get filtered user IDs and paid commission PIDs
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // Get filtered user IDs for filtering paid commissions
            if ($officeId != 'all') {
                if (! empty($request->user_id)) {
                    $filteredUserIds = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
                } else {
                    $filteredUserIds = User::where('office_id', $officeId)->pluck('id');
                }
            } else {
                if (! empty($request->user_id)) {
                    $filteredUserIds = User::where('id', $request->input('user_id'))->pluck('id');
                } else {
                    $filteredUserIds = collect(); // No specific user filter
                }
            }

            // Get PIDs with paid commissions (status = 3) - CRITICAL for Accounts Serviced calculation
            // Must filter by user_id when office/user filtering is applied
            $paidCommissionQuery = UserCommission::where('status', 3)->where('amount', '>', 0);
            if ($filteredUserIds->isNotEmpty()) {
                $paidCommissionQuery->whereIn('user_id', $filteredUserIds);
            }
            $paidCommissionPids = $paidCommissionQuery->distinct()->pluck('pid');
        }

        $totalSales = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
        })->when($officeId != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })
            ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->count();

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // ACCOUNTS SERVICED - Deals with m1_date or m2_date milestone completed
            $m2Complete = SalesMaster::whereNull('date_cancelled')
                //->whereIn('pid', $paidCommissionPids) // CRITICAL: Only include deals with paid commissions
                ->where(function ($query) {
                    $query->whereNotNull('m1_date')
                        ->orWhereNotNull('m2_date');
                })
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })
                ->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->where(function ($query) {
                    // Exclude deals with trigger_date = '[{"date":null}]'
                    $query->whereNull('trigger_date')
                        ->orWhere('trigger_date', '!=', '[{"date":null}]');
                })
                ->count();

            $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();
            $m2Pending = SalesMaster::whereNull('date_cancelled')
                ->whereNotNull('customer_signoff')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
                })
                ->where(function ($query) {
                    $query->where(function ($q) {
                        // Excel sales with NULL or invalid trigger_date
                        $q->where('data_source_type', 'excel')
                            ->where(function ($subq) {
                                $subq->whereNull('trigger_date')
                                    ->orWhereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                            });
                    })->orWhere(function ($q) {
                        $q->where('data_source_type', '!=', 'excel');
                    });
                })
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('m1_date')
                            ->whereNull('m2_date');
                    });
                })
                ->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->count();

        } else {
            $m2Complete = SalesMaster::whereNull('date_cancelled')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->where(function ($query) {
                    $query->whereNotNull('m1_date')
                        ->orWhereNotNull('m2_date');
                })
                ->count();

            $m2Pending = SalesMaster::whereNull('date_cancelled')
                ->whereNotNull('customer_signoff')
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->when($officeId != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })
                ->where(function ($query) {
                    $query->whereNull('m1_date')
                        ->whereNull('m2_date');
                })
                ->count();
        }

        $clawBackPid = ClawbackSettlement::whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
        $cancelled = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
        })->when($officeId != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })
            ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->whereNotNull('date_cancelled')->whereNotIn('pid', $clawBackPid)->count();

        $clawBack = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
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
        })->when($officeId != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })
            ->when((! empty($request->user_id) && ($officeId == 'all')), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->whereNotNull('date_cancelled')->whereIn('pid', $clawBackPid)->count();

        $data['accounts'] = [
            'total_sales' => $totalSales,
            'm2_complete' => $m2Complete,
            'm2_pending' => $m2Pending,
            'cancelled' => $cancelled,
            'clawback' => $clawBack,
        ];

        $this->successResponse('Successfully.', 'salesAccount', $data);
    }
}
