<?php

namespace App\Exports\ExportRecon;

use App\Models\Locations;
use App\Models\Positions;
use App\Models\ReconciliationFinalizeHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MainReconListExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection(): Collection
    {
        $requestData = $this->request;
        $date = $requestData->query()['executed_on'];
        // Fetch data based on the presence of the date
        $data = $this->fetchData($requestData);
        if ($data->isEmpty()) {
            return collect([]);
        }
        // Initialize totals
        $totals = $this->initializeTotals();

        // Transform data
        $data->transform(function ($data) use ($date, &$totals) {
            return $this->transformData($data, $date, $totals);
        });

        // Calculate totals
        $this->calculateTotals($totals, $date);

        // Prepare final total summary
        $this->prepareTotalSummary($totals, $date);

        // Attach total summary to data
        // $data['Heading_total'] = $totalSummary;
        return collect($data);
    }

    private function fetchData($requestData)
    {
        $pages = $requestData->input('perpage', 10);
        $executedOn = $requestData->input('executed_on', date('Y'));

        // Initial Query Construction
        $query = ReconciliationFinalizeHistory::query()
            ->whereIn('status', ['payroll', 'clawback'])
            ->whereYear('executed_on', $executedOn);

        if ($requestData->has('search')) {
            $search = $requestData->search;
            $query->where(function ($q) use ($search) {
                $q->where('start_date', $search)
                    ->orWhere('end_date', $search)
                    ->orWhereRaw('CONCAT(start_date, "-", end_date) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('CONCAT(start_date, " - ", end_date) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('CONCAT(start_date, " ", end_date) LIKE ?', ['%'.$search.'%'])
                    ->orWhereHas('office', function ($query) use ($search) {
                        $query->where('office_name', 'LIKE', '%'.$search.'%');
                    })
                    ->orWhereHas('position', function ($query) use ($search) {
                        $query->where('position_name', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        // Aggregating Data
        $query->clone()->select(
            DB::raw('SUM(paid_commission) as total_commission'),
            DB::raw('SUM(paid_override) as total_override'),
            DB::raw('SUM(clawback) as total_clawback'),
            DB::raw('SUM(adjustments) as total_adjustments'),
            DB::raw('SUM(gross_amount) as total_gross_amount'),
            DB::raw('SUM(net_amount) as total_payout')
        )->get();
        $totalCommission = $query->sum('paid_commission');
        $totalOverride = $query->sum('paid_override');
        $totalClawback = $query->sum('clawback');
        $totalAdjustments = $query->sum('adjustments');
        $totalGrossAmount = $query->sum('gross_amount');
        $totalPayout = $query->sum('net_amount');
        // Response Total Calculation
        $responseTotal = [
            'totalCommision' => $totalCommission,
            'override' => $totalOverride,
            'clawback' => $totalClawback,
            'adjustments' => $totalAdjustments,
            'gross_amount' => $totalGrossAmount,
            'payout' => $totalPayout,
            'year' => $executedOn,
            'next_recon' => 123,
        ];
        // Paginating the results
        $data = $query->select([
            'payout',
            'finalize_count',
            'start_date',
            'end_date',
            'executed_on',
            'position_id',
            'office_id',
            DB::raw('SUM(paid_commission) as total_commission'),
            DB::raw('SUM(paid_override) as total_override'),
            DB::raw('SUM(clawback) as total_clawback'),
            DB::raw('SUM(adjustments) as total_adjustments'),
            DB::raw('SUM(gross_amount) as total_gross_amount'),
            DB::raw('SUM(net_amount) as total_net_amount'),
        ])
            ->groupBy('finalize_count', 'start_date', 'end_date')
            ->orderBy('id', 'asc')->get();

        return $data->transform(function ($item) {
            $positionId = $item->pluck('position_id')->unique()->toArray();
            $officeId = $item->pluck('office_id')->unique()->toArray();
            $positionNames = Positions::whereIn('id', $positionId)->pluck('position_name')->toArray();
            $officeNames = Locations::whereIn('id', $officeId)->pluck('office_name')->toArray();

            return [
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'executed_on' => $item->executed_on,
                'office' => implode(', ', $officeNames),
                'position' => implode(', ', $positionNames),
                'commission' => $item->total_commission,
                'overrides' => $item->total_override,
                'clawback' => $item->total_clawback,
                'adjustments' => $item->total_adjustments,
                'gross_amount' => $item->total_gross_amount,
                'payout' => $item->payout,
                'net_amount' => $item->total_net_amount, // If net_amount is the same as payout, otherwise adjust accordingly
                'status' => $item->status,
                'sent_id' => $item->finalize_count,
            ];
        });
    }

    private function initializeTotals()
    {
        return [
            'totalCommission' => 0,
            'totalOverride' => 0,
            'totalClawback' => 0,
            'totalAdjustments' => 0,
            'grossAmount' => 0,
            'payout' => 0,
        ];
    }

    private function transformData($data, $date, &$totals)
    {
        $position = $this->fetchPosition($data, $date);
        $office = $this->fetchOffice($data, $date);
        $sums = $this->calculateSums($data, $date);
        // Update totals
        $this->updateTotals($totals, $sums);

        return [
            'start_date' => Carbon::parse($data['start_date'])->format('m-d-Y'),
            'end_date' => Carbon::parse($data['end_date'])->format('m-d-Y'),
            'executed_on' => Carbon::parse($data['executed_on'])->format('m-d-Y'),
            'office' => $office,
            'position' => $position,
            /* 'office' => $data["office"],
            'position' => $data["position"], */
            'commission' => $sums['commission'],
            'overrides' => $sums['override'],
            'clawback' => '-1' * $sums['clawback'],
            'adjustments' => $sums['adjustments'],
            'gross_amount' => $sums['gross_amount'],
            'payout %' => $data['payout'],
            'payout' => $sums['payout'],
        ];
    }

    private function fetchPosition($data, $date)
    {
        $positionIds = ReconciliationFinalizeHistory::whereYear('executed_on', $date)
            ->where('start_date', $data['start_date'])
            ->where('end_date', $data['end_date'])
            ->whereIn('status', ['payroll', 'clawback'])
            ->pluck('position_id')
            ->unique()
            ->values()
            ->all();
        if ($positionIds[0] === 'all') {
            return 'All office';
        }

        return implode(',', array_map(function ($id) {
            return Positions::find($id)->position_name;
        }, $positionIds));
    }

    private function fetchOffice($data, $date)
    {
        $officeIds = ReconciliationFinalizeHistory::whereYear('executed_on', $date)
            ->where('start_date', $data['start_date'])
            ->where('end_date', $data['end_date'])
            ->whereIn('status', ['payroll', 'clawback'])
            ->pluck('office_id')
            ->unique()
            ->values()
            ->all();

        if ($officeIds[0] === 'all') {
            return 'All office';
        }

        return implode(',', array_map(function ($id) {
            return Locations::find($id)->office_name;
        }, $officeIds));
    }

    private function calculateSums($data, $date)
    {
        $query = ReconciliationFinalizeHistory::whereYear('executed_on', $date)
            ->where('start_date', $data['start_date'])
            ->where('end_date', $data['end_date'])
            ->whereIn('status', ['payroll', 'clawback']);

        return [
            'commission' => floatval($query->sum('paid_commission')),
            'override' => floatval($query->sum('paid_override')),
            'clawback' => floatval($query->sum('clawback')),
            'adjustments' => floatval($query->sum('adjustments')),
            'gross_amount' => floatval($query->sum('gross_amount')),
            // 'payout' => $query->get()[0]["payout"],
            'payout' => floatval($query->sum('net_amount')),
        ];
    }

    private function updateTotals(&$totals, $sums)
    {
        $totals['totalCommission'] += $sums['commission'];
        $totals['totalOverride'] += $sums['override'];
        $totals['totalClawback'] += $sums['clawback'];
        $totals['totalAdjustments'] += $sums['adjustments'];
        $totals['grossAmount'] += $sums['gross_amount'];
        $totals['payout'] += $sums['payout'];
    }

    private function calculateTotals(&$totals, $date)
    {
        $dataCalculate = ReconciliationFinalizeHistory::whereYear('executed_on', $date)
            ->orderBy('id', 'asc')
            ->groupBy('start_date')
            ->where('status', 'payroll')
            ->get();

        foreach ($dataCalculate as $dataCalculates) {
            $sums = $this->calculateSums($dataCalculates, $date);
            $this->updateTotals($totals, $sums);
        }
    }

    private function prepareTotalSummary($totals, $date)
    {
        return [
            'totalCommission' => $totals['totalCommission'],
            'override' => $totals['totalOverride'],
            'clawback' => $totals['totalClawback'],
            'adjustments' => $totals['totalAdjustments'],
            'gross_amount' => $totals['grossAmount'],
            'payout' => $totals['payout'],
            'year' => $date ?? date('Y'),
            'nextRecon' => $totals['grossAmount'] - $totals['payout'],
        ];
    }

    /* Set header in the sheet */
    public function headings(): array
    {
        return [
            'Start Date ',
            'End Date ',
            'Execute On ',
            'Location ',
            'Position ',
            'Commissions  ',
            'Overrides ',
            'Clawback ',
            'Adjustments ',
            'Total  ',
            'Payout % ',
            'Payout  ',

        ];
    }

    /* Set style sheet */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999', // Background color (light gray)
                ],
            ],
        ]);
    }

    /* set footer value */
    public function registerEvents(): array
    {
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                $this->setHeadersAndFooter($event);
                $this->styleRows($event, $collectionData);

                $lastRow = $event->sheet->getDelegate()->getHighestDataRow();

                // Define the colors for alternating rows
                $evenRowColor = 'f0f0f0'; // Light green
                $oddRowColor = 'FFFFFF'; // White

                for ($row = 2; $row <= $lastRow; $row += 2) {
                    $event->sheet->getStyle("A$row:L$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $oddRowColor],
                        ],
                    ]);
                }

                for ($row = 3; $row <= $lastRow; $row += 2) {
                    $event->sheet->getStyle("A$row:L$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $evenRowColor],
                        ],
                    ]);
                }

                for ($i = 2; $i <= $lastRow; $i++) {
                    for ($col = 'A'; $col != 'AC'; $col++) {
                        $event->sheet->getStyle("$col$i")->applyFromArray([
                            'borders' => $this->borderStyle(),
                        ]);
                    }
                }
                /* Set footer values */
                $this->setFooterValues($event);
            },
        ];
    }

    public function setFooterValues($event)
    {
        $worksheet = $event->sheet->getDelegate();
        // Get the last row index
        $lastRowIndex = $worksheet->getHighestDataRow();
        $worksheet->insertNewRowBefore($lastRowIndex + 1, 1);

        $bgColor = 'f0f0f0'; // White odd row
        if (($lastRowIndex + 1) % 2 === 0) {
            $bgColor = 'FFFFFF'; // Light green even row
        }

        $event->sheet->getStyle('A'.($lastRowIndex + 1).':L'.($lastRowIndex + 1))->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $bgColor],
            ],
        ]);

        // Populate the footer columns with data and apply styling
        $footerColumns = [
            'A'.($lastRowIndex + 1),
            'F'.($lastRowIndex + 1),
            'G'.($lastRowIndex + 1),
            'H'.($lastRowIndex + 1),
            'I'.($lastRowIndex + 1),
            'J'.($lastRowIndex + 1),
            'L'.($lastRowIndex + 1),
        ];

        // Loop through each footer column
        foreach ($footerColumns as $value) {
            // Apply bold font style
            $event->sheet->getStyle($value)->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
            ]);

            // Determine the content and apply conditional formatting
            switch ($value) {
                case 'A'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, 'Total');
                    break;
                case 'F'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, '$ '.$this->totalSum('commission'));
                    break;
                case 'G'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, '$ '.$this->totalSum('overrides'));
                    break;
                case 'H'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, '$ '.$this->totalSum('clawback'));
                    break;
                case 'I'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, '$ '.$this->totalSum('adjustments'));
                    break;
                case 'J'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, '$ '.$this->totalSum('gross_amount'));
                    break;
                case 'K'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, '$ '.$this->totalSum('payout %'));
                    break;
                case 'L'.($lastRowIndex + 1):
                    $event->sheet->setCellValue($value, '$ '.$this->totalSum('payout'));
                    break;
            }

            // Apply bold font style again
            $event->sheet->getStyle($value)->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
            ]);

            // Apply conditional formatting based on the value
            $cellValue = $event->sheet->getCell($value)->getValue();
            if (strpos($cellValue, '$ ') < 1) {
                $valueParts = explode('$ ', $cellValue);
                if (isset($valueParts[1]) && (floatval($valueParts[1]) < 0)) {
                    $styleArray = [
                        'font' => [
                            'color' => ['rgb' => 'FF0000'], // Red color
                        ],
                    ];
                    $formattedValue = '$ ('.number_format(abs(floatval($valueParts[1]))).')';
                    $event->sheet->setCellValue($value, $formattedValue);
                    $event->sheet->getStyle($value)->applyFromArray($styleArray);
                }
            }
        }

    }

    /**
     * Method applyStyles: if negative found in cell then remove negative sign and replace with round bracket with red color
     *
     * @param  AfterSheet  $event  [explicit description]
     */
    private function styleRows(AfterSheet $event, $collectionData): void
    {
        $rowCounter = 2; // Reset the row counter before processing each collection
        foreach ($collectionData as $val) {
            $this->applyCellStyle($event, $val, 'commission', 'F'.$rowCounter);
            $this->applyCellStyle($event, $val, 'overrides', 'G'.$rowCounter);
            $this->applyCellStyle($event, $val, 'clawback', 'H'.$rowCounter);
            $this->applyCellStyle($event, $val, 'adjustments', 'I'.$rowCounter);
            $this->applyCellStyle($event, $val, 'gross_amount', 'J'.$rowCounter);
            $this->applyCellStyle($event, $val, 'payout %', 'K'.$rowCounter);
            $this->applyCellStyle($event, $val, 'payout', 'L'.$rowCounter);

            $rowCounter++;
        }
    }

    private function borderStyle()
    {
        return [
            'top' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
            'bottom' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
            'left' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
            'right' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
        ];
    }

    private function setHeadersAndFooter(AfterSheet $event)
    {
        $lastRowIndex = $event->sheet->getDelegate()->getHighestDataRow();

        $event->sheet->freezePane('A2');
        $event->sheet->insertNewRowBefore($lastRowIndex + 1, 1);
    }

    /**
     * Method applyCellStyle: set cell styling and formatting
     *
     * @param  AfterSheet  $event  [explicit description]
     * @param  $val  $val [explicit description]
     * @param  $field  $field [explicit description]
     * @param  $cellAddress  $cellAddress [explicit description]
     */
    private function applyCellStyle(AfterSheet $event, $val, $field, $cellAddress): void
    {
        if (! empty($val[$field])) {
            $totalPay = floatval(str_replace(',', '', $val[$field]));
            $styleArray = [
                'font' => [
                    'bold' => false,
                    'size' => 12,
                ],
            ];
            if ($field == 'payout %') {
                $event->sheet->setCellValue($cellAddress, $totalPay.'%');
            } else {
                $event->sheet->setCellValue($cellAddress, '$ '.$totalPay);
            }
            if ($totalPay < 0) {
                $event->sheet->setCellValue($cellAddress, '$ ('.exportNumberFormat(abs($totalPay)).')');
                // Set text color to red for negative values
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }
            $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
        } else {
            $event->sheet->setCellValue($cellAddress, '$ 0.00');
        }
    }

    /**
     * Method totalSum: Sum of all net pay amount
     *
     * @return float|int
     */
    private function totalSum($fieldName)
    {
        $totalGrossAmount = [];
        foreach ($this->collection() as $value) {
            $totalGrossAmount[] = floatval(str_replace(',', '', $value[$fieldName]));
        }

        return array_sum($totalGrossAmount);
    }
}
