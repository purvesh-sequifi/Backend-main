<?php

namespace App\Exports\ExportReports\Clawback;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ClawbackReportExport implements WithMultipleSheets
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        return [
            'Clawback List' => new ClawbackList($this->data),
            'Clawback Details' => new ClawbackDetailsExport($this->data),
        ];
    }
}
