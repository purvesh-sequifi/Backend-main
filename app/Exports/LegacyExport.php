<?php

namespace App\Exports;

use App\Models\ImportExpord;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class LegacyExport implements FromCollection
{
    public function collection(): Collection
    {
        // return ImportExpord::all();
        return ImportExpord::get();

    }
}
