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

class SalesPidDetailsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $data;

    private $companyProfile;

    private $triggerDate;

    private $pestCompany;
    private bool $isMortgageCompany;

    private const CUSTOMER_PHONE = 'Customer Phone';

    private const ZERO_AMOUNT = '$0.00';
    private const FEE_PERCENTAGE = 'Fee %';

    public function __construct($data, $companyProfile)
    {
        $this->data = $data;
        $this->companyProfile = $companyProfile;
        $this->triggerDate = getTriggerDatesForSample();
        $this->pestCompany = in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $this->isMortgageCompany = $this->companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE;
    }

    /**
     * Sales Pid Details-only milestone header normalization.
     * - Rename "Final Payment" => "M2 Date"
     * - Rename "M3" => "M3 Date"
     * - Remove "M2" and "M2 Date" (to avoid duplicates)
     */
    private function normalizePidDetailsMilestoneHeader(?string $name): ?string
    {
        $name = $name !== null ? trim($name) : null;
        if ($name === null || $name === '') {
            return null;
        }

        return match ($name) {
            'M2', 'M2 Date' => null,
            'Final Payment' => 'M2 Date',
            'M3' => 'M3 Date',
            default => $name,
        };
    }

    /**
     * Normalize milestone names for matching (handles whitespace / NBSP / case differences).
     */
    private function normalizeMilestoneNameForMatch(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        // Replace non-breaking spaces and collapse whitespace
        $name = str_replace("\u{00A0}", ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        if ($name === '' || $name === null) {
            return null;
        }

        return mb_strtolower($name);
    }

    /**
     * Returns the milestone column map for the Sales Pid Details sheet (normalized, filtered).
     * Order is preserved from triggerDate (SchemaTriggerDate) so headings + row values align.
     *
     * @return array<int, array{source: string, header: string, keys: array<int, string>}>
     */
    private function pidDetailsMilestoneColumnMap(): array
    {
        $map = [];
        foreach ($this->triggerDate as $date) {
            $source = isset($date['name']) ? trim((string) $date['name']) : null;
            if ($source === null || $source === '') {
                continue;
            }
            $header = $this->normalizePidDetailsMilestoneHeader($source);
            if ($header === null) {
                continue;
            }

            // Build candidate match keys (to handle legacy naming differences).
            // For example, some datasets may store "M3 Date" while triggers are "M3" (or vice versa).
            $keys = [];
            $pushKey = function (?string $name) use (&$keys): void {
                $k = $this->normalizeMilestoneNameForMatch($name);
                if ($k !== null && ! in_array($k, $keys, true)) {
                    $keys[] = $k;
                }
            };

            // Always include the original trigger name as a candidate.
            $pushKey($source);

            // Special aliases required by business mapping:
            // - "Final Payment" is displayed as "M2 Date", but underlying data may still be "Final Payment" or already "M2 Date"/"M2".
            // - "M3" is displayed as "M3 Date", but underlying data may be "M3" or "M3 Date".
            if ($header === 'M2 Date') {
                $pushKey('Final Payment');
                $pushKey('M2 Date');
                $pushKey('M2');
            }
            if ($header === 'M3 Date') {
                $pushKey('M3');
                $pushKey('M3 Date');
            }

            if ($keys === []) {
                continue;
            }

            $map[] = ['source' => $source, 'header' => $header, 'keys' => $keys];
        }

        return $map;
    }

    /**
     * Build an index of milestone dates keyed by normalized name.
     *
     * @return array<string, string|null>
     */
    private function milestoneDateIndex(array $milestones): array
    {
        $index = [];
        foreach ($milestones as $m) {
            $key = $this->normalizeMilestoneNameForMatch($m['name'] ?? null);
            if ($key === null) {
                continue;
            }
            $index[$key] = ! ($m['is_projected'] ?? false) ? ($m['date'] ?? null) : null;
        }

        return $index;
    }

    private function milestoneDateFromIndexByKeys(array $milestoneIndex, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $milestoneIndex) && $milestoneIndex[$key]) {
                return $milestoneIndex[$key];
            }
        }

        return null;
    }

    public function registerEvents1(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->freezePane('A2');
            },
        ];
    }

    public function collection()
    {
        $data = $this->data;
        $data = collect($data);
        if ($this->pestCompany) {
            $data->transform(function ($pidDetails) {
                $final = [
                    'PID' => $pidDetails['pid'] ?? '-',
                    'Customer Name' => $pidDetails['customer_name'] ?? '-',
                    'Prospect ID' => $pidDetails['pid'] ?? '-',
                    'Customer Address' => $pidDetails['customer_address'] ?? '-',
                    'Homeowner ID' => $pidDetails['homeowner_id'] ?? '-',
                    'Customer Address2' => $pidDetails['customer_address_2'] ?? '-',
                    'Closer-1' => $pidDetails['closer_1'] ?? '-',
                    'Closer-2' => $pidDetails['closer_2'] ?? '-',
                    'Proposal ID' => $pidDetails['proposal_id'] ?? '-',
                    'Customer City' => ucwords($pidDetails['customer_city']) ?? '-',
                    'Product' => $pidDetails['product'] ?? '-',
                    'Product Code' => $pidDetails['product_id'] ?? '-',
                    'Customer State' => ucwords($pidDetails['state']) ?? '-',
                    'Gross Account Value' => $pidDetails['gross_account_value'] ?? '-',
                    'Location Code' => $pidDetails['location_code'] ?? '-',
                    'Installer' => $pidDetails['installer'] ?? '-',
                    'Customer Zip' => $pidDetails['customer_zip'] ?? '-',
                    'Customer Email' => $pidDetails['customer_email'] ?? '-',
                    self::CUSTOMER_PHONE => $pidDetails['customer_phone'] ?? '-',
                    'Approved Date' => $pidDetails['customer_signoff'] ?? '-',
                    'Dealer Fee %' => $pidDetails['dealer_fee_percentage'] ?? '-',
                    'Dealer Fee $' => $pidDetails['dealer_fee_amount'] ?? '-',
                    'SOW ' => $pidDetails['show'] ?? '-',
                    'Cancel Date' => $pidDetails['date_cancelled'] ?? '-',
                ];

                $milestones = $pidDetails['all_milestone'] ?? [];
                $milestoneIndex = $this->milestoneDateIndex($milestones);
                foreach ($this->pidDetailsMilestoneColumnMap() as $col) {
                    $final[$col['header']] = $this->milestoneDateFromIndexByKeys($milestoneIndex, $col['keys']);
                }

                return $final;
            });
        } else {
            $data->transform(function ($pidDetails) {
                $netEpc = $pidDetails['net_epc'] ?? null;
                $feePercentage = null;
                $formattedCompRate = '-';
                if ($this->isMortgageCompany && is_numeric($netEpc)) {
                    $feePercentage = number_format(((float) $netEpc) * 100, 4, '.', '').'%';
                }
                if ($this->isMortgageCompany && is_numeric($pidDetails['comp_rate'] ?? null)) {
                    $formattedCompRate = number_format((float) $pidDetails['comp_rate'], 4, '.', '').'%';
                }
                $final = [
                    'PID' => $pidDetails['pid'] ?? '-',
                    'Customer Name' => ucfirst($pidDetails['customer_name']) ?? '-',
                    'Prospect ID' => $pidDetails['pid'] ?? '-',
                    'Customer Address' => $pidDetails['customer_address'] ?? '-',
                    'Homeowner ID' => $pidDetails['homeowner_id'] ?? '-',
                    'Customer Address 2' => $pidDetails['customer_address_2'] ?? '-',
                ];

                // Mortgage-specific worker labels
                if ($this->isMortgageCompany) {
                    $final['MLO'] = $pidDetails['closer_1'] ?? '-';
                    $final['Additional Rep 3'] = $pidDetails['closer_2'] ?? '-';
                    $final['LOA'] = $pidDetails['setter_1'] ?? '-';
                    $final['Additional Rep 4'] = $pidDetails['setter_2'] ?? '-';
                } else {
                    $final['Closer-1'] = $pidDetails['closer_1'] ?? '-';
                    $final['Closer-2'] = $pidDetails['closer_2'] ?? '-';
                    $final['Setter-1'] = $pidDetails['setter_1'] ?? '-';
                    $final['Setter-2'] = $pidDetails['setter_2'] ?? '-';
                }

                $final['Proposal ID'] = $pidDetails['proposal_id'] ?? '-';
                $final['Customer City'] = ucwords($pidDetails['customer_city']) ?? '-';
                $final['Product'] = $pidDetails['product'] ?? '-';
                $final['Product Code'] = $pidDetails['product_id'] ?? '-';
                $final['Customer State'] = ucwords($pidDetails['state']) ?? '-';
                $final['Gross Account Value'] = $pidDetails['gross_account_value'] ?? '-';
                $final['Location Code'] = ucwords($pidDetails['location_code']) ?? '-';
                $final['Installer'] = $pidDetails['installer'] ?? '-';
                $final['Customer Zip'] = $pidDetails['customer_zip'] ?? '-';
                $final[$this->isMortgageCompany ? 'Loan Amount' : 'KW'] = $pidDetails['kw'] ?? self::ZERO_AMOUNT;
                $final['Customer Email'] = $pidDetails['customer_email'] ?? '-';
                $final['EPC'] = $pidDetails['epc'] ?? self::ZERO_AMOUNT;
                $final[self::CUSTOMER_PHONE] = $pidDetails['customer_phone'] ?? '-';
                $final['Net EPC'] = $pidDetails['net_epc'] ?? self::ZERO_AMOUNT;

                if ($this->isMortgageCompany) {
                    $final[self::FEE_PERCENTAGE] = $feePercentage ?? '-';
                    $final['Comp Rate'] = $formattedCompRate;
                }

                $final['Approved Date'] = $pidDetails['customer_signoff'] ?? '-';
                $final['Dealer Fee %'] = $pidDetails['dealer_fee_percentage'] ?? '-';
                $final['Dealer Fee $'] = $pidDetails['dealer_fee_amount'] ?? '-';
                $final['SOW '] = $pidDetails['show'] ?? '-';
                $final['Cancel Date'] = $pidDetails['date_cancelled'] ?? '-';

                $milestones = $pidDetails['all_milestone'] ?? [];
                $milestoneIndex = $this->milestoneDateIndex($milestones);
                foreach ($this->pidDetailsMilestoneColumnMap() as $col) {
                    $final[$col['header']] = $this->milestoneDateFromIndexByKeys($milestoneIndex, $col['keys']);
                }

                return $final;
            });
        }

        return collect($data);
    }

    public function headings(): array
    {
        if (in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $final = [
                'PID',
                'Customer Name',
                'Prospect ID',
                'Customer Address',
                'Homeowner ID',
                'Customer Address2',
                'Closer-1',
                'Closer-2',
                'Proposal ID',
                'Customer City',
                'Product',
                'Product Code',
                'Customer State',
                'Gross Account Value',
                'Location Code',
                'Installer',
                'Customer Zip',
                'Customer Email',
                self::CUSTOMER_PHONE,
                'Approved Date',
                'Dealer Fee %',
                'Dealer Fee $',
                'SOW',
                'Cancel Date',
            ];
        } else {
            $final = [
                'PID',
                'Customer Name',
                'Prospect ID',
                'Customer Address',
                'Homeowner ID',
                'Customer Address2',
                ...($this->isMortgageCompany ? [
                    'MLO',
                    'Additional Rep 3',
                    'LOA',
                    'Additional Rep 4',
                ] : [
                    'Closer-1',
                    'Closer-2',
                    'Setter-1',
                    'Setter-2',
                ]),
                'Proposal ID',
                'Customer City',
                'Product',
                'Product Code',
                'Customer State',
                'Gross Account Value',
                'Location Code',
                'Installer',
                'Customer Zip',
                $this->isMortgageCompany ? 'Loan Amount' : 'KW',
                'Customer Email',
                'EPC',
                self::CUSTOMER_PHONE,
                'Net EPC',
                ...($this->isMortgageCompany ? [self::FEE_PERCENTAGE, 'Comp Rate'] : []),
                'Approved Date',
                'Dealer Fee %',
                'Dealer Fee $',
                'SOW',
                'Cancel Date',
            ];
        }

        foreach ($this->pidDetailsMilestoneColumnMap() as $col) {
            $final[] = $col['header'];
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
                    'Net EPC',
                    'Gross Account Value',
                ];

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
                        $fieldValue = $value[$field];
                        $this->setCellValueAndStyle($event, $columnIndex, $rowCounter, $fieldValue);
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
            $event->sheet->getDelegate()->getStyle($cell->getCoordinate())
                ->applyFromArray([
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
        return 'Sales Pid Details';
    }
}
