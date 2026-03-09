<?php

namespace App\Exports\ExportPayroll;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PayrollCustomExport implements FromCollection, WithHeadings, WithTitle
{
    private $data;

    private $title;

    private $column;

    public function __construct($column, $data, $title)
    {
        $this->column = $column;
        $this->data = $data;
        $this->title = $title;
    }

    /**
     * @return string[]
     */
    public function headings(): array
    {
        return $this->column;

    }

    public function collection(): Collection
    {
        return new Collection([$this->data]);
    }

    public function title(): string
    {
        return $this->title;
    }
}
