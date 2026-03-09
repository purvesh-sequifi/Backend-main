<?php

namespace App\Http\Controllers\API\V2\Payroll;

use Illuminate\Http\Request;
use App\Models\FrequencyType;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Exports\Admin\PayrollExport\PidBasicExport;
use App\Exports\Admin\PayrollExport\PidDetailExport;
use App\Exports\Admin\PayrollExport\WorkerBasicExport;
use App\Exports\Admin\PayrollExport\WorkerDetailExport;
use App\Exports\Admin\PayrollExport\RepaymentDetailExport;
use App\Exports\Admin\PayrollExport\WorkerAllDetailsExport;

class PayrollExportController extends Controller
{
    const FILE_TYPE = ".xlsx";
    const EXPORT_FOLDER_PATH = 'exports/';
    const EXPORT_STORAGE_FOLDER_PATH = "exports/";

    public function workerBasic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "worker-basic",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "worker-basic-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new WorkerBasicExport($request), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function workerDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "worker-detail",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "worker-detail-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new WorkerDetailExport($request), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function workerAllDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "worker-all-details",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "worker-all-details-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new WorkerAllDetailsExport($request), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function pidBasic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "pid-basic",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "pid-basic-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new PidBasicExport($request), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function pidDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "pid-detail",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "pid-details-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new PidDetailExport($request), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function repaymentDetail(Request $request)
    {
        $filename = "repayment-detail-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new RepaymentDetailExport($request), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }
}
