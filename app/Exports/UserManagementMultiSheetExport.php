<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserManagementMultiSheetExport implements WithMultipleSheets
{
    use Exportable;

    protected $type;

    protected $data;

    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [];
        $sheets[] = new BasicInfoSheetExport($this->data['basic'], 'Basic Info');
        if (count($this->data['personal']) != 0) {
            $sheets[] = new PersonalInfoSheetExport($this->data['personal'], 'Personal Info');
        }

        if (count($this->data['organization']) != 0) {
            $sheets[] = new OrganizationInfoSheetExport($this->data['organization'], 'Organization Info');
        }

        if (! empty($this->data['commission'])) {
            foreach ($this->data['commission'] as $sheetName => $productCommission) {
                // Extract the last element
                $mileStonesNames = array_pop($productCommission);

                // Pass the modified array and the last element separately
                $sheets[] = new EmployeeInfoSheetExport($productCommission, $sheetName, $mileStonesNames);
            }
        }

        // if (sizeof($this->data['employment']) != 0) {
        //     $sheets[] = new EmployeeInfoSheetExport($this->data['employment'], 'Employment Info');
        // }

        if (count($this->data['tax']) != 0) {
            $sheets[] = new TaxInfoSheetExport($this->data['tax'], 'Tax Info');
        }

        if (count($this->data['banking']) != 0) {
            $sheets[] = new BankingInfoSheetExport($this->data['banking'], 'Banking Info');
        }

        return $sheets;
    }
}
