<?php

namespace Database\Seeders;

use App\Models\PermissionModules;
use Illuminate\Database\Seeder;

class PermissionModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $names = [
        'Dashboard',
        'My Sales',
        'Hirring',
        'Calendar',
        'Management',
        'Community',
        'Projections',
        'Reports',
        'Training',
        'Requests & Approvals',
        'Support',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->names)) as $index) {
            PermissionModules::create([
                'module' => $this->names[$index - 1],
                'status' => 1,
            ]);

        }
    }
}
