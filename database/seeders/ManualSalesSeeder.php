<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Products;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManualSalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // 1. Ensure we have test users (closers/setters)
            $closer1 = User::firstOrCreate(
                ['email' => 'test.closer1@sequifi.com'],
                [
                    'first_name' => 'Test',
                    'last_name' => 'Closer One',
                    'password' => Hash::make('password'),
                    'is_active' => '1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $closer2 = User::firstOrCreate(
                ['email' => 'test.closer2@sequifi.com'],
                [
                    'first_name' => 'Test',
                    'last_name' => 'Closer Two',
                    'password' => Hash::make('password'),
                    'is_active' => '1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $setter1 = User::firstOrCreate(
                ['email' => 'test.setter1@sequifi.com'],
                [
                    'first_name' => 'Test',
                    'last_name' => 'Setter One',
                    'password' => Hash::make('password'),
                    'is_active' => '1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 2. Ensure we have test products
            $product1 = Products::firstOrCreate(
                ['product_id' => 'TEST_PRODUCT_001'],
                [
                    'name' => 'Test Product One',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $product2 = Products::firstOrCreate(
                ['product_id' => 'TEST_PRODUCT_002'],
                [
                    'name' => 'Test Product Two',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Get default product
            $defaultProduct = Products::where('product_id', config('global_vars.DEFAULT_PRODUCT_ID', 'DBP'))->first();

            // 3. Ensure we have states
            $stateAL = State::firstOrCreate(
                ['state_code' => 'AL'],
                [
                    'name' => 'Alabama',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $stateCA = State::firstOrCreate(
                ['state_code' => 'CA'],
                [
                    'name' => 'California',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $stateTX = State::firstOrCreate(
                ['state_code' => 'TX'],
                [
                    'name' => 'Texas',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 4. Create test manual sales data
            $testSales = [
                [
                    'pid' => 'TEST_MANUAL_001',
                    'customer_name' => 'John Doe',
                    'customer_state' => 'AL',
                    'state_id' => $stateAL->id,
                    'customer_email' => 'john.doe@example.com',
                    'customer_phone' => '555-0001',
                    'product_id' => $product1->id,
                    'product_code' => $product1->product_id,
                    'gross_account_value' => 5000.00,
                    'net_epc' => 2000.00,
                    'epc' => 100.00,
                    'kw' => 50,
                    'approved_date' => '2025-11-20',
                    'customer_signoff' => '2025-11-20',
                    'sales_rep_name' => $closer1->first_name . ' ' . $closer1->last_name,
                    'sales_rep_email' => $closer1->email,
                    'data_source_type' => 'manual',
                    'closer1_id' => $closer1->id,
                    'closer2_id' => null,
                    'setter1_id' => $setter1->id,
                    'setter2_id' => null,
                ],
                [
                    'pid' => 'TEST_MANUAL_002',
                    'customer_name' => 'Jane Smith',
                    'customer_state' => 'CA',
                    'state_id' => $stateCA->id,
                    'customer_email' => 'jane.smith@example.com',
                    'customer_phone' => '555-0002',
                    'product_id' => $product2->id,
                    'product_code' => $product2->product_id,
                    'gross_account_value' => 7500.00,
                    'net_epc' => 3000.00,
                    'epc' => 150.00,
                    'kw' => 75,
                    'approved_date' => '2025-11-21',
                    'customer_signoff' => '2025-11-21',
                    'sales_rep_name' => $closer2->first_name . ' ' . $closer2->last_name,
                    'sales_rep_email' => $closer2->email,
                    'data_source_type' => 'manual',
                    'closer1_id' => $closer2->id,
                    'closer2_id' => $closer1->id,
                    'setter1_id' => $setter1->id,
                    'setter2_id' => null,
                ],
                [
                    'pid' => 'TEST_MANUAL_003',
                    'customer_name' => 'Bob Johnson',
                    'customer_state' => 'TX',
                    'state_id' => $stateTX->id,
                    'customer_email' => 'bob.johnson@example.com',
                    'customer_phone' => '555-0003',
                    'product_id' => $defaultProduct?->id ?? $product1->id,
                    'product_code' => $defaultProduct?->product_id ?? $product1->product_id,
                    'gross_account_value' => 10000.00,
                    'net_epc' => 4000.00,
                    'epc' => 200.00,
                    'kw' => 100,
                    'approved_date' => '2025-11-22',
                    'customer_signoff' => '2025-11-22',
                    'm1_date' => '2025-11-25',
                    'sales_rep_name' => $closer1->first_name . ' ' . $closer1->last_name,
                    'sales_rep_email' => $closer1->email,
                    'data_source_type' => 'manual',
                    'closer1_id' => $closer1->id,
                    'closer2_id' => null,
                    'setter1_id' => null,
                    'setter2_id' => null,
                ],
            ];

            foreach ($testSales as $saleData) {
                // Check if sale already exists
                $existingSale = SalesMaster::where('pid', $saleData['pid'])->first();

                if (!$existingSale) {
                    // Extract process data
                    $closer1Id = $saleData['closer1_id'];
                    $closer2Id = $saleData['closer2_id'];
                    $setter1Id = $saleData['setter1_id'];
                    $setter2Id = $saleData['setter2_id'];

                    unset($saleData['closer1_id'], $saleData['closer2_id'], $saleData['setter1_id'], $saleData['setter2_id']);

                    // Create sales master
                    $saleMaster = SalesMaster::create($saleData);

                    // Create sale master process
                    SaleMasterProcess::create([
                        'sale_master_id' => $saleMaster->id,
                        'pid' => $saleData['pid'],
                        'weekly_sheet_id' => $saleMaster->weekly_sheet_id,
                        'closer1_id' => $closer1Id,
                        'closer2_id' => $closer2Id,
                        'setter1_id' => $setter1Id,
                        'setter2_id' => $setter2Id,
                        'data_source_type' => 'manual',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($this->command) {
                        $this->command->info("Created manual sale: {$saleData['pid']}");
                    }
                } else {
                    if ($this->command) {
                        $this->command->warn("Sale {$saleData['pid']} already exists, skipping...");
                    }
                }
            }

            DB::commit();

            if ($this->command) {
                $this->command->info('Manual sales seeder completed successfully!');
                $this->command->info('Test Users Created/Found:');
                $this->command->info("  - Closer 1: {$closer1->email} (ID: {$closer1->id})");
                $this->command->info("  - Closer 2: {$closer2->email} (ID: {$closer2->id})");
                $this->command->info("  - Setter 1: {$setter1->email} (ID: {$setter1->id})");
                $this->command->info('Test Products Created/Found:');
                $this->command->info("  - Product 1: {$product1->product_id} (ID: {$product1->id})");
                $this->command->info("  - Product 2: {$product2->product_id} (ID: {$product2->id})");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            if ($this->command) {
                $this->command->error('Manual sales seeder failed: ' . $e->getMessage());
                $this->command->error('File: ' . $e->getFile());
                $this->command->error('Line: ' . $e->getLine());
            }
            throw $e;
        }
    }
}

