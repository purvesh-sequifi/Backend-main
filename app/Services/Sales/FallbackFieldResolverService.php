<?php

namespace App\Services\Sales;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\DB;

class FallbackFieldResolverService
{
    private const FIELD_MAP = [
        'homeowner_id' => ['table' => 'sale_masters', 'column' => 'homeowner_id'],
        'proposal_id' => ['table' => 'sale_masters', 'column' => 'proposal_id'],
        'customer_name' => ['table' => 'sale_masters', 'column' => 'customer_name'],
        'customer_address' => ['table' => 'sale_masters', 'column' => 'customer_address'],
        'customer_address_2' => ['table' => 'sale_masters', 'column' => 'customer_address_2'],
        'customer_city' => ['table' => 'sale_masters', 'column' => 'customer_city'],
        'customer_state' => ['table' => 'sale_masters', 'column' => 'customer_state'],
        'customer_zip' => ['table' => 'sale_masters', 'column' => 'customer_zip'],
        'customer_email' => ['table' => 'sale_masters', 'column' => 'customer_email'],
        'customer_phone' => ['table' => 'sale_masters', 'column' => 'customer_phone'],
        'location_code' => ['table' => 'sale_masters', 'column' => 'location_code'],
        'install_partner' => ['table' => 'sale_masters', 'column' => 'install_partner'],
        'job_status' => ['table' => 'sale_masters', 'column' => 'job_status'],
        'gross_account_value' => ['table' => 'sale_masters', 'column' => 'gross_account_value'],
        'net_epc' => ['table' => 'sale_masters', 'column' => 'net_epc'],
        'epc' => ['table' => 'sale_masters', 'column' => 'epc'],
        'dealer_fee_amount' => ['table' => 'sale_masters', 'column' => 'dealer_fee_amount'],
        'dealer_fee_percentage' => ['table' => 'sale_masters', 'column' => 'dealer_fee_percentage'],
        'adders' => ['table' => 'sale_masters', 'column' => 'adders'],
        'adders_description' => ['table' => 'sale_masters', 'column' => 'adders_description'],
        'cancel_fee' => ['table' => 'sale_masters', 'column' => 'cancel_fee'],
        'cash_amount' => ['table' => 'sale_masters', 'column' => 'cash_amount'],
        'loan_amount' => ['table' => 'sale_masters', 'column' => 'loan_amount'],
        'financing_term' => ['table' => 'sale_masters', 'column' => 'financing_term'],
        'customer_signoff' => ['table' => 'sale_masters', 'column' => 'customer_signoff'],
        'date_cancelled' => ['table' => 'sale_masters', 'column' => 'date_cancelled'],
        'closer1_id' => ['table' => 'sale_masters', 'column' => 'closer1_id'],
        'closer2_id' => ['table' => 'sale_masters', 'column' => 'closer2_id'],
        'setter1_id' => ['table' => 'sale_masters', 'column' => 'setter1_id'],
        'setter2_id' => ['table' => 'sale_masters', 'column' => 'setter2_id'],
        'sales_rep_email' => ['table' => 'sale_masters', 'column' => 'sales_rep_email'],
        'product_id' => ['table' => 'sale_masters', 'column' => 'product_id'],
        'product_code' => ['table' => 'sale_masters', 'column' => 'product_code'],
        'kw' => ['table' => 'sale_masters', 'column' => 'kw'],
        'm1_date' => ['table' => 'sale_masters', 'column' => 'm1_date'],
        'm2_date' => ['table' => 'sale_masters', 'column' => 'm2_date'],
    ];

    public function fillMissingFields(
        array $row,
        array $mappedFields,
        array $unmappedFields,
        string $companyType
    ): array {
        $pidField = $mappedFields['pid'] ?? null;
        $pid = $pidField ? ($row[$pidField] ?? null) : null;

        if (! $pid) {
            \Log::warning('fillMissingFields: Missing PID in row', ['row' => $row]);

            return $row;
        }

        foreach ($unmappedFields as $field) {
            $row[$field] = $this->resolve($field, $pid, $companyType);
        }

        return $row;
    }

    public function resolve(string $fieldName, string $pid, ?string $companyType = null): mixed
    {
        if ($fieldName === 'kw') {
            return $this->resolveKw($pid, $companyType);
        }

        $mapping = self::FIELD_MAP[$fieldName] ?? null;

        if (! $mapping) {
            \Log::warning("FallbackFieldResolverService: Field '{$fieldName}' not configured for fallback resolution.");

            return null;
        }

        return DB::table($mapping['table'])
            ->where('pid', $pid)
            ->value($mapping['column']);
    }

    private function resolveKw(string $pid, ?string $companyType): mixed
    {
        $isPest = in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE, true);

        if ($isPest) {
            return DB::table('sale_product_master')
                ->where('pid', $pid)
                ->value('product_kw');
        }

        return DB::table('sale_masters')
            ->where('pid', $pid)
            ->value('kw');
    }
}
