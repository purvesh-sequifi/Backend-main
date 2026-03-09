<?php

namespace Database\Seeders;

use App\Models\PermissionSubModules;
use Illuminate\Database\Seeder;

class ModuleWithPermissionMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $moduleid = [
        '2', '2', '2', '2', '2', '2', '2',
        '2', '2', '2', '2', '2', '2', '2',
        '3', '3', '3', '3', '3',
        '3', '3', '3', '3',
        '3', '3', '3', '3', // '3','3','3',
        '4', '4', '4', '4', '4', '4',
        '5', '5', '5',
        '5', '5', '5', '5', '5', '5',
        '5', '5', '5', '5', '5', '5', '5', '5', '5', '5', '5', '5',
        '7', '7', '7', '7',
        '8', '8', '8', '8', '8', '8',
        '8', '8', '8', '8', '8', '8', '8',
        '8', '8', '8',
        '8', '8', '8', '8', '8', '8', '8', '8',
        '8', '8',
        '10', '10', '10', '10',
        '10', '10', '10', '10', '10',
        '10',
        '10',
    ];

    private $moduletabid = [
        '1', '1', '1', '1', '1', '1', '1',
        '2', '2', '2', '2', '2', '2', '2',
        '3', '3', '3', '3', '3',
        '4', '4', '4', '4',
        '5', '5', '5', '5', // '5','5','5',
        '6', '6', '6', '6', '6', '6',
        '7', '7', '7',
        '8', '8', '8', '8', '8', '8',
        '9', '9', '9', '9', '9', '9', '9', '9', '9', '9', '9', '9',
        '10', '10', '10', '10',
        '11', '11', '11', '11', '11', '11',
        '12', '12', '12', '12', '12', '12', '12',
        '13', '13', '13',
        '14', '14', '14', '14', '14', '14', '14', '14',
        '15', '15',
        '16', '16', '16', '16',
        '17', '17', '17', '17',
        '18',
        '19',
    ];

    private $names = [
        'My sales list', 'Date Range Selection(at top)', 'Export', 'Charts and Graphs', 'Customer info', 'Add Setter button (in customer info)', 'Customer search bar', // my sales
        '(only people with overrides will have this tab)', 'Date Range Selection(at top)', 'Export', 'Office Overrides', 'Direct Overrides', 'Indirect Overrides', 'Search functionality',  // MY OVERRIDES    'EMPLOYEES',
        'Graph', 'Hiring Progress report', 'Hiring report filter', 'Hiring events calendar', 'Recently Hired',
        'Leads Report', 'Schedule Interview Button', 'Hire Button', 'Comments(yellow bubble)',
        'Configuration Button', 'Hire New Button', 'Hiring Report', 'Approve Button(in report)',
        'Your Location', 'Add Event/Meeting to Personal Calendar', 'Recruiting Events', 'Leadership Events', 'Company Events', 'Office Events',
        'Search an Employee', 'Filter', 'Employee Report',
        'Team Dropdown', 'Search an Employee', 'Create New', 'Team Boxes', 'Edit Teams', 'Remove Members',
        'Templates', 'Search', 'Categories', 'Create New Button', 'Use Button', 'Assign Template Button', 'Edit Template', 'Documents', 'Search', 'Categories', 'Filter', 'Documents Report',
        'Sales Projections Report', 'Overrides Projections Report', 'Commission Calculator', 'Override Calculator',
        'Location', 'Rep Search', 'Date Range', 'Export', 'Graphs', 'Employee Report',
        'Location', 'Rep Search', 'Date Range', 'Export', 'Graphs', 'Employee Report', 'Team Customers',
        'Pay Stub for (Dropdown List)', 'Pay Stubs Report (bottom)', 'Individual Pay Stubs',
        '(only appears if individual gets has recon)', 'Commission Breakdown', 'Earnings Breakdown', 'Commission', 'Overrides (only appears if they get them)', 'Other Items', 'Deductions', 'Payout Summary',
        'Search for a Marketing Deal', 'View Personal Marketing Deal',
        'Search Bar', 'Filter', 'Request Button', 'Requests Report',
        'Employee Search Bar', 'History Button', 'Filter', 'Approvals List Approve / Reject Buttons',
        'Bonus and incentive form',
        'Fine and Fee Form',
    ];

    private $action = [
        'saleslist', '', '', '', '', '', '',  // my saler
        'saleslist', '', '', '', '', '', '', // my override
        'hiring_graph', 'hiring_reports', 'hiring_filter', '', 'recent_hired', // hiring process
        'leads_list.index', 'interview_reschedule', '', '',   // leads
        '', 'onboarding_employee_details', 'onboarding_employee.index', '',  // onboarding employee
        '', '', '', '', '', '',  // Calendar
        'search-employee', 'filter-employee', 'employee-report',   // management Employee
        'select_team', 'search-management-employee', 'management-team.store', 'management-team.index', 'update-management-team', 'delete-management-team-member',
        'template-list', 'search-template', 'template-categories', 'template.store', '', '', 'template.update', '', '', '', '', '',
        '', '', '', '',
        '', '', '', '', '', '',
        '', '', '', '', '', '', '',
        '', '', '',
        '', '', '', '', '', '', '', '',
        '', '',
        'search-request', 'filter-request', 'request-approval.store', 'request-approval.index',
        'search-approval', 'approval-list', 'filter-approval', 'update-status-approval-list',
        '',
        '',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->names)) as $index) {
            PermissionSubModules::create([
                'module_id' => $this->moduleid[$index - 1],
                'module_tab_id' => $this->moduletabid[$index - 1],
                'submodule' => $this->names[$index - 1],
                'action' => $this->action[$index - 1],
                'status' => 1,
            ]);

        }
    }
}
