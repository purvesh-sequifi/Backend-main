<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $type = [
        'Offer Letter',
        'Rent AgreeMent',
        'Employee Contract',
        'Signed Appointment Letter',

    ];

    public function run(): void
    {
        // foreach(range(1, sizeOf($this->type)) as $index)
        // {
        //     DocumentType::create([
        //         'document_type' => $this->type[$index-1],
        //     ]);

        // }

    }
}
