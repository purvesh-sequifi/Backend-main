<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Exports\ExportRecon\MainReconListExport;
use App\Exports\ExportRecon\UserReconListExport;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\Positions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalize;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationsAdjustement;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconDeductionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOverrides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ReconAdminReportController extends Controller
{
    const FILE_TYPE = '.xlsx';

    const EXPORT_FOLDER_PATH = 'exports/';

    const EXPORT_STORAGE_FOLDER_PATH = 'exports/';

    public $isPestServer = false;

    public $isUpfront = false;

    public function __construct()
    {
        /* check server is pest or not */
        $companyProfile = CompanyProfile::first();
        $this->isPestServer = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $this->isUpfront = $companyProfile->deduct_any_available_reconciliation_upfront;
    }

    public function mainReport(Request $request)
    {
        $pages = $request->input('perpage', 10);
        $executedOn = $request->input('executed_on', date('Y'));

        // Initial Query Construction
        $query = ReconciliationFinalize::query()
            ->whereIn('status', ['payroll', 'clawback'])
            ->whereYear('executed_on', $executedOn);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('start_date', $search)
                    ->orWhere('end_date', $search)
                    ->orWhereRaw('CONCAT(start_date, "-", end_date) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('CONCAT(start_date, " - ", end_date) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('CONCAT(start_date, " ", end_date) LIKE ?', ['%'.$search.'%'])
                    ->orWhereHas('office', function ($query) use ($search) {
                        $query->where('office_name', 'LIKE', '%'.$search.'%');
                    })
                    ->orWhereHas('position', function ($query) use ($search) {
                        $query->where('position_name', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        // Paginating the results
        $data = $query->groupBy('id', 'start_date', 'end_date')
            ->orderBy('id', 'asc')
            ->paginate($pages);

        $adjustAmount = 0;
        $data->transform(function ($item) use ($executedOn, &$adjustAmount) {

            $positionId = $item->pluck('position_id')->unique()->toArray();
            $officeId = $item->pluck('office_id')->unique()->toArray();
            $positionNames = Positions::whereIn('id', $positionId)->pluck('position_name')->toArray();
            $officeNames = Locations::whereIn('id', $officeId)->pluck('office_name')->toArray();
            $position = $this->fetchPosition($item, $executedOn);
            $office = $this->fetchOffice($item, $executedOn);

            // $adjustmentsTotal = ($item->adjustments + $item->deductions);
            $adjustmentsTotal = ($item->adjustments - $item->deductions);

            return [
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'executed_on' => $item->executed_on,
                'office' => $office,
                'position' => $position,
                'gross_amount' => $item->commissions + $item->overrides,
                'commission' => $item->commissions,
                'overrides' => $item->overrides,
                'clawback' => (-1 * $item->clawbacks),
                'adjustments' => $adjustmentsTotal,
                'payout' => $item->payout_percentage,
                'net_amount' => $item->commissions + $item->overrides + $adjustmentsTotal - $item->clawbacks,
                'status' => $item->status,
                // 'sent_id' => $item->finalize_count,
                'sent_id' => $item->id,
            ];
        });

        $res = $data->toArray();

        /* updated code total recon amount */
        $total = array_reduce($res['data'], function ($carry, $item) {
            $carry['commission'] += $item['commission'];
            $carry['overrides'] += $item['overrides'];
            $carry['clawback'] += $item['clawback'];
            $carry['adjustments'] += $item['adjustments'];
            $carry['net_amount'] += $item['net_amount'];
            $carry['gross_amount'] += $item['gross_amount'];

            return $carry;
        }, ['commission' => 0, 'overrides' => 0, 'clawback' => 0, 'adjustments' => 0, 'net_amount' => 0, 'gross_amount' => 0]);

        $responseTotal = [
            'total_commission' => $total['commission'],
            'override' => $total['overrides'],
            'clawback' => $total['clawback'],
            'adjustments' => $total['adjustments'],
            'gross_amount' => $total['net_amount'],
            'payout' => $total['gross_amount'],
            'year' => $executedOn,
        ];

        return response()->json([
            'ApiName' => 'reconciliation payroll list',
            'status' => true,
            'message' => 'Successfully.',
            'total' => $responseTotal,
            'data' => $data,
        ], 200);
    }

    private function fetchPosition($data, $date)
    {
        $positionIds = ReconciliationFinalizeHistory::whereYear('executed_on', $date)
            ->where('start_date', $data['start_date'])
            ->where('end_date', $data['end_date'])
            ->whereIn('status', ['payroll', 'clawback'])
            ->pluck('position_id')
            ->unique()
            ->values()
            ->all();
        if ($positionIds[0] === 'all') {
            return 'All office';
        }

        return implode(',', array_map(function ($id) {
            return Positions::find($id)->position_name;
        }, $positionIds));
    }

    private function fetchOffice($data, $date)
    {
        $officeIds = ReconciliationFinalizeHistory::whereYear('executed_on', $date)
            ->where('start_date', $data['start_date'])
            ->where('end_date', $data['end_date'])
            ->whereIn('status', ['payroll', 'clawback'])
            ->pluck('office_id')
            ->unique()
            ->values()
            ->all();
        if ($officeIds[0] === 'all') {
            return 'All office';
        }

        return implode(',', array_map(function ($id) {
            return Locations::find($id)->office_name;
        }, $officeIds));
    }

    public function userReconReport(Request $request)
    {
        $page = $request->page;
        $perPage = $request->perpage;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;
        // $finalizeId = $request->finalize_id;

        if (($page && is_numeric($page) && $page > 0) && ($perPage && is_numeric($perPage) && $perPage > 10)) {
            $page = $request->page;
            $perPage = $request->perpage;
        } else {
            $page = 1;
            $perPage = 10;
        }
        $finalizeDataTotal = ReconciliationFinalizeHistory::select(DB::raw('payout as payout, SUM(gross_amount) as total'))
            ->where('finalize_id', $request->sent_id)
            ->whereIn('status', ['payroll', 'clawback'])
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)->first();
        $payoutPercent = isset($finalizeDataTotal->payout) ? $finalizeDataTotal->payout : 0;
        $payoutTotal = isset($finalizeDataTotal->total) ? $finalizeDataTotal->total : 0;

        $finalizeData = ReconciliationFinalizeHistory::where('reconciliation_finalize_history.finalize_id', $request->sent_id)
            ->whereIn('status', ['payroll', 'clawback'])
            ->whereDate('reconciliation_finalize_history.start_date', $startDate)
            ->whereDate('reconciliation_finalize_history.end_date', $endDate)
            ->join('users', 'users.id', 'reconciliation_finalize_history.user_id')
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('users.first_name', 'LIKE', "%{$search}%")
                        ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                        ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'LIKE', "%{$search}%");
                }
            });
        // Determine the sorting field and direction
        $sortField = 'emp_name';
        $sortDirection = 'asc';
        if ($request->has('sort') && $request->input('sort')) {
            $sortField = $request->input('sort', 'emp_name'); // Default sorting field
        }

        if ($request->has('sort_val') && $request->input('sort_val')) {
            $sortDirection = $request->input('sort_val', 'asc'); // Default sorting direction
        }

        // Map the sort field from the request to the corresponding database field
        $sortableFields = [
            'commission' => 'paid_commission',
            'override' => 'paid_override',
            'clawback' => 'clawback',
            'adjustments' => 'adjustments',
            'total_due' => 'net_amount',
            'payout' => 'net_amount',
            'net_pay' => 'net_pay',
            // 'emp_name' => 'first_name', // Assuming emp_name refers to user first name
        ];

        // Validate and set the sort field
        if (array_key_exists($sortField, $sortableFields)) {
            $finalizeData = $finalizeData->orderBy($sortableFields[$sortField], $sortDirection);
        } else {
            $finalizeData = $finalizeData->orderBy('users.first_name', 'asc'); // Default sort by emp_name
        }
        $finalizeData = $finalizeData->groupBy('user_id')->get();
        // return $finalizeData;
        if (! $finalizeData->isEmpty()) {
            $payoutPer = $finalizeData[0]->payout;
            $response = $finalizeData->transform(function ($result) use ($startDate, $endDate) {
                $commission = ReconCommissionHistory::where('finalize_id', $result->finalize_id)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $result->user_id)
                    ->sum('paid_amount');

                $overrideDue = ReconOverrideHistory::where('finalize_id', $result->finalize_id)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $result->user_id)
                    ->sum('paid');

                /* $totalAdjustmentSum = ReconciliationsAdjustement::whereDate("start_date", $startDate)
                    ->whereDate("end_date", $endDate)
                    ->where("user_id", $result->user_id)
                    ->whereNotNull("payroll_id")
                    ->select(
                        DB::raw('SUM(clawback_due) as total_clawback'),
                        DB::raw('SUM(commission_due) as total_commission'),
                        DB::raw('SUM(overrides_due) as total_override_due'),
                        DB::raw('SUM(clawback_due + commission_due + overrides_due) as total_sum')
                    )
                    ->first(); */

                $totalAdjustmentSum = ReconAdjustment::where('finalize_id', $result->finalize_id)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $result->user_id)
                    ->sum('adjustment_amount');

                $deductionSum = ReconDeductionHistory::where('finalize_id', $result->finalize_id)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $result->user_id)
                    ->sum('total');

                // $totalAdjustments = $totalAdjustmentSum + $deductionSum;
                $totalAdjustments = $totalAdjustmentSum - $deductionSum;

                $clawbackDue = $result->clawback;
                // $totalAdjustments = $totalAdjustmentSum["total_clawback"] + $totalAdjustmentSum["total_commission"] + $totalAdjustmentSum["total_override_due"];
                $totalDue = $result->gross_amount;
                $netPay = $commission + $overrideDue + $totalAdjustments - $clawbackDue;
                $adjustmentTotal = ($result->adjustments + $result->deductions);

                return [
                    'user_id' => $result->user_id,
                    'emp_img' => $result?->user?->image ?? null,
                    'emp_name' => $result->user->first_name.' '.$result->user->last_name ?? null,
                    'position_id' => $result->user->position_id,
                    'sub_position_id' => $result->user->sub_position_id,
                    'is_super_admin' => $result->user->is_super_admin,
                    'is_manager' => $result->user->is_manager,
                    'commissionWithholding' => isset($commission) ? $commission : 0,
                    'overrideDue' => isset($overrideDue) ? $overrideDue : 0,
                    'clawbackDue' => isset($clawbackDue) ? (-1 * $clawbackDue) : 0,
                    'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                    'payout' => $netPay,
                    'sent_id' => $result->sent_count,
                    'status' => $result->status,
                    'finalize_count' => isset($result->finalize_id) ? $result->finalize_id : 0,
                    'finalize_id' => isset($result->finalize_id) ? $result->finalize_id : 0,
                    'dismiss' => isUserDismisedOn($result->user_id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isUserTerminatedOn($result->user_id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isUserContractEnded($result->user_id) ? 1 : 0,
                ];
            });
            $total = array_reduce($response->toArray(), function ($carry, $item) {
                $carry['payout'] += $item['payout'];

                return $carry;
            }, ['payout' => 0]);
            $data = paginate($response->toArray(), $perPage, $page);

            return response()->json([
                'ApiName' => 'reconciliation finalize',
                'status' => true,
                'message' => 'Successfully.',
                'office' => $this->getFinalizeLocation($startDate, $endDate, $request->sent_id),
                'position' => $this->getFinalizePosition($startDate, $endDate, $request->sent_id),
                // 'payout_per' => $payoutPer,
                // 'total_payout' => $total['payout'],
                'payout_per' => $payoutPercent,
                'total_payout' => $payoutTotal,
                'data' => $data,
            ], 200);
        }

        return response()->json([
            'ApiName' => 'reconciliation finalize',
            'status' => true,
            'message' => 'Successfully.',
            // 'office' => [],
            // 'position' => [],
            // 'payout_per' => [],
            // 'total_payout' => [],
            'office' => $this->getFinalizeLocation($startDate, $endDate, $request->sent_id),
            'position' => $this->getFinalizePosition($startDate, $endDate, $request->sent_id),
            'payout_per' => $payoutPercent,
            'total_payout' => $payoutTotal,
            'data' => [],
        ], 200);

    }

    public function getFinalizeLocation($startDate, $endDate, $finalizeCount)
    {
        $finalizeData = ReconciliationFinalizeHistory::where('finalize_id', $finalizeCount)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->pluck('office_id');

        // Assuming the Positions model has a relationship with ReconciliationFinalizeHistory via `office_id`
        return $officeNames = Locations::whereIn('id', $finalizeData->toArray())
            ->pluck('office_name');

        return implode(', ', $officeNames->toArray());

    }

    public function getFinalizePosition($startDate, $endDate, $finalizeCount)
    {
        $finalizeData = ReconciliationFinalizeHistory::where('finalize_id', $finalizeCount)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->pluck('position_id');

        // Assuming the Positions model has a relationship with ReconciliationFinalizeHistory via `office_id`
        return $positionNames = Positions::whereIn('id', $finalizeData->toArray())
            ->pluck('position_name');

        return implode(', ', $positionNames->toArray());

    }

    public function adminCommissionReportBreakdown($request, $userId)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->sent_id;
        $search = $request->search;
        $isUpfront = $this->isUpfront;

        $sales = SalesMaster::select('sale_masters.*')->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->join('sale_master_process', 'sale_master_process.pid', 'sale_masters.pid')
            ->where(function ($subQuery) use ($userId) {
                $subQuery->where('sale_master_process.closer1_id', $userId)
                    ->orWhere('sale_master_process.closer2_id', $userId)
                    ->orWhere('sale_master_process.setter1_id', $userId)
                    ->orWhere('sale_master_process.setter2_id', $userId);
            })->when($search && ! empty($search), function ($q) use ($search) {
                $q->where('sale_masters.pid', 'LIKE', '%'.$search.'%')->orWhere('sale_masters.customer_name', 'LIKE', '%'.$search.'%');
            })->orderBy('sale_masters.pid', 'ASC')->get();

        $salesData = $sales->transform(function ($result) use ($userId, $finalizeId, $startDate, $endDate, $isUpfront) {

            $reconCommissionData = ReconCommissionHistory::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->where('finalize_id', $finalizeId)
                ->where('user_id', $userId)
                ->first();

            $payout = $reconCommissionData->payout ?? 0;

            $isEligible = false;
            if (! empty($result->m2_date) && $result->m2_date >= $startDate && $result->m2_date <= $endDate && empty($result->date_cancelled)) {
                $isEligible = true;
            }

            $inReconAmount = 0;
            $inReconPercentage = 0;
            $userCommissionPercentage = 0;
            $usercommissiontype = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $userId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $result->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $userCommissionPercentage = $commissionHistory->commission;
                $usercommissiontype = $commissionHistory->commission_type;
            }

            $commission = UserCommission::selectRaw("SUM(amount) as totalCommission, SUM(CASE WHEN settlement_type = 'reconciliation' THEN amount ELSE 0 END) as totalReconAmount")
                ->where(['user_id' => $userId, 'pid' => $result->pid, 'is_displayed' => '1'])->first();

            $totalCommission = $commission->totalCommission ?? 0;
            $totalReconAmount = $commission->totalReconAmount ?? 0;

            $totalPaidInRecon = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'is_deducted' => '0', 'is_displayed' => '1'])
                ->where('status', 'payroll')
                ->where('is_ineligible', 0)
                ->where('finalize_id', '<', $finalizeId)
                ->sum('paid_amount');
            $totalPaidInRecondeduct = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'is_deducted' => '1', 'is_displayed' => '1'])
                ->where('status', 'payroll')
                ->where('is_ineligible', 0)
                ->where('payroll_id', '!=', 0)
                ->sum('paid_amount') ?? 0;
            // dd(-($totalPaidInRecondeduct));
            if ($totalPaidInRecondeduct != 0) {
                $totalPaid = ($totalCommission - $totalReconAmount) + $totalPaidInRecondeduct;
            } else {
                $totalPaid = ($totalCommission - $totalReconAmount) + $totalPaidInRecon;
            }
            // dd($totalPaid);

            if ($isEligible) {
                $remain = $totalReconAmount - $totalPaidInRecon;
                $inReconPercentage = ($remain * ($payout / 100));
                $inReconAmount = $remain - $inReconPercentage;

            } else {
                $inReconAmount = $totalReconAmount - $totalPaidInRecon;
            }

            if ($isUpfront == 1) {
                $upfrontAmount = UserCommission::where(['pid' => $result->pid, 'user_id' => $userId, 'amount_type' => 'm1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->sum('amount') ?? 0;
                $paidUpfront = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'type' => 'm1', 'is_deducted' => '1', 'is_displayed' => '1'])
                    ->where('status', 'payroll')
                    ->where('is_ineligible', 0)
                    ->where('finalize_count', '<', $finalizeId)
                    ->sum('paid_amount') ?? 0;

                $totalPaid = $totalPaid + $paidUpfront;
                $upfrontAmount = $upfrontAmount + $paidUpfront;
                if ($inReconPercentage) {
                    $m2 = SalesMaster::where(['pid' => $result->pid])->whereNotNull('m2_date')->first();
                    if (! empty($m2->pid)) {
                        $inReconPercentage = $inReconPercentage;
                    } else {
                        $inReconPercentage = $inReconPercentage - $upfrontAmount;
                    }

                } else {
                    $inReconPercentage = $inReconPercentage + (0 - $upfrontAmount);
                }
            }

            $state_name = State::find($result->state_id);

            return [
                'user_id' => $userId,
                'pid' => $result->pid,
                'customer_name' => $result->customer_name,
                'customer_state' => ucfirst($state_name->state_code),
                'rep_redline' => User::find($userId)->redline,
                'kw' => $result->kw,
                'net_epc' => $result->net_epc,
                'recon_status' => 1,
                'type' => $reconCommissionData->payout ?? null,
                'amount' => $totalCommission,
                'paid' => $totalPaid,
                'in_recon_percentage' => $inReconPercentage = ($finalizeId == 1) ? $inReconPercentage : ($inReconPercentage > 0 ? $inReconPercentage : 0),
                // ($inReconPercentage < 0 ) ? 0 : $inReconPercentage,
                'in_recon' => $inReconAmount,
                'finalize_payout' => $payout,
                'adjustment_amount' => 0,
                'is_ineligible' => $isEligible ? 0 : 1, // 0 = Eligible, 1 = Ineligible
                'is_upfront' => $isUpfront, // 0 = disable, 1 = enable
                'gross_account_value' => $result->gross_account_value,
                'user_commission' => $userCommissionPercentage,
                'user_commission_type' => $usercommissiontype,
            ];

        });

        $totalReconAmount = array_reduce($salesData->toArray(), function ($carry, $item) {
            $carry['in_recon_percentage'] += $item['in_recon_percentage'];
            $carry['commission_sub_total'] += $item['amount'];

            return $carry;
        }, ['in_recon_percentage' => 0, 'commission_sub_total' => 0]);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $salesData,
            'subtotal' => $totalReconAmount['in_recon_percentage'],
            'total' => $totalReconAmount,
        ]);
    }

    public function mainReportExport(Request $request): JsonResponse
    {
        $filename = 'main-recon-list-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new MainReconListExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    public function userReconReportExport($request): JsonResponse
    {
        $filename = 'user-recon-list-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new UserReconListExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    public function reportReconCommissionbyEmployeeId(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d'],
            'sent_id' => ['required', 'integer'],
        ]);

        // If validation fails, return an error response
        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'Recon Override reports breakdown details',
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->sent_id;
        $userId = $id;
        $search = $request->search;
        $isUpfront = $this->isUpfront;

        $sales = SalesMaster::select('sale_masters.*')->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->join('sale_master_process', 'sale_master_process.pid', 'sale_masters.pid')
            ->where(function ($subQuery) use ($userId) {
                $subQuery->where('sale_master_process.closer1_id', $userId)
                    ->orWhere('sale_master_process.closer2_id', $userId)
                    ->orWhere('sale_master_process.setter1_id', $userId)
                    ->orWhere('sale_master_process.setter2_id', $userId);
            })->when($search && ! empty($search), function ($q) use ($search) {
                $q->where('sale_masters.pid', 'LIKE', '%'.$search.'%')->orWhere('sale_masters.customer_name', 'LIKE', '%'.$search.'%');
            })->orderBy('sale_masters.pid', 'ASC')->get();

        $salesData = $sales->transform(function ($result) use ($userId, $finalizeId, $startDate, $endDate, $isUpfront) {

            $reconFinalizeData = ReconciliationFinalizeHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'finalize_id' => $finalizeId,
            ])->first();

            $reconCommissionData = ReconCommissionHistory::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->where('finalize_id', $finalizeId)
                ->where('user_id', $userId)
                ->first();

            $payout = $reconCommissionData->payout ?? 0;

            $isEligible = false;
            $finalDate = SaleProductMaster::select('id', 'pid', 'milestone_date')->where(['pid' => $result->pid, 'is_last_date' => 1])->first();
            if (! empty($finalDate->milestone_date) && $finalDate->milestone_date >= $startDate && $finalDate->milestone_date <= $endDate && empty($result->date_cancelled)) {
                $isEligible = true;
            }

            $inReconAmount = 0;
            $inReconPercentage = 0;
            $userCommissionPercentage = 0;
            $usercommissiontype = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $userId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $result->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $userCommissionPercentage = $commissionHistory->commission;
                $usercommissiontype = $commissionHistory->commission_type;
            }

            $commission = UserCommission::selectRaw("SUM(amount) as totalCommission, SUM(CASE WHEN settlement_type = 'reconciliation' THEN amount ELSE 0 END) as totalReconAmount")
                ->where(['user_id' => $userId, 'pid' => $result->pid, 'is_displayed' => '1'])->first();

            $totalCommission = $commission->totalCommission ?? 0;
            $totalReconAmount = $commission->totalReconAmount ?? 0;

            $totalPaidInRecon = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'is_deducted' => '0', 'is_displayed' => '1'])
                ->where('status', 'payroll')
                ->where('is_ineligible', 0)
                ->where('finalize_id', '<', $finalizeId)
                ->sum('paid_amount');

            $totalPaid = ($totalCommission - $totalReconAmount) + $totalPaidInRecon;

            if ($isEligible) {
                $remain = $totalReconAmount - $totalPaidInRecon;
                $inReconPercentage = ($remain * ($payout / 100));
                $inReconAmount = $remain - $inReconPercentage;

            } else {
                $inReconAmount = $totalReconAmount - $totalPaidInRecon;
            }

            if ($isUpfront == 1) {
                $upfrontAmount = UserCommission::where(['pid' => $result->pid, 'user_id' => $userId, 'amount_type' => 'm1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->sum('amount') ?? 0;
                $paidUpfront = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'type' => 'm1', 'is_deducted' => '1', 'is_displayed' => '1'])
                    ->where('status', 'payroll')
                    ->where('is_ineligible', 0)
                    ->where('finalize_id', '<', $finalizeId)
                    ->sum('paid_amount') ?? 0;

                $totalPaid = $totalPaid + $paidUpfront;
                $upfrontAmount = $upfrontAmount + $paidUpfront;
                if ($isEligible) {
                    // $inReconPercentage = $inReconPercentage - $upfrontAmount;
                    $inReconPercentage = $inReconPercentage;
                } else {
                    $inReconPercentage = $inReconPercentage + (0 - $upfrontAmount);
                }

                $inReconPercentage = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId])
                    ->where('start_date', $startDate)
                    ->where('end_date', $endDate)
                    ->where('finalize_id', $finalizeId)
                    ->sum('paid_amount') ?? 0;
            }

            $state_name = State::find($result->state_id);

            return [
                'user_id' => $userId,
                'pid' => $result->pid,
                'customer_name' => $result->customer_name,
                'customer_state' => ucfirst(@$state_name->state_code),
                'rep_redline' => User::find($userId)->redline,
                'kw' => $result->kw,
                'net_epc' => $result->net_epc,
                'recon_status' => 1,
                'type' => $reconCommissionData->payout ?? null,
                'amount' => $totalCommission,
                'paid' => $totalPaid,
                'in_recon_percentage' => $inReconPercentage,
                // ($inReconPercentage < 0 ) ? 0 : $inReconPercentage,
                'in_recon' => $inReconAmount,
                'finalize_payout' => $payout,
                'adjustment_amount' => 0,
                'is_ineligible' => $isEligible ? 0 : 1, // 0 = Eligible, 1 = Ineligible
                // 'is_upfront' => $isUpfront, // 0 = disable, 1 = enable
                'is_upfront' => isset($reconFinalizeData->is_upfront) ? $reconFinalizeData->is_upfront : $isUpfront,
                'gross_account_value' => $result->gross_account_value,
                'user_commission' => $userCommissionPercentage,
                'user_commission_type' => $usercommissiontype,
            ];

        });

        $totalReconAmount = array_reduce($salesData->toArray(), function ($carry, $item) {
            $carry['in_recon_percentage'] += $item['in_recon_percentage'];
            $carry['commission_sub_total'] += $item['amount'];

            return $carry;
        }, ['in_recon_percentage' => 0, 'commission_sub_total' => 0]);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $salesData,
            'subtotal' => $totalReconAmount['in_recon_percentage'],
            'total' => $totalReconAmount,
        ]);

    }

    public function reportReconOverridebyEmployeeId(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d'],
            'sent_id' => ['required', 'integer'],
        ]);

        // If validation fails, return an error response
        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'Recon Override reports breakdown details',
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $userId = $id;

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        // $sentCount = $request->input('sent_id');
        $finalizeId = $request->input('sent_id');
        if ($startDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date',

            ], 400);
        }
        /* new code start */
        $reconOverrideData = ReconOverrideHistory::with('salesDetail')->where('user_id', $userId)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
           // ->where("finalize_count", $sentCount)
            ->where('finalize_id', $finalizeId)
            ->get();

        $data = $reconOverrideData->transform(function ($result) use ($startDate, $endDate, $finalizeId, $userId) {
            $amount = ReconciliationFinalizeHistory::where('user_id', $userId)
                ->whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->where('finalize_id', $finalizeId)->sum('override');

            $user = User::find($result->overrider);
            $overrider = $user?->first_name.' '.$user?->last_name;
            $totalAmount = UserOverrides::where('user_id', $userId)->where('type', $result->type)->where('pid', $result->pid)->sum('amount');
            $overrideDetails = UserOverrides::where('pid', $result->pid)
                ->where('user_id', $userId)
                ->where('type', $result->type)
                ->first();

            return [
                'id' => $result->id,
                'user_id' => $result->user_id,
                'pid' => $result->pid,
                'customer_name' => $result->customer_name ?? null,
                'customer_state' => $result->salesDetail->customer_state ?? null,
                'position_id' => $result->userData->position_id ?? null,
                'sub_position_id' => $result->userData->sub_position_id ?? null,
                'is_super_admin' => $result->userData->is_super_admin ?? null,
                'is_manager' => $result->userData->is_manager ?? null,
                'image' => $result->userpayrolloverride->image ?? null,
                'override_over_image' => $result->userpayrolloverride->image ?? null,
                'override_name' => $overrider,
                'override_over_first_name' => $user?->first_name,
                'override_over_last_name' => $user?->last_name,
                // 'overrides_amount' => $overrideDetails["overrides_amount"] . " ". $overrideDetails["overrides_type"],
                'overrides_amount' => $overrideDetails?->overrides_amount ?? 0 .' '.$overrideDetails?->overrides_type,
                'type' => $result->type,
                'rep_redline' => $result->userData->redline, // Assuming this is hardcoded as per your code
                'kw' => $result->kw,
                // 'overrides_type' => $result->type,
                'overrides_type' => '',
                'amount' => $totalAmount,
                'paid' => $totalAmount - $result->total_amount,
                'in_recon' => $result->total_amount - floatval($result->paid),
                'in_recon_percentage' => $result->paid,
                'sent_id' => $result->finalize_count,
                'is_ineligible' => isset($result->is_ineligible) ? $result->is_ineligible : 0, // 0 = Eligible, 1 = Ineligible
                'gross_account_value' => $result->salesDetail->gross_account_value,
            ];
        });

        $total = array_reduce($data->toArray(), function ($carry, $item) {
            $carry['in_recon_percentage'] += $item['in_recon_percentage'];
            $carry['in_recon'] += $item['in_recon'];
            $carry['override_sub_total'] += $item['amount'];

            return $carry;
        }, ['in_recon_percentage' => 0, 'in_recon' => 0, 'override_sub_total' => 0]);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data->toArray(),
            'sub_total' => $total['in_recon_percentage'],
            'total' => $total,

        ], 200);

    }

    public function reportReconClawbackListbyEmployeeId(Request $request, $id)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Reports Reconciliation Clawback By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',

            ], 400);
        }
        $pid = $request->pid;
        $response = [];
        /* $data = ClawbackSettlement::with('user','salesDetail')->where(['user_id'=>$id,'status'=>1])->where('clawback_type','reconciliation')->whereDate('created_at', '>=', $startDate)
        ->whereDate('created_at', '<=', $endDate)->get(); */
        $data = ReconClawbackHistory::with('user', 'salesDetail')->where(['user_id' => $id])
            // ->where('status','payroll')
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('finalize_id', $request->finalize_count)
            ->get();
        // dd($data->toArray());

        foreach ($data as $d) {
            $clawback_amount = isset($d->total_amount) ? $d->total_amount : 0;
            $totalClawback = $clawback_amount;

            if ($d->user->redline_amount_type == 'Fixed') {
                $rep_redline = $d->user->redline;
            } else {
                $sale_state_redline = SalesMaster::join('states', 'states.state_code', '=', 'sale_masters.customer_state')
                    ->join('locations', function ($join) use ($d) {
                        $join->on('states.id', '=', 'locations.id')
                            ->where('locations.general_code', '=', $d->salesDetail->customer_state);
                    })->value('redline_standard');
                $user_redline = $d->user->redline;
                $user_office_redline = Locations::where(['id' => $d->user->office_id, 'general_code' => $d->salesDetail->customer_state])->value('redline_standard');
                $rep_redline = $sale_state_redline + ($user_redline - $user_office_redline);

            }
            $location = Locations::with('State')->where('general_code', '=', $d->salesDetail->customer_state)->first();
            if ($location) {
                $state_code = $location->state->id;
            } else {
                $state_code = null;
            }

            $response[] = [
                'id' => $d->user_id,
                'pid' => $d->salesDetail->pid,
                'customer_name' => $d->salesDetail->customer_name,
                'first_name' => $d->user->first_name,
                'last_name' => $d->user->last_name,
                'state_id' => $state_code,
                'state' => $d->salesDetail->customer_state,
                'rep_redline' => $rep_redline,
                'kw' => $d->salesDetail->kw,
                'net_epc' => $d->salesDetail->net_epc,
                'adders' => $d->salesDetail->adders,
                'date' => isset($d->created_at) ? date('Y-m-d', strtotime($d->created_at)) : null,
                'amount' => isset($totalClawback) ? (0 - $totalClawback) : null,
                'type' => 'Clawback',
                'is_prev_paid' => isset($totalClawback) ? 0 - $totalClawback : null,
                // 'description'=> 'clawback amount = '. $clawback_amount
            ];
        }

        $total = array_reduce($response, function ($carry, $item) {
            $carry['total'] += $item['is_prev_paid'];

            return $carry;
        }, ['total' => 0]);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Clawback By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
            'subTotal' => $total['total'],

        ], 200);

    }
}
