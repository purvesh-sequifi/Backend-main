<?php

namespace Database\Seeders;

use App\Models\OverridesType;
use Illuminate\Database\Seeder;

class OverridetypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OverridesType::create([
            'overrides_type' => 'Direct Overrides',
            // 'lock_pay_out_type'   => '',
            // 'max_limit' =>  '',
            // 'parsonnel_limit'   => '',
            // 'min_position' =>  '',
            // 'level'   => '',
            'override_setting_id' => '1',
            // 'is_check'  => '',
        ]);
        OverridesType::create([
            'overrides_type' => 'Indirect Overrides',
            'lock_pay_out_type' => 'Par Kw',
            'max_limit' => '100',
            'parsonnel_limit' => '5',
            'min_position' => 'Closer',
            'level' => '1',
            'override_setting_id' => '1',
            'is_check' => '1',
        ]);
        OverridesType::create([
            'overrides_type' => 'Office Overrides',
            'lock_pay_out_type' => 'Office Overrides',
            'max_limit' => '100',
            'parsonnel_limit' => '80',
            'min_position' => 'Manager',
            'level' => '1',
            'override_setting_id' => '1',
            'is_check' => '1',
        ]);

        OverridesType::create([
            'overrides_type' => 'Office Stack Overrides',
            'lock_pay_out_type' => 'Office Stack Overrides',
            'max_limit' => '100',
            'parsonnel_limit' => '80',
            'min_position' => 'Manager',
            'level' => '1',
            'override_setting_id' => '1',
            'is_check' => '1',
        ]);
    }
}
