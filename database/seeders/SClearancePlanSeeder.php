<?php

namespace Database\Seeders;

use App\Models\SClearancePlan;
use Illuminate\Database\Seeder;

class SClearancePlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = $this->data();

        foreach ($data as $value) {
            SClearancePlan::create([
                'bundle_id' => $value['bundle_id'],
                'plan_name' => $value['plan_name'],
                'price' => $value['price'],
            ]);
        }
    }

    public function data()
    {
        return [
            ['bundle_id' => '5', 'plan_name' => 'Basic', 'price' => '25'],
            ['bundle_id' => '6', 'plan_name' => 'Plus', 'price' => '40'],
            ['bundle_id' => '7', 'plan_name' => 'Pro', 'price' => '60'],
        ];
    }
}
