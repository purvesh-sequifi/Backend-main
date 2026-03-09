<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Events\SendSalesExportToPusher;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Services\SalesExportStreamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesControllerForSalesExport extends SalesController
{
    /**
     * Sales List-only milestone header normalization.
     * - Rename "M1 Date" => "M1"
     * - Rename "M2 Date" => "M2"
     * - Remove "Final Payment"
     */
    private function normalizeSalesListMilestoneHeader(?string $name): ?string
    {
        $name = $name !== null ? trim($name) : null;
        if ($name === null || $name === '') {
            return null;
        }

        return match ($name) {
            'Final Payment' => null,
            'M1 Date' => 'M1',
            // Drop the legacy/duplicate "M2" trigger column; we keep "M2" via "M2 Date" => "M2".
            'M2' => null,
            'M2 Date' => 'M2',
            default => $name,
        };
    }

    /**
     * @param array<int, string> $triggerNames Original trigger names (as stored in milestones)
     * @return array<int, array{source: string, header: string}>
     */
    private function buildSalesListMilestoneColumnMap(array $triggerNames): array
    {
        $map = [];
        foreach ($triggerNames as $name) {
            $header = $this->normalizeSalesListMilestoneHeader($name);
            if ($header === null) {
                continue;
            }
            $map[] = ['source' => $name, 'header' => $header];
        }

        return $map;
    }

    /**
     * Sales Pid Details-only milestone header normalization.
     * - Rename "Final Payment" => "M2 Date"
     * - Rename "M3" => "M3 Date"
     * - Remove "M2" and "M2 Date" (to avoid duplicates)
     */
    private function normalizePidDetailsMilestoneHeader(?string $name): ?string
    {
        $name = $name !== null ? trim($name) : null;
        if ($name === null || $name === '') {
            return null;
        }

        return match ($name) {
            'M2', 'M2 Date' => null,
            'Final Payment' => 'M2 Date',
            'M3' => 'M3 Date',
            default => $name,
        };
    }

    /**
     * Normalize milestone names for matching (handles whitespace / NBSP / case differences).
     */
    private function normalizeMilestoneNameForMatch(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = str_replace("\u{00A0}", ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        if ($name === '' || $name === null) {
            return null;
        }

        return mb_strtolower($name);
    }

    /**
     * @param array<int, string> $triggerNames Original trigger names (as stored in milestones)
     * @return array<int, array{source: string, header: string}>
     */
    private function buildPidDetailsMilestoneColumnMap(array $triggerNames): array
    {
        $map = [];
        foreach ($triggerNames as $name) {
            $header = $this->normalizePidDetailsMilestoneHeader($name);
            if ($header === null) {
                continue;
            }
            $map[] = ['source' => $name, 'header' => $header];
        }

        return $map;
    }

    /**
     * Optimized Sales Export - Handles large datasets efficiently without background jobs
     * Maintains same API interface and Pusher notifications as original SalesExportJob
     */
    public function salesExportOptimized(Request $request): JsonResponse
    {
        $data = $request->all();

        try {
            // MEMORY PROTECTION: Check dataset size before processing
            $estimatedRecordCount = $this->estimateRecordCount($data);

            Log::info('Sales Export Request', [
                'estimated_records' => $estimatedRecordCount,
                'request_filters' => $data,
                'memory_limit' => ini_get('memory_limit'),
            ]);

            if ($estimatedRecordCount > 75000) {
                Log::warning('Large dataset export rejected', [
                    'estimated_records' => $estimatedRecordCount,
                    'request' => $data,
                ]);

                return response()->json([
                    'status' => false,
                    'ApiName' => 'sales-export',
                    'message' => "Dataset too large ({$estimatedRecordCount} records). Please narrow your date range or filters and try again.",
                    'data' => 'dataset_too_large',
                ], 413);
            }

            // Increase memory limit based on dataset size and environment config
            $baseMemoryLimit = config('sales-export-optimization.memory_limit', '1024M');
            $memoryLimit = $estimatedRecordCount > 30000 ? '2048M' : $baseMemoryLimit;
            ini_set('memory_limit', $memoryLimit);

            Log::info('Memory limit set', [
                'base_memory_limit' => $baseMemoryLimit,
                'applied_memory_limit' => $memoryLimit,
                'estimated_records' => $estimatedRecordCount,
                'env_chunk_size' => config('sales-export-optimization.chunk_size'),
            ]);

            // Start processing using CSV streaming (always use CSV path in this controller)
            $this->processSalesExportCsv($data);

            // Return same response format as original - empty data array, file URL sent via Pusher
            return response()->json([
                'status' => true,
                'ApiName' => 'sales-export',
                'message' => 'We are getting your file ready for download. This may take a few minutes depending on its size. Please be patient.',
                'data' => [],
            ], 200);
        } catch (\Exception $e) {
            // Log detailed error information
            Log::error('Sales Export Failed: '.$e->getMessage(), [
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request' => $data,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return more specific error message
            $errorMessage = 'Export failed. Please try again with a smaller date range.';
            if (strpos($e->getMessage(), 'memory') !== false || strpos($e->getMessage(), 'Dataset too large') !== false) {
                $errorMessage = 'Dataset too large for export. Please narrow your date range, add filters, or contact support for assistance with large exports.';
            } elseif (strpos($e->getMessage(), 'timeout') !== false) {
                $errorMessage = 'Export timed out. Please narrow your date range or try again later.';
            } elseif (strpos($e->getMessage(), 'records using') !== false) {
                // This is our custom memory error with details
                $errorMessage = $e->getMessage();
            }

            return response()->json([
                'status' => false,
                'ApiName' => 'sales-export',
                'message' => $errorMessage,
                'data' => get_class($e),
            ], 500);
        }
    }

    /**
     * Stream export to two CSVs and zip them (mirrors Excel two-sheet output)
     */
    private function processSalesExportCsv($request): void
    {
        $companyProfile = CompanyProfile::first();
        [$startDate, $endDate] = $this->getDateFromFilter($request);

        // OPTIMIZATION: Get PIDs first without loading all relations (much faster)
        $baseQuery = $this->buildBaseQueryForPids($startDate, $endDate, $request, $companyProfile);
        $pids = $baseQuery->pluck('pid')->toArray();

        // Preload lookup data using PIDs (avoids duplicate query)
        $lookupData = $this->preloadLookupDataByPids($pids, $companyProfile);
        $isRecon = (bool) $lookupData['reconciliationSetting'];
        $isPestCompany = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);

        // Get field labels from template (cached, non-blocking)
        $fieldLabels = $this->getDefaultTemplateFieldLabels($companyProfile);
        
        $service = new SalesExportStreamingService;
        $baseDir = storage_path('app/public/exports/reports/sales');
        $service->ensureExportDirectory($baseDir);

        $date = date('Y-m-d');
        $listCsv = $baseDir.'/sales_export_list_'.$date.'.csv';
        $pidCsv = $baseDir.'/sales_export_pid_'.$date.'.csv';
        $zipPath = $baseDir.'/sales_export_'.$date.'.zip';

        $triggerDates = getTriggerDatesForSample();
        $triggerNames = array_map(fn ($t) => $t['name'], $triggerDates);
        $salesListMilestoneMap = $this->buildSalesListMilestoneColumnMap($triggerNames);
        $pidDetailsMilestoneMap = $this->buildPidDetailsMilestoneColumnMap($triggerNames);

        // Build and write headings with template labels
        // Get field labels from default template (first template in DB)
        // Cache this to avoid repeated DB queries
        $fieldLabels = $this->getDefaultTemplateFieldLabels($companyProfile);

        // Build and write headings using template labels
        $listHeadings = $this->buildSalesListHeadings($isPestCompany, $isRecon, $salesListMilestoneMap, $fieldLabels);
        $pidHeadings = $this->buildPidHeadings($isPestCompany, $pidDetailsMilestoneMap, $fieldLabels);

        $listFh = $service->openCsv($listCsv);
        $pidFh = $service->openCsv($pidCsv);
        $service->writeHeadings($listFh, $listHeadings);
        $service->writeHeadings($pidFh, $pidHeadings);

        // OPTIMIZATION: Increase chunk size for large datasets to reduce query overhead
        $estimatedCount = count($pids);
        $chunkSize = $estimatedCount > 10000 ? 2000 : config('sales-export-optimization.chunk_size', 1000);
        // CSV streaming export (quiet mode)
        $totalProcessed = 0;
        $this->buildBaseQuery($startDate, $endDate, $request, $companyProfile)
            ->chunk($chunkSize, function ($chunk) use ($lookupData, $listFh, $pidFh, $isPestCompany, $isRecon, $triggerNames, $companyProfile, $chunkSize, &$totalProcessed) {
                $processed = $this->transformChunkData($chunk, $lookupData, $companyProfile);
                $chunkCount = 0;
                foreach ($processed as $row) {
                    $listRow = $this->mapSalesListRow($row, $isPestCompany, $isRecon, $salesListMilestoneMap, $companyProfile);
                    $pidRow = $this->mapPidRow($row, $isPestCompany, $pidDetailsMilestoneMap, $companyProfile);
                    $listFh->fputcsv($listRow);
                    $pidFh->fputcsv($pidRow);
                    $chunkCount++;
                }
                $totalProcessed += $chunkCount;

                // Force garbage collection every 5 chunks to prevent memory buildup
                if ($totalProcessed % (5 * $chunkSize) === 0) {
                    gc_collect_cycles();
                }
            });
        // noop

        // Zip both CSVs
        $service->zipFiles([$listCsv, $pidCsv], $zipPath);

        // Log file creation
        Log::info('Sales Export - Files created', [
            'list_csv' => $listCsv,
            'pid_csv' => $pidCsv,
            'zip_path' => $zipPath,
            'zip_exists' => file_exists($zipPath),
            'zip_size' => file_exists($zipPath) ? filesize($zipPath) : 0,
            'total_processed' => $totalProcessed,
        ]);

        // Log file creation
        Log::info('Sales Export - Files created', [
            'list_csv' => $listCsv,
            'pid_csv' => $pidCsv,
            'zip_path' => $zipPath,
            'zip_exists' => file_exists($zipPath),
            'zip_size' => file_exists($zipPath) ? filesize($zipPath) : 0,
            'total_processed' => $totalProcessed,
        ]);

        // Optionally remove individual CSVs after zipping
        if ((bool) config('sales-export-optimization.csv_zip_on_large', true)) {
            @unlink($listCsv);
            @unlink($pidCsv);
        }

        $url = getStoragePath('exports/reports/sales/'.basename($zipPath));
        // Fix double slashes in URL (but preserve http:// or https://)
        $url = preg_replace('#([^:])//+#', '$1/', $url);

        Log::info('Sales Export - Completed', [
            'zip_path' => $zipPath,
            'zip_exists' => file_exists($zipPath),
            'zip_size' => file_exists($zipPath) ? filesize($zipPath) : 0,
            'url' => $url,
            'total_processed' => $totalProcessed,
        ]);

        $this->sendPusherNotification($url, $request);

        Log::info('Sales Export - Pusher notification sent', [
            'url' => $url,
            'request_session_key' => $request['session_key'] ?? null,
        ]);
    }

    /**
     * Get field labels from default template (first template in DB)
     * Returns mapping: field_name => excel_field
     * Cached to avoid repeated DB queries during export
     */
    private function getDefaultTemplateFieldLabels(CompanyProfile $companyProfile): array
    {
        // Use cache key based on company type to avoid repeated queries
        $cacheKey = 'sales_export_field_labels_' . $companyProfile->company_type;
        return Cache::remember($cacheKey, 3600, function () use ($companyProfile) {
            // Determine table names based on company type
            $templatesTable = match (true) {
                $companyProfile->company_type === CompanyProfile::FIBER_COMPANY_TYPE => 'fiber_sales_import_templates',
                $companyProfile->company_type === CompanyProfile::SOLAR_COMPANY_TYPE => 'solar_sales_import_templates',
                $companyProfile->company_type === CompanyProfile::TURF_COMPANY_TYPE => 'turf_sales_import_templates',
                $companyProfile->company_type === CompanyProfile::ROOFING_COMPANY_TYPE => 'roofing_sales_import_templates',
                $companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE => 'mortgage_sales_import_templates',
                in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE, true) => 'pest_sales_import_templates',
                default => [] // Return empty array for unsupported types instead of throwing
            };

            if (empty($templatesTable)) {
                return [];
            }

            $detailsTable = str_replace('_templates', '_template_details', $templatesTable);
            $fieldsTable = str_replace('_templates', '_fields', $templatesTable);

            // Get first template ID
            $templateId = DB::table($templatesTable)
                ->orderBy('id')
                ->value('id');

            if (!$templateId) {
                return [];
            }

            // Get template details with field names - optimized query with indexes
            $details = DB::table($detailsTable)
                ->join($fieldsTable, "{$detailsTable}.field_id", '=', "{$fieldsTable}.id")
                ->where("{$detailsTable}.template_id", $templateId)
                ->whereNotNull("{$detailsTable}.excel_field")
                ->where("{$detailsTable}.excel_field", '!=', '')
                ->select("{$fieldsTable}.name as field_name", "{$detailsTable}.excel_field")
                ->get();

            $labels = [];
            foreach ($details as $detail) {
                if ($detail->field_name && $detail->excel_field) {
                    $labels[trim($detail->field_name)] = trim($detail->excel_field);
                }
            }

            return $labels;
        });
    }

    /**
     * Get label for field from template, fallback to default
     */
    private function getFieldLabel(string $fieldName, array $fieldLabels, string $default): string
    {
        return $fieldLabels[$fieldName] ?? $default;
    }

    /**
     * @param array<int, array{source: string, header: string}> $milestoneMap
     */
    private function buildSalesListHeadings(bool $isPest, bool $isRecon, array $milestoneMap, array $fieldLabels = [], ?CompanyProfile $companyProfile = null): array
    {
        if ($isPest) {
            $headings = [
                $this->getFieldLabel('pid', $fieldLabels, 'PID'),
                $this->getFieldLabel('data_source_type', $fieldLabels, 'Source'),
                $this->getFieldLabel('customer_name', $fieldLabels, 'Customer'),
                $this->getFieldLabel('job_status', $fieldLabels, 'Job Status'),
                $this->getFieldLabel('customer_state', $fieldLabels, 'State'),
                $this->getFieldLabel('location_code', $fieldLabels, 'Location'),
                $this->getFieldLabel('closer1_id', $fieldLabels, 'Sales Rep-1'),
                $this->getFieldLabel('closer2_id', $fieldLabels, 'Sales Rep-2'),
                'Sales Rep-1 Email',
                'Sales Rep-2 Email',
                $this->getFieldLabel('customer_signoff', $fieldLabels, 'Approved Date'),
                $this->getFieldLabel('gross_account_value', $fieldLabels, 'Gross Value'),
                $this->getFieldLabel('adders', $fieldLabels, 'Adders '),
                'Total Commission',
                'Total Override',
                'Payment Status',
            ];
        } elseif ($companyProfile && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $headings = [
                'Borrower ID',
                'Source',
                'Borrower Name',
                'Job Status',
                'State',
                'Location',
                'MLO',
                'MLO-2',
                'LOA',
                'LOA-2',
                'MLO Email',
                'MLO-2 Email',
                'LOA Email',
                'LOA-2 Email',
                'Sold Date',
                'Loan Amount',
                'Gross Revenue',
                'Fee',
                'Comp',
                'Total Commission',
                'Total Override',
                'Payment Status',
            ];
        } else {
            $headings = [
                $this->getFieldLabel('pid', $fieldLabels, 'PID'),
                $this->getFieldLabel('data_source_type', $fieldLabels, 'Source'),
                $this->getFieldLabel('customer_name', $fieldLabels, 'Customer'),
                $this->getFieldLabel('job_status', $fieldLabels, 'Job Status'),
                $this->getFieldLabel('customer_state', $fieldLabels, 'State'),
                $this->getFieldLabel('location_code', $fieldLabels, 'Location'),
                $this->getFieldLabel('closer1_id', $fieldLabels, 'Closer-1'),
                $this->getFieldLabel('closer2_id', $fieldLabels, 'Closer-2'),
                $this->getFieldLabel('setter1_id', $fieldLabels, 'Setter-1'),
                $this->getFieldLabel('setter2_id', $fieldLabels, 'Setter-2'),
                'Closer-1 Email',
                'Closer-2 Email',
                'Setter-1 Email',
                'Setter-2 Email',
                $this->getFieldLabel('customer_signoff', $fieldLabels, 'Approved Date'),
                $this->getFieldLabel('kw', $fieldLabels, 'KW'),
                $this->getFieldLabel('epc', $fieldLabels, 'EPC'),
                $this->getFieldLabel('net_epc', $fieldLabels, 'Net EPC'),
                $this->getFieldLabel('adders', $fieldLabels, 'Adders '),
                'Total Commission',
                'Total Override',
                'Payment Status',
            ];
        }
        if ($isRecon) {
            $headings[] = 'Recon';
        }
        foreach ($milestoneMap as $m) {
            $headings[] = $m['header'];
        }

        return $headings;
    }

    /**
     * @param array<int, array{source: string, header: string}> $milestoneMap
     */
    private function buildPidHeadings(bool $isPest, array $milestoneMap, array $fieldLabels = [], ?CompanyProfile $companyProfile = null): array
    {
        if ($isPest) {
            $headings = [
                $this->getFieldLabel('pid', $fieldLabels, 'PID'),
                $this->getFieldLabel('customer_name', $fieldLabels, 'Customer Name'),
                $this->getFieldLabel('pid', $fieldLabels, 'Prospect ID'),
                $this->getFieldLabel('customer_address', $fieldLabels, 'Customer Address'),
                $this->getFieldLabel('homeowner_id', $fieldLabels, 'Homeowner ID'),
                $this->getFieldLabel('customer_address_2', $fieldLabels, 'Customer Address2'),
                $this->getFieldLabel('closer1_id', $fieldLabels, 'Closer-1'),
                $this->getFieldLabel('closer2_id', $fieldLabels, 'Closer-2'),
                $this->getFieldLabel('proposal_id', $fieldLabels, 'Proposal ID'),
                $this->getFieldLabel('customer_city', $fieldLabels, 'Customer City'),
                $this->getFieldLabel('product', $fieldLabels, 'Product'),
                $this->getFieldLabel('product_id', $fieldLabels, 'Product Code'),
                $this->getFieldLabel('customer_state', $fieldLabels, 'Customer State'),
                $this->getFieldLabel('gross_account_value', $fieldLabels, 'Gross Account Value'),
                $this->getFieldLabel('location_code', $fieldLabels, 'Location Code'),
                $this->getFieldLabel('install_partner', $fieldLabels, 'Installer'),
                $this->getFieldLabel('customer_zip', $fieldLabels, 'Customer Zip'),
                $this->getFieldLabel('customer_email', $fieldLabels, 'Customer Email'),
                $this->getFieldLabel('customer_phone', $fieldLabels, 'Customer Phone'),
                $this->getFieldLabel('customer_signoff', $fieldLabels, 'Approved Date'),
                $this->getFieldLabel('dealer_fee_percentage', $fieldLabels, 'Dealer Fee %'),
                $this->getFieldLabel('dealer_fee_amount', $fieldLabels, 'Dealer Fee $'),
                $this->getFieldLabel('show', $fieldLabels, 'SOW'),
                $this->getFieldLabel('date_cancelled', $fieldLabels, 'Cancel Date'),
            ];
        } elseif ($companyProfile && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $headings = [
                'Borrower ID',
                'Borrower Name',
                'Prospect ID',
                'Customer Address',
                'Homeowner ID',
                'Customer Address2',
                'MLO',
                'MLO-2',
                'LOA',
                'LOA-2',
                'Proposal ID',
                'Customer City',
                'Product',
                'Product Code',
                'Customer State',
                'Gross Account Value',
                'Location Code',
                'Installer',
                'Customer Zip',
                'Loan Amount',
                'Customer Email',
                'Gross Revenue',
                'Customer Phone',
                'Fee',
                'Comp',
                'Sold Date',
                'Dealer Fee %',
                'Dealer Fee $',
                'SOW',
                'Cancel Date',
            ];
        } else {
            $headings = [
                $this->getFieldLabel('pid', $fieldLabels, 'PID'),
                $this->getFieldLabel('customer_name', $fieldLabels, 'Customer Name'),
                $this->getFieldLabel('pid', $fieldLabels, 'Prospect ID'),
                $this->getFieldLabel('customer_address', $fieldLabels, 'Customer Address'),
                $this->getFieldLabel('homeowner_id', $fieldLabels, 'Homeowner ID'),
                $this->getFieldLabel('customer_address_2', $fieldLabels, 'Customer Address2'),
                $this->getFieldLabel('closer1_id', $fieldLabels, 'Closer-1'),
                $this->getFieldLabel('closer2_id', $fieldLabels, 'Closer-2'),
                $this->getFieldLabel('setter1_id', $fieldLabels, 'Setter-1'),
                $this->getFieldLabel('setter2_id', $fieldLabels, 'Setter-2'),
                $this->getFieldLabel('proposal_id', $fieldLabels, 'Proposal ID'),
                $this->getFieldLabel('customer_city', $fieldLabels, 'Customer City'),
                $this->getFieldLabel('product', $fieldLabels, 'Product'),
                $this->getFieldLabel('product_id', $fieldLabels, 'Product Code'),
                $this->getFieldLabel('customer_state', $fieldLabels, 'Customer State'),
                $this->getFieldLabel('gross_account_value', $fieldLabels, 'Gross Account Value'),
                $this->getFieldLabel('location_code', $fieldLabels, 'Location Code'),
                $this->getFieldLabel('install_partner', $fieldLabels, 'Installer'),
                $this->getFieldLabel('customer_zip', $fieldLabels, 'Customer Zip'),
                $this->getFieldLabel('kw', $fieldLabels, 'KW'),
                $this->getFieldLabel('customer_email', $fieldLabels, 'Customer Email'),
                $this->getFieldLabel('epc', $fieldLabels, 'EPC'),
                $this->getFieldLabel('customer_phone', $fieldLabels, 'Customer Phone'),
                $this->getFieldLabel('net_epc', $fieldLabels, 'Net EPC'),
                $this->getFieldLabel('customer_signoff', $fieldLabels, 'Approved Date'),
                $this->getFieldLabel('dealer_fee_percentage', $fieldLabels, 'Dealer Fee %'),
                $this->getFieldLabel('dealer_fee_amount', $fieldLabels, 'Dealer Fee $'),
                $this->getFieldLabel('show', $fieldLabels, 'SOW'),
                $this->getFieldLabel('date_cancelled', $fieldLabels, 'Cancel Date'),
            ];
        }
        foreach ($milestoneMap as $m) {
            $headings[] = $m['header'];
        }

        return $headings;
    }

    /**
     * @param array<int, array{source: string, header: string}> $milestoneMap
     */
    private function mapSalesListRow(array $row, bool $isPest, bool $isRecon, array $milestoneMap, ?CompanyProfile $companyProfile = null): array
    {
        if ($isPest) {
            $values = [
                $row['pid'] ?? '-',
                isset($row['source']) ? ucfirst((string) $row['source']) : '-',
                isset($row['customer_name']) ? ucfirst((string) $row['customer_name']) : '-',
                $row['job_status'] ?? '-',
                isset($row['state']) ? ucwords((string) $row['state']) : '-',
                isset($row['location_code']) ? ucwords((string) $row['location_code']) : '-',
                $row['closer_1'] ?? '-',
                $row['closer_2'] ?? '-',
                $row['closer_1_email'] ?? '-',
                $row['closer_2_email'] ?? '-',
                $row['customer_signoff'] ?? '-',
                $this->formatMoney($row['gross_account_value'] ?? 0),
                $row['adders'] ?? '-',
                $this->formatMoney($row['total_commission'] ?? 0),
                $this->formatMoney($row['total_override'] ?? 0),
                $row['mark_account_status_name'] ?? '-',
            ];
        } elseif ($companyProfile && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $values = [
                $row['pid'] ?? '-',
                isset($row['source']) ? ucfirst((string) $row['source']) : '-',
                isset($row['customer_name']) ? ucfirst((string) $row['customer_name']) : '-',
                $row['job_status'] ?? '-',
                isset($row['state']) ? strtoupper((string) $row['state']) : '-',
                isset($row['location_code']) ? ucwords((string) $row['location_code']) : '-',
                $row['closer_1'] ?? '-',
                $row['closer_2'] ?? '-',
                $row['setter_1'] ?? '-',
                $row['setter_2'] ?? '-',
                $row['closer_1_email'] ?? '-',
                $row['closer_2_email'] ?? '-',
                $row['setter_1_email'] ?? '-',
                $row['setter_2_email'] ?? '-',
                $row['customer_signoff'] ?? '-',
                ($row['kw'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['kw']),
                ($row['epc'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['epc']),
                ($row['net_epc'] ?? 0) == 0 ? '0.0000%' : number_format((float) ($row['net_epc'] ?? 0), 4, '.', '') . '%',
                ($row['adders'] ?? 0) == 0 ? '0.0000%' : number_format((float) ($row['adders'] ?? 0), 4, '.', '') . '%',
                $this->formatMoney($row['total_commission'] ?? 0),
                $this->formatMoney($row['total_override'] ?? 0),
                $row['mark_account_status_name'] ?? '-',
            ];
        } else {
            $values = [
                $row['pid'] ?? '-',
                isset($row['source']) ? ucfirst((string) $row['source']) : '-',
                $row['customer_name'] ?? '-',
                $row['job_status'] ?? '-',
                isset($row['state']) ? strtoupper((string) $row['state']) : '-',
                isset($row['location_code']) ? ucwords((string) $row['location_code']) : '-',
                $row['closer_1'] ?? '-',
                $row['closer_2'] ?? '-',
                $row['setter_1'] ?? '-',
                $row['setter_2'] ?? '-',
                $row['closer_1_email'] ?? '-',
                $row['closer_2_email'] ?? '-',
                $row['setter_1_email'] ?? '-',
                $row['setter_2_email'] ?? '-',
                $row['customer_signoff'] ?? '-',
                ($row['kw'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['kw']),
                ($row['epc'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['epc']),
                ($row['net_epc'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['net_epc']),
                $row['adders'] ?? '-',
                $this->formatMoney($row['total_commission'] ?? 0),
                $this->formatMoney($row['total_override'] ?? 0),
                $row['mark_account_status_name'] ?? '-',
            ];
        }
        if ($isRecon) {
            $values[] = $this->formatMoney($row['total_recon'] ?? 0);
        }
        // Milestones (values, 0 if projected)
        $milestones = $row['all_milestone'] ?? [];
        foreach ($milestoneMap as $m) {
            $values[] = $this->formatMoney($this->milestoneValueOrZero($milestones, $m['source']));
        }

        return $values;
    }

    /**
     * @param array<int, array{source: string, header: string}> $milestoneMap
     */
    private function mapPidRow(array $row, bool $isPest, array $milestoneMap, ?CompanyProfile $companyProfile = null): array
    {
        if ($isPest) {
            $values = [
                $row['pid'] ?? '-',
                $row['customer_name'] ?? '-',
                $row['pid'] ?? '-',
                $row['customer_address'] ?? '-',
                $row['homeowner_id'] ?? '-',
                $row['customer_address_2'] ?? '-',
                $row['closer_1'] ?? '-',
                $row['closer_2'] ?? '-',
                $row['proposal_id'] ?? '-',
                isset($row['customer_city']) ? ucwords((string) $row['customer_city']) : '-',
                $row['product'] ?? '-',
                $row['product_id'] ?? '-',
                isset($row['state']) ? ucwords((string) $row['state']) : '-',
                $row['gross_account_value'] ?? '-',
                $row['location_code'] ?? '-',
                $row['installer'] ?? '-',
                $row['customer_zip'] ?? '-',
                $row['customer_email'] ?? '-',
                $row['customer_phone'] ?? '-',
                $row['customer_signoff'] ?? '-',
                $row['dealer_fee_percentage'] ?? '-',
                $row['dealer_fee_amount'] ?? '-',
                $row['show'] ?? '-',
                $row['date_cancelled'] ?? '-',
            ];
        } elseif ($companyProfile && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $values = [
                $row['pid'] ?? '-',
                isset($row['customer_name']) ? ucfirst((string) $row['customer_name']) : '-',
                $row['pid'] ?? '-',
                $row['customer_address'] ?? '-',
                $row['homeowner_id'] ?? '-',
                $row['customer_address_2'] ?? '-',
                $row['closer_1'] ?? '-',
                $row['closer_2'] ?? '-',
                $row['setter_1'] ?? '-',
                $row['setter_2'] ?? '-',
                $row['proposal_id'] ?? '-',
                isset($row['customer_city']) ? ucwords((string) $row['customer_city']) : '-',
                $row['product'] ?? '-',
                $row['product_id'] ?? '-',
                isset($row['state']) ? ucwords((string) $row['state']) : '-',
                $row['gross_account_value'] ?? '-',
                isset($row['location_code']) ? ucwords((string) $row['location_code']) : '-',
                $row['installer'] ?? '-',
                $row['customer_zip'] ?? '-',
                ($row['kw'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['kw']),
                $row['customer_email'] ?? '-',
                ($row['epc'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['epc']),
                $row['customer_phone'] ?? '-',
                ($row['net_epc'] ?? 0) == 0 ? '0.0000%' : number_format((float) ($row['net_epc'] ?? 0), 4, '.', '') . '%',
                ($row['adders'] ?? 0) == 0 ? '0.0000%' : number_format((float) ($row['adders'] ?? 0), 4, '.', '') . '%',
                $row['customer_signoff'] ?? '-',
                $row['dealer_fee_percentage'] ?? '-',
                $row['dealer_fee_amount'] ?? '-',
                $row['show'] ?? '-',
                $row['date_cancelled'] ?? '-',
            ];
        } else {
            $values = [
                $row['pid'] ?? '-',
                isset($row['customer_name']) ? ucfirst((string) $row['customer_name']) : '-',
                $row['pid'] ?? '-',
                $row['customer_address'] ?? '-',
                $row['homeowner_id'] ?? '-',
                $row['customer_address_2'] ?? '-',
                $row['closer_1'] ?? '-',
                $row['closer_2'] ?? '-',
                $row['setter_1'] ?? '-',
                $row['setter_2'] ?? '-',
                $row['proposal_id'] ?? '-',
                isset($row['customer_city']) ? ucwords((string) $row['customer_city']) : '-',
                $row['product'] ?? '-',
                $row['product_id'] ?? '-',
                isset($row['state']) ? ucwords((string) $row['state']) : '-',
                $row['gross_account_value'] ?? '-',
                isset($row['location_code']) ? ucwords((string) $row['location_code']) : '-',
                $row['installer'] ?? '-',
                $row['customer_zip'] ?? '-',
                ($row['kw'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['kw']),
                $row['customer_email'] ?? '-',
                ($row['epc'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['epc']),
                $row['customer_phone'] ?? '-',
                ($row['net_epc'] ?? 0) == 0 ? '$0.00' : '$'.exportNumberFormat($row['net_epc']),
                $row['customer_signoff'] ?? '-',
                $row['dealer_fee_percentage'] ?? '-',
                $row['dealer_fee_amount'] ?? '-',
                $row['show'] ?? '-',
                $row['date_cancelled'] ?? '-',
            ];
        }
        // Milestones (dates, null if projected)
        $milestones = $row['all_milestone'] ?? [];
        foreach ($milestoneMap as $m) {
            $values[] = $this->milestoneDateOrNull($milestones, $m['source']);
        }

        return $values;
    }

    private function milestoneValueOrZero(array $milestones, string $name)
    {
        $target = $this->normalizeMilestoneNameForMatch($name);
        foreach ($milestones as $m) {
            if ($target !== null && $this->normalizeMilestoneNameForMatch($m['name'] ?? null) === $target) {
                return ! ($m['is_projected'] ?? false) ? ($m['value'] ?? 0) : 0;
            }
        }

        return 0;
    }

    private function milestoneDateOrNull(array $milestones, string $name)
    {
        $target = $this->normalizeMilestoneNameForMatch($name);
        foreach ($milestones as $m) {
            if ($target !== null && $this->normalizeMilestoneNameForMatch($m['name'] ?? null) === $target) {
                return ! ($m['is_projected'] ?? false) ? ($m['date'] ?? null) : null;
            }
        }

        return null;
    }

    private function formatMoney($value): string
    {
        $num = floatval($value ?? 0);
        if ($num <= 0) {
            return '$ ('.exportNumberFormat(abs($num)).')';
        }

        return '$ '.exportNumberFormat($num);
    }

    /**config/octane.php
     * Preload lookup data using PIDs (avoids duplicate query)
     * OPTIMIZATION: Accepts PIDs directly instead of rebuilding query
     */
    private function preloadLookupDataByPids(array $pids, ?CompanyProfile $companyProfile = null)
    {

        if (empty($pids)) {
            return [
                'commissionData' => collect(),
                'reconCommissions' => collect(),
                'clawbackPids' => [],
                'reconciliationSetting' => null,
                'compRateData' => collect(),
            ];
        }

        // Pre-load all commission data for these PIDs (prevents N+1 queries)
        $commissionData = UserCommission::whereIn('pid', $pids)
            ->where('status', 3)
            ->get()
            ->keyBy('pid');

        // Pre-load comp_rate data for mortgage company type (prevents N+1 queries)
        // This was causing 5,000+ extra queries for large exports
        $compRateData = collect();
        if ($companyProfile && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $compRateData = UserCommission::whereIn('pid', $pids)
                ->where('comp_rate', '>', 0)
                ->select('pid', 'comp_rate')
                ->get()
                ->groupBy('pid')
                ->map(function ($group) {
                    // Get the first comp_rate for each PID (same logic as value() method)
                    $first = $group->first();
                    return $first ? $first->comp_rate : null;
                });
        }

        // Pre-load reconciliation commission data (prevents N+1 queries)
        $reconCommissions = UserCommission::selectRaw('SUM(amount) as amount, pid, user_id, date')
            ->whereIn('pid', $pids)
            ->where('settlement_type', 'reconciliation')
            ->groupBy('pid')
            ->get()
            ->keyBy('pid');

        // Get clawback PIDs once (this was running for every record in original!)
        $clawbackPids = ClawbackSettlement::whereNotNull('pid')
            ->whereIn('pid', $pids)
            ->groupBy('pid')
            ->pluck('pid')
            ->toArray();

        // Get reconciliation setting once (same as original)
        $reconciliationSetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

        return [
            'commissionData' => $commissionData,
            'reconCommissions' => $reconCommissions,
            'clawbackPids' => $clawbackPids,
            'reconciliationSetting' => $reconciliationSetting,
            'compRateData' => $compRateData,
        ];
    }

    /**
     * Build simplified query for getting PIDs only (no eager loading - much faster)
     * OPTIMIZATION: Used to get PIDs without loading all relations
     */
    private function buildBaseQueryForPids($startDate, $endDate, $request, $companyProfile)
    {
        $result = SalesMaster::query()
            ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })
            ->when((isset($request['office_id']) && ! empty($request['office_id'])), function ($q) use ($request) {
                $officeId = $request['office_id'];
                if ($officeId != 'all') {
                    $userId = User::withoutGlobalScopes()->where('office_id', $officeId)->pluck('id');
                    $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)
                        ->orWhereIn('closer2_id', $userId)
                        ->orWhereIn('setter1_id', $userId)
                        ->orWhereIn('setter2_id', $userId)
                        ->pluck('pid');
                    $q->whereIn('pid', $salesPid);
                }
            })
            ->when((isset($request['search']) && ! empty($request['search'])), function ($q) use ($request) {
                $search = $request['search'];
                $q->where(function ($query) use ($search) {
                    $query->where('pid', 'LIKE', '%'.$search.'%')
                        ->orWhere('customer_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('closer1_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('setter1_name', 'LIKE', '%'.$search.'%');
                });
            })
            ->when((isset($request['filter_product']) && ! empty($request['filter_product'])), function ($q) use ($request) {
                $q->where('product_id', $request['filter_product']);
            })
            ->when((isset($request['location']) && ! empty($request['location'])), function ($q) use ($request) {
                $q->where('customer_state', $request['location']);
            })
            ->when((isset($request['filter_install']) && ! empty($request['filter_install'])), function ($q) use ($request) {
                $q->where('install_partner', $request['filter_install']);
            })
            ->when((isset($request['filter_status']) && ! empty($request['filter_status'])), function ($q) use ($request) {
                $q->where('job_status', $request['filter_status']);
            })
            ->when((isset($request['date_filter']) && ! empty($request['date_filter'])), function ($q) use ($request) {
                if ($request['date_filter'] == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($request) {
                        $date_filter = $request['date_filter'];
                        $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($date_filter) {
                            $q->where('name', $date_filter);
                        });
                    });
                }
            });

        return $result->select('pid');
    }

    /**
     * Build the base query with same logic as original SalesExportJob
     */
    private function buildBaseQuery($startDate, $endDate, $request, $companyProfile)
    {
        $result = SalesMaster::with([
            'productInfo' => function ($q) {
                $q->withTrashed();
            },
            'salesMasterProcess.closer1Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcess.closer2Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcess.setter1Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcess.setter2Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcessInfo.status',
            'salesProductMaster' => function ($q) {
                $q->selectRaw('pid, type, SUM(amount) as value, milestone_date, is_projected, milestone_schema_id')->groupBy('pid', 'type');
            },
            'lastMilestone.milestoneSchemaTrigger',
            'salesProductMaster.milestoneSchemaTrigger',
            'legacyAPINull' => function ($q) {
                $q->whereNotNull('data_source_type');
            },
        ])
            ->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })
            ->when((isset($request['office_id']) && ! empty($request['office_id'])), function ($q) use ($request) {
                $officeId = $request['office_id'];
                if ($officeId != 'all') {
                    $userId = User::withoutGlobalScopes()->where('office_id', $officeId)->pluck('id');
                    $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)
                        ->orWhereIn('closer2_id', $userId)
                        ->orWhereIn('setter1_id', $userId)
                        ->orWhereIn('setter2_id', $userId)
                        ->pluck('pid');
                    $q->whereIn('pid', $salesPid);
                }
            })
            ->when((isset($request['search']) && ! empty($request['search'])), function ($q) use ($request) {
                $search = $request['search'];
                $q->where(function ($query) use ($search) {
                    $query->where('pid', 'LIKE', '%'.$search.'%')
                        ->orWhere('customer_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('closer1_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('setter1_name', 'LIKE', '%'.$search.'%');
                });
            })
            ->when((isset($request['filter_product']) && ! empty($request['filter_product'])), function ($q) use ($request) {
                $q->where('product_id', $request['filter_product']);
            })
            ->when((isset($request['location']) && ! empty($request['location'])), function ($q) use ($request) {
                $q->where('customer_state', $request['location']);
            })
            ->when((isset($request['filter_install']) && ! empty($request['filter_install'])), function ($q) use ($request) {
                $q->where('install_partner', $request['filter_install']);
            })
            ->when((isset($request['filter_status']) && ! empty($request['filter_status'])), function ($q) use ($request) {
                $q->where('job_status', $request['filter_status']);
            })
            ->when((isset($request['date_filter']) && ! empty($request['date_filter'])), function ($q) use ($request) {
                if ($request['date_filter'] == 'Cancel Date') {
                    $q->whereNotNull('date_cancelled');
                } else {
                    $q->whereHas('salesProductMaster', function ($q) use ($request) {
                        $date_filter = $request['date_filter'];
                        $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($date_filter) {
                            $q->where('name', $date_filter);
                        });
                    });
                }
            });

        // Apply sorting exactly like original
        $orderBy = (isset($request['sort_val']) && ! empty($request['sort_val'])) ? $request['sort_val'] : 'DESC';

        if (isset($request['sort']) && $request['sort'] == 'state') {
            $result->orderBy('customer_state', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 'kw') {
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $result->orderBy('gross_account_value', $orderBy);
            } else {
                $result->orderBy(DB::raw('CAST(kw AS UNSIGNED)'), $orderBy);
            }
        } elseif (isset($request['sort']) && $request['sort'] == 'epc') {
            $result->orderBy('epc', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 'net_epc') {
            $result->orderBy('net_epc', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 'adders') {
            $result->orderBy(DB::raw('CAST(adders AS UNSIGNED)'), $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 't_Commission') {
            $result->orderBy('total_commission', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 't_Overrides') {
            $result->orderBy('total_override', $orderBy);
        } else {
            $result->orderBy('id', $orderBy);
        }

        return $result;
    }

    /**
     * Transform chunk data efficiently using pre-loaded lookup data
     * This maintains the exact same data transformation logic as original SalesExportJob
     */
    private function transformChunkData($chunk, $lookupData, $companyProfile)
    {

        return $chunk->map(function ($data) use ($lookupData, $companyProfile) {
            $pid = $data->pid;

            // Use pre-loaded data instead of individual queries (major optimization)
            $commissionData = $lookupData['commissionData']->get($pid);
            $reconCommission = $lookupData['reconCommissions']->get($pid);
            $reconAmount = $reconCommission ? $reconCommission->amount : 0;
            $isClawback = in_array($pid, $lookupData['clawbackPids']);

            // Determine payment status exactly like original
            if ($data->salesMasterProcessInfo && ! in_array($data->salesMasterProcessInfo->mark_account_status_id ?? null, [1, 6]) && $commissionData) {
                $paymentStatus = ($commissionData) ? 'Paid' : null;
            } else {
                $paymentStatus = ($data->salesMasterProcessInfo && $data->salesMasterProcessInfo->status)
                    ? ($data->salesMasterProcessInfo->status->account_status ?? null)
                    : null;
            }

            // Process milestones exactly like original
            $allMileStones = [];
            foreach ($data->salesProductMaster as $mileStone) {
                $allMileStones[] = [
                    'name' => $mileStone?->milestoneSchemaTrigger?->name,
                    'trigger' => $mileStone?->milestoneSchemaTrigger?->on_trigger,
                    'value' => $mileStone->value,
                    'date' => $mileStone->milestone_date,
                    'is_projected' => $mileStone->is_projected,
                ];
            }

            // Determine job status exactly like original
            $firstMilestoneDate = reset($allMileStones)['date'] ?? null;
            $firstDate = $firstMilestoneDate && ! $data->date_cancelled;

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $jobStatus = match (true) {
                    $firstDate => 'Serviced',
                    $data->date_cancelled && $isClawback => 'Clawback',
                    $data->date_cancelled && ! $isClawback => 'Cancelled',
                    default => 'Pending',
                };
            } else {
                // Fallback to original job status with null safety
                $jobStatus = $data->job_status ?? 'Pending';
            }

            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $userCompRate = $lookupData['compRateData']->get($pid);
                $adders = $userCompRate ?? 0;
                $netepc = $data->net_epc * 100;
            } else {
                $adders = $data->adders;
                $netepc = $data->net_epc;
            }

            $productId = $data?->productInfo?->product_id;

            // Return exact same data structure as original
            return [
                'pid' => $data->pid,
                'source' => $data->data_source_type,
                'customer_name' => $data->customer_name,
                'job_status' => $jobStatus,
                'product' => $data->product,
                'product_id' => $productId,
                'state' => $data->customer_state,
                'location_code' => $data->location_code,
                'closer_1' => isset($data->salesMasterProcess->closer1Detail->first_name) ?
                    $data->salesMasterProcess->closer1Detail->first_name.' '.$data->salesMasterProcess->closer1Detail->last_name : null,
                'closer_2' => isset($data->salesMasterProcess->closer2Detail->first_name) ?
                    $data->salesMasterProcess->closer2Detail->first_name.' '.$data->salesMasterProcess->closer2Detail->last_name : null,
                'setter_1' => isset($data->salesMasterProcess->setter1Detail->first_name) ?
                    $data->salesMasterProcess->setter1Detail->first_name.' '.$data->salesMasterProcess->setter1Detail->last_name : null,
                'setter_2' => isset($data->salesMasterProcess->setter2Detail->first_name) ?
                    $data->salesMasterProcess->setter2Detail->first_name.' '.$data->salesMasterProcess->setter2Detail->last_name : null,
                'closer_1_email' => isset($data->salesMasterProcess->closer1Detail->email) ? $data->salesMasterProcess->closer1Detail->email : null,
                'closer_2_email' => isset($data->salesMasterProcess->closer2Detail->email) ? $data->salesMasterProcess->closer2Detail->email : null,
                'setter_1_email' => isset($data->salesMasterProcess->setter1Detail->email) ? $data->salesMasterProcess->setter1Detail->email : null,
                'setter_2_email' => isset($data->salesMasterProcess->setter2Detail->email) ? $data->salesMasterProcess->setter2Detail->email : null,
                'customer_signoff' => $data->customer_signoff,
                'gross_account_value' => $data->gross_account_value,
                'adders' => $adders,
                'total_commission' => $data->total_commission + $reconAmount,
                'total_recon' => $reconAmount,
                'total_override' => $data->total_override,
                'mark_account_status_name' => $paymentStatus,
                'kw' => $data->kw,
                'epc' => $data->epc,
                'net_epc' => $netepc,
                'customer_address' => $data->customer_address,
                'homeowner_id' => $data->homeowner_id,
                'customer_address_2' => $data->customer_address_2,
                'proposal_id' => $data->proposal_id,
                'installer' => $data->installer,
                'customer_city' => $data->customer_city,
                'customer_zip' => $data->customer_zip,
                'customer_email' => $data->customer_email,
                'customer_phone' => $data->customer_phone,
                'dealer_fee_percentage' => $data->dealer_fee_percentage,
                'dealer_fee_amount' => $data->dealer_fee_amount,
                'show' => $data->show,
                'date_cancelled' => $data->date_cancelled,
                'all_milestone' => $allMileStones,
            ];
        });
    }

    /**
     * Send Pusher notification exactly like original SalesExportJob
     */
    private function sendPusherNotification($url, $request)
    {
        $domainName = config('app.domain_name');
        $pusherEvent = 'sale-export-excel'; // Same as original
        $pusherMsg = 'Sales exported successfully';
        $pusherUniqueKey = $request['session_key'] ?? null; // Same as original

        // Log all parameters before sending Pusher notification
        Log::info('Sales Export - Preparing Pusher notification', [
            'domain_name' => $domainName,
            'domain_name_env' => config('app.domain_name'),
            'pusher_event' => $pusherEvent,
            'pusher_message' => $pusherMsg,
            'pusher_unique_key' => $pusherUniqueKey,
            'file_url' => $url,
            'request_data' => [
                'session_key' => $request['session_key'] ?? null,
                'has_session_key' => isset($request['session_key']),
            ],
            'pusher_channel' => 'sequifi-'.$domainName,
            'broadcast_event' => $pusherEvent,
            'pusher_config' => [
                'app_id' => config('broadcasting.connections.pusher.app_id') ? 'SET' : 'NOT SET',
                'key' => config('broadcasting.connections.pusher.key') ? 'SET' : 'NOT SET',
                'secret' => config('broadcasting.connections.pusher.secret') ? 'SET' : 'NOT SET',
                'cluster' => config('broadcasting.connections.pusher.options.cluster', 'not_set'),
                'broadcast_driver' => config('broadcasting.default'),
                'broadcast_service_provider_registered' => in_array(\App\Providers\BroadcastServiceProvider::class, config('app.providers', [])),
            ],
        ]);

        try {
            $event = new SendSalesExportToPusher($domainName, $pusherEvent, $pusherMsg, $url, $pusherUniqueKey);

            Log::info('Sales Export - Pusher event created', [
                'event_class' => get_class($event),
                'event_domain_name' => $event->domain_name,
                'event_name' => $event->event_name,
                'event_report_url' => $event->report_url,
                'event_pusher_unique_key' => $event->pusherUniqueKey,
            ]);

            event($event);

            Log::info('Sales Export - Pusher event dispatched', [
                'domain_name' => $domainName,
                'channel' => 'sequifi-'.$domainName,
                'event' => $pusherEvent,
            ]);
        } catch (\Exception $e) {
            Log::error('Sales Export - Pusher notification failed', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'domain_name' => $domainName,
                'url' => $url,
                'session_key' => $pusherUniqueKey,
            ]);
            throw $e;
        }
    }

    /**
     * Get date range from filter - EXACT same logic as original SalesExportJob
     */
    public function getDateFromFilter($request)
    {
        $startDate = null;
        $endDate = null;
        if (isset($request['filter']) && ! empty($request['filter'])) {
            $filterDataDateWise = $request['filter'];
            if ($filterDataDateWise == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            } elseif ($filterDataDateWise == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } elseif ($filterDataDateWise == 'custom') {
                $startDateInput = $request['start_date'] ?? null;
                $endDateInput = $request['end_date'] ?? null;
                if (! empty($startDateInput)) {
                    $startDate = date('Y-m-d', strtotime($startDateInput));
                }
                if (! empty($endDateInput)) {
                    $endDate = date('Y-m-d', strtotime($endDateInput));
                }
            }
        }

        return [$startDate, $endDate];
    }

    /**
     * Estimate record count to prevent memory exhaustion
     */
    private function estimateRecordCount($request)
    {
        $companyProfile = CompanyProfile::first();
        [$startDate, $endDate] = $this->getDateFromFilter($request);

        return $this->buildBaseQuery($startDate, $endDate, $request, $companyProfile)->count();
    }
}
