<?php

namespace Database\Seeders;

use App\Models\TicketModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class TicketModulesDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $modules = [
            [
                'jira_id' => '15108',
                'jira_key' => 'SEQ-5046',
                'jira_summary' => 'Integrations',
            ],
            [
                'jira_id' => '14229',
                'jira_key' => 'SEQ-4172',
                'jira_summary' => 'Login > Onboarding ',
            ],
            [
                'jira_id' => '12640',
                'jira_key' => 'SEQ-2584',
                'jira_summary' => 'Notifications',
            ],
            [
                'jira_id' => '12638',
                'jira_key' => 'SEQ-2582',
                'jira_summary' => 'Requests and Approvals > Approvals',
            ],
            [
                'jira_id' => '12637',
                'jira_key' => 'SEQ-2581',
                'jira_summary' => 'Requests and Approvals > My Requests',
            ],
            [
                'jira_id' => '12636',
                'jira_key' => 'SEQ-2580',
                'jira_summary' => 'Reports > Past Pay Stubs',
            ],
            [
                'jira_id' => '12635',
                'jira_key' => 'SEQ-2579',
                'jira_summary' => 'Reports > Office',
            ],
            [
                'jira_id' => '12634',
                'jira_key' => 'SEQ-2578',
                'jira_summary' => 'Management > Teams',
            ],
            [
                'jira_id' => '12633',
                'jira_key' => 'SEQ-2577',
                'jira_summary' => 'Management > Employees',
            ],
            [
                'jira_id' => '12632',
                'jira_key' => 'SEQ-2576',
                'jira_summary' => 'Calendar',
            ],
            [
                'jira_id' => '12631',
                'jira_key' => 'SEQ-2575',
                'jira_summary' => 'Hiring > Onboarding Employees',
            ],
            [
                'jira_id' => '12630',
                'jira_key' => 'SEQ-2574',
                'jira_summary' => 'Hiring > Leads',
            ],
            [
                'jira_id' => '12629',
                'jira_key' => 'SEQ-2573',
                'jira_summary' => 'Hiring > Hiring Progress',
            ],
            [
                'jira_id' => '12628',
                'jira_key' => 'SEQ-2572',
                'jira_summary' => 'SequiDocs > Templates',
            ],
            [
                'jira_id' => '12626',
                'jira_key' => 'SEQ-2570',
                'jira_summary' => 'My Sales > Pay Stubs',
            ],
            [
                'jira_id' => '12625',
                'jira_key' => 'SEQ-2569',
                'jira_summary' => 'My Sales > Overrides',
            ],
            [
                'jira_id' => '12624',
                'jira_key' => 'SEQ-2568',
                'jira_summary' => 'My Sales > Sales',
            ],
            [
                'jira_id' => '12623',
                'jira_key' => 'SEQ-2567',
                'jira_summary' => 'Support',
            ],
            [
                'jira_id' => '12622',
                'jira_key' => 'SEQ-2566',
                'jira_summary' => 'Reports > Reconcilliation',
            ],
            [
                'jira_id' => '12621',
                'jira_key' => 'SEQ-2565',
                'jira_summary' => 'Payroll > Payment Request',
            ],
            [
                'jira_id' => '12620',
                'jira_key' => 'SEQ-2564',
                'jira_summary' => 'Payroll > One-Time Payroll',
            ],
            [
                'jira_id' => '12619',
                'jira_key' => 'SEQ-2563',
                'jira_summary' => 'Payroll > Run Payroll',
            ],
            [
                'jira_id' => '12618',
                'jira_key' => 'SEQ-2562',
                'jira_summary' => 'SequiDocs >Documents',
            ],
            [
                'jira_id' => '12617',
                'jira_key' => 'SEQ-2561',
                'jira_summary' => 'SequiDocs >Send Letter',
            ],
            [
                'jira_id' => '12616',
                'jira_key' => 'SEQ-2560',
                'jira_summary' => 'SequiDocs >Other Templates',
            ],
            [
                'jira_id' => '12615',
                'jira_key' => 'SEQ-2559',
                'jira_summary' => 'SequiDocs >Email Templates',
            ],
            [
                'jira_id' => '12614',
                'jira_key' => 'SEQ-2558',
                'jira_summary' => 'SequiDocs >Agreements',
            ],
            [
                'jira_id' => '12613',
                'jira_key' => 'SEQ-2557',
                'jira_summary' => 'SequiDocs > Offer Letter',
            ],
            [
                'jira_id' => '12611',
                'jira_key' => 'SEQ-2555',
                'jira_summary' => 'User Profile',
            ],
            [
                'jira_id' => '12610',
                'jira_key' => 'SEQ-2554',
                'jira_summary' => 'Company Profile',
            ],
            [
                'jira_id' => '12523',
                'jira_key' => 'SEQ-2467',
                'jira_summary' => 'Alert Center > People Alert',
            ],
            [
                'jira_id' => '12522',
                'jira_key' => 'SEQ-2466',
                'jira_summary' => 'Alert Center > Rep Redline',
            ],
            [
                'jira_id' => '12521',
                'jira_key' => 'SEQ-2465',
                'jira_summary' => 'Alert Center > Location Redline',
            ],
            [
                'jira_id' => '12520',
                'jira_key' => 'SEQ-2464',
                'jira_summary' => 'Alert Center > Closed Payroll',
            ],
            [
                'jira_id' => '12519',
                'jira_key' => 'SEQ-2463',
                'jira_summary' => 'Alert Center > Missing Rep',
            ],
            [
                'jira_id' => '12518',
                'jira_key' => 'SEQ-2462',
                'jira_summary' => 'Alert Center > Sales Info',
            ],
            [
                'jira_id' => '12517',
                'jira_key' => 'SEQ-2461',
                'jira_summary' => 'Permission > Policies',
            ],
            [
                'jira_id' => '12516',
                'jira_key' => 'SEQ-2460',
                'jira_summary' => 'Permission > Groups',
            ],
            [
                'jira_id' => '12515',
                'jira_key' => 'SEQ-2459',
                'jira_summary' => 'Reports > Pending Installs',
            ],
            [
                'jira_id' => '12514',
                'jira_key' => 'SEQ-2458',
                'jira_summary' => 'Reports > Clawback',
            ],
            [
                'jira_id' => '12512',
                'jira_key' => 'SEQ-2456',
                'jira_summary' => 'Reports > Payroll',
            ],
            [
                'jira_id' => '12511',
                'jira_key' => 'SEQ-2455',
                'jira_summary' => 'Reports > Costs',
            ],
            [
                'jira_id' => '12510',
                'jira_key' => 'SEQ-2454',
                'jira_summary' => 'Reports > Sales',
            ],
            [
                'jira_id' => '12509',
                'jira_key' => 'SEQ-2453',
                'jira_summary' => 'Reports > Company',
            ],
            [
                'jira_id' => '12508',
                'jira_key' => 'SEQ-2452',
                'jira_summary' => 'Payroll > Reconcilliation',
            ],
            [
                'jira_id' => '12495',
                'jira_key' => 'SEQ-2439',
                'jira_summary' => 'Settings > Paperwork',
            ],
            [
                'jira_id' => '12494',
                'jira_key' => 'SEQ-2438',
                'jira_summary' => 'Settings > Bank Accounts',
            ],
            [
                'jira_id' => '12493',
                'jira_key' => 'SEQ-2437',
                'jira_summary' => 'Settings > Alerts',
            ],
            [
                'jira_id' => '12492',
                'jira_key' => 'SEQ-2436',
                'jira_summary' => 'Settings > Positions',
            ],
            [
                'jira_id' => '12491',
                'jira_key' => 'SEQ-2435',
                'jira_summary' => 'Settings > Departments',
            ],
            [
                'jira_id' => '12490',
                'jira_key' => 'SEQ-2434',
                'jira_summary' => 'Settings > Cost Centres',
            ],
            [
                'jira_id' => '12489',
                'jira_key' => 'SEQ-2433',
                'jira_summary' => 'Settings > Locations',
            ],
            [
                'jira_id' => '12488',
                'jira_key' => 'SEQ-2432',
                'jira_summary' => 'Settings > Hiring',
            ],
            [
                'jira_id' => '12487',
                'jira_key' => 'SEQ-2431',
                'jira_summary' => 'Settings > Setup',
            ],
            [
                'jira_id' => '12486',
                'jira_key' => 'SEQ-2430',
                'jira_summary' => 'Admin Setting > System',
            ],
            [
                'jira_id' => '12485',
                'jira_key' => 'SEQ-2429',
                'jira_summary' => 'Admin Setting > User Management',
            ],
            [
                'jira_id' => '12484',
                'jira_key' => 'SEQ-2428',
                'jira_summary' => 'Admin Setting > Billing',
            ],
            [
                'jira_id' => '12474',
                'jira_key' => 'SEQ-2418',
                'jira_summary' => 'Dashboard',
            ],
        ];

        try {
            //            $modules = Http::withBasicAuth(env('JIRA_EMAIL'), env('JIRA_SECRET_KEY'))
            //                ->get(env('JIRA_API_BASE_URL') . 'rest/api/2/search?jql=issuetype=Epic&maxResults=1000')->json();

            foreach ($modules as $module) {
                TicketModule::query()->updateOrCreate(['jira_id' => $module['id']], [
                    'jira_id' => $module['id'],
                    'jira_key' => $module['key'],
                    'jira_summary' => $module['fields']['summary'],
                ]);
            }
        } catch (\Throwable $th) {
            // throw $th;
        }

    }
}
