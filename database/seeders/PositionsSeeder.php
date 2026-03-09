<?php

namespace Database\Seeders;

use App\Models\Positions;
use Illuminate\Database\Seeder;

class PositionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Positions::create([
            'position_name' => 'Manager',
            'department_id' => 1,
            'group_id' => 1,
            'is_manager' => 1,
            'order_by' => 1,
            'setup_status' => 0,
        ]);
        Positions::create([
            'position_name' => 'Closer',
            'department_id' => 2,
            'group_id' => 3,
            'is_manager' => 1,
            'order_by' => 2,
            'setup_status' => 0,
        ]);
        Positions::create([
            'position_name' => 'Setter',
            'department_id' => 2,
            'group_id' => 4,
            'is_manager' => 1,
            'is_stack' => 1,
            'order_by' => 3,
            'setup_status' => 0,
        ]);
        // Positions::create([
        //     'position_name'  =>  'junior Setter',
        //     'department_id'  =>  2,
        //     // 'parent_id'   => 3,
        //     'group_id' =>4,
        //     'is_manager' => 1,
        //     'order_by'   => 4,
        //     'setup_status'=> 1,
        // ]);
    }
}
