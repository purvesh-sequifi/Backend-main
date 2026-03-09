<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Core\Traits\SaleTraits\EditSaleTrait;
use App\Exports\ExportReportMySalesStandard;
use App\Exports\ExportReportSales;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\LegacyApiNullData;
use App\Models\MilestoneSchema;
use App\Models\MilestoneSchemaTrigger;
use App\Models\OnboardingEmployeeAdditionalOverride;
use App\Models\OnboardingEmployeeOverride;
use App\Models\OnboardingEmployeeRedline;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingEmployeeUpfront;
use App\Models\OnboardingEmployeeWithheld;
use App\Models\OnboardingUserRedline;
use App\Models\payFrequencySetting;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionPayFrequency;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionWage;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Models\ProjectionUserCommission;
use App\Models\SaleMasterProcess;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\SchemaTriggerDate;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserAgreementHistory;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionLock;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserRedlines;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class SalesStandardController extends BaseController
{
    use EditSaleTrait;

    /**
     * Helper method to check if trigger_date JSON field has valid date
     *
     * @param  string|null  $triggerDate  JSON string of trigger dates
     * @return bool Whether the trigger date is valid or not
     */
    protected function hasValidTriggerDate(?string $triggerDate): bool
    {
        if (empty($triggerDate)) {
            return false;
        }

        try {
            $triggerDates = json_decode($triggerDate, true);

            if (! is_array($triggerDates) || empty($triggerDates)) {
                return false;
            }

            foreach ($triggerDates as $item) {
                if (isset($item['date']) && $item['date'] != 'null' && $item['date'] != '') {
                    // Check if it's a valid date format YYYY-MM-DD
                    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $item['date'])) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            // If any error in parsing JSON, consider it invalid
            return false;
        }
    }

    public function reportSalesList(Request $request)
    {
        $userId = $request->user_id;
        $officeId = $request->office_id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = filter_var($request->milestone_date, FILTER_VALIDATE_BOOLEAN);
        $sale_type = $request->sale_type ?? '';
        if ($officeId == 'all') {
            if (! empty($userId)) {
                $userId = User::where('id', $request->input('user_id'))->pluck('id');
            } else {
                $userId = User::pluck('id');
            }
        } else {
            if (! empty($userId)) {
                $userId = User::where('office_id', $officeId)->where('id', $userId)->pluck('id');
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
            }
        }

        // Check if FieldRoutes integration is active
        $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

        $pid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid')->toArray();
        $result = SalesMaster::with(['lastMilestone', 'productInfo' => function ($q) {
            $q->withTrashed();
        }, 'salesProductMaster.milestoneSchemaTrigger', 'salesProductMaster' => function ($q) {
            $q->selectRaw('pid, type as name, SUM(amount) as value, MAX(milestone_date) as date, is_projected, milestone_schema_id')->groupBy('pid', 'type');
        }])->whereIn('pid', $pid)
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('date_cancelled', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('customer_state', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('net_epc', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('job_status', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
                });
            })->when($request->filled('filter_product'), function ($q) use ($request) {
                $filterProduct = $request->input('filter_product');

                if ($filterProduct == 1) {
                    // Match both product_id = 1 and product_id IS NULL
                    $q->where(function ($query) use ($filterProduct) {
                        $query->where('product_id', $filterProduct)
                            ->orWhereNull('product_id');
                    });
                } else {
                    // Normal case: match exact product_id
                    $q->where('product_id', $filterProduct);
                }
            })->when($request->filled('location'), function ($q) use ($request) {
                $q->where('customer_state', $request->input('location'));
            })->when($request->filled('filter_install'), function ($q) use ($request) {
                $q->where('install_partner', $request->input('filter_install'));
            })->when($request->filled('filter_status'), function ($q) use ($request) {
                $q->where('job_status', $request->input('filter_status'));
            })->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                        // For installed sales, consider FieldRoutes integration if active
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where('initialStatusText', '=', 'Completed')
                                    ->orWhere(function ($sq) {
                                        $sq->whereNull('initialStatusText')
                                            ->where(function ($q) {
                                                $q->whereNotNull('m1_date')
                                                    ->orWhereNotNull('m2_date');
                                            });
                                    })
                                    ->orWhere(function ($sq) {
                                        $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                            ->whereRaw("EXISTS (
                                            SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                            WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                        )");
                                    });
                            });
                        } else {
                            // Traditional logic for installation date
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } elseif ($sale_type == 'Pending') {
                        // For pending sales, consider FieldRoutes integration if active
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where(function ($subquery) {
                                    $subquery->whereNotNull('initialStatusText')
                                        ->where('initialStatusText', '!=', 'Completed');
                                })
                                    ->orWhere(function ($subquery) {
                                        $subquery->whereNull('initialStatusText')
                                            ->whereNull('m1_date')
                                            ->whereNull('m2_date');
                                    })
                                    ->orWhere(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->where(function ($q2) {
                                                $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                            });
                                    });
                            })
                                ->where(function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                    })->orWhere(function ($q) {
                                        $q->where('data_source_type', '!=', 'excel');
                                    });
                                });
                        } else {
                            // Traditional logic for pending status
                            $q->whereNull('m1_date')->whereNull('m2_date');
                        }
                    } else {
                        // Default milestone handling
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            })->orderBy('id', 'DESC')->orderBy('customer_signoff', 'DESC');
        if (isset($request->is_export) && ($request->is_export == 1)) {
            $data = $result->get();
        } else {
            $perPage = $request->perpage ? $request->perpage : 10;
            $data = $result->paginate($perPage);
        }

        $companyProfile = CompanyProfile::first();
        $pids = $data->pluck('pid')->toArray();
        $clawBackPids = ClawbackSettlement::select('pid')
            ->whereNotNull('pid')
            ->whereIn('pid', $pids)
            ->pluck('pid')
            ->toArray();

        // Get default product for fallback
        $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();

        $data->transform(function ($data) use ($request, $product, $companyProfile, $clawBackPids) {
            $lastPaid = null;
            $lastDisplay = null;
            foreach ($data->lastMilestone as $mileStone) {
                if (! $lastDisplay && $mileStone->milestone_date && ! $mileStone->is_paid) {
                    $amount = collect($data->lastMilestone)->where('type', $mileStone->type)->where('is_paid', 0)->sum('amount');
                    $lastDisplay = [
                        'name' => $mileStone?->milestoneSchemaTrigger?->name,
                        'amount' => $amount ?? 0,
                        'date' => $mileStone->milestone_date,
                    ];
                }
                if ($mileStone->milestone_date && $mileStone->is_paid) {
                    $amount = collect($data->lastMilestone)->where('type', $mileStone->type)->where('is_paid', 1)->sum('amount');
                    $lastPaid = [
                        'name' => $mileStone?->milestoneSchemaTrigger?->name,
                        'amount' => $amount ?? 0,
                        'date' => $mileStone->milestone_date,
                    ];
                }
            }

            // Fetch the active reconciliation setting
            $reconciliationSetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

            // If the setting is active, render and show reconciliation
            $reconAmount = 0;
            if ($reconciliationSetting) {
                $reconCommission = UserCommission::selectRaw('SUM(amount) as amount, pid, user_id, date')->where(['pid' => $data->pid, 'settlement_type' => 'reconciliation', 'user_id' => (! empty($request->user_id) ? $request->user_id : auth()->user()->id)])->first();

                if ($reconCommission) {
                    $data->salesProductMaster[] = [
                        'pid' => $data->pid,
                        'name' => 'Recon',
                        'value' => $reconCommission->amount,
                        'date' => $reconCommission->date,
                        'is_projected' => $isProjected ?? 0,
                    ];
                }
            }

            $lastMilestone = null;
            if ($lastDisplay) {
                $lastMilestone = $lastDisplay;
            } elseif ($lastPaid) {
                $lastMilestone = $lastPaid;
            }

            $dealerFeePer = isset($data->dealer_fee_percentage) ? ($data->dealer_fee_percentage) : null;
            if ($dealerFeePer < 1) {
                $dealerFeePer = (float) $dealerFeePer * 100;
            }

            // Check if salesProductMaster is not empty or null
            if (! empty($data->salesProductMaster)) {
                // Loop through each salesProductMaster item
                foreach ($data->salesProductMaster as $key => $saleProductMaster) {
                    // Check if milestoneSchemaTrigger exists and its name is not empty or null
                    if (! empty($saleProductMaster->milestoneSchemaTrigger) && ! empty($saleProductMaster->milestoneSchemaTrigger->name)) {
                        // Assign milestoneSchemaTrigger name to salesProductMaster name
                        $data->salesProductMaster[$key]->name = $saleProductMaster->milestoneSchemaTrigger->name;
                    }
                }
            }
            $closer_details = $setter_details = [
                'dismiss' => 0,
                'terminate' => 0,
                'contract_ended' => 0,
            ];

            if (isset($data->closer1_id)) {
                $closer_details['dismiss'] = isUserDismisedOn($data->closer1_id, date('Y-m-d')) ? 1 : 0;
                $closer_details['terminate'] = isUserTerminatedOn($data->closer1_id, date('Y-m-d')) ? 1 : 0;
                $closer_details['contract_ended'] = isUserContractEnded($data->closer1_id) ? 1 : 0;
            }
            if (isset($data->setter1_id)) {
                $setter_details['dismiss'] = isUserDismisedOn($data->setter1_id, date('Y-m-d')) ? 1 : 0;
                $setter_details['terminate'] = isUserTerminatedOn($data->setter1_id, date('Y-m-d')) ? 1 : 0;
                $setter_details['contract_ended'] = isUserContractEnded($data->setter1_id) ? 1 : 0;
            }

            // Format product name like in salesList endpoint
            $productName = $data?->productInfo?->product_code ?? $product->name;
            if ($data?->product && $data?->product !== $productName) {
                $productName = $data->product.' - '.$productName;
            }

            $hasClawback = in_array($data->pid, $clawBackPids);

            // Determine installation status considering FieldRoutes integration
            $isInstalled = false;
            $displayJobStatus = $data->job_status; // Default to the stored job_status

            // Calculate first milestone date logic
            $firstMilestoneDate = collect($data->salesProductMaster)
                ->filter(function ($milestone) {
                    return ! empty($milestone->date);
                })
                ->isNotEmpty();
            // Fix: Remove circular dependency on job_status for PEST company status determination
            $firstDate = $firstMilestoneDate && ! $data->date_cancelled;

            // Apply 4-status job system logic only for PEST company type
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($hasClawback) {
                    $displayJobStatus = 'Clawback';
                } elseif ($data->date_cancelled) {
                    $displayJobStatus = 'Cancelled';
                } elseif ($firstDate && ! $data->date_cancelled) {
                    $displayJobStatus = 'Serviced';
                    $isInstalled = true;
                } else {
                    $displayJobStatus = 'Pending';
                }
            } else {
                // Fallback to original job status with null safety for non-PEST companies
                $displayJobStatus = $data->job_status ?? 'Pending';
                if (! is_null($data->m1_date) || ! is_null($data->m2_date)) {
                    $isInstalled = true;
                }
            }

            /*// If not a clawback, proceed with specific checks
            else if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) && $fieldRouteCount > 0) {
                // For PEST companies with FieldRoutes, check initialStatusText or initial_service_date

                // Check if sale is cancelled
                if (!is_null($data->date_cancelled)) {
                    $displayJobStatus = 'Cancelled';
                }
                // Check if installed/serviced
                else if (($data->initialStatusText == 'Completed') ||
                    (is_null($data->initialStatusText) && !is_null($data->initial_service_date)) ||
                    (in_array($data->data_source_type, ['excel', 'randcpest2__field_routes']) &&
                        $this->hasValidTriggerDate($data->trigger_date))
                ) {
                    $isInstalled = true;
                    $displayJobStatus = 'Serviced';
                }
                // Must be pending
                else if ((!is_null($data->initialStatusText) && $data->initialStatusText != 'Completed') ||
                    (is_null($data->initialStatusText) && is_null($data->initial_service_date)) ||
                    ($data->data_source_type == 'excel' && !$this->hasValidTriggerDate($data->trigger_date))
                ) {
                    $displayJobStatus = 'Pending';
                }
                // Fallback
                else {
                    $displayJobStatus = 'Pending';
                }
            } else {
                // Traditional check for other companies
                if (!is_null($data->date_cancelled)) {
                    $displayJobStatus = 'Cancelled';
                } else if (!is_null($data->m1_date) || !is_null($data->m2_date)) {
                    $isInstalled = true;
                }
            } */

            return [
                'pid' => $data->pid,
                'customer_name' => $data->customer_name,
                'state' => $data->customer_state,
                'installer' => $data->install_partner,
                'product' => $productName,
                'closer' => $data->closer1_name,
                'closer_details' => $closer_details,
                'setter' => $data->setter1_name,
                'setter_details' => $setter_details,
                'service_completed' => $data->service_completed,
                'kw' => $data->kw,
                'gross_account_value' => $data->gross_account_value,
                'job_status' => $displayJobStatus,
                'date_cancelled' => $data->date_cancelled,
                'last_milestone' => $lastMilestone,
                'all_milestone' => $data->salesProductMaster,
                'adders' => $data->adders,
                'epc' => $data->epc,
                'net_epc' => $data->net_epc,
                'dealer_fee' => (isset($data->dealer_fee_amount) && is_int($data->dealer_fee_amount)) ? round($data->dealer_fee_amount, 5) : '',
                'dealer_fee_percentage' => round($dealerFeePer, 5),
                'is_installed' => $isInstalled,
            ];
        });

        if (isset($request->is_export) && ($request->is_export == 1)) {
            $fileName = 'sales_customer_list_export_'.date('Y_m_d_H_i_s').'.xlsx';
            Excel::store(new ExportReportSales($data), 'exports/sales/'.$fileName, 'public', \Maatwebsite\Excel\Excel::XLSX);
            $url = getStoragePath('exports/sales/'.$fileName);

            // $url = getExportBaseUrl() . 'storage/exports/sales/' . $fileName;
            return response()->json(['url' => $url]);
        }

        $this->successResponse('Successfully.', 'reportSalesList', $data);
    }

    public function reportSalesGraph(Request $request): JsonResponse
    {
        $data = [];
        $kwType = $request->kw_type ?? 'sold';
        $dateColumn = ($kwType == '' || $kwType == 'sold') ? 'customer_signoff' : 'm2_date';
        $milestone_date = filter_var($request->milestone_date, FILTER_VALIDATE_BOOLEAN);
        $sale_type = $request->sale_type ?? '';

        // Get user IDs based on filters
        $userId = $this->getFilteredUserIds($request);

        // Get PIDs for sales processing
        $pid = SaleMasterProcess::whereIn('closer1_id', $userId)
            ->orWhereIn('closer2_id', $userId)
            ->orWhereIn('setter1_id', $userId)
            ->orWhereIn('setter2_id', $userId)
            ->pluck('pid')
            ->toArray();

        $clawPid = ClawbackSettlement::whereIn('user_id', $userId)->pluck('pid');

        // Get date range
        [$startDate, $endDate] = getDateFromFilter($request);
        $companyProfile = CompanyProfile::first();
        $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

        // Get sales metrics
        $metrics = $this->getSalesMetrics($companyProfile, $pid, $clawPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        // Generate time-based data
        $timeData = $this->generateTimeBasedData(
            $request->input('filter'),
            $companyProfile,
            $pid,
            $clawPid,
            $startDate,
            $endDate,
            $dateColumn,
            $milestone_date,
            $sale_type,
            $fieldRouteCount
        );

        $data['heading_count_kw'] = [
            'largest_system_size' => round($metrics['largestSystemSize']),
            'avg_system_size' => round($metrics['avgSystemSize']),
            'install_kw' => round($metrics['installKw']).'('.$metrics['installCount'].')',
            'pending_kw' => round($metrics['pendingKw']).'('.$metrics['pendingKwCount'].')',
            'clawBack_account' => $metrics['clawBackAccount'].'('.$metrics['clawBackAccountCount'].')',
        ];

        $data['my_sales'] = $timeData;
        $data['kw_type'] = $kwType;

        return response()->json([
            'ApiName' => 'My sales graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    // Helper function to get filtered user IDs
    private function getFilteredUserIds(Request $request)
    {
        $userId = $request->user_id;
        $officeId = $request->office_id;

        if ($officeId == 'all') {
            return ! empty($userId)
                ? User::where('id', $request->input('user_id'))->pluck('id')
                : User::pluck('id');
        }

        return ! empty($userId)
            ? User::where('office_id', $officeId)->where('id', $userId)->pluck('id')
            : User::where('office_id', $officeId)->pluck('id');
    }

    // Helper function to get sales metrics
    private function getSalesMetrics($companyProfile, $pid, $clawPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $isPestCompany = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $valueField = $isPestCompany ? 'gross_account_value' : 'kw';

        // Base query
        $query = SalesMaster::when(! empty($pid), function ($q) use ($pid) {
            $q->whereIn('pid', $pid);
        })->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                    if ($fieldRouteCount > 0) {
                        $q->where(function ($query) {
                            $query->where('initialStatusText', '=', 'Completed')
                                ->orWhere(function ($sq) {
                                    $sq->whereNull('initialStatusText')
                                        ->where(function ($q) {
                                            $q->whereNotNull('m1_date')
                                                ->orWhereNotNull('m2_date');
                                        });
                                })
                                ->orWhere(function ($sq) {
                                    $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                        ->whereRaw("EXISTS (
                                        SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                        WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                    )");
                                });
                        });
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } elseif ($sale_type == 'Pending') {
                    if ($fieldRouteCount > 0) {
                        $q->where(function ($query) {
                            $query->where(function ($subquery) {
                                $subquery->whereNotNull('initialStatusText')
                                    ->where('initialStatusText', '!=', 'Completed');
                            })
                                ->orWhere(function ($subquery) {
                                    $subquery->whereNull('initialStatusText')
                                        ->whereNull('m1_date')
                                        ->whereNull('m2_date');
                                })
                                ->orWhere(function ($q) {
                                    $q->where('data_source_type', 'excel')
                                        ->where(function ($q2) {
                                            $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                        });
                                });
                        })
                            ->where(function ($query) {
                                $query->where(function ($q) {
                                    $q->where('data_source_type', 'excel')
                                        ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                })->orWhere(function ($q) {
                                    $q->where('data_source_type', '!=', 'excel');
                                });
                            });
                    } else {
                        $q->whereNull('m1_date')->whereNull('m2_date');
                    }
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                        $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                            $q->where('name', $sale_type)
                                ->orWhere('on_trigger', $sale_type);
                        });
                    });
                }
            } else {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            }
        });

        // Get metrics
        $metrics = [
            'largestSystemSize' => (clone $query)->max(DB::raw("CAST($valueField AS DECIMAL(10,2))")),
            'avgSystemSize' => (clone $query)->avg($valueField),
        ];

        // Install metrics
        $installQuery = (clone $query)->whereNull('date_cancelled');
        if ($isPestCompany) {
            if ($fieldRouteCount > 0) {
                $installQuery->where(function ($q) {
                    $q->where('initialStatusText', '=', 'Completed')
                        ->orWhere(function ($sq) {
                            $sq->whereNull('initialStatusText')
                                ->where(function ($q) {
                                    $q->whereNotNull('m1_date')
                                        ->orWhereNotNull('m2_date');
                                });
                        })
                        ->orWhere(function ($sq) {
                            $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                ->whereRaw("EXISTS (
                            SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                            WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                        )");
                        });
                });
            } else {
                $installQuery->whereNotNull('m1_date')->orWhereNotNull('m2_date');
            }
        } else {
            $installQuery->whereNotNull('m2_date');
        }

        $metrics['installKw'] = $installQuery->sum($valueField);
        $metrics['installCount'] = $installQuery->count();

        // Pending metrics
        $pendingQuery = (clone $query)->whereNull('date_cancelled');
        if ($isPestCompany) {
            if ($fieldRouteCount > 0) {
                $pendingQuery->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('initialStatusText')
                            ->where('initialStatusText', '!=', 'Completed');
                    })
                        ->orWhere(function ($q3) {
                            $q3->whereNull('initialStatusText')
                                ->whereNull('m1_date')
                                ->whereNull('m2_date');
                        })
                        ->orWhere(function ($q) {
                            $q->where('data_source_type', 'excel')
                                ->where(function ($q2) {
                                    $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                });
                        });
                })
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            $q->where('data_source_type', 'excel')
                                ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                        })->orWhere(function ($q) {
                            $q->where('data_source_type', '!=', 'excel');
                        });
                    });
            } else {
                $pendingQuery->whereNull('m1_date')->whereNull('m2_date');
            }
        } else {
            $pendingQuery->whereNull('m2_date');
        }

        $metrics['pendingKw'] = $pendingQuery->sum($valueField);
        $metrics['pendingKwCount'] = $pendingQuery->count();

        // Clawback metrics
        $clawBackPid = SalesMaster::whereIn('pid', $clawPid)
            ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where('initialStatusText', '=', 'Completed')
                                    ->orWhere(function ($sq) {
                                        $sq->whereNull('initialStatusText')
                                            ->where(function ($q) {
                                                $q->whereNotNull('m1_date')
                                                    ->orWhereNotNull('m2_date');
                                            });
                                    })
                                    ->orWhere(function ($sq) {
                                        $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                            ->whereRaw("EXISTS (
                                            SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                            WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                        )");
                                    });
                            });
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } elseif ($sale_type == 'Pending') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where(function ($subquery) {
                                    $subquery->whereNotNull('initialStatusText')
                                        ->where('initialStatusText', '!=', 'Completed');
                                })
                                    ->orWhere(function ($subquery) {
                                        $subquery->whereNull('initialStatusText')
                                            ->whereNull('m1_date')
                                            ->whereNull('m2_date');
                                    })
                                    ->orWhere(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->where(function ($q2) {
                                                $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                            });
                                    });
                            })
                                ->where(function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                    })->orWhere(function ($q) {
                                        $q->where('data_source_type', '!=', 'excel');
                                    });
                                });
                        } else {
                            $q->whereNull('m1_date')->whereNull('m2_date');
                        }
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            })
            ->whereNotNull('date_cancelled')
            ->whereIn('pid', $pid)
            ->pluck('pid');

        $metrics['clawBackAccount'] = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
        $metrics['clawBackAccountCount'] = count($clawBackPid);

        return $metrics;
    }

    // Helper function to generate time-based data
    private function generateTimeBasedData($filter, $companyProfile, $pid, $clawBackPid, $startDate, $endDate, $dateColumn, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $isPestCompany = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $valueField = $isPestCompany ? 'gross_account_value' : 'kw';
        $mdates = getdates();
        $timeData = [];

        switch ($filter) {
            case 'this_week':
                $currentDate = Carbon::now();
                for ($i = 1; $i <= $currentDate->dayOfWeek; $i++) {
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                    $timeData[] = $this->getDailyData($weekDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
                }
                break;

            case 'last_week':
                for ($i = 0; $i < 7; $i++) {
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                    $weekDate = date('Y-m-d', strtotime($startOfLastWeek));
                    $timeData[] = $this->getDailyData($weekDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
                }
                break;

            case 'this_month':
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                for ($i = 0; $i <= $dateDays; $i++) {
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                    $timeData[] = $this->getDailyData($weekDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
                }
                break;

            case 'last_month':
                $month = Carbon::now()->subMonths(1)->daysInMonth;
                for ($i = 0; $i < $month; $i++) {
                    $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays($i)));
                    $timeData[] = $this->getDailyData($weekDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
                }
                break;

            case 'this_quarter':
                $currentMonth = date('n');
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                for ($i = 0; $i < $weeks; $i++) {
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $timeData[] = $this->getDateRangeData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates);
                }
                break;

            case 'last_quarter':
                $currentMonth = date('n');
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                for ($i = 0; $i < $weeks; $i++) {
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $timeData[] = $this->getDateRangeData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates);
                }
                break;

            case 'this_year':
                $currentYearMonth = date('m');
                for ($i = 0; $i < $currentYearMonth; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-t', strtotime('+'.$i.' months', strtotime($startDate)));
                    $timeData[] = $this->getMonthlyData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates);
                }
                break;

            case 'last_year':
                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));
                    $timeData[] = $this->getMonthlyData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates);
                }
                break;

            case 'last_12_months':
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                        $timeData[] = $this->getDailyData($weekDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
                    }
                } else {
                    $currentDate = Carbon::parse($startDate);
                    $endDateObj = Carbon::parse($endDate);

                    while ($currentDate <= $endDateObj) {
                        $month = $currentDate->format('F');
                        $sDate = $currentDate->copy()->startOfMonth()->format('Y-m-d');
                        $eDate = $currentDate->copy()->endOfMonth()->format('Y-m-d');
                        if ($eDate > $endDate) {
                            $eDate = $endDate;
                        }
                        $currentDate->addMonth();

                        $timeData[] = $this->getMonthlyData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates);
                    }
                }
                break;

            case 'custom':
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                        $timeData[] = $this->getDailyData($weekDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
                    }
                } else {
                    $weekCount = round($dateDays / 7);
                    $totalWeekDay = 7 * $weekCount;
                    $extraDay = $dateDays - $totalWeekDay;

                    if ($extraDay > 0) {
                        $weekCount = $weekCount + 1;
                    }

                    for ($i = 0; $i < $weekCount; $i++) {
                        $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                        $dayWeek = 7 * $i;
                        if ($i == 0) {
                            $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                        } else {
                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                        }
                        if ($i == $weekCount - 1) {
                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = $endDate;
                        }

                        $timeData[] = $this->getDateRangeData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates);
                    }
                }
                break;
        }

        return $timeData;
    }

    // Helper function to get daily data
    private function getDailyData($date, $pid, $clawBackPid, $dateColumn, $valueField, $mdates, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $accountM1 = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('m1_date', '!=', null)->where($dateColumn, $date)->where('date_cancelled', null)->whereIn('pid', $pid)
            ->first();

        $accountM2 = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('m2_date', '!=', null)->where($dateColumn, $date)->where('date_cancelled', null)->whereIn('pid', $pid)
            ->first();

        $clawBack = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('date_cancelled', '!=', null)->where($dateColumn, $date)->whereIn('pid', $clawBackPid)
            ->first();

        $totalValue = $accountM1->$valueField + $accountM2->$valueField + $clawBack->$valueField;

        $data = [
            'date' => date('m-d-Y', strtotime($date)),
            'claw_back' => round($clawBack->account, 5),
            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
            'total_kw' => round($totalValue, 5),
        ];

        foreach ($mdates as $dateTrigger) {
            $saleTriggerCount = DB::table('sale_masters')
                ->whereRaw("JSON_CONTAINS(milestone_trigger, JSON_OBJECT('trigger', ?))", [$dateTrigger])
                ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                    if ($milestone_date) {
                        if ($sale_type == 'Cancel Date') {
                            $q->whereNotNull('date_cancelled');
                        } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                            if ($fieldRouteCount > 0) {
                                $q->where(function ($query) {
                                    $query->where('initialStatusText', '=', 'Completed')
                                        ->orWhere(function ($sq) {
                                            $sq->whereNull('initialStatusText')
                                                ->where(function ($q) {
                                                    $q->whereNotNull('m1_date')
                                                        ->orWhereNotNull('m2_date');
                                                });
                                        })
                                        ->orWhere(function ($sq) {
                                            $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                                ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                        });
                                });
                            } else {
                                $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                    $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                        $q->where('name', $sale_type)
                                            ->orWhere('on_trigger', $sale_type);
                                    });
                                });
                            }
                        } elseif ($sale_type == 'Pending') {
                            if ($fieldRouteCount > 0) {
                                $q->where(function ($query) {
                                    $query->where(function ($subquery) {
                                        $subquery->whereNotNull('initialStatusText')
                                            ->where('initialStatusText', '!=', 'Completed');
                                    })
                                        ->orWhere(function ($subquery) {
                                            $subquery->whereNull('initialStatusText')
                                                ->whereNull('m1_date')
                                                ->whereNull('m2_date');
                                        })
                                        ->orWhere(function ($q) {
                                            $q->where('data_source_type', 'excel')
                                                ->where(function ($q2) {
                                                    $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                                });
                                        });
                                })
                                    ->where(function ($query) {
                                        $query->where(function ($q) {
                                            $q->where('data_source_type', 'excel')
                                                ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                        })->orWhere(function ($q) {
                                            $q->where('data_source_type', '!=', 'excel');
                                        });
                                    });
                            } else {
                                $q->whereNull('m1_date')->whereNull('m2_date');
                            }
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } else {
                        $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                    }
                })
                ->whereIn('pid', $pid)
                ->count();
            $data[$dateTrigger] = $saleTriggerCount;
        }

        return $data;
    }

    // Helper function to get date range data
    private function getDateRangeData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates)
    {
        $accountM1 = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
            ->first();

        $accountM2 = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
            ->first();

        $clawBack = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
            ->first();

        $totalValue = $accountM1->$valueField + $accountM2->$valueField + $clawBack->$valueField;

        $data = [
            'date' => date('Y-m-d', strtotime($sDate)).' to '.date('Y-m-d', strtotime($eDate)),
            'claw_back' => round($clawBack->account, 5),
            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
            'total_kw' => round($totalValue, 5),
        ];

        foreach ($mdates as $dateTrigger) {
            $saleTriggerCount = DB::table('sale_masters')
                ->whereRaw("JSON_CONTAINS(milestone_trigger, JSON_OBJECT('trigger', ?))", [$dateTrigger])
                ->whereBetween('customer_signoff', [$sDate, $eDate])
                ->whereIn('pid', $pid)
                ->count();
            $data[$dateTrigger] = $saleTriggerCount;
        }

        return $data;
    }

    // Helper function to get monthly data
    private function getMonthlyData($sDate, $eDate, $pid, $clawBackPid, $dateColumn, $valueField, $mdates)
    {
        $accountM1 = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
            ->first();

        $accountM2 = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
            ->first();

        $clawBack = SalesMaster::selectRaw("count(`pid`) AS account, SUM(`$valueField`) AS $valueField")
            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
            ->first();

        $totalValue = $accountM1->$valueField + $accountM2->$valueField + $clawBack->$valueField;

        $time = strtotime($sDate);
        $month = date('M', $time);

        $data = [
            'date' => $month,
            'claw_back' => round($clawBack->account, 5),
            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
            'total_kw' => round($totalValue, 5),
        ];

        foreach ($mdates as $dateTrigger) {
            $saleTriggerCount = DB::table('sale_masters')
                ->whereRaw("JSON_CONTAINS(milestone_trigger, JSON_OBJECT('trigger', ?))", [$dateTrigger])
                ->whereBetween('customer_signoff', [$sDate, $eDate])
                ->whereIn('pid', $pid)
                ->count();
            $data[$dateTrigger] = $saleTriggerCount;
        }

        return $data;
    }

    public function reportAccountInstallRatioGraph(Request $request)
    {
        $officeId = $request->office_id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = filter_var($request->milestone_date, FILTER_VALIDATE_BOOLEAN);
        $sale_type = $request->sale_type ?? '';
        $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

        // Initialize data structure with default values
        $data = [
            'accounts' => [
                'total_sales' => 0,
                'm2_complete' => 0,
                'm2_pending' => 0,
                'cancelled' => 0,
                'clawback' => 0,
            ],
            'install_ratio' => [
                'install' => '0%',
                'uninstall' => '100%',
            ],
            'contracts' => [
                'total_kw_installed' => 0,
                'total_kw_pending' => 0,
                'paid_comissions' => 0,
                'projected_comissions' => 0,
                'avg_account_per_rep' => 0,
                'avg_kw_per_rep' => 0,
            ],
            'services' => [],
        ];

        // Get user IDs based on request
        $userIds = $this->getUserIds($request, $officeId);

        // Get sales PIDs associated with these users
        $salesPid = $this->getSalesPids($userIds);

        // Get company profile
        $companyProfile = CompanyProfile::first();
        $isPestCompany = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $isMortgageCompany = $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE;
        $isTurfCompany = $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf';

        // Calculate metrics
        $data['accounts']['total_sales'] = $this->getTotalSalesCount($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        [$m2Complete, $m2Pending] = $this->getInstallationMetrics(
            $request,
            $salesPid,
            $startDate,
            $endDate,
            $milestone_date,
            $sale_type,
            $fieldRouteCount,
            $isPestCompany,
            $isMortgageCompany,
            $isTurfCompany
        );

        $data['accounts']['m2_complete'] = $m2Complete;
        $data['accounts']['m2_pending'] = $m2Pending;

        $clawBackPid = ClawbackSettlement::whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
        $data['accounts']['cancelled'] = $this->getCancelledSalesCount($request, $salesPid, $startDate, $endDate, $clawBackPid, $milestone_date, $sale_type, $fieldRouteCount);
        $data['accounts']['clawback'] = $this->getClawbackSalesCount($request, $salesPid, $startDate, $endDate, $clawBackPid, $milestone_date, $sale_type, $fieldRouteCount);

        // Calculate install ratio
        $this->calculateInstallRatio($data, $m2Complete, $data['accounts']['total_sales']);

        // Calculate contract metrics
        $this->calculateContractMetrics(
            $data,
            $request,
            $salesPid,
            $startDate,
            $endDate,
            $milestone_date,
            $sale_type,
            $fieldRouteCount,
            $officeId,
            $isPestCompany,
            $isMortgageCompany,
            $isTurfCompany
        );

        $this->successResponse('Successfully.', 'reportSalesList', $data);
    }

    // Helper methods
    private function getUserIds(Request $request, $officeId)
    {
        if (! empty($request->user_id)) {
            return User::where('id', $request->user_id)->pluck('id');
        }

        if ($officeId != 'all') {
            return User::where('office_id', $officeId)->pluck('id');
        }

        return collect();
    }

    private function getSalesPids($userIds)
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        return SaleMasterProcess::whereIn('closer1_id', $userIds)
            ->orWhereIn('closer2_id', $userIds)
            ->orWhereIn('setter1_id', $userIds)
            ->orWhereIn('setter2_id', $userIds)
            ->pluck('pid')
            ->toArray();
    }

    private function getTotalSalesCount($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $query = SalesMaster::query();

        if (! empty($request->user_id)) {
            if (! empty($salesPid)) {
                $query->whereIn('pid', $salesPid);
            } else {
                return 0;
            }
        }

        $this->applyDateFilter($query, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        return $query->count();
    }

    private function getInstallationMetrics($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, $isPestCompany, $isMortgageCompany, $isTurfCompany)
    {
        $queryComplete = SalesMaster::whereNull('date_cancelled');
        $queryPending = SalesMaster::whereNull('date_cancelled')->whereNotNull('customer_signoff');

        if (! empty($request->user_id)) {
            if (! empty($salesPid)) {
                $queryComplete->whereIn('pid', $salesPid);
                $queryPending->whereIn('pid', $salesPid);
            } else {
                return [0, 0];
            }
        }

        $this->applyDateFilter($queryComplete, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
        $this->applyDateFilter($queryPending, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        if ($isPestCompany) {
            // FieldRoutes integration logic
            if ($fieldRouteCount > 0) {
                $queryComplete->where(function ($q) {
                    $q->where('initialStatusText', '=', 'Completed')
                        ->orWhere(function ($sq) {
                            $sq->whereNull('initialStatusText')
                                ->where(function ($q) {
                                    $q->whereNotNull('m1_date')
                                        ->orWhereNotNull('m2_date');
                                });
                        })
                        ->orWhere(function ($sq) {
                            $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                ->whereRaw("EXISTS (
                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                            )");
                        });
                });

                $queryPending->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('initialStatusText')
                            ->where('initialStatusText', '!=', 'Completed');
                    })
                        ->orWhere(function ($q3) {
                            $q3->whereNull('initialStatusText')
                                ->whereNull('m1_date')
                                ->whereNull('m2_date');
                        })
                        ->orWhere(function ($q) {
                            $q->where('data_source_type', 'excel')
                                ->where(function ($q2) {
                                    $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                });
                        });
                });
            } else {
                $queryComplete->where(function ($q) {
                    $q->whereNotNull('m1_date')
                        ->orWhereNotNull('m2_date');
                });

                $queryPending->where(function ($q) {
                    $q->whereNull('m1_date')
                        ->whereNull('m2_date');
                });
            }
        } elseif ($isMortgageCompany || $isTurfCompany) {
            $queryComplete->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNotNull('milestone_date');
            });

            $queryPending->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        } else {
            $queryComplete->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNotNull('milestone_date');
            });

            $queryPending->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        }

        return [$queryComplete->count(), $queryPending->count()];
    }

    private function getCancelledSalesCount($request, $salesPid, $startDate, $endDate, $clawBackPid, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $query = SalesMaster::whereNotNull('date_cancelled');

        if (! empty($request->user_id)) {
            if (! empty($salesPid)) {
                $query->whereIn('pid', $salesPid);
            } else {
                return 0;
            }
        }

        $this->applyDateFilter($query, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        if (! empty($clawBackPid)) {
            $query->whereNotIn('pid', $clawBackPid);
        }

        return $query->count();
    }

    private function getClawbackSalesCount($request, $salesPid, $startDate, $endDate, $clawBackPid, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $query = SalesMaster::whereNotNull('date_cancelled');

        if (! empty($request->user_id)) {
            if (! empty($salesPid)) {
                $query->whereIn('pid', $salesPid);
            } else {
                return 0;
            }
        }

        $this->applyDateFilter($query, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        if (! empty($clawBackPid)) {
            $query->whereIn('pid', $clawBackPid);
        }

        return $query->count();
    }

    private function applyDateFilter($query, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount)
    {
        if (! empty($startDate)) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $query->whereNotNull('date_cancelled');
                } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                    if ($fieldRouteCount > 0) {
                        $query->where(function ($q) {
                            $q->where('initialStatusText', '=', 'Completed')
                                ->orWhere(function ($sq) {
                                    $sq->whereNull('initialStatusText')
                                        ->where(function ($q) {
                                            $q->whereNotNull('m1_date')
                                                ->orWhereNotNull('m2_date');
                                        });
                                })
                                ->orWhere(function ($sq) {
                                    $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                        ->whereRaw("EXISTS (
                                        SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                        WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                    )");
                                });
                        });
                    } else {
                        $query->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } elseif ($sale_type == 'Pending') {
                    if ($fieldRouteCount > 0) {
                        $query->where(function ($q) {
                            $q->where(function ($subquery) {
                                $subquery->whereNotNull('initialStatusText')
                                    ->where('initialStatusText', '!=', 'Completed');
                            })
                                ->orWhere(function ($subquery) {
                                    $subquery->whereNull('initialStatusText')
                                        ->whereNull('m1_date')
                                        ->whereNull('m2_date');
                                })
                                ->orWhere(function ($q) {
                                    $q->where('data_source_type', 'excel')
                                        ->where(function ($q2) {
                                            $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                        });
                                });
                        })
                            ->where(function ($q) {
                                $q->where(function ($q2) {
                                    $q2->where('data_source_type', 'excel')
                                        ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                })->orWhere(function ($q2) {
                                    $q2->where('data_source_type', '!=', 'excel');
                                });
                            });
                    } else {
                        $query->whereNull('m1_date')->whereNull('m2_date');
                    }
                } else {
                    $query->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                        $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                            $q->where('name', $sale_type)
                                ->orWhere('on_trigger', $sale_type);
                        });
                    });
                }
            } else {
                $query->whereBetween('customer_signoff', [$startDate, $endDate]);
            }
        }
    }

    private function calculateInstallRatio(&$data, $m2Complete, $totalSales)
    {
        if ($m2Complete > 0 && $totalSales > 0) {
            $install = round((($m2Complete / $totalSales) * 100), 5);
            $data['install_ratio'] = [
                'install' => $install.'%',
                'uninstall' => round(100 - $install, 5).'%',
            ];
        }
    }

    private function calculateContractMetrics(&$data, $request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, $officeId, $isPestCompany, $isMortgageCompany, $isTurfCompany)
    {
        $companyProfile = CompanyProfile::first();

        // Get total sales PIDs for commission calculations
        $query = SalesMaster::query();

        // Apply PID filter for specific office OR specific user
        if (($officeId != 'all' && ! empty($salesPid)) || (! empty($request->user_id) && ! empty($salesPid))) {
            $query->whereIn('pid', $salesPid);
        }

        $this->applyDateFilter($query, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);
        $totalSales = $query->pluck('pid');

        // Calculate KW metrics
        if ($isPestCompany) {
            $data['contracts']['total_kw_installed'] = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, true, 'gross_account_value');
            $data['contracts']['total_kw_pending'] = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, false, 'gross_account_value');
            $totalKw = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, null, 'gross_account_value');
        } elseif ($isMortgageCompany || $isTurfCompany) {
            $data['contracts']['total_kw_installed'] = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, true, 'gross_account_value');
            $data['contracts']['total_kw_pending'] = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, false, 'gross_account_value');
            $totalKw = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, null, 'gross_account_value');
        } else {
            $data['contracts']['total_kw_installed'] = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, true, 'kw');
            $data['contracts']['total_kw_pending'] = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, false, 'kw');
            $totalKw = $this->getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, null, 'kw');
        }

        // Get filtered user IDs for commission calculations
        $filteredUserIds = $this->getFilteredUserIds($request);

        // Calculate commission metrics with proper user filtering
        $projectedCommissionQuery = ProjectionUserCommission::whereIn('pid', $totalSales);
        $commissionQuery = UserCommission::whereIn('pid', $totalSales)->where('status', '3');
        $clawBackQuery = ClawbackSettlement::whereIn('pid', $totalSales)->where(['type' => 'commission', 'status' => '3']);

        // Apply user filter if specific user is selected
        if (! empty($request->user_id)) {
            $projectedCommissionQuery->whereIn('user_id', $filteredUserIds);
            $commissionQuery->whereIn('user_id', $filteredUserIds);
            $clawBackQuery->whereIn('user_id', $filteredUserIds);
        }

        $projectedCommission = $projectedCommissionQuery->sum('amount') ?? 0;
        $commission = $commissionQuery->sum('amount');
        $clawBack = $clawBackQuery->sum('clawback_amount');

        $totalReps = User::where('is_super_admin', '!=', 1)->count();

        $data['contracts']['paid_comissions'] = ($commission - $clawBack);
        $data['contracts']['projected_comissions'] = $projectedCommission;
        $data['contracts']['avg_account_per_rep'] = (count($totalSales) && $totalReps) ? round(count($totalSales) / $totalReps, 3) : 0;
        $data['contracts']['avg_kw_per_rep'] = ($totalKw && $totalReps) ? round($totalKw / $totalReps, 3) : 0;
    }

    private function getKwMetric($request, $salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount, $isInstalled, $field)
    {
        $query = SalesMaster::whereNull('date_cancelled');

        if (! empty($request->user_id)) {
            if (! empty($salesPid)) {
                $query->whereIn('pid', $salesPid);
            } else {
                return 0;
            }
        }

        $this->applyDateFilter($query, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        if ($isInstalled === true) {
            $query->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNotNull('milestone_date');
            });
        } elseif ($isInstalled === false) {
            $query->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        }

        return $query->sum($field);
    }

    // MY EARNINGS
    public function mySalesList(Request $request)
    {
        $userId = auth()->user()->id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = filter_var($request->milestone_date, FILTER_VALIDATE_BOOLEAN);
        $sale_type = $request->sale_type ?? '';
        $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

        // Get PIDs associated with the user
        $pid = $this->getUserSalesPids($userId);

        // Build base query with relationships
        $query = $this->buildBaseQuery($userId, $pid);

        // Apply filters
        $this->applyFilters($query, $request, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        // Handle export vs pagination
        $transformedData = $this->getResultData($query, $request);
        // dd($transformedData);

        // // Transform data
        $transformedData = $this->transformSalesData($transformedData, $userId);

        // Handle export if needed
        if ($request->is_export == 1) {
            return $this->handleExport($transformedData);
        }

        $this->successResponse('Successfully.', 'mySalesList', $transformedData);
    }

    // Helper methods

    private function getUserSalesPids($userId)
    {
        return SaleMasterProcess::where('closer1_id', $userId)
            ->orWhere('closer2_id', $userId)
            ->orWhere('setter1_id', $userId)
            ->orWhere('setter2_id', $userId)
            ->pluck('pid')
            ->toArray();
    }

    private function buildBaseQuery($userId, $pid)
    {
        return SalesMaster::with([
            'lastMilestone' => function ($q) use ($userId) {
                $q->where('closer1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('setter2_id', $userId)
                    ->orderByRaw("CASE 
                        WHEN type = 'm2' THEN 1 
                        WHEN type = 'm1' THEN 2 
                        ELSE 3 
                    END")
                    ->orderBy('milestone_date', 'DESC')
                    ->orderBy('id', 'DESC');
            },
            'lastMilestone.milestoneSchemaTrigger',
            'salesProductMaster.milestoneSchemaTrigger',
        ])->whereIn('pid', $pid)
            ->orderBy('id', 'DESC')
            ->orderBy('customer_signoff', 'DESC');
    }

    private function applyFilters($query, $request, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount)
    {
        // Date filter
        if (! empty($startDate)) {
            $query->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where('initialStatusText', '=', 'Completed')
                                    ->orWhere(function ($sq) {
                                        $sq->whereNull('initialStatusText')
                                            ->where(function ($q) {
                                                $q->whereNotNull('m1_date')
                                                    ->orWhereNotNull('m2_date');
                                            });
                                    })
                                    ->orWhere(function ($sq) {
                                        $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                            ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                    });
                            });
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } elseif ($sale_type == 'Pending') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where(function ($subquery) {
                                    $subquery->whereNotNull('initialStatusText')
                                        ->where('initialStatusText', '!=', 'Completed');
                                })
                                    ->orWhere(function ($subquery) {
                                        $subquery->whereNull('initialStatusText')
                                            ->whereNull('m1_date')
                                            ->whereNull('m2_date');
                                    })
                                    ->orWhere(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->where(function ($q2) {
                                                $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                            });
                                    });
                            })
                                ->where(function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                    })->orWhere(function ($q) {
                                        $q->where('data_source_type', '!=', 'excel');
                                    });
                                });
                        } else {
                            $q->whereNull('m1_date')->whereNull('m2_date');
                        }
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type, $startDate, $endDate) {
                            $q->whereNotNull('milestone_date')
                                ->whereBetween('milestone_date', [$startDate, $endDate])
                                ->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            });
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $search = $request->input('search');
                $q->where('customer_name', 'LIKE', "%$search%")
                    ->orWhere('date_cancelled', 'LIKE', "%$search%")
                    ->orWhere('pid', 'LIKE', "%$search%")
                    ->orWhere('customer_state', 'LIKE', "%$search%")
                    ->orWhere('net_epc', 'LIKE', "%$search%")
                    ->orWhere('job_status', 'LIKE', "%$search%")
                    ->orWhere('kw', 'LIKE', "%$search%");
            });
        }

        // Other filters
        $filters = [
            'filter_product' => 'product_id',
            'location' => 'customer_state',
            'filter_install' => 'install_partner',
            'filter_status' => 'job_status',
        ];

        foreach ($filters as $requestKey => $column) {
            if ($request->filled($requestKey)) {
                $query->where($column, $request->input($requestKey));
            }
        }

        // Date type filter
        if ($request->filled('date_filter')) {
            if ($request->input('date_filter') == 'Cancel Date') {
                $query->whereNotNull('date_cancelled');
            } else {
                $query->whereHas('salesProductMaster', function ($q) use ($request) {
                    $q->where('type', $request->input('date_filter'))
                        ->whereNotNull('milestone_date');
                });
            }
        }
    }

    private function getResultData($query, $request)
    {
        if (isset($request->is_export) && $request->is_export == 1) {
            return $query->get();
        }
        \Log::info('sssssss');
        $perPage = $request->perpage ?: 10;

        return $query->paginate($perPage);
    }

    private function transformSalesData($data, $userId)
    {
        $isPaginator = $data instanceof LengthAwarePaginator || $data instanceof Paginator;

        $collection = $isPaginator ? $data->getCollection() : $data;

        $transformedCollection = $collection->map(function ($sale) use ($userId) {
            // Process milestones
            $milestoneData = $this->processMilestones($sale, $userId);

            // Calculate commission data
            $commissionData = $this->calculateCommissions($sale, $userId);

            // Get reconciliation setting
            $reconciliationSetting = CompanySetting::where([
                'type' => 'reconciliation',
                'status' => '1',
            ])->first();

            // Calculate comp rate if mortgage company
            $compRate = $this->calculateCompRate($sale, $userId);

            return [
                'pid' => $sale->pid,
                'customer_name' => $sale->customer_name,
                'state' => $sale->customer_state,
                'product' => $sale->product_code,
                'closer' => $sale->closer1_name,
                'setter' => $sale->setter1_name,
                'kw' => $sale->kw,
                'gross_account_value' => $sale->gross_account_value,
                'net_epc' => $sale->net_epc,
                'comp_rate' => $compRate,
                'job_status' => $sale->job_status,
                'date_cancelled' => $sale->date_cancelled,
                'last_milestone' => $milestoneData['lastMilestone'],
                'all_milestone' => $milestoneData['allMilestone'],
                'total_commission' => $commissionData['totalCommission'],
                'projected_commission' => $commissionData['projectedCommission'],
            ];
        });

        if ($isPaginator) {
            $data->setCollection($transformedCollection);

            return $data;
        }

        return $transformedCollection;
    }

    private function processMilestones($sale, $userId)
    {
        $lastPaid = null;
        $lastDisplay = null;
        $bestMilestone = null;

        foreach ($sale->lastMilestone as $milestone) {
            if ($milestone->milestone_date) {
                $amount = collect($sale->lastMilestone)
                    ->where('type', $milestone->type)
                    ->where('is_paid', $milestone->is_paid)
                    ->sum('amount');

                $milestoneData = [
                    'name' => $milestone->milestoneSchemaTrigger?->name,
                    'value' => $amount ?? 0,
                    'date' => $milestone->milestone_date,
                    'is_paid' => $milestone->is_paid,
                    'type' => $milestone->type,
                ];

                // Separate paid and unpaid for backward compatibility
                if ($milestone->is_paid) {
                    $lastPaid = $milestoneData;
                } else {
                    if (! $lastDisplay) {
                        $lastDisplay = $milestoneData;
                    }
                }

                // Select best milestone based on priority:
                // 1. Milestones with actual commission amounts (value > 0)
                // 2. Paid milestones over unpaid ones
                // 3. M2 over M1 (already handled by ordering)
                if (! $bestMilestone || $this->isBetterMilestone($milestoneData, $bestMilestone)) {
                    $bestMilestone = $milestoneData;
                }
            }
        }

        // Use the best milestone, fallback to original logic if none found
        $lastMilestone = $bestMilestone ?: ($lastDisplay ?: $lastPaid);
        $allMilestone = [];

        foreach ($sale->salesProductMaster as $productMaster) {
            if ($this->isUserAssociated($productMaster, $userId)) {
                $allMilestone[] = [
                    'name' => $productMaster->milestoneSchemaTrigger?->name,
                    'value' => $productMaster->amount,
                    'date' => $productMaster->milestone_date,
                    'is_projected' => $productMaster->is_projected,
                ];
            }
        }

        return [
            'lastMilestone' => $lastMilestone,
            'allMilestone' => $allMilestone,
        ];
    }

    private function isBetterMilestone($current, $existing)
    {
        // Priority 1: Milestones with actual commission amounts (value > 0)
        if ($current['value'] > 0 && $existing['value'] <= 0) {
            return true;
        }
        if ($current['value'] <= 0 && $existing['value'] > 0) {
            return false;
        }

        // Priority 2: Among milestones with amounts, prefer higher values
        if ($current['value'] > 0 && $existing['value'] > 0) {
            if ($current['value'] > $existing['value']) {
                return true;
            }
            if ($current['value'] < $existing['value']) {
                return false;
            }
        }

        // Priority 3: Paid milestones over unpaid ones
        if ($current['is_paid'] && ! $existing['is_paid']) {
            return true;
        }
        if (! $current['is_paid'] && $existing['is_paid']) {
            return false;
        }

        // Priority 4: M2 over M1 (handled by database ordering, so first wins)
        // Since we ordered by M2 first, if we reach here, current is not better
        return false;
    }

    private function calculateCommissions($sale, $userId)
    {
        $reconCommission = UserCommission::selectRaw('SUM(amount) as amount, pid, user_id, date, comp_rate')
            ->where([
                'pid' => $sale->pid,
                'user_id' => $userId,
                'settlement_type' => 'reconciliation',
            ])->first();

        $totalCommission = 0;
        $projectedCommission = 0;

        foreach ($sale->salesProductMaster as $productMaster) {
            if ($this->isUserAssociated($productMaster, $userId)) {
                if (! $projectedCommission && $productMaster->is_projected) {
                    $projectedCommission = 1;
                }
                $totalCommission += $productMaster->amount;
            }
        }

        // Add reconciliation commission if setting is active
        $reconciliationSetting = CompanySetting::where([
            'type' => 'reconciliation',
            'status' => '1',
        ])->first();

        if ($reconciliationSetting && $reconCommission) {
            $totalCommission += $reconCommission->amount;
        }

        return [
            'reconCommission' => $reconCommission,
            'totalCommission' => $totalCommission,
            'projectedCommission' => $projectedCommission,
        ];
    }

    private function calculateCompRate($sale, $userId)
    {
        $companyProfile = CompanyProfile::first();
        if ($companyProfile->company_type != CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            return 0;
        }

        // Get comp_rate with priority: reconciliation > during_m2 > others
        $commission = UserCommission::select('comp_rate', 'redline', 'settlement_type')
            ->where([
                'pid' => $sale->pid,
                'user_id' => $userId,
                'is_displayed' => '1',
            ])
            ->whereNotNull('comp_rate')
            ->where('comp_rate', '>', 0)
            ->orderByRaw("CASE 
                WHEN settlement_type = 'reconciliation' THEN 1 
                WHEN settlement_type = 'during_m2' THEN 2 
                ELSE 3 
            END")
            ->first();

        if ($commission && $commission->comp_rate > 0) {
            return number_format($commission->comp_rate, 4);
        }

        // Fallback: Calculate manually if no comp_rate found
        return $this->calculateCompRateManually($sale, $userId);
    }

    private function calculateCompRateManually($sale, $userId)
    {
        // Get redline from any commission record or user profile
        $commissionWithRedline = UserCommission::select('redline')
            ->where([
                'pid' => $sale->pid,
                'user_id' => $userId,
                'is_displayed' => '1',
            ])
            ->whereNotNull('redline')
            ->orderByRaw("CASE 
                WHEN settlement_type = 'during_m2' THEN 1 
                WHEN settlement_type = 'reconciliation' THEN 2 
                ELSE 3 
            END")
            ->first();

        $redline = 0;
        if ($commissionWithRedline && $commissionWithRedline->redline) {
            $redline = $commissionWithRedline->redline;
        } else {
            // Fallback to user's default redline
            $user = User::find($userId);
            if ($user && $user->redline) {
                $redline = $user->redline;
            }
        }

        // Calculate: (net_epc * 100) - redline
        $netEpc = $sale->net_epc ?? 0;
        $compRate = ($netEpc * 100) - $redline;

        return number_format($compRate, 4);
    }

    private function isUserAssociated($productMaster, $userId)
    {
        return in_array($userId, [
            $productMaster->setter1_id,
            $productMaster->setter2_id,
            $productMaster->closer1_id,
            $productMaster->closer2_id,
        ]);
    }

    private function handleExport($data)
    {
        // Ensure export directories exist
        $directories = ['exports', 'exports/reports', 'exports/reports/sales'];
        foreach ($directories as $dir) {
            if (! Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
        }

        $fileName = 'manager_mysales_export_'.date('Y_m_d_H_i_s').'.xlsx';
        Excel::store(
            new ExportReportMySalesStandard($data),
            'exports/reports/sales/'.$fileName,
            'public',
            \Maatwebsite\Excel\Excel::XLSX
        );

        $url = getStoragePath('exports/reports/sales/'.$fileName);

        return response()->json(['url' => $url]);
    }

    public function mySalesGraph(Request $request): JsonResponse
    {
        $userId = auth()->user()->id;
        [$startDate, $endDate] = getDateFromFilter($request);

        $kwType = $request->kw_type ?? 'sold';
        $dateColumn = ($kwType == '' || $kwType == 'sold') ? 'customer_signoff' : 'm2_date';
        $milestone_date = filter_var($request->milestone_date, FILTER_VALIDATE_BOOLEAN);
        $sale_type = $request->sale_type ?? '';
        $filterDataDateWise = $request->input('filter');
        $companyProfile = CompanyProfile::first();
        $mdates = milestoneSchemaTriggerDates();

        $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

        // Get PIDs for the user
        $clawbackPid = ClawbackSettlement::where('user_id', $userId)->distinct()->pluck('pid')->toArray();
        $pid = SaleMasterProcess::where('closer1_id', $userId)
            ->orWhere('closer2_id', $userId)
            ->orWhere('setter1_id', $userId)
            ->orWhere('setter2_id', $userId)
            ->pluck('pid')
            ->toArray();

        // Base query for sales data
        $salesQuery = SalesMaster::whereIn('pid', $pid)
            ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where('initialStatusText', '=', 'Completed')
                                    ->orWhere(function ($sq) {
                                        $sq->whereNull('initialStatusText')
                                            ->where(function ($q) {
                                                $q->whereNotNull('m1_date')
                                                    ->orWhereNotNull('m2_date');
                                            });
                                    })
                                    ->orWhere(function ($sq) {
                                        $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                            ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                    });
                            });
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } elseif ($sale_type == 'Pending') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where(function ($subquery) {
                                    $subquery->whereNotNull('initialStatusText')
                                        ->where('initialStatusText', '!=', 'Completed');
                                })
                                    ->orWhere(function ($subquery) {
                                        $subquery->whereNull('initialStatusText')
                                            ->whereNull('m1_date')
                                            ->whereNull('m2_date');
                                    })
                                    ->orWhere(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->where(function ($q2) {
                                                $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                            });
                                    });
                            })
                                ->where(function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                    })->orWhere(function ($q) {
                                        $q->where('data_source_type', '!=', 'excel');
                                    });
                                });
                        } else {
                            $q->whereNull('m1_date')->whereNull('m2_date');
                        }
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            });

        // Get summary statistics based on company type
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $valueField = 'gross_account_value';
            $installField = 'm1_date';
        } elseif (
            $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE ||
            ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')
        ) {
            $valueField = 'gross_account_value';
            $installField = 'm2_date';
        } else {
            $valueField = 'kw';
            $installField = 'm2_date';
        }

        // Get summary data
        $summary = [
            'largest_system_size' => $salesQuery->max(DB::raw("CAST($valueField AS DECIMAL(10,2))")),
            'avg_system_size' => $salesQuery->avg($valueField),
            'install_kw' => $salesQuery->whereNull('date_cancelled')->whereNotNull($installField)->sum($valueField),
            'install_count' => $salesQuery->whereNull('date_cancelled')->whereNotNull($installField)->count(),
            'pending_kw' => $salesQuery->whereNull('date_cancelled')->whereNull($installField)->sum($valueField),
            'pending_count' => $salesQuery->whereNull('date_cancelled')->whereNull($installField)->count(),
            'clawback_amount' => ClawbackSettlement::whereIn('pid', $clawbackPid)->sum('clawback_amount'),
            'clawback_count' => SalesMaster::whereIn('pid', $clawbackPid)
                ->whereNotNull('date_cancelled')
                ->whereIn('pid', $pid)
                ->count(),
        ];

        // Process data based on time filter
        $total = [];
        $dateRange = $this->getDateRange($filterDataDateWise, $startDate, $endDate);
        foreach ($dateRange as $dateItem) {
            // Determine if we're using a single date or date range
            $useRange = isset($dateItem['start']) && isset($dateItem['end']);
            $sDate = $useRange ? $dateItem['start'] : $dateItem['date'];
            $eDate = $useRange ? $dateItem['end'] : $dateItem['date'];
            $displayDate = $dateItem['display'] ?? date('m/d/Y', strtotime($sDate));

            $query = SalesMaster::whereIn('pid', $pid)
                ->when(! empty($startDate), function ($q) use ($useRange, $sDate, $eDate, $dateColumn) {
                    if ($useRange) {
                        $q->whereBetween($dateColumn, [$sDate, $eDate]);
                    } else {
                        $q->where($dateColumn, $sDate);
                    }
                });

            $kw = $query->sum($valueField);
            $kwCancel = SalesMaster::whereIn('pid', $pid)
                ->when($useRange, function ($q) use ($sDate, $eDate) {
                    $q->whereBetween('date_cancelled', [$sDate, $eDate]);
                }, function ($q) use ($sDate) {
                    $q->where('date_cancelled', $sDate);
                })
                ->sum($valueField);
            $kwTotals = max($kw - $kwCancel, 0);

            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM('.$valueField.') AS kw')
                ->when($useRange, function ($q) use ($sDate, $eDate) {
                    $q->whereBetween('m1_date', [$sDate, $eDate]);
                }, function ($q) use ($sDate) {
                    $q->where('m1_date', $sDate);
                })
                ->whereIn('pid', $pid)
                ->where(function ($q) use ($sDate, $eDate, $useRange) {
                    if ($useRange) {
                        $q->whereNotBetween('date_cancelled', [$sDate, $eDate])
                            ->orWhereNull('date_cancelled');
                    } else {
                        $q->where('date_cancelled', '!=', $sDate)
                            ->orWhereNull('date_cancelled');
                    }
                })
                ->first();

            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM('.$valueField.') AS kw')
                ->whereNotNull('m2_date')
                ->when($useRange, function ($q) use ($sDate, $eDate) {
                    $q->whereBetween('m2_date', [$sDate, $eDate]);
                }, function ($q) use ($sDate) {
                    $q->where('m2_date', $sDate);
                })
                ->whereIn('pid', $pid)
                ->where(function ($q) use ($sDate, $eDate, $useRange) {
                    if ($useRange) {
                        $q->whereNotBetween('date_cancelled', [$sDate, $eDate])
                            ->orWhereNull('date_cancelled');
                    } else {
                        $q->where('date_cancelled', '!=', $sDate)
                            ->orWhereNull('date_cancelled');
                    }
                })
                ->first();

            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM('.$valueField.') AS kw')
                ->whereNotNull('date_cancelled')
                ->when($useRange, function ($q) use ($sDate, $eDate) {
                    $q->whereBetween('date_cancelled', [$sDate, $eDate]);
                }, function ($q) use ($sDate) {
                    $q->where('date_cancelled', $sDate);
                })
                ->whereIn('pid', $clawbackPid)
                ->first();

            $totalAccount = ($accountM1->account ?? 0) + ($accountM2->account ?? 0) + ($clawBack->account ?? 0);

            $item = [
                'date' => $displayDate,
                'claw_back' => round($clawBack->account ?? 0, 5),
                'total_account' => round($totalAccount, 5),
                'total_kw' => round($kwTotals, 5),
            ];

            // Add milestone trigger counts
            foreach ($mdates as $mdate) {
                $saleTriggerCount = DB::table('sale_masters')
                    ->whereRaw("JSON_CONTAINS(milestone_trigger, JSON_OBJECT('trigger', ?))", [$mdate])
                    ->when(! empty($startDate), function ($q) use ($sDate, $eDate, $useRange) {
                        if ($useRange) {
                            $q->whereBetween('customer_signoff', [$sDate, $eDate]);
                        } else {
                            $q->where('customer_signoff', $sDate);
                        }
                    })
                    ->whereIn('pid', $pid)
                    ->count();
                $item[$mdate] = $saleTriggerCount;
            }

            $total[] = $item;
        }

        // Prepare response
        $data = [
            'heading_count_kw' => [
                'largest_system_size' => round($summary['largest_system_size'], 5),
                'avg_system_size' => round($summary['avg_system_size'], 5),
                'install_kw' => round($summary['install_kw'], 5).'('.$summary['install_count'].')',
                'pending_kw' => round($summary['pending_kw'], 5).'('.$summary['pending_count'].')',
                'clawBack_account' => round($summary['clawback_amount'], 5).'('.$summary['clawback_count'].')',
            ],
            'my_sales' => $total,
            'kw_type' => $kwType,
        ];

        return response()->json([
            'ApiName' => 'My sales graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    private function getDateRange($filter, $startDate, $endDate)
    {
        $range = [];

        switch ($filter) {
            case 'this_week':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now();
                for ($date = clone $start; $date <= $end; $date->addDay()) {
                    $range[] = [
                        'date' => $date->format('Y-m-d'),
                        'display' => $date->format('m/d/Y'),
                    ];
                }
                break;

            case 'last_week':
                for ($i = 0; $i < 7; $i++) {
                    $date = Carbon::now()->subWeek()->startOfWeek()->addDays($i);
                    $range[] = [
                        'date' => $date->format('Y-m-d'),
                        'display' => $date->format('m/d/Y'),
                    ];
                }
                break;

            case 'this_month':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                for ($date = clone $start; $date <= $end; $date->addDay()) {
                    $range[] = [
                        'date' => $date->format('Y-m-d'),
                        'display' => $date->format('m/d/Y'),
                    ];
                }
                break;

            case 'last_month':
                $days = Carbon::now()->subMonth()->daysInMonth;
                for ($i = 0; $i < $days; $i++) {
                    $date = Carbon::now()->subMonth()->startOfMonth()->addDays($i);
                    $range[] = [
                        'date' => $date->format('Y-m-d'),
                        'display' => $date->format('m/d/Y'),
                    ];
                }
                break;

            case 'this_quarter':
            case 'last_quarter':
                $start = $filter === 'this_quarter' ? Carbon::now()->startOfQuarter() : Carbon::now()->subQuarter()->startOfQuarter();
                $end = $filter === 'this_quarter' ? Carbon::now()->endOfQuarter() : Carbon::now()->subQuarter()->endOfQuarter();

                // Group by week
                for ($date = clone $start; $date <= $end; $date->addWeek()) {
                    $weekEnd = (clone $date)->endOfWeek();
                    if ($weekEnd > $end) {
                        $weekEnd = $end;
                    }
                    $range[] = [
                        'start' => $date->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d'),
                        'display' => $date->format('m/d/Y').' to '.$weekEnd->format('m/d/Y'),
                    ];
                    $date = $weekEnd;
                }
                break;

            case 'this_year':
            case 'last_year':
                $year = $filter === 'this_year' ? Carbon::now()->year : Carbon::now()->subYear()->year;
                for ($month = 1; $month <= 12; $month++) {
                    $date = Carbon::create($year, $month, 1);
                    $range[] = [
                        'start' => $date->format('Y-m-d'),
                        'end' => $date->endOfMonth()->format('Y-m-d'),
                        'display' => $date->format('M'),
                    ];
                }
                break;

            case 'last_12_months':
                // Use the actual startDate and endDate parameters
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                $current = clone $start;

                while ($current <= $end) {
                    // Determine the end of current period (either end of month or the final end date)
                    $periodEnd = $current->copy()->endOfMonth();
                    if ($periodEnd > $end) {
                        $periodEnd = $end;
                    }

                    $range[] = [
                        'start' => $current->format('Y-m-d'),
                        'end' => $periodEnd->format('Y-m-d'),
                        'display' => $current->format('M'),
                    ];

                    // Move to the first day of next month
                    $current = $current->copy()->addMonth()->startOfMonth();
                }
                break;

            case 'custom':
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                $diffDays = $start->diffInDays($end);

                if ($diffDays <= 15) {
                    // Daily data
                    for ($date = clone $start; $date <= $end; $date->addDay()) {
                        $range[] = [
                            'date' => $date->format('Y-m-d'),
                            'display' => $date->format('m/d/Y'),
                        ];
                    }
                } else {
                    // Weekly data
                    for ($date = clone $start; $date <= $end; $date->addWeek()) {
                        $weekEnd = (clone $date)->endOfWeek();
                        if ($weekEnd > $end) {
                            $weekEnd = $end;
                        }
                        $range[] = [
                            'start' => $date->format('Y-m-d'),
                            'end' => $weekEnd->format('Y-m-d'),
                            'display' => $date->format('m/d/Y').' to '.$weekEnd->format('m/d/Y'),
                        ];
                        $date = $weekEnd;
                    }
                }
                break;

            default: // Default to last 7 days
                for ($i = 0; $i < 7; $i++) {
                    $date = Carbon::now()->subDays(6 - $i);
                    $range[] = [
                        'date' => $date->format('Y-m-d'),
                        'display' => $date->format('m/d/Y'),
                    ];
                }
        }

        return $range;
    }

    public function myAccountInstallRatioGraph(Request $request): JsonResponse
    {
        $userId = auth()->user()->id;

        if (! isset($request->filter)) {
            return response()->json([
                'ApiName' => 'My sales graph',
                'status' => false,
                'message' => 'filter not found.',
            ], 400);
        }

        $milestone_date = filter_var($request->milestone_date, FILTER_VALIDATE_BOOLEAN);
        $sale_type = $request->sale_type ?? '';
        $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

        [$startDate, $endDate] = getDateFromFilter($request);
        $salesPid = $this->getUserSalesPids2($userId);
        $companyProfile = CompanyProfile::first();

        // Calculate sales metrics
        $salesMetrics = $this->calculateSalesMetrics($salesPid, $startDate, $endDate, $companyProfile, $milestone_date, $sale_type, $fieldRouteCount);

        // Prepare response data
        $data = [
            'accounts' => $salesMetrics,
            'install_ratio' => $this->calculateInstallRatio2($salesMetrics['m2_complete'], $salesMetrics['total_sales']),
            'graph_m1_m2_amount' => $this->getGraphData($userId, $startDate, $endDate),
        ];

        $this->successResponse('Successfully.', 'reportSalesList', $data);
    }

    // Helper methods
    private function getUserSalesPids2($userId)
    {
        return SaleMasterProcess::where('closer1_id', $userId)
            ->orWhere('closer2_id', $userId)
            ->orWhere('setter1_id', $userId)
            ->orWhere('setter2_id', $userId)
            ->pluck('pid')
            ->toArray();
    }

    private function calculateSalesMetrics($salesPid, $startDate, $endDate, $companyProfile, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $totalSales = $this->getTotalSalesCount2($salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount);

        $isPestCompany = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $isMortgageCompany = $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE;

        $m2Complete = $this->getM2CompleteCount($salesPid, $startDate, $endDate, $isPestCompany, $isMortgageCompany, $milestone_date, $sale_type, $fieldRouteCount);
        $m2Pending = $this->getM2PendingCount($salesPid, $startDate, $endDate, $isPestCompany, $isMortgageCompany, $milestone_date, $sale_type, $fieldRouteCount);

        $clawBackPid = ClawbackSettlement::whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
        $cancelled = $this->getCancelledCount($salesPid, $startDate, $endDate, $clawBackPid, false, $milestone_date, $sale_type, $fieldRouteCount);
        $clawBack = $this->getCancelledCount($salesPid, $startDate, $endDate, $clawBackPid, true, $milestone_date, $sale_type, $fieldRouteCount);

        return [
            'total_sales' => $totalSales,
            'm2_complete' => $m2Complete,
            'm2_pending' => $m2Pending,
            'cancelled' => $cancelled,
            'clawback' => $clawBack,
        ];
    }

    private function getTotalSalesCount2($salesPid, $startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount)
    {
        return SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                    if ($fieldRouteCount > 0) {
                        $q->where(function ($query) {
                            $query->where('initialStatusText', '=', 'Completed')
                                ->orWhere(function ($sq) {
                                    $sq->whereNull('initialStatusText')
                                        ->where(function ($q) {
                                            $q->whereNotNull('m1_date')
                                                ->orWhereNotNull('m2_date');
                                        });
                                })
                                ->orWhere(function ($sq) {
                                    $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                        ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                });
                        });
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } elseif ($sale_type == 'Pending') {
                    if ($fieldRouteCount > 0) {
                        $q->where(function ($query) {
                            $query->where(function ($subquery) {
                                $subquery->whereNotNull('initialStatusText')
                                    ->where('initialStatusText', '!=', 'Completed');
                            })
                                ->orWhere(function ($subquery) {
                                    $subquery->whereNull('initialStatusText')
                                        ->whereNull('m1_date')
                                        ->whereNull('m2_date');
                                })
                                ->orWhere(function ($q) {
                                    $q->where('data_source_type', 'excel')
                                        ->where(function ($q2) {
                                            $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                        });
                                });
                        })
                            ->where(function ($query) {
                                $query->where(function ($q) {
                                    $q->where('data_source_type', 'excel')
                                        ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                })->orWhere(function ($q) {
                                    $q->where('data_source_type', '!=', 'excel');
                                });
                            });
                    } else {
                        $q->whereNull('m1_date')->whereNull('m2_date');
                    }
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                        $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                            $q->where('name', $sale_type)
                                ->orWhere('on_trigger', $sale_type);
                        });
                    });
                }
            } else {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            }
        })
            ->when(! empty($salesPid), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->count();
    }

    private function getM2CompleteCount($salesPid, $startDate, $endDate, $isPestCompany, $isMortgageCompany, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $query = SalesMaster::whereNull('date_cancelled')
            ->when(! empty($salesPid), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where('initialStatusText', '=', 'Completed')
                                    ->orWhere(function ($sq) {
                                        $sq->whereNull('initialStatusText')
                                            ->where(function ($q) {
                                                $q->whereNotNull('m1_date')
                                                    ->orWhereNotNull('m2_date');
                                            });
                                    })
                                    ->orWhere(function ($sq) {
                                        $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                            ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                    });
                            });
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } elseif ($sale_type == 'Pending') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where(function ($subquery) {
                                    $subquery->whereNotNull('initialStatusText')
                                        ->where('initialStatusText', '!=', 'Completed');
                                })
                                    ->orWhere(function ($subquery) {
                                        $subquery->whereNull('initialStatusText')
                                            ->whereNull('m1_date')
                                            ->whereNull('m2_date');
                                    })
                                    ->orWhere(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->where(function ($q2) {
                                                $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                            });
                                    });
                            })
                                ->where(function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                    })->orWhere(function ($q) {
                                        $q->where('data_source_type', '!=', 'excel');
                                    });
                                });
                        } else {
                            $q->whereNull('m1_date')->whereNull('m2_date');
                        }
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            });

        if ($isPestCompany || $isMortgageCompany) {
            $query->where(function ($q) {
                $q->whereNotNull('m1_date')
                    ->orWhereNotNull('m2_date');
            });
        } else {
            $query->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNotNull('milestone_date');
            });
        }

        return $query->count();
    }

    private function getM2PendingCount($salesPid, $startDate, $endDate, $isPestCompany, $isMortgageCompany, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $query = SalesMaster::whereNull('date_cancelled')
            ->when(! empty($salesPid), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where('initialStatusText', '=', 'Completed')
                                    ->orWhere(function ($sq) {
                                        $sq->whereNull('initialStatusText')
                                            ->where(function ($q) {
                                                $q->whereNotNull('m1_date')
                                                    ->orWhereNotNull('m2_date');
                                            });
                                    })
                                    ->orWhere(function ($sq) {
                                        $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                            ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                    });
                            });
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } elseif ($sale_type == 'Pending') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where(function ($subquery) {
                                    $subquery->whereNotNull('initialStatusText')
                                        ->where('initialStatusText', '!=', 'Completed');
                                })
                                    ->orWhere(function ($subquery) {
                                        $subquery->whereNull('initialStatusText')
                                            ->whereNull('m1_date')
                                            ->whereNull('m2_date');
                                    })
                                    ->orWhere(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->where(function ($q2) {
                                                $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                            });
                                    });
                            })
                                ->where(function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                    })->orWhere(function ($q) {
                                        $q->where('data_source_type', '!=', 'excel');
                                    });
                                });
                        } else {
                            $q->whereNull('m1_date')->whereNull('m2_date');
                        }
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            });

        if ($isPestCompany) {
            $query->whereNotNull('customer_signoff')
                ->whereDoesntHave('salesProductMasterDetails', function ($q) {
                    $q->whereNotNull('milestone_date');
                });
        } elseif ($isMortgageCompany) {
            $query->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        } else {
            $query->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        }

        return $query->count();
    }

    private function getCancelledCount($salesPid, $startDate, $endDate, $clawBackPid, $isClawback, $milestone_date, $sale_type, $fieldRouteCount)
    {
        $query = SalesMaster::whereNotNull('date_cancelled')
            ->when(! empty($salesPid), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })
            ->when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
                if ($milestone_date) {
                    if ($sale_type == 'Cancel Date') {
                        $q->whereNotNull('date_cancelled');
                    } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where('initialStatusText', '=', 'Completed')
                                    ->orWhere(function ($sq) {
                                        $sq->whereNull('initialStatusText')
                                            ->where(function ($q) {
                                                $q->whereNotNull('m1_date')
                                                    ->orWhereNotNull('m2_date');
                                            });
                                    })
                                    ->orWhere(function ($sq) {
                                        $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                            ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                    });
                            });
                        } else {
                            $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                                $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                    $q->where('name', $sale_type)
                                        ->orWhere('on_trigger', $sale_type);
                                });
                            });
                        }
                    } elseif ($sale_type == 'Pending') {
                        if ($fieldRouteCount > 0) {
                            $q->where(function ($query) {
                                $query->where(function ($subquery) {
                                    $subquery->whereNotNull('initialStatusText')
                                        ->where('initialStatusText', '!=', 'Completed');
                                })
                                    ->orWhere(function ($subquery) {
                                        $subquery->whereNull('initialStatusText')
                                            ->whereNull('m1_date')
                                            ->whereNull('m2_date');
                                    })
                                    ->orWhere(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->where(function ($q2) {
                                                $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                            });
                                    });
                            })
                                ->where(function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('data_source_type', 'excel')
                                            ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                    })->orWhere(function ($q) {
                                        $q->where('data_source_type', '!=', 'excel');
                                    });
                                });
                        } else {
                            $q->whereNull('m1_date')->whereNull('m2_date');
                        }
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } else {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                }
            });

        if ($isClawback) {
            $query->whereIn('pid', $clawBackPid);
        } else {
            $query->whereNotIn('pid', $clawBackPid);
        }

        return $query->count();
    }

    private function calculateInstallRatio2($m2Complete, $totalSales)
    {
        if ($m2Complete > 0 && $totalSales > 0) {
            $install = round((($m2Complete / $totalSales) * 100), 5);

            return [
                'install' => $install.'%',
                'uninstall' => round(100 - $install, 5).'%',
            ];
        }

        return [
            'install' => '0%',
            'uninstall' => '100%',
        ];
    }

    private function getGraphData($userId, $startDate, $endDate)
    {
        $filteredDates = displayDateRanges($startDate, $endDate);
        $dates = getTriggerDatesForSample();
        $schemaName = array_column($dates, 'name');

        $earnings = UserCommission::selectRaw('DATE(updated_at) as date, schema_trigger, SUM(amount) as earnings')
            ->where(['user_id' => $userId, 'status' => 3])
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->whereIn('schema_trigger', $schemaName)
            ->groupBy(DB::raw('DATE(updated_at), schema_trigger'))
            ->get();

        $graphAmount = [];
        foreach ($filteredDates['data'] as $filteredDate) {
            $dateEarnings = $earnings->filter(function ($earning) use ($filteredDate) {
                return $earning->date >= $filteredDate['start'] && $earning->date <= $filteredDate['end'];
            });

            $schemaData = [];
            foreach ($schemaName as $schema) {
                $schemaData[$schema] = $dateEarnings->where('schema_trigger', $schema)->first()->earnings ?? 0;
            }

            $graphAmount[] = array_merge(
                ['date' => $filteredDate['label']],
                $schemaData
            );
        }

        return [
            'graph_amount' => $graphAmount,
            'total_amount' => [
                'm1_date' => '10',
                'm1_date_projected_amount' => '',
                'm2_date' => '10',
                'm2_date_projected_amount' => '',
            ],
        ];
    }

    public function projectedSaleEarnings(Request $request)
    {
        $userId = auth()->user()->id;
        // $userId = 77;
        $productId = isset($request->product_id) ? ($request->product_id) : null;
        [$startDate, $endDate] = getDateFromFilter($request);
        $milestone_date = filter_var($request->milestone_date, FILTER_VALIDATE_BOOLEAN);
        $sale_type = $request->sale_type ?? '';
        $fieldRouteCount = \App\Models\Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();

        $salesPids = SaleMasterProcess::where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId)->pluck('pid')->toArray();

        // Debug logging for troubleshooting
        \Log::info('Projected Sale Earnings Debug', [
            'user_id' => $userId,
            'sales_pids_count' => count($salesPids),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'milestone_date' => $milestone_date,
            'sale_type' => $sale_type,
            'product_id' => $productId,
        ]);
        $salesPid = SalesMaster::when(! empty($startDate), function ($q) use ($startDate, $endDate, $milestone_date, $sale_type, $fieldRouteCount) {
            if ($milestone_date) {
                if ($sale_type == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } elseif ($sale_type == 'Installed' || $sale_type == 'Installation Date') {
                    if ($fieldRouteCount > 0) {
                        $q->where(function ($query) {
                            $query->where('initialStatusText', '=', 'Completed')
                                ->orWhere(function ($sq) {
                                    $sq->whereNull('initialStatusText')
                                        ->where(function ($q) {
                                            $q->whereNotNull('m1_date')
                                                ->orWhereNotNull('m2_date');
                                        });
                                })
                                ->orWhere(function ($sq) {
                                    $sq->whereIn('data_source_type', ['excel', 'randcpest2__field_routes'])
                                        ->whereRaw("EXISTS (
                                                SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                                                WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                                            )");
                                });
                        });
                    } else {
                        $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                            $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                                $q->where('name', $sale_type)
                                    ->orWhere('on_trigger', $sale_type);
                            });
                        });
                    }
                } elseif ($sale_type == 'Pending') {
                    if ($fieldRouteCount > 0) {
                        $q->where(function ($query) {
                            $query->where(function ($subquery) {
                                $subquery->whereNotNull('initialStatusText')
                                    ->where('initialStatusText', '!=', 'Completed');
                            })
                                ->orWhere(function ($subquery) {
                                    $subquery->whereNull('initialStatusText')
                                        ->whereNull('m1_date')
                                        ->whereNull('m2_date');
                                })
                                ->orWhere(function ($q) {
                                    $q->where('data_source_type', 'excel')
                                        ->where(function ($q2) {
                                            $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                        });
                                });
                        })
                            ->where(function ($query) {
                                $query->where(function ($q) {
                                    $q->where('data_source_type', 'excel')
                                        ->whereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                })->orWhere(function ($q) {
                                    $q->where('data_source_type', '!=', 'excel');
                                });
                            });
                    } else {
                        $q->whereNull('m1_date')->whereNull('m2_date');
                    }
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($sale_type) {
                        $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($sale_type) {
                            $q->where('name', $sale_type)
                                ->orWhere('on_trigger', $sale_type);
                        });
                    });
                }
            } else {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            }
        })->whereIn('pid', $salesPids)->pluck('pid')->toArray();

        // Additional debug logging
        \Log::info('Projected Sale Earnings Filtering Debug', [
            'filtered_sales_pids_count' => count($salesPid),
            'filtered_sales_pids' => $salesPid,
        ]);

        /* $commissionArray = [];
        $commissions = ProjectionUserCommission::with('user.parentPositionDetail')->where(['user_id' => $userId])->when(!empty($salesPid), function($q) use ($salesPid) {
                $q->whereIn("pid", $salesPid);
            })->get();
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
                    'position_name' => isset($commission->user->parentPositionDetail->position_name) ? $commission->user->parentPositionDetail->position_name : NULL
                ];
            }
        } */

        $finalResponse = [];
        if ($productId) {
            $commissions = ProjectionUserCommission::with('user.parentPositionDetail')
                ->where('user_id', $userId)
                ->where(function ($q) use ($productId) {
                    $q->where('product_id', $productId)
                        ->orWhereNull('product_id');
                })
                ->when(! empty($salesPid), function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->get();
        } else {
            $commissions = ProjectionUserCommission::with('user.parentPositionDetail')->where(['user_id' => $userId])->when(! empty($salesPid), function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->get();
        }

        // Final debug logging for commission results
        \Log::info('Projected Sale Earnings Commission Debug', [
            'commissions_count' => count($commissions),
            'commission_pids' => $commissions->pluck('pid')->toArray(),
            'commission_amounts' => $commissions->pluck('amount')->toArray(),
        ]);

        $result = [];
        $subtotal = 0;
        foreach ($commissions as $commission) {
            $amountType = $commission->schema_name;
            if ($commission->value_type == 'reconciliation') {
                $amountType = $commission->value_type;
            }

            if (isset($result[$amountType])) {
                $result[$amountType] += $commission->amount ?? 0;
            } else {
                $result[$amountType] = $commission->amount ?? 0;
            }

            if (@$subtotal) {
                $subtotal += $commission->amount ?? 0;
            } else {
                $subtotal = $commission->amount ?? 0;
            }
        }

        $finalResponse['result'] = $result;
        $finalResponse['subtotal'] = $subtotal;

        // Add debug info when result is empty
        if (empty($result)) {
            // Check for projection sync issues
            $projectionFlags = SalesMaster::whereIn('pid', $salesPid)
                ->where('projected_commission', 1)
                ->count();

            $finalResponse['debug_info'] = [
                'sales_pids_count' => count($salesPids),
                'filtered_sales_count' => count($salesPid),
                'commissions_count' => count($commissions),
                'sales_with_projection_flag' => $projectionFlags,
                'sync_issue' => $projectionFlags > 0 && count($commissions) === 0,
                'message' => $projectionFlags > 0 && count($commissions) === 0
                    ? 'Projection sync issue detected. Sales have projected_commission=1 but no projection data. Run: php artisan fix:projection-sync'
                    : 'No projected earnings found. Check logs for detailed debug info.',
                'suggested_fix' => $projectionFlags > 0 && count($commissions) === 0
                    ? 'php artisan fix:projection-sync'
                    : 'php artisan syncSalesProjectionData:sync',
            ];
        }

        $this->successResponse('Successfully.', 'projectedSaleEarnings', $finalResponse);
    }

    public function migrateProductData()
    {
        if (! MilestoneSchema::first() && ! Products::first()) {
            $milestone = MilestoneSchema::create([
                'schema_name' => 'Default',
                'schema_description' => 'Default Milestone',
                'status' => 1,
            ]);

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $milestoneTriggers = [
                    [
                        'name' => 'Initial Service Date',
                        'on_trigger' => 'Initial Service Date',
                    ],
                    [
                        'name' => 'Service Completion Date',
                        'on_trigger' => 'Service Completion Date',
                    ],
                ];

                foreach ($milestoneTriggers as $milestoneTrigger) {
                    SchemaTriggerDate::create(['name' => $milestoneTrigger['name']]);
                    MilestoneSchemaTrigger::create([
                        'name' => $milestoneTrigger['name'],
                        'on_trigger' => @$milestoneTrigger['on_trigger'] ? $milestoneTrigger['on_trigger'] : null,
                        'milestone_schema_id' => $milestone->id,
                    ]);
                }

                $product = Products::create([
                    'name' => 'Default',
                    'product_id' => 'DBP',
                    'description' => 'Default Product',
                ]);

                ProductMilestoneHistories::updateOrCreate([
                    'product_id' => $product->id,
                    'milestone_schema_id' => 1,
                    'effective_date' => '2020-01-01',
                ], [
                    'clawback_exempt_on_ms_trigger_id' => 0,
                    'override_on_ms_trigger_id' => 2,
                    'product_redline' => null,
                ]);
            } else {
                $milestoneTriggers = [
                    [
                        'name' => 'M1 Date',
                        'on_trigger' => 'M1 Date',
                    ],
                    [
                        'name' => 'M2 Date',
                        'on_trigger' => 'M2 Date',
                    ],
                ];

                foreach ($milestoneTriggers as $milestoneTrigger) {
                    SchemaTriggerDate::create(['name' => $milestoneTrigger['name']]);
                    MilestoneSchemaTrigger::create([
                        'name' => $milestoneTrigger['name'],
                        'on_trigger' => @$milestoneTrigger['on_trigger'] ? $milestoneTrigger['on_trigger'] : null,
                        'milestone_schema_id' => $milestone->id,
                    ]);
                }

                $product = Products::create([
                    'name' => 'Default',
                    'product_id' => 'DBP',
                    'description' => 'Default Product',
                ]);

                ProductMilestoneHistories::updateOrCreate([
                    'product_id' => $product->id,
                    'milestone_schema_id' => 1,
                    'effective_date' => '2020-01-01',
                ], [
                    'clawback_exempt_on_ms_trigger_id' => 0,
                    'override_on_ms_trigger_id' => 2,
                    'product_redline' => '0',
                ]);
            }
        }
    }

    public function migratePositionData()
    {
        $companyProfile = CompanyProfile::first();
        $frequency = payFrequencySetting::first();
        $positionId = [1];
        if (! in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
            $positionId = [1, 2, 3];
        }

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $positions = Positions::whereNotIn('id', $positionId)->where('position_name', '!=', 'Super Admin')->get();
            foreach ($positions as $position) {
                $position->update(['is_selfgen' => '2']);

                if (! PositionPayFrequency::where('position_id', $position->id)->first()) {
                    PositionPayFrequency::create([
                        'position_id' => $position->id,
                        'frequency_type_id' => $frequency->frequency_type_id,
                    ]);
                }

                PositionProduct::updateOrCreate([
                    'product_id' => 1,
                    'position_id' => $position->id,
                ], []);

                if (! PositionWage::where('position_id', $position->id)->first()) {
                    PositionWage::create([
                        'position_id' => $position->id,
                        'wages_status' => 0,
                    ]);
                }
            }

            PositionCommission::query()->update(['product_id' => 1, 'core_position_id' => 2, 'tiers_id' => 0]);
            PositionCommissionUpfronts::query()->update(['product_id' => 1, 'core_position_id' => 2, 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
            PositionOverride::query()->update(['product_id' => 1, 'tiers_id' => 0]);
            PositionReconciliations::query()->update(['product_id' => 1]);
        } else {
            $positions = Positions::whereNotIn('id', $positionId)->where('position_name', '!=', 'Super Admin')->get();
            foreach ($positions as $position) {
                $corePosition = '';
                if (empty($position->parent_id) || $position->parent_id == 2) {
                    $position->update(['is_selfgen' => '2']);
                    $corePosition = 2;
                } else {
                    $position->update(['is_selfgen' => '3']);
                    $corePosition = 3;
                }

                if (! PositionPayFrequency::where('position_id', $position->id)->first()) {
                    PositionPayFrequency::create([
                        'position_id' => $position->id,
                        'frequency_type_id' => $frequency->frequency_type_id,
                    ]);
                }

                PositionProduct::updateOrCreate([
                    'product_id' => 1,
                    'position_id' => $position->id,
                ], []);

                if (! PositionWage::where('position_id', $position->id)->first()) {
                    PositionWage::create([
                        'position_id' => $position->id,
                        'wages_status' => 0,
                    ]);
                }

                PositionCommission::where('position_id', $position->id)->update(['product_id' => 1, 'core_position_id' => $corePosition, 'tiers_id' => 0]);
                PositionCommissionUpfronts::where('position_id', $position->id)->update(['product_id' => 1, 'core_position_id' => $corePosition, 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
                PositionOverride::where('position_id', $position->id)->update(['product_id' => 1, 'tiers_id' => 0]);
                PositionReconciliations::where('position_id', $position->id)->update(['product_id' => 1]);
            }
        }
    }

    public function migrateOnboardingData()
    {
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            OnboardingUserRedline::query()->update(['product_id' => 1, 'core_position_id' => 2, 'tiers_id' => 0]);
            OnboardingEmployeeUpfront::query()->update(['product_id' => 1, 'core_position_id' => 2, 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
            OnboardingEmployeeWithheld::query()->update(['product_id' => 1]);
            OnboardingEmployeeOverride::query()->update(['product_id' => 1, 'direct_tiers_id' => 0, 'indirect_tiers_id' => 0, 'office_tiers_id' => 0]);
            OnboardingEmployeeAdditionalOverride::query()->update(['product_id' => 1]);
        } else {
        }
    }

    public function migrateUserData()
    {
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            UserOrganizationHistory::query()->update(['product_id' => 1]);
            UserCommissionHistory::query()->update(['product_id' => 1, 'core_position_id' => 2, 'tiers_id' => 0]);
            UserUpfrontHistory::query()->update(['product_id' => 1, 'core_position_id' => 2, 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
            UserWithheldHistory::query()->update(['product_id' => 1]);
            UserOverrideHistory::query()->update(['product_id' => 1, 'direct_tiers_id' => 0, 'indirect_tiers_id' => 0, 'office_tiers_id' => 0]);
            UserAdditionalOfficeOverrideHistory::query()->update(['product_id' => 1]);
        } else {
            UserOrganizationHistory::query()->update(['product_id' => 1]);
            UserCommissionHistory::whereIn('position_id', [2, 3])->update(['product_id' => 1, 'core_position_id' => DB::raw('position_id'), 'tiers_id' => 0]);
            UserRedlines::whereIn('position_type', [2, 3])->update(['core_position_id' => DB::raw('position_type')]);
            UserUpfrontHistory::whereIn('position_id', [2, 3])->update(['product_id' => 1, 'core_position_id' => DB::raw('position_id'), 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
            UserWithheldHistory::query()->update(['product_id' => 1]);
            UserOverrideHistory::query()->update(['product_id' => 1, 'direct_tiers_id' => 0, 'indirect_tiers_id' => 0, 'office_tiers_id' => 0]);
            UserAdditionalOfficeOverrideHistory::query()->update(['product_id' => 1]);

            $redLines = UserRedlines::whereNotIn('position_type', [2, 3])->get();
            foreach ($redLines as $redLine) {
                $position = Positions::find($redLine->position_type);

                if ($position) {
                    $corePosition = '';
                    if (empty($position->parent_id) || $position->parent_id == 2) {
                        $corePosition = 2;
                    } else {
                        $corePosition = 3;
                    }

                    $redLine->update(['position_type' => $corePosition, 'core_position_id' => $corePosition, 'self_gen_user' => '0']);
                }
            }

            $userCommissionHistories = UserCommissionHistory::whereNotIn('position_id', [2, 3])->get();
            foreach ($userCommissionHistories as $userCommissionHistory) {
                $position = Positions::find($userCommissionHistory->position_id);

                if ($position) {
                    $corePosition = '';
                    if (empty($position->parent_id) || $position->parent_id == 2) {
                        $corePosition = 2;
                    } else {
                        $corePosition = 3;
                    }

                    $userCommissionHistory->update(['product_id' => 1, 'position_id' => $corePosition, 'core_position_id' => $corePosition, 'tiers_id' => 0]);
                }
            }

            $userUpFrontHistories = UserUpfrontHistory::whereNotIn('position_id', [2, 3])->get();
            foreach ($userUpFrontHistories as $userUpFrontHistory) {
                $position = Positions::find($userCommissionHistory->position_id);

                if ($position) {
                    $corePosition = '';
                    if (empty($position->parent_id) || $position->parent_id == 2) {
                        $corePosition = 2;
                    } else {
                        $corePosition = 3;
                    }

                    $userUpFrontHistory->update(['product_id' => 1, 'position_id' => $corePosition, 'core_position_id' => $corePosition, 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
                }
            }

            // SELF-GEN REDLINE
            $closerRedLines = UserRedlines::where(['position_type' => '2'])->get();
            foreach ($closerRedLines as $closerRedLine) {
                $replace = $closerRedLine;
                $replace['core_position_id'] = null;
                $replace['self_gen_user'] = 1;
                $replace['updater_id'] = auth()->user()->id;
                UserRedlines::create($replace->toArray());
            }
            UserRedlines::whereNotNull('core_position_id')->update(['self_gen_user' => '0']);

            // SELF-GEN COMMISSION
            $selfGenCommissions = UserSelfGenCommmissionHistory::groupBy('commission_effective_date')->get();
            foreach ($selfGenCommissions as $selfGenCommission) {
                UserCommissionHistory::create([
                    'user_id' => $selfGenCommission->user_id,
                    'updater_id' => auth()->user()->id,
                    'product_id' => 1,
                    'self_gen_user' => 1,
                    'commission' => $selfGenCommission->commission,
                    'commission_type' => $selfGenCommission->commission_type,
                    'commission_effective_date' => $selfGenCommission->commission_effective_date,
                    'tiers_id' => 0,
                    'core_position_id' => null,
                    'position_id' => $selfGenCommission->position_id,
                    'sub_position_id' => $selfGenCommission->sub_position_id,
                ]);
            }

            // SELF-GEN UPFRONT
            $upFronts = UserUpfrontHistory::where('self_gen_user', '1')->get();
            foreach ($upFronts as $upFront) {
                $upFrontType = $upFront->upfront_sale_type;
                $upFrontAmount = $upFront->upfront_pay_amount;
                $prevUpfront = UserUpfrontHistory::where(['user_id' => $upFront->user_id, 'self_gen_user' => '0'])->where('upfront_effective_date', '<=', $upFront->upfront_effective_date)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($prevUpfront) {
                    if ($prevUpfront->upfront_sale_type == 'percent') {
                        if ($upFrontType == 'percent' && $prevUpfront->upfront_pay_amount > $upFrontAmount) {
                            $upFrontAmount = $prevUpfront->upfront_pay_amount;
                            $upFrontType = $prevUpfront->upfront_sale_type;
                        } elseif ($upFrontType == 'per kw' || $upFrontType == 'per sale') {
                            $upFrontAmount = $prevUpfront->upfront_pay_amount;
                            $upFrontType = $prevUpfront->upfront_sale_type;
                        }
                    } elseif ($prevUpfront->upfront_sale_type == 'per kw') {
                        if ($upFrontType == 'per kw' && $prevUpfront->upfront_pay_amount > $upFrontAmount) {
                            $upFrontAmount = $prevUpfront->upfront_pay_amount;
                            $upFrontType = $prevUpfront->upfront_sale_type;
                        } elseif ($upFrontType == 'per sale') {
                            $upFrontAmount = $prevUpfront->upfront_pay_amount;
                            $upFrontType = $prevUpfront->upfront_sale_type;
                        }
                    } elseif ($prevUpfront->upfront_sale_type == 'per sale') {
                        if ($upFrontType == 'per sale' && $prevUpfront->upfront_pay_amount > $upFrontAmount) {
                            $upFrontAmount = $prevUpfront->upfront_pay_amount;
                            $upFrontType = $prevUpfront->upfront_sale_type;
                        }
                    }
                }

                UserUpfrontHistory::create([
                    'user_id' => $upFront->user_id,
                    'updater_id' => auth()->user()->id,
                    'product_id' => 1,
                    'milestone_schema_id' => 1,
                    'milestone_schema_trigger_id' => 1,
                    'self_gen_user' => 1,
                    'upfront_pay_amount' => $upFrontAmount,
                    'upfront_sale_type' => $upFrontType,
                    'upfront_effective_date' => $upFront->upfront_effective_date,
                    'position_id' => $upFront->position_id,
                    'core_position_id' => null,
                    'sub_position_id' => $upFront->sub_position_id,
                    'tiers_id' => 0,
                ]);
            }
            UserUpfrontHistory::whereNotNull('core_position_id')->update(['self_gen_user' => '0']);
        }
    }

    public function migrateSaleData_old()
    {
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            SalesMaster::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            LegacyApiNullData::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            UserCommission::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
            UserCommission::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
            ClawbackSettlement::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
            ClawbackSettlement::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
            UserCommissionLock::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
            UserCommissionLock::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
            ClawbackSettlementLock::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
            ClawbackSettlementLock::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
            UserOverrides::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            UserOverridesLock::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            ClawbackSettlement::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);
            ClawbackSettlementLock::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);

            $productId = 1;
            $milestoneId = 1;
            SaleProductMaster::truncate();
            $sales = SalesMaster::with('salesMasterProcess')->get();
            foreach ($sales as $sale) {
                $pid = $sale->pid;
                $closer1Id = $sale->salesMasterProcess->closer1_id;
                $closer2Id = $sale->salesMasterProcess->closer2_id;

                if ($closer1Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 1,
                        'milestone_date' => $sale->m1_date,
                        'type' => 'm1',
                        'is_last_date' => 0,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m1_date ? 0 : 1,
                        'closer1_id' => $closer1Id,
                    ]);

                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 2,
                        'milestone_date' => $sale->m2_date,
                        'type' => 'm2',
                        'is_last_date' => 1,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m2_date ? 0 : 1,
                        'closer1_id' => $closer1Id,
                    ]);
                }

                if ($closer2Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 1,
                        'milestone_date' => $sale->m1_date,
                        'type' => 'm1',
                        'is_last_date' => 0,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m1_date ? 0 : 1,
                        'closer1_id' => $closer2Id,
                    ]);

                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 2,
                        'milestone_date' => $sale->m2_date,
                        'type' => 'm2',
                        'is_last_date' => 1,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m2_date ? 0 : 1,
                        'closer1_id' => $closer2Id,
                    ]);
                }

                $this->manageDataForDisplay($pid);
            }
        } else {
            SalesMaster::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            LegacyApiNullData::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            UserCommission::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
            UserCommission::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
            ClawbackSettlement::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
            ClawbackSettlement::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
            UserCommissionLock::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
            UserCommissionLock::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
            ClawbackSettlementLock::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
            ClawbackSettlementLock::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
            UserOverrides::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            UserOverridesLock::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
            ClawbackSettlement::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);
            ClawbackSettlementLock::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);

            $productId = 1;
            $milestoneId = 1;
            SaleProductMaster::truncate();
            $sales = SalesMaster::with('salesMasterProcess')->get();
            foreach ($sales as $sale) {
                $pid = $sale->pid;
                $closer1Id = $sale->salesMasterProcess->closer1_id;
                $setter1Id = $sale->salesMasterProcess->setter1_id;
                $closer2Id = $sale->salesMasterProcess->closer2_id;
                $setter2Id = $sale->salesMasterProcess->setter2_id;

                if ($closer1Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 1,
                        'milestone_date' => $sale->m1_date,
                        'type' => 'm1',
                        'is_last_date' => 0,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m1_date ? 0 : 1,
                        'closer1_id' => $closer1Id,
                    ]);

                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 2,
                        'milestone_date' => $sale->m2_date,
                        'type' => 'm2',
                        'is_last_date' => 1,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m2_date ? 0 : 1,
                        'closer1_id' => $closer1Id,
                    ]);
                }

                if ($setter1Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 1,
                        'milestone_date' => $sale->m1_date,
                        'type' => 'm1',
                        'is_last_date' => 0,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m1_date ? 0 : 1,
                        'setter1_id' => $setter1Id,
                    ]);

                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 2,
                        'milestone_date' => $sale->m2_date,
                        'type' => 'm2',
                        'is_last_date' => 1,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m2_date ? 0 : 1,
                        'setter1_id' => $setter1Id,
                    ]);
                }

                if ($closer2Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 1,
                        'milestone_date' => $sale->m1_date,
                        'type' => 'm1',
                        'is_last_date' => 0,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m1_date ? 0 : 1,
                        'closer1_id' => $closer2Id,
                    ]);

                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 2,
                        'milestone_date' => $sale->m2_date,
                        'type' => 'm2',
                        'is_last_date' => 1,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m2_date ? 0 : 1,
                        'closer1_id' => $closer2Id,
                    ]);
                }

                if ($setter2Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 1,
                        'milestone_date' => $sale->m1_date,
                        'type' => 'm1',
                        'is_last_date' => 0,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m1_date ? 0 : 1,
                        'setter2_id' => $setter2Id,
                    ]);

                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 2,
                        'milestone_date' => $sale->m2_date,
                        'type' => 'm2',
                        'is_last_date' => 1,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m2_date ? 0 : 1,
                        'setter2_id' => $setter2Id,
                    ]);
                }

                $this->manageDataForDisplay($pid);
            }
        }
    }

    public function migrateSaleData()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');
        $pid = null;
        try {
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                SalesMaster::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                LegacyApiNullData::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                UserCommission::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
                UserCommission::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
                ClawbackSettlement::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
                ClawbackSettlement::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
                UserCommissionLock::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
                UserCommissionLock::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
                ClawbackSettlementLock::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'Initial Service Date', 'schema_trigger' => 'Initial Service Date', 'schema_type' => 'm1']);
                ClawbackSettlementLock::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'Service Completion Date', 'schema_trigger' => 'Service Completion Date', 'schema_type' => 'm2', 'is_last' => '1']);
                UserOverrides::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                UserOverridesLock::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                ClawbackSettlement::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);
                ClawbackSettlementLock::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);

                $productId = 1;
                $milestoneId = 1;
                SaleProductMaster::truncate();
                // $sales = SalesMaster::with('salesMasterProcess')->get();
                SalesMaster::with('salesMasterProcess')->chunk(100, function ($sales) use ($productId, $milestoneId) {
                    foreach ($sales as $sale) {
                        $pid = $sale->pid;
                        $closer1Id = $sale->salesMasterProcess->closer1_id ?? null;
                        $closer2Id = $sale->salesMasterProcess->closer2_id ?? null;

                        if ($closer1Id) {
                            SaleProductMaster::insert([
                                [
                                    'pid' => $pid,
                                    'product_id' => $productId,
                                    'milestone_id' => $milestoneId,
                                    'milestone_schema_id' => 1,
                                    'milestone_date' => $sale->m1_date,
                                    'type' => 'm1',
                                    'is_last_date' => 0,
                                    'is_exempted' => 0,
                                    'is_projected' => $sale->m1_date ? 0 : 1,
                                    'closer1_id' => $closer1Id,
                                ],
                                [
                                    'pid' => $pid,
                                    'product_id' => $productId,
                                    'milestone_id' => $milestoneId,
                                    'milestone_schema_id' => 2,
                                    'milestone_date' => $sale->m2_date,
                                    'type' => 'm2',
                                    'is_last_date' => 1,
                                    'is_exempted' => 0,
                                    'is_projected' => $sale->m2_date ? 0 : 1,
                                    'closer1_id' => $closer1Id,
                                ],
                            ]);
                        }

                        if ($closer2Id) {
                            SaleProductMaster::insert([
                                [
                                    'pid' => $pid,
                                    'product_id' => $productId,
                                    'milestone_id' => $milestoneId,
                                    'milestone_schema_id' => 1,
                                    'milestone_date' => $sale->m1_date,
                                    'type' => 'm1',
                                    'is_last_date' => 0,
                                    'is_exempted' => 0,
                                    'is_projected' => $sale->m1_date ? 0 : 1,
                                    'closer1_id' => $closer2Id,
                                ],
                                [
                                    'pid' => $pid,
                                    'product_id' => $productId,
                                    'milestone_id' => $milestoneId,
                                    'milestone_schema_id' => 2,
                                    'milestone_date' => $sale->m2_date,
                                    'type' => 'm2',
                                    'is_last_date' => 1,
                                    'is_exempted' => 0,
                                    'is_projected' => $sale->m2_date ? 0 : 1,
                                    'closer1_id' => $closer2Id,
                                ],
                            ]);
                        }

                        $this->manageDataForDisplay($pid);
                    }
                });
            } else {
                SalesMaster::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                LegacyApiNullData::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                UserCommission::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
                UserCommission::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
                ClawbackSettlement::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
                ClawbackSettlement::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
                UserCommissionLock::where('amount_type', 'm1')->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
                UserCommissionLock::whereIn('amount_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
                ClawbackSettlementLock::where(['type' => 'commission', 'adders_type' => 'm1'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 1, 'schema_name' => 'M1 Date', 'schema_trigger' => 'M1 Date', 'schema_type' => 'm1']);
                ClawbackSettlementLock::where(['type' => 'commission'])->whereIn('adders_type', ['m2', 'm2 update'])->update(['product_id' => 1, 'product_code' => 'DBP', 'milestone_schema_id' => 2, 'schema_name' => 'M2 Date', 'schema_trigger' => 'M2 Date', 'schema_type' => 'm2', 'is_last' => '1']);
                UserOverrides::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                UserOverridesLock::query()->update(['product_id' => 1, 'product_code' => 'DBP']);
                ClawbackSettlement::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);
                ClawbackSettlementLock::where(['type' => 'overrides'])->update(['product_id' => 1, 'product_code' => 'DBP']);

                $productId = 1;
                $milestoneId = 1;
                SaleProductMaster::truncate();
                $sales = SalesMaster::with('salesMasterProcess')->get();
                foreach ($sales as $sale) {
                    $pid = $sale->pid;
                    $closer1Id = $sale->salesMasterProcess->closer1_id;
                    $setter1Id = $sale->salesMasterProcess->setter1_id;
                    $closer2Id = $sale->salesMasterProcess->closer2_id;
                    $setter2Id = $sale->salesMasterProcess->setter2_id;

                    // if ($closer1Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 1,
                        'milestone_date' => $sale->m1_date,
                        'type' => 'm1',
                        'is_last_date' => 0,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m1_date ? 0 : 1,
                        'closer1_id' => $closer1Id,
                    ]);

                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestoneId,
                        'milestone_schema_id' => 2,
                        'milestone_date' => $sale->m2_date,
                        'type' => 'm2',
                        'is_last_date' => 1,
                        'is_exempted' => 0,
                        'is_projected' => $sale->m2_date ? 0 : 1,
                        'closer1_id' => $closer1Id,
                    ]);
                    // }

                    if ($setter1Id) {
                        SaleProductMaster::create([
                            'pid' => $pid,
                            'product_id' => $productId,
                            'milestone_id' => $milestoneId,
                            'milestone_schema_id' => 1,
                            'milestone_date' => $sale->m1_date,
                            'type' => 'm1',
                            'is_last_date' => 0,
                            'is_exempted' => 0,
                            'is_projected' => $sale->m1_date ? 0 : 1,
                            'setter1_id' => $setter1Id,
                        ]);

                        SaleProductMaster::create([
                            'pid' => $pid,
                            'product_id' => $productId,
                            'milestone_id' => $milestoneId,
                            'milestone_schema_id' => 2,
                            'milestone_date' => $sale->m2_date,
                            'type' => 'm2',
                            'is_last_date' => 1,
                            'is_exempted' => 0,
                            'is_projected' => $sale->m2_date ? 0 : 1,
                            'setter1_id' => $setter1Id,
                        ]);
                    }

                    if ($closer2Id) {
                        SaleProductMaster::create([
                            'pid' => $pid,
                            'product_id' => $productId,
                            'milestone_id' => $milestoneId,
                            'milestone_schema_id' => 1,
                            'milestone_date' => $sale->m1_date,
                            'type' => 'm1',
                            'is_last_date' => 0,
                            'is_exempted' => 0,
                            'is_projected' => $sale->m1_date ? 0 : 1,
                            'closer1_id' => $closer2Id,
                        ]);

                        SaleProductMaster::create([
                            'pid' => $pid,
                            'product_id' => $productId,
                            'milestone_id' => $milestoneId,
                            'milestone_schema_id' => 2,
                            'milestone_date' => $sale->m2_date,
                            'type' => 'm2',
                            'is_last_date' => 1,
                            'is_exempted' => 0,
                            'is_projected' => $sale->m2_date ? 0 : 1,
                            'closer1_id' => $closer2Id,
                        ]);
                    }

                    if ($setter2Id) {
                        SaleProductMaster::create([
                            'pid' => $pid,
                            'product_id' => $productId,
                            'milestone_id' => $milestoneId,
                            'milestone_schema_id' => 1,
                            'milestone_date' => $sale->m1_date,
                            'type' => 'm1',
                            'is_last_date' => 0,
                            'is_exempted' => 0,
                            'is_projected' => $sale->m1_date ? 0 : 1,
                            'setter2_id' => $setter2Id,
                        ]);

                        SaleProductMaster::create([
                            'pid' => $pid,
                            'product_id' => $productId,
                            'milestone_id' => $milestoneId,
                            'milestone_schema_id' => 2,
                            'milestone_date' => $sale->m2_date,
                            'type' => 'm2',
                            'is_last_date' => 1,
                            'is_exempted' => 0,
                            'is_projected' => $sale->m2_date ? 0 : 1,
                            'setter2_id' => $setter2Id,
                        ]);
                    }

                    $this->manageDataForDisplay($pid);
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error inserting data for PID {$pid}: ".$e->getMessage());
            dd($e->getMessage(), $e->getFile(), $e->getLine());
            // continue; // Skip this iteration
        }
    }

    public function migrateUserAgreementData()
    {
        $users = User::get();
        foreach ($users as $user) {
            $clawbackJobRun = \App\Models\ClawbackJob::where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->first();

            $clawback_amount = null;
            if (! is_null($clawbackJobRun)) {
                $clawbackDt = Carbon::parse($clawbackJobRun->created_at);
                $clawbackSettlement = ClawbackSettlement::where(['user_id' => $request->user()->id, 'job_id' => $clawbackJobRun->id])->first();
                if (! is_null($clawbackSettlement)) {
                    $clawback_amount = $clawbackSettlement->clawback;
                }
            }
            if (UserAgreementHistory::where(['user_id' => $user->id])->first()) {
                UserAgreementHistory::where(['user_id' => $user->id])->update([
                    'period_of_agreement' => $starDate,
                ]);
            } else {
                UserAgreementHistory::create([
                    'user_id' => $user->id,
                    'updater_id' => 1,
                    'probation_period' => $user->probation_period,
                    'offer_include_bonus' => $user->offer_include_bonus ? $user->offer_include_bonus : 0,
                    'hiring_bonus_amount' => $user->hiring_bonus_amount,
                    'date_to_be_paid' => $user->date_to_be_paid,
                    'period_of_agreement' => $starDate,
                    'end_date' => $user->end_date,
                    'offer_expiry_date' => $user->offer_expiry_date,
                    'hired_by_uid' => $onboardingEmployee?->hired_by_uid,
                    'hiring_signature' => $onboardingEmployee?->hiring_signature,
                ]);
            }
        }
    }

    public function migrateSolarData()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');
        DB::beginTransaction();
        try {
            $companyProfile = CompanyProfile::first();
            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                $frequency = payFrequencySetting::first();
                $positionId = [1];
                // if (!in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
                //     $positionId = [1, 2, 3];
                // }
                $positions = Positions::whereNotIn('id', $positionId)->where('position_name', '!=', 'Super Admin')->get();
                foreach ($positions as $position) {
                    $corePosition = '';
                    if (empty($position->parent_id) || $position->parent_id == 2) {
                        if ($position->id == 2) {
                            $position->update(['is_selfgen' => '2']);
                            $corePosition = 2;
                        } elseif ($position->id == 3) {
                            $position->update(['is_selfgen' => '3']);
                            $corePosition = 3;
                        } else {
                            $position->update(['is_selfgen' => '2']);
                            $corePosition = 2;
                        }
                    } else {
                        $position->update(['is_selfgen' => '3']);
                        $corePosition = 3;
                    }

                    if (! PositionPayFrequency::where('position_id', $position->id)->first()) {
                        PositionPayFrequency::create([
                            'position_id' => $position->id,
                            'frequency_type_id' => $frequency->frequency_type_id,
                        ]);
                    }

                    PositionProduct::updateOrCreate([
                        'product_id' => 1,
                        'position_id' => $position->id,
                    ], []);

                    if (! PositionWage::where('position_id', $position->id)->first()) {
                        PositionWage::create([
                            'position_id' => $position->id,
                            'wages_status' => 0,
                        ]);
                    }

                    PositionCommission::where('position_id', $position->id)->update(['product_id' => 1, 'core_position_id' => $corePosition, 'tiers_id' => 0]);
                    PositionCommissionUpfronts::where('position_id', $position->id)->update(['product_id' => 1, 'core_position_id' => $corePosition, 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
                    PositionOverride::where('position_id', $position->id)->update(['product_id' => 1, 'tiers_id' => 0]);
                    PositionReconciliations::where('position_id', $position->id)->update(['product_id' => 1]);
                }

                $migratedPositions = [];
                $selfGens = [2, 3, null];
                $selfGenPositions = UserOrganizationHistory::where(['self_gen_accounts' => 1])->whereNotNull('sub_position_id')->groupBy('sub_position_id')->pluck('sub_position_id')->toArray();
                $nonHiredEmployees = OnboardingEmployees::where(['self_gen_accounts' => 1])->whereNotNull('sub_position_id')->groupBy('sub_position_id')->pluck('sub_position_id')->toArray();
                $selfGenPositions = array_unique(array_merge($selfGenPositions, $nonHiredEmployees));
                foreach ($selfGenPositions as $selfGenPosition) {
                    $position = Positions::find($selfGenPosition)->toArray();
                    if ($position) {
                        $position['position_name'] = $position['position_name'].' - SelfGen';
                        $position['is_selfgen'] = 1;
                        $newPosition = Positions::updateOrCreate($position);
                        $newPositionId = $newPosition->id;
                        $migratedPositions[$position['id']] = $newPosition;

                        if (! PositionPayFrequency::where('position_id', $newPositionId)->first()) {
                            PositionPayFrequency::create([
                                'position_id' => $newPositionId,
                                'frequency_type_id' => $frequency->frequency_type_id,
                            ]);
                        }

                        PositionProduct::updateOrCreate([
                            'product_id' => 1,
                            'position_id' => $newPositionId,
                        ], []);

                        if (! PositionWage::where('position_id', $newPositionId)->first()) {
                            PositionWage::create([
                                'position_id' => $newPositionId,
                                'wages_status' => 0,
                            ]);
                        }

                        $positionCommission = PositionCommission::where('position_id', $position['id'])->first();
                        if ($positionCommission) {
                            $positionCommission['position_id'] = $newPositionId;
                            $positionCommission['core_position_id'] = 2;
                            $positionCommission['self_gen_user'] = 0;
                            PositionCommission::create($positionCommission->toArray());

                            $positionCommission['position_id'] = $newPositionId;
                            $positionCommission['core_position_id'] = 3;
                            $positionCommission['self_gen_user'] = 0;
                            PositionCommission::create($positionCommission->toArray());

                            $positionCommission['position_id'] = $newPositionId;
                            $positionCommission['core_position_id'] = null;
                            $positionCommission['self_gen_user'] = 1;
                            PositionCommission::create($positionCommission->toArray());
                        }

                        $positionCommissionUpFront = PositionCommissionUpfronts::where('position_id', $position['id'])->first();
                        if ($positionCommissionUpFront) {
                            $positionCommissionUpFront['position_id'] = $newPositionId;
                            $positionCommissionUpFront['core_position_id'] = 2;
                            $positionCommissionUpFront['self_gen_user'] = 0;
                            PositionCommissionUpfronts::create($positionCommissionUpFront->toArray());

                            $positionCommissionUpFront['position_id'] = $newPositionId;
                            $positionCommissionUpFront['core_position_id'] = 3;
                            $positionCommissionUpFront['self_gen_user'] = 0;
                            PositionCommissionUpfronts::create($positionCommissionUpFront->toArray());

                            $positionCommissionUpFront['position_id'] = $newPositionId;
                            $positionCommissionUpFront['core_position_id'] = null;
                            $positionCommissionUpFront['self_gen_user'] = 1;
                            PositionCommissionUpfronts::create($positionCommissionUpFront->toArray());
                        }

                        $positionOverrides = PositionOverride::where('position_id', $position['id'])->get();
                        foreach ($positionOverrides as $positionOverride) {
                            $positionOverride['position_id'] = $newPositionId;
                            PositionOverride::create($positionOverride->toArray());
                        }

                        $positionReconciliation = PositionReconciliations::where('position_id', $position['id'])->first();
                        if ($positionReconciliation) {
                            $positionReconciliation['position_id'] = $newPositionId;
                            PositionReconciliations::create($positionReconciliation->toArray());
                        }

                        UserOrganizationHistory::where(['self_gen_accounts' => 1, 'sub_position_id' => $position['id']])->update(['position_id' => 2, 'sub_position_id' => $newPositionId]);
                    }
                }

                $nonHiredEmployees = OnboardingEmployees::with('positionDetail')->where(['self_gen_accounts' => 1])->whereNotNull('sub_position_id')->get();
                foreach ($nonHiredEmployees as $nonHiredEmployee) {
                    $newPosition = Positions::where('position_name', $nonHiredEmployee->positionDetail->position_name.' - SelfGen')->first();
                    $newPositionId = $newPosition->id;
                    $onboardingRedLines = OnboardingUserRedline::where(['user_id' => $nonHiredEmployee->id])->get();
                    foreach ($selfGens as $selfGen) {
                        $check = empty($selfGen) ? 2 : $selfGen;
                        $redLine = $onboardingRedLines->where('position_id', $check)->first();
                        if ($redLine) {
                            OnboardingEmployeeRedline::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $selfGen], [
                                'user_id' => $nonHiredEmployee->id,
                                'core_position_id' => $selfGen,
                                'position_id' => $newPositionId,
                                'self_gen_user' => empty($selfGen) ? 1 : 0,
                                'updater_id' => auth()->user()->id,
                                'redline' => $redLine['redline'] ?? null,
                                'redline_type' => $redLine['redline_type'] ?? 'per watt',
                                'redline_amount_type' => $redLine['redline_amount_type'] ?? 'Fixed',
                            ]);

                            OnboardingUserRedline::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $selfGen], [
                                'user_id' => $nonHiredEmployee->id,
                                'product_id' => 1,
                                'core_position_id' => $selfGen,
                                'position_id' => $newPositionId,
                                'self_gen_user' => empty($selfGen) ? 1 : 0,
                                'updater_id' => auth()->user()->id,
                                'commission' => $redLine['commission'] ?? 0,
                                'commission_type' => $redLine['commission_type'] ?? null,
                                'tiers_id' => $redLine['tiers_id'] ?? 0,
                            ]);

                            OnboardingEmployeeUpfront::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $selfGen], [
                                'user_id' => $nonHiredEmployee->id,
                                'product_id' => 1,
                                'milestone_schema_id' => 1,
                                'milestone_schema_trigger_id' => 1,
                                'core_position_id' => $selfGen,
                                'position_id' => $newPositionId,
                                'self_gen_user' => empty($selfGen) ? 1 : 0,
                                'updater_id' => auth()->user()->id,
                                'upfront_pay_amount' => $redLine['upfront_pay_amount'] ?? 0,
                                'upfront_sale_type' => $redLine['upfront_sale_type'] ?? 'per sale',
                                'tiers_id' => $redLine['tiers_id'] ?? 0,
                            ]);
                        } elseif (empty($selfGen) && ($nonHiredEmployee->self_gen_redline || $nonHiredEmployee->self_gen_commission || $nonHiredEmployee->self_gen_upfront_amount)) {
                            OnboardingEmployeeRedline::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $selfGen], [
                                'user_id' => $nonHiredEmployee->id,
                                'core_position_id' => $selfGen,
                                'position_id' => $newPositionId,
                                'self_gen_user' => empty($selfGen) ? 1 : 0,
                                'updater_id' => auth()->user()->id,
                                'redline' => $nonHiredEmployee->self_gen_redline ?? null,
                                'redline_type' => $nonHiredEmployee->self_gen_redline_type ?? 'per watt',
                                'redline_amount_type' => $nonHiredEmployee->self_gen_redline_amount_type ?? 'Fixed',
                            ]);

                            OnboardingUserRedline::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $selfGen], [
                                'user_id' => $nonHiredEmployee->id,
                                'product_id' => 1,
                                'core_position_id' => $selfGen,
                                'position_id' => $newPositionId,
                                'self_gen_user' => empty($selfGen) ? 1 : 0,
                                'updater_id' => auth()->user()->id,
                                'commission' => $nonHiredEmployee->self_gen_commission ?? 0,
                                'commission_type' => $nonHiredEmployee->self_gen_commission_type ?? null,
                                'tiers_id' => 0,
                            ]);

                            OnboardingEmployeeUpfront::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $selfGen], [
                                'user_id' => $nonHiredEmployee->id,
                                'product_id' => 1,
                                'milestone_schema_id' => 1,
                                'milestone_schema_trigger_id' => 1,
                                'core_position_id' => $selfGen,
                                'position_id' => $newPositionId,
                                'self_gen_user' => empty($selfGen) ? 1 : 0,
                                'updater_id' => auth()->user()->id,
                                'upfront_pay_amount' => $nonHiredEmployee->self_gen_upfront_amount ?? 0,
                                'upfront_sale_type' => $nonHiredEmployee->self_gen_upfront_type ?? 'per sale',
                                'tiers_id' => 0,
                            ]);
                        }
                    }

                    if (OnboardingEmployeeOverride::where(['user_id' => $nonHiredEmployee->id])->first()) {
                        OnboardingEmployeeOverride::updateOrCreate(['user_id' => $nonHiredEmployee->id], ['product_id' => 1, 'position_id' => $newPositionId]);
                    }

                    $nonHiredEmployee->sub_position_id = $newPositionId;
                    $nonHiredEmployee->save();
                }

                $nonHiredEmployees = OnboardingEmployees::with('positionDetail')->where(['self_gen_accounts' => 0])->whereNotNull('sub_position_id')->get();
                foreach ($nonHiredEmployees as $nonHiredEmployee) {
                    $onboardingRedLine = OnboardingUserRedline::where(['user_id' => $nonHiredEmployee->id])->first();
                    if ($onboardingRedLine) {
                        OnboardingEmployeeRedline::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $nonHiredEmployee->positionDetail->is_selfgen], [
                            'user_id' => $nonHiredEmployee->id,
                            'position_id' => $nonHiredEmployee->sub_position_id,
                            'self_gen_user' => 0,
                            'updater_id' => auth()->user()->id,
                            'redline' => $onboardingRedLine['redline'] ?? null,
                            'redline_type' => $onboardingRedLine['redline_type'] ?? 'per watt',
                            'redline_amount_type' => $onboardingRedLine['redline_amount_type'] ?? 'Fixed',
                        ]);

                        $onboardingRedLine->update([
                            'core_position_id' => $nonHiredEmployee->positionDetail->is_selfgen,
                            'product_id' => 1,
                            'position_id' => $nonHiredEmployee->sub_position_id,
                            'self_gen_user' => 0,
                            'updater_id' => auth()->user()->id,
                            'tiers_id' => $onboardingRedLine['tiers_id'] ?? 0,
                        ]);

                        OnboardingEmployeeUpfront::updateOrCreate(['user_id' => $nonHiredEmployee->id, 'core_position_id' => $nonHiredEmployee->positionDetail->is_selfgen], [
                            'user_id' => $nonHiredEmployee->id,
                            'product_id' => 1,
                            'milestone_schema_id' => 1,
                            'milestone_schema_trigger_id' => 1,
                            'position_id' => $nonHiredEmployee->sub_position_id,
                            'self_gen_user' => 0,
                            'updater_id' => auth()->user()->id,
                            'upfront_pay_amount' => $onboardingRedLine['upfront_pay_amount'] ?? 0,
                            'upfront_sale_type' => $onboardingRedLine['upfront_sale_type'] ?? 'per sale',
                            'tiers_id' => $onboardingRedLine['tiers_id'] ?? 0,
                        ]);
                    }

                    if (OnboardingEmployeeOverride::where(['user_id' => $nonHiredEmployee->id])->first()) {
                        OnboardingEmployeeOverride::updateOrCreate(['user_id' => $nonHiredEmployee->id], ['product_id' => 1, 'position_id' => $nonHiredEmployee->positionDetail->id]);
                    }
                }

                UserOrganizationHistory::query()->update(['product_id' => 1]);
                UserCommissionHistory::whereIn('position_id', [2, 3])->update(['product_id' => 1, 'core_position_id' => DB::raw('position_id'), 'tiers_id' => 0]);
                UserRedlines::whereIn('position_type', [2, 3])->update(['core_position_id' => DB::raw('position_type')]);
                UserUpfrontHistory::whereIn('position_id', [2, 3])->update(['product_id' => 1, 'core_position_id' => DB::raw('position_id'), 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
                UserWithheldHistory::query()->update(['product_id' => 1]);
                UserOverrideHistory::query()->update(['product_id' => 1, 'direct_tiers_id' => 0, 'indirect_tiers_id' => 0, 'office_tiers_id' => 0]);
                UserAdditionalOfficeOverrideHistory::query()->update(['product_id' => 1]);

                $redLines = UserRedlines::whereNotIn('position_type', [2, 3])->get();
                foreach ($redLines as $redLine) {
                    $position = Positions::find($redLine->position_type);

                    if ($position) {
                        $corePosition = '';
                        if (empty($position->parent_id) || $position->parent_id == 2) {
                            $corePosition = 2;
                        } else {
                            $corePosition = 3;
                        }

                        $redLine->update(['position_type' => $corePosition, 'core_position_id' => $corePosition, 'self_gen_user' => '0']);
                    }
                }

                $userCommissionHistories = UserCommissionHistory::whereNotIn('position_id', [2, 3])->get();
                foreach ($userCommissionHistories as $userCommissionHistory) {
                    $position = Positions::find($userCommissionHistory->position_id);

                    if ($position) {
                        $corePosition = '';
                        if (empty($position->parent_id) || $position->parent_id == 2) {
                            $corePosition = 2;
                        } else {
                            $corePosition = 3;
                        }

                        $userCommissionHistory->update(['product_id' => 1, 'position_id' => $corePosition, 'core_position_id' => $corePosition, 'tiers_id' => 0]);
                    }
                }

                $userUpFrontHistories = UserUpfrontHistory::whereNotIn('position_id', [2, 3])->get();
                foreach ($userUpFrontHistories as $userUpFrontHistory) {
                    $position = Positions::find($userCommissionHistory->position_id);

                    if ($position) {
                        $corePosition = '';
                        if (empty($position->parent_id) || $position->parent_id == 2) {
                            $corePosition = 2;
                        } else {
                            $corePosition = 3;
                        }

                        $userUpFrontHistory->update(['product_id' => 1, 'position_id' => $corePosition, 'core_position_id' => $corePosition, 'milestone_schema_id' => 1, 'milestone_schema_trigger_id' => 1, 'tiers_id' => 0]);
                    }
                }

                // SELF-GEN REDLINE
                $closerRedLines = UserRedlines::where(['position_type' => '2'])->get();
                foreach ($closerRedLines as $closerRedLine) {
                    $replace = $closerRedLine;
                    $replace['core_position_id'] = null;
                    $replace['self_gen_user'] = 1;
                    $replace['updater_id'] = auth()->user()->id;
                    UserRedlines::create($replace->toArray());
                }
                UserRedlines::whereNotNull('core_position_id')->update(['self_gen_user' => '0']);

                // SELF-GEN UPFRONT
                $upFronts = UserUpfrontHistory::where('self_gen_user', '1')->get();
                foreach ($upFronts as $upFront) {
                    $upFrontType = $upFront->upfront_sale_type;
                    $upFrontAmount = $upFront->upfront_pay_amount;
                    $prevUpfront = UserUpfrontHistory::where(['user_id' => $upFront->user_id, 'self_gen_user' => '0'])->where('upfront_effective_date', '<=', $upFront->upfront_effective_date)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($prevUpfront) {
                        if ($prevUpfront->upfront_sale_type == 'percent') {
                            if ($upFrontType == 'percent' && $prevUpfront->upfront_pay_amount > $upFrontAmount) {
                                $upFrontAmount = $prevUpfront->upfront_pay_amount;
                                $upFrontType = $prevUpfront->upfront_sale_type;
                            } elseif ($upFrontType == 'per kw' || $upFrontType == 'per sale') {
                                $upFrontAmount = $prevUpfront->upfront_pay_amount;
                                $upFrontType = $prevUpfront->upfront_sale_type;
                            }
                        } elseif ($prevUpfront->upfront_sale_type == 'per kw') {
                            if ($upFrontType == 'per kw' && $prevUpfront->upfront_pay_amount > $upFrontAmount) {
                                $upFrontAmount = $prevUpfront->upfront_pay_amount;
                                $upFrontType = $prevUpfront->upfront_sale_type;
                            } elseif ($upFrontType == 'per sale') {
                                $upFrontAmount = $prevUpfront->upfront_pay_amount;
                                $upFrontType = $prevUpfront->upfront_sale_type;
                            }
                        } elseif ($prevUpfront->upfront_sale_type == 'per sale') {
                            if ($upFrontType == 'per sale' && $prevUpfront->upfront_pay_amount > $upFrontAmount) {
                                $upFrontAmount = $prevUpfront->upfront_pay_amount;
                                $upFrontType = $prevUpfront->upfront_sale_type;
                            }
                        }
                    }

                    UserUpfrontHistory::create([
                        'user_id' => $upFront->user_id,
                        'updater_id' => auth()->user()->id,
                        'product_id' => 1,
                        'milestone_schema_id' => 1,
                        'milestone_schema_trigger_id' => 1,
                        'self_gen_user' => 1,
                        'upfront_pay_amount' => $upFrontAmount,
                        'upfront_sale_type' => $upFrontType,
                        'upfront_effective_date' => $upFront->upfront_effective_date,
                        'position_id' => $upFront->position_id,
                        'core_position_id' => null,
                        'sub_position_id' => $upFront->sub_position_id,
                        'tiers_id' => 0,
                    ]);
                }
                UserUpfrontHistory::whereNotNull('core_position_id')->update(['self_gen_user' => '0']);

                foreach ($migratedPositions as $originalPosition => $migratedPosition) {
                    // SELF-GEN COMMISSION
                    $selfGenCommissions = UserSelfGenCommmissionHistory::where('sub_position_id', $originalPosition)->get();
                    foreach ($selfGenCommissions as $selfGenCommission) {
                        UserCommissionHistory::create([
                            'user_id' => $selfGenCommission->user_id,
                            'updater_id' => auth()->user()->id,
                            'product_id' => 1,
                            'self_gen_user' => 1,
                            'commission' => $selfGenCommission->commission,
                            'commission_type' => $selfGenCommission->commission_type,
                            'commission_effective_date' => $selfGenCommission->commission_effective_date,
                            'tiers_id' => 0,
                            'core_position_id' => null,
                            'position_id' => 2,
                            'sub_position_id' => $migratedPosition,
                        ]);
                    }

                    $subQuery = UserOrganizationHistory::select(
                        'id',
                        'user_id',
                        'effective_date',
                        DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
                    )->where('effective_date', '<=', date('Y-m-d'));
                    $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);
                    $users = UserOrganizationHistory::whereIn('id', $results->pluck('id'))->where(['sub_position_id' => $originalPosition, 'self_gen_accounts' => 1])->groupBy('user_id')->pluck('user_id');
                    foreach ($users as $user) {
                        foreach ($selfGens as $selfGen) {
                            $self = empty($selfGen) ? 1 : 0;
                            if ($commission = UserCommissionHistory::where(['user_id' => $user, 'core_position_id' => $selfGen, 'self_gen_user' => $self])->where('commission_effective_date', '<=', date('Y-m-d'))->orderBy('commission_effective_date', 'DESC')->first()) {
                                $commission->sub_position_id = $migratedPosition;
                                $commission->position_id = 2;
                                $commission->save();
                            } else {
                                $commission = UserCommissionHistory::where(['user_id' => $user, 'core_position_id' => $selfGen, 'self_gen_user' => $self, 'position_id' => $selfGen])->where('commission_effective_date', '<=', date('Y-m-d'))->orderBy('commission_effective_date', 'DESC')->first();
                                if ($commission) {
                                    UserCommissionHistory::create([
                                        'user_id' => $user,
                                        'commission_effective_date' => $commission->commission_effective_date,
                                        'product_id' => 1,
                                        'position_id' => 2,
                                        'core_position_id' => $selfGen,
                                        'sub_position_id' => $migratedPosition,
                                        'updater_id' => auth()->user()->id,
                                        'self_gen_user' => empty($selfGen) ? 1 : 0,
                                        'commission' => $commission->commission,
                                        'commission_type' => $commission->commission_type,
                                        'tiers_id' => 0,
                                    ]);
                                }
                            }

                            if ($redLine = UserRedlines::where(['user_id' => $user, 'core_position_id' => $selfGen, 'self_gen_user' => $self])->where('start_date', '<=', date('Y-m-d'))->orderBy('start_date', 'DESC')->first()) {
                                $redLine->sub_position_type = $migratedPosition;
                                $redLine->position_type = 2;
                                $redLine->save();
                            } else {
                                $redLine = UserRedlines::where(['user_id' => $user, 'core_position_id' => $selfGen, 'self_gen_user' => $self, 'position_type' => $selfGen])->where('start_date', '<=', date('Y-m-d'))->orderBy('start_date', 'DESC')->first();
                                if ($redLine) {
                                    UserRedlines::create([
                                        'user_id' => $user,
                                        'start_date' => $redLine->start_date,
                                        'position_type' => 2,
                                        'core_position_id' => $selfGen,
                                        'sub_position_type' => $migratedPosition,
                                        'updater_id' => auth()->user()->id,
                                        'redline_amount_type' => $redLine->redline_amount_type,
                                        'redline' => $redLine->redline,
                                        'redline_type' => $redLine->redline_type,
                                        'self_gen_user' => empty($selfGen) ? 1 : 0,
                                    ]);
                                }
                            }

                            if ($upfront = UserUpfrontHistory::where(['user_id' => $user, 'core_position_id' => $selfGen, 'self_gen_user' => $self])->where('upfront_effective_date', '<=', date('Y-m-d'))->orderBy('upfront_effective_date', 'DESC')->first()) {
                                $upfront->sub_position_id = $migratedPosition;
                                $upfront->position_id = 2;
                                $upfront->save();
                            } else {
                                $upfront = UserUpfrontHistory::where(['user_id' => $user, 'core_position_id' => $selfGen, 'self_gen_user' => $self, 'position_id' => $selfGen])->where('upfront_effective_date', '<=', date('Y-m-d'))->orderBy('upfront_effective_date', 'DESC')->first();
                                if ($upfront) {
                                    UserUpfrontHistory::create([
                                        'user_id' => $user,
                                        'upfront_effective_date' => $upfront->upfront_effective_date,
                                        'position_id' => 2,
                                        'core_position_id' => $selfGen,
                                        'product_id' => 1,
                                        'milestone_schema_id' => 1,
                                        'milestone_schema_trigger_id' => 1,
                                        'sub_position_id' => $migratedPosition,
                                        'updater_id' => auth()->user()->id,
                                        'self_gen_user' => empty($selfGen) ? 1 : 0,
                                        'upfront_pay_amount' => $upfront->upfront_pay_amount,
                                        'upfront_sale_type' => $upfront->upfront_sale_type,
                                        'tiers_id' => 0,
                                    ]);
                                }
                            }
                        }

                        UserWithheldHistory::where(['user_id' => $user, 'sub_position_id' => $originalPosition])->update(['position_id' => 2, 'sub_position_id' => $migratedPosition]);
                    }
                }
            }

            Artisan::call('ApplyHistoryOnUsersV2:update', ['auth_user_id' => auth()->user()->id]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e->getMessage(), $e->getLine(), $e->getFile());
        }
    }
}
