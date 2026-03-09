<?php

namespace Database\Seeders;

use App\Models\PoliciesTabs;
use Illuminate\Database\Seeder;

class PoliciesTabsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $policytab = [
        [
            'policy_id' => 2,
            'policy_tab' => ['Setup', 'Locations', 'Cost Traking', 'Departments', 'Positions', 'Alerts'],
        ],
        [
            'policy_id' => 3,
            'policy_tab' => ['Integrations'],
        ],
        [
            'policy_id' => 4,
            'policy_tab' => ['Templates', 'Documents'],
        ],
        [
            'policy_id' => 5,
            'policy_tab' => ['Run Payroll & Approvals', 'One-time Payment', 'Payment Request', 'Reconciliation'],
        ],
        [
            'policy_id' => 6,
            'policy_tab' => ['Compony', 'Sales', 'Cost', 'Payroll', 'Reconciliation', 'Clawback', 'Pending Install'],
        ],
        [
            'policy_id' => 7,
            'policy_tab' => ['Group', 'Policies'],
        ],
        [
            'policy_id' => 8,
            'policy_tab' => ['Marketing Deal', 'Cast Tracking'],
        ],
        [
            'policy_id' => 10,
            'policy_tab' => ['Alerts'],
        ],
        [
            'policy_id' => 11,
            'policy_tab' => ['Support'],
        ],
        [
            'policy_id' => 12,
            'policy_tab' => ['Dashboard'],
        ],
        [
            'policy_id' => 13,
            'policy_tab' => ['My Sales', 'My Overrides'],
        ],
        [
            'policy_id' => 14,
            'policy_tab' => ['Hiring Progress', 'Leads', 'Pipeline', 'Onboarding Employees'],
        ],
        [
            'policy_id' => 15,
            'policy_tab' => ['Calendar'],
        ],
        [
            'policy_id' => 16,
            'policy_tab' => ['Employee', 'Team', 'SequifiDocs'],
        ],
        [
            'policy_id' => 17,
            'policy_tab' => ['Community'],
        ],
        [
            'policy_id' => 18,
            'policy_tab' => ['Projections'],
        ],
        [
            'policy_id' => 19,
            'policy_tab' => ['Office', 'Sales', 'Past Pay Stubs', 'Reconciliation', 'Marketing Deal'],
        ],
        [
            'policy_id' => 20,
            'policy_tab' => ['Training'],
        ],
        [
            'policy_id' => 21,
            'policy_tab' => ['Request', 'Approvals'],
        ],
        [
            'policy_id' => 22,
            'policy_tab' => ['Support'],
        ],
        [
            'policy_id' => 23,
            'policy_tab' => ['Templates', 'Documents'],
        ],
        [
            'policy_id' => 24,
            'policy_tab' => ['Referrals'],
        ],
        [
            'policy_id' => 25,
            'policy_tab' => ['Personal Info', 'Employment Package', 'Tax Info', 'Banking', 'Documents', 'Network'],
        ],

    ];

    public function run(): void
    {
        foreach (range(1, count($this->policytab)) as $index) {
            foreach ($this->policytab[$index - 1]['policy_tab'] as $key => $value) {
                PoliciesTabs::create([
                    'policies_id' => $this->policytab[$index - 1]['policy_id'],
                    'tabs' => $value,
                ]);

            }

        }
    }
}
