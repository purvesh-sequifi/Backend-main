<?php

namespace App\Http\Controllers\API\Payroll;

use App\Exports\ExportPayroll\PayrollCustomExport;
use App\Http\Controllers\Controller;
use App\Imports\PayrollCustomImport;
use App\Models\AdditionalPayFrequency;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\DailyPayFrequency;
use App\Models\FrequencyType;
use App\Models\MonthlyPayFrequency;
use App\Models\Payroll;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertimeLock;
use App\Models\PayrollSsetup;
use App\Models\User;
use App\Models\UserCommissionLock;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverridesLock;
use App\Models\W2PayrollTaxDeduction;
use App\Models\WeeklyPayFrequency;
use App\Traits\EmailNotificationTrait;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CustomFiledController extends Controller
{
    use EmailNotificationTrait;

    public function update_payroll_custom_filed(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
                'column_id' => 'required',
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $user_id = $request->user_id;
        $payroll_id = $request->payroll_id;
        $column_id = $request->column_id;
        $value = $request->value;
        $comment = $request->comment;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = Payroll::where(['user_id' => $user_id, 'id' => $payroll_id])->first();
        if ($Payroll) {
            $dataPayroll = CustomField::where(['user_id' => $user_id, 'payroll_id' => $payroll_id, 'column_id' => $column_id])->first();
            $data = [
                'user_id' => $user_id,
                'payroll_id' => $payroll_id,
                'column_id' => $column_id,
                'value' => $value,
                'comment' => $comment ?? null,
                'approved_by' => Auth::id(),
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ];
            if ($dataPayroll) {
                CustomField::where('id', $dataPayroll->id)->update($data);
            } else {
                CustomField::create($data);
            }

            return response()->json([
                'ApiName' => 'payroll_custom filed',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'payroll_custom filed',
                'status' => true,
                'message' => 'Payroll not found.',
            ], 400);
        }
    }

    public function export_custom(Request $request)
    {

        $data = [];
        $payroll_total = 0;
        $workerType = isset($request->worker_type) ? $request->worker_type : '1099';
        $positions = $request->input('position_filter');
        $netPay = $request->input('netpay_filter');
        $commission = $request->input('commission_filter');
        $pay_frequency = $request->input('pay_frequency');
        if (! empty($request->input('perpage'))) {
            $perpage = $request->input('perpage');
        } else {
            $perpage = 10;
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $all_paid = false;

        $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';

        $users = User::orderBy('id', 'asc');

        if ($request->has('search') && ! empty($request->input('search'))) {
            $search_full_name = $request->input('search');
            $users->where(function ($query) use ($search_full_name) {
                return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'LIKE', '%'.$search_full_name.'%')
                    ->orWhere('first_name', 'LIKE', '%'.$search_full_name.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search_full_name.'%');
            });
        }

        $userArray = $users->where('worker_type', $workerType)->pluck('id')->toArray();

        $paydata = Payroll::with('usersdata', 'payrollstatus', 'userDeduction', 'reconciliationInfo')
            ->with(['positionCommissionDeduction' => function ($q) {
                $q->join('cost_centers', 'cost_centers.id', '=', 'position_commission_deductions.cost_center_id');
            }])->whereIn('user_id', $userArray)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'finalize_status' => 0]);
        // ->where(['is_mark_paid'=>0,'finalize_status', '!='=>2]);

        if ($positions && $positions != '') {
            $paydata->where('position_id', $positions);
        }

        if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
            $paydata->where('net_pay', '<', 0);
        }

        if ($commission && $commission != '') {
            $paydata->where('commission', $commission);
        }
        // $paydata = $paydata->orderBy(
        //     User::select('first_name')
        //         ->whereColumn('id', 'payrolls.user_id')
        //         ->orderBy('first_name', 'asc')
        //         ->limit(1),
        //     'ASC'
        // );
        $paydata = $paydata->get();

        // Get Other Users
        $paydataUserIds = $paydata->pluck('user_id')->toArray();
        $users = User::whereNotIn('id', $paydataUserIds)->where('dismiss', 0)->whereIn('id', $userArray)->orderBy('first_name', 'asc')->get();
        // $users = User::whereNotIn('id', $paydataUserIds)->where('dismiss', 0)->orderBy('first_name', 'asc')->get();
        // return $users->toArray();
        // Combine paydata and users
        $combinedData = collect($paydata)->concat($users)->sortBy(function ($item) {
            // Sort by first_name (handling both user objects and payroll objects)
            if (isset($item->usersdata)) {
                return $item->usersdata->first_name;
            } else {
                return $item->first_name;
            }
        });
        // return $combinedData;
        $setting = PayrollSsetup::where('worked_type', 'LIKE', '%'.$workerTypeValue.'%')->orderBy('id', 'Asc')->get()->toArray();
        $setting = array_column($setting, 'field_name');
        $column = [
            'Rep_name',
            'Rep_id',
        ];
        foreach ($setting as $value) {
            array_push($column, $value);
            array_push($column, 'comment');

        }
        // array_push($column,'Start date');
        // array_push($column,'End date');
        $custom_filed = [];
        $count = count($column);
        $excelRow = 0;
        foreach ($paydata as $key => $value) {
            $PayrollSsetup = PayrollSsetup::where('worked_type', 'LIKE', '%'.$workerTypeValue.'%')->orderBy('id', 'Asc')->get();
            $custom_filed[$excelRow][] = ucfirst($value->usersdata->first_name.' '.$value->usersdata->last_name);
            // $custom_filed[$excelRow][]=    $value->id;
            // UserId
            $custom_filed[$excelRow][] = $value->user_id ?? 0;

            foreach ($PayrollSsetup as $filed) {
                $setting = CustomField::with('PayrollSsetup')->where(['user_id' => $value->user_id, 'payroll_id' => $value->id, 'column_id' => $filed->id])->first();
                if ($setting == null) {
                    $setting = new CustomField;
                    $setting->user_id = $value->user_id;
                    $setting->payroll_id = $value->id;
                    $setting->column_id = $filed->id;
                    $setting->user_id = $value->user_id;
                    $setting->value = 0;
                    $setting->pay_period_from = isset($start_date) ? $start_date : null;
                    $setting->pay_period_to = isset($end_date) ? $end_date : null;
                    $setting->save();
                } else {
                    $setting->pay_period_from = isset($start_date) ? $start_date : null;
                    $setting->pay_period_to = isset($end_date) ? $end_date : null;
                    $setting->save();
                }
                $custom_filed[$excelRow][] = isset($setting->value) ? $setting->value : '';
                $custom_filed[$excelRow][] = isset($setting->comment) ? $setting->comment : '';

            }
            // $custom_filed[$excelRow][]= isset($start_date)?$start_date:'';
            // $custom_filed[$excelRow][]= isset($end_date)?$end_date:'';
            $excelRow++;
        }

        // Other Users
        if (count($users) > 0) {
            foreach ($users as $key => $user) {
                $first_name = ucfirst($user->first_name) ?? '';
                $last_name = ucfirst($user->last_name) ?? '';

                $custom_filed[$excelRow][] = $first_name.' '.$last_name;
                $custom_filed[$excelRow][] = $user->id;

                $PayrollSsetup = PayrollSsetup::where('worked_type', 'LIKE', '%'.$workerTypeValue.'%')->orderBy('id', 'Asc')->get();
                foreach ($PayrollSsetup as $filed) {
                    $custom_filed[$excelRow][] = '0';
                    $custom_filed[$excelRow][] = '';
                }

                $excelRow++;
            }
        }
        $sortedData = collect($custom_filed)->sortBy(function ($item) {
            return $item[0]; // Sorting by the 0th index (name)
        })->values()->toArray(); // Reset array keys after sorting

        $title = 'Payroll Custom Field Records';
        $filename = 'Payroll_Date_'.$request->start_date.'_to_'.$request->end_date.'_Records.xlsx';
        Excel::store(new PayrollCustomExport($column, $sortedData, $title), 'exports/'.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);
        $url = getStoragePath('exports/'.$filename);

        // $url = getExportBaseUrl().'storage/exports/' . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);

    }

    public function getPayrollSetting(): JsonResponse
    {
        $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
        $email_setting_type = 0;

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $setting], 200);
    }

    public function import_custom_old(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        // Get the uploaded file
        $file = $request->file('file');
        $extension = $request->file('file')->extension();
        $title = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $file_name = $title.'.'.$extension;

        Excel::import(new PayrollCustomImport, $file);

        return response()->json([
            'ApiName' => 'import_excel_api',
            'status' => true,
            'message' => 'Upload Sheet Successfully',
            // 'data'    => $data,
        ], 200);
    }

    public function import_custom(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
            'start_date' => 'required',
            'end_date' => 'required',
            // 'worker_type' => 'required',
        ]);

        // Get the uploaded file
        $file = $request->file('file');
        $extension = $request->file('file')->extension();
        $title = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $filePath = $title.'.'.$extension;
        $workerType = isset($request->worker_type) ? $request->worker_type : '1099';

        // Check Records

        $rows = Excel::toArray([], $file);
        // Accessing the first sheet (index 0)
        $sheet = $rows[0];
        // Loop through rows
        $validateImportPayroll = $this->validateImportPayrollData($sheet, $request->all(), $title);
        if ($validateImportPayroll['not_available'] > 0) {
            return response()->json([
                'ApiName' => 'import_excel_api',
                'status' => false,
                'message' => 'Unable to import, due to some data not match with payroll',
            ], 400);
        } elseif ($validateImportPayroll['error'] == true) {
            return response()->json([
                'ApiName' => 'import_excel_api',
                'status' => false,
                'message' => $validateImportPayroll['error_message'],
            ], 400);
        }
        // dd($sheet);
        $saveOrUpdateCustomRecords = $this->saveOrUpdateCustomField($sheet, $request->all());
        // dd("working on saveOrUpdateCustomRecords()", $saveOrUpdateCustomRecords);
        // Excel::import(new PayrollCustomImport, $file,$request);
        if ($saveOrUpdateCustomRecords['status'] == false) {
            return response()->json($saveOrUpdateCustomRecords, 400);
        } elseif (empty($saveOrUpdateCustomRecords['data']['success']) || count($saveOrUpdateCustomRecords['data']['success']) <= 0) {
            // Return a specific error response indicating data mismatch with payroll
            return response()->json([
                'ApiName' => 'import_excel_api',
                'status' => false,
                'message' => 'Unable to import, due to data not match with payroll',
            ], 400);
        }

        return response()->json([
            'ApiName' => 'import_excel_api',
            'status' => true,
            'message' => 'Upload Sheet Successfully',
            'data' => $saveOrUpdateCustomRecords,
        ], 200);
    }

    public function getImportFileDate($title)
    {
        $explodeTitle = explode('_', $title);
        $dates = [];
        if (isset($explodeTitle[2]) && ! empty($explodeTitle[2])) {
            $dates['file_start_date'] = $explodeTitle[2];
        }

        if (isset($explodeTitle[4]) && ! empty($explodeTitle[4])) {
            $dates['file_end_date'] = $explodeTitle[4];
        }

        return $dates;
    }

    public function validateImportPayrollData($sheet, $request, $file_name)
    {
        // Loop through rows
        $data = [
            'available' => 0,
            'not_available' => 0,
            'error' => false,
            'error_message' => '',
        ];
        $start_date = $request['start_date'];
        $end_date = $request['end_date'];
        $workerType = isset($request['worker_type']) ? $request['worker_type'] : '1099';
        $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
        $availableClount = $notAvailableClount = 0;

        $getImportFileDate = $this->getImportFileDate($file_name);
        $fileStartDate = $getImportFileDate['file_start_date'] ?? null;
        $fileEndDate = $getImportFileDate['file_end_date'] ?? null;

        if ($fileStartDate == null || $fileEndDate == null) {
            $data['error'] = true;
            $data['error_message'] = 'Payroll Start and End date not found!';

            return $data;
        } elseif ($fileStartDate != $start_date || $fileEndDate != $end_date) {
            $data['error'] = true;
            $data['error_message'] = 'Payroll Start and End date not match!';

            return $data;
        }

        $column_ids = null;
        foreach ($sheet as $rowIndex => $row) {
            // Output row index
            if ($rowIndex == 0) {
                $result = [];

                for ($i = 2; $i < count($row); $i++) {
                    // We removed the condition "$i % 2 == 0" to allow processing of ALL columns starting from index 2.
                    // Odd columns were not coming earlier because of this condition.
                    if ($row[$i] != 'comment' && $row[$i] != 'Start date' && $row[$i] != 'End date') {
                        if (is_string($row[$i])) {
                            $result[$i] = $row[$i];
                        }
                    }
                }

                $columnId = [];
                foreach ($result as $key => $value) {
                    if (is_string($value)) {
                        // $payrollSsetup = PayrollSsetup::select(['id'])->where('field_name' , 'like', '%' .$value. '%')->first();
                        $payrollSsetup = PayrollSsetup::select(['id'])->where('field_name', 'like', '%'.$value.'%')->where('worked_type', 'LIKE', '%'.$workerTypeValue.'%')->first();
                        if (empty($columnId[$key]) && $payrollSsetup != null) {
                            $columnId[$key] = $payrollSsetup->id;
                        }
                    }
                }
                $column_ids = $columnId;
                if (count($column_ids) == 0) {
                    $data['error'] = true;
                    $data['error_message'] = 'Custom Fields not available!';

                    return $data;
                }
            }
        }

        return $data;
    }

    public function saveOrUpdateCustomField($sheet, $request)
    {
        try {
            DB::beginTransaction();
            $start_date = $request['start_date'];
            $end_date = $request['end_date'];
            $workerType = isset($request['worker_type']) ? $request['worker_type'] : '1099';
            $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';

            $availableClount = $notAvailableClount = 0;
            $column_ids = null;
            $successPayrollIds = $failedPayrollIds = [];
            $userIdArray = [];
            foreach ($sheet as $rowIndex => $row) {
                // Output row index
                if ($rowIndex == 0) {
                    $result = [];

                    for ($i = 2; $i < count($row); $i++) {
                        // We removed the condition "$i % 2 == 0" to allow processing of ALL columns starting from index 2.
                        // Odd columns were not coming earlier because of this condition.
                        if ($row[$i] != 'comment' && $row[$i] != 'Start date' && $row[$i] != 'End date') {
                            if (is_string($row[$i])) {
                                $result[$i] = $row[$i];
                            }
                        }
                    }

                    $columnId = null;
                    foreach ($result as $key => $value) {
                        if (is_string($value)) {
                            $payrollSsetup = PayrollSsetup::select(['id'])->where('field_name', 'like', '%'.$value.'%')->first();
                            // $payrollSsetup = PayrollSsetup::select(['id'])->where('field_name' , 'like', '%' .$value. '%')->where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->first();
                            if (empty($columnId[$key]) && $payrollSsetup != null) {
                                $columnId[$key] = $payrollSsetup->id;
                            }
                        }
                    }
                    $column_ids = $columnId;
                } else {
                    $columnRefId = $row[1] ?? 0;
                    $userData = User::select('id', 'first_name', 'last_name', 'email')->where(['id' => $columnRefId])->first();
                    if (empty($userData)) {
                        $userIdArray[] = $columnRefId;

                        continue;
                    }
                    $payroll = Payroll::where(['user_id' => $columnRefId, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'finalize_status' => 0, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->first();
                    if ($payroll) {
                        // Loop through columns in the row
                        // dd($row, $column_id);
                        $t = 1;

                        // dd($row[1], $start_date, $end_date, $payroll);
                        for ($i = 0; $i < count($row); $i += 2) {
                            if ($i >= 2) {
                                // Check Custom Field Value
                                // if($row[$i]){
                                if (isset($column_ids[$i])) {
                                    $customField = CustomField::where(['payroll_id' => $payroll->id, 'column_id' => $column_ids[$i], 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->first();
                                    if ($customField == null) {
                                        $customField = new CustomField;
                                    }

                                    $customField->payroll_id = $payroll->id;
                                    $customField->user_id = $payroll->user_id;
                                    $customField->column_id = $column_ids[$i];
                                    $customField->value = (float) $row[$i];
                                    $customField->comment = isset($row[$i + 1]) ? $row[$i + 1] : '';
                                    $customField->pay_period_from = $payroll->pay_period_from;
                                    $customField->pay_period_to = $payroll->pay_period_to;
                                    $customField->approved_by = Auth::id();
                                    // dd($customField);
                                    if ($customField->save()) {
                                        $successPayrollIds[] = $payroll->id;
                                    } else {
                                        $failedPayrollIds[] = $payroll->id;
                                    }

                                }
                                // }
                            }
                            $t++;
                        }
                    } else {
                        // check Other Management users
                        for ($i = 0; $i < count($row); $i += 2) {
                            if ($i >= 2) {
                                if (isset($column_ids[$i])) {
                                    $columnRefId = $row[1] ?? 0;
                                    $column_id = $column_ids[$i];

                                    $payroll = Payroll::where(['user_id' => $columnRefId, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'finalize_status' => 0, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->first();
                                    if ($payroll == null) {
                                        if ($row[$i] != 0) {
                                            $user = User::where('id', $columnRefId)->where('dismiss', 0)->first();
                                            $position_id = isset($user->positionDetail->id) ? $user->positionDetail->id : 0;

                                            $payroll = new Payroll;
                                            $payroll->user_id = $columnRefId;
                                            $payroll->position_id = $position_id;
                                            $payroll->pay_period_from = $start_date;
                                            $payroll->pay_period_to = $end_date;
                                            $payroll->status = 1;
                                            $payroll->is_mark_paid = 0;
                                            $payroll->is_next_payroll = 0;
                                            $payroll->is_stop_payroll = 0;
                                            $payroll->finalize_status = 0;
                                            $payroll->created_at = new \DateTime;
                                            $payroll->updated_at = new \DateTime;
                                            if ($payroll->save()) {
                                                $customFieldStatus = $this->addOrUpdateCustomFieldPayroll($payroll, $column_id, $row[$i], isset($row[$i + 1]) ? $row[$i + 1] : '');
                                                if ($customFieldStatus['status'] == true) {
                                                    $successPayrollIds[] = $customFieldStatus['payroll_id'];
                                                } else {
                                                    $failedPayrollIds[] = $customFieldStatus['payroll_id'];
                                                }
                                            }
                                        }
                                    } else {
                                        // For  multiple Custom Field
                                        $customFieldStatus = $this->addOrUpdateCustomFieldPayroll($payroll, $column_id, $row[$i], isset($row[$i + 1]) ? $row[$i + 1] : '');
                                        if ($customFieldStatus['status'] == true) {
                                            $successPayrollIds[] = $customFieldStatus['payroll_id'];
                                        } else {
                                            $failedPayrollIds[] = $customFieldStatus['payroll_id'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (count($userIdArray) > 0) {
                $string_data = implode(',', $userIdArray);
                $emailData = [];
                $emailData['email'] = auth()->user()->email;
                $emailData['subject'] = 'Payroll Custom Fields Import';
                $emailData['template'] = "<p> We couldn't find user_id ".$string_data." with the user_id provided in the 'rep_id' column. Please check the user is registered in our system. </p>";
                $this->sendEmailNotification($emailData);
            }

            DB::commit();

            return [
                'status' => true,
                'message' => 'successfully updated',
                'data' => [
                    'success' => array_unique($successPayrollIds),
                    'failed' => array_unique($failedPayrollIds),
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            $error_arr[] = 'UserCommissionHistory '.$e->getMessage();

            return [
                'status' => false,
                'message' => 'Unable to import, due to some data not match with payroll',
                'error' => $error_arr,
            ];
        }
    }

    public function addOrUpdateCustomFieldPayroll($payroll, $column_id, $value, $comment)
    {
        $customField = CustomField::where(['payroll_id' => $payroll->id, 'column_id' => $column_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->first();
        if ($customField == null) {
            $customField = new CustomField;
        }

        $customField->payroll_id = $payroll->id;
        $customField->user_id = $payroll->user_id;
        $customField->column_id = $column_id;
        $customField->value = (float) $value;
        $customField->comment = $comment;
        $customField->pay_period_from = $payroll->pay_period_from;
        $customField->pay_period_to = $payroll->pay_period_to;
        $customField->approved_by = Auth::id();
        // dd($customField);
        if ($customField->save()) {
            return [
                'status' => true,
                'payroll_id' => $payroll->id,
            ];
        } else {
            return [
                'status' => false,
                'payroll_id' => $payroll->id,
            ];
        }
    }

    public function addWorkerCustomField(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'worker_type' => 'required|in:contractor,employee',
            'pay_frequency' => 'required|in:' . FrequencyType::WEEKLY_ID . ',' . FrequencyType::MONTHLY_ID . ',' . FrequencyType::BI_WEEKLY_ID . ',' . FrequencyType::SEMI_MONTHLY_ID . ',' . FrequencyType::DAILY_PAY_ID,
            'users' => 'required|array|min:1',
            'users.*.user_id' => 'required',
            'users.*.custom_fields' => 'required|array|min:1',
            'users.*.custom_fields.*.custom_field_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'add worker custom field api',
                'status' => false,
                'error' => $validator->errors(),
            ], 400);
        }

        $workerType = $request->worker_type == 'employee' ? 'w2' : '1099';
        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $workerType,
            "pay_period_from" => $request->start_date,
            "pay_period_to" => $request->end_date
        ];

        $payroll = Payroll::applyFrequencyFilter($param, ['is_onetime_payment' => 0])->whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json([
                'ApiName' => 'add worker custom field api',
                'status' => false,
                'message' => 'At this time, we are unable to process your request to update custom fields information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.',
            ], 400);
        }

        $workerCustomFieldSave = $this->workerCustomFieldSaveOrUpdate($request, $param);
        if ($workerCustomFieldSave['status']) {
            return response()->json([
                'ApiName' => 'add worker custom field api',
                'message' => $workerCustomFieldSave['message'],
                'status' => true,
                'data' => $workerCustomFieldSave['data']
            ]);
        } else {
            return response()->json([
                'ApiName' => 'add worker custom field api',
                'message' => $workerCustomFieldSave['message'],
                'status' => false,
                'error' => $workerCustomFieldSave['error']
            ], 500);
        }
    }

    public function workerCustomFieldSaveOrUpdate($request, $param)
    {
        try {
            $workerType = $param['worker_type'];
            foreach ($request->users as $customFieldData) {
                $payroll = Payroll::applyFrequencyFilter($param, ['user_id' => $customFieldData['user_id'], 'is_onetime_payment' => 0])->first();
                if (!$payroll) {
                    $user = User::select('id', 'sub_position_id')->where(['id' => $customFieldData['user_id']])->first();
                    $payroll = Payroll::create([
                        'user_id' => $customFieldData['user_id'],
                        'pay_frequency' => $request->pay_frequency,
                        'worker_type' => $workerType,
                        'position_id' => $user?->sub_position_id,
                        'pay_period_from' => $request->start_date,
                        'pay_period_to' => $request->end_date
                    ]);
                }

                if (isset($customFieldData['custom_fields']) && is_array($customFieldData['custom_fields']) && sizeof($customFieldData['custom_fields']) > 0) {
                    foreach ($customFieldData['custom_fields'] as $customFieldValue) {
                        $customField = CustomField::where(['payroll_id' => $payroll->id, 'column_id' => $customFieldValue['custom_field_id']])->first();
                        if (!$customField) {
                            $customField = new CustomField;
                        }

                        $customField->payroll_id = $payroll->id;
                        $customField->user_id = $customFieldData['user_id'];
                        $customField->column_id = $customFieldValue['custom_field_id'];
                        if ($customFieldValue['custom_field_value'] != '') {
                            $customField->value = (float) $customFieldValue['custom_field_value'];
                        }
                        $customField->pay_period_from = $payroll->pay_period_from;
                        $customField->pay_period_to = $payroll->pay_period_to;
                        $customField->approved_by = auth()->id();
                        $customField->save();
                    }
                }
            }

            return [
                'status' => true,
                'message' => 'Successfully updated custom fields!!',
                'data' => []
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Unable to update, due to some data not match with payroll!!',
                'error' => $e->getMessage() ?? 'Something went wrong!!'
            ];
        }
    }

    public function checkRequestPayPeriod(Request $request, $user_id)
    {
        $checkRequestPayPeriodData = [];
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pay_frequency = $request->pay_frequency;

        if ($user_id && $start_date && $end_date) {
            $checkPayroll = Payroll::where(['user_id' => $user_id, 'is_onetime_payment' => 1, 'status' => 3])->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })->get();
            if (empty($checkPayroll->toArray())) {
                $checkPayroll = PayrollHistory::where(['user_id' => $user_id, 'is_onetime_payment' => 1, 'status' => 3])->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {

                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })->get();
            }
            if (empty($checkPayroll->toArray())) {
                $checkRequestPayPeriodData = ['start_date' => null, 'end_date' => null];
            } else {
                $userSubPosition = User::where('id', $user_id)->first('sub_position_id');
                $userSubPositionId = isset($userSubPosition->sub_position_id) ? $userSubPosition->sub_position_id : null;
                $payfrequency = $this->nextPayFrequency($start_date, $userSubPositionId);
                if ($payfrequency->next_pay_period_from != null && $payfrequency->next_pay_period_to != null) {
                    $checkRequestPayPeriodData = ['start_date' => $payfrequency->next_pay_period_from, 'end_date' => $payfrequency->next_pay_period_to];
                } else {
                    return response()->json([
                        'ApiName' => 'update_payment_request',
                        'status' => false,
                        'message' => 'No any next pay period found at this frequency.',
                    ], 400);
                }
            }

        }

        return json_encode($checkRequestPayPeriodData);
    }

    public function reportYearMonthFrequencyWise(Request $request)
    {
        $commissions = 0;
        $override = 0;
        $hourlysalary = 0;
        $overtime = 0;
        $deduction = 0;
        $reconciliation = 0;
        $adjustment = 0;
        $totalPay = 0;
        $taxes = 0;
        $reimbursement = 0;
        $payrollList = [];
        $custompayment = 0;
        if ($request->year && $request->frequency_type) {
            $frequency = FrequencyType::find($request->frequency_type);
            if ($frequency->name == 'Weekly') {
                $pay_frequencies = WeeklyPayFrequency::where('closed_status', 1)->orWhere('w2_closed_status', 1);
            } elseif ($frequency->name == 'Monthly') {
                $pay_frequencies = MonthlyPayFrequency::where('closed_status', 1)->orWhere('w2_closed_status', 1);
            } elseif ($frequency->name == 'Bi-Weekly') {
                // $pay_frequencies = AdditionalPayFrequency::where(['closed_status'=> 1, 'type' => '1']);
                $pay_frequencies = AdditionalPayFrequency::where(function ($query) {
                    $query->where('closed_status', 1)->orWhere('w2_closed_status', 1);
                })->where('type', '1');
            } elseif ($frequency->name == 'Semi-Monthly') {
                // $pay_frequencies = AdditionalPayFrequency::where(['closed_status'=>1, 'type' => '2']);
                $pay_frequencies = AdditionalPayFrequency::where(function ($query) {
                    $query->where('closed_status', 1)->orWhere('w2_closed_status', 1);
                })->where('type', '2');
            } elseif ($frequency->name == 'Daily-pay' || $frequency->name == 'daily-pay') {
                $pay_frequencies = DailyPayFrequency::where(['closed_status' => 1]);
            }

            $pay_frequencies = $pay_frequencies->orderBy('pay_period_from', 'desc')->get();

            // if ($request->pay_period_from_month != 'all') {
            //     $pay_frequencies =  $pay_frequencies->whereMonth('pay_period_to', request()->input('pay_period_from_month'));
            // }
            // $pay_frequencies =  $pay_frequencies->whereYear('pay_period_to',request()->input('pay_period_from_year'))->orderBy('id','DESC')->get();

            $pay_frequencies->transform(function ($pay_frequencies) use ($request, &$commissions, &$override, &$adjustment, &$reconciliation, &$deduction, &$totalPay, &$reimbursement, &$custompayment, &$payrollList, &$hourlysalary, &$overtime, &$taxes) {
                $payrollHistory_query = PayrollHistory::when($request->frequency_type == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_frequencies) {
                    $query->whereBetween('pay_period_from', [$pay_frequencies->pay_period_from, $pay_frequencies->pay_period_to])
                        ->whereBetween('pay_period_to', [$pay_frequencies->pay_period_from, $pay_frequencies->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($pay_frequencies, $request) {
                    $query->where('pay_period_from', $pay_frequencies->pay_period_from)
                        ->where('pay_period_to', $pay_frequencies->pay_period_to)
                        ->whereYear('created_at', $request->pay_period_from_year)
                        ->when($request->pay_period_from_month != 'all', function ($q) use ($request) {
                            $q->whereMonth('created_at', $request->pay_period_from_month);
                        });

                })
                    ->where('payroll_id', '!=', 0)
                    ->groupBy(['payroll_history.pay_period_from', 'payroll_history.pay_period_to'])
                    ->selectRaw('payroll_history.*, sum(payroll_history.commission) as commission, sum(payroll_history.override) as override,
                    sum(payroll_history.reimbursement) as reimbursement, sum(payroll_history.clawback) as clawback, sum(payroll_history.deduction) as deduction,
                    sum(payroll_history.adjustment) as adjustment, sum(payroll_history.reconciliation) as reconciliation, sum(payroll_history.net_pay) as net_pay,
                    payroll_history.created_at as get_date, GROUP_CONCAT(payroll_history.user_id) as user_id, GROUP_CONCAT(payroll_history.payroll_id) as payroll_id')->first();

                if ($payrollHistory_query) {
                    $userIds = explode(',', $payrollHistory_query->user_id);
                    $payrollIds = explode(',', $payrollHistory_query->payroll_id);
                    $userCommissionPayrollIDs = UserCommissionLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                    $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                    $commissionIds = array_merge($userCommissionPayrollIDs, $ClawbackSettlementPayRollIDS);
                    $commission1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $commissionIds)->sum('commission');
                    $overridePayrollIDs = UserOverridesLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                    $override1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overridePayrollIDs)->sum('override');
                    $reconciliation1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $userCommissionPayrollIDs)->sum('reconciliation');

                    // hourlysalary
                    $hourlysalaryPayrollIDs = PayrollHourlySalaryLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                    $hourlysalary1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $hourlysalaryPayrollIDs)->sum('hourly_salary');

                    // overtime
                    $overtimePayrollIDs = PayrollOvertimeLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                    $overtime1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overtimePayrollIDs)->sum('overtime');

                    $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                    $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::whereIn('user_id', $userIds)->whereIn('payroll_id', $overridePayrollIDs)->orWhereIn('payroll_id', $userCommissionPayrollIDs)->pluck('payroll_id')->toArray();
                    $adjustmentIds = array_merge($approvalsAndRequestPayrollIDs, $PayrollAdjustmentDetailPayRollIDS, $ClawbackSettlementPayRollIDS);
                    $miscellaneous1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $adjustmentIds)->sum('adjustment');
                    $customFieldPayrollIds = CustomFieldHistory::whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to])->pluck('payroll_id')->toArray();
                    $netPayIDS = array_merge($commissionIds, $overridePayrollIDs, $approvalsAndRequestPayrollIDs, $adjustmentIds, $customFieldPayrollIds, $hourlysalaryPayrollIDs, $overtimePayrollIDs);
                    $net_pay2 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $netPayIDS)->sum('net_pay');
                    $reimbursement1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $approvalsAndRequestPayrollIDs)->sum('reimbursement');
                    $custompayment1 = PayrollHistory::whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to])->sum('custom_payment');

                    // added to show taxes on payroll as in everee and to match the net pay amount as everee

                    $w2taxDetails = W2PayrollTaxDeduction::select(DB::raw('(SUM(state_income_tax) + SUM(federal_income_tax) + SUM(medicare_tax) + SUM(social_security_tax) + SUM(additional_medicare_tax)) as total_taxes'))->whereIn('user_id', $userIds)->where(['pay_period_from' => $payrollHistory_query->pay_period_from, 'pay_period_to' => $payrollHistory_query->pay_period_to])->first();
                    $w2taxDetails->total_taxes = ($w2taxDetails->total_taxes ?? 0);

                    if (isset($w2taxDetails->total_taxes) && $w2taxDetails->total_taxes !== '') {
                        $net_pay2 = $net_pay2 - $w2taxDetails->total_taxes;
                    }

                    $commissions += ($commission1);
                    $hourlysalary += ($hourlysalary1);
                    $overtime += ($overtime1);
                    $override += ($override1);
                    $adjustment += ($miscellaneous1);
                    $reconciliation += ($reconciliation1);
                    $deduction += ($payrollHistory_query->deduction);
                    $totalPay += ($net_pay2);
                    $reimbursement += ($reimbursement1);
                    $custompayment += ($custompayment1);
                    $taxes += ($w2taxDetails->total_taxes ?? 0);

                    $payrollList[] = [
                        'commission' => isset($commission1) ? $commission1 : '0',
                        'override' => isset($override1) ? $override1 : '0',
                        'hourlysalary' => isset($hourlysalary1) ? $hourlysalary1 : '0',
                        'overtime' => isset($hourlysalary1) ? $overtime : '0',
                        'adjustment' => isset($miscellaneous1) ? $miscellaneous1 : '0',
                        'reconciliation' => isset($reconciliation1) ? $reconciliation1 : '0',
                        'deduction' => isset($payrollHistory_query->deduction) ? $payrollHistory_query->deduction : '0',
                        'netPay' => isset($net_pay2) ? $net_pay2 : '0',
                        'taxes' => $w2taxDetails->total_taxes ?? 0,
                        'employer_taxes' => 0,
                        'reimbursement' => isset($reimbursement1) ? $reimbursement1 : '0',
                        'custom_payment' => isset($custompayment1) ? $custompayment1 : '0',
                        'payroll_date' => isset($payrollHistory_query->get_date) ? date('Y-m-d', strtotime($payrollHistory_query->get_date)) : $pay_frequencies->updated_at,
                        'pay_period_from' => isset($payrollHistory_query->pay_period_from) ? $payrollHistory_query->pay_period_from : $pay_frequencies->pay_period_from,
                        'pay_period_to' => isset($payrollHistory_query->pay_period_to) ? $payrollHistory_query->pay_period_to : $pay_frequencies->pay_period_to,
                    ];

                    return $payrollHistory_query;
                }
            });
        }

        // Apply sorting if sort parameters are provided
        if (! empty($payrollList)) {
            $sortBy = $request->input('sort', 'payroll_date'); // Default sort by payroll_date
            $sortDirection = $request->input('sort_val', 'desc'); // Default sort direction desc

            $payrollList = collect($payrollList);

            if ($sortBy === 'date_range') {
                // Special case for sorting by combined date range
                $payrollList = $payrollList->sortBy(function ($item) {
                    return strtotime($item['pay_period_from']).'-'.strtotime($item['pay_period_to']);
                }, SORT_REGULAR, $sortDirection === 'desc');
            } else {
                // Regular sorting for other fields
                if ($sortDirection === 'asc') {
                    $payrollList = $payrollList->sortBy($sortBy);
                } else {
                    $payrollList = $payrollList->sortByDesc($sortBy);
                }
            }

            $payrollList = $payrollList->values()->all();
        }
        $data = [
            'year' => $request->year,
            'total_commissions' => isset($commissions) ? $commissions : 0,
            'total_override' => isset($override) ? $override : 0,
            'total_hourlysalary' => isset($hourlysalary) ? $hourlysalary : 0,
            'total_overtime' => isset($overtime) ? $overtime : 0,
            'total_adjustment' => isset($adjustment) ? $adjustment : 0,
            'total_reconciliation' => isset($reconciliation) ? $reconciliation : 0,
            'total_deduction' => isset($deduction) ? $deduction : 0,
            'total_Pay' => isset($totalPay) ? $totalPay : 0,
            'total_taxes' => $taxes ?? 0,
            'total_employer_taxes' => 0,
            'total_reimbursement' => isset($reimbursement) ? $reimbursement : 0,
            'total_custom_payment' => isset($custompayment) ? $custompayment : 0,
            'payroll_report' => isset($payrollList) ? $payrollList : null,
        ];
        if (isset($request->is_export) && ($request->is_export == 1)) {
            $file_name = 'payroll_export_'.date('Y_m_d').'.xlsx';
            Excel::store(new \App\Exports\ExportPayroll\ExportPayroll($payrollList),
                'exports/payroll/frequency-type/'.$file_name,
                'public',
                \Maatwebsite\Excel\Excel::XLSX);
            $url = getStoragePath('exports/payroll/frequency-type/'.$file_name);

            // $url = getExportBaseUrl().'storage/exports/payroll/frequency-type/' . $file_name;
            return response()->json(['url' => $url]);
            // return Excel::download(new ExportPayroll($payrollList), $file_name);
        }

        return response()->json([
            'ApiName' => 'Report Year Month And Frequency Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }
}
