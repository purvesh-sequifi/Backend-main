<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeadsExportSample implements FromCollection, WithHeadings
{
    /**
     * Return a collection of sample lead data.
     */
    public function collection(): Collection
    {
        $sampleData = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'source' => 'Referral', // Sample source, can be 'Excel Import'
                'mobile_no' => '123-456-7890',
                'email' => 'john.doe@example.com',
                'state_id' => 'CA', // Assuming 'state_id' represents a state code
                'reporting_manager_id' => 'Jane Smith',
                'comments' => 'Interested in the new product line',
            ],
        ];

        // Convert the array to a collection
        return collect($sampleData);
    }

    /**
     * Define the headings for the exported file.
     */
    public function headings(): array
    {
        return [
            'First Name',
            'Last Name',
            'Source',
            'Mobile No',
            'Email',
            'Home',
            'Reporting Manager',
            'Comments',
        ];
    }
}
