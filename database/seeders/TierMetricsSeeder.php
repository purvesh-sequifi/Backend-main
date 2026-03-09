<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\TierMetrics;
use App\Traits\CompanyDependentSeeder;
use Illuminate\Database\Seeder;

class TierMetricsSeeder extends Seeder
{
    use CompanyDependentSeeder;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Validate prerequisites
        if (!$this->shouldRun()) {
            return;
        }

        $companyProfile = CompanyProfile::first();
        $tierMetrics = [];

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
                [
                    'value' => 'Product Name',
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
                [
                    'value' => 'Product Name',
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
                [
                    'value' => 'Product Name',
                    'tier_system_id' => '6',
                ],
            ];
        }

        foreach ($tierMetrics as $tierMetric) {
            TierMetrics::updateOrCreate(
                [
                    'tier_system_id' => $tierMetric['tier_system_id'],
                    'value' => $tierMetric['value']
                ],
                [
                    'symbol' => $tierMetric['symbol'] ?? null
                ]
            );
        }

        // Log that seeder ran independently
        $this->logSeederRun('TierMetricsSeeder', true);
    }
}
