<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportReportOfficeStandard implements FromCollection, WithHeadings
{
    private $exportdata;

    public function __construct($exportdata)
    {
        $this->exportdata = $exportdata;
    }

    public function collection(): Collection
    {
        $data = $this->exportdata;
        foreach ($data as $val) {
            $res[] = [
                'user_name' => $val['user_name'],
                'team' => $val['team'],
                'account' => isset($val['account']) ? $val['account'] : null,
                'pending' => isset($val['pending']) ? $val['pending'] : null,
                'pending_percentage' => isset($val['pending_percentage']) ? $val['pending_percentage'] : null,
                'install' => isset($val['install']) ? $val['install'] : null,
                'install_percentage' => isset($val['install_percentage']) ? $val['install_percentage'] : null,
                'cancelled' => isset($val['cancelled']) ? $val['cancelled'] : null,
                'cancelled_percentage' => isset($val['cancelled_percentage']) ? $val['cancelled_percentage'] : null,
                'team_lead' => isset($val['team_lead']) ? $val['team_lead'] : null,
                'closing_ratio' => isset($val['closing_ratio']) ? $val['closing_ratio'] : null,
                'avg_system_size' => isset($val['avg_system_size']) ? $val['avg_system_size'] : null,
            ];
        }

        return collect($res);
    }

    public function headings(): array
    {
        return [
            'Name',
            'Team',
            'Accounts',
            'Pending',
            'Pending %',
            'Installed',
            'Installed %',
            'Cancelled',
            'Cancelled %',
            'Team Lead',
            'Closing Ratio',
            'Avg System size',
        ];
    }
}
