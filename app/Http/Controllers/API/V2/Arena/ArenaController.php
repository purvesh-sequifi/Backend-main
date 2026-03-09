<?php

namespace App\Http\Controllers\API\V2\Arena;

use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\Subscriptions;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // use model

class ArenaController extends Controller
{
    public function crmplanaddupgrade(Request $request): JsonResponse
    {
        try {
            // Validation
            $Validator = Validator::make($request->all(), [
                'crm_id' => 'required',
                'plan_name' => 'required',
                'amount_per_user' => 'required',
            ]);

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $crm_id = $request->crm_id;
            $updatedata = array_filter([
                'plan_name' => $request->plan_name ?? '',
                'amount_per_job' => $request->amount_per_user ?? '',
                'status' => 1,
            ]);

            if (Crms::where('id', $crm_id)->exists()) {
                CrmSetting::updateOrCreate(
                    ['crm_id' => $crm_id],
                    $updatedata
                );
                CrmSetting::where('crm_id', $crm_id)->update($updatedata);
                Arena::addplanandsubscription($crm_id);
                $data = CrmSetting::where('crm_id', $crm_id)->get();

                return response()->json([
                    'ApiName' => 'planaddupgrade',
                    'msg' => 'Successfully Add Updated',
                    'status' => true,
                    'data' => $data,
                ], 200);
            }

            return response()->json([
                'ApiName' => 'planaddupgrade',
                'msg' => 'Crm not exists.',
                'status' => false,
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'crmplanaddupgrade',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function activedeactivecrm(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make($request->all(), [
                'crm_id' => 'required',
                'status' => 'required',
            ]);

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $crm_id = $request->crm_id;
            $crm_status = $request->status;

            // Check if CRM exists
            if (Crms::where('id', $crm_id)->exists()) {
                Crms::where('id', $crm_id)->update(['status' => $crm_status]);

                return response()->json([
                    'ApiName' => 'activedeactivecrm',
                    'msg' => 'Successfully Updated',
                    'status' => true,
                ], 200);
            }

            return response()->json([
                'ApiName' => 'activedeactivecrm',
                'msg' => 'Crm not exists.',
                'status' => false,
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'activedeactivecrm',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getplanactive(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make($request->all(), [
                'crm_id' => 'required',
            ]);

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $crm_id = $request->crm_id;
            $crmExists = Crms::where('id', $crm_id)->first();

            if ($crmExists) {
                $totalCount = Subscriptions::where('plan_id', 7)->sum('total_pid');
                $data = CrmSetting::where('crm_id', $crm_id)
                    ->where('plan_name', '!=', '')
                    ->first();

                // Ensure $data is an array and not a model object
                if ($data) {
                    $data = $data->toArray();  // Convert model object to array
                    $data['date'] = \Carbon\Carbon::parse($crmExists->created_at)->format('m/d/Y | H:i'); // Properly format the date
                    $data['count'] = $totalCount;  // Count of subscriptions

                    return response()->json([
                        'ApiName' => 'getplanactive',
                        'msg' => '',
                        'status' => true,
                        'data' => $data,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'getplanactive',
                        'msg' => 'No CRM Plan exist.',
                        'status' => false,
                        'data' => [],
                    ], 404);
                }
            }

            return response()->json([
                'ApiName' => 'getplanactive',
                'msg' => 'Crm not exists.',
                'status' => false,
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'getplanactive',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public static function get_userdata(Request $request): JsonResponse
    {
        $subscription_id = isset($request->subscription_id) ? $request->subscription_id : 0;
        $search = isset($request->search) ? $request->search : '';
        $plan_id = isset($request->plan_id) ? $request->plan_id : 0;

        $unique_pid_rack_price = $unique_pid_discount_price = $m2_rack_price = $m2_discount_price = $pid_count = 0;
        $total_price = $sales_tax_amount = $sales_tax_per = $grand_total = $pid_kw_sum = 0;
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $pricebilled = [];
        $getdata = [];
        $status_code = 400;
        $status = false;
        $message = 'subscription not found!';
        $subscription = Subscriptions::with('plans', 'billingType')->where('id', $subscription_id)->first();
        if (! empty($subscription) && $subscription != null) {
            $status_code = 200;
            $status = true;
            $message = 'Data get!';
            $start_date = $subscription->start_date;
            $end_date = $subscription->end_date;
            $allusers = User::select('id', 'employee_id', 'first_name', 'last_name', 'worker_type', 'period_of_agreement_start_date', 'created_at')->whereBetween('created_at', [$start_date, $end_date]);
            // ->whereYear('created_at', $currentYear)
            if ($search != '') {
                $allusers->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            }

            $allusers = $allusers->get();

            $pid_count = $allusers->count();

            // calculation
            $unique_pid_rack_price = isset($subscription->plans) ? $subscription->plans->unique_pid_rack_price : 0;
            $unique_pid_discount_price = isset($subscription->plans) ? $subscription->plans->unique_pid_discount_price : 0;

            $pid_total_amount = ($unique_pid_rack_price * $pid_count);
            $pid_total_amount = ($pid_total_amount - ($pid_count * $unique_pid_discount_price));
            $total_price = $pid_total_amount;
            // echo $total_price;die();
            $sales_tax_per = isset($subscription->sales_tax_per) && $subscription->sales_tax_per > 0 ? $subscription->sales_tax_per : 7.25;
            $sales_tax_amount = (($total_price * $sales_tax_per) / 100);
            $grand_total = ($total_price + $sales_tax_amount);

            foreach ($allusers as $data) {
                $billed_price = $unique_pid_rack_price;
                $price = $unique_pid_rack_price - $unique_pid_discount_price;
                $getdata[] = [
                    'id' => $data['id'],
                    'user_id' => $data['employee_id'],
                    'username' => $data['first_name'].' '.$data['last_name'],
                    'type' => $data['worker_type'],
                    'approval_date' => $data['period_of_agreement_start_date'],
                    'created_at' => $data['created_at'],
                    'price' => $price,
                    'billed_price' => round($billed_price, 2),
                ];
            }
        }
        $getdata = paginate($getdata, $perpage);

        return response()->json([
            'ApiName' => 'get_pidsdata',
            'status' => $status,
            'message' => $message,
            'pid_total' => $pid_count,
            'total_price' => round($total_price, 2),
            'sales_tax_amount' => round($sales_tax_amount, 2),
            'sales_tax_per' => $sales_tax_per,
            'total_price_without_tex' => round($total_price, 2),
            'total_price_with_tex' => round($grand_total, 2),
            'unique_pid_rack_price' => $unique_pid_rack_price,
            'unique_pid_discount_price' => $unique_pid_discount_price,
            'data' => $getdata,
        ], $status_code);
    }

    public function office_and_position_wise_user_list(Request $request)
    {
        $ApiName = 'office_and_position_wise_user_list_for_arena';
        $status_code = 200;
        $status = true;
        $message = 'User list based on Office and Position';
        $user_data = [];

        try {
            $office_id = isset($request->office_id) ? $request->office_id : 'All';
            $position_id = isset($request->position_id) ? $request->position_id : 'All';
            // return [$office_id, $position_id];
            $user_data_query = User::where('dismiss', 0)
                ->whereNotNull('office_id')
                ->select(
                    'id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'sub_position_id',
                    'office_id',
                    'image',
                    'position_id',
                    'sub_position_id',
                    'is_super_admin',
                    'is_manager'
                )
                ->with(['office' => function ($query) {
                    $query->select('id', 'office_name', 'type');
                }])
                ->with(['positionDetail' => function ($query) {
                    $query->select('id', 'position_name');
                }])
                ->orderBy('office_id', 'ASC');

            /*if((int)$position_id > 0 ){
                $user_data_query = $user_data_query->where('sub_position_id' , $position_id);
            }

            if((int)$office_id > 0){
                $user_data_query = $user_data_query->where('office_id' , $office_id);
            }*/
            if ($position_id != 'All') {
                $user_data_query = $user_data_query->whereIn('sub_position_id', $position_id);
            }

            if ($office_id != 'All') {
                $user_data_query = $user_data_query->whereIn('office_id', $office_id);
            }

            $user_data = $user_data_query->get();
            $user_data->map(function ($user) {
                // Check if image exists and generate a temporary URL
                if ($user->image) {
                    $user->image = s3_getTempUrl(config('app.domain_name').'/'.$user->image);
                }

                return $user;
            });

        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }

        return response()->json([
            'ApiName' => $ApiName,
            'status' => $status,
            'message' => $message,
            'user_count' => count($user_data),
            'data' => $user_data,
        ], $status_code);
    }
}
