<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\Crms;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Models\AdjustementType;
use App\Models\OneTimePayments;
use App\Models\paystubEmployee;
use App\Core\Traits\EvereeTrait;
use App\Models\UserOverridesLock;
use App\Models\CustomFieldHistory;
use App\Models\UserCommissionLock;
use Illuminate\Support\Facades\DB;
use App\Models\ApprovalsAndRequest;
use App\Models\PayrollOvertimeLock;
use App\Http\Controllers\Controller;
use App\Models\PayrollDeductionLock;
use App\Models\PositionPayFrequency;
use Illuminate\Support\Facades\Auth;
use App\Models\W2PayrollTaxDeduction;
use App\Models\ClawbackSettlementLock;
use App\Models\ApprovalsAndRequestLock;
use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\PayrollHourlySalaryLock;
use Illuminate\Support\Facades\Validator;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationFinalizeHistoryLock;

class OneTimePaymentController extends Controller
{
    use EvereeTrait;

    public function oneTimeHistoryList(Request $request)
    {
        $perPage = 10;
        if (!empty($request->perpage)) {
            $perPage = $request->perpage;
        }
        $paymentHistory = OneTimePayments::select(
            "id",
            "user_id",
            "adjustment_type_id",
            "description",
            "req_no",
            "amount",
            "created_at",
            "everee_payment_status",
            "payment_status",
            "everee_external_id",
            "everee_paymentId",
            "everee_webhook_response"
        )->with("userData", "adjustment")
            ->when($request->filled("search"), function ($q) use ($request) {
                $q->whereHas("userData", function ($query) use ($request) {
                    $query->where("first_name", "LIKE", "%" . $request->search . "%")
                        ->orWhere("last_name", "LIKE", "%" . $request->search . "%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%" . $request->search . "%"]);
                });
            })->when($request->filled("type"), function ($q) use ($request) {
                $q->whereHas("adjustment", function ($query) use ($request) {
                    $query->where("name", $request->type);
                });
            })->when($request->filled("filter"), function ($q) use ($request) {
                [$startDate, $endDate] = getDateFromFilter($request);
                $q->whereBetween("created_at", [$startDate, $endDate]);
            })->when($request->filled("status"), function ($q) use ($request) {
                $status = $request->status;
                if ($status == "pending") {
                    $statusFilter = 0;
                    $q->where("everee_payment_status", $statusFilter);
                } else if ($status == "success") {
                    $statusFilter = 1;
                    $q->where("everee_payment_status", $statusFilter);
                } else if ($status == "failed") {
                    $statusFilter = 2;
                    $q->where("everee_payment_status", $statusFilter);
                } else if ($status == "all_status") {
                    $statusFilter = [0, 1, 2];
                    $q->whereIn("everee_payment_status", $statusFilter);
                }
            })->where("payment_status", 3)->orderBy("id", "desc")->paginate($perPage);

        $transformedCollection = $paymentHistory->getCollection()->map(function ($payment) {
            $userImage = NULL;
            if (isset($payment->userData->image) && $payment->userData->image && $payment->userData->image && $payment->userData->image != "Employee_profile/default-user.png") {
                $userImage = s3_getTempUrl(config("app.domain_name") . "/" . $payment->userData->image);
            }

            $status = "all";
            if ($payment->payment_status == 3 && $payment->everee_payment_status == 0) {
                $status = "pending";
            } else if ($payment->payment_status == 3 && $payment->everee_payment_status == 1) {
                $status = "success";
            } else if ($payment->payment_status == 3 && $payment->everee_payment_status == 2) {
                $status = "failed";
            }

            if ($payment->everee_payment_status == 1) {
                $evereeWebhookMessage = "Payment Success From Everee";
            } else if ($payment->everee_payment_status == 2 && $payment->everee_webhook_response) {
                $evereeWebhookData = json_decode($payment->everee_webhook_response, true);
                if ($evereeWebhookData["paymentStatus"] == "ERROR") {
                    $evereeWebhookMessage = $evereeWebhookData["paymentErrorMessage"];
                } else {
                    $evereeWebhookMessage = $payment->everee_webhook_response;
                }
            } else if ($payment->everee_payment_status == 0) {
                $evereeWebhookMessage = "Waiting for payment status to be updated.";
            }

            return [
                "id" => $payment->id,
                "user_id" => $payment->user_id,
                "adjustment_id" => $payment->adjustment_type_id,
                "payment_type" => $payment?->adjustment?->name,
                "position_id" => isset($payment->userData->position_id) ? $payment->userData->position_id : null,
                "sub_position_id" => isset($payment->userData->sub_position_id) ? $payment->userData->sub_position_id : null,
                "is_super_admin" => isset($payment->userData->is_super_admin) ? $payment->userData->is_super_admin : null,
                "is_manager" => isset($payment->userData->is_manager) ? $payment->userData->is_manager : null,
                "created_at" => $payment->created_at,
                "amount" => $payment->amount,
                "type" => "onetimepayment",
                "req_no" => $payment->req_no,
                "everee_payment_status" => $payment->everee_payment_status,
                "payment_status" => $payment->payment_status,
                "txn_id" => $payment->everee_paymentId,
                "userData" => $payment->userData,
                "user_image" => $userImage,
                "status" => $status,
                "description" => $payment->description,
                "everee_response" => $evereeWebhookMessage
            ];
        });
        $paymentHistory->setCollection($transformedCollection);

