<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Department::create([
            'name' => 'Management',
            'parent_id' => null,
        ]);
        Department::create([
            'name' => 'Sales',
            'parent_id' => null,
        ]);
    }
}
