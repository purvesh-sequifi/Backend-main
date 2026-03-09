<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Core\Traits\SaleTraits\EditSaleTrait;
use App\Core\Traits\SaleTraits\SubroutineTrait;
use App\Helpers\CustomSalesFieldHelper;
use App\Jobs\GenerateAlertJob;
use App\Models\CompanyProfile;
use App\Models\ExcelImportHistory;
use App\Models\FiberSalesImportTemplateDetail;
use Laravel\Pennant\Feature;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\MortgageSalesImportTemplateDetail;
use App\Models\PestSalesImportTemplateDetail;
use App\Models\Products;
use App\Models\RoofingSalesImportTemplateDetail;
use App\Models\SaleMasterProcess;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\SolarSalesImportTemplateDetail;
use App\Models\TurfSalesImportTemplateDetail;
use App\Services\JobNotificationService;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Services\ExcelImportCounterService;
use App\Support\SalesExcelImportContext;
use App\Traits\EmailNotificationTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesExcelProcessController extends BaseController
{
    use EditSaleTrait, EmailNotificationTrait, SubroutineTrait;

    protected ExcelImportCounterService $counterService;

    public function __construct(ExcelImportCounterService $counterService)
    {
        $this->counterService = $counterService;
    }

    // NOTE: DO NOT USE THIS PROCESS BESIDES FROM EXCEL IMPORT PROCESS.
    public function salesExcelCompleteProcess($user = null, $excel = null, ?string $importNotificationKey = null, ?string $importInitiatedAt = null)
    {
        $excelId = $excel->id;
        $excel = ExcelImportHistory::where('id', $excelId)->first();
        if (! $excel) {
            return false;
        }
        $salesController = new SalesController;

        $successPID = [];
        try {
            $initiatorUserId = (int) ($user->id ?? 0);
            $salesProcessKey = 'sales_process_excel_' . (int) $excelId;
            $importKey = is_string($importNotificationKey) && $importNotificationKey !== ''
                ? $importNotificationKey
                : ('sales_excel_import_' . (int) $excelId);
            $importStartedAt = is_string($importInitiatedAt) && $importInitiatedAt !== ''
                ? $importInitiatedAt
                : now()->toIso8601String();
            // Share these so saveErrorReport can emit progress updates without changing signatures.
            app(SalesExcelImportContext::class)->set($importKey, $importStartedAt, $initiatorUserId);
            $salesProcessInitiatedAt = null;
            $recalcTasks = [];

            $companyProfile = CompanyProfile::first();

            // Check if Custom Sales Fields feature is enabled ONCE before loops
            $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

            [$templateMappedFields, $templateCustomFields, $templateMappedCustomFields] = $this->getTemplateData($excel->template_id, $companyProfile);
            if (count($templateMappedFields) == 0) {
                return false;
            }

            $domainName = config('app.domain_name');
            $allProducts = Products::withTrashed()->pluck('product_id', 'id')->toArray();
            $defaultProduct = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
            $history = LegacyApiRawDataHistory::where(['data_source_type' => 'excel', 'import_to_sales' => '0', 'excel_import_id' => $excelId])
                ->orderBy('id', 'ASC');

            // Track processing metrics
            $totalPendingRecords = $history->count();
            $processedInChunks = 0;
            $rowNumber = 0;

            // Keep Import History progress denominator aligned to actual raw rows linked to this import.
            // If total_records was computed differently at upload time, the UI % and notification % will diverge.
            try {
                $currentTotal = (int) ($excel->total_records ?? 0);
                if ($currentTotal !== $totalPendingRecords) {
                    ExcelImportHistory::where('id', $excelId)->update([
                        'total_records' => $totalPendingRecords,
                    ]);
                    $excel->total_records = $totalPendingRecords;
                }
            } catch (\Throwable $e) {
                Log::warning('[SalesExcelImport] Failed to align total_records', [
                    'excel_id' => (int) $excelId,
                    'current_total' => (int) ($excel->total_records ?? 0),
                    'expected_total' => (int) $totalPendingRecords,
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }

            // Pre-load all PIDs from this import to check existence (optimization for counter logic)
            $allImportPids = LegacyApiRawDataHistory::where(['data_source_type' => 'excel', 'import_to_sales' => '0', 'excel_import_id' => $excelId])
                ->pluck('pid')
                ->toArray();

            // Pre-load existing PIDs from database (single query for entire import)
            $existingPidsInDb = SalesMaster::whereIn('pid', $allImportPids)
                ->pluck('pid')
                ->flip() // Convert to associative array for O(1) lookup
                ->toArray();

            // If there were no existing PIDs, this is effectively a "brand new" import.
            // In that case, user-facing copy should say "Sales calculation" instead of "Sales recalculation".
            $isBrandNewImport = count($existingPidsInDb) === 0;

            // Mark Phase 1 (sale import) so Import History shows "File uploading" 0-100% in same row
            try {
                ExcelImportHistory::where('id', $excelId)->update([
                    'current_phase' => ExcelImportHistory::PHASE_SALE_IMPORT,
                    'phase_progress' => 0,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[SalesExcelImport] Failed to set phase start', [
                    'excel_id' => (int) $excelId,
                    'phase' => ExcelImportHistory::PHASE_SALE_IMPORT,
                    'phase_progress' => 0,
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Excel import starting chunk processing', [
                'excel_import_id' => $excelId,
                'total_pending_records' => $totalPendingRecords,
                'chunk_size' => 100,
                'expected_chunks' => ceil($totalPendingRecords / 100),
                'existing_pids_count' => count($existingPidsInDb),
                'expected_new_records' => $totalPendingRecords - count($existingPidsInDb),
            ]);
            // CRITICAL CHECK: Verify that records exist for processing
            $totalRecordsToProcess = $history->count();
            if ($totalRecordsToProcess === 0) {
                // NO RECORDS FOUND - This indicates a linking error
                $errorDetails = [
                    'error_type' => 'no_records_found',
                    'message' => 'Import failed: No records were found to process. Records may not have been properly linked to this import. Please re-upload your file.',
                    'technical_details' => "Query returned 0 records with excel_import_id = {$excelId}",
                    'timestamp' => now()->toIso8601String(),
                ];

                ExcelImportHistory::where('id', $excelId)->update([
                    'status' => 2, // Failed
                    'current_phase' => null,
                    'errors' => json_encode($errorDetails),
                ]);

                \Log::error('Excel Import Processing Error: No Records Found', [
                    'excel_id' => $excelId,
                    'error' => $errorDetails,
                ]);
                return false;
            }

            $rowNumber = 0; // Row number counter (1-based)
            $history->chunkById(100, function ($records) use (
                $excelId,
                $domainName,
                $allProducts,
                $companyProfile,
                $defaultProduct,
                $salesController,
                $templateMappedFields,
                $templateCustomFields,
                $templateMappedCustomFields,
                &$rowNumber,
                &$processedInChunks,
                $totalPendingRecords,
                &$existingPidsInDb,
                $initiatorUserId,
                $importKey,
                $importStartedAt,
                &$salesRecalcInitiatedAt,
                &$recalcTasks,
                $isCustomFieldsEnabled
            ) {
                // PERFORMANCE FIX: Track counters locally during chunk processing
                // This reduces Redis lock operations from 1513 (per-row) to ~15 (per-chunk)
                // Expected improvement: 60-90 min → 10-12 min (80-85% faster)
                $chunkCounters = [
                    'updated_records' => 0,
                    'new_records' => 0,
                    'error_records' => 0,
                ];

                // Log chunk processing.
                // NOTE: Import History % is derived from ExcelImportHistory counters:
                // (new_records + updated_records + error_records) / total_records.
                // We emit sales import notification progress using the same counters *after* the chunk is processed,
                // so the Import History progress bar and notification progress bar stay aligned.
                $chunkSize = 100;
                $chunkNumber = (int) floor($processedInChunks / $chunkSize) + 1;
                $processedInChunks += count($records);

                Log::info('Processing Excel import chunk', [
                    'excel_import_id' => $excelId,
                    'chunk_number' => $chunkNumber,
                    'records_in_chunk' => count($records),
                    'processed_before_chunk' => $processedInChunks,
                    'total_expected' => $totalPendingRecords,
                ]);
                $saleMasterRecords = SalesMaster::with('salesMasterProcess')->whereIn('pid', $records->pluck('pid'))->get();
                $saleProductRecords = SaleProductMaster::whereIn('pid', $records->pluck('pid'))->groupBy('pid', 'type')->get();

                $paidCombined = [];
                $paidMilestones = [];
                $paidCommissions = [];
                $paidCombinedRecon = [];
                $paidMilestonesRecon = [];
                $paidCommissionsRecon = [];
                $commissions = UserCommission::select(
                    'pid',
                    'status',
                    'is_last',
                    DB::raw('CONCAT(pid, "-", schema_type) as combined_key'),
                )->whereIn('pid', $records->pluck('pid'))->where(['status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
                foreach ($commissions as $commission) {
                    if ($commission->is_last) {
                        $paidCommissions[$commission->pid] = $commission->status;
                    }
                    $paidMilestones[$commission->combined_key] = $commission->status;
                    $paidCombined[$commission->pid] = $commission->status;
                }
                $reconCommissions = UserCommission::select(
                    'pid',
                    'recon_status',
                    'is_last',
                    DB::raw('CONCAT(pid, "-", schema_type) as combined_key'),
                )->whereIn('pid', $records->pluck('pid'))->where(['settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->pluck('recon_status', 'combined_key');
                foreach ($reconCommissions as $reconCommission) {
                    if ($reconCommission->is_last) {
                        $paidCommissionsRecon[$reconCommission->pid] = $reconCommission->status;
                    }
                    $paidMilestonesRecon[$reconCommission->combined_key] = $reconCommission->status;
                    $paidCombinedRecon[$reconCommission->pid] = $reconCommission->status;
                }

                $override = UserOverrides::whereIn('pid', $records->pluck('pid'))->where(['status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->pluck('status', 'pid');
                $reconOverride = UserOverrides::whereIn('pid', $records->pluck('pid'))->where(['overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->pluck('recon_status', 'pid');
                foreach ($records as $checked) {
                    // Use actual CSV row number from database instead of processing order counter
                    $rowNumber = $checked->row_number ?? ($rowNumber + 1);
                    $salesMaster = $saleMasterRecords->where('pid', $checked->pid)->first();
                    try {
                        DB::beginTransaction();
                        $saleMasterData = $this->buildCreateSaleMasterData($checked, $allProducts, $defaultProduct, $saleProductRecords, $templateMappedFields, $templateCustomFields, $templateMappedCustomFields, $companyProfile);
                        if ($domainName === 'phoenixlending' && array_key_exists('net_epc', $saleMasterData)) {
                            $saleMasterData['net_epc'] = (($saleMasterData['net_epc'] ?? 0) > 0) ? $saleMasterData['net_epc'] : 1;
                        }

                        if (! isset($saleMasterData['customer_signoff'])) {
                            DB::rollBack();
                            $this->saveErrorReport($checked->id, $excelId, "Sale Date Missing Error", ['pid' => $checked->pid, 'errors' => ['Sale date not found!!']], $rowNumber, false);
                            $chunkCounters['error_records']++;
                            continue;
                        }

                        $validateEmptyState = $this->validateDateState($saleMasterData['customer_signoff']);
                        if (! $validateEmptyState['success']) {
                            DB::rollBack();
                            $this->saveErrorReport($checked->id, $excelId, "Sale Date Invalid Error", ['pid' => $checked->pid, 'errors' => ['Sale date is invalid!!']], $rowNumber, false);
                            $chunkCounters['error_records']++;
                            continue;
                        }

                        if (array_key_exists('product_id', $saleMasterData)) {
                            $productId = $saleMasterData['product_id'];
                        } else {
                            $productId = $salesMaster?->product_id;
                        }
                        if (! $productId) {
                            $productId = $defaultProduct->id;
                        }

                        $milestoneDates = [];
                        $milestone = $this->milestoneWithSchema($productId, $saleMasterData['customer_signoff'], false);
                        // Use values() to ensure sequential numeric keys (0, 1, 2...) instead of record IDs
                        $triggers = isset($milestone?->milestone?->milestone_trigger)
                            ? $milestone->milestone->milestone_trigger->values()
                            : collect([]);

                        $triggerDebug = [];
                        foreach ($triggers as $key => $trigger) {
                            // FIX: Match milestone trigger with trigger_dates by field_name
                            // trigger_dates now contains field_name which we match against trigger.on_trigger (not name)
                            // trigger.name is display name (e.g. "M1 Date"), trigger.on_trigger is field name for matching
                            $triggerName = $trigger->on_trigger ?? $trigger->name ?? null;
                            $normalizedTriggerName = $triggerName ? strtolower(trim($triggerName)) : null;
                            $dateValue = null;
                            $matchedBy = 'not_found';
                            $matchedFieldName = null;

                            // Search through milestone_dates (which is trigger_dates with field_name)
                            if (is_array($saleMasterData['milestone_dates'] ?? null)) {
                                foreach ($saleMasterData['milestone_dates'] as $idx => $milestoneData) {
                                    $fieldName = $milestoneData['field_name'] ?? null;
                                    $normalizedFieldName = $fieldName ? strtolower(trim($fieldName)) : null;

                                    // Match by normalized name (case-insensitive)
                                    if ($normalizedTriggerName && $normalizedFieldName && $normalizedTriggerName === $normalizedFieldName) {
                                        $dateValue = $milestoneData['date'] ?? null;
                                        $matchedBy = 'field_name';
                                        $matchedFieldName = $fieldName;
                                        break;
                                    }
                                }
                            }

                            // Fallback to numeric index if no match by name
                            if ($dateValue === null && isset($saleMasterData['milestone_dates'][$key]['date'])) {
                                $dateValue = $saleMasterData['milestone_dates'][$key]['date'];
                                $matchedBy = 'index_fallback';
                            }

                            $milestoneDates[] = [
                                'date' => $dateValue,
                            ];
                            $triggerDebug[] = [
                                'loop_key' => $key,
                                'trigger_id' => $trigger->id ?? 'N/A',
                                'trigger_name' => $trigger->name ?? 'N/A',
                                'trigger_on_trigger' => $trigger->on_trigger ?? 'N/A',
                                'used_for_matching' => $triggerName,
                                'matched_by' => $matchedBy,
                                'matched_field_name' => $matchedFieldName,
                                'date_value' => $dateValue,
                            ];
                        }

                        // Build milestone_trigger JSON for SalesMaster (for history tracking)
                        // Wrapped in try-catch to prevent import failure if milestone_trigger logic fails
                        try {
                            if (!empty($milestoneDates) && $triggers->isNotEmpty()) {
                                $milestoneTriggerData = [];
                                foreach ($triggers as $key => $trigger) {
                                    $date = $milestoneDates[$key]['date'] ?? null;
                                    $milestoneTriggerData[] = [
                                        'name' => $trigger->name ?? 'Unknown',
                                        'trigger' => $trigger->on_trigger ?? $trigger->name ?? 'Unknown',
                                        'date' => $date,
                                    ];
                                }
                                if (!empty($milestoneTriggerData)) {
                                    $saleMasterData['milestone_trigger'] = json_encode($milestoneTriggerData, JSON_UNESCAPED_SLASHES);
                                }
                            }
                        } catch (\Throwable $e) {
                            // Log error but continue import - milestone_trigger is optional
                            \Log::warning('[MILESTONE_TRIGGER] Failed to build milestone_trigger JSON', [
                                'pid' => $checked->pid,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }

                        \Log::info('[MILESTONE_DEBUG] Final milestoneDates formation', [
                            'pid' => $checked->pid,
                            'product_id' => $productId,
                            'triggers_count' => count($triggers),
                            'saleMasterData_milestone_dates' => $saleMasterData['milestone_dates'] ?? [],
                            'trigger_iteration_debug' => $triggerDebug,
                            'final_milestoneDates' => $milestoneDates,
                            'milestone_trigger' => $saleMasterData['milestone_trigger'] ?? null,
                        ]);

                        if (count($milestoneDates) > 0) {
                            $validateMilestoneDates = $this->validateMilestoneDates($milestoneDates, $saleMasterData['customer_signoff']);
                            if (! $validateMilestoneDates['success']) {
                                DB::rollBack();
                                $this->saveErrorReport($checked->id, $excelId, "Milestone Date Error", ['pid' => $checked->pid, 'errors' => [$validateMilestoneDates['message']]], $rowNumber, false);
                                $chunkCounters['error_records']++;
                                continue;
                            }
                        }

                        if ($salesMaster) {
                            $calculate = 0;
                            if (array_key_exists('customer_signoff', $saleMasterData) && $salesMaster->customer_signoff != $saleMasterData['customer_signoff']) {
                                DB::rollBack();
                                $this->saveErrorReport($checked->id, $excelId, "Sale Date Change Error", ['pid' => $checked->pid, 'errors' => ['Sale date can not be changed!!']], $rowNumber, false);
                                $chunkCounters['error_records']++;
                                continue;
                            }

                            salesDataChangesClawback($salesMaster->pid);

                            // ✅ BLOCK: If sale is cancelled, remove closer/setter fields from update data
                            // This allows other fields (GAV, cancel_date, etc.) to be updated while preventing sales rep changes
                            if (!empty($salesMaster->date_cancelled)) {
                                $removedFields = [];
                                $fieldsToBlock = ['closer1_id', 'closer2_id', 'setter1_id', 'setter2_id'];

                                foreach ($fieldsToBlock as $field) {
                                    if (array_key_exists($field, $saleMasterData) && $salesMaster->$field != $saleMasterData[$field]) {
                                        $removedFields[] = $field;
                                        unset($saleMasterData[$field]);
                                    }
                                }

                                if (!empty($removedFields)) {
                                    \Log::info('[EXCEL_IMPORT] Blocked closer/setter updates on cancelled sale', [
                                        'pid' => $salesMaster->pid,
                                        'blocked_fields' => $removedFields,
                                        'row_number' => $rowNumber,
                                        'message' => 'Closers/Setters cannot be changed on cancelled sales. Other fields will be updated.'
                                    ]);
                                }
                            }

                            if (array_key_exists('product_id', $saleMasterData) && $salesMaster->product_id != $saleMasterData['product_id']) {
                                if (isset($paidCombined[$salesMaster->pid]) || isset($paidCombinedRecon[$salesMaster->pid]) || isset($override[$salesMaster->pid]) || isset($reconOverride[$salesMaster->pid])) {
                                    DB::rollBack();
                                    if (isset($paidCombined[$salesMaster->pid])) {
                                        $this->saveErrorReport($checked->id, $excelId, "Product Change Error", ['pid' => $checked->pid, 'errors' => ['Product can not be changed because the Milestone amount has already been paid!!']], $rowNumber, false);
                                    }
                                    if (isset($paidCombinedRecon[$salesMaster->pid])) {
                                        $this->saveErrorReport($checked->id, $excelId, "Product Change Error", ['pid' => $checked->pid, 'errors' => ['Product can not be changed because the milestone amount from reconciliation has already been paid!!']], $rowNumber, false);
                                    }
                                    if (isset($override[$salesMaster->pid])) {
                                        $this->saveErrorReport($checked->id, $excelId, "Product Change Error", ['pid' => $checked->pid, 'errors' => ['Product can not be changed because the override amount has already been paid!!']], $rowNumber, false);
                                    }
                                    if (isset($reconOverride[$salesMaster->pid])) {
                                        $this->saveErrorReport($checked->id, $excelId, "Product Change Error", ['pid' => $checked->pid, 'errors' => ['Product can not be changed because the reconciliation override amount has already been paid!!']], $rowNumber, false);
                                    }
                                    $chunkCounters['error_records']++;
                                    continue;
                                } else {
                                    $this->saleProductMappingChanges($salesMaster->pid);
                                }
                                $calculate += 1;
                            }

                            if (array_key_exists('closer1_id', $saleMasterData) && $salesMaster->closer1_id != $saleMasterData['closer1_id']) {
                                if (isset($paidCommissions[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Closer Change Error", ['pid' => $checked->pid, 'errors' => ['Closer can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } elseif (isset($paidCommissionsRecon[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Closer Change Error", ['pid' => $checked->pid, 'errors' => ['Closer can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } else {
                                    $this->clawBackSalesData($salesMaster->closer1_id, $salesMaster);
                                    $this->removeClawBackForNewUser($saleMasterData['closer1_id'], $salesMaster);
                                }
                                $calculate += 1;
                            }

                            if (array_key_exists('closer2_id', $saleMasterData) && $salesMaster->closer2_id != $saleMasterData['closer2_id']) {
                                if (isset($paidCommissions[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Closer 2 Change Error", ['pid' => $checked->pid, 'errors' => ['Closer 2 can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } elseif (isset($paidCommissionsRecon[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Closer 2 Change Error", ['pid' => $checked->pid, 'errors' => ['Closer 2 can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } else {
                                    $this->clawBackSalesData($salesMaster->closer2_id, $salesMaster);
                                    $this->removeClawBackForNewUser($saleMasterData['closer2_id'], $salesMaster);
                                }
                                $calculate += 1;
                            }

                            if (array_key_exists('setter1_id', $saleMasterData) && $salesMaster->setter1_id != $saleMasterData['setter1_id']) {
                                if (isset($paidCommissions[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Setter Change Error", ['pid' => $checked->pid, 'errors' => ['Setter can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } elseif (isset($paidCommissionsRecon[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Setter Change Error", ['pid' => $checked->pid, 'errors' => ['Setter can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } else {
                                    $this->clawBackSalesData($salesMaster->setter1_id, $salesMaster);
                                    $this->removeClawBackForNewUser($saleMasterData['setter1_id'], $salesMaster);
                                }
                                $calculate += 1;
                            }

                            if (array_key_exists('setter2_id', $saleMasterData) && $salesMaster->setter2_id != $saleMasterData['setter2_id']) {
                                if (isset($paidCommissions[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Setter 2 Change Error", ['pid' => $checked->pid, 'errors' => ['Setter 2 can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } elseif (isset($paidCommissionsRecon[$salesMaster->pid])) {
                                    DB::rollBack();
                                    $this->saveErrorReport($checked->id, $excelId, "Setter 2 Change Error", ['pid' => $checked->pid, 'errors' => ['Setter 2 can not be changed because the commission amount has already been paid!!']], $rowNumber, false);
                                    $chunkCounters['error_records']++;
                                    continue;
                                } else {
                                    $this->clawBackSalesData($salesMaster->setter2_id, $salesMaster);
                                    $this->removeClawBackForNewUser($saleMasterData['setter2_id'], $salesMaster);
                                }
                                $calculate += 1;
                            }

                            $failed = false;
                            $upFrontRemove = [];
                            $commissionRemove = [];
                            $upFrontChange = [];
                            $commissionChange = [];
                            foreach ($milestoneDates as $key => $milestoneDate) {
                                $schemaType = 'm' . ($key + 1);
                                $saleProduct = $saleProductRecords->where('pid', $salesMaster->pid)->where('type', $schemaType)->first();
                                if ($saleProduct) {
                                    if (! empty($saleProduct->milestone_date) && empty($milestoneDate['date'])) {
                                        if (isset($paidMilestones[$salesMaster->pid . '-' . $schemaType])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Remove Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be removed because the milestone amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } elseif (isset($paidMilestonesRecon[$salesMaster->pid . '-' . $schemaType])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Remove Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be removed because the milestone amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } elseif ($saleProduct->is_override && isset($override[$salesMaster->pid])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Remove Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be removed because the override amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } elseif ($saleProduct->is_override && isset($reconOverride[$salesMaster->pid])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Remove Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be removed because the reconciliation override amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } else {
                                            $calculate += 1;
                                            if ($saleProduct->is_last_date) {
                                                $commissionRemove[] = $schemaType;
                                            } else {
                                                $upFrontRemove[] = $schemaType;
                                            }
                                        }
                                    } elseif ($saleProduct->milestone_date != $milestoneDate['date']) {
                                        if (isset($paidMilestones[$salesMaster->pid . '-' . $schemaType])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Change Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be changed because the milestone amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } elseif (isset($paidMilestonesRecon[$salesMaster->pid . '-' . $schemaType])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Change Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be changed because the milestone amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } elseif ($saleProduct->is_override && isset($override[$salesMaster->pid])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Change Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be changed because the override amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } elseif ($saleProduct->is_override && isset($reconOverride[$salesMaster->pid])) {
                                            $failed = true;
                                            DB::rollBack();
                                            $this->saveErrorReport($checked->id, $excelId, "Milestone Date Change Error", ['pid' => $checked->pid, 'errors' => ['Milestone date can not be changed because the reconciliation override amount has already been paid!!']], $rowNumber, false);
                                            $chunkCounters['error_records']++;
                                            break;
                                        } else {
                                            $calculate += 1;
                                            if ($saleProduct->is_last_date) {
                                                $commissionChange[] = [
                                                    'type' => $schemaType,
                                                    'date' => $milestoneDate['date'],
                                                ];
                                            } else {
                                                $upFrontChange[] = [
                                                    'type' => $schemaType,
                                                    'date' => $milestoneDate['date'],
                                                ];
                                            }
                                        }
                                    }
                                }
                            }

                            if ($failed) {
                                continue;
                            }

                            foreach ($upFrontRemove as $remove) {
                                $this->removeUpFrontSaleData($salesMaster->pid, $remove);
                            }

                            foreach ($upFrontChange as $change) {
                                $this->changeUpFrontPayrollData($salesMaster->pid, $change);
                            }

                            if (count($commissionRemove) != 0) {
                                $this->removeCommissionSaleData($salesMaster->pid);
                            }

                            foreach ($commissionChange as $change) {
                                $this->changeCommissionPayrollData($salesMaster->pid, $change);
                            }

                            if (count($milestoneDates) == 0) {
                                $this->removeUpFrontSaleData($salesMaster->pid);
                                $this->removeCommissionSaleData($salesMaster->pid);
                            }

                            if (array_key_exists('kw', $saleMasterData) && $salesMaster->kw != $saleMasterData['kw']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('net_epc', $saleMasterData) && $salesMaster->net_epc != $saleMasterData['net_epc']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('gross_account_value', $saleMasterData) && $salesMaster->gross_account_value != $saleMasterData['gross_account_value']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('date_cancelled', $saleMasterData) && $salesMaster->date_cancelled != $saleMasterData['date_cancelled']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('customer_state', $saleMasterData) && $salesMaster->customer_state != $saleMasterData['customer_state']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('product_id', $saleMasterData) && $salesMaster->product_id != $saleMasterData['product_id']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('closer1_id', $saleMasterData) && $salesMaster->closer1_id != $saleMasterData['closer1_id']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('closer2_id', $saleMasterData) && $salesMaster->closer2_id != $saleMasterData['closer2_id']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('setter1_id', $saleMasterData) && $salesMaster->setter1_id != $saleMasterData['setter1_id']) {
                                $calculate += 1;
                            }
                            if (array_key_exists('setter2_id', $saleMasterData) && $salesMaster->setter2_id != $saleMasterData['setter2_id']) {
                                $calculate += 1;
                            }

                            $saleMaster = SalesMaster::updateOrCreate(['pid' => $saleMasterData['pid']], $saleMasterData);
                            $saleMasterProcessData = ['sale_master_id' => $saleMaster->id];
                            if (array_key_exists('closer1_id', $saleMasterData)) {
                                $saleMasterProcessData['closer1_id'] = $saleMasterData['closer1_id'];
                            }
                            if (array_key_exists('closer2_id', $saleMasterData)) {
                                $saleMasterProcessData['closer2_id'] = $saleMasterData['closer2_id'];
                            }
                            if (array_key_exists('setter1_id', $saleMasterData)) {
                                $saleMasterProcessData['setter1_id'] = $saleMasterData['setter1_id'];
                            }
                            if (array_key_exists('setter2_id', $saleMasterData)) {
                                $saleMasterProcessData['setter2_id'] = $saleMasterData['setter2_id'];
                            }
                            if (array_key_exists('job_status', $saleMasterData)) {
                                $saleMasterProcessData['job_status'] = $saleMasterData['job_status'];
                            }
                            $saleMasterProcess = SaleMasterProcess::updateOrCreate(['pid' => $saleMaster->pid], $saleMasterProcessData);

                            $nullTableVal = $saleMaster->toArray();
                            $nullTableVal['closer_id'] = $saleMasterProcess->closer1_id;
                            $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : null; //
                            $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : null; //
                            $nullTableVal['setter_id'] = $saleMasterProcess->setter1_id;
                            $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : null; //
                            $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : null; //
                            $nullTableVal['job_status'] = $saleMaster->job_status;
                            LegacyApiNullData::updateOrCreate(['pid' => $saleMaster->pid], $nullTableVal);

                            // Save custom field values to Crmsaleinfo (Custom Sales Fields feature)
                            // This enables commission/override calculations based on imported custom field values
                            if ($isCustomFieldsEnabled) {
                                CustomSalesFieldHelper::saveCustomFieldValuesForExistingSale(
                                    $saleMaster->pid,
                                    $checked->custom_field_values ?? [],
                                    $companyProfile->id ?? null,
                                    ['excel_import_id' => $excel->id ?? null, 'raw_row_id' => $checked->id ?? null]
                                );
                            }

                            // Check if milestone records are missing but dates exist in import data
                            // This handles the case where milestone_schema_id was NULL during previous import
                            if (!$calculate && count($milestoneDates) > 0) {
                                $hasMilestones = SaleProductMaster::where('pid', $salesMaster->pid)->exists();
                                if (!$hasMilestones) {
                                    Log::info('[MILESTONE_FIX] Forcing recalculation for existing sale with missing milestones', [
                                        'pid' => $salesMaster->pid,
                                        'milestone_dates_count' => count($milestoneDates)
                                    ]);
                                    $calculate = 1; // Force recalculation to create milestone records
                                }
                            }

                            LegacyApiRawDataHistory::where('id', $checked->id)->update(['import_to_sales' => 1]);

                            // PERFORMANCE FIX: Track counter locally instead of acquiring lock per row
                            $chunkCounters['updated_records']++;

                            DB::commit();

                            // Add to recalcTasks if changes detected (UPDATE path)
                            if ($calculate > 0) {
                                $requestArray = ['milestone_dates' => $milestoneDates];
                                if (array_key_exists('date_cancelled', $saleMasterData) && ! empty($salesMaster->date_cancelled) && empty($saleMasterData['date_cancelled'])) {
                                    salesDataChangesBasedOnClawback($salesMaster->pid);
                                    $requestArray['full_recalculate'] = 1;
                                }

                                $recalcTasks[] = [
                                    'pid' => $salesMaster->pid,
                                    'request' => $requestArray,
                                    'raw_id' => (int) $checked->id,
                                    'row_number' => (int) $rowNumber,
                                    'is_new_sale' => false,
                                    'data_source_type' => $checked->data_source_type ?? null,
                                ];
                            }
                        } else {
                            // Check if sale exists in database using pre-loaded array (O(1) lookup)
                            // OR if it was created in previous chunk (handles duplicate PIDs in same import)
                            $existsInDb = isset($existingPidsInDb[$saleMasterData['pid']]);

                            $saleMaster = SalesMaster::updateOrCreate(['pid' => $saleMasterData['pid']], $saleMasterData);

                            // updateOrCreate will set wasRecentlyCreated when the PID didn't exist prior to this call.
                            $isNewSale = (bool) $saleMaster->wasRecentlyCreated;

                            // If record was just created, add to tracking array for subsequent chunks
                            if ($saleMaster->wasRecentlyCreated) {
                                $existingPidsInDb[$saleMasterData['pid']] = true;
                            }

                            $saleMasterProcessData = ['sale_master_id' => $saleMaster->id];
                            if (array_key_exists('closer1_id', $saleMasterData)) {
                                $saleMasterProcessData['closer1_id'] = $saleMasterData['closer1_id'];
                            }
                            if (array_key_exists('closer2_id', $saleMasterData)) {
                                $saleMasterProcessData['closer2_id'] = $saleMasterData['closer2_id'];
                            }
                            if (array_key_exists('setter1_id', $saleMasterData)) {
                                $saleMasterProcessData['setter1_id'] = $saleMasterData['setter1_id'];
                            }
                            if (array_key_exists('setter2_id', $saleMasterData)) {
                                $saleMasterProcessData['setter2_id'] = $saleMasterData['setter2_id'];
                            }
                            if (array_key_exists('job_status', $saleMasterData)) {
                                $saleMasterProcessData['job_status'] = $saleMasterData['job_status'];
                            }
                            $saleMasterProcess = SaleMasterProcess::updateOrCreate(['pid' => $saleMaster->pid], $saleMasterProcessData);

                            $nullTableVal = $saleMaster->toArray();
                            $nullTableVal['closer_id'] = $saleMasterProcess->closer1_id;
                            $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : null; //
                            $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : null; //
                            $nullTableVal['setter_id'] = $saleMasterProcess->setter1_id;
                            $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : null; //
                            $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : null; //
                            $nullTableVal['job_status'] = $saleMaster->job_status;
                            LegacyApiNullData::updateOrCreate(['pid' => $saleMaster->pid], $nullTableVal);

                            // Save custom field values to Crmsaleinfo (Custom Sales Fields feature)
                            // This enables commission/override calculations based on imported custom field values
                            if ($isCustomFieldsEnabled) {
                                CustomSalesFieldHelper::saveCustomFieldValuesForNewSale(
                                    $saleMaster->pid,
                                    $checked->custom_field_values ?? [],
                                    $companyProfile->id ?? null,
                                    ['excel_import_id' => $excel->id ?? null, 'raw_row_id' => $checked->id ?? null]
                                );
                            }

                            // Decide whether this row actually needs a recalculation.
                            // NOTE: updateOrCreate() will often touch updated_at, so we ignore timestamp-only changes.
                            $forceRecalcMissingMilestones = false;
                            if (! $isNewSale && count($milestoneDates) > 0) {
                                $hasMilestones = SaleProductMaster::where('pid', $saleMaster->pid)->exists();
                                if (! $hasMilestones) {
                                    Log::info('[MILESTONE_FIX] Forcing recalculation for existing sale with missing milestones', [
                                        'pid' => $saleMaster->pid,
                                        'milestone_dates_count' => count($milestoneDates),
                                    ]);
                                    $forceRecalcMissingMilestones = true;
                                }
                            }

                            $saleMeaningfulChanges = $this->modelHasMeaningfulChanges($saleMaster);
                            $processMeaningfulChanges = $this->modelHasMeaningfulChanges($saleMasterProcess);
                            $shouldRecalc = (bool) ($isNewSale || $saleMeaningfulChanges || $processMeaningfulChanges || $forceRecalcMissingMilestones);

                            // Commit the main transaction before calling subroutineProcess
                            // This prevents nested transaction/savepoint issues
                            LegacyApiRawDataHistory::where('id', $checked->id)->update(['import_to_sales' => 1]);

                            // PERFORMANCE FIX: Track counter locally instead of acquiring lock per row
                            if ($existsInDb) {
                                $chunkCounters['updated_records']++;
                            } else {
                                $chunkCounters['new_records']++;
                            }

                            DB::commit();

                            if ($shouldRecalc) {
                                // Defer recalculation until AFTER sales processing completes (sequential UX).
                                $recalcTasks[] = [
                                    'pid' => $saleMaster->pid,
                                    'request' => ['milestone_dates' => $milestoneDates],
                                    'raw_id' => (int) $checked->id,
                                    'row_number' => (int) $rowNumber,
                                    'is_new_sale' => (bool) $isNewSale,
                                    'data_source_type' => $checked->data_source_type ?? null,
                                ];
                            }

                            // Emit Sales import progress more frequently using the same DB counters as Import History.
                            // Throttled to avoid spamming broadcasts.
                            $this->emitSalesImportProgressMaybe(
                                (int) $excelId,
                                (string) $importKey,
                                (string) $importStartedAt,
                                (int) $initiatorUserId,
                            );
                        }

                        $successPID[] = $checked->pid;
                    } catch (\Throwable $e) {
                        // Only rollback if there's an active transaction
                        // (transaction may have been committed before subroutineProcess)
                        if (DB::transactionLevel() > 0) {
                            DB::rollBack();
                        }
                        $this->saveErrorReport($checked->id, $excelId, "Import Process Error", ['pid' => $checked->pid, 'errors' => [$e->getMessage() . ' on line ' . $e->getLine()]], $rowNumber, false);
                        $chunkCounters['error_records']++;
                        continue;
                    }
                }

                // PERFORMANCE FIX: Batch update counters ONCE per chunk (not per row)
                // This reduces Redis lock operations from 1513 to ~15 per import
                // Expected improvement: 60-90 min → 10-12 min (80-85% faster)
                if (array_sum($chunkCounters) > 0) {
                    $this->counterService->updateCounters($excelId, $chunkCounters, [
                        'context' => 'chunk_batch_update',
                        'chunk_size' => count($records),
                        'chunk_number' => $chunkNumber,
                    ]);

                    // Emit progress AFTER counters are written to database
                    // This ensures progress bar shows current (not stale) values
                    $this->emitSalesImportProgressMaybe(
                        (int) $excelId,
                        (string) $importKey,
                        (string) $importStartedAt,
                        (int) $initiatorUserId,
                    );
                }
            });

            // Verify all records were processed - prevent silent failures
            $remainingRecords = LegacyApiRawDataHistory::where([
                'data_source_type' => 'excel',
                'import_to_sales' => '0',
                'excel_import_id' => $excelId
            ])->count();

            if ($remainingRecords > 0) {
                Log::error('Excel import incomplete - records remaining unprocessed', [
                    'excel_import_id' => $excelId,
                    'remaining_records' => $remainingRecords,
                    'processed_in_chunks' => $processedInChunks,
                    'expected_total' => $totalPendingRecords,
                    'completion_percentage' => round((($totalPendingRecords - $remainingRecords) / $totalPendingRecords) * 100, 2),
                ]);

                // Mark as FAILED instead of completed
                $errorData = [
                    'pid' => '',
                    'errors' => [
                        "Import incomplete: {$remainingRecords} out of {$totalPendingRecords} records were not processed.",
                        "This may indicate a system issue. Please contact support or retry the import.",
                    ],
                ];

                $escapedJson = addslashes(json_encode($errorData));
                ExcelImportHistory::where('id', $excelId)->update([
                    'status' => 2, // Failed status
                    'current_phase' => null,
                    'errors' => DB::raw("JSON_ARRAY_APPEND(IFNULL(errors, JSON_ARRAY()), '$', '" . $escapedJson . "')"),
                ]);

                // Fail fast: do not start Sales processing / recalculation if import didn't finish.
                app(JobNotificationService::class)->notify(
                    $initiatorUserId > 0 ? $initiatorUserId : null,
                    'sales_excel_import',
                    'Sales import',
                    'failed',
                    100,
                    'Sales import failed: import did not process all rows.',
                    $importKey,
                    $importStartedAt,
                    now()->toIso8601String(),
                    [
                        'excel_id' => (int) $excelId,
                        'remaining_records' => (int) $remainingRecords,
                        'total_records' => (int) $totalPendingRecords,
                    ]
                );

                return false;
            } else {
                Log::info('Excel import chunk processing completed successfully', [
                    'excel_import_id' => $excelId,
                    'total_processed' => $processedInChunks,
                    'total_expected' => $totalPendingRecords,
                    'verification' => 'All records processed',
                ]);

                // 🔧 FIX: Calculate actual error/success counts from processed records
                $actualErrorCount = LegacyApiRawDataHistory::where(['data_source_type' => 'excel', 'excel_import_id' => $excelId])->where('import_to_sales', '2')->count();
                $actualSuccessCount = LegacyApiRawDataHistory::where(['data_source_type' => 'excel', 'excel_import_id' => $excelId])->where('import_to_sales', '1')->count();

                // Phase 1 done: switch to Phase 2 (sale processing). Do NOT set status=0 yet; "Completed" only after Phase 2.
                ExcelImportHistory::where('id', $excelId)->update([
                    'error_records' => $actualErrorCount,
                    'current_phase' => ExcelImportHistory::PHASE_SALE_PROCESSING,
                    'phase_progress' => 0,
                ]);

                Log::info('Excel import counts updated', [
                    'excel_import_id' => $excelId,
                    'actual_errors' => $actualErrorCount,
                    'actual_successes' => $actualSuccessCount,
                ]);
            }

            // Phase 1 completion: force Sales import to 100% BEFORE starting Sales processing.
            app(JobNotificationService::class)->notify(
                $initiatorUserId > 0 ? $initiatorUserId : null,
                'sales_excel_import',
                'Sales import',
                'completed',
                100,
                'Sales import completed.',
                $importKey,
                $importStartedAt,
                now()->toIso8601String(),
                [
                    'excel_id' => (int) $excelId,
                ]
            );

            // Phase 2: Sales processing (sequential). Use the raw rows we just imported successfully.
            $salesProcessInitiatedAt = now()->toIso8601String();
            app(JobNotificationService::class)->notify(
                $initiatorUserId > 0 ? $initiatorUserId : null,
                'sales_process',
                'Sales processing',
                'started',
                0,
                'Sales processing started.',
                $salesProcessKey,
                $salesProcessInitiatedAt,
                null,
                [
                    'excel_id' => (int) $excelId,
                    'data_source_type' => 'excel',
                ]
            );

            $rawIdsToProcess = LegacyApiRawDataHistory::where([
                'data_source_type' => 'excel',
                'excel_import_id' => $excelId,
                'import_to_sales' => '1',
            ])->orderBy('id', 'ASC')->pluck('id')->map(fn($id) => (int) $id)->toArray();

            if (! empty($rawIdsToProcess)) {
                $salesProcessController = new SalesProcessController();
                $processResult = $salesProcessController->integrationSaleProcess(
                    $rawIdsToProcess,
                    $salesProcessKey,
                    $salesProcessInitiatedAt,
                    $initiatorUserId > 0 ? $initiatorUserId : null,
                    null
                );
                if ($processResult === false) {
                    throw new \RuntimeException('Sales processing failed.');
                }
            }

            app(JobNotificationService::class)->notify(
                $initiatorUserId > 0 ? $initiatorUserId : null,
                'sales_process',
                'Sales processing',
                'completed',
                100,
                'Sales processing completed.',
                $salesProcessKey,
                $salesProcessInitiatedAt,
                now()->toIso8601String(),
                [
                    'excel_id' => (int) $excelId,
                    'record_count' => count($rawIdsToProcess ?? []),
                    'data_source_type' => 'excel',
                ]
            );

            // Phase 3: Sales calculation/recalculation - creates milestone records via subroutineProcess
            // This is CRITICAL: without this, SaleProductMaster records are never created and commissions fail
            foreach ($recalcTasks as $task) {
                try {
                    // Clear previous iteration's request data to prevent contamination
                    // subroutineProcess reads: milestone_dates, full_recalculate
                    request()->offsetUnset('milestone_dates');
                    request()->offsetUnset('full_recalculate');

                    $requestArray = is_array($task['request'] ?? null) ? $task['request'] : [];
                    if ($requestArray !== []) {
                        request()->merge($requestArray);
                    }
                    $salesController->subroutineProcess((string) ($task['pid'] ?? ''));
                } catch (\Throwable $e) {
                    $this->saveErrorReport(
                        (int) ($task['raw_id'] ?? 0),
                        $excelId,
                        'Subroutine Process Error',
                        [
                            'pid' => (string) ($task['pid'] ?? ''),
                            'errors' => [$e->getMessage() . ' on line ' . $e->getLine()],
                        ],
                        (int) ($task['row_number'] ?? 0)
                    );
                    continue;
                }
            }

            // All phases done: mark completed so Import History shows "Completed" in same row
            ExcelImportHistory::where('id', $excelId)->update([
                'status' => 0,
                'current_phase' => null,
                'phase_progress' => 100,
            ]);

            $salesErrorReport = [];
            $salesSuccessReport = [];
            $salesErrors = LegacyApiRawDataHistory::select('pid', 'import_status_description')->where(['data_source_type' => 'excel', 'excel_import_id' => $excelId])->where('import_to_sales', '2')->get();
            $salesSuccesses = LegacyApiRawDataHistory::select('pid')->where(['data_source_type' => 'excel', 'excel_import_id' => $excelId])->where('import_to_sales', '1')->get();
            foreach ($salesErrors as $salesError) {
                $salesErrorReport[] = [
                    'is_error' => true,
                    'pid' => $salesError->pid,
                    'message' => $salesError->import_status_description,
                ];
            }

            foreach ($salesSuccesses as $salesSuccess) {
                $salesSuccessReport[] = [
                    'is_error' => false,
                    'pid' => $salesSuccess->pid,
                    'message' => 'Success',
                ];
            }

            // Send emails after all phases complete.
            if (count($salesErrorReport) != 0) {
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Failed',
                    'template' => view('mail.saleImportFailed', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user]),
                ];
                $this->sendEmailNotification($data);

                $data = [
                    'email' => 'jay@sequifi.com',
                    'subject' => 'Sale Import Failed',
                    'template' => view('mail.saleImportFailed', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user]),
                ];
                $this->sendEmailNotification($data, true);
            } else {
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Success',
                    'template' => view('mail.saleImportSuccess', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user]),
                ];
                $this->sendEmailNotification($data);

                $data = [
                    'email' => 'jay@sequifi.com',
                    'subject' => 'Sale Import Success',
                    'template' => view('mail.saleImportSuccess', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user]),
                ];
                $this->sendEmailNotification($data, true);
            }
        } catch (\Throwable $e) {
            dispatch(new GenerateAlertJob(implode(',', $successPID)));

            // Build error message for records
            $errorMessage = 'Import process failed: ' . $e->getMessage() . ' on line ' . $e->getLine();
            $errorReason = 'Import Process Error';

            // Mark failed records in raw data history with error details
            // This ensures they appear in the "Skipped from file" modal
            LegacyApiRawDataHistory::where(['data_source_type' => 'excel', 'import_to_sales' => '0', 'excel_import_id' => $excelId])
                ->whereNotIn('pid', $successPID)
                ->update([
                    'import_to_sales' => '2',
                    'import_status_reason' => $errorReason,
                    'import_status_description' => json_encode([$errorMessage]),
                ]);

            // Get all failed PIDs for tracking
            $failedPids = LegacyApiRawDataHistory::where(['data_source_type' => 'excel', 'import_to_sales' => '2', 'excel_import_id' => $excelId])
                ->pluck('pid')
                ->toArray();

            // Use counter service for atomic bulk error update with Redis lock
            $this->counterService->bulkIncrementError($excelId, $failedPids, [
                'reason' => 'catastrophic_failure',
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'context' => 'salesExcelCompleteProcess_catch_block',
            ]);

            // Update status and append error details
            $errorData = ['pid' => '', 'errors' => ['Import process failed: ' . $e->getMessage() . ' on line ' . $e->getLine()]];
            $escapedJson = addslashes(json_encode($errorData));
            ExcelImportHistory::where('id', $excelId)->update([
                'status' => 2,
                'current_phase' => null,
                'errors' => DB::raw("JSON_ARRAY_APPEND(IFNULL(errors, JSON_ARRAY()), '$', '" . $escapedJson . "')"),
            ]);

            $errors[] = [
                'pid' => '',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            $data = [
                'email' => 'jay@sequifi.com',
                'subject' => 'Excel Import Failed In Normal Case!!',
                'template' => view('mail.excel-import-failed', ['errors' => $errors, 'user' => '']),
            ];
            $this->sendEmailNotification($data, true);

            // Best-effort failure emits for inline phases
            $initiatorUserId = (int) ($user->id ?? 0);
            $excelIdSafe = (int) ($excelId ?? 0);
            $salesProcessKey = 'sales_process_excel_' . $excelIdSafe;
            $importKey = is_string($importNotificationKey) && $importNotificationKey !== ''
                ? $importNotificationKey
                : ('sales_excel_import_' . (int) $excelIdSafe);

            // Always emit safe, user-facing notification messages (no raw exception text).
            // Full details are available in logs/emails.
            $errorId = 'err_' . bin2hex(random_bytes(4));
            Log::error('[SalesExcelImport] Import pipeline failed', [
                'error_id' => $errorId,
                'excel_id' => $excelIdSafe,
                'initiator_user_id' => $initiatorUserId,
                'exception' => $e,
            ]);

            try {
                app(JobNotificationService::class)->notify(
                    $initiatorUserId > 0 ? $initiatorUserId : null,
                    'sales_excel_import',
                    'Sales import',
                    'failed',
                    100,
                    "Sales import failed. Please retry or contact support. Error ID: {$errorId}.",
                    $importKey,
                    now()->subSeconds(1)->toIso8601String(),
                    now()->toIso8601String(),
                    [
                        'excel_id' => $excelIdSafe,
                        'error_id' => $errorId,
                    ]
                );
            } catch (\Throwable $e) {
                Log::debug('[SalesExcelImport] Failed to emit import failure notification (best-effort)', [
                    'excel_id' => (int) ($excelIdSafe ?? 0),
                    'error_id' => $errorId ?? null,
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                app(JobNotificationService::class)->notify(
                    $initiatorUserId > 0 ? $initiatorUserId : null,
                    'sales_process',
                    'Sales processing',
                    'failed',
                    100,
                    "Sales processing failed. Please retry or contact support. Error ID: {$errorId}.",
                    $salesProcessKey,
                    now()->subSeconds(1)->toIso8601String(),
                    now()->toIso8601String(),
                    [
                        'excel_id' => $excelIdSafe,
                        'data_source_type' => 'excel',
                        'error_id' => $errorId,
                    ]
                );
            } catch (\Throwable $e) {
                Log::debug('[SalesExcelImport] Failed to emit sales processing failure notification (best-effort)', [
                    'excel_id' => (int) ($excelIdSafe ?? 0),
                    'error_id' => $errorId ?? null,
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getTemplateData($templateId, $companyProfile)
    {
        // IMPORTANT: orderBy('id') ensures consistent field order matching AbstractSalesImport
        $templateDetails = match (true) {
            $companyProfile->company_type === CompanyProfile::FIBER_COMPANY_TYPE => FiberSalesImportTemplateDetail::with('field')->where('template_id', $templateId)->orderBy('id')->get(),
            $companyProfile->company_type === CompanyProfile::SOLAR_COMPANY_TYPE => SolarSalesImportTemplateDetail::with('field')->where('template_id', $templateId)->orderBy('id')->get(),
            $companyProfile->company_type === CompanyProfile::TURF_COMPANY_TYPE => TurfSalesImportTemplateDetail::with('field')->where('template_id', $templateId)->orderBy('id')->get(),
            $companyProfile->company_type === CompanyProfile::ROOFING_COMPANY_TYPE => RoofingSalesImportTemplateDetail::with('field')->where('template_id', $templateId)->orderBy('id')->get(),
            $companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE => MortgageSalesImportTemplateDetail::with('field')->where('template_id', $templateId)->orderBy('id')->get(),
            in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE, true) => PestSalesImportTemplateDetail::with('field')->where('template_id', $templateId)->orderBy('id')->get(),
            default => null
        };

        if (! $templateDetails) {
            return [[], [], []];
        }

        $templateMappedFields = [];
        $templateCustomFields = [];
        $templateMappedCustomFields = [];
        foreach ($templateDetails as $templateDetail) {
            // Skip if field relationship is null (orphaned template detail)
            if (! $templateDetail->field) {
                continue;
            }

            // Only include date-type custom fields to align with $triggerDates index
            // Non-date custom fields were causing index mismatch with trigger_date array
            $isDateType = strtolower($templateDetail->field->field_type ?? '') === 'date';
            $isCustom = (bool) ($templateDetail->field->is_custom ?? false);

            if ($isCustom && $isDateType) {
                $templateCustomFields[] = $templateDetail->field->name;
                if ($templateDetail->excel_field) {
                    $templateMappedCustomFields[] = $templateDetail->field->name;
                }
            } elseif (! $isCustom) {
                if ($templateDetail->excel_field) {
                    $templateMappedFields[] = $templateDetail->field->name;
                }
            }
        }

        \Log::info('[MILESTONE_DEBUG] getTemplateData', [
            'template_id' => $templateId,
            'company_type' => $companyProfile->company_type ?? 'N/A',
            'templateMappedFields' => $templateMappedFields,
            'templateCustomFields' => $templateCustomFields,
            'templateMappedCustomFields' => $templateMappedCustomFields,
            'templateDetails_count' => $templateDetails ? count($templateDetails) : 0,
        ]);

        return [$templateMappedFields, $templateCustomFields, $templateMappedCustomFields];
    }

    private function validateDateState($saleDate)
    {
        if (! $saleDate || $saleDate == '0000-00-00') {
            return [
                'success' => false,
                'message' => 'Apologies, the sale date cannot be empty.',
            ];
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    private function validateMilestoneDates($milestoneDates, $customerSignoff)
    {
        if (is_array($milestoneDates) && count($milestoneDates) != 0) {
            foreach ($milestoneDates as $milestoneDate) {
                if (@$milestoneDate['date'] && $customerSignoff && $milestoneDate['date'] < $customerSignoff) {
                    return [
                        'success' => false,
                        'message' => 'Apologies, the milestone date cannot be earlier than the sale date.',
                    ];
                }
            }
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    private function buildCreateSaleMasterData($checked, $allProducts, $defaultProduct, $saleProductRecords, $templateMappedFields, $templateCustomFields, $templateMappedCustomFields, $companyProfile)
    {
        $saleMasterData = [];
        $ignoreMapping = ['product_id', 'closer1_flexi_id', 'closer1_id', 'closer2_flexi_id', 'closer2_id', 'setter1_flexi_id', 'setter1_id', 'setter2_flexi_id', 'setter2_id'];

        // Get the actual fields that were present in the Excel file
        $mappedFieldsFromExcel = $checked->mapped_fields ?? [];

        // Date fields that should be removed if mapped and blank
        $dateFields = [
            'date_cancelled',
            'scheduled_install',
            'install_complete_date',
            'return_sales_date',
            'm1_date',
            'm2_date',
            'initial_service_date',
            'last_service_date',
        ];

        foreach ($templateMappedFields as $templateMappedField) {
            if (in_array($templateMappedField, $ignoreMapping)) {
                continue;
            }

            // Check if this field was actually present in the Excel import
            $wasInExcel = in_array($templateMappedField, $mappedFieldsFromExcel);
            $fieldValue = $checked->$templateMappedField;
            $isDateField = in_array($templateMappedField, $dateFields);

            // For date fields: include if mapped (even if null, to allow clearing)
            // For other fields: only include if has value
            if ($isDateField && $wasInExcel) {
                if ($templateMappedField == 'gross_account_value' && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $saleMasterData['kw'] = $fieldValue;
                }
                $saleMasterData[$templateMappedField] = $fieldValue;
            } elseif (!$isDateField && ($fieldValue !== null && $fieldValue !== '')) {
                if ($templateMappedField == 'gross_account_value' && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $saleMasterData['kw'] = $fieldValue;
                }
                $saleMasterData[$templateMappedField] = $fieldValue;
            }
        }

        if (in_array('product_id', $templateMappedFields)) {
            $product = isset($allProducts[$checked->product_id]) ? $allProducts[$checked->product_id] : null;
            if ($product) {
                $saleMasterData['product_id'] = $checked->product_id;
                $saleMasterData['product_code'] = $product;
            } else {
                $saleMasterData['product_id'] = $defaultProduct?->id;
                $saleMasterData['product_code'] = $defaultProduct?->product_id;
            }
        }

        // Only include closer/setter IDs if they are mapped AND have a value (not null)
        // This prevents overwriting existing values with NULL during updates
        if ((in_array('closer1_flexi_id', $templateMappedFields) || in_array('closer1_id', $templateMappedFields)) && $checked->closer1_id !== null) {
            $saleMasterData['closer1_id'] = $checked->closer1_id;
        }

        if ((in_array('closer2_flexi_id', $templateMappedFields) || in_array('closer2_id', $templateMappedFields)) && $checked->closer2_id !== null) {
            $saleMasterData['closer2_id'] = $checked->closer2_id;
        }

        if ((in_array('setter1_flexi_id', $templateMappedFields) || in_array('setter1_id', $templateMappedFields)) && $checked->setter1_id !== null) {
            $saleMasterData['setter1_id'] = $checked->setter1_id;
        }

        if ((in_array('setter2_flexi_id', $templateMappedFields) || in_array('setter2_id', $templateMappedFields)) && $checked->setter2_id !== null) {
            $saleMasterData['setter2_id'] = $checked->setter2_id;
        }

        $triggerDates = [];
        if ($checked->trigger_date) {
            $triggerDates = json_decode($checked->trigger_date, true);
        }

        \Log::info('[MILESTONE_DEBUG] buildCreateSaleMasterData - START', [
            'pid' => $checked->pid,
            'raw_trigger_date' => $checked->trigger_date,
            'parsed_trigger_dates' => $triggerDates,
            'templateCustomFields' => $templateCustomFields,
            'templateMappedCustomFields' => $templateMappedCustomFields,
            'templateMappedCustomFields_count' => count($templateMappedCustomFields),
        ]);

        if (count($templateMappedCustomFields) != 0) {
            // FIX: Simply pass trigger_dates directly - they now contain field_name for matching
            // This preserves the field_name which will be used to match with milestone triggers
            $saleMasterData['milestone_dates'] = $triggerDates;

            \Log::info('[MILESTONE_DEBUG] buildCreateSaleMasterData - USING TRIGGER_DATES', [
                'pid' => $checked->pid,
                'trigger_dates_with_field_names' => $triggerDates,
                'milestone_dates' => $saleMasterData['milestone_dates'] ?? [],
            ]);
        } else {
            $milestoneDate = [];
            $saleProducts = $saleProductRecords->where('pid', $checked->pid)->values();
            foreach ($saleProducts as $key => $saleProduct) {
                $milestoneDate[$key]['date'] = $saleProduct->milestone_date;
            }
            $saleMasterData['milestone_dates'] = $milestoneDate;

            \Log::info('[MILESTONE_DEBUG] buildCreateSaleMasterData - NO MAPPED CUSTOM FIELDS', [
                'pid' => $checked->pid,
                'using_existing_sale_products' => true,
                'milestone_dates' => $milestoneDate,
            ]);
        }

        // Set data_source_type from LegacyApiRawDataHistory
        // This works for all sources: excel, fieldroutes, denver, pocomos, etc.
        $saleMasterData['data_source_type'] = $checked->data_source_type;

        return $saleMasterData;
    }

    private function saveErrorReport($id, $excelId, $reason, $errorData, $rowNumber, $incrementCounter = true)
    {
        // Format error messages with row number
        $formattedErrors = [];
        foreach ($errorData['errors'] as $error) {
            $errorMessage = is_string($error) ? $error : json_encode($error);
            // Check if message already contains [Row: X]
            if (!preg_match('/\[Row:\s*\d+\]/', $errorMessage)) {
                $formattedErrors[] = "[Row: {$rowNumber}] {$errorMessage}";
            } else {
                $formattedErrors[] = $errorMessage;
            }
        }

        LegacyApiRawDataHistory::where('id', $id)->update([
            'import_to_sales' => 2,
            'import_status_reason' => $reason,
            'import_status_description' => json_encode($formattedErrors)
        ]);

        $escapedJson = addslashes(json_encode($errorData));
        ExcelImportHistory::where('id', $excelId)->update([
            'errors' => DB::raw("JSON_ARRAY_APPEND(IFNULL(errors, JSON_ARRAY()), '$', '" . $escapedJson . "')"),
        ]);

        // PERFORMANCE FIX: Only increment counter if requested (batch mode disables this)
        // When batching is enabled ($incrementCounter = false), counters are updated at chunk end
        if ($incrementCounter) {
            $this->counterService->incrementError($excelId, [
                'pid' => $errorData['pid'] ?? 'unknown',
                'reason' => $reason,
                'row_number' => $rowNumber,
                'context' => 'record_error',
            ]);

            // Emit progress ONLY if we actually updated the counter in database
            // If batching ($incrementCounter = false), progress is emitted after chunk completes
            try {
                $ctx = app(SalesExcelImportContext::class);
                if ($ctx->isSet()) {
                    $this->emitSalesImportProgressMaybe((int) $excelId, $ctx->importKey(), $ctx->importStartedAt(), $ctx->initiatorUserId());
                }
            } catch (\Throwable $e) {
                Log::debug('[SalesExcelImport] Failed to emit progress (best-effort)', [
                    'excel_id' => (int) $excelId,
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Emit Sales import progress using the *same* ExcelImportHistory counters as Import History.
     * Throttled by time + percent changes to avoid spamming broadcasts/Redis writes.
     */
    private function emitSalesImportProgressMaybe(int $excelId, string $importKey, string $importStartedAt, int $initiatorUserId, int $minIntervalMs = 250): void
    {
        $ctx = app(SalesExcelImportContext::class);
        $nowMs = (int) floor(microtime(true) * 1000);
        $lastMs = $ctx->lastProgressTsMs($excelId);
        if ($nowMs - $lastMs < $minIntervalMs) {
            return;
        }

        $progressRow = ExcelImportHistory::query()
            ->whereKey($excelId)
            ->first(['new_records', 'updated_records', 'error_records', 'total_records']);

        $total = (int) ($progressRow?->total_records ?? 0);
        $processed = (int) ($progressRow?->new_records ?? 0)
            + (int) ($progressRow?->updated_records ?? 0)
            + (int) ($progressRow?->error_records ?? 0);
        $pct = $total > 0 ? round(max(0, min(100, ($processed / $total) * 100)), 2) : 0.0;
        if ($pct >= 100) {
            $pct = 99.99;
        }

        $lastPct = $ctx->lastProgressPct($excelId);
        if ($lastPct !== null && (float) $lastPct === (float) $pct) {
            $ctx->setProgressThrottleState($excelId, $nowMs, $lastPct);
            return;
        }

        app(JobNotificationService::class)->notify(
            $initiatorUserId > 0 ? $initiatorUserId : null,
            'sales_excel_import',
            'Sales import',
            'processing',
            $pct,
            "Importing sales rows ({$processed} / {$total})...",
            $importKey,
            $importStartedAt,
            null,
            [
                'excel_id' => $excelId,
                // Keep chunk metadata for UI grouping, but progress is now real-time.
                'chunk_number' => (int) ceil(max(1, $processed) / 100),
                'total_chunks' => (int) ceil(max(1, $total) / 100),
                'chunk_size' => 100,
                'processed' => $processed,
                'total' => $total,
                'progress_percentage' => (float) $pct,
            ]
        );

        $ctx->setProgressThrottleState($excelId, $nowMs, (float) $pct);
    }

    /**
     * Eloquent models typically update updated_at during updateOrCreate() even if no business fields changed.
     * This helper returns true only when there are non-timestamp changes.
     */
    private function modelHasMeaningfulChanges(?Model $model, array $ignoredKeys = ['updated_at', 'created_at']): bool
    {
        if (! $model) {
            return false;
        }

        $changes = $model->getChanges();
        foreach ($ignoredKeys as $key) {
            unset($changes[$key]);
        }

        return count($changes) > 0;
    }
}
