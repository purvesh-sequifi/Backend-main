<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\MoveToReconHistory;
use App\Models\Payroll;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollCommon;
use App\Models\PayrollDeductions;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollOvertime;
use App\Models\PositionReconciliations;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class PayrollMoveToReconController extends Controller
{
    /**
     * Method moveToRecon: This function is used for move to recon from payroll commission tab.
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
    public function moveToRecon(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'is_recon_move' => 'required|boolean',
                'user_id' => 'required',
                'id' => 'required|array',
                'pid' => 'required|array',
                'select_type' => 'required|integer|in:1,2,3,5,7,8,9', // commission - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconciliation - 6, clawback - 7
                'pay_period_to' => 'required|date',
                'pay_period_from' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Check if payroll is already finalized
            if (Payroll::where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'status' => '2'])->exists()) {
                return response()->json([
                    'ApiName' => 'Payroll Commission Move To Reconciliation',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }
            $moveToReconStatus = $request->is_recon_move;
            $jsonData = $request->json()->all();
            $payrollIds = $request->pid;
            $payPeriod = [
                'payPeriodFrom' => $request->pay_period_from,
                'payPeriodTo' => $request->pay_period_to,
                'userId' => $request->user_id,
            ];
            $selectType = $request->select_type;
            $data = $jsonData['id'];
            $this->handleSelectionType($selectType, $data, $moveToReconStatus, $request->adjustment, $payPeriod, $payrollIds);

            $response = response()->json([
                'ApiName' => 'Commission Move To Recon',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        } catch (Exception $ex) {
            DB::rollback();
            Log::channel('reconLog')->info('!! Error !!: '.$ex->getMessage().'. At Line No '.$ex->getLine().' in this file '.$ex->getFile());
            $response = response()->json([
                'ApiName' => 'Commission Move To Recon',
                'status' => false,
                'message' => "'Failed.'",
            ], 400);
        }

        return $response;
    }

    private function moveToReconActivity($payrollData, $payrollId, $reconId, $type, $reconFlag)
    {
        foreach ($reconId as $value) {
            $checkHistory = MoveToReconHistory::where([
                'user_id' => $payrollData['userId'],
                'pay_period_from' => $payrollData['payPeriodFrom'],
                'pay_period_to' => $payrollData['payPeriodTo'],
                'type' => $type,
                'type_id' => $value,
            ])->exists();
            if ($reconFlag == 0) {
                $checkHistory = MoveToReconHistory::where([
                    'user_id' => $payrollData['userId'],
                    'pay_period_from' => $payrollData['payPeriodFrom'],
                    'pay_period_to' => $payrollData['payPeriodTo'],
                    'type' => $type,
                    'type_id' => $value,
                ])->delete();
            }
            if (! $checkHistory) {
                MoveToReconHistory::create([
                    'user_id' => $payrollData['userId'],
                    'pay_period_from' => $payrollData['payPeriodFrom'],
                    'pay_period_to' => $payrollData['payPeriodTo'],
                    'type' => $type,
                    'type_id' => $value,
                    'pid' => $payrollId[0],
                ]);
            }
        }
    }

    private function handleSelectionType($selectType, $recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId = null)
    {
        switch ($selectType) {
            case 1:
                $this->handleCommission($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId);
                $type = 'commission';
                break;
            case 2:
                $this->handleOverride($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId);
                $type = 'overrides';
                break;
            case 3:
                $this->handleAdjustment($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId);
                $type = 'adjustments';
                break;
            case 5:
                $this->handleDeduction($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId);
                $type = 'deductions';
                break;
            case 7:
                $this->handleClawback($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds);
                $type = 'clawback';
                break;
            case 8:
                $this->handleHourlySalary($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId);
                $type = 'hourlysalary';
                break;
            case 9:
                $this->handleOvertime($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId);
                $type = 'overtime';
                break;
            default:
                throw new Exception('Invalid select type.');
        }
    }

    private function handleCommission($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId)
    {
        if (isset($recordId['commission']) && ! empty($recordId['commission'])) {
            UserCommission::whereIn('id', $recordId['commission'])->update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                'status' => $moveToReconStatus ? 6 : 1,
            ]);
            $this->updateReferences($recordId['commission'], 'commission', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['commission'], 'commission', $moveToReconStatus);
        }

        if (! empty($adjustmentId)) {
            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->delete()/* update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                "status" => $moveToReconStatus ? 6 : 1,
            ]) */;
            $this->updateReferences($adjustmentId, 'adjustment', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $adjustmentId, 'adjustments', $moveToReconStatus);
        }

        if ($recordId['clawback']) {
            ClawbackSettlement::whereIn('id', $recordId['clawback'])->update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                'status' => $moveToReconStatus ? 6 : 1,
            ]);
            $this->updateReferences($recordId['clawback'], 'clawback', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['clawback'], 'clawback', $moveToReconStatus);
        }
    }

    private function handleDeduction($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId)
    {
        // /* $this->updateReferences($recordId, 'commission', $moveToReconStatus); */
        if (isset($recordId['deduction']) && ! empty($recordId['deduction'])) {
            PayrollDeductions::whereIn('id', $recordId['deduction'])->update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                'status' => $moveToReconStatus ? 6 : 1,
            ]);
            $this->updateReferences($recordId['deduction'], 'deduction', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['deduction'], 'deduction', $moveToReconStatus);
        }

        if (! empty($adjustmentId)) {
            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)
                ->delete()/* update([
                    'is_mark_paid' => 0,
                    'is_move_to_recon' => $moveToReconStatus,
                    "status" => $moveToReconStatus ? 6 : 1,
                ]) */;
            $this->updateReferences($adjustmentId, 'adjustment', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $adjustmentId, 'adjustments', $moveToReconStatus);
        }
    }

    private function handleClawback($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds)
    {
        if ((isset($recordId['clawback']) && ! empty($recordId['clawback'])) || (isset($recordId['commission']) && ! empty($recordId['commission']))) {
            $clawbackId = ! empty($recordId['clawback']) ? $recordId['clawback'] : $recordId['commission'];
            ClawbackSettlement::whereIn('id', $clawbackId)
                ->where([
                    // "clawback_type" => "reconciliation",
                    'user_id' => $payPeriod['userId'],
                    // "type" => "commission",
                ])
                ->whereIn('type', ['recon-commission', 'commission'])
                ->update([
                    'is_mark_paid' => 0,
                    'is_move_to_recon' => $moveToReconStatus,
                    'status' => $moveToReconStatus ? 6 : 1,
                ]);
            $this->updateReferences($clawbackId, 'clawback', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $clawbackId, 'clawback', $moveToReconStatus);
        } elseif ((isset($recordId['clawback']) && ! empty($recordId['clawback'])) || (isset($recordId['overrides']) && ! empty($recordId['overrides']))) {
            $clawbackId = ! empty($recordId['clawback']) ? $recordId['clawback'] : $recordId['overrides'];
            ClawbackSettlement::whereIn('id', $clawbackId)
                ->where([
                    // "clawback_type" => "reconciliation",
                    'user_id' => $payPeriod['userId'],
                    // "type" => "overrides",
                ])
                ->whereIn('type', ['recon-override', 'overrides'])
                ->update([
                    'is_mark_paid' => 0,
                    'is_move_to_recon' => $moveToReconStatus,
                    'status' => $moveToReconStatus ? 6 : 1,
                ]);
            $this->updateReferences($clawbackId, 'clawback', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $clawbackId, 'clawback', $moveToReconStatus);
        }
    }

    private function handleOverride($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId)
    {
        if (isset($recordId['overrides']) && ! empty($recordId['overrides'])) {
            UserOverrides::whereIn('id', $recordId['overrides'])
                ->update([
                    'is_mark_paid' => 0,
                    'is_move_to_recon' => $moveToReconStatus,
                    'status' => $moveToReconStatus ? 6 : 1,
                ]);
            $this->updateReferences($recordId['overrides'], 'override', $moveToReconStatus);

            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['overrides'], 'override', $moveToReconStatus);
        }

        if (! empty($adjustmentId)) {
            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)
                ->delete()/* update([
                    'is_mark_paid' => 0,
                    'is_move_to_recon' => $moveToReconStatus,
                    "status" => $moveToReconStatus ? 6 : 1,
                ]) */;
            $this->updateReferences($adjustmentId, 'adjustment', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $adjustmentId, 'adjustments', $moveToReconStatus);
        }

        if (isset($recordId['clawback']) && ! empty($recordId['clawback'])) {
            ClawbackSettlement::whereIn('id', $recordId['clawback'])
                ->update([
                    'is_mark_paid' => 0,
                    'is_move_to_recon' => $moveToReconStatus,
                    'status' => $moveToReconStatus ? 6 : 1,
                ]);
            $this->updateReferences($recordId['clawback'], 'clawback', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['clawback'], 'clawback', $moveToReconStatus);
        }
    }

    private function handleAdjustment($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId)
    {
        if (! empty($adjustmentId)) {
            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => 0, 'is_next_payroll' => $moveToReconStatus]);
            $this->updatePayrollAdjustment($payrollIds, $payPeriod, 0, $moveToReconStatus);
            $this->updateReferences($adjustmentId, 'adjustment', $moveToReconStatus);
        }

        ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->update(['is_mark_paid' => 0, 'is_next_payroll' => $moveToReconStatus]);
        $this->updateReferences($recordId, 'approvalreject', $moveToReconStatus);

        if ($clawbackId) {
            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => 0, 'is_next_payroll' => $moveToReconStatus]);
            $this->updateReferences($clawbackId, 'clawback', $moveToReconStatus);
        }
    }

    private function handleReimbursement($recordId, $moveToReconStatus, $clawbackId)
    {
        ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->update(['is_mark_paid' => 0, 'is_next_payroll' => $moveToReconStatus]);
        $this->updateReferences($recordId, 'approvalreject', $moveToReconStatus);

        if ($clawbackId) {
            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => 0, 'is_next_payroll' => $moveToReconStatus]);
            $this->updateReferences($clawbackId, 'clawback', $moveToReconStatus);
        }
    }

    private function handleReconciliation($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds)
    {
        if (! empty($adjustmentId)) {
            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => 0, 'is_next_payroll' => $moveToReconStatus]);
            $this->updatePayrollAdjustment($payrollIds, $userId, $payPeriodFrom, $payPeriodTo, 0, $moveToReconStatus);
            $this->updateReferences($adjustmentId, 'adjustment', $moveToReconStatus);
        }

        PayrollAdjustments::whereIn('id', $recordId)->update(['is_mark_paid' => 0, 'is_next_payroll' => $moveToReconStatus]);
        $this->updateReferences($recordId, 'reconciliation', $moveToReconStatus);
    }

    private function handleHourlySalary($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId)
    {
        if (isset($recordId['hourlysalary']) && ! empty($recordId['hourlysalary'])) {
            PayrollHourlySalary::whereIn('id', $recordId['hourlysalary'])->update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                'status' => $moveToReconStatus ? 6 : 1,
            ]);
            $this->updateReferences($recordId['hourlysalary'], 'hourlysalary', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['hourlysalary'], 'hourlysalary', $moveToReconStatus);
        }

        if (! empty($adjustmentId)) {
            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->delete()/* update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                "status" => $moveToReconStatus ? 6 : 1,
            ]) */;
            $this->updateReferences($adjustmentId, 'adjustment', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $adjustmentId, 'adjustments', $moveToReconStatus);
        }

        if ($recordId['clawback']) {
            ClawbackSettlement::whereIn('id', $recordId['clawback'])->update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                'status' => $moveToReconStatus ? 6 : 1,
            ]);
            $this->updateReferences($recordId['clawback'], 'clawback', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['clawback'], 'clawback', $moveToReconStatus);
        }
    }

    private function handleOvertime($recordId, $moveToReconStatus, $adjustmentId, $payPeriod, $payrollIds, $clawbackId)
    {
        if (isset($recordId['overtime']) && ! empty($recordId['overtime'])) {
            PayrollOvertime::whereIn('id', $recordId['overtime'])->update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                'status' => $moveToReconStatus ? 6 : 1,
            ]);
            $this->updateReferences($recordId['overtime'], 'overtime', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['overtime'], 'overtime', $moveToReconStatus);
        }

        if (! empty($adjustmentId)) {
            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->delete()/* update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                "status" => $moveToReconStatus ? 6 : 1,
            ]) */;
            $this->updateReferences($adjustmentId, 'adjustment', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $adjustmentId, 'adjustments', $moveToReconStatus);
        }

        if ($recordId['clawback']) {
            ClawbackSettlement::whereIn('id', $recordId['clawback'])->update([
                'is_mark_paid' => 0,
                'is_move_to_recon' => $moveToReconStatus,
                'status' => $moveToReconStatus ? 6 : 1,
            ]);
            $this->updateReferences($recordId['clawback'], 'clawback', $moveToReconStatus);
            $this->moveToReconActivity($payPeriod, $payrollIds, $recordId['clawback'], 'clawback', $moveToReconStatus);
        }
    }

    private function updateReferences($recordId, $type, $moveToReconStatus)
    {
        foreach ($recordId as $check_ref_id) {
            $date = $moveToReconStatus ? date('Y-m-d') : '';
            switch ($type) {
                case 'commission':
                    $this->updateReferenceForModel(UserCommission::class, $check_ref_id, $date);
                    break;
                case 'override':
                    $this->updateReferenceForModel(UserOverrides::class, $check_ref_id, $date);
                    break;
                case 'deduction':
                    $this->updateReferenceForModel(PayrollDeductions::class, $check_ref_id, $date);
                    break;
                case 'clawback':
                    $this->updateReferenceForModel(ClawbackSettlement::class, $check_ref_id, $date);
                    break;
                case 'adjustment':
                    $this->updateReferenceForModel(PayrollAdjustmentDetail::class, $check_ref_id, $date);
                    break;
                case 'hourlysalary':
                    $this->updateReferenceForModel(PayrollHourlySalary::class, $check_ref_id, $date);
                    break;
                case 'overtime':
                    $this->updateReferenceForModel(PayrollOvertime::class, $check_ref_id, $date);
                    break;
                    /* case 'approvalreject':
                case 'approvalrejectreimbursement':
                $this->updateReferenceForModel(ApprovalsAndRequest::class, $check_ref_id, $date);
                break;

                case 'reconciliation':
                $this->updateReferenceForModel(ReconciliationFinalizeHistory::class, $check_ref_id, $date);
                break; */
                default:
                    throw new InvalidArgumentException("Unknown type: $type");
            }
        }
    }

    private function updateReferenceForModel($modelClass, $check_ref_id, $date)
    {
        $modelInstance = $modelClass::find($check_ref_id);
        if ($modelInstance && $modelInstance->ref_id == 0) {
            $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
            $modelClass::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
        } elseif ($modelInstance) {
            $pay_period = PayrollCommon::where('id', $modelInstance->ref_id)->first();
            if ($pay_period) {
                if (empty($pay_period->orig_payfrom)) {
                    PayrollCommon::where('id', $modelInstance->ref_id)->update(['payroll_modified_date' => $date]);
                }
            } else {
                $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                $modelClass::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
            }
        }
    }

    private function updateEveree($payrollId)
    {
        $jobId = ProcessQueue::createJob($payrollId, 'process', '0');
        ProcessQueue::updateStatus($jobId, '1');
    }

    private function checkPositionReconStatus($positionId)
    {
        return PositionReconciliations::where([
            'position_id' => $positionId,
            'status' => 1,
        ])->exists();
    }
}
