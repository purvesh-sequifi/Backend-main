<?php

namespace Database\Seeders;

use App\Models\AdditionalInfoForEmployeeToGetStarted;
use Illuminate\Database\Seeder;

class AdditionalInfoForEmployeeToGetStartedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AdditionalInfoForEmployeeToGetStarted::create([
            'configuration_id' => 1,
            'field_name' => 'Shirt Size',
            'field_type' => 'dropdown',
            'field_required' => 'required',
            'attribute_option' => '[\"Extra Small\",\"Small\",\"Medium\",\"Large\",\"Extra Large\",\"XXL\"]',
            'is_deleted' => 0,
        ]);
    }
}
