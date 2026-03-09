<?php

namespace Database\Seeders;

use App\Models\GroupPolicies;
use Illuminate\Database\Seeder;

class GroupPoliciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $role = [
        1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, // 11
        2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, // 24
    ];

    private $policy = [
        'Dashboard',
        'Setting',
        'Integrations',
        'SequiDocs',
        'Payroll',
        'Reports',
        'Permissions',
        'Marketing Deals',
        'Import/Export',
        'Alerts Center',
        'Support',  // end of 1  #11
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
        'SequiDocs',
        'Referrals', // 24
    ];

    public function run(): void
    {
        foreach (range(1, count($this->policy)) as $index) {
            GroupPolicies::create([
                'role_id' => $this->role[$index - 1],
                'policies' => $this->policy[$index - 1],
            ]);

        }
        GroupPolicies::create([
            'role_id' => 3,
            'policies' => 'Profile',
        ]);
    }
}
