<?php

namespace Database\Seeders;

use App\Models\PermissionTabs;
use Illuminate\Database\Seeder;

class PermissionSubModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $id = [
        '2', '2', '3', '3', '3', '4', '5', '5', '5', '7', '8', '8', '8', '8', '8', '10', '10', '10', '10',
    ];

    private $names = [
        'MY SALES',
        'MY OVERRIDES',
        'HIRING PROGRESS',
        'LEADS',
        'ONBOARDING EMPLOYEES',
        'CALENDAR',
        'EMPLOYEES',
        'TEAMS',
        'SequiDocs',
        'PROJECTIONS',
        'OFFICE',
        'SALES',
        'PAY STUBS',
        'RECONCILIATIONS',
        'MARKETING DEAL',
        'REQUESTS',
        'APPROVALS',
        'BONUS / INCENTIVES',
        'FINE / FEE',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->names)) as $index) {
            PermissionTabs::create([
                'module_id' => $this->id[$index - 1],
                'module_tab' => $this->names[$index - 1],
                'status' => 1,
            ]);

        }
    }
}
