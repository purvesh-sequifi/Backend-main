<?php

namespace App\Http\Controllers\API\Setting;

use App\Core\Traits\CheckCompanySettingTrait;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\Payroll;
use App\Models\PayrollHistory;
use App\Models\PayrollSsetup;
use App\Models\Positions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetupPayrollController extends Controller
{
    private $compensationPlan;

    private $incompleteAccountAlert;

    private $marketingDealAlert;

    use CheckCompanySettingTrait;

    public function __construct(Positions $position) {}

    /**
     * Display a listing of the resource.
     */
    public function getPayrollSetting(): JsonResponse
    {
        $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
        $email_setting_type = 0;

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $setting], 200);
    }

    public function addPayrollSetting(Request $request): JsonResponse
    {
        $payrollfiledValue = $request->payrollfiled;
        foreach ($payrollfiledValue as $key => $data) {
            $value = (object) $data;
            $id = $value->id;
            $update_data = [
                'field_name' => $value->field_name,
                'worked_type' => $value->worked_type ?? '',
                'payment_type' => $value->payment_type ?? '',
            ];
            if ($id != '') {
                // $insert =  PayrollSsetup::where('id',$id)->update($update_data);
                $payrollSetup = PayrollSsetup::where('id', $id)->first(); // added for activity log
                $payrollSetup->field_name = $value->field_name;
                $payrollSetup->worked_type = $value->worked_type ?? '';
                $payrollSetup->payment_type = $value->payment_type ?? '';
                $response_status = $payrollSetup->save();
            } else {
                $insert = PayrollSsetup::create($update_data);
            }

        }

        return response()->json([
            'ApiName' => 'Add_payroll_setup_setting',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
        ], 200);

    }

    public function updatePayrollSetting(Request $request): JsonResponse
    {

        $userId = Auth::user();
        $id = $request->id;
        $data = PayrollSsetup::where('id', $id)->first();
        if ($data) {

            $update = PayrollSsetup::where('id', $id)->first();
            $update->field_name = $request['field_name'];
            $update->worked_type = $request['worked_type'];
            $update->payment_type = $request['payment_type'];

            $update->save();
            if ($update) {
                $description = $userId->first_name.' '.$userId->last_name.' changed domain status for '.$data->domain_name.' is disabled';

                $data1 = [
                    'updated_by_id' => $userId['id'],
                    'descriptions' => $description,
                ];

                $status_code = 200;
                $status = true;
                $message = 'Payroll Setup settings updated successfully.';

            }

        } else {
            $status_code = 403;
            $status = false;
            $message = 'Payroll Setup settings not found.';
        }

        return response()->json([
            'ApiName' => 'update_ayroll_setup_setting',
            'status' => $status,
            'message' => $message,
        ], $status_code);
    }

    public function deletePayrollSetting($id): JsonResponse
    {

        if (! null == $id) {

            $data = PayrollSsetup::where('id', $id)->first();
            if ($data == null) {
                return response()->json(['status' => true, 'message' => 'Payroll Setting  not find.'], 200);
            } else {
                // Check It is used in Paystub
                $customeFieldPayrollIds = CustomField::where('column_id', $id)->pluck('payroll_id')->toArray();
                $customeFieldIds = CustomField::where('column_id', $id)->pluck('id')->toArray();

                // Check Payroll is Finalised
                $message = '';
                $count = 0;

                $checkPayrolls = Payroll::whereIn('id', $customeFieldPayrollIds)->where('finalize_status', '!=', 0)->count();
                if ($checkPayrolls > 0) {
                    $message = 'Unable to delete, this Field available in finanlized!';
                    $count = $checkPayrolls;
                }

                // Check Payroll is Execute
                $checkPayrollHistories = PayrollHistory::whereIn('payroll_id', $customeFieldPayrollIds)->count();
                if ($checkPayrollHistories > 0) {
                    $message = 'Unable to delete, this Field available in execution!';
                    $count = $checkPayrollHistories;
                }

                if ($count > 0) {
                    return response()->json([
                        'ApiName' => 'delete_payroll_setting',
                        'status' => false,
                        'message' => 'Unable to delete, this Field available in finanlized!',
                        'no_of_records' => $count,
                    ], 400);
                }

                $id = PayrollSsetup::find($id);
                $userId = auth::user();
                if ($id) {
                    $page = 'Setting';
                    $action = 'Payroll Setting Deleted';
                    $description = 'status =>'.$data->status;
                    user_activity_log($page, $action, $description);
                    $description = $userId->first_name.' '.$userId->last_name.' is deleted Payroll Seetting '.$data->field_name;

                    CustomField::whereIn('id', $customeFieldIds)->delete();
                }

                $id->delete();

                return response()->json([
                    'ApiName' => 'delete_payroll_setting',
                    'status' => true,
                    'message' => 'delete Successfully.',
                    'data' => $id,
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'delete_payroll_setting',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    // code by nikhil end
}
