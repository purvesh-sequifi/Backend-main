<?php

namespace App\Exports\ExportPayroll;

use App\Exports\ExportPayrollList;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExportPayroll implements WithMultipleSheets
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function sheets(): array
    {
        return [
            'Payroll List' => new ExportPayrollList($this->request),
            'Paid Payroll List' => new ExportPaidPayroll($this->request),
            'Next Payroll List' => new ExportNextPayroll($this->request),
        ];
    }
}
