<?php

namespace App\Exports\Sale\Factory;

use Illuminate\Support\Carbon;

class UniversalSampleDataFactory
{
    public static function getSampleData(): array
    {
        return [
            'pid' => 'ABC123456',
            'prospect_id' => 'PR-2323232',

            'product_id' => 'PROD001',
            'product_code' => 'SOL-6KW',
            'sale_product_name' => 'Solar 6kW',

            'customer_name' => 'John Doe',
            'customer_address' => '123 Solar Street',
            'customer_address_2' => 'Apt 101',
            'customer_city' => 'Phoenix',
            'customer_state' => 'AZ',
            'customer_zip' => '85001',
            'customer_email' => 'john.solar@example.com',
            'customer_phone' => '5559876543',

            'homeowner_id' => '3737916',
            'proposal_id' => '2323232',
            'location_code' => 'LOC123',

            'install_partner' => 'SolarInstall Co.',
            'job_status' => 'active',

            'gross_account_value' => '25000',
            'kw' => '6.0',
            'epc' => '3.75',
            'net_epc' => '23000',
            'dealer_fee_percentage' => '15',
            'dealer_fee_amount' => '3750',
            'adders' => '1000',

            'customer_signoff' => self::excelDate('2024-07-01'),
            'date_cancelled' => self::excelDate('2024-07-05'),
            'initial_service_date' => self::excelDate('2024-07-15'),
            'service_completion_date' => self::excelDate('2024-07-30'),
            'm1_date' => self::excelDate('2024-07-20'),
            'm2_date' => self::excelDate('2024-07-30'),

            'adders_description' => 'battery, roof fix',

            'closer1_id' => 'closer-1@example.com',
            'setter1_id' => 'setter-1@example.com',
            'closer2_id' => 'closer-2@example.com',
            'setter2_id' => 'setter-2@example.com',

            // Role-specific Flexible IDs - alternatives to corresponding email fields
            // Note: Only fields that exist for each company type will be used in templates
            'closer1_flexi_id' => 'REP-001',        // Used by: ALL company types
            'setter1_flexi_id' => 'SETTER-001',     // Used by: SOLAR, TURF, ROOFING, MORTGAGE (as LOA)
            'closer2_flexi_id' => 'REP-002',        // Used by: ALL company types except MORTGAGE
            'setter2_flexi_id' => 'SETTER-002',     // Used by: SOLAR, TURF, ROOFING only

            'advanced_payment_10_percent' => self::excelDate('2024-07-03'),
            'next_40_percent_payment' => self::excelDate('2024-07-12'),
            'final_payment' => self::excelDate('2024-07-31'),

            // Custom Sales Field Values - JSON encoded field values for the sale
            // Format: {"field_id": value, "field_id": value}
            // These are optional and only used when Custom Sales Fields feature is enabled
            'custom_field_values' => '',
        ];
    }

    private static function excelDate(string $date)
    {
        return Carbon::parse($date)->format('m/d/Y');
    }
}
