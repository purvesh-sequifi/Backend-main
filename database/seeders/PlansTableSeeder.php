<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('plans')->insert([
            [
                'id' => 1,
                'name' => 'Every Unique, M2 Complete PID',
                'product_name' => 'sequifi',
                'price' => '0.01',
                'discount_price' => '0.0025',
                'retail_price' => '0.01',
                'discount_retail_price' => '0.005',
                'created_at' => '2023-08-23 10:42:43',
                'updated_at' => null,
            ],
            [
                'id' => 2,
                'name' => 'Every Unique, M2 Complete PID',
                'product_name' => 'S Clearance',
                'price' => '0.01',
                'discount_price' => '0.005',
                'retail_price' => '0.01',
                'discount_retail_price' => '0.005',
                'created_at' => '2023-11-23 06:02:43',
                'updated_at' => null,
            ],
            [
                'id' => 3,
                'name' => '$2 for EVERY check sent to everee',
                'product_name' => 'Sequipay',
                'price' => '2',
                'discount_price' => '0',
                'retail_price' => '2',
                'discount_retail_price' => '0',
                'created_at' => '2023-11-23 06:02:43',
                'updated_at' => null,
            ],
        ]);
    }
}
