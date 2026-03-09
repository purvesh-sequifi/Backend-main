<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportReportMySalesStandard implements FromCollection, WithHeadings
{
    private $exportdata;

    private $milestoneNames = [];

    public function __construct($exportdata)
    {
        $this->exportdata = $exportdata;
        $this->collectMilestoneNames();
    }

    private function collectMilestoneNames()
    {
        $names = [];
        foreach ($this->exportdata as $item) {
            if (! empty($item['all_milestone']) && is_array($item['all_milestone'])) {
                foreach ($item['all_milestone'] as $milestone) {
                    if (isset($milestone['name'])) {
                        $names[] = $milestone['name'];
                    }
                }
            }
        }

        $unique = array_values(array_unique($names));

        // Move 'Recon' to the end if it exists
        $reconIndex = array_search('Recon', $unique);
        if ($reconIndex !== false) {
            unset($unique[$reconIndex]);
            $unique[] = 'Recon';
        }

        $this->milestoneNames = $unique;
    }

    public function collection()
    {
        $data = $this->exportdata;
        $res = [];

        foreach ($data as $val) {
            $row = [
                'pid' => $val['pid'] ?? null,
                'customer_name' => $val['customer_name'] ?? null,
                'state' => $val['state'] ?? 0,
                'setter' => $val['setter'] ?? 0,
                'closer' => $val['closer'] ?? 0,
                'net_epc' => $val['net_epc'] ?? 0,
                'kw' => $val['kw'] ?? 0,
                'product' => $val['product'] ?? null,
                'date_cancelled' => $val['date_cancelled'] ?? 0,
            ];

            // Add milestone columns (only value for 'Recon')
            foreach ($this->milestoneNames as $milestoneName) {
                $row[$milestoneName] = null;

                if ($milestoneName !== 'Recon') {
                    $row[$milestoneName.'-date'] = null;
                }
            }

            if (! empty($val['all_milestone'])) {
                foreach ($val['all_milestone'] as $milestone) {
                    $name = $milestone['name'] ?? null;
                    if ($name && in_array($name, $this->milestoneNames)) {
                        $row[$name] = $milestone['value'] ?? null;

                        if ($name !== 'Recon') {
                            $row[$name.'-date'] = $milestone['date'] ?? null;
                        }
                    }
                }
            }

            $res[] = $row;
        }

        return collect($res);
    }

    public function headings(): array
    {
        $milestoneHeadings = [];
        foreach ($this->milestoneNames as $name) {
            $milestoneHeadings[] = $name.' Amount';

            if ($name !== 'Recon') {
                $milestoneHeadings[] = $name.' Date';
            }
        }

        return array_merge([
            'PID',
            'Customer',
            'State',
            'Setter',
            'Closer',
            'Net EPC',
            'KW',
            'Product',
            'Cancel',
        ], $milestoneHeadings);
    }
}
