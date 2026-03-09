<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\PayFrequencyTrait;
use App\Http\Controllers\Controller;
use App\Models\AdditionalPayFrequency;
use App\Models\AdvancePaymentSetting;
use App\Models\ApprovalsAndRequest;
use App\Models\CompanyPayrolls;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\ConfigureTier;
use App\Models\Crms;
use App\Models\DomainSetting;
use App\Models\EmailLogin;
use App\Models\EmailNotificationSetting;
use App\Models\FrequencyType;
use App\Models\GroupPermissions;
use App\Models\MarginOfDifferences;
use App\Models\MarginSetting;
use App\Models\MarketingDealsReconciliations;
use App\Models\MarketingDealsSetting;
use App\Models\MonthlyPayFrequency;
use App\Models\Notification;
use App\Models\OverrideSettings;
use App\Models\OverridesType;
use App\Models\payFrequencySetting;
use App\Models\Payroll;
use App\Models\PositionPayFrequency;
use App\Models\Positions;
use App\Models\ReconciliationSchedule;
use App\Models\SchedulingApprovalSetting;
use App\Models\Settings;
use App\Models\TierDurationSettings;
use App\Models\TierLevelSetting;
use App\Models\TierSettings;
use App\Models\TiersSchema;
use App\Models\upfrontSystemSetting;
use App\Models\User;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use App\Models\UserTransferHistory;
use App\Models\WeeklyPayFrequency;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Log;
use Spatie\Activitylog\Models\Activity;

class SetupController extends Controller
{
    use EmailNotificationTrait;
    use PayFrequencyTrait;
    use PushNotificationTrait;
    // check all setup page active/inactive

    public function status(Request $request)
    {
        if ($request->type == 'reconciliation') {
            return app(\App\Http\Controllers\API\V1\ReconController::class)->reconCompanySettingStatus($request);
        }
        $Validator = Validator::make($request->all(),
            [
                'status' => 'required',
            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        if (! empty($request->type)) {
            if ($request->type == 'tier') {
                $count = TiersSchema::tierspositionexist();
                if ($count > 0) {
                    return response()->json([
                        'ApiName' => 'setup-active-inactive',
                        'status' => false,
                        'message' => 'Tiers are assigned to a position.',
                        'data' => [],
                    ], 404);
                }
            }
            // echo $request->type;die;
            $status = CompanySetting::where('type', $request->type)->first();
            if ($status) {
                $status->status = $request->status;
                $status->save();
                $page = 'Setting';
                $action = 'Company Setting Status Updated';
                $description = 'Type =>'.$request->type.', '.'Status =>'.$request->status;
                user_activity_log($page, $action, $description);

                return response()->json([
                    'ApiName' => 'setup-active-inactive',
                    'status' => true,
                    'message' => 'Status Updated Successfully.',
                    'data' => $status,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'setup-active-inactive',
                    'status' => false,
                    'message' => 'Type not exists.',
                    'data' => [],
                ], 404);
            }
        } else {
            // return response()->json(['Failed' => 'Type invalid.']);
            return response()->json([
                'ApiName' => 'setup-active-inactive',
                'status' => false,
                'message' => 'Type invalid',
                'data' => [],
            ], 404);
        }
    }

    public function AdvancePaymentSetting(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(),
            [
                'adwance_setting' => 'required',
            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        } else {
            $adwance_setting = AdvancePaymentSetting::find(1);
            if ($request->adwance_setting == 'automatic') {
                if ($adwance_setting->adwance_setting != $request->adwance_setting) {
                    $adwance_setting->adwance_setting = $request->adwance_setting;
                    $adwance_requests = ApprovalsAndRequest::with('user:id,sub_position_id,position_id')->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved')->get();
                    $adwance_requests->transform(function ($result) {
                        // $payroll = Payroll::where(['pay_period_from' => $result->pay_period_from,'pay_period_to' => $result->pay_period_to])->whereIn('finalize_status',[1,2])->count();
                        // if($payroll ==0){
                        ApprovalsAndRequest::where('id', $result->id)->update([
                            'payroll_id' => null,
                            'pay_period_from' => null,
                            'pay_period_to' => null,
                            'ref_id' => 0,
                            'is_next_payroll' => 0,
                            'is_mark_paid' => 0,
                            'status' => 'Accept',
                        ]);
                        $chiieldRequestAmmount = ApprovalsAndRequest::where('parent_id', $result->id)->sum('amount');
                        $reqAmmount = $result->amount - $chiieldRequestAmmount;
                        $result->amount = $reqAmmount;
                        if ($reqAmmount < 0) {
                            $newData = $result->toArray();
                            $newData['amount'] = $reqAmmount;
                            $newData['parent_id'] = $result->id;
                            $newData['status'] = 'Accept';
                            unset($newData['user']);
                            unset($newData['id']);
                            $partially_request = ApprovalsAndRequest::create($newData);
                            $start_date = date('Y-m-d');
                            // $payFrequency = $this->payFrequencyNew($start_date, $result->user->sub_position_id);
                            $payFrequency = $this->openPayFrequency($result->user->sub_position_id, $result->user->id);
                            $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                            $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                            $payroll_id = updateExistingPayroll($result->user_id, $startDateNext, $endDateNext, (($reqAmmount) * (-1)), 'adjustment', $result->user->position_id, 0);
                            ApprovalsAndRequest::where('id', $partially_request->id)->update([
                                'payroll_id' => $payroll_id,
                                'pay_period_from' => $startDateNext,
                                'pay_period_to' => $endDateNext,
                                'ref_id' => 0,
                                'is_next_payroll' => 0,
                                'is_mark_paid' => 0,
                                'status' => 'Accept',
                            ]);
                        }
                        // }
                    });
                }
            } elseif ($request->adwance_setting == 'manual') {
                if ($adwance_setting->adwance_setting != $request->adwance_setting) {
                    $adwance_setting->adwance_setting = $request->adwance_setting;
                    $adwance_requests = ApprovalsAndRequest::with('user:id,sub_position_id,position_id')->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Accept')->get();
                    $adwance_requests->transform(function ($result) {

                        $processing_payroll = Payroll::whereIn('finalize_status', [1, 2])->first();
                        if ($processing_payroll) {
                            $payroll_ids = Payroll::where('pay_period_from', $processing_payroll->pay_period_from)->where('pay_period_to', $processing_payroll->pay_period_to)->pluck('id')->toArray();
                        }
                        $query = ApprovalsAndRequest::where('parent_id', $result->id)
                            ->where('status', 'Accept');

                        $start_date = date('Y-m-d');
                        // $payFrequency = $this->payFrequencyNew($start_date, $result->user->sub_position_id);
                        $payFrequency = $this->openPayFrequency($result->user->sub_position_id, $result->user->id);
                        $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                        $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;

                        if (! empty($payroll_ids)) {
                            $query->whereNotIn('payroll_id', $payroll_ids);
                            ApprovalsAndRequest::whereNotIn('payroll_id', $payroll_ids)->where('id', $result->id)->update([
                                'payroll_id' => null,
                                'pay_period_from' => null,
                                'pay_period_to' => null,
                                'ref_id' => 0,
                                'is_next_payroll' => 0,
                                'is_mark_paid' => 0,
                                'status' => 'Approved',
                            ]);
                        } else {
                            ApprovalsAndRequest::where('id', $result->id)->update([
                                'payroll_id' => null,
                                'pay_period_from' => null,
                                'pay_period_to' => null,
                                'ref_id' => 0,
                                'is_next_payroll' => 0,
                                'is_mark_paid' => 0,
                                'status' => 'Approved',
                            ]);
                        }

                        $chiieldRequestAmmount = $query->sum('amount');
                        $reqAmmount = $result->amount - $chiieldRequestAmmount;
                        $result->amount = $reqAmmount;
                        $start_date = date('Y-m-d');
                        // $payFrequency = $this->payFrequencyNew($start_date, $result->user->sub_position_id);
                        $payFrequency = $this->openPayFrequency($result->user->sub_position_id, $result->user->id);

                        $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                        $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                        $payroll_id = updateExistingPayroll($result->user_id, $startDateNext, $endDateNext, ($reqAmmount), 'adjustment', $result->user->position_id, 0);
                        $deleteChiieldRequest = $query->delete();
                    });
                }
            } else {
                return response()->json([
                    'ApiName' => 'adwance-payment-setting',
                    'status' => false,
                    'message' => 'invalid adwance setting ',
                    'data' => [],
                ], 400);
            }
            $adwance_setting->save();
            $data = AdvancePaymentSetting::find(1);

            return response()->json([
                'ApiName' => 'adwance-payment-setting',
                'status' => true,
                'message' => 'update adwance setting successfully.',
                'data' => $data,
            ], 200);

        }
    }

    public function getAdvancePaymentSetting(Request $request): JsonResponse
    {

        $data = AdvancePaymentSetting::find(1);
        if ($data) {
            return response()->json([
                'ApiName' => 'adwance-payment-setting',
                'status' => true,
                'message' => 'Success.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'adwance-payment-setting',
                'status' => false,
                'message' => 'no data found.',
                'data' => [],
            ], 400);
        }
    }

    public function getCompanySettingList(Request $request): JsonResponse
    {
        $companySetting = CompanySetting::get();

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $companySetting], 200);
    }
    // backend with reconcililation Show
    // public function getReconciliationSchedule()
    // {
    //     $data = ReconciliationSchedule::get();
    //     $data->transform(function ($data) {
    //         return [
    //             'id' => $data->id,
    //             'period_from' => $data->period_from, //dateToYMD($data->period_from),
    //             'period_to' => $data->period_to, //dateToYMD($data->period_to),
    //             'day_date' => $data->day_date, //dateToYMD($data->day_date),
    //         ];
    //     });

    //     return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    // }

