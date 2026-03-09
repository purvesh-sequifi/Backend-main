<?php

namespace Database\Seeders;

use App\Models\CostCenter;
use Illuminate\Database\Seeder;

class CostCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CostCenter::create([
            'name' => 'Travel',
            // 'parent_id'  => '',
            'code' => 'TRAVEL-001',
            'description' => 'TRAVEL-001',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Flights',
            'parent_id' => 1,
            'code' => 'FLIGHTS',
            'description' => 'FLIGHTS',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Trains',
            'parent_id' => 1,
            'code' => 'ABC-003',
            'description' => 'ABC-003',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Advertising',
            // 'parent_id'  => '',
            'code' => 'AADV-001',
            'description' => 'ADV-001',
            'status' => 0,
        ]);
        CostCenter::create([
            'name' => 'Employee Benefits',
            // 'parent_id'  => '',
            'code' => 'EMP-001',
            'description' => 'Employee Benefits',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Meal & entertainsment',
            // 'parent_id'  => '',
            'code' => 'MEA-001',
            'description' => 'Meal & entertainsment',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Office Costs',
            // 'parent_id'  => '',
            'code' => 'OFC-001',
            'description' => 'Office Costs',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Professional Services',
            // 'parent_id'  => '',
            'code' => 'PRO-001',
            'description' => 'Professional Services',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Rent',
            // 'parent_id'  => '',
            'code' => 'REN-001',
            'description' => 'Rent',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Utilities',
            // 'parent_id'  => '',
            'code' => 'UTI-001',
            'description' => 'Utilities',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Training & Education',
            // 'parent_id'  => '',
            'code' => 'TRN-001',
            'description' => 'Training & Education',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Business Insurance',
            // 'parent_id'  => '',
            'code' => 'BUS-001',
            'description' => 'Business Insurance',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Loan & Interest',
            // 'parent_id'  => '',
            'code' => 'LOA-001',
            'description' => 'Loan & Interest',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Bad Debt',
            // 'parent_id'  => '',
            'code' => 'BAD-001',
            'description' => 'Bad Debt',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Miscellaneous',
            // 'parent_id'  => '',
            'code' => 'MIS-001',
            'description' => 'Miscellaneous',
            'status' => 1,
        ]);
        CostCenter::create([
            'name' => 'Phone Bill',
            // 'parent_id'  => '',
            'code' => 'PHN-001',
            'description' => 'Phone Bill',
            'status' => 1,
        ]);
    }
}
