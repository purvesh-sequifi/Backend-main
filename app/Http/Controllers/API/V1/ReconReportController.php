<?php

namespace App\Http\Controllers\API\V1;

use App\Exports\ExportRecon\MainReconListExport;
use App\Exports\ExportRecon\UserReconListExport;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\Positions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationsAdjustement;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserReconciliationWithholding;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ReconReportController extends Controller
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

    /**
     * Method mainReport: this function getting recon data based on date
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
    public function mainReport(Request $request)
    {
        $pages = $request->input('perpage', 10);
        $executedOn = $request->input('executed_on', date('Y'));

        // Initial Query Construction
        $query = ReconciliationFinalizeHistory::query()
            // ->whereIn('status', ['payroll', 'clawback'])
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

        // Aggregating Data
        $query->clone()->select(
            DB::raw('SUM(paid_commission) as total_commission'),
            DB::raw('SUM(paid_override) as total_override'),
            DB::raw('SUM(clawback) as total_clawback'),
            DB::raw('SUM(adjustments) as total_adjustments'),
            DB::raw('SUM(deductions) as total_deduction'),
            DB::raw('SUM(gross_amount) as total_gross_amount'),
            DB::raw('SUM(net_amount) as total_payout')
        )->get();
        $totalCommission = $query->sum('paid_commission');
        $totalOverride = $query->sum('paid_override');
        $totalClawback = $query->sum('clawback');
        $totalAdjustments = $query->sum('adjustments');
        $totalGrossAmount = $query->sum('gross_amount');
        $totalPayout = $query->sum('net_amount');
        // Response Total Calculation
        $responseTotal = [
            'total_commission' => $totalCommission,
            'override' => $totalOverride,
            'clawback' => (-1 * $totalClawback),
            'adjustments' => $totalAdjustments,
            'gross_amount' => $totalGrossAmount,
            'payout' => $totalPayout,
            'year' => $executedOn,
            'next_recon' => 123,
        ];
        // Paginating the results
        $data = $query->select([
            'payout',
            'finalize_count',
            'start_date',
            'end_date',
            'executed_on',
            'position_id',
            // "office_id",
            DB::raw('SUM(paid_commission) as total_commission'),
            DB::raw('SUM(paid_override) as total_override'),
            DB::raw('SUM(clawback) as total_clawback'),
            DB::raw('SUM(adjustments) as total_adjustments'),
            DB::raw('SUM(deductions) as total_deductions'),
            DB::raw('SUM(gross_amount) as total_gross_amount'),
            DB::raw('SUM(net_amount) as total_net_amount'),
            DB::raw('GROUP_CONCAT(office_id) AS office_id'),
        ])->groupBy('finalize_count', 'start_date', 'end_date')
            ->orderBy('id', 'asc')
            ->paginate($pages);

        $adjustAmount = 0;
        $data->transform(function ($item) use ($executedOn, &$adjustAmount) {
            $totalCommission = ReconCommissionHistory::where('finalize_count', $item->finalize_count)
                ->whereDate('start_date', $item->start_date)
                ->whereDate('end_date', $item->end_date)
                ->sum('paid_amount');
            $totalOverride = ReconOverrideHistory::where('finalize_count', $item->finalize_count)
                ->whereDate('start_date', $item->start_date)
                ->whereDate('end_date', $item->end_date)
                ->sum('paid');
            $totalAdjustment = ReconAdjustment::whereDate('start_date', $item->start_date)
                ->whereDate('end_date', $item->end_date)
                ->where('finalize_count', $item->finalize_count)
                ->sum('adjustment_amount');

            $positionId = $item->pluck('position_id')->unique()->toArray();
            $officeId = $item->pluck('office_id')->unique()->toArray();
            $positionNames = Positions::whereIn('id', $positionId)->pluck('position_name')->toArray();
            $officeNames = Locations::whereIn('id', $officeId)->pluck('office_name')->toArray();

            $position = $this->fetchPosition($item, $executedOn);
            $office = $this->fetchOffice($item, $executedOn);
            $adjustmentsTotal = ($item->total_adjustments + $item->total_deductions);

            return [
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'executed_on' => $item->executed_on,
                'office' => $office,
                'position' => $position,
                'gross_amount' => $totalCommission + $totalOverride,
                'commission' => $totalCommission,
                'overrides' => $totalOverride,
                'clawback' => (-1 * $item->total_clawback),
                'adjustments' => $adjustmentsTotal,
                'payout' => $item->payout,
                'net_amount' => $totalCommission + $totalOverride + $adjustmentsTotal - $item->total_clawback,
                'status' => $item->status,
                'sent_id' => $item->finalize_count,
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
            'payout' => $total['net_amount'],
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

    /**
     * Method mainReportExport: This function is use for the main-recon-list export
     *
     * @param  Request  $request  [explicit description]
     */
    public function mainReportExport(Request $request): JsonResponse
    {
        $filename = 'main-recon-list-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new MainReconListExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    /**
     * Method mainReportExport: This function is use for the main-recon-list export
     *
     * @param  Request  $request  [explicit description]
     */
    public function userReconReportExport(Request $request): JsonResponse
    {
        $filename = 'user-recon-list-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new UserReconListExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    public function standardReportPastReconciliation_old(Request $request)
    {
        $userId = Auth()->user()->id;
        $user = Auth()->user();

        $page = $request->perpage;
        if (isset($page) && $page != null) {
            $pages = $page;
        } else {
            $pages = 10;
        }

        $yearFilter = $request->input('year');
        $data = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->orderBy('id', 'asc')->groupBy('start_date')->where('status', 'payroll');

        if ($request->has('search')) {
            $data->where('start_date', $request->search)->where('status', 'payroll')->orWhere('end_date', $request->search)->where('status', 'payroll')
                ->orWhereHas('office', function ($query) use ($request) {
                    $query->where('office_name', 'LIKE', '%'.$request->search.'%');
                })->where('status', 'payroll')
                ->orWhereHas('position', function ($query) use ($request) {
                    $query->where('position_name', 'LIKE', '%'.$request->search.'%');
                })->where('status', 'payroll')
                ->orWhereRaw('CONCAT(start_date, "-", end_date) LIKE ?', ['%'.$request->search.'%'])->where('status', 'payroll')
                ->orWhereRaw('CONCAT(start_date, " - ", end_date) LIKE ?', ['%'.$request->search.'%'])->where('status', 'payroll')
                ->orWhereRaw('CONCAT(start_date, " ", end_date) LIKE ?', ['%'.$request->search.'%'])->where('status', 'payroll');
        }
        if (Auth()->user()->is_super_admin != '1' && Auth()->user()->id != 1) {
            $data->where('user_id', $userId);
        }

        $data = $data->with('position', 'office')->paginate($pages);

        $totalCommision = 0;
        $totalOverride = 0;
        $totalClawback = 0;
        $totalAdjustments = 0;
        $grossAmount = 0;
        $payout = 0;
        $data->transform(function ($data) use ($yearFilter) {
            $total = [];
            $positionId = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')
                ->when($user && $user->is_super_admin != 1 && $user->id != 1, function ($query) use ($data) {
                    return $query->where('user_id', $data->user_id);
                })->orderBy('id', 'asc')->pluck('position_id');
            $officeId = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')
                ->when($user && $user->is_super_admin != 1 && $user->id != 1, function ($query) use ($data) {
                    return $query->where('user_id', $data->user_id);
                })->orderBy('id', 'asc')->pluck('office_id');
            $uniqueArray = collect($positionId)->unique()->values()->all();

            if (isset($uniqueArray[0]) && $uniqueArray[0] == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                $val = [];
                foreach ($uniqueArray as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }
            $officeIdArray = collect($officeId)->unique()->values()->all();
            if (isset($officeIdArray[0]) && $officeIdArray[0] == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                $vals = [];
                foreach ($officeIdArray as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }

            $val = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)
                ->when($user && $user->is_super_admin != 1 && $user->id != 1, function ($query) use ($data) {
                    return $query->where('user_id', $data->user_id);
                })
                ->where('sent_count', $data->sent_count)
                ->orderBy('id', 'asc')
                ->where('start_date', $data->start_date)
                ->where('end_date', $data->end_date)
                ->where('status', 'payroll');

            $sumComm = $val->sum('paid_commission');
            $sumOver = $val->sum('paid_override');
            $sumClaw = $val->sum('clawback');
            $sumAdju = $val->sum('adjustments');
            $sumGross = $val->sum('gross_amount');
            $sumPayout = $val->sum('net_amount');

            return [
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'executed_on' => $data->executed_on,
                'office' => $office,
                'position' => $position,
                'commission' => $sumComm,
                'overrides' => $sumOver,
                'clawback' => $sumClaw,
                'adjustments' => $sumAdju,
                'gross_amount' => $sumPayout,
                'payout' => $data->payout,
                'net_amount' => $sumPayout,
                'status' => $data->status,
                'sent_id' => $data->sent_count,
            ];

        });

        $dataCalculate = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->orderBy('id', 'asc')->groupBy('sent_count')->where('status', 'payroll');
        if ($request->has('search')) {
            $dataCalculate->where('start_date', $request->search)->orWhere('end_date', $request->search)
                ->orWhereRaw('CONCAT(start_date, "-", end_date) LIKE ?', ['%'.$request->search.'%'])
                ->orWhereRaw('CONCAT(start_date, " - ", end_date) LIKE ?', ['%'.$request->search.'%'])
                ->orWhereRaw('CONCAT(start_date, " ", end_date) LIKE ?', ['%'.$request->search.'%'])
                ->orWhereHas('office', function ($query) use ($request) {
                    $query->where('office_name', 'LIKE', '%'.$request->search.'%');
                })

                ->orWhereHas('position', function ($query) use ($request) {
                    $query->where('position_name', 'LIKE', '%'.$request->search.'%');
                });
        }
        $dataCalculate = $dataCalculate->get();

        foreach ($dataCalculate as $dataCalculates) {
            $vals = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->orderBy('id', 'asc')->where('start_date', $dataCalculates->start_date)->where('end_date', $dataCalculates->end_date)->where('sent_count', $dataCalculates->sent_count)->where('status', 'payroll');
            $sumComm = $vals->sum('paid_commission');
            $sumOver = $vals->sum('paid_override');
            $sumClaw = $vals->sum('clawback');
            $sumAdju = $vals->sum('adjustments');
            $sumGross = $vals->sum('gross_amount');
            $sumPayout = $vals->sum('net_amount');

            $totalCommision += $sumComm;
            $totalOverride += $sumOver;
            $totalClawback += $sumClaw;
            $totalAdjustments += $sumAdju;
            $grossAmount += $sumGross;
            $payout += $sumPayout;
        }

        $total = [
            'totalCommision' => $totalCommision,
            'override' => $totalOverride,
            'clawback' => $totalClawback,
            'adjustments' => $totalAdjustments,
            'gross_amount' => $payout,
            'payout' => $payout,
            'year' => isset($yearFilter) ? $yearFilter : date('Y'),
            'nextRecon' => $grossAmount - $payout,
        ];

        return response()->json([
            'ApiName' => 'standard report past reconciliation',
            'status' => true,
            'message' => 'Successfully.',
            'total' => $total,
            'data' => $data,
        ], 200);

    }

    public function standardReportPastReconciliation(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'year' => [
                    'required',
                    'digits:4',
                    'integer',
                    'between:2000,'.Carbon::now()->year,
                ],
                'user_id' => ['required', function ($attribute, $value, $fail) {
                    if ($value != 'all') {
                        if (! User::where('id', $value)->exists()) {
                            $fail('This user is not exists in our system.');
                        }
                    }
                }],
            ]);
            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validation->errors(),
                ], 400);
            }
            $userId = $request->user_id;
            $yearFilter = $request->year;
            if ($userId != 'all') {
                $user = User::find($userId);
                $recon = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->orderBy('id', 'asc')->groupBy(['finalize_count', 'user_id', 'start_date', 'end_date'])->whereIn('status', ['payroll', 'clawback'])->where('user_id', $userId);
            } else {
                $user = User::find(auth()->user()->id);
                $recon = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->orderBy('id', 'asc')->groupBy(['finalize_count', 'user_id', 'start_date', 'end_date'])->whereIn('status', ['payroll', 'clawback']);
            }
            $recon = $recon->get();

            $response = $recon->transform(function ($result) {

                $totalCommission = ReconCommissionHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_count' => $result->finalize_count])->whereNotIn('status', ['finalize'])->sum('paid_amount') ?? 0;
                $totalOverride = ReconOverrideHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_count' => $result->finalize_count])->whereNotIn('status', ['finalize'])->sum('paid') ?? 0;
                $totalClawback = ReconClawbackHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_count' => $result->finalize_count])->whereNotIn('status', ['finalize'])->sum('paid_amount') ?? 0;
                $totalAdjustment = ReconAdjustment::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_count' => $result->finalize_count])->whereNotIn('payroll_status', ['finalize'])->sum('adjustment_amount') ?? 0;
                $totalDeduction = ReconciliationFinalizeHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_count' => $result->finalize_count])->whereNotIn('status', ['finalize'])->sum('deductions') ?? 0;

                return [
                    'user_id' => $result->user_id,
                    'start_date' => $result->start_date,
                    'end_date' => $result->end_date,
                    'executed_on' => $result->executed_on,
                    'commission' => $totalCommission,
                    'overrides' => $totalOverride,
                    'clawback' => (-1 * $totalClawback),
                    'adjustments' => $totalAdjustment - $totalDeduction,
                    'total_due' => $totalCommission + $totalOverride,
                    'payout_percent' => $result->payout,
                    'payout' => $totalCommission + $totalOverride,
                    // 'total_payout' => $result->gross_amount,
                    'total_payout' => ($totalCommission + $totalOverride + $totalAdjustment - $totalDeduction - $totalClawback),
                    'status' => $result->status,
                    'sent_id' => $result->sent_count,
                    'finalize_count' => $result->finalize_count,
                ];
            });

            $total = array_reduce($response->toArray(), function ($carry, $item) {
                $carry['totalCommision'] += $item['commission'];
                $carry['override'] += $item['overrides'];
                $carry['clawback'] += $item['clawback'];
                $carry['adjustments'] += $item['adjustments'];
                $carry['payout'] += $item['total_payout'];
                $carry['total_due'] += $item['total_due'];
                $carry['total_payout'] += $item['total_payout'];

                return $carry;
            }, ['totalCommision' => 0, 'override' => 0, 'clawback' => 0, 'adjustments' => 0, 'payout' => 0, 'total_due' => 0, 'total_payout' => 0]);

            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => $total,
                'data' => $response,
            ], 200);

            /* this c ode is remove after standard report is stable */
            $totalCommision = 0;
            $totalOverride = 0;
            $totalClawback = 0;
            $totalAdjustments = 0;
            $dueAmountTotal = 0;
            $totalpayouts = 0;
            $grossPayaAmount = 0;

            if (count($recon) > 0) {

                foreach ($recon as $key => $data) {
                    $total = [];
                    $positionId = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')
                        ->when($user && $user->is_super_admin != 1 && $user->id != 1, function ($query) use ($user) {
                            return $query->where('user_id', $user->id);
                        })->orderBy('id', 'asc')->pluck('position_id');

                    $officeId = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')
                        ->when($user && $user->is_super_admin != 1 && $user->id != 1, function ($query) use ($user) {
                            return $query->where('user_id', $user->id);
                        })->orderBy('id', 'asc')->pluck('office_id');

                    $uniqueArray = collect($positionId)->unique()->values()->all();

                    if (isset($uniqueArray[0]) && $uniqueArray[0] == 'all') {
                        $position = 'All office';
                    } else {
                        $positionid = explode(',', $data->position_id);
                        $val = [];
                        foreach ($uniqueArray as $positions) {
                            $positionvalu = Positions::where('id', $positions)->first();
                            $val[] = $positionvalu->position_name;
                        }
                        $position = implode(',', $val);
                    }
                    $officeIdArray = collect($officeId)->unique()->values()->all();
                    if (isset($officeIdArray[0]) && $officeIdArray[0] == 'all') {
                        $office = 'All office';
                    } else {
                        $officeId = explode(',', $data->office_id);
                        $vals = [];
                        foreach ($officeIdArray as $offices) {
                            $positionvalu = Locations::where('id', $offices)->first();
                            $vals[] = $positionvalu->office_name;
                        }
                        $office = implode(',', $vals);
                    }

                    $val = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)
                        ->where('user_id', $data->user_id)
                        ->where('start_date', $data->start_date)
                        ->where('end_date', $data->end_date)
                        ->where('finalize_count', $data->finalize_count)
                        ->where('status', 'payroll')
                        ->orderBy('id', 'asc');

                    $sumCommpaid = $val->sum('paid_commission');
                    $sumOverpaid = $val->sum('paid_override');
                    $sumComm = $val->sum('commission');
                    $sumOver = $val->sum('override');
                    $sumClaw = $val->sum('clawback');
                    $sumAdju = $val->sum('adjustments');
                    // $sumGross = $val->sum('gross_amount');
                    $sumPayout = $val->sum('net_amount');

                    $totalDue = (($sumComm + $sumOver) - ($sumCommpaid + $sumOverpaid));
                    $payout = ($sumCommpaid + $sumOverpaid);

                    $totalCommision += $sumCommpaid;
                    $totalOverride += $sumOverpaid;
                    $totalClawback += $sumClaw;
                    $totalAdjustments += $sumAdju;
                    $dueAmountTotal += $totalDue;
                    $totalpayouts += $payout;
                    $grossPayaAmount += $sumPayout;

                    $result[] = [
                        'user_id' => $data->user_id,
                        'start_date' => $data->start_date,
                        'end_date' => $data->end_date,
                        'executed_on' => $data->executed_on,
                        'office' => $office,
                        'position' => $position,
                        'commission' => $sumCommpaid,
                        'overrides' => $sumOverpaid,
                        'clawback' => $sumClaw,
                        'adjustments' => $sumAdju,
                        'total_due' => $totalDue,
                        'payout_percent' => $data->payout,
                        'payout' => $payout,
                        'total_payout' => $sumPayout,
                        'status' => $data->status,
                        'sent_id' => $data->sent_count,
                        'finalize_count' => $data->finalize_count,
                    ];
                }

                $total = [
                    'totalCommision' => $totalCommision,
                    'override' => $totalOverride,
                    'clawback' => $totalClawback,
                    'adjustments' => $totalAdjustments,
                    'total_due' => $dueAmountTotal,
                    'payout' => $totalpayouts,
                    'total_payout' => $grossPayaAmount,
                    'year' => isset($yearFilter) ? $yearFilter : date('Y'),
                    // 'nextRecon' => ($grossAmount - $payout),
                ];

                return response()->json([
                    'ApiName' => 'standard report past reconciliation',
                    'status' => true,
                    'message' => 'Successfully.',
                    'total' => $total,
                    'data' => $result,
                ], 200);

            } else {
                return response()->json([
                    'ApiName' => 'standard report past reconciliation',
                    'status' => true,
                    'message' => 'Successfully.',
                    'total' => [],
                    'data' => [],
                ], 200);
            }
        } catch (\Throwable $th) {
            Log::channel('reconLog')->debug($th);

            return response()->json([
                'api_name' => 'past reconciliation standard reports',
                'status' => false,
                'message' => 'Something went wrong',
                'data' => [],
            ], 400);
        }
    }

    /**
     * Method userReconReport : this function is use for the admin side user report
     *
     * @object $request $request [explicit description]
     *
     * @return void
     */
    public function userReconReport($request)
    {
        $page = $request->page;
        $perPage = $request->perpage;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;

        if (($page && is_numeric($page) && $page > 0) && ($perPage && is_numeric($perPage) && $perPage > 10)) {
            $page = $request->page;
            $perPage = $request->perpage;
        } else {
            $page = 1;
            $perPage = 10;
        }
        $finalizeDataTotal = ReconciliationFinalizeHistory::select(DB::raw('payout as payout, SUM(gross_amount) as total'))->where('finalize_count', $request->sent_id)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)->first();
        $payoutPercent = isset($finalizeDataTotal->payout) ? $finalizeDataTotal->payout : 0;
        $payoutTotal = isset($finalizeDataTotal->total) ? $finalizeDataTotal->total : 0;

        $finalizeData = ReconciliationFinalizeHistory::where('reconciliation_finalize_history.finalize_count', $request->sent_id)
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
                $commission = ReconCommissionHistory::where('finalize_count', $result->finalize_count)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $result->user_id)
                    ->sum('paid_amount');

                $overrideDue = ReconOverrideHistory::where('finalize_count', $result->finalize_count)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $result->user_id)
                    ->sum('paid');

                $totalAdjustmentSum = ReconciliationsAdjustement::whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $result->user_id)
                    ->whereNotNull('payroll_id')
                    ->select(
                        DB::raw('SUM(clawback_due) as total_clawback'),
                        DB::raw('SUM(commission_due) as total_commission'),
                        DB::raw('SUM(overrides_due) as total_override_due'),
                        DB::raw('SUM(clawback_due + commission_due + overrides_due) as total_sum')
                    )
                    ->first();
                /* $commission = $result->paid_commission;
                $overrideDue = $result->paid_override; */
                $clawbackDue = $result->clawback;
                $totalAdjustments = $totalAdjustmentSum['total_clawback'] + $totalAdjustmentSum['total_commission'] + $totalAdjustmentSum['total_override_due'];
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
                    'payout' => $result->gross_amount ? $result->gross_amount : 0,
                    'clawbackDue' => isset($clawbackDue) ? (-1 * $clawbackDue) : 0,
                    'totalAdjustments' => isset($adjustmentTotal) ? $adjustmentTotal : 0,
                    'sent_id' => $result->sent_count,
                    'status' => $result->status,
                    'finalize_count' => isset($result->finalize_count) ? $result->finalize_count : 0,
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
        $finalizeData = ReconciliationFinalizeHistory::where('finalize_count', $finalizeCount)
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
        $finalizeData = ReconciliationFinalizeHistory::where('finalize_count', $finalizeCount)
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
        $finalizeCount = $request->sent_id;
        $isUpfront = $this->isUpfront;
        $reconCommissionData = ReconCommissionHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('finalize_count', $finalizeCount)
            ->where('user_id', $userId)
            ->get();

        $salesData = $reconCommissionData->transform(function ($result) use ($userId, $finalizeCount, $startDate, $endDate, $isUpfront) {
            $upfrontAmount = 0;
            if ($isUpfront == 1) {
                $upfrontAmount = UserCommission::where('pid', $result->pid)->where('user_id', $userId)->where('amount_type', 'm1')->sum('amount') ?? 0;
            }
            $totalUserCommission = UserCommission::where('pid', $result->pid)->where('user_id', $userId)->sum('amount');
            $withHeldCommission = UserReconciliationWithholding::where(function ($query) use ($userId) {
                $query->where('closer_id', $userId)
                    ->orWhere('setter_id', $userId);
            })->where('pid', $result->pid)
                ->sum('withhold_amount');
            $saleData = SalesMaster::where('pid', $result->pid)->first();
            // $commission =  $totalUserCommission + $withHeldCommission;
            $commission = $totalUserCommission - $upfrontAmount;
            $previousReconPaid = ReconCommissionHistory::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->where('finalize_count', '<', $finalizeCount)
                ->where('user_id', $userId)
                ->where('is_ineligible', 0)
                ->sum('paid_amount');

            if ($finalizeCount == 1) {
                // $paidAmount = $totalUserCommission - $previousReconPaid;
                $paidAmount = $previousReconPaid;
            } else {
                $paidAmount = $commission - $previousReconPaid;
            }
            $type = $result->type != 'recon-commission' ? ucfirst($result->type) : 'Reconciliation';
            $in_recon = $result->total_amount - $result->paid_amount;

            if ($result->is_ineligible == 1) {
                $in_recon = 0;
            }

            return [
                'user_id' => $userId,
                'pid' => $result->pid,
                'customer_name' => $saleData->customer_name,
                'customer_state' => ucfirst($saleData->customer_state),
                'rep_redline' => User::find($userId)->redline,
                'kw' => $saleData->kw,
                'net_epc' => $saleData->net_epc,
                'recon_status' => 1,
                'type' => $type,
                'amount' => $commission,
                'paid' => $paidAmount,
                'in_recon' => $in_recon,
                'in_recon_percentage' => $result->paid_amount,
                'finalize_payout' => $result->payout,
                'adjustment_amount' => 0,
                'is_ineligible' => isset($result->is_ineligible) ? $result->is_ineligible : 0, // 0 = Eligible, 1 = Ineligible
                'is_upfront' => $isUpfront, // 0 = disable, 1 = enable
            ];
        });

        $totalReconAmount = array_reduce($salesData->toArray(), function ($carry, $item) {
            $carry['in_recon'] += $item['in_recon_percentage'];

            return $carry;
        }, ['in_recon' => 0]);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $salesData,
            'subtotal' => $totalReconAmount['in_recon'],
        ]);
    }
}
