<?php

namespace Database\Seeders;

use App\Models\CompanyType;
use Illuminate\Database\Seeder;

class CompanyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanyType::create([
            'company_name' => 'finance',

        ]);
        CompanyType::create([
            'company_name' => 'IT',
        ]);
    }
}
