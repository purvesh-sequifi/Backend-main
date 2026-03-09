<?php

namespace App\Exports\Sale;

use App\Exports\Sale\Factory\UniversalSampleDataFactory;
use App\Models\CompanyProfile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SampleSaleExport implements FromArray, WithColumnFormatting, WithHeadings, WithStyles
{
    private array $headers;

    private array $requiredColumnLetters;

    private array $dateColumnLetters;

    private array $data;

    public function __construct(array $triggerDate, CompanyProfile $companyProfile, int $templateId)
    {
        $structure = SalesTemplateStructureResolver::resolve($companyProfile, $templateId);

        //        $triggerNames = array_column($triggerDate, 'name');
        //        $this->headers = array_values(array_unique(array_merge(
        //            $structure->headers,
        //            array_diff($triggerNames, $structure->headers)
        //        )));

        $this->headers = array_values($structure->headers);
        $this->requiredColumnLetters = $this->convertIndexesToLetters($structure->requiredColumnIndexes);
        $this->dateColumnLetters = $this->convertIndexesToLetters($structure->dateColumnIndexes);

        $sampleAssoc = UniversalSampleDataFactory::getSampleData();
        $this->data = [$this->buildRowByHeaders($this->headers, $sampleAssoc)];
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function styles(Worksheet $sheet)
    {
        foreach ($this->headers as $index => $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);

            if (in_array($columnLetter, $this->requiredColumnLetters, true)) {
                $sheet->getStyle($columnLetter.'1')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'fdff00'],
                    ],
                ]);
            }

            $sheet->getColumnDimension($columnLetter)->setWidth(strlen($header) + 5);
        }

        $sheet->getStyle('1:1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => '000000']],
        ]);

        return [];
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->dateColumnLetters as $columnLetter) {
            $formats[$columnLetter] = NumberFormat::FORMAT_DATE_YYYYMMDD;
        }

        return $formats;
    }

    private function convertIndexesToLetters(array $indexes): array
    {
        return array_map(function ($i) {
            return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        }, $indexes);
    }

    private function buildRowByHeaders(array $headers, array $sampleAssoc): array
    {
        $row = [];
        foreach ($headers as $header) {
            $norm = $this->normalizeHeader($header);
            $internal = $this->headerToInternalName($norm);
            $row[] = $sampleAssoc[$internal] ?? '';
        }

        return $row;
    }

    private function normalizeHeader(string $header): string
    {
        $replaceMap = [
            ' ' => '_',
            '%' => 'percent',
            '$' => 'dollar',
            '&' => 'and',
            '#' => 'number',
            '/' => '_',
            '-' => '_',
            '+' => 'plus',
            ',' => '',
            ':' => '',
        ];
        $normalized = strtr(strtolower(trim($header)), $replaceMap);

        return preg_replace('/[^a-z0-9_]/', '', $normalized);
    }

    private function headerToInternalName(string $normalizedHeader): string
    {
        static $aliases = [
            'customer_address2' => 'customer_address_2',
            'closer_1' => 'closer1_id',
            'closer_2' => 'closer2_id',
            'approved_date' => 'customer_signoff',
            'sow' => 'adders_description',
            'product' => 'product_id',
            'product_code' => 'product_code',
            'm1_date' => 'm1_date',
            'm2_date' => 'm2_date',
            'advanced_payment_10percent' => 'advanced_payment_10_percent',
            'next_40percent_payment' => 'next_40_percent_payment',
            'final_payment' => 'final_payment',
        ];

        return $aliases[$normalizedHeader] ?? $normalizedHeader;
    }
}
