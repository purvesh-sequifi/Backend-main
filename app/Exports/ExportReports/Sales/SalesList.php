<?php

namespace App\Exports\ExportReports\Sales;

use App\Models\CompanyProfile;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesList implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $data;

    private $isRecon;

    private const JOB_STATUS = 'Job Status';

    private const TOTAL_COMMISSION = 'Total Commission';

    private const TOTAL_OVERRIDE = 'Total Override';

    private const PAYMENT_STATUS = 'Payment Status';

    private const ZERO_AMOUNT = '$0.00';

    private const ADDERS = 'Adders ';
    private const COMP_RATE = 'Comp Rate';
    private const FEE_PERCENTAGE = 'Fee %';

    private $companyProfile;

    private $triggerDate;

    private $pestCompany;
    private bool $isMortgageCompany;

    public function __construct($data, $companyProfile, $isRecon)
    {
        $this->data = $data;
        $this->isRecon = $isRecon;
        $this->companyProfile = $companyProfile;
        $this->triggerDate = getTriggerDatesForSample();
        $this->pestCompany = in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $this->isMortgageCompany = $this->companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE;
    }

    /**
     * Sales List-only milestone header normalization.
     * - Rename "M1 Date" => "M1"
     * - Rename "M2 Date" => "M2"
     * - Remove "Final Payment"
     */
    private function normalizeSalesListMilestoneHeader(?string $name): ?string
    {
        $name = $name !== null ? trim($name) : null;
        if ($name === null || $name === '') {
            return null;
        }

        return match ($name) {
            'Final Payment' => null,
            'M1 Date' => 'M1',
            // Drop the legacy/duplicate "M2" trigger column; we keep "M2" via "M2 Date" => "M2".
            'M2' => null,
            'M2 Date' => 'M2',
            default => $name,
        };
    }

    /**
     * Returns the milestone headers to use for the Sales List sheet (normalized, filtered).
     *
     * @return array<int, string>
     */
    private function salesListMilestoneHeaders(): array
    {
        $headers = [];
        foreach ($this->triggerDate as $date) {
            $normalized = $this->normalizeSalesListMilestoneHeader($date['name'] ?? null);
            if ($normalized !== null) {
                $headers[] = $normalized;
            }
        }

        return $headers;
    }

    public function collection()
    {
        $data = $this->data;
        $data = collect($data);
        if ($this->pestCompany) {
            $data->transform(function ($result) {
                $final = [
                    'PID' => $result['pid'] ?? '-',
                    'Source' => ucfirst($result['source']) ?? '-',
                    'Customer' => ucfirst($result['customer_name']) ?? '-',
                    self::JOB_STATUS => $result['job_status'] ?? '-',
                    'State' => ucwords($result['state']) ?? '-',
                    'Location' => ucwords($result['location_code']) ?? '-',
                    'Sales Rep-1' => $result['closer_1'] ?? '-',
                    'Sales Rep-2' => $result['closer_2'] ?? '-',
                    'Sales Rep-1 Email' => $result['closer_1_email'] ?? '-',
                    'Sales Rep-2 Email' => $result['closer_2_email'] ?? '-',
                    'Approved Date' => $result['customer_signoff'] ?? '-',
                    'Gross Value' => $result['gross_account_value'] ?? self::ZERO_AMOUNT,
                    self::ADDERS => $result['adders'] ?? '-',
                    self::TOTAL_COMMISSION => $result['total_commission'] ?? self::ZERO_AMOUNT,
                    self::TOTAL_OVERRIDE => $result['total_override'] ?? self::ZERO_AMOUNT,
                    self::PAYMENT_STATUS => $result['mark_account_status_name'] ?? '-',
                ];

                if ($this->isRecon) {
                    $final['Recon'] = $result['total_recon'] ?? self::ZERO_AMOUNT;
                }

                foreach ($result['all_milestone'] as $milestone) {
                    $header = $this->normalizeSalesListMilestoneHeader($milestone['name'] ?? null);
                    if ($header === null) {
                        continue;
                    }
                    $final[$header] = (! $milestone['is_projected'] ? $milestone['value'] : 0) ?? self::ZERO_AMOUNT;
                }

                return $final;
            });
        } else {
            $data->transform(function ($result) {
                $netEpc = $result['net_epc'] ?? null;
                $feePercentage = null;
                $formattedCompRate = '-';
                if ($this->isMortgageCompany && is_numeric($netEpc)) {
                    $feePercentage = number_format(((float) $netEpc) * 100, 4, '.', '').'%';
                }
                if ($this->isMortgageCompany && is_numeric($result['comp_rate'] ?? null)) {
                    $formattedCompRate = number_format((float) $result['comp_rate'], 4, '.', '').'%';
                }
                $final = [
                    'PID' => $result['pid'] ?? '-',
                    'Source' => ucfirst($result['source']) ?? '-',
                    'Customer' => $result['customer_name'] ?? '-',
                    self::JOB_STATUS => $result['job_status'] ?? '-',
                    'State' => strtoupper($result['state']) ?? '-',
                    'Location' => ucwords($result['location_code']) ?? '-',
                ];

                // Mortgage-specific worker labels
                if ($this->isMortgageCompany) {
                    $final['MLO'] = $result['closer_1'] ?? '-';
                    $final['Additional Rep 3'] = $result['closer_2'] ?? '-';
                    $final['LOA'] = $result['setter_1'] ?? '-';
                    $final['Additional Rep 4'] = $result['setter_2'] ?? '-';
                    $final['MLO Email'] = $result['closer_1_email'] ?? '-';
                    $final['Additional Rep 3 Email'] = $result['closer_2_email'] ?? '-';
                    $final['LOA Email'] = $result['setter_1_email'] ?? '-';
                    $final['Additional Rep 4 Email'] = $result['setter_2_email'] ?? '-';
                } else {
                    $final['Closer-1'] = $result['closer_1'] ?? '-';
                    $final['Closer-2'] = $result['closer_2'] ?? '-';
                    $final['Setter-1'] = $result['setter_1'] ?? '-';
                    $final['Setter-2'] = $result['setter_2'] ?? '-';
                    $final['Closer-1 Email'] = $result['closer_1_email'] ?? '-';
                    $final['Closer-2 Email'] = $result['closer_2_email'] ?? '-';
                    $final['Setter-1 Email'] = $result['setter_1_email'] ?? '-';
                    $final['Setter-2 Email'] = $result['setter_2_email'] ?? '-';
                }

                $final['Approved Date'] = $result['customer_signoff'] ?? '-';
                $final[$this->isMortgageCompany ? 'Loan Amount' : 'KW'] = $result['kw'] ?? self::ZERO_AMOUNT;
                $final['EPC'] = $result['epc'] ?? self::ZERO_AMOUNT;
                $final['Net EPC'] = $result['net_epc'] ?? self::ZERO_AMOUNT;
                $final[self::ADDERS] = $result['adders'] ?? '-';

                if ($this->isMortgageCompany) {
                    $final[self::FEE_PERCENTAGE] = $feePercentage ?? '-';
                    $final[self::COMP_RATE] = $formattedCompRate;
                }

                $final[self::TOTAL_COMMISSION] = $result['total_commission'] ?? self::ZERO_AMOUNT;
                $final[self::TOTAL_OVERRIDE] = $result['total_override'] ?? self::ZERO_AMOUNT;
                $final[self::PAYMENT_STATUS] = $result['mark_account_status_name'] ?? '-';

                if ($this->isRecon) {
                    $final['Recon'] = $result['total_recon'] ?? self::ZERO_AMOUNT;
                }

                foreach ($result['all_milestone'] as $milestone) {
                    $header = $this->normalizeSalesListMilestoneHeader($milestone['name'] ?? null);
                    if ($header === null) {
                        continue;
                    }
                    $final[$header] = (! $milestone['is_projected'] ? $milestone['value'] : 0) ?? self::ZERO_AMOUNT;
                }

                return $final;
            });
        }

        return collect($data);
    }

    public function headings(): array
    {
        if ($this->pestCompany) {
            $final = [
                'PID',
                'Source',
                'Customer',
                self::JOB_STATUS,
                'State',
                'Location',
                'Sales Rep-1',
                'Sales Rep-2',
                'Sales Rep-1 Email',
                'Sales Rep-2 Email',
                'Approved Date',
                'Gross Value',
                self::ADDERS,
                self::TOTAL_COMMISSION,
                self::TOTAL_OVERRIDE,
                'Payment Status',
            ];
        } else {
            $final = [
                'PID',
                'Source',
                'Customer',
                self::JOB_STATUS,
                'State',
                'Location',
                ...($this->isMortgageCompany ? [
                    'MLO',
                    'Additional Rep 3',
                    'LOA',
                    'Additional Rep 4',
                    'MLO Email',
                    'Additional Rep 3 Email',
                    'LOA Email',
                    'Additional Rep 4 Email',
                ] : [
                    'Closer-1',
                    'Closer-2',
                    'Setter-1',
                    'Setter-2',
                    'Closer-1 Email',
                    'Closer-2 Email',
                    'Setter-1 Email',
                    'Setter-2 Email',
                ]),
                'Approved Date',
                $this->isMortgageCompany ? 'Loan Amount' : 'KW',
                'EPC',
                'Net EPC',
                self::ADDERS,
                ...($this->isMortgageCompany ? [self::FEE_PERCENTAGE, self::COMP_RATE] : []),
                self::TOTAL_COMMISSION,
                self::TOTAL_OVERRIDE,
                self::PAYMENT_STATUS,
            ];
        }

        if ($this->isRecon) {
            $final[] = 'Recon';
        }

        foreach ($this->salesListMilestoneHeaders() as $header) {
            $final[] = $header;
        }

        return $final;
    }

    public function registerEvents(): array
    {
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                $event->sheet->freezePane('A2');
                $this->alternateRowColorStyle($event);
                $headers = $this->headings();
                $fieldsToFormat = [
                    $this->isMortgageCompany ? 'Loan Amount' : 'KW',
                    'EPC',
                    self::TOTAL_COMMISSION,
                    self::TOTAL_OVERRIDE,
                    'Gross Value',
                ];
                // Net EPC and Adders are treated as money formatting for non-mortgage exports only.
                // Mortgage: Fee % / Comp Rate are percent strings, so do not format them as money.
                if (! $this->isMortgageCompany) {
                    $fieldsToFormat[] = 'Net EPC';
                    $fieldsToFormat[] = self::ADDERS;
                }
                if ($this->isRecon) {
                    $fieldsToFormat[] = 'Recon';
                }
                foreach ($this->salesListMilestoneHeaders() as $header) {
                    $fieldsToFormat[] = $header;
                }

                $fieldIndexes = [];
                foreach ($fieldsToFormat as $field) {
                    $index = array_search($field, $headers);
                    if ($index !== false) {
                        $fieldIndexes[$field] = $index + 1;
                    }
                }

                $rowCounter = 2;
                foreach ($collectionData as $value) {
                    foreach ($fieldIndexes as $field => $columnIndex) {
                        if (array_key_exists($field, $value)) {
                            $fieldValue = $value[$field];
                            $this->setCellValueAndStyle($event, $columnIndex, $rowCounter, $fieldValue);
                        }
                    }
                    $rowCounter++;
                }
            },
        ];
    }

    private function setCellValueAndStyle($event, $columnIndex, $rowCounter, $value)
    {
        $cell = $event->sheet->getDelegate()->getCellByColumnAndRow($columnIndex, $rowCounter);
        $cell->setValue('$ '.exportNumberFormat(floatval($value)));

        if ($value <= 0) {
            $cell->setValue('$ ('.exportNumberFormat(abs(floatval($value))).')');
            $event->sheet->getDelegate()->getStyle($cell->getCoordinate())->applyFromArray([
                'font' => [
                    'color' => ['rgb' => 'FF0000'],
                ],
            ]);
        }
    }

    private function alternateRowColorStyle($event)
    {
        $evenRowColor = 'f0f0f0';
        $oddRowColor = 'FFFFFF';

        $sheet = $event->sheet->getDelegate();
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);

        for ($row = 2; $row <= $lastRow; $row++) {
            $fillColor = $row % 2 === 0 ? $evenRowColor : $oddRowColor;

            for ($colIndex = 1; $colIndex <= $lastColumnIndex; $colIndex++) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $cell = $colLetter.$row;

                $sheet->getStyle($cell)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $fillColor],
                    ],
                    'borders' => $this->borderStyle(),
                ]);
            }
        }
    }

    private function borderStyle()
    {
        return [
            'top' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
            'bottom' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
            'left' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
            'right' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headerCount = count($this->headings());
        $lastColumnLetter = Coordinate::stringFromColumnIndex($headerCount);
        $sheet->getStyle("A1:{$lastColumnLetter}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999',
                ],
            ],
        ]);
    }

    public function title(): string
    {
        return 'Sales List';
    }
}
