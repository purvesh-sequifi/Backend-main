<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Models\User;
use App\Models\State;
use App\Models\Products;
use App\Models\Locations;
use App\Models\Integration;
use App\Models\SalesMaster;
use App\Models\UserOverrides;
use App\Jobs\GenerateAlertJob;
use App\Models\CompanySetting;
use App\Models\UserCommission;
use App\Models\CustomerPayment;
use App\Models\LegacyApiNullData;
use App\Models\SaleMasterProcess;
use App\Models\SaleProductMaster;
use App\Models\ExcelImportHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Traits\EmailNotificationTrait;
use App\Models\LegacyApiRawDataHistory;
use App\Jobs\RecalculateOpenTieredSalesJob;
use App\Services\JobNotificationService;
use App\Core\Traits\SaleTraits\EditSaleTrait;
use App\Core\Traits\SaleTraits\SubroutineTrait;
use App\Helpers\CustomSalesFieldHelper;
use App\Http\Controllers\API\V2\Sales\SalesController;
use App\Jobs\Sales\SaleProcessJob;

class SalesProcessController extends BaseController
{
    use EditSaleTrait, EmailNotificationTrait, SubroutineTrait;

    private function buildSaleMasterData(\App\Models\LegacyApiRawDataHistory $raw, string $type): array
    {
        $simpleFields = [
            // Customer
            'customer_name','customer_address','customer_address_2','customer_city',
            'customer_zip','customer_email','customer_phone','homeowner_id',

            // Sale / product
            'proposal_id','product','product_id','product_code','sale_product_name',

            // Money / metrics
            'epc','net_epc','gross_account_value','dealer_fee_percentage','dealer_fee_amount',
            'adders','adders_description','funding_source','financing_rate','financing_term',
            'cash_amount','loan_amount','redline','cancel_fee','length_of_agreement',
            'service_schedule','initial_service_cost','subscription_payment','card_on_file',
            'auto_pay','service_completed','bill_status','balance_age','kw',

            // Dates
            'customer_signoff','date_cancelled','scheduled_install','install_complete_date',
            'return_sales_date','m1_date','m2_date','initial_service_date','last_service_date',

            // Other
            'install_partner','install_partner_id','location_code','job_status',
            'sales_rep_name','employee_id','sales_rep_email','trigger_date',
        ];

        $data = $this->pickProvidedFields($simpleFields, $raw);

        $data['pid']              = $raw->pid;
        $data['data_source_type'] = $type;

        $stateFields = $this->resolveStateFields($raw);
        if (!empty($stateFields)) {
            $data = array_merge($data, $stateFields);
        }

        return $data;
    }

    private function pickProvidedFields(array $fields, \App\Models\LegacyApiRawDataHistory $raw): array
    {
        $out = [];
        $mappedFields = $raw->mapped_fields ?? [];

        // Date fields (excluding sale date) that should be removed if mapped and blank
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

        foreach ($fields as $field) {
            $isMapped = in_array($field, $mappedFields, true);
            $isProvided = $this->isProvided($raw->{$field} ?? null);
            $isDateField = in_array($field, $dateFields, true);

            // Logic for date fields (except sale date):
            // If mapped and blank, include as null to remove from DB
            if ($isDateField && $isMapped) {
                $out[$field] = $raw->{$field};
            }
            // Logic for non-date fields:
            // Only include if has value (even if mapped, blank should not overwrite)
            elseif ($isProvided) {
                $out[$field] = $raw->{$field};
            }
        }
        return $out;
    }

    private function isProvided($value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return true;
    }

    private function resolveStateFields(\App\Models\LegacyApiRawDataHistory $raw): array
    {
        $hasCustomerState  = $this->isProvided($raw->customer_state ?? null);
        $hasLocationCode   = $this->isProvided($raw->location_code ?? null);

        if (!$hasCustomerState && !$hasLocationCode) {
            return [];
        }

        if ($hasCustomerState) {
            $state = \App\Models\State::where('state_code', $raw->customer_state)->first();
            if ($state) {
                return [
                    'customer_state' => $state->state_code,
                    'state_id'       => $state->id,
                ];
            }

            return [];
        }

        if ($hasLocationCode) {
            $location = \App\Models\Locations::with('State')->where('general_code', $raw->location_code)->first();
            if ($location && $location->State) {
                return [
                    'customer_state' => $location->State->state_code,
                    'state_id'       => $location->State->id,
                ];
            }
        }

        return [];
    }

    /**
     * Format error message with row number [Row: X]
     *
     * @param string $message The error message
     * @param int $rowNumber The row number (1-based index)
     * @return string Formatted error message with [Row: X] prefix
     */
    private function formatErrorMessageWithRowNumber(string $message, int $rowNumber): string
    {
        // Check if message already contains [Row: X]
        if (preg_match('/\[Row:\s*\d+\]/', $message)) {
            return $message;
        }

        return "[Row: {$rowNumber}] {$message}";
    }

