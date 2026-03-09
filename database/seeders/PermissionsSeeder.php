<?php

namespace Database\Seeders;

use App\Models\Permissions;
use DB;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $policytab = [
        [
            'tab_id' => 1,
            'name' => ['setup-add', 'setup-edit', 'setup-view'],
            'guard_name' => ['Add', 'Edit', 'View'],
        ],
        [
            'tab_id' => 2,
            'name' => ['locations-add', 'locations-edit', 'locations-delete', 'locations-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 3,
            'name' => ['cost-tracking-add', 'cost-tracking-edit', 'cost-tracking-delete', 'cost-tracking-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 4,
            'name' => ['departments-add', 'departments-edit', 'departments-delete', 'departments-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 5,
            'name' => ['positions-add', 'positions-edit', 'positions-delete', 'positions-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 6,
            'name' => ['alerts-add', 'alerts-edit', 'alerts-delete', 'alerts-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 7,
            'name' => ['integrations-add', 'integrations-edit', 'integrations-delete', 'integrations-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 8,
            'name' => ['templates-add', 'templates-edit', 'templates-delete', 'templates-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 9,
            'name' => ['documents-add', 'documents-edit', 'documents-delete', 'documents-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 10,
            'name' => ['run-payroll-add', 'run-payroll-edit', 'run-payroll-delete', 'run-payroll-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 11,
            'name' => ['one-time-payment-add', 'one-time-payment-edit', 'one-time-payment-delete', 'one-time-payment-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 12,
            'name' => ['payment-request-add', 'payment-request-edit', 'payment-request-delete', 'payment-request-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 13,
            'name' => ['reconciliation-add', 'reconciliation-edit', 'reconciliation-delete', 'reconciliation-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 14,
            'name' => ['compony-add', 'compony-edit', 'compony-delete', 'compony-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 15,
            'name' => ['sales-add', 'sales-edit', 'sales-delete', 'sales-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 16,
            'name' => ['cost-add', 'cost-edit', 'cost-delete', 'cost-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 17,
            'name' => ['payroll-add', 'payroll-edit', 'payroll-delete', 'payroll-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 18,
            'name' => ['report-reconciliation-add', 'report-reconciliation-edit', 'report-reconciliation-delete', 'report-reconciliation-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 19,
            'name' => ['clawback-add', 'clawback-edit', 'clawback-delete', 'clawback-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 20,
            'name' => ['pending-install-add', 'pending-install-edit', 'pending-install-delete', 'pending-install-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 21,
            'name' => ['group-add', 'group-edit', 'group-delete', 'group-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 22,
            'name' => ['policies-add', 'policies-edit', 'policies-delete', 'policies-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 23,
            'name' => ['marketing-deal-add', 'marketing-deal-edit', 'marketing-deal-delete', 'marketing-deal-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 24,
            'name' => ['cast-tracking-add', 'cast-tracking-edit', 'cast-tracking-delete', 'cast-tracking-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 25,
            'name' => ['alert-center-add', 'alert-center-edit', 'alert-center-delete', 'alert-center-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 26,
            'name' => ['support-add', 'support-edit', 'support-delete', 'support-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 27,
            'name' => ['dashboard-add', 'dashboard-edit', 'dashboard-delete', 'dashboard-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 28,
            'name' => ['my-sales-add', 'my-sales-edit', 'my-sales-delete', 'my-sales-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 29,
            'name' => ['my-overrides-add', 'my-overrides-edit', 'my-overrides-delete', 'my-overrides-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 30,
            'name' => ['hiring-progress-add', 'hiring-progress-edit', 'hiring-progress-delete', 'hiring-progress-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 31,
            'name' => ['leads-add', 'leads-edit', 'leads-delete', 'leads-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 32,
            'name' => ['pipeline-add', 'pipeline-edit', 'pipeline-delete', 'pipeline-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 33,
            'name' => ['onboarding-employees-add', 'onboarding-employees-edit', 'onboarding-employees-delete', 'onboarding-employees-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 34,
            'name' => ['calendar-add', 'calendar-edit', 'calendar-delete', 'calendar-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 35,
            'name' => ['employee-add', 'employee-edit', 'employee-delete', 'employee-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 36,
            'name' => ['team-add', 'team-edit', 'team-delete', 'team-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 37,
            'name' => ['sequifiDocs-add', 'sequifiDocs-edit', 'sequifiDocs-delete', 'sequifiDocs-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 38,
            'name' => ['community-add', 'community-edit', 'community-delete', 'community-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 39,
            'name' => ['projections-add', 'projections-edit', 'projections-delete', 'projections-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 40,
            'name' => ['office-add', 'office-edit', 'office-delete', 'office-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 41,
            'name' => ['report-sales-add', 'report-sales-edit', 'report-sales-delete', 'report-sales-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 42,
            'name' => ['past-pay-stubs-add', 'past-pay-stubs-edit', 'past-pay-stubs-delete', 'past-pay-stubs-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 43,
            'name' => ['reports-reconciliation-add', 'reports-reconciliation-edit', 'reports-reconciliation-delete', 'reports-reconciliation-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 44,
            'name' => ['marketing-deals-add', 'marketing-deals-edit', 'marketing-deals-delete', 'marketing-deals-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 45,
            'name' => ['training-add', 'training-edit', 'training-delete', 'training-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 46,
            'name' => ['request-add', 'request-edit', 'request-delete', 'request-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 47,
            'name' => ['approvals-add', 'approvals-edit', 'approvals-delete', 'approvals-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 48,
            'name' => ['supports-add', 'supports-edit', 'supports-delete', 'supports-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 49,
            'name' => ['sequidoc-templates-add', 'sequidoc-templates-edit', 'sequidoc-templates-delete', 'sequidoc-templates-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 50,
            'name' => ['sequidoc-documents-add', 'sequidoc-documents-edit', 'sequidoc-documents-delete', 'sequidoc-documents-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 51,
            'name' => ['referrals-add', 'referrals-edit', 'referrals-delete', 'referrals-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 52,
            'name' => ['personal-info-add', 'personal-info-edit', 'personal-info-delete', 'personal-info-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 53,
            'name' => ['employment-package-add', 'employment-package-edit', 'employment-package-delete', 'employment-package-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 54,
            'name' => ['tax-info-add', 'tax-info-edit', 'tax-info-delete', 'tax-info-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 55,
            'name' => ['banking-add', 'banking-edit', 'banking-delete', 'banking-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 56,
            'name' => ['profile-documents-add', 'profile-documents-edit', 'profile-documents-delete', 'profile-documents-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],
        [
            'tab_id' => 57,
            'name' => ['network-add', 'network-edit', 'network-delete', 'network-view'],
            'guard_name' => ['Add', 'Edit', 'Delete', 'View'],
        ],

    ];

    public function run(): void
    {
        // DB::table('permissions')->truncate();
        foreach (range(1, count($this->policytab)) as $index) {
            foreach ($this->policytab[$index - 1]['name'] as $key => $value) {
                Permissions::updateOrCreate(
                    [
                        'name' => $value,
                        'guard_name' => $this->policytab[$index - 1]['guard_name'][$key],
                    ],
                    [
                        'policies_tabs_id' => $this->policytab[$index - 1]['tab_id'],
                    ]
                );

            }

        }
    }
}
