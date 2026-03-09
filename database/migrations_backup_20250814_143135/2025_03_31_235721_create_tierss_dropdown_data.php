<?php

use App\Models\CompanyProfile;
use App\Models\TierDuration;
use App\Models\TierMetrics;
use App\Models\TierSystem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        TierSystem::truncate();
        $tierSystems = [
            [
                'value' => 'Tiered based on Individual performance',
            ],
            [
                'value' => 'Tiered based on Office Performance',
            ],
            [
                'value' => 'Tiered based on hiring/ recruitment performance',
            ],
            [
                'value' => 'Tiered based on job metrics',
            ],
            [
                'value' => 'Tiered based on Downline Performance',
            ],
            [
                'value' => 'Tiered based on job metrics (Exact Match)',
            ],
        ];

        foreach ($tierSystems as $tierSystem) {
            if (! TierSystem::where('value', $tierSystem['value'])->first()) {
                TierSystem::create(['value' => $tierSystem['value']]);
            }
        }

        $tierMetrics = [];
        TierMetrics::truncate();
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $tierMetrics = [
                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Accounts Serviced',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Serviced',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Service Rate',
                    'tier_system_id' => '1',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '1',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Autopay Enrolment Rate',
                    'tier_system_id' => '1',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Accounts Serviced',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Serviced',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Service Rate',
                    'tier_system_id' => '2',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '2',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Autopay Enrolment Rate',
                    'tier_system_id' => '2',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Workers Hired',
                    'tier_system_id' => '3',
                ],
                [
                    'value' => 'Leads Generated',
                    'tier_system_id' => '3',
                ],
                [
                    'value' => 'Workers Managed',
                    'tier_system_id' => '3',
                ],

                [
                    'value' => 'Gross Account Value Serviced',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Initial Service Cost',
                    'tier_system_id' => '4',
                ],
                [
                    'value' => 'Subscription Payment',
                    'tier_system_id' => '4',
                ],

                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Accounts Serviced',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Serviced',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Service Rate',
                    'tier_system_id' => '5',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '5',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Autopay Enrolment Rate',
                    'tier_system_id' => '5',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Service Provider',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Location Code',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Customer State',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Customer Zip',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Card on File',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Autopay',
                    'tier_system_id' => '6',
                ],
            ];
        } elseif ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $tierMetrics = [
                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Accounts Installed',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'KW Sold',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'KW Installed',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Installed',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Install Rate',
                    'tier_system_id' => '1',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '1',
                ],

                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Accounts Installed',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'KW Sold',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'KW Installed',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Installed',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Install Rate',
                    'tier_system_id' => '2',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '2',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Workers Hired',
                    'tier_system_id' => '3',
                ],
                [
                    'value' => 'Leads Generated',
                    'tier_system_id' => '3',
                ],
                [
                    'value' => 'Workers Managed',
                    'tier_system_id' => '3',
                ],

                [
                    'value' => 'Gross Account Value',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'KW',
                    'tier_system_id' => '4',
                ],
                [
                    'value' => 'Net EPC',
                    'tier_system_id' => '4',
                ],
                [
                    'value' => 'Gross EPC',
                    'tier_system_id' => '4',
                ],
                [
                    'value' => 'Dealer Fee %',
                    'tier_system_id' => '4',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Dealer Fee $',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'SOW',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],

                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Accounts Installed',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'KW Sold',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'KW Installed',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Installed',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Install Rate',
                    'tier_system_id' => '5',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '5',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Installer',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Location Code',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Customer State',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Customer Zip',
                    'tier_system_id' => '6',
                ],
            ];
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
            $tierMetrics = [
                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Accounts Installed',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Sq ft Sold',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Sq ft Installed',
                    'tier_system_id' => '1',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Installed',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '1',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Install Rate',
                    'tier_system_id' => '1',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '1',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Accounts Installed',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Sq ft Sold',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Sq ft Installed',
                    'tier_system_id' => '2',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Installed',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '2',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Install Rate',
                    'tier_system_id' => '2',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '2',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Workers Hired',
                    'tier_system_id' => '3',
                ],
                [
                    'value' => 'Leads Generated',
                    'tier_system_id' => '3',
                ],
                [
                    'value' => 'Workers Managed',
                    'tier_system_id' => '3',
                ],

                [
                    'value' => 'Gross Account Value',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Sq ft',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Net $ per Sq ft',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Gross $ per Sq ft',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Dealer Fee %',
                    'tier_system_id' => '4',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Dealer Fee $',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],
                [
                    'value' => 'SOW',
                    'tier_system_id' => '4',
                    'symbol' => '$',
                ],

                [
                    'value' => 'Accounts Sold',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Accounts Installed',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Sq ft Sold',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Sq ft Installed',
                    'tier_system_id' => '5',
                ],
                [
                    'value' => 'Revenue Sold',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Revenue Installed',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Average Contract Value',
                    'tier_system_id' => '5',
                    'symbol' => '$',
                ],
                [
                    'value' => 'Install Rate',
                    'tier_system_id' => '5',
                    'symbol' => '%',
                ],
                [
                    'value' => 'Cancellation Rate',
                    'tier_system_id' => '5',
                    'symbol' => '%',
                ],

                [
                    'value' => 'Installer',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Location Code',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Customer State',
                    'tier_system_id' => '6',
                ],
                [
                    'value' => 'Customer Zip',
                    'tier_system_id' => '6',
                ],
            ];
        }

        foreach ($tierMetrics as $tierMetric) {
            if (! TierMetrics::where(['tier_system_id' => $tierMetric['tier_system_id'], 'value' => $tierMetric['value']])->first()) {
                TierMetrics::create(['value' => $tierMetric['value'], 'tier_system_id' => $tierMetric['tier_system_id'], 'symbol' => @$tierMetric['symbol']]);
            }
        }

        TierDuration::truncate();
        $tierDurations = [
            [
                'value' => 'Per Pay Period',
            ],
            [
                'value' => 'Weekly',
            ],
            [
                'value' => 'Monthly',
            ],
            [
                'value' => 'Quarterly',
            ],
            [
                'value' => 'Semi-Annually',
            ],
            [
                'value' => 'Annually',
            ],
            [
                'value' => 'Per Recon Period',
            ],
            [
                'value' => 'On Demand',
            ],
            [
                'value' => 'Continuous',
            ],
        ];

        foreach ($tierDurations as $tierDuration) {
            if (! TierDuration::where('value', $tierDuration['value'])->first()) {
                TierDuration::create(['value' => $tierDuration['value']]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tierss_dropdown_data');
    }
};