    // EXCEL SALE PROCESS
    public function excelInsertUpdateSaleMaster($user = NULL, $type = 'excel', $excel = NULL)
    {
        $successPID = [];
        $excelId = $excel->id;
        $excel = ExcelImportHistory::where('id', $excelId)->first();
        if (!$excel) {
            return false;
        }
        $salesController = new SalesController();
        try {
            $query = LegacyApiRawDataHistory::where(['data_source_type' => $type, 'import_to_sales' => '0', 'excel_import_id' => $excelId])
                ->orderBy('id', 'ASC');
            $totalRecords = $query->count();

            $processedCount = 0;
            $salesErrorReport = [];
            $salesSuccessReport = [];
            // For multiple records, use chunking to avoid memory issues
            $batchSize = 500; // Process records in batches of 500 for better performance

            $rowNumber = 0; // Row number counter (1-based)
            $query->chunkById($batchSize, function ($records) use (&$salesErrorReport, &$salesSuccessReport, &$processedCount, $type, &$successPID, $totalRecords, &$excel, $salesController, &$rowNumber) {
                foreach ($records as $checked) {
                    $rowNumber++; // Increment row number for each record
                    $milestoneDates = [];
                    $salesMaster = SalesMaster::with('salesMasterProcess')->where('pid', $checked->pid)->first();
                    if ($checked->trigger_date) {
                        $milestoneDates = json_decode($checked->trigger_date, true);
                    }

                    if (is_array($milestoneDates) && sizeOf($milestoneDates) != 0) {
                        $continue = 0;
                        foreach ($milestoneDates as $milestoneDate) {
                            if (@$milestoneDate['date'] && $checked->customer_signoff && $milestoneDate['date'] < $checked->customer_signoff) {
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the date cannot be earlier than the sale date.',
                                    'realMessage' => 'Apologies, the date cannot be earlier than the sale date.',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-'
                                ];

                                $excel->error_records = $excel->error_records + 1;
                                $excel->save();
                                $continue = 1;
                                $checked->import_to_sales = 2;
                                $checked->import_status_reason = 'Invalid Milestone Date';
                                $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The milestone date cannot be earlier than the sale date', $rowNumber);
                                $checked->save();
                                break;
                            }
                        }

                        if ($continue) {
                            continue;
                        }
                    }

                    $productId = $checked->product_id;
                    $systemProductId = $checked->product_id;
                    $product = Products::withTrashed()->where('id', $productId)->first();
                    if (!$product) {
                        $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                        $systemProductId = $product->id;
                    }
                    $finalDates = [];
                    $effectiveDate = $checked->customer_signoff;
                    $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
                    $triggers = (is_array($milestoneDates) && sizeOf($milestoneDates) != 0 && isset($milestone?->milestone?->milestone_trigger)) ? $milestone?->milestone?->milestone_trigger : [];
                    foreach ($triggers as $key => $schema) {
                        $date = isset($milestoneDates[$key]['date']) ? $milestoneDates[$key]['date'] : NULL;
                        $finalDates[] = [
                            'date' => $date
                        ];
                    }
                    $milestoneDates = $finalDates;

                    $saleMasterData = $this->buildSaleMasterData($checked, $type);

                    // Preserve existing product_code if not provided in import (template mapping issue)
                    if (empty($saleMasterData['product_code']) && !empty($salesMaster->product_code)) {
                        $saleMasterData['product_code'] = $salesMaster->product_code;
                        $checked->product_code = $salesMaster->product_code; // Update checked object for validation logic
                    }

                    $domainName = config('app.domain_name');
                    if ($domainName === 'phoenixlending' && array_key_exists('net_epc', $saleMasterData)) {
                        $saleMasterData['net_epc'] = (($saleMasterData['net_epc'] ?? 0) > 0) ? $saleMasterData['net_epc'] : 1;
                    }

                    $customerPaymentJson = NULL;
                    $closer = User::where('id', $checked->closer1_id)->first();
                    $setter = User::where('id', $checked->setter1_id)->first();
                    if (isset($checked->customer_payment_json)) {
                        $customerPaymentJson = json_encode(json_decode($checked->customer_payment_json, true));
                    }
                    CustomerPayment::updateOrCreate(['pid' => $checked->pid], ['customer_payment_json' => $customerPaymentJson]);
                    $isImportStatus = 1;
                    if (!$salesMaster) {
                        $nullTableVal = $saleMasterData;
                        $nullTableVal['setter_id'] = $checked->setter1_id;
                        $nullTableVal['closer_id'] = $checked->closer1_id;
                        $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                        $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                        $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                        $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                        $nullTableVal['job_status'] = $checked->job_status;
                        LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);
                        $saleMaster = SalesMaster::create($saleMasterData);
                        $saleMasterProcessData = [
                            'sale_master_id' => $saleMaster->id,
                            'weekly_sheet_id' => $saleMaster->weekly_sheet_id,
                            'pid' => $checked->pid,
                            'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : NULL,
                            'closer2_id' => isset($checked->closer2_id) ? $checked->closer2_id : NULL,
                            'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : NULL,
                            'setter2_id' => isset($checked->setter2_id) ? $checked->setter2_id : NULL,
                            'job_status' => $checked->job_status
                        ];
                        SaleMasterProcess::create($saleMasterProcessData);

                        try {
                            request()->merge(['milestone_dates' => $milestoneDates]);
                            $salesController->subroutineProcess($saleMaster->pid);
                            $salesSuccessReport[] = [
                                'is_error' => false,
                                'pid' => $checked->pid,
                                'message' => 'Success',
                                'realMessage' => 'Success',
                                'file' => '',
                                'line' => '',
                                'name' => '-'
                            ];
                            $excel->new_records = $excel->new_records + 1;
                            $excel->save();
                        } catch (\Throwable $e) {
                            $isImportStatus = 2;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Error During Subroutine Process',
                                'realMessage' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'name' => '-'
                            ];
                            $excel->error_records = $excel->error_records + 1;
                            $excel->save();
                            $checked->import_status_reason = 'Subroutine Process Error';
                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber($e->getMessage(), $rowNumber);
                            $checked->save();
                        }
                    } else {
                        try {
                            $checkKw = ($checked->kw == $salesMaster->kw) ? 0 : 1;
                            $checkNetEpc = ($checked->net_epc == $salesMaster->net_epc) ? 0 : 1;
                            $checkDateCancelled = ($checked->date_cancelled == $salesMaster->date_cancelled) ? 0 : 1;
                            $checkCustomerState = ($checked->customer_state == $salesMaster->customer_state) ? 0 : 1;
                            $checkProduct = ($checked->product_code == $salesMaster->product_code) ? 0 : 1;

                            $salesMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                            salesDataChangesClawback($salesMasterProcess->pid);
                            $checkSetter = 0;
                            $checkSetter2 = 0;
                            $checkCloser = 0;
                            $checkCloser2 = 0;
                            if ($salesMasterProcess) {
                                $checkSetter = ($checked->setter1_id == $salesMasterProcess->setter1_id) ? 0 : 1;
                                $checkSetter2 = ($checked->setter2_id == $salesMasterProcess->setter2_id) ? 0 : 1;
                                $checkCloser = ($checked->closer1_id == $salesMasterProcess->closer1_id) ? 0 : 1;
                                $checkCloser2 = ($checked->closer2_id == $salesMasterProcess->closer2_id) ? 0 : 1;
                            }
                            $check = ($checkKw + $checkNetEpc + $checkDateCancelled + $checkCustomerState + $checkProduct + $checkSetter + $checkSetter2 + $checkCloser + $checkCloser2);

                            $success = true;
                            $pid = $checked->pid;
                            if ($success) {
                                if (!empty($salesMaster->product_code) && empty($checked->product_code)) {
                                    $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    if ($commission || $recon || $override || $reconOverride) {
                                        if ($commission) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Removal Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product cannot be removed because the Milestone amount has already been paid', $rowNumber);
                                            $checked->save();
                                        }
                                        if ($recon) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Removal Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product cannot be removed because the Milestone amount has already been paid', $rowNumber);
                                            $checked->save();
                                        }
                                        if ($override) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Removal Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product cannot be removed because some of the override amount has already been paid', $rowNumber);
                                            $checked->save();
                                        }
                                        if ($reconOverride) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Removal Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product cannot be removed because some of the override amount has already been paid', $rowNumber);
                                            $checked->save();
                                        }
                                    } else {
                                        $this->saleProductMappingChanges($pid);
                                    }
                                    $check += 1;
                                }
                                // Check for product code changes with NULL-safe comparison
                                $oldCode = $salesMaster->product_code ?? '';
                                $newCode = $checked->product_code ?? '';

                                if (!empty($oldCode) && !empty($newCode) && strcasecmp($oldCode, $newCode) !== 0) {
                                    $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                    $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                    if ($commission || $recon || $override || $reconOverride) {
                                        if ($commission) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                                'realMessage' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Code Change Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product code cannot be changed because commission payments have been finalized', $rowNumber);
                                            $checked->save();
                                        }
                                        if ($recon) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                                'realMessage' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Code Change Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product code cannot be changed because reconciliation has been executed', $rowNumber);
                                            $checked->save();
                                        }
                                        if ($override) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                                'realMessage' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Code Change Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product code cannot be changed because override payments have been finalized', $rowNumber);
                                            $checked->save();
                                        }
                                        if ($reconOverride) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                                'realMessage' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = 'Product Removal Error';
                                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The product cannot be removed because some of the override amount has already been paid', $rowNumber);
                                            $checked->save();
                                        }
                                    } else {
                                        $this->saleProductMappingChanges($pid);
                                    }
                                    $check += 1;
                                }
                            }

                            if ($success) {
                                if (isset($salesMaster->customer_signoff) && isset($checked->customer_signoff) && $checked->customer_signoff != $salesMaster->customer_signoff) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, sale date cannot be changed',
                                        'realMessage' => 'Apologies, sale date cannot be changed',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Sale Date Change Error';
                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('Sale date cannot be changed', $rowNumber);
                                    $checked->save();
                                }
                            }

                            if ($success) {
                                $isRemove = true;
                                $isChange = true;
                                $commissionIsRemove = true;
                                $commissionIsChange = true;
                                $overrides = false;
                                $isM2Paid = false;
                                $withHeldPaid = false;
                                $upFrontRemove = [];
                                $upFrontChange = [];
                                $commissionRemove = [];
                                $commissionChange = [];
                                $count = count($milestoneDates);
                                foreach ($finalDates as $key => $finalDate) {
                                    $sType = 'm' . ($key + 1);
                                    $date = @$finalDate['date'];
                                    $saleProduct = SaleProductMaster::where(['pid' => $pid, 'type' => $sType])->first();
                                    if ($saleProduct) {
                                        if ($count == ($key + 1)) {
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isM2Paid = true;
                                            }
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $withHeldPaid = true;
                                            }

                                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                                if ($isM2Paid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsRemove = false;
                                                    $checked->import_status_reason = 'Final Payment Date Removal Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The Final payment date cannot be removed because the Final amount has already been paid', $rowNumber);
                                                    $checked->save();
                                                } else if ($withHeldPaid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsRemove = false;
                                                    $checked->import_status_reason = 'Final Payment Date Removal Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation', $rowNumber);
                                                    $checked->save();
                                                } else {
                                                    $commissionRemove[] = $sType;
                                                }
                                            }

                                            if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                                if ($isM2Paid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsChange = false;
                                                    $checked->import_status_reason = 'Final Payment Date Change Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The Final payment date cannot be changed because the Final amount has already been paid', $rowNumber);
                                                    $checked->save();
                                                } else if ($withHeldPaid) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'realMessage' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $commissionIsChange = false;
                                                    $checked->import_status_reason = 'Final Payment Date Change Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation', $rowNumber);
                                                    $checked->save();
                                                } else {
                                                    $commissionChange[] = [
                                                        'type' => $sType,
                                                        'date' => $date
                                                    ];
                                                }
                                            }

                                            if ($saleProduct->milestone_date != $date) {
                                                $check += 1;
                                            }
                                        } else {
                                            if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isRemove = false;
                                                    $checked->import_status_reason = $sType . ' Date Removal Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid', $rowNumber);
                                                    $checked->save();
                                                } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isRemove = false;
                                                    $checked->import_status_reason = $sType . ' Date Removal Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid', $rowNumber);
                                                    $checked->save();
                                                } else {
                                                    $upFrontRemove[] = $sType;
                                                }
                                            }

                                            if (!empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                                if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isChange = false;
                                                    $checked->import_status_reason = $sType . ' Date Change Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be changed because the ' . $sType . ' amount has already been paid', $rowNumber);
                                                    $checked->save();
                                                } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                    $isImportStatus = 2;
                                                    $success = false;
                                                    $salesErrorReport[] = [
                                                        'is_error' => true,
                                                        'pid' => $checked->pid,
                                                        'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                        'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                        'file' => '',
                                                        'line' => '',
                                                        'name' => '-'
                                                    ];
                                                    $isChange = false;
                                                    $checked->import_status_reason = $sType . ' Date Change Error';
                                                    $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be changed because the ' . $sType . ' amount has finalized or executed from reconciliation', $rowNumber);
                                                    $checked->save();
                                                } else {
                                                    $upFrontChange[] = [
                                                        'type' => $sType,
                                                        'date' => $date
                                                    ];
                                                }
                                            }

                                            if ($saleProduct->milestone_date != $date) {
                                                $check += 1;
                                            }
                                        }
                                    }
                                    if (!$overrides && $saleProduct && $saleProduct->is_override) {
                                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                            if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $checked->import_status_reason = $sType . ' Date Removal Error';
                                                $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be removed because the override amount has already been paid', $rowNumber);
                                                $checked->save();
                                            } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $checked->import_status_reason = $sType . ' Date Removal Error';
                                                $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be removed because the override amount has already been paid', $rowNumber);
                                                $checked->save();
                                            }
                                        }

                                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                            if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $checked->import_status_reason = $sType . ' Date Removal Error';
                                                $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be removed because the override amount has already been paid', $rowNumber);
                                                $checked->save();
                                            } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $checked->import_status_reason = $sType . ' Date Removal Error';
                                                $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The ' . $sType . ' date cannot be removed because the override amount has already been paid', $rowNumber);
                                                $checked->save();
                                            }
                                        }
                                        $overrides = true;
                                    }
                                }

                                if ($isRemove) {
                                    foreach ($upFrontRemove as $remove) {
                                        $this->removeUpFrontSaleData($pid, $remove);
                                    }
                                }

                                if ($isChange) {
                                    foreach ($upFrontChange as $change) {
                                        $this->changeUpFrontPayrollData($pid, $change);
                                    }
                                }

                                if ($commissionIsRemove) {
                                    if (sizeOf($commissionRemove) != 0) {
                                        $this->removeCommissionSaleData($pid);
                                    }
                                }

                                if ($commissionIsChange) {
                                    foreach ($commissionChange as $change) {
                                        $this->changeCommissionPayrollData($pid, $change);
                                    }
                                }

                                if (sizeof($finalDates) == 0) {
                                    $this->removeUpFrontSaleData($pid);
                                    $this->removeCommissionSaleData($pid);
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->closer1_id) && isset($checked->closer1_id) && $checked->closer1_id != $salesMasterProcess->closer1_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Closer Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The closer cannot be changed because the commission amount has already been paid', $rowNumber);
                                        $checked->save();
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Closer Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The closer cannot be changed because the M2 amount has been finalized or executed from reconciliation', $rowNumber);
                                        $checked->save();
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->closer1_id, $salesMaster);
                                        $this->removeClawBackForNewUser($checked->closer1_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->closer2_id) && isset($checked->closer2_id) && $checked->closer2_id != $salesMasterProcess->closer2_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Closer Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The closer cannot be changed because the commission amount has already been paid', $rowNumber);
                                        $checked->save();
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Closer Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The closer cannot be changed because the M2 amount has been finalized or executed from reconciliation', $rowNumber);
                                        $checked->save();
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->closer2_id, $salesMaster);
                                        $this->removeClawBackForNewUser($checked->closer2_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->setter1_id) && isset($checked->setter1_id) && $checked->setter1_id != $salesMasterProcess->setter1_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Setter Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The setter cannot be changed because the commission amount has already been paid', $rowNumber);
                                        $checked->save();
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Setter Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The setter cannot be changed because the M2 amount has been finalized or executed from reconciliation', $rowNumber);
                                        $checked->save();
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->setter1_id, $salesMaster, 'setter');
                                        $this->removeClawBackForNewUser($checked->setter1_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->setter2_id) && isset($checked->setter2_id) && $checked->setter2_id != $salesMasterProcess->setter2_id) {
                                    if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Setter Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The setter cannot be changed because the commission amount has already been paid', $rowNumber);
                                        $checked->save();
                                    } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $pid,
                                            'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Setter Change Error';
                                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The setter cannot be changed because the M2 amount has been finalized or executed from reconciliation', $rowNumber);
                                        $checked->save();
                                    } else {
                                        $this->clawBackSalesData($salesMasterProcess->setter2_id, $salesMaster, 'setter');
                                        $this->removeClawBackForNewUser($checked->setter2_id, $salesMaster);
                                    }
                                }
                            }

                            if ($success) {
                                $data = [
                                    'weekly_sheet_id' => $salesMaster->weekly_sheet_id,
                                    'pid' => $checked->pid,
                                    'job_status' => $checked->job_status
                                ];

                                // Only include closer/setter IDs if they are provided (not null)
                                // This prevents overwriting existing values with NULL
                                if ($checked->closer1_id !== null) {
                                    $data['closer1_id'] = $checked->closer1_id;
                                }
                                if ($checked->closer2_id !== null) {
                                    $data['closer2_id'] = $checked->closer2_id;
                                }
                                if ($checked->setter1_id !== null) {
                                    $data['setter1_id'] = $checked->setter1_id;
                                }
                                if ($checked->setter2_id !== null) {
                                    $data['setter2_id'] = $checked->setter2_id;
                                }

                                SaleMasterProcess::updateOrCreate(['pid' => $checked->pid], $data);
                                if (!empty($salesMaster->date_cancelled)) {
                                    unset($saleMasterData['product_id']);
                                    unset($saleMasterData['product_code']);
                                }
                                SalesMaster::where('pid', $checked->pid)->update($saleMasterData);

                                $closer = User::where('id', $checked->closer1_id)->first();
                                $setter = User::where('id', $checked->setter1_id)->first();
                                $nullTableVal = $saleMasterData;
                                $nullTableVal['setter_id'] = $checked->setter1_id;
                                $nullTableVal['closer_id'] = $checked->closer1_id;
                                $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                                $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                                $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                                $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                                $nullTableVal['job_status'] = $checked->job_status;
                                LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);

                                if ($check > 0) {
                                    $requestArray = ['milestone_dates' => $milestoneDates];
                                    if (!empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                                        salesDataChangesBasedOnClawback($salesMaster->pid);
                                        $requestArray['full_recalculate'] = 1;
                                    }
                                    request()->merge($requestArray);
                                    $salesController->subroutineProcess($checked->pid);
                                    $salesSuccessReport[] = [
                                        'is_error' => false,
                                        'pid' => $checked->pid,
                                        'message' => 'Success',
                                        'realMessage' => 'Success',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                } else {
                                    $salesSuccessReport[] = [
                                        'is_error' => false,
                                        'pid' => $checked->pid,
                                        'message' => 'Success!!',
                                        'realMessage' => 'Success!!',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                }
                                $excel->updated_records = $excel->updated_records + 1;
                                $excel->save();
                            } else {
                                $excel->error_records = $excel->error_records + 1;
                                $excel->save();
                            }
                        } catch (\Throwable $e) {
                            $isImportStatus = 2;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Error During Subroutine Process',
                                'realMessage' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'name' => '-'
                            ];
                            $excel->error_records = $excel->error_records + 1;
                            $excel->save();
                            $checked->import_status_reason = 'Subroutine Process Error';
                            $checked->import_status_description = $this->formatErrorMessageWithRowNumber($e->getMessage(), $rowNumber);
                            $checked->save();
                        }
                    }

                    // UPDATE STATUS IN HISTORY TABLE FOR EXECUTED SALES.
                    $checked->import_to_sales = $isImportStatus;
                    $checked->save();

                    // Update progress after each record
                    $processedCount++;
                    $successPID[] = $checked->pid;
                }
            }); // Close the chunk method

            $excel->status = 0;
            $excel->updated_records = $excel->total_records - $excel->new_records - $excel->error_records;
            $excel->save();
            dispatch(new GenerateAlertJob(implode(',', $successPID)));
            if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                foreach ($successPID as $success) {
                    dispatch(new RecalculateOpenTieredSalesJob($success));
                }
            }

            // If Sales From Excel Sheet Has One Or More Error
            if (sizeof($salesErrorReport) != 0) {
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Failed',
                    'template' => view('mail.saleImportFailed', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user])
                ];
                $this->sendEmailNotification($data);
            } else {
                // If Sales From Excel Sheet Has No Error
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Import Success',
                    'template' => view('mail.saleImportSuccess', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user])
                ];
                $this->sendEmailNotification($data);
            }
        } catch (\Throwable $e) {
            dispatch(new GenerateAlertJob(implode(',', $successPID)));
            if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                foreach ($successPID as $success) {
                    dispatch(new RecalculateOpenTieredSalesJob($success));
                }
            }

            LegacyApiRawDataHistory::where(['data_source_type' => $type, 'import_to_sales' => '0', 'excel_import_id' => $excelId])->whereNotIn('pid', $successPID)->update(['import_to_sales' => '2']);
            $excel->status = 2;
            $excel->error_records = $excel->total_records - $excel->new_records - $excel->updated_records;
            $excel->save();

            // Return statistics for job monitoring
            return [
                'processed_count' => $processedCount,
                'created_count' => count($salesSuccessReport),
                'updated_count' => $processedCount - count($salesSuccessReport) - count($salesErrorReport),
                'error_count' => count($salesErrorReport),
                'success_pids' => $successPID
            ];
        }
    }


    // EXCEL SALE PROCESS
    public function integrationSaleProcess(
        $ids,
        ?string $notificationUniqueKey = null,
        ?string $notificationInitiatedAt = null,
        ?int $recipientUserId = null,
        ?array $recipientUserIds = null
    )
    {
        $successPID = [];
        $salesController = new SalesController();
        $domainName = config('app.domain_name');
        try {
            $query = LegacyApiRawDataHistory::whereIn('id', $ids);

            // Log the count for debugging
            $totalRecords = $query->count();
            Log::info("Processing {$totalRecords} records");

            if (!$totalRecords) {
                return false;
            }

            /**
             * Backward-compat / bug-guard:
             * Some legacy callers used the old param order and passed `recipientUserId` as the 2nd argument.
             * Since this method now expects `notificationUniqueKey` 2nd, PHP will coerce int->string (e.g. "1"),
             * causing a duplicate "Sales processing" notification with uniqueKey="1".
             *
             * If we detect that, treat the 2nd argument as recipientUserId and generate a stable uniqueKey.
             */
            if (is_string($notificationUniqueKey) && $notificationUniqueKey !== '' && ctype_digit($notificationUniqueKey)) {
                $maybeUserId = (int) $notificationUniqueKey;
                // Treat numeric uniqueKey as "old userId argument" and NEVER use it as a uniqueKey.
                // This prevents "stuck" duplicate cards like uniqueKey="1".
                if ($maybeUserId > 0 && $recipientUserId === null) {
                    $recipientUserId = $maybeUserId;
                }
                $notificationUniqueKey = null;
            }

            $processedCount = 0;
            $successPID = [];
            $records = $query->get();
            $rowNumber = 0; // Row number counter (1-based) for error messages

            $total = max(1, (int) $totalRecords);
            $lastProgressPercent = -1;
            $lastProgressChunk = 0;
            $excelImportId = 0;
            $dataSourceType = null;
            try {
                $excelImportId = (int) (LegacyApiRawDataHistory::query()
                    ->whereIn('id', $ids)
                    ->whereNotNull('excel_import_id')
                    ->value('excel_import_id') ?? 0);
            } catch (\Throwable $e) {
                Log::debug('SalesProcessController: excelImportId lookup failed (best-effort)', [
                    'ids_count' => is_array($ids) ? count($ids) : null,
                    'error' => $e->getMessage(),
                ]);
                $excelImportId = 0;
            }
            try {
                $dataSourceType = (string) (LegacyApiRawDataHistory::query()->whereIn('id', $ids)->value('data_source_type') ?? '');
                $dataSourceType = $dataSourceType !== '' ? $dataSourceType : null;
            } catch (\Throwable $e) {
                Log::debug('SalesProcessController: dataSourceType lookup failed (best-effort)', [
                    'ids_count' => is_array($ids) ? count($ids) : null,
                    'error' => $e->getMessage(),
                ]);
                $dataSourceType = null;
            }

            // Use a stable uniqueKey for CSV-import processing whenever excel_import_id is present.
            // This prevents duplicate cards and ensures controller-level completion updates the same card.
            $progressKey = $excelImportId > 0
                ? ('sales_process_excel_' . $excelImportId)
                : (
                    is_string($notificationUniqueKey) && $notificationUniqueKey !== ''
                        ? $notificationUniqueKey
                        : ('sale_process_' . time())
                );
            $initiatedAt = is_string($notificationInitiatedAt) && $notificationInitiatedAt !== ''
                ? $notificationInitiatedAt
                : now()->toIso8601String();

            // Check if Custom Sales Fields feature is enabled ONCE before loops (performance optimization)
            $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();

            foreach ($records as $checked) {
                $processedCount++;
                $rowNumber++; // Increment row number for each record

                // Emit progress: ONCE PER CHUNK (every 50 rows) to avoid noisy updates.
                // Completion (100) is emitted by the caller (SaleProcessJob or SalesExcelProcessController phase completion).
                $chunkSize = 50;
                $chunkNumber = (int) ceil($processedCount / $chunkSize);
                if ($processedCount === 1 || $processedCount === $total || ($processedCount % $chunkSize === 0 && $chunkNumber !== $lastProgressChunk)) {
                    $percent = (int) floor(($processedCount / $total) * 100);
                    $percent = max(1, min(99, $percent));
                    $expectedChunks = (int) ceil($total / $chunkSize);

                    // Unified AWS Lambda sales-processing progress:
                    // When SaleProcessJob is executed in AWS Lambda batch mode, all batch jobs share the same
                    // progress key (sale_process_batch_*). In that case, show a unified "X / Y sales (batch a/b)"
                    // message instead of per-batch "rows (300/500)".
                    $message = "Processing sales rows ({$processedCount} / {$total})...";
                    $meta = [
                        'record_count' => $total,
                        'chunk_number' => $chunkNumber,
                        'total_chunks' => $expectedChunks,
                        'chunk_size' => $chunkSize,
                        'processed' => $processedCount,
                        'total' => $total,
                        'data_source_type' => $dataSourceType,
                    ];

                    if (is_string($progressKey) && str_starts_with($progressKey, SaleProcessJob::BATCH_KEY_PREFIX)) {
                        try {
                            // New Redis namespace (preferred):
                            //   sale_process_batch:<suffix>
                            // Legacy namespace (kept for backward compatibility / in-flight runs):
                            //   sale_process_batch:<progressKey>
                            $suffix = substr($progressKey, strlen(SaleProcessJob::BATCH_KEY_PREFIX));
                            $newPrefix = 'sale_process_batch:' . $suffix;
                            $legacyPrefix = 'sale_process_batch:' . $progressKey;

                            $prefix = $newPrefix;
                            $totalRecordsAll = (int) (Redis::get($prefix . ':total_records') ?? 0);
                            $totalBatchesAll = (int) (Redis::get($prefix . ':total_chunks') ?? 0);
                            $batchSizeAll = (int) (Redis::get($prefix . ':batch_size') ?? 0);
                            $sourceAll = (string) (Redis::get($prefix . ':data_source_type') ?? '');
                            $completedBatches = (int) (Redis::get($prefix . ':completed_chunks') ?? 0);
                            $startedBatches = (int) (Redis::get($prefix . ':started_chunks') ?? 0);

                            if ($totalRecordsAll <= 0 || $totalBatchesAll <= 0 || $batchSizeAll <= 0) {
                                $prefix = $legacyPrefix;
                                $totalRecordsAll = (int) (Redis::get($prefix . ':total_records') ?? 0);
                                $totalBatchesAll = (int) (Redis::get($prefix . ':total_chunks') ?? 0);
                                $batchSizeAll = (int) (Redis::get($prefix . ':batch_size') ?? 0);
                                $sourceAll = (string) (Redis::get($prefix . ':data_source_type') ?? '');
                                $completedBatches = (int) (Redis::get($prefix . ':completed_chunks') ?? 0);
                                $startedBatches = (int) (Redis::get($prefix . ':started_chunks') ?? 0);
                            }

                            if ($totalRecordsAll > 0 && $totalBatchesAll > 0 && $batchSizeAll > 0) {
                                $overallProcessed = min($totalRecordsAll, ($completedBatches * $batchSizeAll) + $processedCount);
                                $percent = (int) floor(($overallProcessed / max(1, $totalRecordsAll)) * 99);
                                $percent = max(1, min(99, $percent));

                                $sourceAll = trim($sourceAll);
                                $sourceSuffix = $sourceAll !== '' ? " [source: {$sourceAll}]" : '';
                                // Under parallel processing, multiple batches can be in-flight at once.
                                // Display a range when possible to avoid implying there is only one "current" batch.
                                $inProgressStart = min($totalBatchesAll, max(1, $completedBatches + 1));
                                $inProgressEnd = $inProgressStart;
                                if ($startedBatches > $inProgressStart) {
                                    $inProgressEnd = min($totalBatchesAll, $startedBatches);
                                }
                                $batchDisplay = ($inProgressStart === $inProgressEnd)
                                    ? (string) $inProgressStart
                                    : ($inProgressStart . '-' . $inProgressEnd);

                                $message = sprintf(
                                    'Sales processing: %s / %s sales (batch %s/%d)%s',
                                    number_format($overallProcessed),
                                    number_format($totalRecordsAll),
                                    $batchDisplay,
                                    $totalBatchesAll,
                                    $sourceSuffix
                                );

                                $meta = [
                                    'record_count' => $totalRecordsAll,
                                    'processed' => $overallProcessed,
                                    'total' => $totalRecordsAll,
                                    'batch_number' => $inProgressStart,
                                    'batch_range' => $batchDisplay,
                                    'total_batches' => $totalBatchesAll,
                                    'batch_size' => $batchSizeAll,
                                    'completed_batches' => $completedBatches,
                                    'started_batches' => $startedBatches,
                                    'data_source_type' => $sourceAll !== '' ? $sourceAll : null,
                                ];
                            }
                        } catch (\Throwable $e) {
                            Log::debug('SalesProcessController: unified batch progress read failed (best-effort)', [
                                'progress_key' => $progressKey,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    app(JobNotificationService::class)->notify(
                        $recipientUserId,
                        'sales_process',
                        'SaleProcessJob',
                        'processing',
                        $percent,
                        $message,
                        $progressKey,
                        $initiatedAt,
                        null,
                        $meta,
                        $recipientUserIds
                    );
                    // Keep Import History phase_progress in sync so same row shows "Sale processing" 0-100%
                    if ($excelImportId > 0) {
                        try {
                            \App\Models\ExcelImportHistory::where('id', $excelImportId)->update([
                                'phase_progress' => (float) $percent,
                            ]);
                        } catch (\Throwable) {
                            // best-effort only
                        }
                    }
                    $lastProgressPercent = $percent;
                    $lastProgressChunk = $chunkNumber;
                }

                $milestoneDates = [];
                $salesMaster = SalesMaster::with('salesMasterProcess')->where('pid', $checked->pid)->first();
                if ($checked->trigger_date) {
                    $milestoneDates = json_decode($checked->trigger_date, true);
                }

                if (is_array($milestoneDates) && sizeOf($milestoneDates) != 0) {
                    $continue = 0;
                    foreach ($milestoneDates as $milestoneDate) {
                        if (@$milestoneDate['date'] && $checked->customer_signoff && $milestoneDate['date'] < $checked->customer_signoff) {
                            $continue = 1;
                            break;
                        }
                    }

                    if ($continue) {
                        $checked->import_to_sales = '2';
                        $checked->import_status_reason = 'Invalid Milestone Date';
                        $checked->import_status_description = $this->formatErrorMessageWithRowNumber('The milestone date cannot be earlier than the sale date', $rowNumber);
                        $checked->save();
                        continue;
                    }
                }

                $productId = $checked->product_id;
                $systemProductId = $checked->product_id;
                $product = Products::withTrashed()->where('id', $productId)->first();
                if (!$product) {
                    $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    $systemProductId = $product->id;
                }
                $finalDates = [];
                $effectiveDate = $checked->customer_signoff;
                $milestone = $this->milestoneWithSchema($systemProductId, $effectiveDate, false);
                $triggers = (is_array($milestoneDates) && sizeOf($milestoneDates) != 0 && isset($milestone?->milestone?->milestone_trigger)) ? $milestone?->milestone?->milestone_trigger : [];
                foreach ($triggers as $key => $schema) {
                    $date = isset($milestoneDates[$key]['date']) ? $milestoneDates[$key]['date'] : NULL;
                    $finalDates[] = [
                        'date' => $date
                    ];
                }
                $milestoneDates = $finalDates;

                $stateId = NULL;
                $stateCode = $checked->customer_state;
                if ($checked->customer_state) {
                    $state = State::where('state_code', $checked->customer_state)->first();
                    if ($state) {
                        $stateId = $state?->id ?? NULL;
                        $stateCode = $state?->state_code ?? NULL;
                    }
                } else if ($checked->location_code) {
                    $location = Locations::with('State')->where('general_code', $checked->location_code)->first();
                    if ($location && $location->State) {
                        $stateId = $location?->State?->id ?? NULL;
                        $stateCode = $location?->State?->state_code ?? NULL;
                    }
                }

                $domainName = config('app.domain_name');
                if($domainName=='phoenixlending'){
                    $net_epc = ($checked->net_epc ?? 0) > 0 ? $checked->net_epc : 1;
                } else {
                    $net_epc = $checked->net_epc;
                }
                \Log::info("info",['net_epc'=>$net_epc,'domainName'=>$domainName]);

                $saleMasterData = [
                    'pid' => $checked->pid,
                    'weekly_sheet_id' => NULL,
                    'install_partner' => $checked->install_partner,
                    'install_partner_id' => $checked->install_partner_id,
                    'customer_name' => $checked->customer_name,
                    'customer_address' => $checked->customer_address,
                    'customer_address_2' => $checked->customer_address_2,
                    'customer_city' => $checked->customer_city,
                    'customer_state' => $stateCode,
                    'state_id' => $stateId,
                    'location_code' => $checked->location_code,
                    'customer_zip' => $checked->customer_zip,
                    'customer_email' => $checked->customer_email,
                    'customer_phone' => $checked->customer_phone,
                    'homeowner_id' => $checked->homeowner_id,
                    'proposal_id' => $checked->proposal_id,
                    'sales_rep_name' => $checked->sales_rep_name,
                    'employee_id' => $checked->employee_id,
                    'sales_rep_email' => $checked->sales_rep_email,
                    'kw' => $checked->kw,
                    'date_cancelled' => $checked->date_cancelled,
                    'customer_signoff' => $checked->customer_signoff,
                    'product' => $checked->product,
                    'product_id' => $checked->product_id,
                    'product_code' => $checked->product_code,
                    'sale_product_name' => $checked->sale_product_name,
                    'epc' => $checked->epc,
                    'net_epc' => $checked->net_epc,
                    'gross_account_value' => $checked->gross_account_value,
                    'dealer_fee_percentage' => $checked->dealer_fee_percentage,
                    'dealer_fee_amount' => $checked->dealer_fee_amount,
                    'adders' => $checked->adders,
                    'adders_description' => $checked->adders_description,
                    'funding_source' => $checked->funding_source,
                    'financing_rate' => $checked->financing_rate,
                    'financing_term' => $checked->financing_term,
                    'scheduled_install' => $checked->scheduled_install,
                    'install_complete_date' => $checked->install_complete_date,
                    'return_sales_date' => $checked->return_sales_date,
                    'cash_amount' => $checked->cash_amount,
                    'loan_amount' => $checked->loan_amount,
                    'redline' => $checked->redline,
                    'cancel_fee' => $checked->cancel_fee,
                    'data_source_type' => $checked->data_source_type,
                    'job_status' => $checked->job_status,
                    'length_of_agreement' => $checked->length_of_agreement,
                    'service_schedule' => $checked->service_schedule,
                    'initial_service_cost' => $checked->initial_service_cost,
                    'subscription_payment' => $checked->subscription_payment,
                    'card_on_file' => $checked->card_on_file,
                    'auto_pay' => $checked->auto_pay,
                    'service_completed' => $checked->service_completed,
                    'last_service_date' => $checked->last_service_date,
                    'bill_status' => $checked->bill_status,
                    'm1_date' => $checked->m1_date,
                    'initial_service_date' => $checked->initial_service_date,
                    'initialStatusText' => $checked->initialStatusText,
                    'm2_date' => $checked->m2_date,
                    'trigger_date' => $checked->trigger_date,
                    'balance_age' => $checked->balance_age,
                ];

                // Preserve existing product_code if not provided in import (template mapping issue)
                if (empty($saleMasterData['product_code']) && !empty($salesMaster->product_code)) {
                    $saleMasterData['product_code'] = $salesMaster->product_code;
                    $checked->product_code = $salesMaster->product_code; // Update checked object for validation logic
                }

                $closer = User::where('id', $checked->closer1_id)->first();
                $setter = User::where('id', $checked->setter1_id)->first();
                CustomerPayment::updateOrCreate(['pid' => $checked->pid], ['pid' => $checked->pid, 'customer_payment_json' => json_encode(json_decode($checked->customer_payment_json, true))]);
                $isImportStatus = 1;
                if (!$salesMaster) {
                    $nullTableVal = $saleMasterData;
                    $nullTableVal['setter_id'] = $checked->setter1_id;
                    $nullTableVal['closer_id'] = $checked->closer1_id;
                    $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                    $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                    $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                    $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                    $nullTableVal['job_status'] = $checked->job_status;
                    LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);
                    $saleMaster = SalesMaster::create($saleMasterData);
                    $saleMasterProcessData = [
                        'sale_master_id' => $saleMaster->id,
                        'weekly_sheet_id' => $saleMaster->weekly_sheet_id,
                        'pid' => $checked->pid,
                        'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : NULL,
                        'closer2_id' => isset($checked->closer2_id) ? $checked->closer2_id : NULL,
                        'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : NULL,
                        'setter2_id' => isset($checked->setter2_id) ? $checked->setter2_id : NULL,
                        'job_status' => $checked->job_status
                    ];
                    SaleMasterProcess::create($saleMasterProcessData);

                    // Save custom field values to Crmsaleinfo (Custom Sales Fields feature)
                    // Only runs when feature flag is enabled - no impact on existing functionality
                    if ($isCustomFieldsEnabled) {
                        CustomSalesFieldHelper::saveCustomFieldValuesForNewSale(
                            $saleMaster->pid,
                            $checked->custom_field_values ?? [],
                            null, // companyId not available in this context
                            ['raw_row_id' => $checked->id ?? null]
                        );
                    }

                    try {
                        request()->merge(['milestone_dates' => $milestoneDates]);
                        $salesController->subroutineProcess($saleMaster->pid);
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $checked->import_status_reason = 'Integration Sale Process Error';
                        $checked->import_status_description = $e->getMessage();
                        $checked->save();
                    }
                } else {
                    try {
                        $checkKw = ($checked->kw == $salesMaster->kw) ? 0 : 1;
                        $checkNetEpc = ($checked->net_epc == $salesMaster->net_epc) ? 0 : 1;
                        $checkDateCancelled = ($checked->date_cancelled == $salesMaster->date_cancelled) ? 0 : 1;
                        $checkCustomerState = ($checked->customer_state == $salesMaster->customer_state) ? 0 : 1;
                        //$checkProduct = ($checked->product_code == $salesMaster->product_code) ? 0 : 1;
                        // NEW - Case-insensitive comparison
                        $checkProduct = (strcasecmp($checked->product_code ?? '', $salesMaster->product_code ?? '') === 0) ? 0 : 1;

                        $salesMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                        salesDataChangesClawback($salesMasterProcess->pid);
                        $checkSetter = 0;
                        $checkSetter2 = 0;
                        $checkCloser = 0;
                        $checkCloser2 = 0;
                        if ($salesMasterProcess) {
                            $checkSetter = ($checked->setter1_id == $salesMasterProcess->setter1_id) ? 0 : 1;
                            $checkSetter2 = ($checked->setter2_id == $salesMasterProcess->setter2_id) ? 0 : 1;
                            $checkCloser = ($checked->closer1_id == $salesMasterProcess->closer1_id) ? 0 : 1;
                            $checkCloser2 = ($checked->closer2_id == $salesMasterProcess->closer2_id) ? 0 : 1;
                        }
                        $check = ($checkKw + $checkNetEpc + $checkDateCancelled + $checkCustomerState + $checkProduct + $checkSetter + $checkSetter2 + $checkCloser + $checkCloser2);

                        $success = true;
                        $pid = $checked->pid;
                        if ($success) {
                            if ($domainName == 'whiteknight') {
                                $productCheck = !empty($salesMaster->product_id) && empty($checked->product_id);
                            } else {
                                $productCheck = !empty($salesMaster->product_code) && empty($checked->product_code);
                            }
                            if ($productCheck) {
                                $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                if ($commission || $recon || $override || $reconOverride) {
                                    if ($commission) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Removal Error';
                                        $checked->import_status_description = 'The product cannot be removed because the Milestone amount has already been paid';
                                        $checked->save();
                                    }
                                    if ($recon) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because the Milestone amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Removal Error';
                                        $checked->import_status_description = 'The product cannot be removed because the Milestone amount has already been paid';
                                        $checked->save();
                                    }
                                    if ($override) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Removal Error';
                                        $checked->import_status_description = 'The product cannot be removed because some of the override amount has already been paid';
                                        $checked->save();
                                    }
                                    if ($reconOverride) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'realMessage' => 'Apologies, the product cannot be removed because some of the override amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Removal Error';
                                        $checked->import_status_description = 'The product cannot be removed because some of the override amount has already been paid';
                                        $checked->save();
                                    }
                                } else {
                                    $this->saleProductMappingChanges($pid);
                                }
                                $check += 1;
                            }
                            // Check for product code/ID changes with NULL-safe comparison
                            if ($domainName == 'whiteknight') {
                                $productCheck2 = !empty($salesMaster->product_id) && !empty($checked->product_id) && $salesMaster->product_id != $checked->product_id;
                            } else {
                                $oldCode = $salesMaster->product_code ?? '';
                                $newCode = $checked->product_code ?? '';
                                $productCheck2 = !empty($oldCode) && !empty($newCode) && strcasecmp($oldCode, $newCode) !== 0;
                            }
                            if ($productCheck2) {
                                $commission = UserCommission::where(['pid' => $pid, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $recon = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                $override = UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
                                $reconOverride = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                                if ($commission || $recon || $override || $reconOverride) {
                                    if ($commission) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                            'realMessage' => 'Apologies, the product code cannot be changed because commission payments have been finalized',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Code Change Error';
                                        $checked->import_status_description = 'The product code cannot be changed because commission payments have been finalized';
                                        $checked->save();
                                    }
                                    if ($recon) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                            'realMessage' => 'Apologies, the product code cannot be changed because reconciliation has been executed',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Code Change Error';
                                        $checked->import_status_description = 'The product code cannot be changed because reconciliation has been executed';
                                        $checked->save();
                                    }
                                    if ($override) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                            'realMessage' => 'Apologies, the product code cannot be changed because override payments have been finalized',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Code Change Error';
                                        $checked->import_status_description = 'The product code cannot be changed because override payments have been finalized';
                                        $checked->save();
                                    }
                                    if ($reconOverride) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                            'realMessage' => 'Apologies, the product code cannot be changed because override reconciliation has been executed',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-'
                                        ];
                                        $checked->import_status_reason = 'Product Code Change Error';
                                        $checked->import_status_description = 'The product code cannot be changed because override reconciliation has been executed';
                                        $checked->save();
                                    }
                                } else {
                                    $this->saleProductMappingChanges($pid);
                                }
                                $check += 1;
                            }
                        }

                        if ($success) {
                            $isRemove = true;
                            $isChange = true;
                            $commissionIsRemove = true;
                            $commissionIsChange = true;
                            $overrides = false;
                            $isM2Paid = false;
                            $withHeldPaid = false;
                            $upFrontRemove = [];
                            $upFrontChange = [];
                            $commissionRemove = [];
                            $commissionChange = [];
                            $count = count($milestoneDates);
                            foreach ($finalDates as $key => $finalDate) {
                                $sType = 'm' . ($key + 1);
                                $date = @$finalDate['date'];
                                $saleProduct = SaleProductMaster::where(['pid' => $pid, 'type' => $sType])->first();
                                if ($saleProduct) {
                                    if ($count == ($key + 1)) {
                                        if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                            $isM2Paid = true;
                                        }
                                        if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                            $withHeldPaid = true;
                                        }

                                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                            if ($isM2Paid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be removed because the Final amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsRemove = false;
                                                $checked->import_status_reason = 'Final Payment Date Removal Error';
                                                $checked->import_status_description = 'The Final payment date cannot be removed because the Final amount has already been paid';
                                                $checked->save();
                                            } else if ($withHeldPaid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsRemove = false;
                                                $checked->import_status_reason = 'Final Payment Date Removal Error';
                                                $checked->import_status_description = 'The Final payment date cannot be removed because the reconciliation amount has finalized or executed from reconciliation';
                                                $checked->save();
                                            } else {
                                                $commissionRemove[] = $sType;
                                            }
                                        }

                                        if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                            if ($isM2Paid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be changed because the Final amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsChange = false;
                                                $checked->import_status_reason = 'Final Payment Date Change Error';
                                                $checked->import_status_description = 'The Final payment date cannot be changed because the Final amount has already been paid';
                                                $checked->save();
                                            } else if ($withHeldPaid) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'realMessage' => 'Apologies, the Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $commissionIsChange = false;
                                                $checked->import_status_reason = 'Final Payment Date Change Error';
                                                $checked->import_status_description = 'The Final payment date cannot be changed because the reconciliation amount has finalized or executed from reconciliation';
                                                $checked->save();
                                            } else {
                                                $commissionChange[] = [
                                                    'type' => $sType,
                                                    'date' => $date
                                                ];
                                            }
                                        }

                                        if ($saleProduct->milestone_date != $date) {
                                            $check += 1;
                                        }
                                    } else {
                                        if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isRemove = false;
                                                $checked->import_status_reason = $sType . ' Date Removal Error';
                                                $checked->import_status_description = 'The ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid';
                                                $checked->save();
                                            } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isRemove = false;
                                                $checked->import_status_reason = $sType . ' Date Removal Error';
                                                $checked->import_status_description = 'The ' . $sType . ' date cannot be removed because the ' . $sType . ' amount has already been paid';
                                                $checked->save();
                                            } else {
                                                $upFrontRemove[] = $sType;
                                            }
                                        }

                                        if (!empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                            if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has already been paid',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isChange = false;
                                                $checked->import_status_reason = $sType . ' Date Change Error';
                                                $checked->import_status_description = 'The ' . $sType . ' date cannot be changed because the ' . $sType . ' amount has already been paid';
                                                $checked->save();
                                            } else if (UserCommission::where(['pid' => $pid, 'schema_type' => $sType, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                                $isImportStatus = 2;
                                                $success = false;
                                                $salesErrorReport[] = [
                                                    'is_error' => true,
                                                    'pid' => $checked->pid,
                                                    'message' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                    'realMessage' => 'Apologies, the ' . $sType . ' date cannot be change because the ' . $sType . ' amount has finalized or executed from reconciliation',
                                                    'file' => '',
                                                    'line' => '',
                                                    'name' => '-'
                                                ];
                                                $isChange = false;
                                                $checked->import_status_reason = $sType . ' Date Change Error';
                                                $checked->import_status_description = 'The ' . $sType . ' date cannot be changed because the ' . $sType . ' amount has finalized or executed from reconciliation';
                                                $checked->save();
                                            } else {
                                                $upFrontChange[] = [
                                                    'type' => $sType,
                                                    'date' => $date
                                                ];
                                            }
                                        }

                                        if ($saleProduct->milestone_date != $date) {
                                            $check += 1;
                                        }
                                    }
                                }
                                if (!$overrides && $saleProduct && $saleProduct->is_override) {
                                    if ($saleProduct && !empty($saleProduct->milestone_date) && empty($date)) {
                                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = $sType . ' Date Removal Error';
                                            $checked->import_status_description = 'The ' . $sType . ' date cannot be removed because the override amount has already been paid';
                                            $checked->save();
                                        } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = $sType . ' Date Removal Error';
                                            $checked->import_status_description = 'The ' . $sType . ' date cannot be removed because the override amount has already been paid';
                                            $checked->save();
                                        }
                                    }

                                    if ($saleProduct && !empty($saleProduct->milestone_date) && !empty($date) && $saleProduct->milestone_date != $date) {
                                        if (UserOverrides::where(['pid' => $pid, 'status' => '3', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = $sType . ' Date Removal Error';
                                            $checked->import_status_description = 'The ' . $sType . ' date cannot be removed because the override amount has already been paid';
                                            $checked->save();
                                        } else if (UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                            $isImportStatus = 2;
                                            $success = false;
                                            $salesErrorReport[] = [
                                                'is_error' => true,
                                                'pid' => $checked->pid,
                                                'message' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'realMessage' => 'Apologies, the ' . $sType . ' date cannot be removed because the override amount has already been paid',
                                                'file' => '',
                                                'line' => '',
                                                'name' => '-'
                                            ];
                                            $checked->import_status_reason = $sType . ' Date Removal Error';
                                            $checked->import_status_description = 'The ' . $sType . ' date cannot be removed because the override amount has already been paid';
                                            $checked->save();
                                        }
                                    }
                                    $overrides = true;
                                }
                            }

                            if ($isRemove) {
                                foreach ($upFrontRemove as $remove) {
                                    $this->removeUpFrontSaleData($pid, $remove);
                                }
                            }

                            if ($isChange) {
                                foreach ($upFrontChange as $change) {
                                    $this->changeUpFrontPayrollData($pid, $change);
                                }
                            }

                            if ($commissionIsRemove) {
                                if (sizeOf($commissionRemove) != 0) {
                                    $this->removeCommissionSaleData($pid);
                                }
                            }

                            if ($commissionIsChange) {
                                foreach ($commissionChange as $change) {
                                    $this->changeCommissionPayrollData($pid, $change);
                                }
                            }

                            if (sizeof($finalDates) == 0) {
                                $this->removeUpFrontSaleData($pid);
                                $this->removeCommissionSaleData($pid);
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->closer1_id) && isset($checked->closer1_id) && $checked->closer1_id != $salesMasterProcess->closer1_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Closer Change Error';
                                    $checked->import_status_description = 'The closer cannot be changed because the commission amount has already been paid';
                                    $checked->save();
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Closer Change Error';
                                    $checked->import_status_description = 'The closer cannot be changed because the M2 amount has been finalized or executed from reconciliation';
                                    $checked->save();
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->closer1_id, $salesMaster);
                                    $this->removeClawBackForNewUser($checked->closer1_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->closer2_id) && isset($checked->closer2_id) && $checked->closer2_id != $salesMasterProcess->closer2_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Closer Change Error';
                                    $checked->import_status_description = 'The closer cannot be changed because the commission amount has already been paid';
                                    $checked->save();
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Closer Change Error';
                                    $checked->import_status_description = 'The closer cannot be changed because the M2 amount has been finalized or executed from reconciliation';
                                    $checked->save();
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->closer2_id, $salesMaster);
                                    $this->removeClawBackForNewUser($checked->closer2_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->setter1_id) && isset($checked->setter1_id) && $checked->setter1_id != $salesMasterProcess->setter1_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Setter Change Error';
                                    $checked->import_status_description = 'The setter cannot be changed because the commission amount has already been paid';
                                    $checked->save();
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Setter Change Error';
                                    $checked->import_status_description = 'The setter cannot be changed because the M2 amount has been finalized or executed from reconciliation';
                                    $checked->save();
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->setter1_id, $salesMaster, 'setter');
                                    $this->removeClawBackForNewUser($checked->setter1_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            if (isset($salesMasterProcess->setter2_id) && isset($checked->setter2_id) && $checked->setter2_id != $salesMasterProcess->setter2_id) {
                                if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Setter Change Error';
                                    $checked->import_status_description = 'The setter cannot be changed because the commission amount has already been paid';
                                    $checked->save();
                                } else if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $pid,
                                        'message' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the setter be change because the M2 amount has been finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-'
                                    ];
                                    $checked->import_status_reason = 'Setter Change Error';
                                    $checked->import_status_description = 'The setter cannot be changed because the M2 amount has been finalized or executed from reconciliation';
                                    $checked->save();
                                } else {
                                    $this->clawBackSalesData($salesMasterProcess->setter2_id, $salesMaster, 'setter');
                                    $this->removeClawBackForNewUser($checked->setter2_id, $salesMaster);
                                }
                            }
                        }

                        if ($success) {
                            $data = [
                                'weekly_sheet_id' => $salesMaster->weekly_sheet_id,
                                'pid' => $checked->pid,
                                'job_status' => $checked->job_status
                            ];

                            // Only include closer/setter IDs if they are provided (not null)
                            // This prevents overwriting existing values with NULL
                            if ($checked->closer1_id !== null) {
                                $data['closer1_id'] = $checked->closer1_id;
                            }
                            if ($checked->closer2_id !== null) {
                                $data['closer2_id'] = $checked->closer2_id;
                            }
                            if ($checked->setter1_id !== null) {
                                $data['setter1_id'] = $checked->setter1_id;
                            }
                            if ($checked->setter2_id !== null) {
                                $data['setter2_id'] = $checked->setter2_id;
                            }

                            SaleMasterProcess::updateOrCreate(['pid' => $checked->pid], $data);
                            if (!empty($salesMaster->date_cancelled)) {
                                unset($saleMasterData['product_id']);
                                unset($saleMasterData['product_code']);
                            }
                            SalesMaster::where('pid', $checked->pid)->update($saleMasterData);

                            $closer = User::where('id', $checked->closer1_id)->first();
                            $setter = User::where('id', $checked->setter1_id)->first();
                            $nullTableVal = $saleMasterData;
                            $nullTableVal['setter_id'] = $checked->setter1_id;
                            $nullTableVal['closer_id'] = $checked->closer1_id;
                            $nullTableVal['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name . ' ' . $closer->last_name : NULL;
                            $nullTableVal['sales_rep_email'] = isset($closer->email) ? $closer->email : NULL;
                            $nullTableVal['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name . ' ' . $setter->last_name : NULL;
                            $nullTableVal['sales_setter_email'] = isset($setter->email) ? $setter->email : NULL;
                            $nullTableVal['job_status'] = $checked->job_status;
                            LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $nullTableVal);

                            // Save custom field values to Crmsaleinfo (Custom Sales Fields feature)
                            // Only runs when feature flag is enabled - no impact on existing functionality
                            if ($isCustomFieldsEnabled) {
                                CustomSalesFieldHelper::saveCustomFieldValuesForExistingSale(
                                    $checked->pid,
                                    $checked->custom_field_values ?? [],
                                    null, // companyId not available in this context
                                    ['raw_row_id' => $checked->id ?? null]
                                );
                            }

                            if ($check > 0) {
                                $requestArray = ['milestone_dates' => $milestoneDates];
                                if (!empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                                    salesDataChangesBasedOnClawback($salesMaster->pid);
                                    $requestArray['full_recalculate'] = 1;
                                }
                                request()->merge($requestArray);
                                $salesController->subroutineProcess($checked->pid);
                            }
                        }
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $checked->import_status_reason = 'Integration Sale Process Error';
                        $checked->import_status_description = $e->getMessage();
                        $checked->save();
                    }
                }

                // UPDATE STATUS IN HISTORY TABLE FOR EXECUTED SALES.
                $checked->import_to_sales = $isImportStatus;
                $checked->save();
                $successPID[] = $checked->pid;
            }

            if (!in_array($domainName, ['evomarketing', 'whitenight', 'threeriverspest'])) {
                if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                    foreach ($successPID as $success) {
                        dispatch(new RecalculateOpenTieredSalesJob($success));
                    }
                }
            }
        } catch (\Throwable $e) {
            if (!in_array($domainName, ['evomarketing', 'whitenight', 'threeriverspest'])) {
                if (config('app.recalculate_tiered_sales') == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                    foreach ($successPID as $success) {
                        dispatch(new RecalculateOpenTieredSalesJob($success));
                    }
                }
            }
            // Update failed records with error message, using row number from existing description or position
            $failedRecords = LegacyApiRawDataHistory::whereIn('id', $ids)
                ->whereNotIn('pid', $successPID)
                ->orderBy('id', 'ASC')
                ->get();

            $rowNum = 0;
            foreach ($failedRecords as $failedRecord) {
                $rowNum++;
                // Try to extract row number from existing import_status_description
                $rowNumber = $rowNum;
                if ($failedRecord->import_status_description) {
                    if (preg_match('/\[Row:\s*(\d+)\]/', $failedRecord->import_status_description, $matches)) {
                        $rowNumber = (int) $matches[1];
                    }
                }

                $failedRecord->import_to_sales = '2';
                $failedRecord->import_status_reason = 'Integration Process Error';
                $failedRecord->import_status_description = $this->formatErrorMessageWithRowNumber($e->getMessage(), $rowNumber);
                $failedRecord->save();
            }

            Log::info(["Failed to process", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]]);
        }
    }
}
