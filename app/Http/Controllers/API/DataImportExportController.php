<?php

namespace App\Http\Controllers\API;

use App\Exports\OfficeExportSheet;
use App\Exports\PositionsExportSheet;
use App\Exports\SeprateTabExport;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

// use App\Exports\InvoicesExport;

class DataImportExportController extends Controller
{
    public function __construct() {}

    public function download_user_separate_sheet()
    {
        $export = new SeprateTabExport;

        return Excel::download($export, 'separate_sheet.xlsx');

        // return (new InvoicesExport(2018))->download('separate_sheet.xlsx');
        // $position = $this->positions_sheet();
        // $position2 = $this->office_sheet();

    }

    public function download_user_separate_sheet1(): BinaryFileResponse
    {
        $files = [];
        $file_name1 = 'positions_sheet.xlsx';
        $files[] = storage_path('app/'.$file_name1);
        Excel::store(new PositionsExportSheet, $file_name1, 'public');

        $file_name2 = 'positions2_sheet.xlsx';
        $files[] = storage_path('app/'.$file_name2);
        Excel::store(new PositionsExportSheet, $file_name2, 'public');

        $zipFileName = 'excel_files.zip';
        $zipPath = storage_path($zipFileName);
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach ($files as $file) {
            $zip->addFile($file, pathinfo($file, PATHINFO_BASENAME));
        }
        $zip->close();

        return Response::download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    public function positions_sheet()
    {
        // $file_name = 'separate_sheet_'.date('Y_m_d_H_i_s').'.xlsx';
        $file_name = 'positions_sheet.xlsx';

        return Excel::download(new PositionsExportSheet, $file_name);
    }

    public function office_sheet()
    {
        $file_name = 'office_sheet.xlsx';

        return Excel::download(new OfficeExportSheet, $file_name);
    }
}