    public function getReconciliationSchedule(): JsonResponse
    {
        $result = [];
        $datas = ReconciliationSchedule::get();
        foreach ($datas as $data) {

            $recon_data = UserReconciliationCommission::selectRaw('count(`status`) as count,`status`,`period_from`,`period_to`,`period_to`')
                ->where('period_from', $data->period_from)
                ->where('period_to', $data->period_to)
                ->groupBy(['period_from', 'period_to', 'status'])
                ->orderBy('period_from', 'desc')->get()->toArray();
            if (! empty($recon_data)) {
                $status = [];
                foreach ($recon_data as $d) {
                    $result[] = [
                        'id' => $data->id,
                        'period_from' => $data->period_from, // dateToYMD($data->period_from),
                        'period_to' => $data->period_to, // dateToYMD($data->period_to),
                        'count' => $d['count'],
                        'status' => $d['status'],
                        // 'status' => $status
                    ];
                }

            } else {
                $result[] = [
                    'id' => $data->id,
                    'period_from' => $data->period_from, // dateToYMD($data->period_from),
                    'period_to' => $data->period_to, // dateToYMD($data->period_to),
                    'count' => 0,
                    'status' => null,
                ];
            }
        }

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $result], 200);
    }
    // backend + reconciliations Update
    // public function updateReconciliationSchedule(Request $request)
    // {

    //     $Validator = Validator::make(
    //         $request->all(),
    //         [
    //             'Schedule.*.period_from' => 'required',
    //             'Schedule.*.period_to' => 'required',
    //             // 'Schedule.*.day_date' => 'required',

    //         ]
    //     );

    //     if ($Validator->fails()) {
    //         return response()->json(['error' => $Validator->errors()], 200);
    //     }
    //     $UserReconciliationWithholding = UserReconciliationWithholding::first();

    //     $data = ReconciliationSchedule::get();
    //     //ReconciliationSchedule::truncate();

    //     foreach ($request->Schedule as $reconciliation) {

    //         $userReconciliationCommission = UserReconciliationCommission::where(['period_from'=> $reconciliation['period_from'],'period_to'=>$reconciliation['period_to']])->first();
    //         if(!$userReconciliationCommission){

    //             ReconciliationSchedule::where(['period_from'=> $reconciliation['period_from'],'period_to'=>$reconciliation['period_to']])->delete();

    //             return response()->json([
    //                 'ApiName' => 'backend-reconciliations-update',
    //                 'status' => true,
    //                 'message' => 'You can not change reconciliations pay period becouse of reconciliations have already data .',
    //             ], 200);
    //         }
    //         $ReconciliationSchedule = ReconciliationSchedule::where(['period_from'=> $reconciliation['period_from'],'period_to'=>$reconciliation['period_to']])->first();
    //         if(!$ReconciliationSchedule){

    //             ReconciliationSchedule::create(
    //                 [
    //                     'period_from'       => $reconciliation['period_from'],
    //                     'period_to'         => $reconciliation['period_to'],
    //                     // 'day_date'         => $reconciliation['day_date'],
    //                 ]
    //             );
    //         }

    //     }

    //     $check = ReconciliationSchedule::get();
    //     //$data = $check;
    //     $superAdmin=User::where('is_super_admin',1)->first();
    //     $data =  Notification::create([
    //         'user_id' => $superAdmin->id,
    //         'type' => 'Reconciliation',
    //         'description' => 'Updated reconciliations Data by ' . auth()->user()->first_name,
    //         'is_read' => 0,

    //     ]);

    //     $notificationData = array(
    //         'user_id'      => auth()->user()->id,
    //         'device_token' => $superAdmin->device_token,
    //         'title'        => 'Updated reconciliations Data.',
    //         'sound'        => 'sound',
    //         'type'         => 'Reconciliation',
    //         'body'         => 'Updated reconciliations Data by ' . auth()->user()->first_name,
    //     );
    //       $this->sendNotification($notificationData);

    //     return response()->json([
    //         'ApiName' => 'backend-reconciliations-update',
    //         'status' => true,
    //         'message' => 'changes Successfully.',
    //     ], 200);
    // }

    public function updateReconciliationSchedule(Request $request): JsonResponse
    {

        $Validator = Validator::make(
            $request->all(),
            [
                'Schedule.*.period_from' => 'required',
                'Schedule.*.period_to' => 'required',
                // 'Schedule.*.day_date' => 'required',

            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 200);
        }
        // $UserReconciliationWithholding = UserReconciliationWithholding::first();

        $data = ReconciliationSchedule::get();
        // //ReconciliationSchedule::truncate();

        foreach ($request->Schedule as $reconciliation) {

            // $userReconciliationCommission = UserReconciliationCommission::where(['period_from'=> $reconciliation['period_from'],'period_to'=>$reconciliation['period_to']])->first();
            // if(empty($userReconciliationCommission)){

            //     ReconciliationSchedule::where(['period_from'=> $reconciliation['period_from'],'period_to'=>$reconciliation['period_to']])->delete();

            //     // return response()->json([
            //     //     'ApiName' => 'backend-reconciliations-update',
            //     //     'status' => true,
            //     //     'message' => 'You can not change reconciliations pay period becouse of reconciliations have already data .',
            //     // ], 200);
            // }
            $ReconciliationSchedule = ReconciliationSchedule::where(['period_from' => $reconciliation['period_from'], 'period_to' => $reconciliation['period_to']])->first();
            if (empty($ReconciliationSchedule)) {

                ReconciliationSchedule::create(
                    [
                        'period_from' => $reconciliation['period_from'],
                        'period_to' => $reconciliation['period_to'],
                        // 'day_date'         => $reconciliation['day_date'],
                    ]
                );
            }

        }

        $superAdmin = User::where('is_super_admin', 1)->first();

        $notificationData = [
            'user_id' => auth()->user()->id,
            'device_token' => $superAdmin->device_token,
            'title' => 'Updated reconciliations Data.',
            'sound' => 'sound',
            'type' => 'Reconciliation',
            'body' => 'Updated reconciliations Data by '.auth()->user()->first_name,
        ];
        $this->sendNotification($notificationData);

        return response()->json([
            'ApiName' => 'backend-reconciliations-update',
            'status' => true,
            'message' => 'changes Successfully.',
        ], 200);
    }

    public function deleteReconciliationSchedule(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'period_from' => 'required',
                'period_to' => 'required',
                'id' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 200);
        }
        try {
            DB::beginTransaction();
            $count = UserReconciliationCommission::where(['period_from' => $request->period_from, 'period_to' => $request->period_to])->count();
            if ($count == 0) {
                $delete = ReconciliationSchedule::where(['id' => $request->id, 'period_from' => $request->period_from, 'period_to' => $request->period_to])->first();
                if (! empty($delete)) {
                    $deletes = ReconciliationSchedule::find($delete->id)->delete();
                }

            }
            DB::commit();

            return response()->json([
                'ApiName' => 'delete_Reconciliation_Schedule',
                'status' => true,
                'message' => 'deleted Successfully.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'delete_Reconciliation_Schedule',
                'status' => false,
                'message' => 'delete failed.',
            ], 400);
        }
    }

    // Show Overrides
    public function getoverrides(): JsonResponse
    {
        $data = OverrideSettings::first();
        $status = OverridesType::all();

        return response()->json([
            'setting_status' => $data->status,
            'ApiName' => 'overrides-show',
            'status' => true,
            'message' => 'Overrides',
            'data' => $status,
        ], 200);
    }

    public function getUpfrontSetting(): JsonResponse
    {
        $data = upfrontSystemSetting::first();

        return response()->json([
            'ApiName' => 'getUpfrontSetting',
            'status' => true,
            'message' => 'Upfront',
            'data' => $data,
        ], 200);
    }

    public function AddUpdateUpfrontSetting(Request $request): JsonResponse
    {

        $upfrontSystemSetting = upfrontSystemSetting::first();
        if ($upfrontSystemSetting) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'upfront_for_self_gen' => 'required',
                    'id' => 'required',
                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $upfrontSystemSettingData = upfrontSystemSetting::where('id', $request->id)->first();
            if ($upfrontSystemSettingData) {
                $upfrontSystemSettingData->upfront_for_self_gen = $request->upfront_for_self_gen;
                $upfrontSystemSettingData->save();
            } else {

                return response()->json([
                    'ApiName' => 'updateUpfrontSetting',
                    'status' => false,
                    'message' => 'Invalid Id',
                    'data' => null,
                ], 400);

            }

            return response()->json([
                'ApiName' => 'upfront',
                'status' => true,
                'message' => 'Updated Successfully.',
                'data' => $upfrontSystemSettingData,
            ], 200);

        } else {
            $upfrontSystemSetting = upfrontSystemSetting::first();
            if (empty($upfrontSystemSetting)) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        'upfront_for_self_gen' => 'required',
                    ]
                );

                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 400);
                }

                $upfrontSystemSettingData = upfrontSystemSetting::create([
                    'upfront_for_self_gen' => $request->upfront_for_self_gen,

                ]);

                return response()->json([
                    'ApiName' => 'upfront',
                    'status' => true,
                    'message' => 'Added Successfully.',
                    'data' => $upfrontSystemSettingData,
                ], 200);

            }

        }
    }

    // Overrides Update +  Overrides_setting
    public function updateOverrides(Request $request)
    {

        // return $request->all();

        if (! null == $request->overrides_setting) {

            $status = OverrideSettings::first();
            if ($status->status == $request->status) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        'settlement_type' => 'required',
                        'overrides_setting.*.override_type' => 'required',
                        'override_setting_id' => 'required',
                    ]
                );

                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 400);
                }
                $user = OverrideSettings::where('id', $request->override_setting_id)->first();
                $user->settlement_type = $request->settlement_type;
                $user->save();
                // $data = OverrideSettings::get();
                foreach ($request->overrides_setting as $overrides) {
                    $post = OverridesType::find($overrides['id']);
                    $post->lock_pay_out_type = $overrides['lock_pay_out_type'];
                    $post->max_limit = $overrides['max_limit'];
                    $post->parsonnel_limit = $overrides['personnel_limit'];
                    $post->min_position = $overrides['min_position'];
                    $post->level = $overrides['level'];
                    $post->is_check = $overrides['is_check'];
                    $post->override_setting_id = $request->override_setting_id;
                    $post->save();
                }
                $superAdmin = User::where('is_super_admin', 1)->first();
                $data = Notification::create([
                    'user_id' => $superAdmin->id,
                    'type' => 'Overrides',
                    'description' => 'Updated Overrides Data by '.auth()->user()->first_name,
                    'is_read' => 0,

                ]);

                $notificationData = [

                    'user_id' => auth()->user()->id,
                    'device_token' => $superAdmin->device_token,
                    'title' => 'Updated Overrides Data.',
                    'sound' => 'sound',
                    'type' => 'Overrides',
                    'body' => 'Updated Overrides Data by '.auth()->user()->first_name,
                ];
                $this->sendNotification($notificationData);

                $status = OverridesType::all();

                return response()->json([
                    'ApiName' => 'Overrides-update',
                    'status' => true,
                    'message' => 'Overrides_type',
                    'data' => $user, $status,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'override-update',
                    'status' => false,
                    'message' => 'access denied',
                    'data' => null,
                ], 400);
            }
        }
    }

    // show Marketing Deal Recanciliation
    public function getMarketingdeal()
    {
        $data1 = MarketingDealsSetting::first();
        $data = MarketingDealsReconciliations::all();
        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'period_from' => dateToYMD($data->period_from),
                'period_to' => dateToYMD($data->period_to),
                'day_date' => dateToYMD($data->day_date),
                'marketing_setting_id' => $data->marketing_setting_id,
            ];
        });

        return response()->json([
            'setting_status' => $data1->status,
            'ApiName' => 'Marketingdeal-Reconciliation-show',
            'status' => true,
            'message' => 'access',
            'marketing_reconciliations' => $data,
        ], 200);
    }

    public function updateMarketingdeal(Request $request)
    {
        // return $request;
        if (! null == $request->marketing_reconciliations && $request->marketing_setting_id) {
            $data1 = MarketingDealsSetting::first();
            if ($data1->status == $request->status) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        'marketing_reconciliations.*.period_from' => 'required',
                        'marketing_reconciliations.*.period_to' => 'required',
                        'marketing_reconciliations.*.day_date' => 'required',
                        'marketing_setting_id' => 'required',
                    ]
                );

                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 200);
                }
                $setting_id = $request->marketing_setting_id;
                // dd($setting_id);
                $data = MarketingDealsReconciliations::where('marketing_setting_id', $setting_id)->delete();

                foreach ($request->marketing_reconciliations as $reconciliation) {
                    MarketingDealsReconciliations::create(
                        [
                            'period_from' => $reconciliation['period_from'],
                            'period_to' => $reconciliation['period_to'],
                            'day_date' => $reconciliation['day_date'],
                            'marketing_setting_id' => $setting_id,
                        ]
                    );
                }
                $status = MarketingDealsReconciliations::where('marketing_setting_id', $setting_id)->get();

                return response()->json([
                    'ApiName' => 'backend-reconciliations-update',
                    'status' => true,
                    'message' => 'changes Successfully.',
                    'data' => $status,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'backend-reconciliations-update',
                    'status' => false,
                    'message' => '',
                    'data' => null,
                ], 400);
            }
        }
    }

    public function getMargin(): JsonResponse
    {
        $data1 = MarginSetting::first();
        $data = MarginOfDifferences::all();

        return response()->json([
            'setting_status' => $data1->status,
            'ApiName' => 'Marketingdeal-Reconciliation-show',
            'status' => true,
            'message' => 'access',
            'margin_difference' => $data,
        ], 200);
    }

    public function updateMargin(Request $request)
    {
        // return $request->all();
        if (! null == $request->margin_difference && $request->margin_setting_id) {
            $data1 = MarginSetting::first();
            if ($data1->status == $request->status) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        'margin_difference.*.difference_parcentage' => 'required',
                        'margin_difference.*.applied_to' => 'required',
                        'margin_setting_id' => 'required',
                    ]
                );

                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 200);
                }
                $setting_id = $request->margin_setting_id;
                // dd($request->margin_difference);
                $data = MarginOfDifferences::where('margin_setting_id', $setting_id)->delete();

                foreach ($request->margin_difference as $reconciliation) {
                    // dd($reconciliation);
                    MarginOfDifferences::create(
                        [
                            'difference_parcentage' => $reconciliation['difference_parcentage'],
                            'applied_to' => $reconciliation['applied_to'],
                            'margin_setting_id' => $setting_id,
                        ]
                    );
                }
                $status = MarginOfDifferences::where('margin_setting_id', $setting_id)->get();

                return response()->json([
                    'ApiName' => 'Margin-reconciliations-update',
                    'status' => true,
                    'message' => 'changes Successfully.',
                    'data' => $status,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'Margin-reconciliations-update',
                    'status' => false,
                    'message' => '',
                    'data' => null,
                ], 400);
            }
        }
    }

    // Tier duration setting
    public function getTierDuration()
    {
        $data = TierSettings::first();
        if ($data->status == '1') {
            $data1 = TierDurationSettings::with('Level', 'Configure')->get();
            // dd($data1);

            $data1->transform(function ($data1) {
                return [
                    'id' => $data1->id,
                    'name' => $data1->name,
                    'is_check' => $data1->is_check,
                    'scale_based_on' => isset($data1->Level->scale_based_on) ? $data1->Level->scale_based_on : null,
                    'shifts_on' => isset($data1->Level->shifts_on) ? $data1->Level->shifts_on : null,
                    'Reset' => isset($data1->Level->rest) ? $data1->Level->rest : null,
                    'Configure' => isset($data1->Configure) ? $data1->Configure : 'NA',
                ];
            });

            return response()->json([
                'ApiName' => 'get-Tier-duration',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data1,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'get-Tier-duration',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    // Tier-Duration-Setting-update
    public function updateTierDuration(Request $request)
    {
        //  return $request;
        if (! null == $request->data && $request->tier_setting_id) {
            $data1 = TierSettings::first();
            if ($data1->status == $request->tier_setting_id) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        'data.*.id' => 'required',
                        'data.*.is_check' => 'required',
                        'data.*.scale_based_on' => 'required',
                        'data.*.rest' => 'required',
                        'tier_setting_id' => 'required',
                    ]
                );

                // if ($Validator->fails()) {
                //     return response()->json(['error' => $Validator->errors()], 200);
                // }
                $record = TierLevelSetting::where('tier_setting_id', $request->tier_setting_id)->get();
                foreach ($record as $key => $data) {
                    $post = TierLevelSetting::where('id', $data['id'])
                        ->where('tier_setting_id', $request->tier_setting_id)->first();

                    $post->scale_based_on = $request['data'][$key]['scale_based_on'];
                    $post->shifts_on = $request['data'][$key]['shifts_on'];
                    // $post->shifts_on = $request['data'][$key]['shifts_on'];
                    $post->rest = $request['data'][$key]['rest'];
                    $post->tier_type_id = $request['data'][$key]['id'];
                    $post->tier_setting_id = $request['tier_setting_id'];
                    $post->save();
                }
                foreach ($request->data as $value) {
                    // dd($value);
                    $post = TierDurationSettings::find($value['id']);
                    $post->is_check = $value['is_check'];
                    $post->save();
                }
                // if($request->is_check == 0)
                // {
                $data2 = TierDurationSettings::where('is_check', 0)->get();
                foreach ($data2 as $data4) {
                    // $data3 = TierLevelSetting::where('tier_type_id',$data4->id)->->delete();
                    $data3 = TierLevelSetting::where('tier_type_id', $data4->id)->select('scale_based_on', 'rest', 'shifts_on')->update(['scale_based_on' => null, 'rest' => null, 'shifts_on' => null]);
                    $data3 = ConfigureTier::where('tier_type_id', $data4->id)->delete();
                    // dd($data3);
                }

                return response()->json([
                    'ApiName' => 'Tier-durations-update',
                    'status' => true,
                    'message' => 'changes Successfully.',
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'Tier-durations-update',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    // get company payrolls
    public function getCompanyPayroll()
    {
        $data = CompanyPayrolls::with('frequencyType')->first();
        $f_name = $data->frequencyType->name;
        $data1['id'] = $data->id;
        $data1['frequency_type_id'] = $data->frequency_type_id;
        $data1['frequency_name'] = $data->frequencyType->name;
        $data1['first_months'] = $data->first_months;
        $data1['first_day'] = dateToDMY($data->first_day);
        $data1['day_of_week'] = $data->day_of_week;
        $data1['day_of_months'] = $data->day_of_months;
        $data1['pay_period'] = $data->pay_period;
        $data1['monthly_per_days'] = $data->monthly_per_days;
        $data1['first_day_pay_of_manths'] = $data->first_day_pay_of_manths;
        $data1['second_pay_day_of_month'] = $data->second_pay_day_of_month;
        $data1['deadline_to_run_payroll'] = $data->deadline_to_run_payroll;
        $data1['first_pay_period_ends_on'] = $data->first_pay_period_ends_on;
        // $data1['frequency_id'] = $data->frequencyType->id;

        return $data1;
    }

    public function updateCompanyPayroll(Request $request)
    {
        // return  $request;
        if (! null == $request->all()) {
            $setting_id = $request->frequency_type_id;
            // dd($setting_id);
            $data = CompanyPayrolls::first();
            $data->delete();

            // $post = CompanyPayrolls::find($request["id"]);
            CompanyPayrolls::create(
                [
                    'first_months' => $request['first_months'],
                    'day_of_week' => $request['day_of_week'],
                    'first_day' => $request['first_day'],
                    'frequency_type_id' => $request['frequency_type_id'],
                    'day_of_months' => $request['day_of_months'],
                    'pay_period' => $request['pay_period'],
                    'monthly_per_days' => $request['monthly_per_days'],
                    'first_day_pay_of_manths' => $request['first_day_pay_of_manths'],
                    'second_pay_day_of_month' => $request['second_pay_day_of_month'],
                    'deadline_to_run_payroll' => $request['deadline_to_run_payroll'],
                    'first_pay_period_ends_on' => $request['first_pay_period_ends_on'],
                ]
            );

            // $data1 = CompanyPayrolls::first();
            return response()->json([
                'ApiName' => 'Compoany-Payroll-update',
                'status' => true,
                'message' => 'changes Successfully.',
                // 'data' => $data1,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Company-payroll-update',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    // get Configure list
    public function getconfigure($id): JsonResponse
    {
        $data = ConfigureTier::where('tier_type_id', $id)->get();

        return response()->json([
            'ApiName' => 'Tier-Configure-list',
            'status' => true,
            'message' => 'changes Successfully.',
            'data' => $data,
        ], 200);
    }

    // create  tier configure
    public function createconfigure(Request $request): JsonResponse
    {
        $data5 = TierDurationSettings::where('id', $request->tier_type_id)->first();
        if ($data5->is_check == ! 0) {
            if (! null == $request->data && $request->tier_type_id) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        'data.*is_check' => 'required',
                        'data.*.installs_from' => 'required',
                        'data.*.redline_shift' => 'required',
                        'data.*.installs_to' => 'required',
                        'tier_type_id' => 'required',
                    ]
                );

                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 400);
                }
                $data = ConfigureTier::where('tier_type_id', $request->tier_type_id)->delete();
                foreach ($request->data as $configure) {
                    // dd($configure);
                    $data = ConfigureTier::create(
                        [
                            'installs_from' => $configure['installs_from'],
                            'redline_shift' => $configure['redline_shift'],
                            'installs_to' => $configure['installs_to'],
                            'tier_type_id' => $request['tier_type_id'],
                        ]
                    );
                }

                return response()->json([
                    'ApiName' => 'Tier-Configure-creat',
                    'status' => true,
                    'message' => 'add Successfully.',
                ], 200);
            }
        }

        return response()->json([
            'ApiName' => 'Tier-Configure-list',
            'status' => false,
            'message' => 'faild.',
            'data' => '',
        ], 400);
    }

    // edit Tier configure
    // public function updateconfigure(Request $request)
    // {
    //     // return $request;
    //     $Validator = Validator::make(
    //         $request->all(),
    //         [
    //             'installs_from'  => 'required',
    //             'redline_shift' => 'required',
    //             'installs_to' => 'required',
    //             'id' => 'required',
    //         ]
    //     );

    //     if ($Validator->fails()) {
    //         return response()->json(['error' => $Validator->errors()], 400);
    //     }
    //     $post = ConfigureTier::find(request()["id"]);
    //     $post->installs_from = $request['installs_from'];
    //     $post->redline_shift = $request['redline_shift'];
    //     $post->installs_to = $request['installs_to'];
    //     // $post->min_position = $request['min_position'];
    //     $post->save();
    //     return response()->json([
    //         'ApiName' => 'Tier-Configure-creat',
    //         'status' => true,
    //         'message' => 'changes Successfully.',
    //         'data'  => $post,
    //     ], 200);
    // }

    // delete configure
    public function deleteconfigure($id)
    {
        // return $id;
        if (! null == $id) {

            $data = ConfigureTier::where('tier_type_id', $id);
            if ($data == null) {
                return response()->json(['status' => true, 'message' => 'Tier Configure not find.'], 200);
            } else {
                $id = ConfigureTier::where('tier_type_id', $id);
                $id->delete();

                return response()->json([
                    'ApiName' => 'tier-configure-delete',
                    'status' => true,
                    'message' => 'delete Successfully.',
                    'data' => $id,
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'tier-configure-delete',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function AddPayfrequencySettingOld(Request $request): JsonResponse
    {
        $data = PositionCommission::where('position_id', $request['position_id'])->first();

        if ($request->upfront_status == 1) {
            $data1 = PositionCommissionUpfronts::where('position_id', $request['position_id'])->first();

            $data6 = PositionTierOverride::where('position_id', $request->position_id)->first();

            $data8 = PositionPayFrequency::where('position_id', $request->position_id)->first();
            // dd($data8);
            if ($data8 == null) {
                PositionPayFrequency::create(
                    [
                        'position_id' => $request['position_id'],
                        'frequency_type_id' => $request['frequency_type_id'],
                        'first_months' => $request['first_months'],
                        'day_of_week' => $request['day_of_week'],
                        'first_day' => $request['first_day'],
                        'day_of_months' => $request['day_of_months'],
                        'pay_period' => $request['pay_period'],
                        'monthly_per_days' => $request['monthly_per_days'],
                        'first_day_pay_of_manths' => $request['first_day_pay_of_manths'],
                        'second_pay_day_of_month' => $request['second_pay_day_of_month'],
                        'deadline_to_run_payroll' => $request['deadline_to_run_payroll'],
                        'first_pay_period_ends_on' => $request['first_pay_period_ends_on'],
                    ]
                );
            }
            $Position = Positions::where('id', $request['position_id'])->first();
            $Position->setup_status = 1;
            $Position->save();
        }
        $data = Positions::with('departmentDetail', 'Commission', 'Upfront', 'deductionname', 'Override', 'deductionlimit', 'OverrideTier', 'reconciliation', 'payFrequency')->where('id', $request['position_id'])->first();

        return response()->json(['status' => true, 'message' => 'Add Successfully.', 'data' => $data], 200);
    }

    public function createWeeklyPayFrequency_old($pay_period): JsonResponse
    {
        $frequencyValue = explode('-', $pay_period);
        $startDate = date('Y-m-d', strtotime($frequencyValue[0]));
        // $endDate   =  date('Y-m-d', strtotime($frequencyValue[1]));
        $endDate = date('Y-12-31');

        $now = strtotime($endDate);
        $your_date = strtotime($startDate);
        $dateDiff = $now - $your_date;
        $dateDays = floor($dateDiff / (60 * 60 * 24)) + 1;

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

            // $aWeek = $sDate.'-to-'.$eDate;
            // $weekdata[] = $aWeek;
            WeeklyPayFrequency::create(
                [
                    'pay_period_from' => $sDate,
                    'pay_period_to' => $eDate,
                ]
            );

        }

        return response()->json(['ApiName' => 'Weekly Pay Frequency Setting', 'status' => true, 'message' => 'Create Successfully.'], 200);
    }

    public function createWeeklyPayFrequency($pay_period): JsonResponse
    {
        if (config('app.domain_name') == 'flex') {
            $period = explode(' - ', $pay_period);

            // Begins From Past 3 Years
            $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->copy()->subYears(3);

            // Two Years Later From The Start Date
            $endDateTwoYearsLater = $startDate->copy()->addYears(5);
            $currentDate = $startDate->copy();

            $payPeriods = [];
            while ($currentDate->lte($endDateTwoYearsLater)) {
                $payPeriods[] = [
                    'pay_period_from' => $currentDate->copy()->format('Y-m-d'),
                    'pay_period_to' => $currentDate->copy()->addDays(6)->format('Y-m-d'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Move To The Next Pay Period
                $currentDate->addDays(7);
            }
            WeeklyPayFrequency::insert($payPeriods);
        } else {
            $period = explode(' - ', $pay_period);

            if (! isset($period[0])) {
                return response()->json(['ApiName' => 'Weekly Pay Frequency Setting', 'status' => false, 'message' => 'Start Date is not defined.'], 400);
            }

            // Begins From Given Date
            $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->copy();

            // Two Years Added From The Start Date
            $endDateTwoYearsLater = $startDate->copy()->addYears(2);
            $currentDate = $startDate->copy();

            $payPeriods = [];
            while ($currentDate->lte($endDateTwoYearsLater)) {
                $payPeriods[] = [
                    'pay_period_from' => $currentDate->copy()->format('Y-m-d'),
                    'pay_period_to' => $currentDate->copy()->addDays(6)->format('Y-m-d'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Move To The Next Pay Period
                $currentDate->addDays(7);
            }
            WeeklyPayFrequency::insert($payPeriods);
        }

        return response()->json(['ApiName' => 'Weekly Pay Frequency Setting', 'status' => true, 'message' => 'Create Successfully.'], 200);
    }

    public function createWeeklyPayFrequency1(Request $request): JsonResponse
    {
        // echo"dasd";die;

        $frequencyValue = explode('-', $request->pay_period);
        $newDate = date('Y-m-d', strtotime($frequencyValue[0]));
        // $threeYearBackDate = date('Y', strtotime($newDate. ' - 3 years'));
        $threeYearBackDate = date('Y', strtotime($newDate));
        // $firstMonth = date($threeYearBackDate.'-01-01');
        $firstMonth = date('2024-01-30');
        $firstMonthDay = date('Y-m-d', strtotime('first monday of 2020'));
        // $day = $day_of_week;
        $day = 'Tuesday';
        // $startDate = date("Y-m-d", strtotime($day." jan ".$threeYearBackDate));
        $startDate = date('2024-01-30');

        $firstMonth = date('Y-m-d', strtotime($threeYearBackDate.'1 months'));
        // $endDate   =  date('Y-m-d', strtotime($frequencyValue[1]));
        $endDate = date('2024-12-31');

        $now = strtotime($endDate);
        $your_date = strtotime($startDate);
        $dateDiff = $now - $your_date;

        $dateDays = floor($dateDiff / (60 * 60 * 24)) + 1;
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

            // $aWeek = $sDate.'-to-'.$eDate;
            // $weekdata[] = $aWeek;
            WeeklyPayFrequency::create(
                [
                    'pay_period_from' => $sDate,
                    'pay_period_to' => $eDate,
                ]
            );

        }

        return response()->json(['ApiName' => 'Weekly Pay Frequency Setting', 'status' => true, 'message' => 'Create Successfully.'], 200);
    }

    public function createWeeklyPayFrequency12(Request $request): JsonResponse
    {

        $frequencyValue = explode('-', $request->pay_period);
        $newDate = date('Y-m-d', strtotime($frequencyValue[0]));
        $years = '2024';
        $day = 'Monday';
        $startDateJan = date('Y-m-d', strtotime($day.' jan '.$years));

        $startDate = date('2024-01-05');
        $endDate = date('2025-12-31');

        $now = strtotime($endDate);
        $your_date = strtotime($startDate);
        $dateDiff = $now - $your_date;

        $dateDays = floor($dateDiff / (60 * 60 * 24)) + 1;
        $weekCount = round($dateDays / 7);
        $totalWeekDay = 7 * $weekCount;
        $extraDay = $dateDays - $totalWeekDay;

        if ($extraDay > 0) {
            $weekCount = $weekCount + 1;
        }

        // WeeklyPayFrequency::create(
        //     [
        //         'pay_period_from' => '2024-01-01',
        //         'pay_period_to' => '2024-01-05'
        //     ]
        // );

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

            // $aWeek = $sDate.'-to-'.$eDate;
            // $weekdata[] = $aWeek;
            WeeklyPayFrequency::create(
                [
                    'pay_period_from' => $sDate,
                    'pay_period_to' => $eDate,
                ]
            );

        }

        return response()->json(['ApiName' => 'Weekly Pay Frequency Setting', 'status' => true, 'message' => 'Create Successfully.'], 200);
    }

    public function createMonthlyPayFrequency_old($pay_period): JsonResponse
    {
        $frequencyValue = explode('-', $pay_period);
        $startDate = date('Y-m-d', strtotime($frequencyValue[0]));
        // $endDate   =  date('Y-m-d', strtotime($frequencyValue[1]));
        $month = 12 - (date('m', strtotime($startDate)));
        $endDate = date('Y-12-31');

        for ($i = 0; $i <= $month; $i++) {
            $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
            $eDate = date('Y-m-t', strtotime('+'.$i.' months', strtotime($startDate)));
            MonthlyPayFrequency::create(
                [
                    'pay_period_from' => $sDate,
                    'pay_period_to' => $eDate,
                ]
            );

        }

        return response()->json(['ApiName' => 'Weekly Pay Frequency Setting', 'status' => true, 'message' => 'Create Successfully.'], 200);
    }

    public function createMonthlyPayFrequency($pay_period): JsonResponse
    {
        if (config('app.domain_name') == 'flex') {
            $period = explode(' - ', $pay_period);

            // Begins From Past 3 Years
            $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->copy()->subYears(3);

            // Two Years Later From The Start Date
            $endDateTwoYearsLater = $startDate->copy()->addYears(5);
            $currentDate = $startDate->copy();

            $payPeriods = [];
            while ($currentDate->lte($endDateTwoYearsLater)) {
                $payPeriods[] = [
                    'pay_period_from' => $currentDate->copy()->format('Y-m-d'),
                    'pay_period_to' => $currentDate->copy()->addDays(29)->format('Y-m-d'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Move To The Next Pay Period
                $currentDate->addDays(30);
            }
            MonthlyPayFrequency::insert($payPeriods);
        } else {
            $period = explode(' - ', $pay_period);
            $startDate = Carbon::createFromFormat('m/d/Y', $period[0]);
            $currentDate = $startDate->copy();

            $payPeriods = [];
            for ($i = 0; $i < 24; $i++) {
                $payPeriods[] = [
                    'pay_period_from' => $currentDate->copy()->addMonths($i)->format('Y-m-d'),
                    'pay_period_to' => $currentDate->copy()->addMonths(($i + 1))->subDay()->format('Y-m-d'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            MonthlyPayFrequency::insert($payPeriods);
        }

        //         $frequencyValue = explode('-', $pay_period);
        //         $threeYearBackDate = date("Y", strtotime("-3 year"));
        //
        //         for($k=0; $k<=4; $k++)
        //         {
        //             $year = $threeYearBackDate+$k;
        //             $startDate = date($year.'-01-01');
        //
        //             for($i=0; $i<=11; $i++)
        //             {
        //                 $sDate = date('Y-m-d', strtotime("+". $i ." months", strtotime($startDate)));
        //                 $eDate = date('Y-m-t', strtotime("+". $i ." months", strtotime($startDate)));
        //                 MonthlyPayFrequency::create([
        //                     'pay_period_from' => $sDate,
        //                     'pay_period_to' => $eDate
        //                 ]);
        //             }
        //         }

        return response()->json(['ApiName' => 'Weekly Pay Frequency Setting', 'status' => true, 'message' => 'Create Successfully.'], 200);
    }

    protected function createAdditionalPayFrequency($pay_period, int $addDays, $type)
    {
        $period = explode(' - ', $pay_period);

        // Begins From Given Date
        $startDate = Carbon::createFromFormat('m/d/Y', $period[0]);

        // Two Years Later From The Start Date
        $endDateTwoYearsLater = $startDate->copy()->addYears(2);
        $currentDate = $startDate->copy();

        $payPeriods = [];
        while ($currentDate->lte($endDateTwoYearsLater)) {
            $payPeriods[] = [
                'pay_period_from' => $currentDate->copy()->format('Y-m-d'),
                'pay_period_to' => $currentDate->copy()->addDays($addDays)->format('Y-m-d'),
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Move To The Next Pay Period
            $currentDate->addDays(($addDays + 1));
        }
        AdditionalPayFrequency::insert($payPeriods);

        return response()->json(['ApiName' => 'Additional Pay Frequency Setting', 'status' => true, 'message' => 'Created Successfully.']);
    }

    protected function createAdditionalSemiMonthlyPayFrequency($startDate, $endDate)
    {
        $payPeriods = [];
        $payPeriods[] = [
            'pay_period_from' => $startDate->copy()->format('Y-m-d'),
            'pay_period_to' => $endDate->copy()->format('Y-m-d'),
            'type' => '2',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $start = $startDate->copy()->startOfMonth()->format('Y-m-d');
        $start2 = $startDate->copy()->endOfMonth()->format('Y-m-d');
        $end = $endDate->copy()->startOfMonth()->format('Y-m-d');
        $end2 = $endDate->copy()->endOfMonth()->format('Y-m-d');
        if ($startDate->copy()->format('Y-m-d') == $start) { // 01-03 == 01-03
            $payPeriods[] = [
                'pay_period_from' => $endDate->copy()->addDay()->format('Y-m-d'),
                'pay_period_to' => $endDate->copy()->endOfMonth()->format('Y-m-d'),
                'type' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            for ($i = 0; $i < 12; $i++) {
                $payPeriods[] = [
                    'pay_period_from' => $startDate->copy()->addMonth($i + 1)->startOfMonth()->format('Y-m-d'),
                    'pay_period_to' => $endDate->copy()->addMonth($i + 1)->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $payPeriods[] = [
                    'pay_period_from' => $endDate->copy()->addMonth($i + 1)->addDay()->format('Y-m-d'),
                    'pay_period_to' => $endDate->copy()->addMonth($i + 1)->endOfMonth()->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } elseif ($startDate->copy()->format('Y-m-d') == $start2) { // 31-03 == 31-03
            $payPeriods[] = [
                'pay_period_from' => $endDate->copy()->addDay()->format('Y-m-d'),
                'pay_period_to' => $endDate->copy()->endOfMonth()->subDay()->format('Y-m-d'),
                'type' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            for ($i = 0; $i < 11; $i++) {
                $payPeriods[] = [
                    'pay_period_from' => $startDate->copy()->subDay()->addMonth($i + 1)->endOfMonth()->format('Y-m-d'),
                    'pay_period_to' => $endDate->copy()->addMonth($i + 1)->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $payPeriods[] = [
                    'pay_period_from' => $endDate->copy()->addMonth($i + 1)->addDay()->format('Y-m-d'),
                    'pay_period_to' => $endDate->copy()->addMonth($i + 1)->endOfMonth()->subDay()->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } elseif ($endDate->copy()->format('Y-m-d') == $end) { // 01-03 == 01-03
            $payPeriods[] = [
                'pay_period_from' => $endDate->copy()->addDay()->format('Y-m-d'),
                'pay_period_to' => $startDate->copy()->addMonth()->subDay()->format('Y-m-d'),
                'type' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            for ($i = 0; $i < 11; $i++) {
                $payPeriods[] = [
                    'pay_period_from' => $startDate->copy()->addMonth($i + 1)->format('Y-m-d'),
                    'pay_period_to' => $startDate->copy()->addMonth($i + 1)->endOfMonth()->addDay()->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $payPeriods[] = [
                    'pay_period_from' => $startDate->copy()->addMonth($i + 1)->endOfMonth()->addDays(2)->format('Y-m-d'),
                    'pay_period_to' => $startDate->copy()->addMonth($i + 2)->subDay()->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } elseif ($endDate->copy()->format('Y-m-d') == $end2) { // 31-03 == 31-03
            $payPeriods[] = [
                'pay_period_from' => $endDate->copy()->addDay()->format('Y-m-d'),
                'pay_period_to' => $startDate->copy()->addMonth()->subDay()->format('Y-m-d'),
                'type' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            for ($i = 0; $i < 11; $i++) {
                $payPeriods[] = [
                    'pay_period_from' => $startDate->copy()->addMonth($i + 1)->format('Y-m-d'),
                    'pay_period_to' => $startDate->copy()->addMonth($i + 1)->endOfMonth()->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $payPeriods[] = [
                    'pay_period_from' => $startDate->copy()->addMonth($i + 1)->endOfMonth()->addDay()->format('Y-m-d'),
                    'pay_period_to' => $startDate->copy()->addMonth($i + 2)->subDay()->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } else {
            $payPeriods[] = [
                'pay_period_from' => $endDate->copy()->addDay()->format('Y-m-d'),
                'pay_period_to' => $startDate->copy()->addMonth()->subDay()->format('Y-m-d'),
                'type' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            for ($i = 0; $i < 11; $i++) {
                $payPeriods[] = [
                    'pay_period_from' => $startDate->copy()->addMonth($i + 1)->format('Y-m-d'),
                    'pay_period_to' => $endDate->copy()->addMonth($i + 1)->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $payPeriods[] = [
                    'pay_period_from' => $endDate->copy()->addMonth($i + 1)->addDay()->format('Y-m-d'),
                    'pay_period_to' => $startDate->copy()->addMonth($i + 2)->subDay()->format('Y-m-d'),
                    'type' => '2',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        AdditionalPayFrequency::insert($payPeriods);

        return response()->json(['ApiName' => 'Additional Pay Frequency Setting', 'status' => true, 'message' => 'Created Successfully.']);
    }

    public function AddPayfrequencySetting(Request $request): JsonResponse
    {
        $check = false;
        $frequencyValue = $request->frequency;
        DB::beginTransaction();
        try {
            foreach ($frequencyValue as $data) {
                $frequency = PositionPayFrequency::where('frequency_type_id', $data['frequency_type_id'])->count();
                if ($frequency > 0) {
                    $freq = payFrequencySetting::where([
                        'frequency_type_id' => $data['frequency_type_id'],
                        'first_months' => $data['first_months'],
                        'first_day' => $data['first_day'],
                        'day_of_week' => $data['day_of_week'],
                        'day_of_months' => $data['day_of_months'],
                        'pay_period' => $data['pay_period'],
                        'monthly_pay_type' => $data['monthly_pay_type'],
                        'monthly_per_days' => $data['monthly_per_days'],
                        'first_day_pay_of_manths' => $data['first_day_pay_of_manths'],
                        'second_pay_day_of_month' => $data['second_pay_day_of_month'],
                        'deadline_to_run_payroll' => $data['deadline_to_run_payroll'],
                        'first_pay_period_ends_on' => $data['first_pay_period_ends_on'],
                    ])->first();
                    if (! $freq) {
                        DB::rollBack();

                        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => false, 'message' => 'Pay frequency is already assigned to the position and therefor you can not change or remove pay frequency.'], 400);
                    }

                    continue;
                }

                if ($data['status'] != '1') {
                    $check = true;
                    FrequencyType::where('id', $data['frequency_type_id'])->update(['status' => $data['status']]);
                    // payFrequencySetting::where('frequency_type_id', $data['frequency_type_id'])->delete();
                    $records = payFrequencySetting::where('frequency_type_id', $data['frequency_type_id'])->get(); // Added for activity log
                    foreach ($records as $record) {
                        $record->delete();
                    }
                    if ($data['frequency_type_id'] == 2) {
                        WeeklyPayFrequency::truncate();
                    } elseif ($data['frequency_type_id'] == 5) {
                        MonthlyPayFrequency::truncate();
                    } elseif ($data['frequency_type_id'] == FrequencyType::BI_WEEKLY_ID) {
                        AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->delete();
                    } elseif ($data['frequency_type_id'] == FrequencyType::SEMI_MONTHLY_ID) {
                        AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->delete();
                    }

                    continue;
                }

                $check = true;
                if ($data['frequency_type_id'] == 2) {
                    $deleteData = WeeklyPayFrequency::first();
                    if ($deleteData) {
                        WeeklyPayFrequency::truncate();
                    }
                    $this->createWeeklyPayFrequency($data['pay_period']);
                } elseif ($data['frequency_type_id'] == 5) {
                    $deleteData = MonthlyPayFrequency::first();
                    if ($deleteData) {
                        MonthlyPayFrequency::truncate();
                    }
                    $this->createMonthlyPayFrequency($data['pay_period']);
                } elseif ($data['frequency_type_id'] == FrequencyType::BI_WEEKLY_ID) {
                    if (AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->first()) {
                        AdditionalPayFrequency::where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->delete();
                    }
                    $this->createAdditionalPayFrequency($data['pay_period'], 13, 1);
                } elseif ($data['frequency_type_id'] == FrequencyType::SEMI_MONTHLY_ID) {
                    if (AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->first()) {
                        AdditionalPayFrequency::where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->delete();
                    }

                    $period = explode(' - ', $data['pay_period']);

                    $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->format('Y-m-d');
                    $endDate = Carbon::createFromFormat('m/d/Y', $period[1])->format('Y-m-d');

                    if (Carbon::createFromFormat('m/d/Y', $period[1])->lessThanOrEqualTo(Carbon::createFromFormat('m/d/Y', $period[0]))) {
                        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'End date can not be less than or equal to start date.'], 400);
                    }

                    if ($startDate == $endDate) {
                        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => "Pay period from and pay period to data can't be the same!"], 400);
                    }

                    $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->startOfMonth()->format('Y-m-d');
                    $endDate = Carbon::createFromFormat('m/d/Y', $period[1])->endOfMonth()->format('Y-m-d');
                    $customStartDate = Carbon::createFromFormat('m/d/Y', $period[0])->format('Y-m-d');
                    $customEndDate = Carbon::createFromFormat('m/d/Y', $period[1])->format('Y-m-d');
                    if ($startDate == $customStartDate && $endDate == $customEndDate) {
                        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'You can’t select an entire month as pay period for semi monthly pay frequency!'], 400);
                    }

                    $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->startOfMonth()->format('Y-m-d');
                    $endDate = Carbon::createFromFormat('m/d/Y', $period[1])->endOfMonth()->subDay()->format('Y-m-d');
                    $customStartDate = Carbon::createFromFormat('m/d/Y', $period[0])->format('Y-m-d');
                    $customEndDate = Carbon::createFromFormat('m/d/Y', $period[1])->format('Y-m-d');
                    if ($startDate == $customStartDate && $endDate == $customEndDate) {
                        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'There is no room to create the other pay period for semi monthly!'], 400);
                    }

                    $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->startOfMonth()->addDay()->format('Y-m-d');
                    $endDate = Carbon::createFromFormat('m/d/Y', $period[1])->endOfMonth()->format('Y-m-d');
                    $customStartDate = Carbon::createFromFormat('m/d/Y', $period[0])->format('Y-m-d');
                    $customEndDate = Carbon::createFromFormat('m/d/Y', $period[1])->format('Y-m-d');
                    if ($startDate == $customStartDate && $endDate == $customEndDate) {
                        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'There is no room to create the other pay period for semi monthly!'], 400);
                    }

                    $endDate = Carbon::createFromFormat('m/d/Y', $period[1])->format('m');
                    if ($endDate == 2) {
                        $startDate = Carbon::createFromFormat('m/d/Y', $period[0])->startOfDay();
                        $endDate = Carbon::createFromFormat('m/d/Y', $period[1])->endOfDay();

                        if ($startDate == $endDate) {
                            return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'You can’t select an entire month as pay period for semi monthly pay frequency!'], 400);
                        }

                        $differenceInDays = $startDate->diffInDays($endDate);

                        $firstDayOfMonth = Carbon::createFromFormat('m/d/Y', $period[1])->startOfMonth();
                        $lastDayOfMonth = Carbon::createFromFormat('m/d/Y', $period[1])->endOfMonth();
                        $febMonth = $firstDayOfMonth->diffInDays($lastDayOfMonth) - 1;

                        if ($differenceInDays > $febMonth) {
                            return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'There is no room to create the other pay period for semi monthly!'], 400);
                        }
                    }

                    $customStartDate = Carbon::createFromFormat('m/d/Y', $period[0])->startOfDay();
                    $customEndDate = Carbon::createFromFormat('m/d/Y', $period[1])->addDay()->endOfDay();
                    $differenceInDays = $customEndDate->diffInDays($customStartDate);
                    if ($differenceInDays >= 31) {
                        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'Selected period is more then a month!'], 400);
                    }

                    $startDate = Carbon::createFromFormat('m/d/Y', $period[0]);
                    $endDate = Carbon::createFromFormat('m/d/Y', $period[1]);
                    $this->createAdditionalSemiMonthlyPayFrequency($startDate, $endDate);
                }

                // $payFrequencySettingInst = payFrequencySetting::where(['frequency_type_id' => $data['frequency_type_id']])->first();
                // $oldAttributes = $payFrequencySettingInst->getOriginal();
                $frequencyTypeName = FrequencyType::where('id', $data['frequency_type_id'])->value('name');
                $payFrequencySettingInst = new payFrequencySetting;
                $payFrequencySettingInst->setCustomField($frequencyTypeName);

                payFrequencySetting::updateOrCreate(['frequency_type_id' => $data['frequency_type_id']], [
                    'frequency_type_id' => $data['frequency_type_id'],
                    'first_months' => $data['first_months'],
                    'first_day' => $data['first_day'],
                    'day_of_week' => $data['day_of_week'],
                    'day_of_months' => $data['day_of_months'],
                    'pay_period' => $data['pay_period'],
                    'monthly_pay_type' => $data['monthly_pay_type'],
                    'monthly_per_days' => $data['monthly_per_days'],
                    'first_day_pay_of_manths' => $data['first_day_pay_of_manths'],
                    'second_pay_day_of_month' => $data['second_pay_day_of_month'],
                    'deadline_to_run_payroll' => $data['deadline_to_run_payroll'],
                    'first_pay_period_ends_on' => $data['first_pay_period_ends_on'],
                ]);

                // custom activity
                //  $newAttributes = $payFrequencySettingInst->getAttributes();

                //  $newAttributes['frequency_type'] = $frequencyTypeName;
                //  $oldAttributes['frequency_type'] = $frequencyTypeName;
                // Log::info($frequencyTypeName);
                // Log::info('newAttributes '.print_r($newAttributes, true));
                // Log::info('oldAttributes '.print_r($oldAttributes, true));
                // array_merge($oldAttributes, ['frequency_type' => $frequencyTypeName]);
                // array_merge($newAttributes, ['frequency_type' => $frequencyTypeName]);

                //  activity()
                //      ->causedBy(auth()->user())
                //      ->performedOn(new payFrequencySetting())
                //      ->withProperties(['attributes' => $newAttributes, 'old' => $oldAttributes])
                //      ->log('updated');

                FrequencyType::where('id', $data['frequency_type_id'])->update(['status' => $data['status']]);
                $this->superAdminPositionFrequencyTypeCreate();
            }

            if (! $check) {
                DB::rollBack();

                return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => false, 'message' => 'Pay frequency is already assigned to the position and therefor you can not change or remove pay frequency.'], 400);
            }

            Notification::create([
                'user_id' => auth()->user()->id,
                'type' => 'Pay Frequency Setting',
                'description' => 'Updated Pay Frequency Setting Data by '.auth()->user()->first_name,
                'is_read' => 0,
            ]);
            $superAdmin = User::where('is_super_admin', 1)->first();
            $notificationData = [
                'user_id' => auth()->user()->id,
                'device_token' => $superAdmin->device_token,
                'title' => 'Updated Pay Frequency Setting Data.',
                'sound' => 'sound',
                'type' => 'Pay Frequency Setting',
                'body' => 'Pay Frequency Setting Data by '.auth()->user()->first_name,
            ];
            $this->sendNotification($notificationData);

            DB::commit();

            return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'Add Successfully.']);
        } catch (Exception $ex) {
            DB::rollBack();

            return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => false, 'message' => $ex->getMessage().' '.$ex->getLine()]);
        }
    }

    public function listPayfrequencySetting(Request $request)
    {

        $data = payFrequencySetting::get();
        $data->transform(function ($data) {
            $val = FrequencyType::where('id', $data->frequency_type_id)->first();

            return [
                'frequency_type_id' => $data['frequency_type_id'],
                'frequency_type_name' => isset($val['name']) ? ($val['name'] === 'Daily-pay' ? 'Daily (on demand)' : $val['name']) : '-',
                'status' => isset($val['status']) ? $val['status'] : '-',
                'first_months' => $data['first_months'],
                'first_day' => $data['first_day'],
                'day_of_week' => $data['day_of_week'],
                'day_of_months' => $data['day_of_months'],
                'pay_period' => $data['pay_period'],
                'monthly_pay_type' => $data['monthly_pay_type'],
                'monthly_per_days' => $data['monthly_per_days'],
                'first_day_pay_of_manths' => $data['first_day_pay_of_manths'],
                'second_pay_day_of_month' => $data['second_pay_day_of_month'],
                'deadline_to_run_payroll' => $data['deadline_to_run_payroll'],
                'first_pay_period_ends_on' => $data['first_pay_period_ends_on'],
            ];
        });

        // $data = array_filter($data[0], function ($value) {
        //     return $value !== null;
        // });

        return response()->json(['ApiName' => 'Pay Frequency Setting', 'status' => true, 'message' => 'Add Successfully.', 'frequency' => $data], 200);
    }

    public function emailNotificationSetting(Request $request): JsonResponse
    {
        $companyId = $request->company_id;
        $status = $request->status;
        $result = EmailNotificationSetting::where('company_id', $companyId)->first();
        if ($result) {
            $update = EmailNotificationSetting::where('company_id', $companyId)->first();
            $update->status = $status;
            $update->save();
        } else {
            EmailNotificationSetting::create(
                [
                    'company_id' => $companyId,
                    'status' => $status,
                ]
            );
        }

        return response()->json(['ApiName' => 'email_notification_setting', 'status' => true, 'message' => 'Add Successfully.'], 200);
    }

    public function getEmailNotificationSetting()
    {
        // $data = EmailNotificationSetting::where('company_id',$companyId)->first();
        $data = EmailNotificationSetting::get();
        $data->transform(function ($data) {
            return [
                'company_id' => $data['company_id'],
                'status' => $data['status'],
            ];
            $page = 'Setting';
            $action = 'Email Setting Added';
            $description = 'company_id =>'.$data['company_id'].', '.'Status =>'.$data['status'];
            user_activity_log($page, $action, $description);
        });

        return response()->json(['ApiName' => 'get_email_notification_setting', 'status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function getDomainSetting(): JsonResponse
    {
        $domain = DomainSetting::orderBy('id', 'Asc')->get();
        $EmailNotificationSetting = EmailNotificationSetting::where('company_id', 1)->first();
        $email_setting_type = 0;
        if ($EmailNotificationSetting != null && $EmailNotificationSetting != '') {
            $email_setting_type = ($EmailNotificationSetting->email_setting_type != null && $EmailNotificationSetting->email_setting_type != '') ? $EmailNotificationSetting->email_setting_type : 0;
        }

        return response()->json(['status' => true, 'message' => 'Successfully.', 'email_setting_type' => $email_setting_type, 'data' => $domain], 200);
    }

    public function addDomainSetting(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'domain_setting.*.domain_name' => 'required|regex:/(.*)\./i|unique:domain_settings',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
            // return response()->json(['error' => 'Domain format is invalid'], 400);
        }
        $domain = $request->domain_setting;
        foreach ($domain as $key => $value) {
            $data = [
                'domain_name' => $value['domain_name'],
                'status' => $value['status'],

            ];
            $insert = DomainSetting::create($data);
            if ($insert) {
                $userId = auth::user();
                if ($insert['status'] == 0) {
                    $description = $userId->first_name.' '.$userId->last_name.' is added domain name '.$data['domain_name'].' and status is disabled';
                }
                if ($insert['status'] == 1) {
                    $description = $userId->first_name.' '.$userId->last_name.' is added domain name '.$data['domain_name'].' and status is enabled';
                }
                $data1 = [
                    'updated_by_id' => $userId['id'],
                    'descriptions' => $description,
                ];
                EmailLogin::create($data1);
            }
        }

        return response()->json([
            'ApiName' => 'Add_domain_setting',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
        ], 200);
    }

    public function updateDomainSetting(Request $request)
    {
        $status_code = 400;
        $status = false;
        $message = 'somthing went wrong! Domain not found!!';

        try {
            $userId = Auth::user();
            if (isset($request->email_setting_type)) {
                $email_setting_type = $request->email_setting_type;
                $EmailNotificationSetting = EmailNotificationSetting::where('company_id', 1)->first();
                if ($EmailNotificationSetting != null && $EmailNotificationSetting != '') {
                    $update_data = EmailNotificationSetting::where('company_id', 1)->first();
                    $update_data->email_setting_type = $email_setting_type;
                    $update_status = $update_data->save();
                    if ($update_status == 1 || $update_status == true) {
                        $status_code = 200;
                        $status = true;
                        $message = 'Domain settings updated successfully.';
                    }
                }
            } else {
                // updating Domain Status
                $id = $request->id;
                $data = DomainSetting::where('id', $id)->first();
                if ($data) {
                    $domain_status = $request['status'];
                    // $updateDate =[
                    //     'domain_name' => $request['domain_name'],
                    //     'status' => $request['status']
                    // ];
                    $update = DomainSetting::where('id', $id)->first();
                    $update->domain_name = $request['domain_name'];
                    $update->status = $request['status'];
                    $update->save();
                    if ($update) {
                        if ($domain_status == 0) {
                            $description = $userId->first_name.' '.$userId->last_name.' changed domain status for '.$data->domain_name.' is disabled';
                        }
                        if ($domain_status == 1) {
                            $description = $userId->first_name.' '.$userId->last_name.' changed domain status for '.$data->domain_name.' is enabled';
                        }

                        $data1 = [
                            'updated_by_id' => $userId['id'],
                            'descriptions' => $description,
                        ];

                        EmailLogin::create($data1);

                        $status_code = 200;
                        $status = true;
                        $message = 'Domain settings updated successfully. Please review custom domains.';

                    }
                }
            }
        } catch (Exception $error) {
            // return $error;
            $message = $error->getMessage();
        }

        return response()->json([
            'ApiName' => 'updateDomainSetting',
            'status' => $status,
            'message' => $message,
        ], $status_code);
    }

    public function updateDomainSetting_24112023(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                // 'domain_name' => 'required|regex:/(.*)\./i|unique:domain_settings',
            ]
        );

        if ($Validator->fails()) {

            return response()->json(['error' => $Validator->errors()], 200);
        }
        $id = $request->id;
        $data = DomainSetting::where('id', $id)->first();

        $updateDate = [
            'domain_name' => $request['domain_name'],
            'status' => $request['status'],
        ];

        $update = DomainSetting::where('id', $id)->update($updateDate);
        if ($update) {
            $page = 'Setting';
            $action = 'Domains update';
            $description = 'Domain =>'.$request['domain_name'].', '.'status =>'.$request['status'];
            $userLog = user_activity_log($page, $action, $description);
        }
        $emailEttingType = $request->email_setting_type;
        if ($emailEttingType == 1) {
            $statusVal = 1;
        } else {
            $statusVal = 0;
        }
        if (isset($request->email_setting_type) && $statusVal != '') {
            $response_status = DomainSetting::query()->update(['email_setting_type' => $statusVal]);
            if ($response_status) {
                $page = 'Setting';
                $action = 'Changed domain Updated';
                $description = 'Email setting type =>'.$statusVal;
                $userLog = user_activity_log($page, $action, $description);
            }
        }

        if ($data) {
            $userId = auth::user();

            if ($data->status == 0) {
                $description = $userId->first_name.' '.$userId->last_name.' changed domain status '.$data->domain_name.' is disabled';
            }
            if ($data->status == 1) {
                $description = $userId->first_name.' '.$userId->last_name.' changed domain status '.$data->domain_name.' is enabled';
            }

            $data1 = [
                'updated_by_id' => $userId['id'],
                'descriptions' => $description,
            ];
            EmailLogin::create($data1);
        }

        if ($statusVal == 1) {
            return response()->json([
                'ApiName' => 'Update Domain',
                'status' => true,
                'message' => 'Domain settings updated successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Update Domain',
                'status' => true,
                'message' => 'Domain settings updated successfully. Please review custom domains.',
            ], 200);
        }
    }

    public function deleteDomainSetting($id): JsonResponse
    {

        if (! null == $id) {

            $data = DomainSetting::where('id', $id)->first();
            if ($data == null) {
                return response()->json(['status' => true, 'message' => 'Domain Setting  not find.'], 200);
            } else {
                $id = DomainSetting::find($id);
                $userId = auth::user();
                if ($id) {
                    $page = 'Setting';
                    $action = 'Domain Deleted';
                    $description = 'Domain =>'.$data->domain_name.', '.'status =>'.$data->status;
                    user_activity_log($page, $action, $description);
                    $description = $userId->first_name.' '.$userId->last_name.' is deleted domain name '.$data->domain_name;

                    $data1 = [
                        'updated_by_id' => $userId['id'],
                        'descriptions' => $description,
                    ];
                    EmailLogin::create($data1);
                }

                $id->delete();

                return response()->json([
                    'ApiName' => 'delete_domain_setting',
                    'status' => true,
                    'message' => 'delete Successfully.',
                    'data' => $id,
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'delete_domain_setting',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function imageDragAndDropSetting(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(),
            [
                'image' => 'mimes:jpeg,jpg,png,pdf,gif|required|max:10000',
            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $stored_bucket = 'public';
        $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
        $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
        if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
            $image = $request->file('image');
            if (isset($image) && $image != null && $image != '') {
                $file = $request->image;
                $img_path = time().$file->getClientOriginalName();
                $img_path = str_replace(' ', '_', $img_path);
                $awsPath = config('app.domain_name').'/'.'draganddropimage/'.$img_path;
                $imagepath = 'draganddropimage/'.$img_path;
                s3_upload($awsPath, file_get_contents($file), false, $stored_bucket);
                $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                $file_link = $image_file_path.'/'.$imagepath;

                return response()->json([
                    'ApiName' => 'Drag And Drop Image Upload',
                    'status' => true,
                    'message' => 'Image Successfully Upload.',
                    'data' => $file_link,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'Drag And Drop Image Upload',
                    'status' => false,
                    'message' => '',
                    'data' => null,
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'Drag And Drop Image Upload',
                'status' => false,
                'message' => 'S3 bucket setting is not found.',
                'data' => null,
            ], 400);
        }
    }

    public function getEvereeCrmsSetting(): JsonResponse
    {
        $crms = Crms::whereHas('crmSetting')->where('status', 1)->with(['crmSetting'])->find(3);
        // if(!$crms){
        //     return response()->json([
        //         'status' => false,
        //         'data' => $crms,
        //         'message' => 'Your external payment setting is not active'
        //     ],
        //     200);
        // }
        // if($crms){
        //     return response()->json([
        //         'status' => true,
        //         'data' => $crms,
        //         'message' => 'Your external payment setting is active'
        //     ],
        //     200);
        // }

        $companySetting = CompanySetting::where([
            'type' => 'w2',
            'status' => 1,
        ])->first();

        if (! $companySetting) {
            return response()->json([
                'status' => false,
                'data' => [
                    'status' => 0,
                ],
                'message' => 'W2 is not active.',
            ],
                200);
        }
        if ($companySetting) {
            return response()->json([
                'status' => true,
                'data' => $companySetting,
                'message' => 'W2 is active.',
            ],
                200);
        }
    }

    public function timesheetApprovalSetting(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(),
            [
                'scheduling_setting' => 'required',
            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        } else {
            $scheduling_setting = SchedulingApprovalSetting::find(1);
            if (! empty($scheduling_setting)) {
                $scheduling_setting->scheduling_setting = $request->scheduling_setting;
                $message = 'Updated scheduling setting successfully.';
            } else {
                $scheduling_setting = new SchedulingApprovalSetting;
                $scheduling_setting->scheduling_setting = $request->scheduling_setting;
                $message = 'Added scheduling setting successfully.';
            }
            $scheduling_setting->save();
            $data = SchedulingApprovalSetting::find(1);

            return response()->json([
                'ApiName' => 'timesheetApprovalSetting',
                'status' => true,
                'message' => $message,
                'data' => $data,
            ], 200);

        }
    }

    public function getTimesheetApprovalSetting(Request $request): JsonResponse
    {

        $data = SchedulingApprovalSetting::find(1);
        if ($data) {
            return response()->json([
                'ApiName' => 'getTimesheetApprovalSetting',
                'status' => true,
                'message' => 'Success.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'getTimesheetApprovalSetting',
                'status' => true,
                'message' => 'no data found.',
                'data' => [],
            ], 200);
        }
    }

    public function isApprovalPopupVisible($id): JsonResponse
    {
        $data = SchedulingApprovalSetting::find(1);

        if (! $data) {
            return response()->json([
                'ApiName' => 'isApprovalPopupVisible',
                'status' => true,
                'message' => 'No Data Found.',
                'is_show' => false,
            ], 200);
        }

        $user = User::with('positionDetail')->find($id);

        if (! $user || ! $user->positionDetail) {
            return response()->json([
                'ApiName' => 'isApprovalPopupVisible',
                'status' => false,
                'message' => 'User or Position Detail not found.',
                'is_show' => false,
            ], 400);
        }

        $groupId = $user->positionDetail->group_id;
        $status = false;

        if (($user->is_manager == 1 || $user->is_super_admin == 1) && $data->scheduling_setting == 'manual') {
            $status = GroupPermissions::where('group_id', $groupId)
                ->whereIn('role_id', GroupPermissions::distinct()->pluck('role_id'))
                ->whereIn('permissions_id', function ($query) {
                    $query->select('id')
                        ->from('permissions')
                        ->where('name', 'scheduling-timesheet-approval');
                })
                ->exists();
        }

        $transfer = UserTransferHistory::where('user_id', $id)
            ->whereDate('transfer_effective_date', '<=', now())
            ->orderBy('transfer_effective_date', 'desc')
            ->first();

        if (! $transfer) {
            return response()->json([
                'ApiName' => 'isApprovalPopupVisible',
                'status' => false,
                'message' => 'No transfer history found for the user.',
                'is_show' => false,
            ], 400);
        }

        $officeId = $transfer->office_id;

        $schedule = UserSchedule::where('user_id', $id)->first();

        if (! $schedule) {
            return response()->json([
                'ApiName' => 'isApprovalPopupVisible',
                'status' => false,
                'message' => 'No schedule found for the user.',
                'is_show' => false,
            ], 400);
        }

        $scheduleId = $schedule->id;

        $attendanceStatusExists = UserScheduleDetail::where('office_id', $officeId)
            ->where('schedule_id', $scheduleId)
            ->where('attendance_status', 1)
            ->exists();

        if (! $attendanceStatusExists) {
            $status = false;
        }

        return response()->json([
            'ApiName' => 'isApprovalPopupVisible',
            'status' => true,
            'is_show' => $status,
        ], 200);
    }

    public function deduct_any_available_reconciliation(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'deduct_any_available_reconciliation' => 'required|boolean',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $companyProfile = CompanyProfile::first();

        if ($companyProfile) {
            $companyProfile->deduct_any_available_reconciliation_upfront = $request->deduct_any_available_reconciliation;
            $companyProfile->save();

            $page = 'Company Profile';
            $action = 'Updated Deduct Any Available Reconciliation Upfront';
            $description = 'Deduct Any Available Reconciliation Upfront => '.$request->deduct_any_available_reconciliation;
            user_activity_log($page, $action, $description);

            return response()->json([
                'ApiName' => 'deduct-any-available-reconciliation-upfront',
                'status' => true,
                'message' => 'Deduct Any Available Reconciliation Upfront updated successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'deduct-any-available-reconciliation-upfront',
                'status' => false,
                'message' => 'Company profile not found.',
                'data' => [],
            ], 404);
        }
    }

    public function get_deduct_any_available_reconciliation_status(Request $request): JsonResponse
    {
        $companyProfile = CompanyProfile::first();

        if ($companyProfile) {
            $deductStatus = $companyProfile->deduct_any_available_reconciliation_upfront;

            return response()->json([
                'ApiName' => 'get-deduct-any-available-reconciliation-status',
                'status' => true,
                'message' => 'Deduct Any Available Reconciliation Upfront retrieved successfully.',
                'data' => [
                    'deduct_any_available_reconciliation_upfront' => $deductStatus,
                ],
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'get-deduct-any-available-reconciliation-status',
                'status' => false,
                'message' => 'Company profile not found.',
                'data' => [],
            ], 404);
        }
    }

    public function superAdminPositionFrequencyTypeCreate()
    {
        $frequency_type_id = FrequencyType::where('status', 1)->first();
        if ($frequency_type_id) {
            $position = Positions::select('id', 'parent_id', 'department_id')->where('position_name', 'Super Admin')->first();
            if ($position) {
                $positionPayFrequency = PositionPayFrequency::where('position_id', $position->id)->first();
                if (! $positionPayFrequency) {
                    PositionPayFrequency::create([
                        'position_id' => $position->id,
                        'frequency_type_id' => $frequency_type_id->id,
                    ]);
                }
            }
        }
    }
}
