<?php

namespace App\Http\Controllers\API\Payroll;

use App\Exports\ExportPayroll\ExportPayroll;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportPayRollController extends Controller
{
    public function exportPayrollReport(Request $request): JsonResponse
    {
        $data = [];

        $all_paid = true;
        $file_name = 'payrollList_'.date('Y_m_d_H_i_s').'.xlsx';

        Excel::store(new ExportPayroll($request), 'exports/'.$file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);
        // Get the URL for the stored file
        $url = getStoragePath('exports/'.$file_name);

        // $url = getExportBaseUrl().'storage/exports/' . $file_name;
        // Return the URL in the API response
        return response()->json(['url' => $url]);

    }
}