        return response()->json([
            "status" => true,
            "ApiName" => "get OneTime Payment History",
            "message" => "Successfully.",
            "data" => $paymentHistory,
            "type" => "onetimepayment"
        ]);
    }

    public function totalOneTimePayment()
    {
        return response()->json([
            "status" => true,
            "ApiName" => "Onetime Payment Total",
            "message" => "Onetime Payment Total Successfully",
            "total_amount" => (float) (round(OneTimePayments::where("payment_status", "3")->sum("amount"), 6))
        ]);
    }

    public function oneTimePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "adjustment_type_id" => "required",
            "amount" => "required|numeric|min:1",
            "user_id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment",
                "error" => $validator->errors()
            ], 400);
        }

        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->where('user_id', $request->user_id)->first();
        if ($payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment",
                "message" => "At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience."
            ], 400);
        }

        try {
            $reqData = null;
            $reqId = $request->req_id;
            $amount = $request->amount;
            $userId = $request->user_id;
            $description = $request->description;
            $adjustmentTypeId = $request->adjustment_type_id;
            if ($reqId) {
                $reqData = ApprovalsAndRequest::where(["id" => $reqId, "status" => "Approved"])->first();
                if (!$reqData) {
                    return response()->json([
                        "status" => false,
                        "ApiName" => "one-time-payment",
                        "message" => "Request Not Found!!"
                    ], 400);
                }

                $userId = $reqData->user_id;
                $adjustmentTypeId = $reqData->adjustment_type_id;
                $amount = $reqData->amount;
                $description = $reqData->description;
            }

            if (in_array($adjustmentTypeId, [5, 7, 8, 9])) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "one-time-payment",
                    "message" => "Invalid adjustment type " . AdjustementType::find($adjustmentTypeId)?->name
                ], 400);
            }

            $user = User::find($userId);
            if ($user && $user->stop_payroll) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "one-time-payment",
                    "message" => $user?->first_name . " " . $user?->last_name . " payroll have been stopped, therefore a one-time payment can't be made."
                ], 400);
            }

            $crm = Crms::where(["id" => 3, "status" => 1])->first();
            if (!$crm) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "one-time-payment",
                    "message" => "You are presently not set up to utilize Sequifi's payment services. Therefore, this payment cannot be processed. Please reach out to your system administrator."
                ], 400);
            }

            $positionPayFrequency = PositionPayFrequency::query()->where(["position_id" => $user->sub_position_id])->first();
            if (!$positionPayFrequency) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "one-time-payment",
                    "message" => "Selected user is not associated with any position or pay frequency, that's why the request can not be processed."
                ], 400);
            }

            if ($reqData) {
                $reqNo = $reqData->req_no;
            } else {
                $check = OneTimePayments::where("adjustment_type_id", $adjustmentTypeId)->count();
                $prefix = oneTimePaymentPrefix($adjustmentTypeId);
                if (!empty($check)) {
                    $reqNo = $prefix . str_pad($check + 1, 6, "0", STR_PAD_LEFT);
                } else {
                    $reqNo = $prefix . str_pad("000000" + 1, 6, "0", STR_PAD_LEFT);
                }
            }

            $externalId = $user->employee_id . "-" . strtotime("now");
            $evereeFields = [
                "usersdata" => [
                    "employee_id" => $user->employee_id,
                    "everee_workerId" => $user->everee_workerId,
                    "id" => $user->id,
                    "worker_type" => $user->worker_type,
                    "onboardProcess" => $user->onboardProcess
                ],
                "everee_external_id" => $externalId,
                "net_pay" => $amount,
                "payable_type" => "one time payment",
                "payable_label" => "one time payment"
            ];

            $externalWorkerId = $user->employee_id;
            $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
            if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                foreach ($payAblesList['items'] as $payAbleValue) {
                    $this->delete_payable($payAbleValue['id'], $user->id);
                }
            }

            if ($adjustmentTypeId == 2) {
                $payable = $this->add_payable($evereeFields, $externalId, "REIMBURSEMENT");
            } else {
                $payable = $this->add_payable($evereeFields, $externalId, "COMMISSION");
            }

            if ((isset($payable["success"]["status"]) && $payable["success"]["status"] == true)) {
                $payableRequest = $this->payable_request($evereeFields, 1);
                if ($reqData) {
                    // SHOULD NOT TRIGGER ApprovalsAndRequestObserver
                    ApprovalsAndRequest::where(["id" => $reqData->id, "status" => "Approved"])->update([
                        "status" => "Accept",
                        "payroll_id" => 0,
                        "pay_period_from" => date("Y-m-d"),
                        "pay_period_to" => date("Y-m-d")
                    ]);
                }

                $oneTimePayment = OneTimePayments::create([
                    "user_id" => $user->id,
                    "req_id" => $reqData?->id,
                    "pay_by" => Auth::user()->id,
                    "req_no" => $reqNo ? $reqNo : null,
                    "everee_external_id" => $externalId,
                    "everee_payment_req_id" => isset($payableRequest["success"]["paymentId"]) ? $payableRequest["success"]["paymentId"] : null,
                    "everee_paymentId" => isset($payableRequest["success"]["everee_payment_id"]) ? $payableRequest["success"]["everee_payment_id"] : null,
                    "adjustment_type_id" => $adjustmentTypeId,
                    "amount" => $amount,
                    "description" => $description,
                    "pay_date" => date("Y-m-d"),
                    "payment_status" => 3,
                    "everee_status" => 1,
                    "everee_json_response" => isset($payableRequest) ? json_encode($payableRequest) : null,
                    "everee_webhook_response" => null,
                    "everee_payment_status" => 0
                ]);

                create_paystub_employee([
                    "one_time_payment_id" => $oneTimePayment->id,
                    "user_id" => $user->id,
                    "pay_period_from" => date("Y-m-d"),
                    "pay_period_to" => date("Y-m-d")
                ], 1);

                return response()->json([
                    "ApiName" => "one-time-payment",
                    "status" => true,
                    "message" => "success!",
                    "everee_response" => $payable["success"]["everee_response"],
                    "data" => $oneTimePayment
                ]);
            } else {
                $payable["fail"]["everee_response"]["errorMessage"] = isset($payable["fail"]["everee_response"]["errorMessage"]) ? $payable["fail"]["everee_response"]["errorMessage"] : (isset($payable["fail"]["everee_response"]["error"]) ? $payable["fail"]["everee_response"]["error"] : "An error occurred during the Everee payment process.");
                return response()->json([
                    "status" => false,
                    "message" => $payable["fail"]["everee_response"]["errorMessage"],
                    "ApiName" => "one-time-payment",
                    "response" => $payable["fail"]["everee_response"]
                ], 400);
            }
        } catch (\Throwable $e) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment",
                "message" => $e->getMessage() . " Line: " . $e->getLine() . " File: " . $e->getFile()
            ], 400);
        }
    }

    public function oneTimePaymentPayStub(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "onetime_id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $oneTimeId = $request->onetime_id;
        $data = OneTimePayments::with("userData", "adjustment", "frequency")->where(["payment_status" => 3, "everee_payment_status" => 1])->where("id", $oneTimeId)->first();
        if (!$data) {
            return response()->json([
                "status" => true,
                "ApiName" => "one-time-payment-pay-stub",
                "message" => "Successfully.",
                "data" => []
            ]);
        }

        $payStubEmployee = paystubEmployee::with("positionDetailTeam")->select(
            "company_name as name",
            "company_address as address",
            "company_website as company_website",
            "company_phone_number as phone_number",
            "company_type as company_type",
            "company_email as company_email",
            "company_business_name",
            "company_mailing_address as mailing_address",
            "company_business_ein as business_ein",
            "company_business_ein as company_business_ein",
            "company_business_phone as business_phone",
            "company_business_address as business_address",
            "company_business_city as business_city",
            "company_business_state as business_state",
            "company_business_zip as business_zip",
            "company_mailing_state as mailing_state",
            "company_mailing_city as mailing_city",
            "company_mailing_zip as mailing_zip",
            "company_time_zone as time_zone",
            "company_business_address_1 as business_address_1",
            "company_business_address_2 as business_address_2",
            "company_business_lat as business_lat",
            "company_business_long as business_long",
            "company_mailing_address_1 as mailing_address_1",
            "company_mailing_address_2 as mailing_address_2",
            "company_mailing_lat as mailing_lat",
            "company_mailing_long as mailing_long",
            "company_business_address_time_zone as business_address_time_zone",
            "company_mailing_address_time_zone as mailing_address_time_zone",
            "company_margin as company_margin",
            "company_country as country",
            "company_logo as logo",
            "company_lat as lat",
            "company_lng as lng",
            "user_first_name as first_name",
            "user_middle_name as middle_name",
            "user_last_name as last_name",
            "user_employee_id as employee_id",
            "user_name_of_bank as name_of_bank",
            "user_social_sequrity_no",
            "user_routing_no",
            "user_account_no",
            "user_type_of_account as type_of_account",
            "user_home_address as home_address",
            "user_zip_code as zip_code",
            "user_email as email",
            "user_work_email as work_email",
            "user_position_id as position_id",
            "user_entity_type as entity_type",
            "user_business_name",
            "user_business_type as business_type",
            "user_business_ein",
        )->where(["one_time_payment_id" => $oneTimeId, "is_onetime_payment" => 1])->first();

        $baseUrl = config("app.aws_s3bucket_url") . "/" . config("app.domain_name");
        $fileLink = $baseUrl . "/" . $payStubEmployee?->logo;
        $companyLogo = $fileLink;
        $companyData = [
            "name" => $payStubEmployee?->name,
            "address" => $payStubEmployee?->address,
            "company_website" => $payStubEmployee?->company_website,
            "phone_number" => $payStubEmployee?->phone_number,
            "company_type" => $payStubEmployee?->company_type,
            "company_email" => $payStubEmployee?->company_email,
            "business_name" => $payStubEmployee?->company_business_name,
            "mailing_address" => $payStubEmployee?->mailing_address,
            "business_ein" => $payStubEmployee?->business_ein,
            "company_business_ein" => $payStubEmployee?->company_business_ein,
            "business_phone" => $payStubEmployee?->business_phone,
            "business_address" => $payStubEmployee?->business_address,
            "business_city" => $payStubEmployee?->business_city,
            "business_state" => $payStubEmployee?->business_state,
            "business_zip" => $payStubEmployee?->business_zip,
            "mailing_state" => $payStubEmployee?->mailing_state,
            "mailing_city" => $payStubEmployee?->mailing_city,
            "mailing_zip" => $payStubEmployee?->mailing_zip,
            "time_zone" => $payStubEmployee?->time_zone,
            "business_address_1" => $payStubEmployee?->business_address_1,
            "business_address_2" => $payStubEmployee?->business_address_2,
            "business_lat" => $payStubEmployee?->business_lat,
            "business_long" => $payStubEmployee?->business_long,
            "mailing_address_1" => $payStubEmployee?->mailing_address_1,
            "mailing_address_2" => $payStubEmployee?->mailing_address_2,
            "mailing_lat" => $payStubEmployee?->mailing_lat,
            "mailing_long" => $payStubEmployee?->mailing_long,
            "business_address_time_zone" => $payStubEmployee?->business_address_time_zone,
            "mailing_address_time_zone" => $payStubEmployee?->mailing_address_time_zone,
            "company_margin" => $payStubEmployee?->company_margin,
            "country" => $payStubEmployee?->country,
            "logo" => $payStubEmployee?->logo,
            "company_logo_s3" => $companyLogo,
            "lat" => $payStubEmployee?->lat,
            "lng" => $payStubEmployee?->lng
        ];

        $result = [];
        $payDate = date("Y-m-d", strtotime($data->pay_date));
        $result["CompanyProfile"] = $companyData;
        $result["pay_stub"]["pay_date"] = $payDate;
        $result["pay_stub"]["net_pay"] = $data->amount;

        $result["pay_stub"]["pay_frequency"] = $data?->frequency ? $data->frequency->name : null;
        $result["pay_stub"]["pay_period_from"] = $data->pay_period_from;
        $result["pay_stub"]["pay_period_to"] = $data->pay_period_to;
        $result["pay_stub"]["period_sale_count"] = 1;
        $result["pay_stub"]["ytd_sale_count"] = OneTimePayments::where(["user_id" => $data->user_id, "payment_status" => "3"])->whereYear("pay_date", date('Y', strtotime($payDate)))->count();

        $userData = [
            "first_name" => $payStubEmployee?->first_name,
            "middle_name" => $payStubEmployee?->middle_name,
            "last_name" => $payStubEmployee?->last_name,
            "employee_id" => $payStubEmployee?->employee_id,
            "name_of_bank" => $payStubEmployee?->name_of_bank,
            "user_social_sequrity_no" => $payStubEmployee?->user_social_sequrity_no,
            "user_routing_no" => $payStubEmployee?->user_routing_no,
            "user_account_no" => $payStubEmployee?->user_account_no,
            "type_of_account" => $payStubEmployee?->type_of_account,
            "home_address" => $payStubEmployee?->home_address,
            "zip_code" => $payStubEmployee?->zip_code,
            "email" => $payStubEmployee?->email,
            "work_email" => $payStubEmployee?->work_email,
            "position_id" => $payStubEmployee?->position_id,
            "entity_type" => $payStubEmployee?->entity_type,
            "business_name" => $payStubEmployee?->user_business_name,
            "business_type" => $payStubEmployee?->business_type,
            "user_business_ein" => $payStubEmployee?->user_business_ein,
            "account_no" => $payStubEmployee?->user_account_no,
            "routing_no" => $payStubEmployee?->user_routing_no,
            "social_sequrity_no" => $payStubEmployee?->user_social_sequrity_no,
            "business_ein" => $payStubEmployee?->user_business_ein,
            "position_detail_team" => $payStubEmployee?->positionDetailTeam
        ];

        $result["employee"] = $userData;
        $result["earnings"] = $this->oneTimePaymentPayStubData($oneTimeId, "earnings");
        $result["deduction"] = $this->oneTimePaymentPayStubData($oneTimeId, "deduction");
        $result["miscellaneous"] = $this->oneTimePaymentPayStubData($oneTimeId, "miscellaneous");
        $result["type"] = "onetimepayment";

        $YTDAmounts = payrollHistoryYTDCalculation($data->user_id, $data->pay_date, $data->pay_date);

        $result["pay_stub"]["ytd_net_pay"] = $YTDAmounts['netPayYTD'];
        if ($result["earnings"]['commission']['is_ytd']) {
            $result["earnings"]['commission']['ytd_total'] = $YTDAmounts['commissionYTD'];
        }
        if ($result["earnings"]['overrides']['is_ytd']) {
            $result["earnings"]['overrides']['ytd_total'] = $YTDAmounts['overrideYTD'];
        }
        if ($result["earnings"]['reconciliation']['is_ytd']) {
            $result["earnings"]['reconciliation']['ytd_total'] = $YTDAmounts['reconciliationYTD'];
        }
        if ($result["earnings"]['additional']['is_ytd']) {
            $result["earnings"]['additional']['ytd_total'] = $YTDAmounts['customFieldYTD'];
        }
        if ($result["earnings"]['wages']['is_ytd']) {
            $result["earnings"]['wages']['ytd_total'] = ($YTDAmounts['salaryYTD'] + $YTDAmounts['overtimeYTD']);
        }
        if ($result["deduction"]['standard_deduction']['is_ytd']) {
            $result["deduction"]['standard_deduction']['ytd_total'] = $YTDAmounts['deductionYTD'];
        }
        if ($result["deduction"]['fica_tax']['is_ytd']) {
            $result["deduction"]['fica_tax']['ytd_total'] = $YTDAmounts['w2DeductionYTD'];
        }
        if ($result["miscellaneous"]['adjustment']['is_ytd']) {
            $result["miscellaneous"]['adjustment']['ytd_total'] = $YTDAmounts['adjustmentYTD'];
        }
        if ($result["miscellaneous"]['reimbursement']['is_ytd']) {
            $result["miscellaneous"]['reimbursement']['ytd_total'] = $YTDAmounts['reimbursementYTD'];
        }

        return response()->json([
            "ApiName" => "one_time_payment_pay_stub_single",
            "status" => true,
            "message" => "Successfully.",
            "data" => $result
        ]);
    }

    protected function oneTimePaymentPayStubData($oneTimeId, $type)
    {
        $oneTimePayroll = OneTimePayments::where(["payment_status" => "3", "id" => $oneTimeId])->first();

        if ($type == "earnings") {
            $totalCommissionAmount = 0;
            if ($oneTimePayroll) {
                $commissionAmount = UserCommissionLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("amount");
                $commissionClawBackAmount = ClawbackSettlementLock::where(["is_onetime_payment" => 1, "type" => "commission", "one_time_payment_id" => $oneTimeId])->sum("clawback_amount");
                $totalCommissionAmount = $commissionAmount - $commissionClawBackAmount;
            }
            $commission = [
                "period_total" => $totalCommissionAmount,
                "is_ytd" => ($commissionAmount || $commissionClawBackAmount) ? 1 : 0
            ];


            $totalOverrideAmount = 0;
            if ($oneTimePayroll) {
                $overrideAmount = UserOverridesLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("amount");
                $overrideClawBackAmount = ClawbackSettlementLock::where(["is_onetime_payment" => 1, "type" => "overrides", "one_time_payment_id" => $oneTimeId])->sum("clawback_amount");
                $totalOverrideAmount = $overrideAmount - $overrideClawBackAmount;
            }
            $overrides = [
                "period_total" => $totalOverrideAmount,
                "is_ytd" => ($overrideAmount || $overrideClawBackAmount) ? 1 : 0
            ];


            $reconAmount = 0;
            if ($oneTimePayroll) {
                $reconAmount = ReconciliationFinalizeHistoryLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("net_amount");
            }
            $reconciliation = [
                "period_total" => $reconAmount,
                "is_ytd" => $reconAmount ? 1 : 0
            ];


            $additionalAmount = 0;
            if ($oneTimePayroll) {
                $additionalAmount = CustomFieldHistory::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("value");
            }
            $additional = [
                "period_total" => $additionalAmount,
                "is_ytd" => $additionalAmount ? 1 : 0
            ];


            $wagesAmount = 0;
            if ($oneTimePayroll) {
                $salaryAmount = PayrollHourlySalaryLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("total");
                $overtimeAmount = PayrollOvertimeLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("total");
                $wagesAmount = $salaryAmount + $overtimeAmount;
            }
            $wages = [
                "period_total" => $wagesAmount,
                "is_ytd" => ($salaryAmount || $overtimeAmount) ? 1 : 0
            ];

            return [
                "commission" => $commission,
                "overrides" => $overrides,
                "reconciliation" => $reconciliation,
                "additional" => $additional,
                "wages" => $wages
            ];
        }


        if ($type == "deduction") {
            $deductionAmount = 0;
            if ($oneTimePayroll) {
                $deductionAmount = PayrollDeductionLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("amount");
            }
            $standardDeduction = [
                "period_total" => $deductionAmount,
                "is_ytd" => $deductionAmount ? 1 : 0
            ];

            $w2Deduction = W2PayrollTaxDeduction::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum('fica_tax');
            $taxDeduction = [
                "period_total" => $w2Deduction,
                "is_ytd" => $w2Deduction ? 1 : 0
            ];
            return [
                "standard_deduction" => $standardDeduction,
                "fica_tax" => $taxDeduction
            ];
        }


        if ($type == "miscellaneous") {
            $adjustmentAmount = 0;
            if ($oneTimePayroll) {
                $oneTimeAmount = OneTimePayments::where(["id" => $oneTimeId, "payment_status" => "3"])->whereNotIn("adjustment_type_id", [2, 5, 7, 8, 9])->sum("amount");
                $positionApprovalAmount = ApprovalsAndRequestLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->whereNotIn("adjustment_type_id", [2, 5, 7, 8, 9])->sum("amount");
                $negativeApprovalAmount = ApprovalsAndRequestLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->whereIn("adjustment_type_id", [5])->sum("amount");
                $payrollAdjustmentAmount = PayrollAdjustmentDetailLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->sum("amount");
                $adjustmentAmount = ($positionApprovalAmount - $negativeApprovalAmount) + $payrollAdjustmentAmount + $oneTimeAmount;
            }
            $adjustment = [
                "period_total" => $adjustmentAmount,
                "is_ytd" => ($oneTimeAmount || $positionApprovalAmount || $negativeApprovalAmount || $payrollAdjustmentAmount) ? 1 : 0
            ];


            $totalReimbursementAmount = 0;
            if ($oneTimePayroll) {
                $oneTimeAmount = OneTimePayments::where(["id" => $oneTimeId, "payment_status" => "3", "adjustment_type_id" => 2])->sum("amount");
                $reimbursementAmount = ApprovalsAndRequestLock::where(["is_onetime_payment" => 1, "adjustment_type_id" => 2, "one_time_payment_id" => $oneTimeId])->sum("amount");
                $totalReimbursementAmount = $oneTimeAmount + $reimbursementAmount;
            }
            $reimbursement = [
                "period_total" => $totalReimbursementAmount,
                "is_ytd" => ($oneTimeAmount || $reimbursementAmount) ? 1 : 0
            ];

            return [
                "adjustment" => $adjustment,
                "reimbursement" => $reimbursement
            ];
        }

        return [];
    }

    public function oneTimePaymentCommissionDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $oneTimeId = $request->id;
        $companyProfile = CompanyProfile::first();
        $userCommissions = UserCommissionLock::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "amount",
            DB::raw("CAST(amount_type AS CHAR) COLLATE utf8mb4_general_ci AS amount_type"),
            "product_code",
            "schema_name",
            "schema_type",
            "redline",
            "redline_type",
            "comp_rate",
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            "commission_amount",
            "commission_type",
            DB::raw("0 as is_claw_back"),
            DB::raw("CAST(amount_type AS CHAR) COLLATE utf8mb4_general_ci AS payroll_type")
        )->with([
            "payrollSaleData:pid,customer_name,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId]);

        $userClawBackCommissions = ClawbackSettlementLock::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "clawback_amount as amount",
            DB::raw("CAST(adders_type AS CHAR) COLLATE utf8mb4_general_ci AS amount_type"),
            "product_code",
            "schema_name",
            "schema_type",
            "redline",
            "redline_type",
            DB::raw("0 as comp_rate"),
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            "clawback_cal_amount as commission_amount",
            "clawback_cal_type as commission_type",
            DB::raw("1 as is_claw_back"),
            DB::raw('"clawback" as payroll_type'),
        )->with([
            "payrollSaleData:pid,customer_name,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->where("type", DB::raw("'commission'"))->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId]);
        $userCommissions = $userCommissions->union($userClawBackCommissions)->get();

        $clawBackPid = [];
        $clawBackType = [];
        $commissionPid = [];
        $commissionType = [];
        foreach ($userCommissions as $userCommission) {
            if ($userCommission->is_claw_back) {
                $clawBackType[] = $userCommission->schema_type;
                $clawBackPid[] = $userCommission->pid;
            } else {
                $commissionType[] = $userCommission->schema_type;
                $commissionPid[] = $userCommission->pid;
            }
        }

        $commissionAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "commission", "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->whereIn("type", $commissionType)->whereIn("adjustment_type", $commissionType)->whereIn("pid", $commissionPid);

        $clawBackAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "commission", "type" => "clawback", "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
        $adjustments = $commissionAdjustments->union($clawBackAdjustments)->get();

        $data = [];
        foreach ($userCommissions as $userCommission) {
            $compRate = 0;
            $repRedline = formatRedline($userCommission->redline, $userCommission->redline_type);
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $userCommission->commission_type !== "per sale") {
                $compRate = number_format($userCommission->comp_rate, 4, ".", "");
            }
            $netEpc = $userCommission?->payrollSaleData?->net_epc;
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && is_numeric($netEpc)) {
                $feePercentage = number_format(((float) $netEpc) * 100, 4, '.', '');
            } else {
                $feePercentage = null;
            }

            if ($userCommission->is_claw_back) {
                $amount = (0 - $userCommission->amount);
            } else {
                $amount = $userCommission->amount;
            }

            $adjustment = adjustmentColumn($userCommission, $adjustments, "commission");
            $row = [
                "id" => $userCommission->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $userCommission->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "customer_name" => $userCommission?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "amount" => $amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "product" => $userCommission->product_code, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "gross_account_value" => $userCommission?->payrollSaleData?->gross_account_value, // PEST, TURF, FIBER, MORTGAGE // WORKER
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "amount_type" => $userCommission->schema_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "trigger_date" => $userCommission?->payrollSaleData?->salesProductMaster, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "rep_redline" => $repRedline, // SOLAR, MORTGAGE // BOTH
                "comp_rate" => $compRate, // MORTGAGE // BOTH
                "operation_type" => $userCommission->is_claw_back ? "clawback" : "commission", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL // BOTH
                "is_mark_paid" => $userCommission->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_next_payroll" => $userCommission->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_stop_payroll" => $userCommission?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_onetime_payment" => $userCommission->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_move_to_recon" => $userCommission->is_move_to_recon, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "kw" => $userCommission?->payrollSaleData?->kw, // SOLAR // WORKER
                "net_epc" => $netEpc, // SOLAR, MORTGAGE // WORKER
                "adders" => $userCommission?->payrollSaleData?->adders, // SOLAR, TURF // WORKER
                "customer_state" => $userCommission->payrollSaleData->customer_state, // SOLAR, TURF, MORTGAGE // WORKER
                "commission_amount" => $userCommission->commission_amount, // PEST, FIBER // BOTH
                "commission_type" => $userCommission->commission_type, // PEST, FIBER // BOTH
            ];
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $row["fee_percentage"] = $feePercentage; // (net_epc * 100, 4dp)
            }
            $data[] = $row;
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-commission-details",
            "data" => $data
        ]);
    }

    public function oneTimePaymentOverrideDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $oneTimeId = $request->id;
        $userOverrides = UserOverridesLock::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "sale_user_id",
            "product_code",
            "type",
            "overrides_amount",
            "overrides_type",
            "amount",
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            DB::raw("0 as is_claw_back"),
            "type as payroll_type",
        )->with([
            "payrollSaleData:pid,customer_name,kw,gross_account_value",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId]);

        $userClawBackOverrides = ClawbackSettlementLock::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "sale_user_id",
            "product_code",
            "adders_type as type",
            "clawback_cal_amount as overrides_amount",
            "clawback_cal_type as overrides_type",
            "clawback_amount as amount",
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            DB::raw("1 as is_claw_back"),
            DB::raw('"clawback" as payroll_type')
        )->with([
            "payrollSaleData:pid,customer_name,kw,gross_account_value",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->where(["type" => "overrides", "clawback_type" => "next payroll", "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId]);
        $userOverrides = $userOverrides->union($userClawBackOverrides)->get();

        $clawBackPid = [];
        $clawBackType = [];
        $overridePid = [];
        $overrideType = [];
        foreach ($userOverrides as $userOverride) {
            if ($userOverride->is_claw_back) {
                $clawBackPid[] = $userOverride->pid;
                $clawBackType[] = $userOverride->type;
            } else {
                $overridePid[] = $userOverride->pid;
                $overrideType[] = $userOverride->type;
            }
        }

        $overrideAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "overrides", "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->whereIn("type", $overrideType)->whereIn("adjustment_type", $overrideType)->whereIn("pid", $overridePid);

        $clawBackAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "overrides", "type" => "clawback", "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
        $adjustments = $overrideAdjustments->union($clawBackAdjustments)->get();

        $data = [];
        foreach ($userOverrides as $userOverride) {
            $overImage = NULL;
            if ($userOverride?->payrollOverUser && $userOverride?->payrollOverUser->image && $userOverride?->payrollOverUser->image != "Employee_profile/default-user.png") {
                $overImage = s3_getTempUrl(config("app.domain_name") . "/" . $userOverride?->payrollOverUser->image);
            }

            if ($userOverride->is_claw_back) {
                $amount = (0 - $userOverride?->amount);
            } else {
                $amount = $userOverride?->amount;
            }

            $adjustment = adjustmentColumn($userOverride, $adjustments, "override");
            $data[] = [
                "id" => $userOverride->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $userOverride->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "customer_name" => $userOverride?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "product" => $userOverride?->product_code, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "product" => $userOverride?->product_code, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_first_name" => $userOverride?->payrollOverUser?->first_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_last_name" => $userOverride?->payrollOverUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_position_id" => $userOverride?->payrollOverUser?->position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_sub_position_id" => $userOverride?->payrollOverUser?->sub_position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_is_super_admin" => $userOverride?->payrollOverUser?->is_super_admin, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_is_manager" => $userOverride?->payrollOverUser?->is_manager, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_image" => $overImage, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "type" => $userOverride?->type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "kw" => $userOverride?->payrollSaleData?->kw, // SOLAR, TURF // WORKER
                "override_amount" => $userOverride?->overrides_amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "override_type" => $userOverride?->overrides_type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "amount" => $amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "adjustment" => $adjustment, // BOTH
                "operation_type" => $userOverride->is_claw_back ? "clawback" : "override", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL // BOTH
                "is_mark_paid" => $userOverride?->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_next_payroll" => $userOverride?->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_stop_payroll" => $userOverride?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_onetime_payment" => $userOverride?->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_move_to_recon" => $userOverride?->is_move_to_recon, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "gross_account_value" => $userOverride?->payrollSaleData?->gross_account_value, // PEST, FIBER, MORTGAGE // WORKER
            ];
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-override-details",
            "data" => $data
        ]);
    }

    public function oneTimePaymentAdjustmentDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $data = [];
        $oneTimeId = $request->id;

        $oneTimeRecords = OneTimePayments::with("userData", "adjustment", "paidBy")->where(["id" => $oneTimeId, "payment_status" => "3"])->whereNotIn("adjustment_type_id", [2, 5, 7, 8, 9])->get();
        foreach ($oneTimeRecords as $oneTimeRecord) {
            $image = null;
            if ($oneTimeRecord?->paidBy?->image && $oneTimeRecord?->paidBy?->image != "Employee_profile/default-user.png") {
                $image = s3_getTempUrl(config("app.domain_name") . "/" . $oneTimeRecord?->paidBy?->image);
            }

            $date = isset($oneTimeRecord->pay_date) ? date("m/d/Y", strtotime($oneTimeRecord->pay_date)) : NULL;
            $data[] = [
                "id" => $oneTimeRecord->id,
                "pid" => NULL,
                "customer_name" => $oneTimeRecord?->userData?->first_name . " " . $oneTimeRecord?->userData?->last_name,
                "payroll_type" => $oneTimeRecord?->adjustment?->name,
                "payroll_modified_date" => $date,
                "amount" => $oneTimeRecord->amount,
                "date" => $date,
                "description" => $oneTimeRecord->description,
                "adjustment" => [
                    "adjustment_amount" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_by" => $oneTimeRecord?->paidBy?->first_name . " " . $oneTimeRecord?->paidBy?->last_name ?? "Super Admin", // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_comment" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_id" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "image" => $image ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "position_id" => $oneTimeRecord?->paidBy?->position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "sub_position_id" => $oneTimeRecord?->paidBy?->sub_position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_manager" => $oneTimeRecord?->paidBy?->is_manager ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_super_admin" => $oneTimeRecord?->paidBy?->is_super_admin ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                ],
                "operation_type" => "onetimepayment",
                "is_mark_paid" => 0,
                "is_next_payroll" => 0,
                "is_stop_payroll" => $oneTimeRecord?->userData?->stop_payroll,
                "is_onetime_payment" => 1,
                "is_move_to_recon" => 0
            ];
        }

        $adjustmentDetails = PayrollAdjustmentDetailLock::with([
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData:pid,customer_name"
        ])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->get();

        foreach ($adjustmentDetails as $adjustmentDetail) {
            $adjustment = adjustmentColumn($adjustmentDetail, $adjustmentDetail, "adjustment");
            $date = isset($adjustmentDetail->updated_at) ? date("m/d/Y", strtotime($adjustmentDetail->updated_at)) : NULL;
            $data[] = [
                "id" => $adjustmentDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $adjustmentDetail->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "customer_name" => $adjustmentDetail?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "payroll_type" => $adjustmentDetail->payroll_type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "payroll_modified_date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "amount" => $adjustmentDetail->amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "description" => $adjustmentDetail->comment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "operation_type" => "adjustment", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL // BOTH
                "is_mark_paid" => $adjustmentDetail->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_next_payroll" => $adjustmentDetail->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_stop_payroll" => $adjustmentDetail?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_onetime_payment" => $adjustmentDetail->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_move_to_recon" => $adjustmentDetail->is_move_to_recon, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
            ];
        }

        $approvalAndRequestDetails = ApprovalsAndRequestLock::with([
            "payrollUser:id,first_name,last_name,stop_payroll",
            "payrollAdjustment",
            "payrollComments",
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
        ])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->where("adjustment_type_id", "!=", 2)->get();

        foreach ($approvalAndRequestDetails as $approvalAndRequestDetail) {
            $adjustment = adjustmentColumn($approvalAndRequestDetail, $approvalAndRequestDetail, "adjustment");
            $date = isset($approvalAndRequestDetail->updated_at) ? date("m/d/Y", strtotime($approvalAndRequestDetail->updated_at)) : NULL;
            $amount = ($approvalAndRequestDetail->adjustment_type_id == 5 && !empty($approvalAndRequestDetail["amount"])) ? -1 * $approvalAndRequestDetail["amount"] : 1 * $approvalAndRequestDetail["amount"];
            $data[] = [
                "id" => $approvalAndRequestDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "pid" => $approvalAndRequestDetail->req_no, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "customer_name" => $approvalAndRequestDetail?->payrollUser?->first_name . " " . $approvalAndRequestDetail?->payrollUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "payroll_type" => $approvalAndRequestDetail?->payrollAdjustment?->name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "payroll_modified_date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "amount" => $amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "description" => isset($approvalAndRequestDetail->description)
                    ? $approvalAndRequestDetail->description
                    : (isset($approvalAndRequestDetail?->payrollComments?->comment) ? strip_tags($approvalAndRequestDetail?->payrollComments?->comment) : null), // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "operation_type" => "request_approval", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $approvalAndRequestDetail->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_next_payroll" => $approvalAndRequestDetail->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_stop_payroll" => $approvalAndRequestDetail?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_onetime_payment" => $approvalAndRequestDetail->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_move_to_recon" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE // CAN NOT MOVE TO RECON
            ];
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-adjustment-details",
            "data" => $data
        ]);
    }

    public function oneTimePaymentReimbursementDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $data = [];
        $oneTimeId = $request->id;

        $oneTimeRecords = OneTimePayments::with("userData", "adjustment", "paidBy")->where(["id" => $oneTimeId, "payment_status" => "3", "adjustment_type_id" => 2])->get();
        foreach ($oneTimeRecords as $oneTimeRecord) {
            $image = null;
            if ($oneTimeRecord?->paidBy?->image && $oneTimeRecord?->paidBy?->image != "Employee_profile/default-user.png") {
                $image = s3_getTempUrl(config("app.domain_name") . "/" . $oneTimeRecord?->paidBy?->image);
            }

            $date = isset($oneTimeRecord->updated_at) ? date("m/d/Y", strtotime($oneTimeRecord->updated_at)) : NULL;
            $data[] = [
                "id" => $oneTimeRecord->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "req_no" => $oneTimeRecord->req_no, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "cost_center" => NULL, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "amount" => $oneTimeRecord->amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "description" => $oneTimeRecord->description, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "adjustment" => [
                    "adjustment_amount" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_by" => $oneTimeRecord?->paidBy?->first_name . " " . $oneTimeRecord?->paidBy?->last_name ?? "Super Admin", // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_comment" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_id" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "image" => $image ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "position_id" => $oneTimeRecord?->paidBy?->position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "sub_position_id" => $oneTimeRecord?->paidBy?->sub_position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_manager" => $oneTimeRecord?->paidBy?->is_manager ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_super_admin" => $oneTimeRecord?->paidBy?->is_super_admin ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                ],
                "operation_type" => "reimbursement", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_next_payroll" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_stop_payroll" => $oneTimeRecord?->userData?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_onetime_payment" => 1, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_move_to_recon" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE // CAN NOT MOVE TO RECON
            ];
        }

        $approvalAndRequestDetails = ApprovalsAndRequestLock::with([
            "payrollCostCenter",
            "payrollUser:id,stop_payroll",
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
        ])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId, "adjustment_type_id" => 2])->get();

        foreach ($approvalAndRequestDetails as $approvalAndRequestDetail) {
            $adjustment = adjustmentColumn($approvalAndRequestDetail, $approvalAndRequestDetail, "adjustment");
            $date = isset($approvalAndRequestDetail->cost_date) ? date("m/d/Y", strtotime($approvalAndRequestDetail->cost_date)) : NULL;

            $data[] = [
                "id" => $approvalAndRequestDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "req_no" => $approvalAndRequestDetail->req_no, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "cost_center" => $approvalAndRequestDetail?->payrollCostCenter?->name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "amount" => $approvalAndRequestDetail->amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "description" => $approvalAndRequestDetail->description, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "operation_type" => "reimbursement", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $approvalAndRequestDetail->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_next_payroll" => $approvalAndRequestDetail->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_stop_payroll" => $approvalAndRequestDetail?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_onetime_payment" => $approvalAndRequestDetail->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_move_to_recon" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE // CAN NOT MOVE TO RECON
            ];
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-reimbursement-details",
            "data" => $data
        ]);
    }

    public function oneTimePaymentDeductionDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $data = [];
        $oneTimeId = $request->id;

        $deductionDetails = PayrollDeductionLock::with([
            "payrollUser:id,stop_payroll",
            "payrollCostCenter"
        ])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->get();

        $deductionAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->get();

        $data = [];
        foreach ($deductionDetails as $deductionDetail) {
            $adjustment = adjustmentColumn($deductionDetail, $deductionAdjustments, "deduction");

            $data[] = [
                "type" => $deductionDetail?->payrollCostCenter?->name,
                "amount" => $deductionDetail->amount,
                "limit" => $deductionDetail->limit,
                "total" => $deductionDetail->total,
                "outstanding" => $deductionDetail->outstanding,
                "cost_center_id" => $deductionDetail->cost_center_id,
                "adjustment" => $adjustment,
                "operation_type" => "deduction", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $deductionDetail->is_mark_paid,
                "is_next_payroll" => $deductionDetail->is_next_payroll,
                "is_stop_payroll" => $deductionDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => $deductionDetail->is_onetime_payment,
                "is_move_to_recon" => $deductionDetail->is_move_to_recon
            ];
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-reimbursement-details",
            "data" => $data
        ]);
    }

    public function oneTimePaymentReconciliationDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $data = [];
        $oneTimeId = $request->id;

        $reconciliationPayrollDetails = ReconciliationFinalizeHistoryLock::where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->get();
        foreach ($reconciliationPayrollDetails as $reconciliationPayrollDetail) {
            $total = ($reconciliationPayrollDetail->paid_commission + $reconciliationPayrollDetail->paid_override + $reconciliationPayrollDetail->adjustments - $reconciliationPayrollDetail->deductions - $reconciliationPayrollDetail->clawback);
            $date = isset($reconciliationPayrollDetail->updated_at) ? date("m/d/Y", strtotime($reconciliationPayrollDetail->updated_at)) : NULL;

            $data[] = [
                "payroll_added_date" => date("m-d-Y h:s:a", strtotime($reconciliationPayrollDetail->updated_at)),
                "start_end" => date("m/d/Y", strtotime($reconciliationPayrollDetail->start_date)) . " to " . date("m/d/Y", strtotime($reconciliationPayrollDetail->end_date)),
                "commission" => $reconciliationPayrollDetail->paid_commission,
                "override" => $reconciliationPayrollDetail->paid_override,
                "clawback" => (-1 * $reconciliationPayrollDetail->clawback),
                "adjustment" => $reconciliationPayrollDetail->adjustments - $reconciliationPayrollDetail->deductions,
                "total" => $total,
                "payout" => $reconciliationPayrollDetail->payout,
                "payroll_modified_date" => $date
            ];
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-reconciliation-details",
            "data" => $data
        ]);
    }

    public function oneTimePaymentAdditionalDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $data = [];
        $oneTimeId = $request->id;

        $customFields = CustomFieldHistory::with(["getColumn", "getApprovedBy"])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->get();
        foreach ($customFields as $customField) {
            $image = null;
            $date = isset($customField->updated_at) ? date("m/d/Y", strtotime($customField->updated_at)) : NULL;
            if ($customField?->getApprovedBy?->image && $customField?->getApprovedBy?->image != "Employee_profile/default-user.png") {
                $image = s3_getTempUrl(config("app.domain_name") . "/" . $customField?->getApprovedBy?->image);
            }

            $data[] =  [
                "id" => $customField->id,
                "custom_field_id" => $customField->column_id,
                "custom_field_name" => $customField?->getColumn?->field_name,
                "amount" => isset($customField->value) ? ($customField->value) : 0,
                "type" => $customField?->getColumn?->field_name ?? "",
                "date" => $date,
                "comment" => $customField->comment,
                "adjustment_by" => $customField->approved_by,
                "adjustment" => [
                    "adjustment_amount" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_by" => $customField?->getApprovedBy?->first_name . " " . $customField?->getApprovedBy?->last_name ?? "Super Admin", // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_comment" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_id" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "image" => $image ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "position_id" => $customField?->getApprovedBy?->position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "sub_position_id" => $customField?->paidBy?->sub_position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_manager" => $customField?->paidBy?->is_manager ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_super_admin" => $customField?->paidBy?->is_super_admin ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                ]
            ];
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-additional-details",
            "data" => $data
        ]);
    }

    public function oneTimePaymentWagesDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $data = [];
        $oneTimeId = $request->id;

        $salaryDetails = PayrollHourlySalaryLock::with(["payrollUser:id,stop_payroll"])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->get();
        $salaryAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId, "type" => "hourlysalary", "payroll_type" => "hourlysalary"])->get();

        foreach ($salaryDetails as $salaryDetail) {
            $adjustment = adjustmentColumn($salaryDetail, $salaryAdjustments, "hourlysalary");
            $date = isset($salaryDetail->updated_at) ? date("m/d/Y", strtotime($salaryDetail->updated_at)) : NULL;

            $data[] = [
                "id" => $salaryDetail->id,
                "date" => $salaryDetail->date ? date("m/d/Y", strtotime($salaryDetail->date)) : NULL,
                "hourly_rate" => $salaryDetail->hourly_rate * 1,
                "salary" => $salaryDetail->salary * 1,
                "regular_hours" => $salaryDetail->regular_hours,
                "total" => $salaryDetail->total * 1,
                "payroll_modified_date" => $date,
                "adjustment" => $adjustment,
                "operation_type" => "salary", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => 0,
                "is_next_payroll" => 0,
                "is_stop_payroll" => $salaryDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => 1
            ];
        }

        $overtimeDetails = PayrollOvertimeLock::with(["payrollUser:id,stop_payroll"])->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId])->get();
        $overtimeAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["is_onetime_payment" => 1, "one_time_payment_id" => $oneTimeId, "type" => "overtime", "payroll_type" => "overtime"])->get();

        foreach ($overtimeDetails as $overtimeDetail) {
            $adjustment = adjustmentColumn($overtimeDetail, $overtimeAdjustments, "overtime");
            $date = isset($overtimeDetail->updated_at) ? date("m/d/Y", strtotime($overtimeDetail->updated_at)) : NULL;

            $data[] = [
                "id" => $overtimeDetail->id,
                "date" => $overtimeDetail->date ? date("m/d/Y", strtotime($overtimeDetail->date)) : NULL,
                "overtime_rate" => $overtimeDetail->overtime_rate * 1,
                "overtime_hour" => $overtimeDetail->overtime_hours,
                "total" => $overtimeDetail->total * 1,
                "payroll_modified_date" => $date,
                "adjustment" => $adjustment,
                "operation_type" => "overtime", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => 0,
                "is_next_payroll" => 0,
                "is_stop_payroll" => $overtimeDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => 1
            ];
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-wages-details",
            "data" => $data
        ]);
    }

    public function paymentRequestAddPayroll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "adjustment_type_id" => "required",
            "user_id" => "required",
            "amount" => "required",
            "pay_periods" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "payment-request-add-payroll",
                "error" => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();
            $payPeriods = json_decode($request->pay_periods, true);
            foreach ($payPeriods as $payPeriod) {
                $workerType = $payPeriod['worker_type'];
                $frequencyTypeId = $payPeriod['frequency_type_id'];
                if ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
                    $payPeriodFrom = $payPeriod['pay_period_to'];
                    $payPeriodTo = $payPeriod['pay_period_to'];
                } else {
                    $payPeriodFrom = $payPeriod['pay_period_from'];
                    $payPeriodTo = $payPeriod['pay_period_to'];
                }
                break;
            }

            $param = [
                "pay_frequency" => $frequencyTypeId,
                "worker_type" => $workerType,
                "pay_period_from" => $payPeriodFrom,
                "pay_period_to" => $payPeriodTo
            ];
            $check = Payroll::applyFrequencyFilter($param, ["status" => 2])->whereIn("finalize_status", [1, 2])->count();
            if ($check) {
                throw new \Exception('Cannot send to payroll. this pay period has been Already Finalize for this employee.');
            }

            $requestApprovalController = (new RequestApprovalController());
            $request->merge(['user_worker_type' => $workerType, 'pay_frequency' => $frequencyTypeId]);
            $response = $requestApprovalController->create($request);
            if ($response->getData()->status == false) {
                throw new \Exception($response->getData()->message);
            }
            $requestId = $response->getData()->data->id;

            $authUser = Auth::user();
            $approvalsAndRequest = ApprovalsAndRequest::find($requestId);
            if ($approvalsAndRequest->adjustment_type_id == 9) {
                approvedTimeAdjustment($approvalsAndRequest, $authUser->id);
            }
            $approvalsAndRequest->status = "Accept";
            $approvalsAndRequest->approved_by = $authUser->id;
            $approvalsAndRequest->pay_period_from = $payPeriodFrom;
            $approvalsAndRequest->pay_period_to = $payPeriodTo;
            $approvalsAndRequest->declined_at = null;
            $approvalsAndRequest->save();

            DB::commit();
            return response()->json([
                "status" => true,
                "message" => "Successfully.",
                "ApiName" => "payment-request-add-payroll"
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                "ApiName" => "payment-request-add-payroll",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    public function paymentRequestPayNow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "adjustment_type_id" => "required",
            "user_id" => "required",
            "amount" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "payment-request-pay-now",
                "error" => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();
            $requestApprovalController = (new RequestApprovalController());
            $response = $requestApprovalController->create($request);
            if ($response->getData()->status == false) {
                throw new \Exception($response->getData()->message);
            }
            $requestId = $response->getData()->data->id;

            $authUser = Auth::user();
            $approvalsAndRequest = ApprovalsAndRequest::find($requestId);
            if ($approvalsAndRequest->adjustment_type_id == 9) {
                approvedTimeAdjustment($approvalsAndRequest, $authUser->id);
            }
            $approvalsAndRequest->status = "Approved";
            $approvalsAndRequest->approved_by = $authUser->id;
            $approvalsAndRequest->declined_at = null;
            $approvalsAndRequest->save();
            $request->merge(['req_id' => $requestId]);
            $oneTimePayment = $this->oneTimePayment($request);
            if ($oneTimePayment->getData()->status == false) {
                throw new \Exception($oneTimePayment->getData()->message);
            }

            DB::commit();
            return response()->json([
                "status" => true,
                "message" => "Successfully.",
                "ApiName" => "payment-request-pay-now"
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                "ApiName" => "payment-request-pay-now",
                "error" => $e->getMessage()
            ], 400);
        }
    }
}
