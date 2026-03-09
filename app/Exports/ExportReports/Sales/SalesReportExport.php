<?php

namespace App\Exports\ExportReports\Sales;

use App\Models\CompanyProfile;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesReportExport implements WithMultipleSheets
{
    private $data;

    private $isRecon;

    public function __construct($data, $reconciliationSetting)
    {
        $this->data = $data;
        $this->isRecon = $reconciliationSetting ? 1 : 0;
    }

    public function sheets(): array
    {
        $companyProfile = CompanyProfile::first();

        return [
            'Sales List' => new SalesList($this->data, $companyProfile, $this->isRecon),
            'Sales Report' => new SalesPidDetailsExport($this->data, $companyProfile),
        ];
    }
}
