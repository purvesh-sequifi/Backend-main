<?php

namespace App\Http\Controllers\API\PayrollExport;

use App\Exports\Admin\PayrollExport\PidBasicExport;
use App\Exports\Admin\PayrollExport\PidDetailExport;
use App\Exports\Admin\PayrollExport\RepaymentDetailExport;
use App\Exports\Admin\PayrollExport\WorkerAllDetailsExport;
use App\Exports\Admin\PayrollExport\WorkerBasicExport;
use App\Exports\Admin\PayrollExport\WorkerDetailExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollExportController extends Controller
{
    const FILE_TYPE = '.xlsx';

    const EXPORT_FOLDER_PATH = 'exports/';

    const EXPORT_STORAGE_FOLDER_PATH = 'exports/';

    public function workerBasicExport(Request $request): JsonResponse
    {
        $filename = 'worker-basic-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new WorkerBasicExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    public function workerDetailExport(Request $request): JsonResponse
    {
        $filename = 'worker-detail-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new WorkerDetailExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    public function workerAllDetailsExport(Request $request): JsonResponse
    {
        $filename = 'worker-all-details-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new WorkerAllDetailsExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);

    }

    public function pidBasicExport(Request $request): JsonResponse
    {
        $filename = 'pid-basic-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new PidBasicExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);

    }

    public function pidDetailExport(Request $request): JsonResponse
    {
        $filename = 'pid-details-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new PidDetailExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);

    }

    public function repaymentDetailExport(Request $request): JsonResponse
    {
        $filename = 'repayment-detail-export-'.date('Y-m-d').self::FILE_TYPE;
        Excel::store(new RepaymentDetailExport($request), self::EXPORT_FOLDER_PATH.$filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH.$filename);

        // $url = getExportBaseUrl() . self::EXPORT_STORAGE_FOLDER_PATH . $filename;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }
}
