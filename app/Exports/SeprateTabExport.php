<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SeprateTabExport implements WithMultipleSheets
{
    use Exportable;

    protected $year;

    public function __construct($year = '')
    {
        $this->year = $year;
    }

    public function sheets(): array
    {
        $sheets = [
            // new DepartmentExportSheet(),
            new PositionsExportSheet,
            // new StatesExportSheet(),
            new OfficeExportSheet,
            new ManagerExportSheet,
        ];

        // $sheets = [];
        // for ($month = 1; $month <= 12; $month++) {
        //     $sheets[] = new InvoicesPerMonthSheet($this->year, $month);
        // }

        return $sheets;
    }
}
