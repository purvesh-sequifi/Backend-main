<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Exports\ExportReconPayrollList;
use App\Http\Controllers\Controller;
use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CostCenter;
use App\Models\FrequencyType;
use App\Models\Locations;
use App\Models\MoveToReconciliation;
use App\Models\MoveToReconHistory;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollDeductions;
use App\Models\PayrollHistory;
use App\Models\Positions;
use App\Models\ReconciliationAdjustmentDetails;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationsAdjustement;
use App\Models\ReconciliationStatusForSkipedUser;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class UserReconciliationController extends Controller
{
    public const RECON_POPUP_CLASS = \App\Http\Controllers\API\V1\ReconPopUpController::class;

    public $isPestServer = false;

    public $isUpfront = false;

    public function __construct()
    {
        /* check server is pest or not */
        $companyProfile = CompanyProfile::first();
        $this->isPestServer = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $this->isUpfront = $companyProfile->deduct_any_available_reconciliation_upfront;
    }

    public function ReconciliationListPayRoll(Request $request) // getting recon list select start date and end date
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $recon_payout = $request->recon_payout;
        $request->position_id;
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);

        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        if ($position_id == 'all' && $office_id != 'all') {
            $userId = User::whereIn('office_id', $officeId);

        } elseif ($office_id == 'all' && $position_id != 'all') {
            $userId = User::whereIn('sub_position_id', $positionId);
        } elseif ($office_id == 'all' && $position_id == 'all') {
            $userId = User::orderBy('id', 'desc');
        } else {
            $userId = User::whereIn('office_id', $officeId)->whereIn('sub_position_id', $positionId);
        }
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            if ($request->has('search') && ! empty($request->input('search'))) {

                $userId->where(function ($query) use ($request) {
                    return $query->where('first_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
            }
        }

        $userIds = $userId->pluck('id');
        $position_id = $userId->pluck('sub_position_id')->toArray();
        $pid = UserReconciliationWithholding::whereIn('closer_id', $userIds)->where('finalize_status', 0)->where('status', '!=', 'paid')->orWhereIn('setter_id', $userIds)->where('status', '!=', 'paid')->where('finalize_status', 0)->pluck('pid');
        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid')->toArray();
        $saleOveerPids = SalesMaster::with('salesMasterProcess')->whereBetween('m2_date', [$startDate, $endDate])->get();
        // $uid = [];
        foreach ($saleOveerPids as $saleOveerPid) {
            $closerId = isset($saleOveerPid->salesMasterProcess->closer1_id) ? $saleOveerPid->salesMasterProcess->closer1_id : null;
            $setterId = isset($saleOveerPid->salesMasterProcess->setter1_id) ? $saleOveerPid->salesMasterProcess->setter1_id : null;

            if (! empty($closerId) && ! empty($setterId)) {
                $overs = UserOverrides::where(['overrides_settlement_type' => 'reconciliation', 'status' => 1, 'pid' => $saleOveerPid->pid])->whereIn('user_id', [$closerId, $setterId])->get();
                if (count($overs) > 0) {
                    foreach ($overs as $key => $over) {
                        $findpid = UserReconciliationWithholding::where('closer_id', $over->user_id)->where('pid', $over->pid)->orWhere('setter_id', $over->user_id)->where('pid', $over->pid)->first();
                        if (empty($findpid)) {
                            $userPosi = User::where('id', $over->user_id)->first();
                            if ($userPosi->position_id == 2) {
                                UserReconciliationWithholding::create([
                                    'pid' => $over->pid,
                                    'closer_id' => $over->user_id,
                                    'status' => 'unpaid',
                                    'finalize_status' => 0,
                                ]);
                            }
                            if ($userPosi->position_id == 3) {
                                UserReconciliationWithholding::create([
                                    'pid' => $over->pid,
                                    'setter_id' => $over->user_id,
                                    'status' => 'unpaid',
                                    'finalize_status' => 0,
                                ]);
                            }
                        }
                    }
                }
            }
        }
        $userId = [];

        // return $salePid;
        $arrayPid = implode(',', $salePid);
        $userDatas = UserReconciliationWithholding::whereIn('pid', $salePid)
            ->where('finalize_status', 0)
            ->whereIn('closer_id', $userIds)
            ->where('status', '!=', 'paid')
            ->orWhereIn('setter_id', $userIds)
            ->where('finalize_status', 0)
            ->where('status', '!=', 'paid')
            ->whereIn('pid', $salePid)->get();

        foreach ($userDatas as $userData) {
            $uid[] = isset($userData->closer_id) ? $userData->closer_id : $userData->setter_id;
            $userId = array_unique($uid);
        }

        $closerSetter = UserReconciliationWithholding::where('finalize_status', 0)
            ->whereIn('pid', $salePid)
            ->where(function ($qry) use ($userId) {
                return $qry->whereIn('closer_id', $userId)->orWhereIn('setter_id', $userId);
            })
                    // ->whereIn('closer_id',$userId)
            ->groupBY('closer_id')
            ->where('status', '!=', 'paid')
                    // ->where('payroll_to_recon_status',null)
                    // ->orWhereIn('setter_id',$userId)
            ->where('finalize_status', 0)
                    // ->whereIn('pid',$salePid)
            ->groupBy('setter_id')
            ->get();

        foreach ($closerSetter as $closerSetters) {
            $userId = isset($closerSetters->closer_id) ? $closerSetters->closer_id : $closerSetters->setter_id;
            $userInfo = User::where('id', $userId)->first();
            $userPids = UserReconciliationWithholding::where('closer_id', $userId)->where('finalize_status', 0)->where('status', '!=', 'paid')->orWhere('setter_id', $userId)->where('status', '!=', 'paid')->where('finalize_status', 0)->pluck('pid');
            $userPid = SalesMaster::whereIn('pid', $userPids)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
            $withholdAmount = UserReconciliationWithholding::where('finalize_status', 0)
                ->whereIn('pid', $userPid)
                ->where('closer_id', $userId)
                ->where('status', '!=', 'paid')
                ->orWhere('setter_id', $userId)
                ->where('finalize_status', 0)
                ->whereIn('pid', $userPid)
                ->where('status', '!=', 'paid')
                ->sum('withhold_amount');
            $commissionWithholding = $withholdAmount;
            $totalOverRideDue = 0;
            $totalClawbackDue = 0;

            $totalOverRideDue = UserOverrides::where(['user_id' => $userId]);
            $totalOverRideDue->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $userPid) {
                return $query->whereIn('pid', $userPid)
                    ->whereBetween('m2_date', [$startDate, $endDate]);
            });
            $totalOverRideDue = $totalOverRideDue->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $userId])->where('overrides_settlement_type', 'reconciliation')->orWhere('status', 6)->where(['user_id' => $userId])->sum('amount');

            $totalClawbackDue = ClawbackSettlement::where('user_id', $userId)->where('payroll_id', 0);
            $totalClawbackDue->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $userPid) {
                return $query->whereIn('pid', $userPid)
                    ->whereBetween('date_cancelled', [$startDate, $endDate]);
            });
            $totalClawbackDue->where('clawback_type', 'reconciliation');
            $totalClawbackDue->where('status', '1');
            $totalClawbackDue->whereIn('pid', $userPid);
            $totalClawbackDue = $totalClawbackDue->with('salesDetail')->sum('clawback_amount');

            // $reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type','reconciliations')->where('user_id', $userId)->where('pid',$salePids)->whereBetween('created_at', [$startDate,$endDate])->first();
            $reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('payroll_status', null)->where('user_id', $userId)->whereIn('pid', $salePid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate);
            $reconCommission = $reconciliationsAdjustment->sum('commission_due');
            $reconoverRide = $reconciliationsAdjustment->sum('overrides_due');
            $reimbursement = $reconciliationsAdjustment->sum('reimbursement');
            $deduction = $reconciliationsAdjustment->sum('deduction');
            $adjustment = $reconciliationsAdjustment->sum('adjustment');
            $reimbursement = $reconciliationsAdjustment->sum('reimbursement');
            $commissionDue = isset($reconCommission) ? $reconCommission : 0;
            $overridesDue = isset($reconoverRide) ? $reconoverRide : 0;
            // $clawbackDue = isset($reconClawback)?$reconClawback:0;
            $reimbursement = isset($reimbursement) ? $reimbursement : 0;
            $deduction = isset($deduction) ? $deduction : 0;
            $adjustment = isset($adjustment) ? $adjustment : 0;
            $reconciliation = isset($reconciliation) ? $reconciliation : 0;

            $totalAdjustments = $commissionDue + $overridesDue + $reimbursement + $deduction + $adjustment + $reconciliation;
            // $total_due = ($commissionWithholding + $totalOverRideDue + ($totalClawbackDue) + ($totalAdjustments));
            // $recUser = ReconciliationStatusForSkipedUser::where('user_id',$userId)->where('start_date',$startDate)->where('end_date',$endDate)->where('status','skipped')->first();
            $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->first();
            if (isset($recUser) && $recUser != '') {
                $userSkip = 1;
            } else {
                $userSkip = 0;
            }

            $payrollPidCommissionGet = ReconciliationFinalizeHistory::whereIn('pid', $salePid)->where('user_id', $userId)->where('status', 'payroll');
            // $payrollPidCommission = $payrollPidCommissionGet->sum('commission');
            $payrollPidCommission = $payrollPidCommissionGet->sum('paid_commission');
            // $payrollPidOverride = $payrollPidCommissionGet->sum('override');
            $payrollPidOverride = $payrollPidCommissionGet->sum('paid_override');
            $payrollPidAdjustment = $payrollPidCommissionGet->sum('adjustments');
            $payrollPidClawback = $payrollPidCommissionGet->sum('clawback');
            $getCommissionPersontage = $payrollPidCommissionGet->first();

            if ($payrollPidCommission > 0 && $getCommissionPersontage->payout != null) {
                $persontage = $getCommissionPersontage->payout;
                $paidCommission = $payrollPidCommission;
            } else {
                $paidCommission = 0;
            }

            if ($payrollPidOverride > 0 && $getCommissionPersontage->payout != null) {
                $persontage = $getCommissionPersontage->payout;
                $paidOverride = $payrollPidOverride;
            } else {
                $paidOverride = 0;
            }
            if ($payrollPidAdjustment > 0) {
                $pidAdjustment = $payrollPidAdjustment;
            } else {
                $pidAdjustment = 0;
            }

            if ($payrollPidClawback > 0) {
                $pidClawback = $payrollPidClawback;
            } else {
                $pidClawback = 0;
            }

            $payrollCommissions = MoveToReconciliation::where('user_id', $userId)->where('payroll_id', $closerSetters->payroll_id);
            $payrollCommissions->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $userPid) {
                return $query->whereIn('pid', $userPid)
                    ->whereBetween('m2_date', [$startDate, $endDate]);
            });
            $payrollCommissions = $payrollCommissions->first();

            if (! empty($commissionWithholding) || ! empty($totalOverRideDue) || ! empty($totalClawbackDue) || ! empty($addjustment) || ! empty($payrollCommissions)) {
                $payrollCommission = MoveToReconciliation::where('user_id', $userId)->where('payroll_id', $closerSetters->payroll_id);
                $payrollCommission = $payrollCommission->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $userPid) {
                    return $query->whereIn('pid', $userPid)
                        ->whereBetween('m2_date', [$startDate, $endDate]);
                });

                $commissioPayroll = @$payrollCommission->sum('commission') ?: 0;
                $overridePayroll = @$payrollCommission->sum('override') ?: 0;
                $payrollPid = $paidCommission + $paidOverride;

                $addjustment = $totalAdjustments;

                $total_due = ($commissionWithholding + $totalOverRideDue + $commissioPayroll + $overridePayroll);

                if ($total_due > 0 || $total_due < 0) {
                    $totalDues = ($total_due) - $payrollPid;
                    $pay = ($totalDues * $recon_payout) / 100;
                } else {
                    $pay = 0;
                }
                if (isset($userInfo->image) && $userInfo->image != null) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$userInfo->image);
                } else {
                    $s3_image = null;
                }
                $myArray[] = [
                    'id' => $closerSetters->id,
                    'user_id' => $userId,
                    'pid' => $arrayPid,
                    'emp_img' => isset($userInfo->image) ? $userInfo->image : null,
                    'emp_img_s3' => $s3_image,
                    'emp_name' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                    'commissionWithholding' => ($commissionWithholding + $commissioPayroll) - $paidCommission,
                    'overrideDue' => isset($totalOverRideDue) ? ($totalOverRideDue + $overridePayroll) - $paidOverride : 0,
                    'total_due' => $total_due - $payrollPid,
                    'pay' => floatval($recon_payout),
                    'total_pay' => $pay,
                    'clawbackDue' => isset($totalClawbackDue) ? $totalClawbackDue : 0,
                    'totalAdjustments' => isset($addjustment) ? $addjustment : 0,
                    'payout' => ($pay + $addjustment - $totalClawbackDue),
                    'already_paid' => $payrollPid + $pidAdjustment,
                    'user_skip' => $userSkip,
                    'totalAdjustments' => $totalAdjustments,
                ];
            }
        }

        // code for sorting result by employee name ASC
        $emp_name = array_column($myArray, 'emp_name');
        array_multisort($emp_name, SORT_ASC, $myArray);
        // $data = $this->paginates($myArray, $perpage);
        $data = $myArray;

        if ($request->has('sort') && $request->input('sort') == 'commission') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'override') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'overrideDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'overrideDue'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'clawback') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'clawbackDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'clawbackDue'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'adjustments') {
            $val = $request->input('sort_val');
            //  $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'total_due') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_due'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_due'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'pay') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {

                array_multisort(array_column($data, 'pay'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'pay'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'payout') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'payout'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'payout'), SORT_ASC, $data);
            }
        }
        $data = $this->paginates($data, $perpage);

        return response()->json([
            'ApiName' => 'reconciliation_details',
            'status' => true,
            'message' => 'Successfully.',
            // 'finalize_status' =>$checkFinalizeStatus,
            'finalize_status' => 0,
            'data' => $data,
        ], 200);

    }

    public function ReconciliationListUserSkipped(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'start_date' => 'required',
                'end_date' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        // return $search = $request->search;
        $user_id = implode(',', $request->user_id);
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $userIds = explode(',', $user_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);
        $selectType = $request->select_type;
        if ($selectType == 'all') {
            if ($position_id == 'all' && $office_id != 'all') {
                $userId = User::whereIn('office_id', $officeId);

            } elseif ($office_id == 'all' && $position_id != 'all') {
                $userId = User::whereIn('sub_position_id', $positionId);
            } elseif ($office_id == 'all' && $position_id == 'all') {
                $userId = User::orderBy('id', 'desc');
            } else {
                $userId = User::whereIn('office_id', $officeId)->whereIn('sub_position_id', $positionId);
            }

            $userIds = $userId->pluck('id');
            $pid = UserReconciliationWithholding::whereIn('closer_id', $userIds)->where('finalize_status', 0)->where('status', 'unpaid')->orWhereIn('setter_id', $userIds)->where('status', 'unpaid')->where('finalize_status', 0)->pluck('pid');
            $salePid = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
            $findUsers = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->get();
            $userId = [];
            foreach ($findUsers as $findUser) {
                $userId[] = $findUser->salesMasterProcess->closer1_id;
                $userId[] = $findUser->salesMasterProcess->setter1_id;
            }
            $payrollUserId = array_unique($userId);
            foreach ($payrollUserId as $userId) {
                $userData = UserReconciliationWithholding::where('finalize_status', 0)
                    ->whereIn('closer_id', $userIds)
                    ->orWhereIn('setter_id', $userIds)
                    ->where('finalize_status', 0)
                    ->first();
                // $userId = isset($userData->closer_id)?$userData->closer_id:$userData->setter_id;

                $userData = User::where('id', $userId)->first();
                $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->first();
                if (! isset($recUser) && $recUser == '') {
                    ReconciliationStatusForSkipedUser::create([
                        'user_id' => $userId,
                        'office_id' => $userData->office_id,
                        'position_id' => $userData->sub_position_id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'skipped',
                    ]);
                }
            }
        } else {
            foreach ($userIds as $userId) {
                $userData = User::where('id', $userId)->first();
                $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->first();
                if (! isset($recUser) && $recUser == '') {
                    ReconciliationStatusForSkipedUser::create([
                        'user_id' => $userId,
                        'office_id' => $userData->office_id,
                        'position_id' => $userData->sub_position_id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'skipped',
                    ]);
                }
            }
        }

        return response()->json([
            'ApiName' => 'reconciliation user skipped',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function ReconciliationListUserSkippedUndo(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        // return $search = $request->search;
        $user_id = implode(',', $request->user_id);
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $userIds = explode(',', $user_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);
        foreach ($userIds as $userId) {
            $userData = User::where('id', $userId)->first();
            $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->delete();
        }

        return response()->json([
            'ApiName' => 'reconciliation user status undo',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function ReconciliationListEditOld(Request $request)
    {
        $id = $request->id;
        $pid = $request->pid;
        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if ($startDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Edit reconciliation commission adjustment',
                'status' => false,
                'message' => 'Please select start date and end date.',
            ], 400);
        }
        $adjustmentAmount = isset($request->adjust_amount) ? $request->adjust_amount : 0;
        $payout = UserReconciliationWithholding::where('closer_id', $userId)->where('id', $id)->where('pid', $pid)->orWhere('setter_id', $userId)->where('id', $id)->where('pid', $pid)->first();
        //     $payout->adjustment_amount =  $adjustmentAmount;
        //     $payout->comment =  $request->comment;
        //     $payout->save();

        $adjusDeatil = ReconciliationAdjustmentDetails::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('adjustment_type', 'commission')->first();
        if (isset($adjusDeatil) && $adjusDeatil != '') {
            $adjusDeatil->amount = $adjustmentAmount;
            $adjusDeatil->comment = $request->comment;
            $adjusDeatil->adjustment_type = 'commission';
            $adjusDeatil->comment_by = auth()->user()->id;
            $adjusDeatil->save();
        } else {
            $data = ReconciliationAdjustmentDetails::create(['user_id' => $userId, 'pid' => $pid, 'comment' => $request->comment, 'amount' => $adjustmentAmount, 'start_date' => $startDate, 'end_date' => $endDate, 'adjustment_type' => 'commission', 'comment_by' => Auth::user()->id]);
        }

        // $finalizeHistory = ReconciliationFinalizeHistory::where('user_id',$userId)->where('pid',$pid)->first();
        // $finalizeHistory->adjustments =  $adjustmentAmount;
        // $finalizeHistory->save();

        $data = ReconciliationsAdjustement::where('user_id', $userId)->where('pid', $pid)->where('adjustment_type', 'reconciliations')->where('payroll_move_status', null)->where('payroll_status', null)->where('type', 'commission')->first();
        if (isset($data) && $data != '') {
            $commiValu = isset($data->commission_due) ? $data->commission_due : 0;
            $data->user_id = $userId;
            $data->pid = $pid;
            $data->reconciliation_id = $payout->id;
            $data->comment = $request->comment;
            $data->comment_by = auth()->user()->id;
            $data->adjustment_type = 'reconciliations';
            $data->commission_due = $adjustmentAmount;
            $data->start_date = $startDate;
            $data->end_date = $endDate;
            $data->save();
        } else {
            $data = ReconciliationsAdjustement::create(['user_id' => $userId, 'reconciliation_id' => $payout->id, 'pid' => $pid, 'comment' => $request->comment, 'adjustment_type' => 'reconciliations', 'commission_due' => $adjustmentAmount, 'start_date' => $startDate, 'end_date' => $endDate, 'type' => 'commission', 'comment_by' => Auth::user()->id]);
        }
        $sumAdjust = ReconciliationAdjustmentDetails::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->sum('amount');
        $findRecon = ReconciliationFinalizeHistory::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'finalize')->first();
        if (isset($findRecon) && $findRecon != '') {
            // return $sumAdjust;
            $findRecon->adjustments = $sumAdjust;
            $findRecon->save();
        }
        // $payout = UserReconciliationWithholding::where('closer_id',$userId)->where('id',$id)->where('pid',$pid)->orWhere('setter_id',$userId)->where('id',$id)->where('pid',$pid)->first();
        // $payout->adjustment_amount =  $adjustmentAmount;
        // $payout->save();

        return response()->json([
            'ApiName' => 'Edit reconciliation commission adjustment',
            'status' => true,
            'message' => 'Update Successfully.',
        ], 200);
        // ReconciliationsAdjustement

    }

    public function ReconciliationListEdit(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required',
                'user_id' => 'required',
                'pid' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
                'adjust_amount' => 'required',
                'comment' => 'required',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        return app(self::RECON_POPUP_CLASS)->reconAdjustmentEdit($request, 'commission');

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $id = $request->id;
        $userId = $request->user_id;
        $pid = $request->pid;
        $adjustmentAmount = $request->adjust_amount;
        $comment = $request->comment;
        $commentBy = auth()->user()->id;
        /* check adjust amount is  move to recon or direct recon */
        $checkAdjustmentAmount = MoveToReconHistory::where([
            'type' => 'commission',
            'type_id' => $id,
            'user_id' => $userId,
        ])->first();
        if ($checkAdjustmentAmount) {
            $userCommissionData = UserCommission::find($id);
            $data = [
                'payroll_id' => $userCommissionData->payroll_id,
                'user_id' => $userId,
                'pid' => $pid,
                'payroll_type' => 'commission',
                'type' => $userCommissionData->amount_type,
                'amount' => $adjustmentAmount,
                'comment' => $comment,
                'comment_by' => $commentBy,
                'status' => 1,
            ];

            $dataPayroll = PayrollAdjustmentDetail::where([
                'payroll_id' => $checkAdjustmentAmount->pid,
                'user_id' => $userId,
                'pid' => $pid,
                'type' => $userCommissionData->amount_type,
                'payroll_type' => 'commission'])->first();
            if ($dataPayroll) {
                PayrollAdjustmentDetail::where('id', $dataPayroll->id)->update($data);
                // Payroll::where('id',$checkAdjustmentAmount->pid)->update(['status'=>6,'finalize_status'=>0, "is_move_to_recon" => 1]);
            } else {
                PayrollAdjustmentDetail::create($data);
            }

            $PayrollAdjustment = PayrollAdjustment::where([
                'payroll_id' => $userCommissionData->payroll_id,
                'user_id' => $userId]
            )->first();

            $totalAmount = PayrollAdjustmentDetail::where(['payroll_id' => $userCommissionData->payroll_id, 'user_id' => $userId, 'payroll_type' => 'commission'])->sum('amount');
            if ($PayrollAdjustment) {
                $updateAjustment = PayrollAdjustment::where([
                    'payroll_id' => $userCommissionData->payroll_id,
                    'user_id' => $userId,
                ])->update([
                    'commission_amount' => $totalAmount,
                    'status' => 6,
                    'is_move_to_recon' => 1,
                ]);
            } else {
                $data1 = [
                    'payroll_id' => $userCommissionData->payroll_id,
                    'user_id' => $userId,
                    'commission_amount' => $totalAmount,
                    'overrides_amount' => 0,
                    'adjustments_amount' => 0,
                    'reimbursements_amount' => 0,
                    'deductions_amount' => 0,
                    'reconciliations_amount' => 0,
                    'clawbacks_amount' => 0,
                    'status' => 6,
                    'is_move_to_recon' => 1,
                ];
                PayrollAdjustment::Create($data1);
            }
        } else {
            $adjustmentAmount = isset($request->adjust_amount) ? $request->adjust_amount : 0;
            $payout = UserReconciliationWithholding::where(function ($join) use ($userId) {
                $join->where('closer_id', $userId)
                    ->orWhere('setter_id', $userId);
            })->whereNotNull('withhold_amount')
                ->where('pid', $pid)
                ->first();
            /* $payout = UserReconciliationWithholding::where('closer_id',$userId)
                ->where('id',$id)
                ->where('pid',$pid)
                ->orWhere('setter_id',$userId)
                ->where('id',$id)
                ->where('pid',$pid)
                ->first(); */

            $adjusDeatil = ReconciliationAdjustmentDetails::where('user_id', $userId)
                ->where('pid', $pid)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->where('adjustment_type', 'commission')
                ->first();
            if (isset($adjusDeatil) && $adjusDeatil != '') {
                $adjusDeatil->amount = $adjustmentAmount;
                $adjusDeatil->comment = $request->comment;
                $adjusDeatil->adjustment_type = 'commission';
                $adjusDeatil->comment_by = $commentBy;
                $adjusDeatil->save();
            } else {
                $data = ReconciliationAdjustmentDetails::create([
                    'user_id' => $userId,
                    'pid' => $pid,
                    'comment' => $request->comment,
                    'amount' => $adjustmentAmount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'adjustment_type' => 'commission',
                    'comment_by' => $commentBy,
                ]);
            }

            $data = ReconciliationsAdjustement::where('user_id', $userId)->where('pid', $pid)->where('adjustment_type', 'reconciliations')->where('payroll_move_status', null)->where('payroll_status', null)->where('type', 'commission')->first();
            if (isset($data) && $data != '') {
                $commiValu = isset($data->commission_due) ? $data->commission_due : 0;
                $data->user_id = $userId;
                $data->pid = $pid;
                $data->reconciliation_id = $payout->id;
                $data->comment = $request->comment;
                $data->comment_by = $commentBy;
                $data->adjustment_type = 'reconciliations';
                $data->commission_due = $adjustmentAmount;
                $data->start_date = $startDate;
                $data->end_date = $endDate;
                $data->save();
            } else {
                $data = ReconciliationsAdjustement::create(['user_id' => $userId,
                    'reconciliation_id' => $payout->id,
                    'pid' => $pid,
                    'comment' => $request->comment,
                    'adjustment_type' => 'reconciliations',
                    'commission_due' => $adjustmentAmount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'type' => 'commission',
                    'comment_by' => $commentBy]);
            }
            $sumAdjust = ReconciliationAdjustmentDetails::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->sum('amount');
            $findRecon = ReconciliationFinalizeHistory::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'finalize')->first();
            if (isset($findRecon) && $findRecon != '') {
                // return $sumAdjust;
                $findRecon->adjustments = $sumAdjust;
                $findRecon->save();
            }
        }

        return response()->json([
            'ApiName' => 'Edit reconciliation commission adjustment',
            'status' => true,
            'message' => 'Update Successfully.',
        ], 200);
    }

    public function ReconciliationOverridesListEdit(Request $request)
    {
        return app(self::RECON_POPUP_CLASS)->overrideAdjustmentEdit($request);
        $id = $request->id;
        $pid = $request->pid;
        $userId = $request->user_id;
        $type = $request->type;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if ($request->start_date == '' && $request->end_date == '') {
            return response()->json([
                'ApiName' => 'Edit reconciliation override adjustment',
                'status' => false,
                'message' => 'Please select sart date and end date.',
            ], 400);
        }
        $adjustmentAmount = $request->adjust_amount;
        // $payout = UserOverrides::where('user_id',$userId)->where('id',$id)->where('type',$type)->where('pid',$pid)->where('overrides_settlement_type','reconciliation')->first();

        $payout = UserOverrides::where(['user_id' => $userId, 'id' => $id]);
        $payout->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $pid) {
            return $query->where('pid', $pid)
                ->whereBetween('m2_date', [$startDate, $endDate]);
        });

        $payout = $payout->with('salesDetail', 'userpayrolloverride')->where(function ($qry) {
            return $qry->where(['overrides_settlement_type' => 'reconciliation', 'status' => 1])->orWhere('status', 6);
        })
            ->where(['user_id' => $userId, 'id' => $id])->first();

        if ($payout) {
            //     $payout->adjustment_amount =  $adjustmentAmount;
            //     $payout->comment =  $request->comment;
            //     $payout->save();
            $adjusDeatil = ReconciliationAdjustmentDetails::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('type', $request->type)->where('adjustment_type', 'overrides')->first();
            if (isset($adjusDeatil) && $adjusDeatil != '') {
                $adjusDeatil->amount = $adjustmentAmount;
                $adjusDeatil->comment = $request->comment;
                $adjusDeatil->type = $request->type;
                $adjusDeatil->adjustment_type = 'overrides';
                $adjusDeatil->comment_by = Auth::user()->id;
                $adjusDeatil->save();
            } else {
                $data = ReconciliationAdjustmentDetails::create(['user_id' => $userId, 'pid' => $pid, 'comment' => $request->comment, 'amount' => $adjustmentAmount, 'start_date' => $startDate, 'end_date' => $endDate, 'type' => $request->type, 'adjustment_type' => 'overrides', 'reconciliation_id' => $request->id, 'comment_by' => Auth::user()->id]);
            }
            $data = ReconciliationsAdjustement::where('user_id', $userId)->where('pid', $pid)->where('override_type', $type)->where('payroll_move_status', null)->where('payroll_status', null)->where('start_date', $startDate)->where('end_date', $endDate)->where('type', 'overrides')->first();
            if (isset($data) && $data != '') {
                $data->user_id = $userId;
                $data->pid = $pid;
                $data->comment = $request->comment;
                $data->override_type = $type;
                $data->adjustment_type = 'reconciliations';
                $data->overrides_due = $adjustmentAmount;
                $data->start_date = $request->start_date;
                $data->end_date = $request->end_date;
                $data->comment_by = auth()->user()->id;
                $data->save();
            } else {
                $data = ReconciliationsAdjustement::create(['user_id' => $userId, 'pid' => $pid, 'override_type' => $type, 'comment' => $request->comment, 'adjustment_type' => 'reconciliations', 'overrides_due' => $adjustmentAmount, 'start_date' => $request->start_date, 'end_date' => $request->end_date, 'type' => 'overrides', 'reconciliation_id' => $request->id, 'comment_by' => Auth::user()->id]);
            }
            // $payout = UserReconciliationWithholding::where('closer_id',$userId)->where('id',$id)->where('pid',$pid)->orWhere('setter_id',$userId)->where('id',$id)->where('pid',$pid)->first();
            // $payout->adjustment_amount =  $adjustmentAmount;
            // $payout->save();

            $sumAdjust = ReconciliationAdjustmentDetails::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->sum('amount');
            $findRecon = ReconciliationFinalizeHistory::where('user_id', $userId)->where('pid', $pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'finalize')->first();
            if (isset($findRecon) && $findRecon != '') {
                // return $sumAdjust;
                $findRecon->adjustments = $sumAdjust;
                $findRecon->save();
            }

        }

        return response()->json([
            'ApiName' => 'Edit reconciliation override adjustment',
            'status' => true,
            'message' => 'Update Successfully.',
        ], 200);
    }

    public function payrollReconciliationHistory(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $reconciliation = UserReconciliationCommission::where('status', 'payroll')->where(['period_from' => $startDate, 'period_to' => $endDate]);
        $result = $reconciliation->get();
        // return $result;
        if (count($result) > 0) {
            foreach ($result as $key1 => $val) {
                $userdata = User::where('id', $val->user_id)->first();

                $reconciliationsAdjustment = ReconciliationsAdjustement::where('reconciliation_id', $val->id)->first();
                $commissionDue = isset($reconciliationsAdjustment->commission_due) ? $reconciliationsAdjustment->commission_due : 0;
                $overridesDue = isset($reconciliationsAdjustment->overrides_due) ? $reconciliationsAdjustment->overrides_due : 0;
                $clawbackDue = isset($reconciliationsAdjustment->clawback_due) ? $reconciliationsAdjustment->clawback_due : 0;

                $totalAdjustments = $commissionDue + $overridesDue + $clawbackDue;

                $myArray[] = [
                    'id' => $val->id,
                    'user_id' => $val->user_id,
                    'emp_img' => $userdata->image,
                    'emp_name' => $userdata->first_name.' '.$userdata->last_name,
                    'commissionWithholding' => $val->amount,
                    'overrideDue' => $val->overrides,
                    'clawbackDue' => $val->clawbacks,
                    'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                    'total_due' => $val->total_due,
                    'pay_period_from' => $val->pay_period_from,
                    'pay_period_to' => $val->pay_period_to,
                ];
            }
        }

        // code for sorting result by employee name  ASC
        $emp_name = array_column($myArray, 'emp_name');
        array_multisort($emp_name, SORT_ASC, $myArray);

        $data = $this->paginate($myArray);

        return response()->json([
            'ApiName' => 'payrollReconciliationHistory',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => 1,
            'data' => $data,
        ], 200);

    }

    public function reconciliationFinalizeDraft(Request $request)
    {
        return app(\App\Http\Controllers\API\V1\ReconFinalizeController::class)->reconciliationFinalizeDraft($request);
    }

    public function reconciliationFinalizeDraftList(Request $request)
    {
        return app(\App\Http\Controllers\API\V1\ReconController::class)->finalizeReconDraftList($request);

    }

    public function reconciliationPayrollHistoriesList(Request $request)
    {
        return app(\App\Http\Controllers\API\V1\ReconReportController::class)->mainReport($request);

    }

    public function finalizeReconciliationList(Request $request)
    {
        return app(\App\Http\Controllers\API\V1\ReconController::class)->finalizeReconciliationList($request);
    }

    public function payrollReconciliationList(Request $request)
    {
        return app(\App\Http\Controllers\API\V1\ReconReportController::class)->userReconReport($request);
    }

    public function exportPayrollReconciliationList(Request $request)
    {
        return app(\App\Http\Controllers\API\V1\ReconReportController::class)->userReconReportExport($request);
    }

    public function sendTopayrollList(Request $request)
    {
        $data = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->groupBy('user_id');
        if ($request->has('search')) {
            $data->whereHas(
                'user', function ($query) use ($request) {
                    $query->where('first_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
        }
        $data = $data->with('user')->get();

        $data->transform(function ($data) use ($request) {

            $officeId = explode(',', $data->office_id);
            if ($data->position_id == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($positionid as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }

            if ($data->office_id == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeId as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $userCalculation = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->where('user_id', $data->user_id);

            $commission = $userCalculation->sum('paid_commission');
            $overrideDue = $userCalculation->sum('paid_override');
            $clawbackDue = $userCalculation->sum('clawback');
            $totalAdjustments = $userCalculation->sum('adjustments');
            $totalDue = $commission + $overrideDue + $clawbackDue + $totalAdjustments;
            $netPay = $userCalculation->sum('net_amount');

            return $myArray[] = [
                'user_id' => $data->user_id,
                'emp_img' => isset($data->user->image) ? $data->user->image : null,
                'emp_name' => isset($data->user->first_name) ? $data->user->first_name.' '.$data->user->last_name : null,
                'commissionWithholding' => isset($commission) ? $commission : 0,
                'overrideDue' => isset($overrideDue) ? $overrideDue : 0,
                'clawbackDue' => isset($clawbackDue) ? $clawbackDue : 0,
                'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                'total_due' => $totalDue,
                'payout' => $data->payout,
                'net_pay' => $netPay,
            ];

        });

        return response()->json([
            'ApiName' => 'Reconciliation Payroll List',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function reconciliationByUser(Request $request)
    {

        $userData = ReconciliationFinalizeHistory::where('user_id', $request->user_id)->groupBy('start_date')->get();
        $userData->transform(function ($userData) {
            $data = ReconciliationFinalizeHistory::where('user_id', $userData->user_id)
                ->where('start_date', $userData->start_date)
                ->where('end_date', $userData->end_date)
                ->get();
            $commision = 0;
            $override = 0;
            $clawback = 0;
            $adjustments = 0;
            $grossAmount = 0;
            $netPay = 0;
            $payout = 0;
            foreach ($data as $datas) {
                $commision += $datas->commission;
                $override += $datas->override;
                $clawback += $datas->clawback;
                $adjustments += $datas->adjustments;
                $grossAmount += $datas->gross_amount;
                $netPay += $datas->net_amount;
                $payout = $datas->payout;
            }

            return $val = [
                'start_date' => $userData->start_date,
                'end_date' => $userData->end_date,
                'commission' => $commision,
                'overrides' => $override,
                'clawback' => $clawback,
                'adjustments' => $adjustments,
                'totalDou' => $grossAmount,
                'netPayment' => $netPay,
                'payout' => $payout,
                // 'next_recon' => $grossAmount-$netPay,
            ];
        });
        $totalCommision = $userData->sum('commission');
        $totalOverride = $userData->sum('overrides');
        $totalClawback = $userData->sum('clawback');
        $totalAdjustments = $userData->sum('adjustments');
        $grossAmount = $userData->sum('totalDou');
        $payout = $userData->sum('netPayment');
        $total = [
            'totalCommision' => $totalCommision,
            'override' => $totalOverride,
            'clawback' => $totalClawback,
            'adjustments' => $totalAdjustments,
            'gross_amount' => $grossAmount,
            'payout' => $payout,
            'nextRecon' => $grossAmount - $payout,
        ];

        return response()->json([
            'ApiName' => 'Reconciliation By User Id',
            'status' => true,
            'message' => 'Successfully.',
            'total' => $total,
            'data' => $userData,
        ], 200);

    }

    public function checkUserClosePayroll(Request $request)
    {
        $user_id = implode(',', $request->user_id);
        $userId = explode(',', $user_id);
        $data = User::whereIn('id', $userId)->where('stop_payroll', 1)->get();
        if (count($data) > 0) {
            $data->transform(function ($data) {
                return $myArray[] = [
                    'user_id' => $data->id,
                    'emp_img' => $data->image,
                    'emp_name' => $data->first_name.' '.$data->last_name,
                ];
            });

            return response()->json([
                'ApiName' => 'Check stop payroll for user api',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Check stop payroll for user api',
                'status' => false,
                'message' => 'Data is not find.',
            ], 400);
        }

    }

    public function sendToPayrollRecon(Request $request): JsonResponse
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $data = $request->data;
        $currentDate = Carbon::now();
        $date = $currentDate->format('Y-m-d h:i:s');

        $UserReconciliationCommissions = ReconciliationFinalizeHistory::where('status', 'finalize')->where('start_date', $startDate)->where('end_date', $endDate)->get();
        $stopUserPayRoll = 0;
        if (count($UserReconciliationCommissions) > 0) {
            $subtotal = 0;
            $overrides = ReconOverrideHistory::where('status', 'finalize')->where('start_date', $startDate)->where('end_date', $endDate)->get();

            if (isset($overrides) && $overrides != '[]') {
                $overrideSent = ReconOverrideHistory::where('status', 'payroll')->where('start_date', $startDate)->where('end_date', $endDate)->orderBy('id', 'desc')->first();
                $overrideSentCount = isset($overrideSent->sent_count) ? $overrideSent->sent_count : 0;
                $overridesCount = $overrideSentCount + 1;
                foreach ($overrides as $override) {
                    $overrides = ReconOverrideHistory::where('id', $override->id)->update(['status' => 'payroll', 'sent_count' => $overridesCount]);
                }
            }
            $sendCount = 0;
            $userReconCount = ReconciliationFinalizeHistory::where('status', 'payroll')->orderBy('id', 'desc')->first();
            $count = isset($userReconCount->sent_count) ? $userReconCount->sent_count : 0;
            $sendCount = $count + 1;
            foreach ($UserReconciliationCommissions as $key => $UserReconciliationCommission) {
                $userCalculation = ReconciliationFinalizeHistory::where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id);
                $commission = $userCalculation->sum('paid_commission');
                $overrideDue = $userCalculation->sum('paid_override');
                $clawbackDue = $userCalculation->sum('clawback');
                $totalAdjustments = $userCalculation->sum('adjustments');
                $totalDue = $userCalculation->sum('gross_amount');
                $netPay = $commission + $overrideDue + $totalAdjustments - $clawbackDue;

                $userdata = User::with('positionDetail')->where('id', $UserReconciliationCommission->user_id)->first();
                if ($userdata->stop_payroll == 0) {
                    if ($userdata->positionDetail->payFrequency->frequency_type_id == 1) {
                        // $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->get();
                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'sent_count' => $sendCount, 'executed_on' => $date, 'pay_period_from' => $data['daily']['pay_period_from'], 'pay_period_to' => $data['daily']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->payroll_id])->update(['status' => 7]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['daily']['pay_period_from'], 'pay_period_to' => $data['daily']['pay_period_to']])->first();
                        if ($paydata) {

                            $updateData = [
                                'reconciliation' => $UserReconciliationCommission->net_amount,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['daily']['pay_period_from'],
                                'pay_period_to' => $data['daily']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['daily']['pay_period_from'],
                                'pay_period_to' => $data['daily']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->net_amount,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['daily']['pay_period_from'],
                                'pay_period_to' => $data['daily']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['daily']['pay_period_from'],
                                    'pay_period_to' => $data['daily']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId]);
                            }
                        }

                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 2) {

                        // $userReconCount = ReconciliationFinalizeHistory::where('status', 'payroll')->where('user_id', $UserReconciliationCommission->user_id)->where('pid',$UserReconciliationCommission->pid)->where('start_date',$startDate)->where('end_date',$endDate)->orderBy('id','desc')->first();

                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'sent_count' => $sendCount, 'executed_on' => $date, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->payroll_id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->payroll_id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']])->first();
                        if ($paydata) {
                            if ($paydata->reconciliation > 0) {
                                $recon = $paydata->reconciliation;
                            } else {
                                $recon = 0;
                            }
                            $updateData = [
                                'reconciliation' => $netPay + $recon,
                            ];

                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['weekly']['pay_period_from'],
                                'pay_period_to' => $data['weekly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {

                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                            // overRides ----------------------------------
                            $userReconOver = UserOverrides::where([
                                'overrides_settlement_type' => 'reconciliation',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pid' => $UserReconciliationCommission->pid,
                            ])->first();
                            if (isset($userReconOver) && $userReconOver != '') {
                                $update = UserOverrides::where([
                                    'overrides_settlement_type' => 'reconciliation',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pid' => $UserReconciliationCommission->pid,
                                ])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $UserReconciliationCommission->pay_period_from,
                                    'pay_period_to' => $UserReconciliationCommission->pay_period_to, 'adjustment_amount' => 0]);
                            }
                            $commissionAdju = UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->orWhere('setter_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->get();
                            if (isset($commissionAdju) && $commissionAdju != '') {
                                UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->orWhere('setter_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->update(['adjustment_amount' => 0]);
                            }

                            //  Adjustment -------------
                            $adjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->get();
                            if (isset($adjustment) && $adjustment != '') {
                                ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->update(['payroll_status' => 'payroll', 'sent_count' => $sendCount]);
                            }
                            $adjustmentDetail = ReconciliationAdjustmentDetails::where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', $startDate)->where('end_date', $endDate)->delete();
                            // clawback -------------
                            MoveToReconciliation::where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->update(['status' => 1]);
                            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->first();
                            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                                ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                            }

                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['weekly']['pay_period_from'],
                                'pay_period_to' => $data['weekly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->net_amount,
                            ]);
                            $payRollId = $payroll_data->id;

                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['weekly']['pay_period_from'],
                                'pay_period_to' => $data['weekly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $count = isset($userReconcomm->sent_count) ? $userReconcomm->sent_count : 0;
                                // $sendCount = $count+1;
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId, 'sent_count' => $sendCount]);
                            }
                            // overRides ----------------------------------
                            $userReconOver = UserOverrides::where([
                                'overrides_settlement_type' => 'reconciliation',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pid' => $UserReconciliationCommission->pid,
                            ])->first();
                            if (isset($userReconOver) && $userReconOver != '') {
                                $update = UserOverrides::where([
                                    'overrides_settlement_type' => 'reconciliation',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pid' => $UserReconciliationCommission->pid,
                                ])->update(['payroll_id' => $payRollId, 'pay_period_from' => $UserReconciliationCommission->pay_period_from,
                                    'pay_period_to' => $UserReconciliationCommission->pay_period_to]);
                            }
                            //  Adjustment -------------
                            $adjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->get();
                            if (isset($adjustment) && $adjustment != '') {
                                ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->update(['payroll_status' => 'payroll']);
                            }
                            // clawback -------------

                            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->first();
                            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                                ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                            }
                        }
                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 5) {

                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'executed_on' => $date, 'pay_period_from' => $data['monthly']['pay_period_from'], 'pay_period_to' => $data['monthly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['monthly']['pay_period_from'], 'pay_period_to' => $data['monthly']['pay_period_to']])->first();
                        if ($paydata) {
                            if ($paydata->reconciliation > 0) {
                                $recon = $paydata->reconciliation;
                            } else {
                                $recon = 0;
                            }
                            $updateData = [
                                // 'reconciliation' => $UserReconciliationCommission->total_due,
                                'reconciliation' => $UserReconciliationCommission->net_amount + $recon,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['monthly']['pay_period_from'],
                                    'pay_period_to' => $data['monthly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                            // overRides ----------------------------------
                            $userReconOver = UserOverrides::where([
                                'overrides_settlement_type' => 'reconciliation',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pid' => $UserReconciliationCommission->pid,
                            ])->first();
                            if (isset($userReconOver) && $userReconOver != '') {
                                $update = UserOverrides::where([
                                    'overrides_settlement_type' => 'reconciliation',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pid' => $UserReconciliationCommission->pid,
                                ])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $UserReconciliationCommission->pay_period_from,
                                    'pay_period_to' => $UserReconciliationCommission->pay_period_to, 'adjustment_amount' => 0]);
                            }
                            $commissionAdju = UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->orWhere('setter_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->get();
                            if (isset($commissionAdju) && $commissionAdju != '') {
                                UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->orWhere('setter_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->update(['adjustment_amount' => 0]);
                            }

                            //  Adjustment -------------
                            $adjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->get();
                            if (isset($adjustment) && $adjustment != '') {
                                ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->update(['payroll_status' => 'payroll', 'sent_count' => $sendCount]);
                            }
                            $adjustmentDetail = ReconciliationAdjustmentDetails::where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', $startDate)->where('end_date', $endDate)->delete();
                            // clawback -------------
                            MoveToReconciliation::where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->update(['status' => 1]);
                            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->first();
                            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                                ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            $update = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                            ])->update(['payroll_id' => $payRollId]);

                            // overRides ----------------------------------
                            $userReconOver = UserOverrides::where([
                                'overrides_settlement_type' => 'reconciliation',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pid' => $UserReconciliationCommission->pid,
                            ])->first();
                            if (isset($userReconOver) && $userReconOver != '') {
                                $update = UserOverrides::where([
                                    'overrides_settlement_type' => 'reconciliation',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pid' => $UserReconciliationCommission->pid,
                                ])->update(['payroll_id' => $payRollId, 'pay_period_from' => $UserReconciliationCommission->pay_period_from,
                                    'pay_period_to' => $UserReconciliationCommission->pay_period_to]);
                            }
                            //  Adjustment -------------
                            $adjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->get();
                            if (isset($adjustment) && $adjustment != '') {
                                ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->update(['payroll_status' => 'payroll']);
                            }
                            // clawback -------------

                            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->first();
                            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                                ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                            }
                        }
                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {

                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'executed_on' => $date, 'pay_period_from' => $data['biweekly']['pay_period_from'], 'pay_period_to' => $data['biweekly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['biweekly']['pay_period_from'], 'pay_period_to' => $data['biweekly']['pay_period_to']])->first();
                        if ($paydata) {

                            $updateData = [
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['biweekly']['pay_period_from'],
                                'pay_period_to' => $data['biweekly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['biweekly']['pay_period_from'],
                                    'pay_period_to' => $data['biweekly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['biweekly']['pay_period_from'],
                                'pay_period_to' => $data['biweekly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['biweekly']['pay_period_from'],
                                'pay_period_to' => $data['biweekly']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId]);
                            }
                        }

                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {

                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'executed_on' => $date, 'pay_period_from' => $data['semimonthly']['pay_period_from'], 'pay_period_to' => $data['semimonthly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['semimonthly']['pay_period_from'], 'pay_period_to' => $data['semimonthly']['pay_period_to']])->first();
                        if ($paydata) {

                            $updateData = [
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                'pay_period_to' => $data['semimonthly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                    'pay_period_to' => $data['semimonthly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                'pay_period_to' => $data['semimonthly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                'pay_period_to' => $data['semimonthly']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                    'pay_period_to' => $data['semimonthly']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId]);
                            }

                        }

                    }
                } else {
                    $stopUserPayRoll += 1;
                }
                if ($UserReconciliationCommission->payout == 100) {
                    $updateStatus = UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('finalize_status', 0)
                        ->where('status', 'unpaid')
                        ->orWhere('setter_id', $UserReconciliationCommission->user_id)
                        ->where('finalize_status', 0)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('status', 'unpaid')
                        ->update(['finalize_status' => 1]);
                }

                if ($UserReconciliationCommission->commission == 0 && $UserReconciliationCommission->commission == 0) {
                    $updateStatus = UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('finalize_status', 0)
                        ->where('status', 'unpaid')
                        ->orWhere('setter_id', $UserReconciliationCommission->user_id)
                        ->where('finalize_status', 0)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('status', 'unpaid')
                        ->update(['finalize_status' => 1]);
                }
            }
        }

        if ($stopUserPayRoll == 1) {
            return response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Can not send to payroll. Payroll has been stopped for this employee.',
            ], 200);
        } elseif ($stopUserPayRoll > 1) {
            return response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Some users Cannot send to payroll. Because Payroll has been stopped for these employee.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        }
    }

    public function deleteReconAdjustement(Request $request): JsonResponse
    {
        $adjustmentId = $request->adjustment_id;
        $userId = $request->user_id;
        $type = $request->type;
        $typeOverride = isset($request->type_override) ? $request->type_override : null;
        try {

            $payrollAdjustmentDetail = ReconciliationsAdjustement::where('id', $adjustmentId)->where('user_id', $userId);
            $data = $payrollAdjustmentDetail->first();
            if ($data == '') {
                return response()->json([
                    'ApiName' => 'delete adjustement',
                    'status' => false,
                    'message' => 'Bad request',
                ], 400);
            }
            if ($type == 'overrides') {
                $data->overrides_due = 0;
                $data->save();
                $over = ReconciliationAdjustmentDetails::where('pid', $data->pid)->where('user_id', $userId)->where('adjustment_type', 'overrides')->where('type', $typeOverride)->first();
                if (isset($over)) {
                    // $over->amount = 0;
                    $over->delete();
                }
            }
            if ($type == 'commission') {
                $data->commission_due = 0;
                $data->save();
                $adj = ReconciliationAdjustmentDetails::where('pid', $data->pid)->where('user_id', $userId)->where('adjustment_type', 'commission')->first();
                if (isset($adj) && $adj != '') {
                    // $adj->amount = 0;
                    $adj->delete();

                }
            }
            if ($type == 'clawback') {
                $data->clawback_due = 0;
                $data->save();
            }

            if ($type == 'reimbursement') {
                $data->reimbursement = 0;
                $data->save();
            }

            if ($type == 'deduction') {
                $data->deduction = 0;
                $data->save();
            }
            if ($type == 'adjustment') {
                $data->adjustment = 0;
                $data->save();
            }
            if ($type == 'reconciliation') {
                $data->reconciliation = 0;
                $data->save();
            }
            $message = 'Deleted Successfully.';

        } catch (Exception $e) {

            $message = $e->getMessage();
        }

        return response()->json([
            'ApiName' => 'delete adjustement',
            'status' => true,
            'message' => $message,
        ], 200);
    }

    public function payrollReconOverridebyEmployeeId(Request $request, $id)
    {
        return app(self::RECON_POPUP_CLASS)->reconOverridePop($request, $id);
        $apiName = 'PayRoll Reconciliation OverRides By employee Id';
        $valid = validator($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        if ($valid->fails()) {
            return response()->json([
                'ApiName' => $apiName,
                'status' => false,
                'message' => $valid->errors()->first(),
            ]);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->search;

        $pid = UserReconciliationWithholding::query()
            ->whereHas('salesDetail', function ($qry) use ($startDate, $endDate, $search) {
                $qry->whereBetween('m2_date', [$startDate, $endDate]);
                // Apply search parameters if they exist
                if (! empty($search)) {
                    $qry->where(function ($subQuery) use ($search) {
                        $subQuery->where('pid', 'LIKE', '%'.$search.'%')
                            ->orWhere('customer_name', 'LIKE', '%'.$search.'%');
                    });
                }
                /* $qry->whereBetween('m2_date',[$startDate,$endDate])
                    ->when(@request('search'),function($q){
                        $q->where('pid','like','%'.request('search').'%')
                            ->orWhere('customer_name','like','%'.request('search').'%');
                    }); */
            })
            ->where(function ($qry) use ($id) {
                $qry->where('closer_id', $id)->orWhere('setter_id', $id);
            })
            ->where('finalize_status', 0)
            ->where('status', '!=', 'paid')
            ->pluck('pid');

        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid')->toArray();

        $data = UserOverrides::where(['user_id' => $id]);
        $data->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $salePid) {
            return $query->whereIn('pid', $salePid)->whereBetween('m2_date', [$startDate, $endDate]);
        });

        $data = $data->with('salesDetail', 'userpayrolloverride')->where(function ($qry) {
            return $qry->where('overrides_settlement_type', 'reconciliation')->orWhere('status', 6);
        })->get();

        $data->transform(function ($data) use ($startDate, $endDate, $id) {
            $payout = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'payroll')->first();
            $paidTotal = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'payroll')->sum('paid_override');
            // $overPaid = ReconOverrideHistory::where('pid',$data->pid)->where('user_id',$data->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->where('status','finalize')->where('type',$data->type)->first();
            $adjustmantDetail = ReconciliationAdjustmentDetails::where('user_id', $id)->where('pid', $data->pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('type', $data->type)->where('adjustment_type', 'overrides')->sum('amount');
            $overPaid = ReconOverrideHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'finalize')->where('type', $data->type)->first();
            $paidOver = ReconOverrideHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'payroll')->where('type', $data->type)->sum('paid');
            $overrideComment = ReconciliationAdjustmentDetails::where('user_id', $id)->where('pid', $data->pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('type', $data->type)->where('adjustment_type', 'overrides')->first();
            if (isset($overrideComment) && $overrideComment != '') {
                $comment = 1;
            } else {
                $comment = 0;
            }
            if (isset($payout->payout) && $payout->payout != 0) {
                $pay = $payout->payout;
            } else {
                $pay = 0;
            }

            if (isset($data->overrides_amount) && $data->overrides_amount != 0) {
                $totalPaid = ($data->amount * $pay) / 100;

            } else {
                $totalPaid = 0;
            }

            if (isset($overPaid) && $overPaid != '') {
                return [
                    'id' => $data->id,
                    'user_id' => $data->user_id,
                    'pid' => $data->pid,
                    'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                    'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                    'image' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                    'override_over_image' => isset($data->userpayrolloverride->image) ? $data->userpayrolloverride->image : null,
                    'override_over_first_name' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                    'override_over_last_name' => isset($data->userpayrolloverride->last_name) ? $data->userpayrolloverride->last_name : null,
                    'type' => $data->type,
                    'rep_redline' => $data->userpayrolloverride->redline,
                    'kw' => $data->kw,
                    'overrides_type' => $data->overrides_type,
                    'overrides_amount' => $overPaid->override_amount,
                    // 'ss'=>'salf',
                    'amount' => $data->amount,
                    'paid' => $paidTotal,
                    'in_recon' => ($overPaid->total_amount),
                    'adjustment_amount' => $adjustmantDetail,
                    'comment_status' => isset($overrideComment) ? 1 : 0,
                    'adjustment_comment' => $overrideComment?->comment,
                    'adjustment_by' => $overrideComment?->commentUser->first_name.' '.$overrideComment?->commentUser->last_name,
                    'is_move_to_recon' => $data->is_move_to_recon,
                    'is_super_admin' => $data->userdata->is_super_admin,
                    'is_manager' => $data->userdata->is_manager,
                    'position_id' => $data->userdata->position_id,
                    'sub_position_id' => $data->userdata->sub_position_id,
                ];
            } else {

                // $overPaidDone = ReconOverrideHistory::where('pid',$data->pid)->where('user_id',$data->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->where('type',$data->type)->where('status','payroll');
                $overPaidDone = ReconOverrideHistory::where('pid', $data->pid)->where('user_id', $data->user_id)->where('type', $data->type)->where('status', 'payroll');
                $overSendAmount = $overPaidDone->sum('paid');
                $overPaidDone = $overPaidDone->first();
                $totalOverPay = isset($overPaidDone->paid) ? $overPaidDone->paid : 0;
                $reconhist = $data->amount - $paidOver;
                if (isset($overPaidDone) && $overPaidDone != '') {
                    return [
                        'id' => $data->id,
                        'user_id' => $data->user_id,
                        'pid' => $data->pid,
                        'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                        'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                        // 'image' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_image' => isset($data->userpayrolloverride->image) ? $data->userpayrolloverride->image : null,
                        'override_over_first_name' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_last_name' => isset($data->userpayrolloverride->last_name) ? $data->userpayrolloverride->last_name : null,
                        'type' => $data->type,
                        'rep_redline' => $data->userpayrolloverride->redline,
                        'kw' => $data->kw,
                        'overrides_type' => $data->overrides_type,
                        'overrides_amount' => $data->overrides_amount,
                        'amount' => $data->amount,
                        // 'ss'=>$totalOverPay,
                        'paid' => $paidOver,
                        'in_recon' => ($reconhist),
                        'adjustment_amount' => $adjustmantDetail,
                        'comment_status' => isset($overrideComment) ? 1 : 0,
                        'adjustment_comment' => $overrideComment?->comment,
                        'adjustment_by' => $overrideComment?->commentUser?->first_name.' '.$overrideComment?->commentUser->last_name,
                        'is_move_to_recon' => $data->is_move_to_recon,
                    ];
                } else {

                    return [
                        'id' => $data->id,
                        'user_id' => $data->user_id,
                        'pid' => $data->pid,
                        'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                        'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                        // 'image' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_image' => isset($data->userpayrolloverride->image) ? $data->userpayrolloverride->image : null,
                        'override_over_first_name' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_last_name' => isset($data->userpayrolloverride->last_name) ? $data->userpayrolloverride->last_name : null,
                        'type' => $data->type,
                        'rep_redline' => $data->userpayrolloverride->redline,
                        'kw' => $data->kw,
                        'overrides_type' => $data->overrides_type,
                        'overrides_amount' => $data->overrides_amount,
                        'amount' => $data->amount,
                        // 'ss'=>$totalOverPay,
                        'paid' => isset($overSendAmount) ? $overSendAmount : 0,
                        'in_recon' => ($reconhist),
                        'adjustment_amount' => $adjustmantDetail,
                        'comment_status' => isset($overrideComment) ? 1 : 0,
                        'adjustment_comment' => $overrideComment?->comment,
                        'adjustment_by' => $overrideComment?->commentUser?->first_name.' '.$overrideComment?->commentUser?->last_name,
                        'is_move_to_recon' => $data->is_move_to_recon,
                    ];
                }
            }
        });

        $commissionTotal = UserOverrides::where(['user_id' => $id]);
        $commissionTotal->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $salePid) {
            return $query->whereIn('pid', $salePid)->whereBetween('m2_date', [$startDate, $endDate]);
        });

        $commissionTotal = $commissionTotal->with('salesDetail', 'userpayrolloverride')->where(function ($qry) {
            return $qry->where('overrides_settlement_type', 'reconciliation')->orWhere('status', 6);
        })->get();
        // $commissionTotal = $commissionTotal->with('salesDetail','userpayrolloverride')->where(['user_id'=>$id, 'overrides_settlement_type'=>'reconciliation','status'=>1])->get();
        $subtotal = 0;
        if (isset($commissionTotal) && $commissionTotal != '[]') {
            foreach ($commissionTotal as $datas) {
                $payout = ReconciliationFinalizeHistory::where('pid', $datas->pid)->where('user_id', $datas->user_id)->where('status', 'payroll')->first();
                $overPaid = ReconOverrideHistory::where('pid', $datas->pid)->where('user_id', $datas->user_id)->where('status', 'finalize')->where('type', $datas->type)->first();

                if (isset($overPaid) && $overPaid == '') {
                    $subtotal += $datas->amount;
                } else {

                    // $overPaidDone = ReconOverrideHistory::where('pid',$data->pid)->where('user_id',$data->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->where('type',$data->type)->where('status','payroll');
                    $overPaidDone = ReconOverrideHistory::where('pid', $datas->pid)->where('user_id', $datas->user_id)->where('type', $datas->type)->where('status', 'payroll');
                    $overSendAmount = $overPaidDone->sum('paid');
                    $overPaidDone = $overPaidDone->first();
                    $totalOverPay = isset($overPaidDone->paid) ? $overPaidDone->paid : 0;

                    $reconhist = $datas->amount - $totalOverPay;
                    if (isset($overPaidDone) && $overPaidDone != '') {
                        $subtotal += $datas->amount - $overSendAmount;
                    } else {
                        $subtotal += $datas->amount;
                    }
                }
            }

            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
                'sub_total' => $subtotal,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data,
                'sub_total' => $subtotal,
            ], 200);
        }
    }

    public function commissionAdjustmentComment(Request $request): JsonResponse
    {
        $salePid = $request->pid;
        $id = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $adjustmantDetails = ReconciliationAdjustmentDetails::with('user')->where('user_id', $id)->where('pid', $salePid)->where('start_date', $startDate)->where('end_date', $endDate)->where('adjustment_type', 'commission')->get();
        if (! empty($adjustmantDetails) && $adjustmantDetails != '[]') {
            $comment = [];
            foreach ($adjustmantDetails as $adjustmantDetail) {
                $comment[] = [
                    'pid' => $adjustmantDetail->pid,
                    'user_id' => $id,
                    'override_over_image' => isset($adjustmantDetail->user->image) ? $adjustmantDetail->user->image : null,
                    'override_over_first_name' => isset($adjustmantDetail->user->first_name) ? $adjustmantDetail->user->first_name : null,
                    'override_over_last_name' => isset($adjustmantDetail->user->last_name) ? $adjustmantDetail->user->last_name : null,
                    'comment' => $adjustmantDetail->comment,
                ];
            }

            return response()->json([
                'ApiName' => 'Commission comment list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $comment,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Commission comment list',
                'status' => false,
                'message' => 'Bad request.',
            ], 400);
        }
    }

    public function overrideAdjustmentComment(Request $request): JsonResponse
    {
        $salePid = $request->pid;
        $id = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $adjustmantDetails = ReconciliationAdjustmentDetails::with('user')->where('user_id', $id)->where('pid', $salePid)->where('start_date', $startDate)->where('end_date', $endDate)->where('adjustment_type', 'overrides')->get();
        if (! empty($adjustmantDetails) && $adjustmantDetails != '[]') {

            foreach ($adjustmantDetails as $adjustmantDetail) {
                if (isset($adjustmantDetail->comment) && $adjustmantDetail->comment != '') {
                    $comment[] = [
                        'pid' => $adjustmantDetail->pid,
                        'user_id' => $id,
                        'override_over_image' => isset($adjustmantDetail->user->image) ? $adjustmantDetail->user->image : null,
                        'override_over_first_name' => isset($adjustmantDetail->user->first_name) ? $adjustmantDetail->user->first_name : null,
                        'override_over_last_name' => isset($adjustmantDetail->user->last_name) ? $adjustmantDetail->user->last_name : null,
                        'comment' => $adjustmantDetail->comment,
                    ];
                } else {
                    $comment[] = '';
                }
            }

            return response()->json([
                'ApiName' => 'Override comment list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $comment,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Override comment list',
                'status' => false,
                'message' => 'Bad request.',
            ], 400);
        }
    }

    public function userRepotRecon(Request $request, $id)
    {
        $data = ReconciliationFinalizeHistory::orderBy('id', 'asc')->groupBy('sent_count')->where('status', 'payroll');
        if ($request->has('search')) {
            $data->where('start_date', $request->search)->orWhere('end_date', $request->search)
                ->orWhereHas('office', function ($query) use ($request) {
                    $query->where('office_name', 'LIKE', '%'.$request->search.'%');
                })
                ->orWhereRaw('CONCAT(start_date, "-", end_date) LIKE ?', ['%'.$request->search.'%'])
                ->orWhereRaw('CONCAT(start_date, " - ", end_date) LIKE ?', ['%'.$request->search.'%'])
                ->orWhereRaw('CONCAT(start_date, " ", end_date) LIKE ?', ['%'.$request->search.'%'])
                ->orWhereHas('position', function ($query) use ($request) {
                    $query->where('position_name', 'LIKE', '%'.$request->search.'%');
                });
        }
        $data = $data->with('position', 'office')->where('user_id', $id)->get();

        $totalCommision = 0;
        $totalOverride = 0;
        $totalClawback = 0;
        $totalAdjustments = 0;
        $grossAmount = 0;
        $payout = 0;
        $data->transform(function ($data) use ($id) {
            $total = [];
            $positionId = ReconciliationFinalizeHistory::where('sent_count', $data->sent_count)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')->where('user_id', $id)->pluck('position_id');
            $officeId = ReconciliationFinalizeHistory::where('sent_count', $data->sent_count)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')->where('user_id', $id)->pluck('office_id');
            $uniqueArray = collect($positionId)->unique()->values()->all();
            if ($uniqueArray[0] == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($uniqueArray as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }
            $officeIdArray = collect($officeId)->unique()->values()->all();
            if ($officeIdArray[0] == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeIdArray as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $val = ReconciliationFinalizeHistory::where('sent_count', $data->sent_count)
                ->orderBy('id', 'asc')
                ->where('start_date', $data->start_date)
                ->where('end_date', $data->end_date)
                ->where('user_id', $id)
                ->where('status', 'payroll');

            $totalSumComm = $val->sum('commission');
            $totalSumOver = $val->sum('override');
            $sumComm = $val->sum('paid_commission');
            $sumOver = $val->sum('paid_override');
            $sumClaw = $val->sum('clawback');
            $sumAdju = $val->sum('adjustments');
            $sumGross = $val->sum('gross_amount');
            $sumPayout = $val->sum('net_amount');

            $totalDue = ($sumComm + $sumOver + $sumAdju) - $sumClaw;
            $nextRecon = ($totalSumComm + $totalSumOver + $sumAdju) - $sumClaw;

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
                'recon' => ((int) ($sumComm + $sumOver + $sumAdju) - $sumClaw).'('.$data->payout.'%)',
                'nextrecon' => $nextRecon - $totalDue,
                'status' => $data->status,
                'sent_id' => $data->sent_count,
            ];

        });

        $dataCalculate = ReconciliationFinalizeHistory::orderBy('id', 'asc')->groupBy('sent_count')->where('status', 'payroll');
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
        $dataCalculate = $dataCalculate->where('user_id', $id)->get();

        foreach ($dataCalculate as $dataCalculates) {
            $vals = ReconciliationFinalizeHistory::orderBy('id', 'asc')->where('start_date', $dataCalculates->start_date)->where('end_date', $dataCalculates->end_date)->where('sent_count', $dataCalculates->sent_count)->where('status', 'payroll')->where('user_id', $id);
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
            // 'year' => isset($executedOn)?$executedOn:date('Y'),
            'nextRecon' => $grossAmount - $payout,
        ];

        return response()->json([
            'ApiName' => 'reconciliation payroll list',
            'status' => true,
            'message' => 'Successfully.',
            'total' => $total,
            'data' => $data,
        ], 200);
    }

    public function oldpayrollReconCommissionbyEmployeeId(Request $request, $id)
    {
        // $pid = ReconciliationFinalizeHistory::where('user_id',$id)->pluck('pid');
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $pid = UserReconciliationWithholding::where('closer_id', $id)->where('finalize_status', 0)->where('status', '!=', 'paid')->orWhere('setter_id', $id)->where('status', '!=', 'paid')->where('finalize_status', 0)->pluck('pid');
        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
        if ($startDate == '' && $endDate == '') {
            $data = UserReconciliationWithholding::with('salesDetail')
                ->where('closer_id', $id)
                ->where('finalize_status', 0)
                ->where('withhold_amount', '!=', 0)
                ->orWhere('setter_id', $id)
                ->where('finalize_status', 0)
                ->where('withhold_amount', '!=', 0)
                ->get();
        } else {
            $data = UserReconciliationWithholding::where('finalize_status', 0)
                ->whereIn('pid', $salePid)
                ->where('closer_id', $id)
              // ->where('withhold_amount','!=',0)
                ->orWhere('setter_id', $id)
                ->where('finalize_status', 0)
                ->whereIn('pid', $salePid)
              // ->where('withhold_amount','!=',0)
                ->get();
        }

        $data->transform(function ($data) use ($id, $startDate, $endDate) {
            $userId = ($data->closer_id != null) ? $data->closer_id : $data->setter_id;
            $redline = User::where('id', $userId)->select('redline', 'redline_type')->first();
            $payout = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'payroll')->first();
            $finalizePer = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'finalize')->first();
            if (isset($payout->payout) && $payout->payout != '') {
                $payOut = $payout->payout;
            } else {
                $payOut = 0;
            }

            if ($data->withhold_amount > 0) {
                $totalPaid = $data->withhold_amount * $payOut / 100;
            } else {
                $totalPaid = 0;
            }
            $location = Locations::with('State')->where('general_code', $data->salesDetail->customer_state)->first();
            if ($location) {
                $state_code = $location->state->state_code;
            } else {
                $state_code = null;
            }

            $paidAmount = ReconciliationFinalizeHistory::where('user_id', $id)->where('pid', $data->pid)->where('status', 'payroll')->sum('paid_commission');
            $paidAdjustmant = ReconciliationsAdjustement::where('user_id', $id)->where('pid', $data->pid)->where('payroll_status', '!=', 'payroll')->sum('adjustment');
            $adjustmantDetail = ReconciliationAdjustmentDetails::where('user_id', $id)->where('pid', $data->pid)->where('start_date', $startDate)->where('end_date', $endDate)->where('adjustment_type', 'commission')->sum('amount');

            $payrollTorecon = MoveToReconciliation::where('user_id', $id)->where('pid', $data->pid);
            $payrollToreconCommission = @$payrollTorecon->sum('commission') ?: 0;
            $totalMoveCommission = $data->withhold_amount + $payrollToreconCommission;

            if ($paidAmount) {
                $recon = $totalMoveCommission - $paidAmount;
            } else {
                $recon = $totalMoveCommission;
            }
            if (isset($adjustmantDetail) && $adjustmantDetail != '') {
                $adjustmantDetail = $adjustmantDetail;
            } else {
                $adjustmantDetail = 0;
            }

            return [
                'id' => $data->id,
                'user_id' => $userId,
                'pid' => $data->pid,
                'state_id' => $state_code,
                'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                'rep_redline' => isset($redline->redline) ? $redline->redline : null,
                'kw' => isset($redline->redline_type) ? $redline->redline_type : null,
                'net_epc' => $data->salesDetail->net_epc,
                'epc' => $data->salesDetail->epc,
                'adders' => $data->salesDetail->adders,
                'type' => 'Withheld', // $data->type,
                'amount' => $totalMoveCommission,
                'paid' => $paidAmount,
                'in_recon' => $recon,
                'finalize_payout' => 0,
                'adjustment_amount' => isset($adjustmantDetail) ? $adjustmantDetail : 0,
            ];
        });

        $commissionTotal = UserReconciliationWithholding::with('salesDetail')
            ->where('closer_id', $id)
            ->whereIn('pid', $salePid)
            ->where('finalize_status', 0)
            ->orWhere('setter_id', $id)
            ->whereIn('pid', $salePid)
            ->where('finalize_status', 0)
            ->get();
        $subtotal = 0;

        foreach ($commissionTotal as $datas) {
            $userId = ($datas->closer_id != null) ? $datas->closer_id : $datas->setter_id;
            $redline = User::where('id', $userId)->select('redline', 'redline_type')->first();
            $payout = ReconciliationFinalizeHistory::where('pid', $datas->pid)->where('user_id', $userId)->first();
            if (isset($payout->payout) && $payout->payout != '') {
                $payOut = $payout->payout;
            } else {
                $payOut = 100;
            }
            if ($datas->withhold_amount > 0) {
                $totalPaid = $datas->withhold_amount * $payOut / 100;
            } else {
                $totalPaid = 0;
            }

            $paidAmount = ReconciliationFinalizeHistory::where('user_id', $id)->where('pid', $datas->pid)->where('status', 'payroll')->sum('paid_commission');

            $payrollTorecon = MoveToReconciliation::where('user_id', $id)->where('pid', $datas->pid);
            $payrollToreconCommission = @$payrollTorecon->sum('commission') ?: 0;
            $totalMoveCommission = (@$datas->withhold_amount ?: 0) + $payrollToreconCommission;
            if ($paidAmount) {
                $recon = $totalMoveCommission - $paidAmount;
            } else {
                $recon = $totalMoveCommission;
            }

            $subtotal += $recon;
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'subtotal' => $subtotal,
        ], 200);

    }

    public function payrollReconCommissionbyEmployeeId(Request $request, $id)
    {
        return app(self::RECON_POPUP_CLASS)->commissionReconPopup($request, $id);
    }

    public function payrollReconClawbackListbyEmployeeId(Request $request, $id)
    {
        return app(self::RECON_POPUP_CLASS)->reconClawbackPopup($request, $id);
    }

    public function payrollReconAdjustementbyEmployeeId(Request $request, $id)
    {
        $response = [];
        $costCenters = [];
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',

            ], 400);
        }
        $totalAdjustmant = 0;
        $totalAdjustmantDeduct = 0;
        $totalAdjustmantDue = 0;

        $totalAdjustmantDeduction = 0;
        $totalAdjustmantDeductDeduction = 0;
        $totalAdjustmantDueDeduction = 0;
        // commission ---------------------------
        $commission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('payroll_status', null);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $commission->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $commission = $commission->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($commission) > 0) {
            $totals = 0;
            $comCount = 0;
            foreach ($commission as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $paidCommission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('pid', $val->pid)->sum('commission_due');
                        $totals += isset($val->commission_due) ? $val->commission_due : 0;
                        $key += 1;
                        $comCount += 1;
                        $response['commission']['data'][] = [
                            'id' => $val->id,
                            // 'payroll_id' => $val->payroll_id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'deducted' => isset($paidCommission) ? $paidCommission : 0,
                            'due' => $val->commission_due - $paidCommission,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due/Moved from payroll',
                            'input_type' => 'commission',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $paidCommission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('commission_due');
                        $totals += isset($val->commission_due) ? $val->commission_due : 0;
                        $key += 1;
                        $comCount += 1;
                        $response['commission']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'deducted' => isset($paidCommission) ? $paidCommission : 0,
                            'due' => $val->commission_due - $paidCommission,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due',
                            'input_type' => 'commission',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $totalAdjustmant = $totalAdjustmant + $totals;
            $totalAdjustmantDeduct = 0;
            $totalAdjustmantDue = $totalAdjustmantDue + $totals;
            $response['commission']['total'] = [
                'count' => $comCount,
                'total' => $totals,
                'deducted' => 0,
                'due' => $totals,
            ];
        }
        // overrides ----------------------------
        $overrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)
            ->where('payroll_status', null);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $overrides->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $overrides = $overrides->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($overrides) > 0) {
            $totals = 0;
            $oveCount = 0;
            foreach ($overrides as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $paidOverrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('overrides_due');
                        $totals += isset($val->overrides_due) ? $val->overrides_due : 0;
                        $key += 1;
                        $oveCount += 1;
                        $response['overrides']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'deducted' => isset($paidOverrides) ? $paidOverrides : 0,
                            'due' => $val->overrides_due - $paidOverrides,
                            'override_type' => isset($val->override_type) ? $val->override_type : null,
                            'adjustment_by' => isset($userInfo->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($userInfo->image) ? $val->user->image : null,
                            'type' => 'Overrides Due/Moved from payroll',
                            'input_type' => 'overrides',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $paidOverrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('overrides_due');
                        $totals += isset($val->overrides_due) ? $val->overrides_due : 0;
                        $key += 1;
                        $oveCount += 1;
                        $response['overrides']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'deducted' => isset($paidOverrides) ? $paidOverrides : 0,
                            'due' => $val->overrides_due - $paidOverrides,
                            'override_type' => isset($val->override_type) ? $val->override_type : null,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Overrides Due',
                            'input_type' => 'overrides',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $totalAdjustmant = $totalAdjustmant + $totals;
            $totalAdjustmantDeduct = 0;
            $totalAdjustmantDue = $totalAdjustmantDue + $totals;
            $response['overrides']['total'] = [
                'count' => $oveCount,
                'total' => $totals,
                'deducted' => 0,
                'due' => $totals,
            ];
        }
        // reimbursament ------------------------
        $reimbursement = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('payroll_status', null);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $reimbursement->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $reimbursement = $reimbursement->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($reimbursement) > 0) {
            foreach ($reimbursement as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $reimbursements = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', '!=', 'Declined')->get();
                        $amount = 0;
                        $totals = 0;
                        foreach ($reimbursements as $key => $reimbursementVal) {
                            $costVal = CostCenter::where('id', $reimbursementVal->cost_tracking_id)->first();
                            $reimbursementPaid = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', 'paid')->where('cost_tracking_id', $costVal->id)->first();
                            if (isset($reimbursementPaid) && $reimbursementPaid != '') {
                                $reimbursementPaidAmount = $reimbursementPaid->amount;
                            } else {
                                $reimbursementPaidAmount = 0;
                            }
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += isset($reimbursementVal->amount) ? $reimbursementVal->amount : 0;
                            $key = $key + 1;

                            $amount = isset($reimbursementVal->amount) ? $reimbursementVal->amount : 0;
                            $response['reimbursement'][$costVal->name][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $reimbursementVal->amount,
                                'deducted' => isset($reimbursementPaidAmount) ? $reimbursementPaidAmount : 0,
                                'due' => $reimbursementVal->amount - $reimbursementPaidAmount,
                                'adjustment_by' => isset($userInfo->first_name) ? $val->user->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Reimbursement Due/Moved from payroll',
                                'input_type' => 'reimbursement',
                                'comment' => isset($val->comment) ? $val->comment : null,
                            ];
                        }
                        $totalAdjustmant = $totalAdjustmant + $totals;
                        $totalAdjustmantDeduct = 0;
                        $totalAdjustmantDue = $totalAdjustmantDue + $totals;
                        $response['reimbursement']['total'] = [
                            'count' => count($reimbursements),
                            'total' => $totals,
                            'deducted' => 0,
                            'due' => $totals,
                        ];
                    }
                } else {
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $reimbursements = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', '!=', 'Declined')->get();
                        $amount = 0;
                        $totals = 0;
                        foreach ($reimbursements as $key => $reimbursementVal) {
                            $costVal = CostCenter::where('id', $reimbursementVal->cost_tracking_id)->first();
                            $reimbursementPaid = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', 'paid')->first();
                            if (isset($reimbursementPaid) && $reimbursementPaid != '') {
                                $reimbursementPaidAmount = $reimbursementPaid->amount;
                            } else {
                                $reimbursementPaidAmount = 0;
                            }
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += isset($reimbursementVal->amount) ? $reimbursementVal->amount : 0;
                            $key = $key + 1;
                            $response['reimbursement'] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $reimbursementVal->amount,
                                'deducted' => isset($reimbursementPaidAmount) ? $reimbursementPaidAmount : 0,
                                'due' => $reimbursementVal->amount - $reimbursementPaidAmount,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Reimbursement Due',
                                'input_type' => 'reimbursement',
                                'comment' => isset($val->comment) ? $val->comment : null,
                            ];
                        }
                        $totalAdjustmant = $totalAdjustmant + $totals;
                        $totalAdjustmantDeduct = 0;
                        $totalAdjustmantDue = $totalAdjustmantDue + $totals;
                        $response['reimbursement']['total'] = [
                            'count' => count($reimbursements),
                            'total' => $totals,
                            'deducted' => 0,
                            'due' => $totals,
                        ];
                    }
                }
            }
        }
        // Adjustment --------------------------------
        $adjustment = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('payroll_status', null);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $adjustment->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $adjustment = $adjustment->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($adjustment) > 0) {
            $totals = 0;
            $adjCount = 0;
            foreach ($adjustment as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $paidAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('adjustment');
                        $totals += isset($val->adjustment) ? $val->adjustment : 0;
                        $adjCount += 1;
                        $response['adjustment'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'deducted' => isset($paidAdjustment) ? $paidAdjustment : 0,
                            'due' => $val->adjustment - $paidAdjustment,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Adjustment Due/Moved from payroll',
                            'input_type' => 'adjustment',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $paidAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('adjustment');
                        $totals += isset($val->adjustment) ? $val->adjustment : 0;
                        $adjCount += 1;
                        $response['adjustment'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'deducted' => isset($paidAdjustment) ? $paidAdjustment : 0,
                            'due' => $val->adjustment - $paidAdjustment,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Adjustment Due',
                            'input_type' => 'adjustment',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $totalAdjustmant = $totalAdjustmant + $totals;
            $totalAdjustmantDeduct = 0;
            $totalAdjustmantDue = $totalAdjustmantDue + $totals;
            $response['adjustment']['total'] = [
                'count' => $adjCount,
                'total' => $totals,
                'deducted' => 0,
                'due' => $totals,
            ];
        }
        // reconciliation ------------------------------
        $reconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('payroll_status', null);

        if ($request->has('search') && ! empty($request->input('search'))) {
            $reconciliation->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $reconciliation = $reconciliation->with('user', 'reconciliationInfo', 'salesDetail')->get();

        if (count($reconciliation) > 0) {
            $totals = 0;
            $reconCount = 0;
            foreach ($reconciliation as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $paidReconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('reconciliation');
                        $totals += isset($val->reconciliation) ? $val->reconciliation : 0;
                        $reconCount += 1;
                        $response['reconciliation'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'deducted' => isset($paidReconciliation) ? $paidReconciliation : 0,
                            'due' => $val->reconciliation - $paidReconciliation,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Reconciliation/Moved from payroll',
                            'input_type' => 'reconciliation',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $paidReconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('reconciliation');
                        $totals += isset($val->reconciliation) ? $val->reconciliation : 0;
                        $reconCount += 1;
                        $response['reconciliation'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'deducted' => isset($paidReconciliation) ? $paidReconciliation : 0,
                            'due' => $val->reconciliation - $paidReconciliation,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Reconciliation',
                            'input_type' => 'reconciliation',
                            'comment' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $totalAdjustmant = $totalAdjustmant + $totals;
            $totalAdjustmantDeduct = 0;
            $totalAdjustmantDue = $totalAdjustmantDue + $totals;
            $response['reconciliation']['total'] = [
                'count' => $reconCount,
                'total' => $totals,
                'deducted' => 0,
                'due' => $totals,
            ];
        }
        // deduction ------------------------------------
        $deduction = ReconciliationsAdjustement::where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('payroll_status', null);

        if ($request->has('search') && ! empty($request->input('search'))) {
            $deduction->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $deduction = $deduction->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($deduction) > 0) {
            $deductionTotals = 0;
            foreach ($deduction as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();

                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->deduction > 0 || $val->deduction < 0) {

                        $deductionLists = PayrollDeductions::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->get();
                        $amount = 0;
                        $deductionTotals = 0;
                        $counts = 0;
                        foreach ($deductionLists as $key => $deductionList) {
                            $costVal = CostCenter::where('id', $deductionList->cost_center_id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $deductionTotals += $deductionList->amount;
                            $total[$costVal->name][] = $deductionList->amount;
                            $count[$costVal->name][] = $counts + 1;
                            $key = $key + 1;
                            $response['deduction'][$costVal->name]['data'][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $deductionList->amount,
                                'deducted' => 0,
                                'due' => isset($deductionList->amount) ? $deductionList->amount : 0,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Deduction Due/Moved from payroll',
                                'input_type' => 'deduction',
                                'comment' => isset($val->comment) ? $val->comment : null,
                            ];
                            $response['deduction'][$costVal->name]['count'] = array_sum($count[$costVal->name]);
                            $response['deduction'][$costVal->name]['total'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['deducted'] = 0;
                            $response['deduction'][$costVal->name]['due'] = array_sum($total[$costVal->name]);

                        }
                        $response['deduction']['deduction_total'] = [
                            'amount' => $deductionTotals,
                            'deducted' => 0,
                            'due' => $deductionTotals,
                        ];
                        $amount = 0;
                    }
                } else {
                    if ($val->deduction > 0 || $val->deduction < 0) {

                        $deductionLists = PayrollDeductions::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->groupBy('cost_center_id')->get();
                        $amount = 0;
                        $deductionTotals = 0;
                        $counts = 0;
                        foreach ($deductionLists as $deductionList) {
                            return $costVal = CostCenter::where('id', $deductionList->cost_center_id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $deductionTotals += $deductionList->amount;
                            $total[$costVal->name][] = $deductionList->amount;
                            $count[$costVal->name][] = $counts + 1;
                            $key = $key + 1;
                            $response['deduction'][$costVal->name]['data'][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $deductionList->amount,
                                'deducted' => 0,
                                'due' => isset($deductionList->amount) ? $deductionList->amount : 0,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Deduction Due/Moved from payroll',
                                'input_type' => 'deduction',
                                'comment' => isset($val->comment) ? $val->comment : null,
                            ];
                            $response['deduction'][$costVal->name]['count'] = array_sum($count[$costVal->name]);
                            $response['deduction'][$costVal->name]['total'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['deducted'] = 0;
                            $response['deduction'][$costVal->name]['due'] = array_sum($total[$costVal->name]);

                        }
                        $response['deduction']['deduction_total'] = [
                            'amount' => $deductionTotals,
                            'deducted' => 0,
                            'due' => $deductionTotals,
                        ];
                        $amount = 0;
                    }
                }
            }

            $totalAdjustmantDeduction = $totalAdjustmantDeduction + $deductionTotals;
            $totalAdjustmantDeductDeduction = 0;
            $totalAdjustmantDueDeduction = $totalAdjustmantDueDeduction + $deductionTotals;

            $adjustmentTotalDeduction['total'] = [
                'amount' => $totalAdjustmantDeduction,
                'deducted' => $totalAdjustmantDeductDeduction,
                'due' => $totalAdjustmantDueDeduction,
            ];

        }
        $adjustmentTotal['total'] = [
            'amount' => $totalAdjustmant,
            'deducted' => $totalAdjustmantDeduct,
            'due' => $totalAdjustmantDue,
        ];
        unset($data);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'adjustmant_total' => $adjustmentTotal,
            // 'adjustment_deduction_total' => $adjustmentTotalDeduction,
            'data' => $response,

        ], 200);

    }

    public function reportReconAdjustementbyEmployeeId(Request $request, $id): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $sentCount = $request->input('sent_id');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Report Reconciliation Commision By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',
            ], 400);
        }
        $response = [];
        $commission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $commission->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }

        $commission = $commission->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($commission) > 0) {
            $totals = 0;
            foreach ($commission as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $paidCommission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('pid', $val->pid)->where('payroll_move_status', 'from_payroll')->sum('commission_due');

                        $totals += isset($val->commission_due) ? $val->commission_due : 0;
                        $key += 1;
                        $response['commission']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'deducted' => isset($paidCommission) ? $paidCommission : 0,
                            'due' => $val->commission_due - $paidCommission,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due/Moved from payroll',
                            'input_type' => 'commission',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $paidCommission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('payroll_move_status', '!=', 'from_payroll')->sum('commission_due');
                        $totals += isset($val->commission_due) ? $val->commission_due : 0;
                        $key += 1;
                        $response['commission']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'deducted' => isset($paidCommission) ? $paidCommission : 0,
                            'due' => $val->commission_due - $paidCommission,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due',
                            'input_type' => 'commission',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $response['commission']['total'] = [
                'count' => $key,
                'total' => $totals,
                'deducted' => $totals,
                'due' => 0,
            ];
        }

        // overrides ----------------------------
        $overrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)
            ->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $overrides->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $overrides = $overrides->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($overrides) > 0) {
            $totals = 0;
            foreach ($overrides as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $paidOverrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('payroll_move_status', 'from_payroll')->sum('overrides_due');
                        $totals += isset($val->overrides_due) ? $val->overrides_due : 0;
                        $key += 1;
                        $response['overrides']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'deducted' => isset($paidOverrides) ? $paidOverrides : 0,
                            'due' => $val->overrides_due - $paidOverrides,
                            'override_type' => isset($val->override_type) ? $val->override_type : null,
                            'adjustment_by' => isset($userInfo->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($userInfo->image) ? $val->user->image : null,
                            'type' => 'Overrides Due/Moved from payroll',
                            'input_type' => 'overrides',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $paidOverrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('payroll_move_status', '!=', 'from_payroll')->sum('overrides_due');
                        $totals += isset($val->overrides_due) ? $val->overrides_due : 0;
                        $key += 1;
                        $response['overrides']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'deducted' => isset($paidOverrides) ? $paidOverrides : 0,
                            'due' => $val->overrides_due - $paidOverrides,
                            'override_type' => isset($val->override_type) ? $val->override_type : null,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Overrides Due',
                            'input_type' => 'overrides',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $response['overrides']['total'] = [
                'count' => $key,
                'total' => $totals,
                'deducted' => $totals,
                'due' => 0,
            ];
        }
        // reimbursament ------------------------
        $reimbursement = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $reimbursement->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $reimbursement = $reimbursement->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($reimbursement) > 0) {
            foreach ($reimbursement as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $reimbursements = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', '!=', 'Approved')->get();
                        $amount = 0;
                        $totals = 0;
                        foreach ($reimbursements as $key => $reimbursementVal) {
                            $costVal = CostCenter::where('id', $reimbursementVal->cost_tracking_id)->first();
                            $reimbursementPaid = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', 'paid')->where('id', $reimbursementVal->id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += isset($reimbursementVal->amount) ? $reimbursementVal->amount : 0;
                            $key = $key + 1;
                            $response['reimbursement'][$costVal->name][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $reimbursementVal->amount,
                                'deducted' => isset($reimbursementPaid->amount) ? $reimbursementPaid->amount : 0,
                                'due' => $reimbursementVal->amount - $reimbursementPaid->amount,
                                'adjustment_by' => isset($userInfo->first_name) ? $val->user->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Reimbursement Due/Moved from payroll',
                                'input_type' => 'reimbursement',
                                'description' => isset($val->comment) ? $val->comment : null,
                            ];
                        }
                        $response['reimbursement']['total'] = [
                            'count' => $key,
                            'total' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                    }
                } else {
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $reimbursements = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', '!=', 'Declined')->get();
                        $amount = 0;
                        $totals = 0;
                        foreach ($reimbursements as $key => $reimbursementVal) {
                            $costVal = CostCenter::where('id', $reimbursementVal->cost_tracking_id)->first();
                            $reimbursementPaid = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', 'paid')->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += isset($reimbursementVal->amount) ? $reimbursementVal->amount : 0;
                            $key = $key + 1;
                            $response['reimbursement'] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $reimbursementVal->amount,
                                'deducted' => isset($reimbursementPaid->amount) ? $reimbursementPaid->amount : 0,
                                'due' => $reimbursementVal->amount - $reimbursementPaid->amount,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Reimbursement Due',
                                'input_type' => 'reimbursement',
                                'description' => isset($val->comment) ? $val->comment : null,
                            ];
                        }
                        $response['reimbursement']['total'] = [
                            'count' => $key,
                            'total' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                    }
                }
            }
        }

        // Adjustment --------------------------------
        $adjustment = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $adjustment->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $adjustment = $adjustment->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($adjustment) > 0) {
            foreach ($adjustment as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $paidAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('pid', $val->pid)->sum('adjustment');
                        $response['adjustment']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'deducted' => isset($paidAdjustment) ? $paidAdjustment : 0,
                            'due' => $val->adjustment - $paidAdjustment,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Adjustment Due/Moved from payroll',
                            'input_type' => 'adjustment',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $paidAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('adjustment');
                        $response['adjustment'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'deducted' => isset($paidAdjustment) ? $paidAdjustment : 0,
                            'due' => $val->adjustment - $paidAdjustment,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Adjustment Due',
                            'input_type' => 'adjustment',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
        }
        // reconciliation ------------------------------
        $reconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $reconciliation->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $reconciliation = $reconciliation->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($reconciliation) > 0) {
            foreach ($reconciliation as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $paidReconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('reconciliation');
                        $response['reconciliation'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'deducted' => isset($paidReconciliation) ? $paidReconciliation : 0,
                            'due' => $val->reconciliation - $paidReconciliation,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Reconciliation/Moved from payroll',
                            'input_type' => 'reconciliation',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $paidReconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('reconciliation');
                        $response['reconciliation'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'deducted' => isset($paidReconciliation) ? $paidReconciliation : 0,
                            'due' => $val->reconciliation - $paidReconciliation,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Reconciliation',
                            'input_type' => 'reconciliation',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
        }
        // deduction ------------------------------------
        $deduction = ReconciliationsAdjustement::where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $deduction->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $deduction = $deduction->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($deduction) > 0) {
            foreach ($deduction as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->deduction > 0 || $val->deduction < 0) {

                        $deductionLists = PayrollDeductions::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->get();
                        $amount = 0;
                        $totals = 0;
                        $counts = 0;
                        foreach ($deductionLists as $key => $deductionList) {
                            $costVal = CostCenter::where('id', $deductionList->cost_center_id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += $deductionList->amount;
                            $total[$costVal->name][] = $deductionList->amount;
                            $count[$costVal->name][] = $counts + 1;
                            $key = $key + 1;
                            $response['deduction'][$costVal->name]['data'][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $deductionList->amount,
                                'deducted' => isset($deductionList->amount) ? $deductionList->amount : 0,
                                'due' => 0,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Deduction Due/Moved from payroll',
                                'input_type' => 'deduction',
                                'description' => isset($val->comment) ? $val->comment : null,
                            ];
                            $response['deduction'][$costVal->name]['count'] = array_sum($count[$costVal->name]);
                            $response['deduction'][$costVal->name]['total'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['deducted'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['due'] = 0;

                        }
                        $response['deduction']['deduction_total'] = [
                            'amount' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                        $amount = 0;
                    }
                } else {
                    if ($val->deduction > 0 || $val->deduction < 0) {

                        $deductionLists = PayrollDeductions::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->groupBy('cost_center_id')->get();
                        $amount = 0;
                        $totals = 0;
                        $counts = 0;
                        foreach ($deductionLists as $deductionList) {
                            $costVal = CostCenter::where('id', $deductionList->cost_center_id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += $deductionList->amount;
                            $total[$costVal->name][] = $deductionList->amount;
                            $count[$costVal->name][] = $counts + 1;
                            $key = $key + 1;
                            $response['deduction'][$costVal->name]['data'][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $deductionList->amount,
                                'deducted' => isset($deductionList->amount) ? $deductionList->amount : 0,
                                'due' => 0,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Deduction Due/Moved from payroll',
                                'input_type' => 'deduction',
                                'comment' => isset($val->comment) ? $val->comment : null,
                            ];
                            $response['deduction'][$costVal->name]['count'] = array_sum($count[$costVal->name]);
                            $response['deduction'][$costVal->name]['total'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['deducted'] = 0;
                            $response['deduction'][$costVal->name]['due'] = array_sum($total[$costVal->name]);

                        }
                        $response['deduction']['deduction_total'] = [
                            'amount' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                        $amount = 0;
                    }
                }
            }
        }

        unset($data);

        return response()->json([
            'ApiName' => 'Report Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,

        ], 200);

    }

    public function reportReconAdjustementbyEmployeeIdOld(Request $request, $id): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $sentCount = $request->input('sent_id');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Report Reconciliation Commision By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',
            ], 400);
        }
        $response = [];
        $commission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $commission->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }

        $commission = $commission->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($commission) > 0) {
            $totals = 0;
            foreach ($commission as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $paidCommission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('pid', $val->pid)->sum('commission_due');
                        $totals += isset($val->commission_due) ? $val->commission_due : 0;
                        $key += 1;
                        $response['commission']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'deducted' => isset($paidCommission) ? $paidCommission : 0,
                            'due' => $val->commission_due - $paidCommission,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due/Moved from payroll',
                            'input_type' => 'commission',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $paidCommission = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('commission_due');
                        $totals += isset($val->commission_due) ? $val->commission_due : 0;
                        $key += 1;
                        $response['commission']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'deducted' => isset($paidCommission) ? $paidCommission : 0,
                            'due' => $val->commission_due - $paidCommission,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due',
                            'input_type' => 'commission',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $response['commission']['total'] = [
                'count' => $key,
                'total' => $totals,
                'deducted' => $totals,
                'due' => 0,
            ];
        }

        // overrides ----------------------------
        $overrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)
            ->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $overrides->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $overrides = $overrides->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($overrides) > 0) {
            $totals = 0;
            foreach ($overrides as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $paidOverrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('overrides_due');
                        $totals += isset($val->overrides_due) ? $val->overrides_due : 0;
                        $key += 1;
                        $response['overrides']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'deducted' => isset($paidOverrides) ? $paidOverrides : 0,
                            'due' => $val->overrides_due - $paidOverrides,
                            'override_type' => isset($val->override_type) ? $val->override_type : null,
                            'adjustment_by' => isset($userInfo->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($userInfo->image) ? $val->user->image : null,
                            'type' => 'Overrides Due/Moved from payroll',
                            'input_type' => 'overrides',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $paidOverrides = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('overrides_due');
                        $totals += isset($val->overrides_due) ? $val->overrides_due : 0;
                        $key += 1;
                        $response['overrides']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'deducted' => isset($paidOverrides) ? $paidOverrides : 0,
                            'due' => $val->overrides_due - $paidOverrides,
                            'override_type' => isset($val->override_type) ? $val->override_type : null,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Overrides Due',
                            'input_type' => 'overrides',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
            $response['overrides']['total'] = [
                'count' => $key,
                'total' => $totals,
                'deducted' => $totals,
                'due' => 0,
            ];
        }
        // reimbursament ------------------------
        $reimbursement = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $reimbursement->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $reimbursement = $reimbursement->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($reimbursement) > 0) {
            foreach ($reimbursement as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $reimbursements = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', '!=', 'Approved')->get();
                        $amount = 0;
                        $totals = 0;
                        foreach ($reimbursements as $key => $reimbursementVal) {
                            $costVal = CostCenter::where('id', $reimbursementVal->cost_tracking_id)->first();
                            $reimbursementPaid = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', 'paid')->where('id', $reimbursementVal->id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += isset($reimbursementVal->amount) ? $reimbursementVal->amount : 0;
                            $key = $key + 1;
                            $response['reimbursement'][$costVal->name][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $reimbursementVal->amount,
                                'deducted' => isset($reimbursementPaid->amount) ? $reimbursementPaid->amount : 0,
                                'due' => $reimbursementVal->amount - $reimbursementPaid->amount,
                                'adjustment_by' => isset($userInfo->first_name) ? $val->user->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Reimbursement Due/Moved from payroll',
                                'input_type' => 'reimbursement',
                                'description' => isset($val->comment) ? $val->comment : null,
                            ];
                        }
                        $response['reimbursement']['total'] = [
                            'count' => $key,
                            'total' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                    }
                } else {
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $reimbursements = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', '!=', 'Declined')->get();
                        $amount = 0;
                        $totals = 0;
                        foreach ($reimbursements as $key => $reimbursementVal) {
                            $costVal = CostCenter::where('id', $reimbursementVal->cost_tracking_id)->first();
                            $reimbursementPaid = ApprovalsAndRequest::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->where('adjustment_type_id', 2)->where('status', 'paid')->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += isset($reimbursementVal->amount) ? $reimbursementVal->amount : 0;
                            $key = $key + 1;
                            $response['reimbursement'] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $reimbursementVal->amount,
                                'deducted' => isset($reimbursementPaid->amount) ? $reimbursementPaid->amount : 0,
                                'due' => $reimbursementVal->amount - $reimbursementPaid->amount,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Reimbursement Due',
                                'input_type' => 'reimbursement',
                                'description' => isset($val->comment) ? $val->comment : null,
                            ];
                        }
                        $response['reimbursement']['total'] = [
                            'count' => $key,
                            'total' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                    }
                }
            }
        }

        // Adjustment --------------------------------
        $adjustment = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $adjustment->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $adjustment = $adjustment->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($adjustment) > 0) {
            foreach ($adjustment as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $paidAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->where('pid', $val->pid)->sum('adjustment');
                        $response['adjustment']['data'][] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'deducted' => isset($paidAdjustment) ? $paidAdjustment : 0,
                            'due' => $val->adjustment - $paidAdjustment,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Adjustment Due/Moved from payroll',
                            'input_type' => 'adjustment',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $paidAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('adjustment');
                        $response['adjustment'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'deducted' => isset($paidAdjustment) ? $paidAdjustment : 0,
                            'due' => $val->adjustment - $paidAdjustment,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Adjustment Due',
                            'input_type' => 'adjustment',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
        }
        // reconciliation ------------------------------
        $reconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $reconciliation->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $reconciliation = $reconciliation->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($reconciliation) > 0) {
            foreach ($reconciliation as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $paidReconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('reconciliation');
                        $response['reconciliation'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->pid) ? $val->pid : '-',
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'deducted' => isset($paidReconciliation) ? $paidReconciliation : 0,
                            'due' => $val->reconciliation - $paidReconciliation,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Reconciliation/Moved from payroll',
                            'input_type' => 'reconciliation',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $paidReconciliation = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
                            ->where('end_date', '<=', $endDate)->where('payroll_status', 'payroll')->sum('reconciliation');
                        $response['reconciliation'] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'deducted' => isset($paidReconciliation) ? $paidReconciliation : 0,
                            'due' => $val->reconciliation - $paidReconciliation,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Reconciliation',
                            'input_type' => 'reconciliation',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                }
            }
        }
        // deduction ------------------------------------
        $deduction = ReconciliationsAdjustement::where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('sent_count', $sentCount);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $deduction->whereHas('salesDetail', function ($query) use ($request) {
                $query->where('customer_name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->search.'%');
            });
        }
        $deduction = $deduction->with('user', 'reconciliationInfo', 'salesDetail')->get();
        if (count($deduction) > 0) {
            foreach ($deduction as $key => $val) {
                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->deduction > 0 || $val->deduction < 0) {

                        $deductionLists = PayrollDeductions::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->get();
                        $amount = 0;
                        $totals = 0;
                        $counts = 0;
                        foreach ($deductionLists as $key => $deductionList) {
                            $costVal = CostCenter::where('id', $deductionList->cost_center_id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += $deductionList->amount;
                            $total[$costVal->name][] = $deductionList->amount;
                            $count[$costVal->name][] = $counts + 1;
                            $key = $key + 1;
                            $response['deduction'][$costVal->name]['data'][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $deductionList->amount,
                                'deducted' => 0,
                                'due' => isset($deductionList->amount) ? $deductionList->amount : 0,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Deduction Due/Moved from payroll',
                                'input_type' => 'deduction',
                                'description' => isset($val->comment) ? $val->comment : null,
                            ];
                            $response['deduction'][$costVal->name]['count'] = array_sum($count[$costVal->name]);
                            $response['deduction'][$costVal->name]['total'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['deducted'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['due'] = 0;

                        }
                        $response['deduction']['deduction_total'] = [
                            'amount' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                        $amount = 0;
                    }
                } else {
                    if ($val->deduction > 0 || $val->deduction < 0) {

                        $deductionLists = PayrollDeductions::where('payroll_id', $val->payroll_id)->where('user_id', $val->user_id)->groupBy('cost_center_id')->get();
                        $amount = 0;
                        $totals = 0;
                        $counts = 0;
                        foreach ($deductionLists as $deductionList) {
                            $costVal = CostCenter::where('id', $deductionList->cost_center_id)->first();
                            $date = Carbon::now();
                            $formattedDate = $costVal->created_at->format('m-d-Y');
                            $totals += $deductionList->amount;
                            $total[$costVal->name][] = $deductionList->amount;
                            $count[$costVal->name][] = $counts + 1;
                            $key = $key + 1;
                            $response['deduction'][$costVal->name]['data'][] = [
                                'id' => $val->id,
                                'cost_name' => $costVal->name,
                                'date' => $formattedDate,
                                'cost_head_code' => isset($costVal->code) ? $costVal->code : '-',
                                'amount' => $deductionList->amount,
                                'deducted' => 0,
                                'due' => isset($deductionList->amount) ? $deductionList->amount : 0,
                                'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                                'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                                'type' => 'Deduction Due/Moved from payroll',
                                'input_type' => 'deduction',
                                'comment' => isset($val->comment) ? $val->comment : null,
                            ];
                            $response['deduction'][$costVal->name]['count'] = array_sum($count[$costVal->name]);
                            $response['deduction'][$costVal->name]['total'] = array_sum($total[$costVal->name]);
                            $response['deduction'][$costVal->name]['deducted'] = 0;
                            $response['deduction'][$costVal->name]['due'] = array_sum($total[$costVal->name]);

                        }
                        $response['deduction']['deduction_total'] = [
                            'amount' => $totals,
                            'deducted' => $totals,
                            'due' => 0,
                        ];
                        $amount = 0;
                    }
                }
            }
        }

        unset($data);

        return response()->json([
            'ApiName' => 'Report Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,

        ], 200);

    }

    public function runPayrollReconciliationPopUp(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $payrollId = $request->payroll_id; // payroll_id
        $userId = $request->user_id;
        $payrollData = Payroll::find($payrollId)/* ->get() */;
        if (! $payrollData) {
            $payrollData = PayrollHistory::where('payroll_id', $payrollId)->get();
        }

        if (! empty($payrollData)) {
            $reconPayrollData = ReconciliationFinalizeHistory::where('payroll_id', $payrollId)
                ->where('user_id', $userId)
                ->get();
            $data = [];

            foreach ($reconPayrollData as $key => $result) {
                $payroll_status = (empty($result->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $period = (empty($result->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($result->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($result->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($result->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($result->payrollcommon->payroll_modified_date)) : '';

                if (! isset($data[$payroll_status])) {
                    $data[$payroll_status] = [];
                }

                if (! isset($data[$payroll_status][$period])) {
                    $data[$payroll_status][$period] = [];
                }

                $data[$payroll_status][$period][] = [
                    'added_to_payroll_on' => Carbon::parse($result->updated_at)->format('m-d-Y h:s:a'),
                    'startDate_endDate' => Carbon::parse($result->start_date)->format('m/d/Y').' to '.Carbon::parse($result->end_date)->format('m/d/Y'),
                    'commission' => $result->paid_commission,
                    'override' => $result->paid_override,
                    'clawback' => (-1 * $result->clawback),
                    'adjustment' => $result->adjustments - $result->deductions,
                    // 'total' => $result->gross_amount,
                    'total' => ($result->paid_commission + $result->paid_override + $result->adjustments - $result->deductions - $result->clawback),
                    'payout' => $result->payout,
                    'payout' => $result->payout,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                ];
            }

            return response()->json([
                'ApiName' => 'payroll in reconcitation popup  api ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }

        return response()->json([
            'ApiName' => 'payroll in reconcitation popup  api ',
            'status' => false,
            'message' => 'Successfully.',
            'data' => [],
        ], 200);
    }

    public function paystubReconciliationDetails(Request $request): JsonResponse
    {
        $validate = Validator::make($request->all(), [
            'payroll_id' => 'required',
            'user_id' => 'required|exists:users,id',
            'pay_period_from' => 'required|date_format:Y-m-d',
            'pay_period_to' => 'required|date_format:Y-m-d',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $datas = ReconciliationFinalizeHistory::where('payroll_id', $request->payroll_id)->where('user_id', $request->user_id)->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->get();
        $myArray = [];
        if (count($datas) > 0) {
            foreach ($datas as $data) {
                $commission = $data->commission;
                $payout = $data->payout;
                $override = $data->override;
                // $totalCommission = ($commission*$payout)/100;
                // $totalOverride = ($override*$payout)/100;
                $totalCommission = $data->paid_commission;
                $totalOverride = $data->paid_override;
                $clawback = $data->clawback;
                $adjustments = $data->adjustments - $data->deductions;
                $total = ($data->net_amount - $clawback + $adjustments);

                $myArray[] = [
                    'added_to_payroll_on' => Carbon::parse($data->updated_at)->format('m-d-Y h:s:a'),
                    'startDate_endDate' => Carbon::parse($data->start_date)->format('m/d/Y').' to '.Carbon::parse($data->end_date)->format('m/d/Y'),
                    'commission' => $totalCommission,
                    'override' => $totalOverride,
                    'clawback' => (-1 * $clawback),
                    'adjustment' => $adjustments,
                    'total' => $total,
                    'payout' => $payout,
                    // "finalize_count" => $data->finalize_count,
                    'finalize_count' => $data->finalize_id,
                    'finalize_id' => $data->finalize_id,
                    'start_date' => $data->start_date,
                    'end_date' => $data->end_date,
                ];
            }
        }

        return response()->json([
            'ApiName' => 'paystub reconcitation details api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $myArray,
        ], 200);

    }

    public function paystubReconciliationDetailsOld(Request $request): JsonResponse
    {
        $validate = Validator::make($request->all(), [
            'payroll_id' => 'required',
            'user_id' => 'required|exists:users,id',
            'pay_period_from' => 'required|date_format:Y-m-d',
            'pay_period_to' => 'required|date_format:Y-m-d',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $datas = ReconciliationFinalizeHistory::where('payroll_id', $request->payroll_id)->where('user_id', $request->user_id)->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->get();
        $myArray = [];
        if (count($datas) > 0) {
            foreach ($datas as $data) {
                $commission = $data->commission;
                $payout = $data->payout;
                $override = $data->override;
                $totalCommission = ($commission * $payout) / 100;
                $totalOverride = ($override * $payout) / 100;
                $clawback = $data->clawback;
                $adjustments = $data->adjustments;
                $total = $data->net_amount;

                $myArray[] = [
                    'added_to_payroll_on' => Carbon::parse($data->updated_at)->format('m-d-Y h:s:a'),
                    'startDate_endDate' => Carbon::parse($data->start_date)->format('m/d/Y').' to '.Carbon::parse($data->end_date)->format('m/d/Y'),
                    'commission' => $totalCommission,
                    'override' => $totalOverride,
                    'clawback' => $clawback,
                    'adjustment' => $adjustments,
                    'total' => $total,
                    'payout' => $payout,
                ];
            }
        }

        return response()->json([
            'ApiName' => 'paystub reconcitation details api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $myArray,
        ], 200);

    }

    public function paginate($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function exportReconciliationPayrollHistoriesList(Request $request)
    {
        $data = [];
        $all_paid = true;
        $file_name = 'reportReconpayrollList_'.date('Y_m_d_H_i_s').'.xlsx';
        $executedOn = $request->input('executed_on');
        Excel::store(new ExportReconPayrollList($executedOn),
            'exports/reports/reconciliation/'.$file_name,
            'public',
            \Maatwebsite\Excel\Excel::XLSX);
        $url = getStoragePath('exports/reports/reconciliation/'.$file_name);

        // $url = getExportBaseUrl().'storage/exports/reports/reconciliation/' . $file_name;
        // Get the URL for the stored file
        // Return the URL in the API response
        return response()->json(['url' => $url]);
        dd('');

        return Excel::download(new ExportReconPayrollList($executedOn), $file_name);

    }

    public function moveRunPayrollToReconStatus(Request $request): JsonResponse
    {
        $data = [];
        $payrollId = $request->payrollId;
        $date = date('Y-m-d');
        if (count($payrollId) > 0) {
            $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
            $data->transform(function ($data) {
                $payroll = Payroll::where(['id' => $data->id])->first();
                UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]);
                UserOverrides::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]);
                Payroll::where(['id' => $data->id])->update(['status' => 6]);

            });
        }

        return response()->json([
            'ApiName' => 'Move to reconciliations Api',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function moveRunPayrollToReconciliationsOld(Request $request): JsonResponse
    {
        $data = [];
        $payrollId = $request->payrollId;
        $date = date('Y-m-d');
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        // $data = Payroll::with('usersdata', 'positionDetail')->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date,'status'=>6])->get();
        $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
        if (count($data) > 0) {
            $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
            $data->transform(function ($data) use ($date) {
                $payroll = Payroll::where(['id' => $data->id])->first();
                $userCommissinDatas = UserCommission::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->where('pay_period_from', $payroll->pay_period_from)->where('pay_period_to', $payroll->pay_period_to)->where('status', '!=', 3)->get();
                if (isset($userCommissinDatas) && count($userCommissinDatas) > 0) {
                    foreach ($userCommissinDatas as $userCommissinData) {
                        $sales = SalesMaster::where('pid', $userCommissinData->pid)->first();
                        $userReconciliationCommission = UserReconciliationWithholding::where('closer_id', $data->user_id)->where('payroll_id', $payroll->id)->where('pid', $userCommissinData->pid)->where('finalize_status', 0)->orWhere('setter_id', $data->user_id)->where('pid', $userCommissinData->pid)->where('finalize_status', 0)->where('payroll_id', $payroll->id)->first();
                        if (isset($userReconciliationCommission) && $userReconciliationCommission != '') {
                            $reconciliationsAdjustement = ReconciliationsAdjustement::where('user_id', $payroll->user_id)->where('pid', $userReconciliationCommission->pid)->where('payroll_id', $payroll->id)->first();
                            if ($reconciliationsAdjustement) {
                                $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                                $reconciliationsAdjustement->start_date = $sales->m2_date;
                                $reconciliationsAdjustement->end_date = $sales->m2_date;
                                $reconciliationsAdjustement->commission_due = $userCommissinData->amount;
                                $reconciliationsAdjustement->pay_period_from = $payroll->pay_period_from;
                                $reconciliationsAdjustement->pay_period_to = $payroll->pay_period_to;
                                $reconciliationsAdjustement->payroll_move_status = 'from_payroll';
                                $reconciliationsAdjustement->save();
                            } else {
                                $create = [
                                    'pid' => $userReconciliationCommission->pid,
                                    'user_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'start_date' => $sales->m2_date,
                                    'end_date' => $sales->m2_date,
                                    'adjustment_type' => 'reconciliations',
                                    'reconciliation_id' => $userReconciliationCommission->id,
                                    'commission_due' => $userCommissinData->amount,
                                    'payroll_move_status' => 'from_payroll',
                                    'pay_period_from' => $payroll->pay_period_from,
                                    'pay_period_to' => $payroll->pay_period_to,
                                    'comment' => 'pending',
                                ];
                                $insert = ReconciliationsAdjustement::create($create);
                            }
                        } else {
                            $user = User::where('id', $payroll->user_id)->first();
                            if ($user->position_id == 3) {
                                $createdata = [
                                    'setter_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                                    'withhold_amount' => 0,
                                    'status' => 'unpaid',
                                    'payroll_to_recon_status' => '1',

                                ];
                            } elseif ($user->position_id == 2) {
                                $createdata = [
                                    'closer_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                                    'withhold_amount' => 0,
                                    'status' => 'unpaid',
                                    'payroll_to_recon_status' => '1',
                                ];
                            }
                            $insert = UserReconciliationWithholding::create($createdata);
                            $create = [
                                'user_id' => $payroll->user_id,
                                'adjustment_type' => 'reconciliations',
                                'payroll_id' => $payroll->id,
                                'start_date' => $date,
                                'end_date' => $date,
                                'pid' => $insert->pid,
                                'reconciliation_id' => $insert->id,
                                'commission_due' => $userCommissinData->amount,
                                'pay_period_from' => $payroll->pay_period_from,
                                'pay_period_to' => $payroll->pay_period_to,
                                'comment' => 'pending',
                                'payroll_move_status' => 'from_payroll',
                            ];
                            $insert = ReconciliationsAdjustement::create($create);
                        }
                    }
                }
                $userOverrideDatas = UserOverrides::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->where('pay_period_from', $payroll->pay_period_from)->where('pay_period_to', $payroll->pay_period_to)->where('status', '!=', 3)->get();
                if (isset($userOverrideDatas) && count($userOverrideDatas) > 0) {
                    foreach ($userOverrideDatas as $userOverrideData) {
                        $saleOver = SalesMaster::where('pid', $userOverrideData->pid)->first();
                        $userReconciliationOverride = UserReconciliationWithholding::where('closer_id', $data->user_id)->where('payroll_id', $payroll->id)->where('pid', $userOverrideData->pid)->where('finalize_status', 0)->orWhere('setter_id', $data->user_id)->where('pid', $userOverrideData->pid)->where('finalize_status', 0)->where('payroll_id', $payroll->id)->first();
                        if (isset($userReconciliationOverride) && $userReconciliationOverride != '') {
                            $reconciliationsAdjustement = ReconciliationsAdjustement::where('user_id', $payroll->user_id)->where('pid', $userOverrideData->pid)->where('payroll_move_status', 'from_payroll')->where('payroll_id', $payroll->id)->first();
                            if ($reconciliationsAdjustement) {
                                $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                                $reconciliationsAdjustement->start_date = $saleOver->m2_date;
                                $reconciliationsAdjustement->end_date = $saleOver->m2_date;
                                $reconciliationsAdjustement->overrides_due = isset($userOverrideData->amount) ? $userOverrideData->amount : 0;
                                $reconciliationsAdjustement->payroll_move_status = 'from_payroll';
                                $reconciliationsAdjustement->pay_period_from = $payroll->pay_period_from;
                                $reconciliationsAdjustement->pay_period_to = $payroll->pay_period_to;
                                $reconciliationsAdjustement->save();
                            } else {
                                $create = [
                                    'user_id' => $payroll->user_id,
                                    'adjustment_type' => 'reconciliations',
                                    'payroll_id' => $payroll->id,
                                    'start_date' => $saleOver->m2_date,
                                    'end_date' => $saleOver->m2_date,
                                    'pid' => $insert->pid,
                                    'reconciliation_id' => $insert->id,
                                    'overrides_due' => isset($userOverrideData->amount) ? $userOverrideData->amount : 0,
                                    'payroll_move_status' => 'from_payroll',
                                    'pay_period_from' => $payroll->pay_period_from,
                                    'pay_period_to' => $payroll->pay_period_to,
                                    'comment' => 'pending',
                                    'payroll_move_status' => 'from_payroll',
                                ];
                                $insert = ReconciliationsAdjustement::create($create);
                            }
                        }

                        $user = User::where('id', $payroll->user_id)->first();
                        if ($user->position_id == 3) {
                            $createdata = [
                                'setter_id' => $payroll->user_id,
                                'payroll_id' => $payroll->id,
                                'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                                'withhold_amount' => 0,
                                'status' => 'unpaid',
                                'payroll_to_recon_status' => '1',

                            ];
                        } elseif ($user->position_id == 2) {
                            $createdata = [
                                'closer_id' => $payroll->user_id,
                                'payroll_id' => $payroll->id,

                                'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                                'withhold_amount' => 0,
                                'status' => 'unpaid',
                                'payroll_to_recon_status' => '1',
                            ];
                        }
                        $insert = UserReconciliationWithholding::create($createdata);
                        $create = [
                            'user_id' => $payroll->user_id,
                            'adjustment_type' => 'reconciliations',
                            'payroll_id' => $payroll->id,
                            'start_date' => $saleOver->m2_date,
                            'end_date' => $saleOver->m2_date,
                            'pid' => $insert->pid,
                            'reconciliation_id' => $insert->id,
                            'overrides_due' => isset($userOverrideData->amount) ? $userOverrideData->amount : 0,
                            'pay_period_from' => $payroll->pay_period_from,
                            'pay_period_to' => $payroll->pay_period_to,
                            'comment' => 'pending',
                            'payroll_move_status' => 'from_payroll',
                        ];
                        $insert = ReconciliationsAdjustement::create($create);
                    }
                }

                $userReconciliationCommission = UserReconciliationWithholding::where('closer_id', $data->user_id)->where('payroll_to_recon_status', 1)->where('finalize_status', 0)->orWhere('setter_id', $data->user_id)->where('payroll_to_recon_status', 1)->where('finalize_status', 0)->first();
                if (isset($userReconciliationCommission) && $userReconciliationCommission != '') {
                    $reconciliationsAdjustement = ReconciliationsAdjustement::where('user_id', $payroll->user_id)->where('adjustment_type', 'reconciliations')->where('payroll_move_status', 'from_payroll')->where('payroll_id', $payroll->id)->first();
                    if ($reconciliationsAdjustement) {
                        $sales = SalesMaster::where('pid', $reconciliationsAdjustement->pid)->first();
                        $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                        $reconciliationsAdjustement->start_date = $sales->m2_date;
                        $reconciliationsAdjustement->end_date = $sales->m2_date;
                        $reconciliationsAdjustement->pay_period_from = $payroll->pay_period_from;
                        $reconciliationsAdjustement->pay_period_to = $payroll->pay_period_to;
                        $reconciliationsAdjustement->reimbursement = $payroll->reimbursement;
                        $reconciliationsAdjustement->deduction = $payroll->deduction;
                        $reconciliationsAdjustement->adjustment = $payroll->adjustment;
                        $reconciliationsAdjustement->reconciliation = $payroll->reconciliation;
                        $reconciliationsAdjustement->payroll_move_status = 'from_payroll';
                        $reconciliationsAdjustement->save();
                    } else {

                        $sales = SalesMaster::where('pid', $userReconciliationCommission->pid)->first();
                        $create = [
                            'pid' => $userReconciliationCommission->pid,
                            'user_id' => $payroll->user_id,
                            'payroll_id' => $payroll->id,
                            'start_date' => $sales->m2_date,
                            'end_date' => $sales->m2_date,
                            'adjustment_type' => 'reconciliations',
                            'reimbursement' => $payroll->reimbursement,
                            'deduction' => $payroll->deduction,
                            'adjustment' => $payroll->adjustment,
                            'reconciliation' => $payroll->reconciliation,
                            'pay_period_from' => $payroll->pay_period_from,
                            'pay_period_to' => $payroll->pay_period_to,
                            'payroll_move_status' => 'from_payroll',
                            'comment' => 'pending',
                        ];
                        $insert = ReconciliationsAdjustement::create($create);
                    }
                } else {
                    $user = User::where('id', $payroll->user_id)->first();
                    if ($user->position_id == 3) {
                        $createdata = [
                            'setter_id' => $payroll->user_id,
                            'payroll_id' => $payroll->id,
                            'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                            'withhold_amount' => 0,
                            'status' => 'unpaid',
                            'payroll_to_recon_status' => '1',

                        ];
                    } elseif ($user->position_id == 2) {
                        $createdata = [
                            'closer_id' => $payroll->user_id,
                            'payroll_id' => $payroll->id,
                            'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                            'withhold_amount' => 0,
                            'status' => 'unpaid',
                            'payroll_to_recon_status' => '1',
                        ];
                    }
                    $insert = UserReconciliationWithholding::create($createdata);
                    $sales = SalesMaster::where('pid', $userCommissinData->pid)->first();
                    $create = [
                        'user_id' => $payroll->user_id,
                        'payroll_id' => $payroll->id,
                        'adjustment_type' => 'reconciliations',
                        'start_date' => $sales->m2_date,
                        'end_date' => $sales->m2_date,
                        'pid' => $insert->pid,
                        'reconciliation_id' => $insert->id,
                        'reimbursement' => $payroll->reimbursement,
                        'deduction' => $payroll->deduction,
                        'adjustment' => $payroll->adjustment,
                        'reconciliation' => $payroll->reconciliation,
                        'pay_period_from' => $payroll->pay_period_from,
                        'pay_period_to' => $payroll->pay_period_to,
                        'comment' => 'pending',
                        'payroll_move_status' => 'from_payroll',
                    ];
                    $insert = ReconciliationsAdjustement::create($create);
                }
                $usercommission = UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                $overrides = UserOverrides::where(['user_id' => $data->user_id, 'overrides_settlement_type' => 'reconciliation', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                // $payrollDelete = Payroll::where(['id' => $data->id])->delete();
            });

            return response()->json([
                'ApiName' => 'Mark_As_Paid',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Mark_As_Paid',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

    }

    public function moveRunPayrollToReconciliations(Request $request): JsonResponse
    {
        $data = [];
        $payrollId = $request->payrollId;
        $date = date('Y-m-d');
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        // $data = Payroll::with('usersdata', 'positionDetail')->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date,'status'=>6])->get();
        $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
        if (count($data) > 0) {
            // $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
            $data->transform(function ($data) use ($date) {
                $payroll = Payroll::where(['id' => $data->id])->first();
                $userCommissinDatas = UserCommission::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->where('pay_period_from', $payroll->pay_period_from)->where('pay_period_to', $payroll->pay_period_to)->where('status', '!=', 3)->get();
                if (isset($userCommissinDatas) && count($userCommissinDatas) > 0) {
                    foreach ($userCommissinDatas as $userCommissinData) {
                        $sales = SalesMaster::where('pid', $userCommissinData->pid)->first();
                        $userReconciliationCommission = UserReconciliationWithholding::where('closer_id', $data->user_id)->where('payroll_id', $payroll->id)->where('pid', $userCommissinData->pid)->where('finalize_status', 0)->orWhere('setter_id', $data->user_id)->where('pid', $userCommissinData->pid)->where('finalize_status', 0)->where('payroll_id', $payroll->id)->first();
                        if (isset($userReconciliationCommission) && $userReconciliationCommission != '') {
                            $reconciliationsAdjustement = MoveToReconciliation::where('user_id', $payroll->user_id)->where('payroll_id', $payroll->id)->where('pid', $userReconciliationCommission->pid)->first();
                            if ($reconciliationsAdjustement) {
                                $commissionAmount = $reconciliationsAdjustement->commission + $userCommissinData->amount;
                                // $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                                $reconciliationsAdjustement->start_date = $sales->m2_date;
                                $reconciliationsAdjustement->end_date = $sales->m2_date;
                                $reconciliationsAdjustement->commission = $commissionAmount;
                                $reconciliationsAdjustement->clawback = $payroll->clawback;
                                $reconciliationsAdjustement->pay_period_from = $payroll->pay_period_from;
                                $reconciliationsAdjustement->pay_period_to = $payroll->pay_period_to;
                                // $reconciliationsAdjustement->payroll_move_status = 'from_payroll';
                                $reconciliationsAdjustement->save();
                            } else {
                                $create = [
                                    'pid' => $userReconciliationCommission->pid,
                                    'user_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'start_date' => $sales->m2_date,
                                    'end_date' => $sales->m2_date,
                                    // 'adjustment_type' => 'reconciliations',
                                    'reconciliation_id' => $userReconciliationCommission->id,
                                    'commission' => $userCommissinData->amount,
                                    'clawback' => $payroll->clawback,
                                    // 'payroll_move_status' => 'from_payroll',
                                    'pay_period_from' => $payroll->pay_period_from,
                                    'pay_period_to' => $payroll->pay_period_to,
                                    'comment' => 'pending',
                                ];
                                $insert = MoveToReconciliation::create($create);
                            }
                        } else {
                            $user = User::where('id', $payroll->user_id)->first();
                            if ($user->position_id == 3) {
                                $createdata = [
                                    'setter_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                                    'withhold_amount' => 0,
                                    'status' => 'unpaid',
                                    'payroll_to_recon_status' => '1',

                                ];
                            } elseif ($user->position_id == 2) {
                                $createdata = [
                                    'closer_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                                    'withhold_amount' => 0,
                                    'status' => 'unpaid',
                                    'payroll_to_recon_status' => '1',
                                ];
                            }
                            $insert = UserReconciliationWithholding::create($createdata);
                            $create = [
                                'user_id' => $payroll->user_id,
                                'adjustment_type' => 'reconciliations',
                                'payroll_id' => $payroll->id,
                                'start_date' => $date,
                                'end_date' => $date,
                                'pid' => $insert->pid,
                                'reconciliation_id' => $insert->id,
                                'commission' => $userCommissinData->amount,
                                'clawback' => $payroll->clawback,
                                'pay_period_from' => $payroll->pay_period_from,
                                'pay_period_to' => $payroll->pay_period_to,
                                'comment' => 'pending',
                                'payroll_move_status' => 'from_payroll',
                            ];
                            $insert = MoveToReconciliation::create($create);
                        }
                    }
                }
                $userOverrideDatas = UserOverrides::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->where('pay_period_from', $payroll->pay_period_from)->where('pay_period_to', $payroll->pay_period_to)->where('status', '!=', 3)->get();
                if (isset($userOverrideDatas) && count($userOverrideDatas) > 0) {
                    foreach ($userOverrideDatas as $userOverrideData) {
                        $saleOver = SalesMaster::where('pid', $userOverrideData->pid)->first();
                        $userReconciliationOverride = UserReconciliationWithholding::where('closer_id', $data->user_id)->where('payroll_id', $payroll->id)->where('pid', $userOverrideData->pid)->where('finalize_status', 0)->orWhere('setter_id', $data->user_id)->where('pid', $userOverrideData->pid)->where('finalize_status', 0)->where('payroll_id', $payroll->id)->first();
                        if (isset($userReconciliationOverride) && $userReconciliationOverride != '') {
                            $reconciliationsAdjustement = MoveToReconciliation::where('user_id', $payroll->user_id)->where('payroll_id', $payroll->id)->where('pid', $userReconciliationOverride->pid)->first();
                            if ($reconciliationsAdjustement) {

                                $overrideAmount = $reconciliationsAdjustement->override + $userOverrideData->amount;
                                // $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                                $reconciliationsAdjustement->start_date = $saleOver->m2_date;
                                $reconciliationsAdjustement->end_date = $saleOver->m2_date;
                                $reconciliationsAdjustement->override = isset($overrideAmount) ? $overrideAmount : 0;
                                // $reconciliationsAdjustement->payroll_move_status = 'from_payroll';
                                $reconciliationsAdjustement->pay_period_from = $payroll->pay_period_from;
                                $reconciliationsAdjustement->pay_period_to = $payroll->pay_period_to;
                                $reconciliationsAdjustement->save();
                            } else {

                                $create = [
                                    'user_id' => $payroll->user_id,
                                    // 'adjustment_type' => 'reconciliations',
                                    'payroll_id' => $payroll->id,
                                    'start_date' => $saleOver->m2_date,
                                    'end_date' => $saleOver->m2_date,
                                    'pid' => $saleOver->pid,
                                    'reconciliation_id' => $userReconciliationOverride->id,
                                    'override' => isset($userOverrideData->amount) ? $userOverrideData->amount : 0,
                                    // 'payroll_move_status' => 'from_payroll',
                                    'pay_period_from' => $payroll->pay_period_from,
                                    'pay_period_to' => $payroll->pay_period_to,
                                    'comment' => 'pending',
                                    // 'payroll_move_status' => 'from_payroll',
                                ];
                                $insert = MoveToReconciliation::create($create);
                            }
                        } else {
                            $user = User::where('id', $payroll->user_id)->first();
                            if ($user->position_id == 3) {
                                $createdata = [
                                    'setter_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'pid' => isset($userOverrideData->pid) ? $userOverrideData->pid : null,
                                    'withhold_amount' => 0,
                                    'status' => 'unpaid',
                                    'payroll_to_recon_status' => '1',

                                ];
                            } elseif ($user->position_id == 2) {
                                $createdata = [
                                    'closer_id' => $payroll->user_id,
                                    'payroll_id' => $payroll->id,
                                    'pid' => isset($userOverrideData->pid) ? $userOverrideData->pid : null,
                                    'withhold_amount' => 0,
                                    'status' => 'unpaid',
                                    'payroll_to_recon_status' => '1',
                                ];
                            }
                            $insert = UserReconciliationWithholding::create($createdata);
                            $create = [
                                'user_id' => $payroll->user_id,
                                // 'adjustment_type' => 'reconciliations',
                                'payroll_id' => $payroll->id,
                                'start_date' => $saleOver->m2_date,
                                'end_date' => $saleOver->m2_date,
                                'pid' => $insert->pid,
                                // 'reconciliation_id' => $insert->id,
                                'override' => isset($userOverrideData->amount) ? $userOverrideData->amount : 0,
                                'pay_period_from' => $payroll->pay_period_from,
                                'pay_period_to' => $payroll->pay_period_to,
                                // 'comment' => 'pending',
                                // 'payroll_move_status' => 'from_payroll',
                            ];
                            $inserts = MoveToReconciliation::create($create);
                        }
                    }
                }
                // adjustment -------------------
                $reconciliationsAdjustement = ReconciliationsAdjustement::where('user_id', $payroll->user_id)->where('adjustment_type', 'reconciliations')->where('payroll_move_status', 'from_payroll')->where('payroll_id', $payroll->id)->first();

                if (isset($reconciliationsAdjustement) && $reconciliationsAdjustement != '') {
                    $adjustmentDetail = PayrollAdjustmentDetail::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->first();
                    $sales = SalesMaster::where('pid', $adjustmentDetail->pid)->first();
                    $adjustment = PayrollAdjustment::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->first();
                    $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                    $reconciliationsAdjustement->start_date = $sales->m2_date;
                    $reconciliationsAdjustement->end_date = $sales->m2_date;
                    $reconciliationsAdjustement->pay_period_from = $payroll->pay_period_from;
                    $reconciliationsAdjustement->pay_period_to = $payroll->pay_period_to;
                    $reconciliationsAdjustement->commission_due = $adjustment->commission_amount;
                    $reconciliationsAdjustement->overrides_due = $adjustment->overrides_amount;
                    $reconciliationsAdjustement->reimbursement = $payroll->reimbursement;
                    $reconciliationsAdjustement->deduction = $payroll->deduction;
                    $reconciliationsAdjustement->adjustment = $payroll->adjustment;
                    $reconciliationsAdjustement->reconciliation = $payroll->reconciliation;
                    $reconciliationsAdjustement->payroll_move_status = 'from_payroll';
                    $reconciliationsAdjustement->save();
                } else {

                    $adjustmentPayroll = PayrollAdjustmentDetail::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->first();
                    if ($adjustmentPayroll) {

                        $sales = SalesMaster::where('pid', $adjustmentPayroll->pid)->first();

                        $create = [
                            'pid' => $sales->pid,
                            'user_id' => $payroll->user_id,
                            'payroll_id' => $payroll->id,
                            'start_date' => $sales->m2_date,
                            'end_date' => $sales->m2_date,
                            'adjustment_type' => 'reconciliations',
                            'commission_due' => $adjustmentPayroll->commission_amount,
                            'overrides_due' => $adjustmentPayroll->overrides_amount,
                            'reimbursement' => $payroll->reimbursement,
                            'deduction' => $payroll->deduction,
                            'adjustment' => $payroll->adjustment,
                            'reconciliation' => $payroll->reconciliation,
                            'pay_period_from' => $payroll->pay_period_from,
                            'pay_period_to' => $payroll->pay_period_to,
                            'payroll_move_status' => 'from_payroll',
                            'comment' => 'pending',
                        ];
                        $insert = ReconciliationsAdjustement::create($create);
                    }
                }

                $usercommission = UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                $overrides = UserOverrides::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                // $payrollDelete = Payroll::where(['id' => $data->id])->delete();
            });

            return response()->json([
                'ApiName' => 'Mark_As_Paid',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Mark_As_Paid',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }
    }

    public function payrollsReconciliationRollback(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $data = [];
        $payrollId = $request->payroll_id;
        $payroll = Payroll::where(['id' => $payrollId, 'status' => 6])->first();

        if ($payroll) {
            $userReconciliationCommission = UserReconciliationWithholding::where('payroll_id', $payrollId)->first();
            if ($userReconciliationCommission) {
                $userId = isset($payroll->user_id) ? $payroll->user_id : 0;
                $reconciliationsAdjustement = ReconciliationsAdjustement::where(['adjustment_type' => 'reconciliations', 'user_id' => $userId, 'payroll_move_status' => 'from_payroll', 'payroll_id' => $payrollId])->first();
                if ($reconciliationsAdjustement) {
                    // return $reconciliationsAdjustement->id;
                    $delete = ReconciliationsAdjustement::where(['adjustment_type' => 'reconciliations', 'user_id' => $userId, 'payroll_move_status' => 'from_payroll', 'payroll_id' => $payrollId])->delete();
                    // $reconciliationsAdjustement->commission_due = 0;
                    // $reconciliationsAdjustement->save();
                }
                UserReconciliationWithholding::where('id', $userReconciliationCommission->id)->delete();
            }
            UserCommission::where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->update(['status' => 1]);
            UserOverrides::where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->update(['status' => 1]);
            Payroll::where(['id' => $payroll->id])->update(['status' => 1]);
        }

        return response()->json([
            'ApiName' => 'payroll_reconciliation_rollback',
            'status' => true,
            'message' => 'Successfully.',

            // 'data' => $data,
        ], 200);

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
        $finalizeCount = $request->sent_id;
        $userId = $id;
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

    public function userCommissionViaPID(Request $request)
    {
        $apiName = 'Employee Commission Via pid';

        $valid = validator($request->all(), [
            'pid' => 'required',
            'user_id' => 'required',
        ]);

        if ($valid->fails()) {
            return response()->json([
                'ApiName' => $apiName,
                'status' => false,
                'message' => $valid->errors()->first(),
            ]);
        }

        $sale = SalesMaster::query()->select('id', 'pid', 'customer_name')->where('pid', $request->pid)->first();

        if (! $sale) {
            return response()->json([
                'ApiName' => $apiName,
                'status' => false,
                'message' => 'Given PID does not exist in our record',
            ]);
        }
        $commissions = UserCommission::query()
            ->with('userdata', 'saledata')
            ->where(['pid' => $request->pid, 'user_id' => $request->user_id])
            ->get();

        $paid_total = 0;
        $due_total = 0;

        $array = [];

        foreach ($commissions as $coms) {
            $paid_amount = $coms->is_mark_paid == 1 ? $coms->amount : 0;
            $due_amount = $coms->is_mark_paid == 1 ? 0 : $coms->amount;

            $paid_total += $paid_amount;
            $due_total += $due_amount;

            $type = $coms->amount_type == 'm1' ? 'M1 Payment' : 'M2 Payment';

            $array[] = [
                'name' => @$coms->userdata->first_name.' '.$coms->userdata->last_name ?: '',
                'date' => $this->setDate('m/d/Y', $coms->date),
                'description' => $type,
                'pay_period' => $this->setDate('m/d/Y', $coms->pay_period_from).' - '.$this->setDate('m/d/Y', $coms->pay_period_to),
                'paid_amount' => $paid_amount ? round($paid_amount, 2) : 0,
                'due_amount' => $due_amount ? round($due_amount, 2) : 0,
            ];
        }

        return [
            'ApiName' => $apiName,
            'status' => true,
            'customer_name' => @$sale->customer_name ?: '',
            'data' => $array,
            'sub_total' => [
                'paid_total' => round($paid_total, 2),
                'due_total' => round($due_total, 2),
            ],
            'total' => round($paid_total + $due_total, 2),
        ];
    }

    public function userCommissionViaPIDInReport(Request $request)
    {
        $apiName = 'Employee Commission Via pid Report';

        $valid = validator($request->all(), [
            'pid' => 'required',
            'user_id' => 'required',
        ]);
        if ($valid->fails()) {
            return response()->json([
                'ApiName' => $apiName,
                'status' => false,
                'message' => $valid->errors()->first(),
            ]);
        }
        $sale = SalesMaster::query()->select('id', 'pid', 'customer_name')->where('pid', $request->pid)->first();
        if (! $sale) {
            return response()->json([
                'ApiName' => $apiName,
                'status' => false,
                'message' => 'Given PID does not exist in our record',
            ]);
        }
        $commissions = UserCommission::query()
            ->with('userdata', 'saledata')
            ->where(['pid' => $request->pid, 'user_id' => $request->user_id])
            ->get();

        $paid_total = 0;
        $due_total = 0;

        $array = [];

        foreach ($commissions as $coms) {
            $paid_amount = $coms->is_mark_paid == 1 ? $coms->amount : 0;
            $due_amount = $coms->is_mark_paid == 1 ? 0 : $coms->amount;

            $paid_total += $paid_amount;
            $due_total += $due_amount;

            $type = $coms->amount_type == 'm1' ? 'M1 Payment' : 'M2 Payment';

            $array[] = [
                'name' => @$coms->userdata->first_name.' '.$coms->userdata->last_name ?: '',
                'date' => $this->setDate('m/d/Y', $coms->date),
                'description' => $type,
                'pay_period' => $this->setDate('m/d/Y', $coms->pay_period_from).' - '.$this->setDate('m/d/Y', $coms->pay_period_to),
                'paid_amount' => $paid_amount ? round($paid_amount, 2) : 0,
                'due_amount' => $due_amount ? round($due_amount, 2) : 0,
            ];
        }

        return [
            'ApiName' => $apiName,
            'status' => true,
            'customer_name' => @$sale->customer_name ?: '',
            'data' => $array,
            'sub_total' => [
                'paid_total' => round($paid_total, 2),
                'due_total' => round($due_total, 2),
            ],
            'total' => round($paid_total + $due_total, 2),
        ];
    }

    protected function setDate($format = 'Y-m-d', $date = '')
    {
        $now = now();

        if ($date) {
            $now = $now->parse($date);
        }

        return $now->format($format);
    }

    public function paginates($items, $perPage = null, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }
}
