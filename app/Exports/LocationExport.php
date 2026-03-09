<?php

namespace App\Exports;

use App\Http\Controllers\API\LocationController;
use Illuminate\Support\Fluent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LocationExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function __construct($request = '')
    {
        $this->request = $request;

    }

    public function collection()
    {
        $this->request['is_export'] = 1;
        $locations = app()->make(LocationController::class)->index($this->request);
        $locations = new Fluent($locations->getData());

        $result = [];
        if ($locations['status'] == 'success') {
            foreach ($locations['locations'] as $location) {
                $redData = [];
                foreach ($location->redline_data as $addiRedLines) {
                    $redData[] = [
                        'redline_standard' => $addiRedLines->redline_standard,
                        'effective_date' => $addiRedLines->effective_date,
                    ];
                }
                $result[] = [
                    'state' => $location->state,
                    'general_code' => $location->general_code,
                    'office_name' => $location->office_name,
                    'work_site_id' => $location->work_site_id,
                    'business_address' => $location->work_site_id,
                    'installation_partner' => $location->installation_partner,
                    'redline_standard' => $location->redline_standard,
                    'effective_date' => @$location->effective_date ? date('m/d/Y', strtotime($location->effective_date)) : null,
                    'user_count' => $location->user_count,
                    'redline_data' => $redData,
                ];
            }
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'State',
            'General Code',
            'Office Name',
            'Work Site Id',
            'Office Address',
            'Installar',
            'Current Standard Redline',
            'Effective Date',
            'Of People',
            'Redline Data with Effective Date',
        ];
    }
}
