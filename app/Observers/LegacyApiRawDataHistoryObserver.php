<?php

namespace App\Observers;

use App\Models\LegacyApiRawDataHistory;
use App\Models\SaleMasterExcluded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LegacyApiRawDataHistoryObserver
{
    /**
     * Handle the LegacyApiRawDataHistory "created" event.
     */
    public function created(LegacyApiRawDataHistory $record): void
    {
        // Handle different import_to_sales statuses
        if ($record->import_to_sales == 2) {
            // Failed import - move to filter table
            Log::info('Observer: Moving newly created failed record to filter table', [
                'record_id' => $record->id,
                'pid' => $record->pid,
                'reason' => $record->import_status_reason,
            ]);

            $this->moveToFilterTable($record);
            
            // Skip deletion for Excel import validation errors - they need to stay for error reporting
            if ($record->data_source_type === 'excel' && !empty($record->import_status_description)) {
                return; // Keep the record in the table for error reporting (but it was copied to filter table)
            }
            
            $record->delete(); // Remove from legacy table
        } elseif ($record->import_to_sales == 1) {
            // Successful import - log to histories_log and cleanup filter table
            Log::info('Observer: Logging newly created successful record to histories_log', [
                'record_id' => $record->id,
                'pid' => $record->pid,
            ]);

            $this->logToHistoriesLog($record);
            $this->cleanupFilterTable($record->pid);
        }
    }

    /**
     * Handle the LegacyApiRawDataHistory "updated" event.
     */
    public function updated(LegacyApiRawDataHistory $record): void
    {
        // Handle import_to_sales status changes
        if ($record->isDirty('import_to_sales')) {
            if ($record->import_to_sales == 2) {
                // Failed import - move to filter table
                Log::info('Observer: Moving updated failed record to filter table', [
                    'record_id' => $record->id,
                    'pid' => $record->pid,
                    'reason' => $record->import_status_reason,
                    'old_import_to_sales' => $record->getOriginal('import_to_sales'),
                ]);

                $this->moveToFilterTable($record);
                
                // Skip deletion for Excel import validation errors - they need to stay for error reporting
                if ($record->data_source_type === 'excel' && !empty($record->import_status_description)) {
                    return; // Keep the record in the table for error reporting (but it was copied to filter table)
                }
                
                $record->delete(); // Remove from legacy table
            } elseif ($record->import_to_sales == 1) {
                // Successful import - log to histories_log and cleanup filter table
                Log::info('Observer: Logging updated successful record to histories_log', [
                    'record_id' => $record->id,
                    'pid' => $record->pid,
                    'old_import_to_sales' => $record->getOriginal('import_to_sales'),
                ]);

                $this->logToHistoriesLog($record);
                $this->cleanupFilterTable($record->pid);
            }
        }
    }

    /**
     * Move record to sale_masters_excluded table with upsert logic
     * Updates if PID exists, inserts if not
     */
    private function moveToFilterTable(LegacyApiRawDataHistory $record): void
    {
        try {
            // Prepare the filter data
            $filterData = [
                'user_id' => null,
                'filter_id' => null,
                'sale_master_id' => null,
                'pid' => $record->pid,
                'ticket_id' => null,
                'initialStatusText' => $record->initialStatusText,
                'appointment_id' => $record->initialAppointmentID,
                'closer1_id' => $record->closer1_id,
                'setter1_id' => $record->setter1_id,
                'closer2_id' => $record->closer2_id,
                'setter2_id' => $record->setter2_id,
                'closer1_name' => $record->sales_rep_name,
                'setter1_name' => null,
                'closer2_name' => null,
                'setter2_name' => null,
                'prospect_id' => $record->prospect_id,
                'panel_type' => null,
                'panel_id' => null,
                'weekly_sheet_id' => null,
                'install_partner' => $record->install_partner,
                'install_partner_id' => $record->install_partner_id,
                'customer_name' => $record->customer_name,
                'customer_address' => $record->customer_address,
                'customer_address_2' => $record->customer_address_2,
                'customer_state' => $record->customer_state,
                'customer_zip' => $record->customer_zip,
                'customer_longitude' => null,
                'customer_latitude' => null,
                'customer_city' => $record->customer_city,
                'location_code' => $record->location_code,
                'customer_email' => $record->customer_email,
                'customer_phone' => $record->customer_phone,
                'homeowner_id' => $record->homeowner_id,
                'proposal_id' => $record->proposal_id,
                'sales_rep_name' => $record->sales_rep_name,
                'employee_id' => $record->employee_id,
                'sales_rep_email' => $record->sales_rep_email,
                'kw' => $record->kw,
                'balance_age' => $record->balance_age,
                'date_cancelled' => $record->date_cancelled,
                'customer_signoff' => $record->customer_signoff,
                'm1_date' => $record->m1_date,
                'm2_date' => $record->m2_date,
                'product' => $record->product,
                'product_id' => $record->product_id,
                'product_code' => $record->product_code,
                'sale_product_name' => $record->sale_product_name,
                'is_exempted' => 0,
                'total_commission_amount' => null,
                'total_override_amount' => 0.00,
                'milestone_trigger' => null,
                'gross_account_value' => $record->gross_account_value,
                'epc' => $record->epc,
                'net_epc' => $record->net_epc,
                'dealer_fee_percentage' => null,
                'dealer_fee_amount' => null,
                'adders' => $record->adders,
                'adders_description' => $record->adders_description,
                'state_id' => null,
                'm1_amount' => null,
                'total_amount_for_acct' => null,
                'prev_amount_paid' => null,
                'total_due' => null,
                'm2_amount' => null,
                'prev_deducted_amount' => null,
                'cancel_fee' => null,
                'cancel_deduction' => null,
                'lead_cost_amount' => null,
                'adv_pay_back_amount' => null,
                'total_amount_in_period' => null,
                'funding_source' => null,
                'financing_rate' => null,
                'financing_term' => null,
                'scheduled_install' => $record->scheduled_install,
                'install_complete_date' => $record->install_complete_date,
                'return_sales_date' => null,
                'cash_amount' => $record->cash_amount,
                'loan_amount' => $record->loan_amount,
                'length_of_agreement' => $record->length_of_agreement,
                'service_schedule' => $record->service_schedule,
                'initial_service_cost' => $record->initial_service_cost,
                'auto_pay' => $record->auto_pay,
                'card_on_file' => $record->card_on_file,
                'subscription_payment' => $record->subscription_payment,
                'service_completed' => $record->service_completed,
                'last_service_date' => $record->last_service_date,
                'last_date_pd' => null,
                'initial_service_date' => $record->initial_service_date,
                'bill_status' => $record->bill_status,
                'sales_type' => null,
                'm1_source_type' => null,
                'job_status' => $record->job_status,
                'trigger_date' => $record->trigger_date,
                'sale_item_status' => 1, // Mark as "In Action Item" since these are failed records
                'total_commission' => null,
                'projected_commission' => null,
                'total_override' => null,
                'data_source_type' => $record->data_source_type,
                'redline' => null,
                'projected_override' => 0,
                'action_item_status' => 1, // Mark as action item due to failure
                'import_status_reason' => $record->import_status_reason,
                'import_status_description' => $record->import_status_description,
                'updated_at' => now(),
            ];

            // Check if record with this PID already exists
            $existingRecord = SaleMasterExcluded::where('pid', $record->pid)->first();

            if ($existingRecord) {
                // Update existing record
                $existingRecord->update($filterData);

                Log::info('Observer: Updated existing filter record', [
                    'filter_record_id' => $existingRecord->id,
                    'pid' => $record->pid,
                    'legacy_record_id' => $record->id,
                    'import_status_reason' => $record->import_status_reason,
                ]);
            } else {
                // Create new record
                $filterData['created_at'] = now();
                $newRecord = SaleMasterExcluded::create($filterData);

                Log::info('Observer: Created new filter record', [
                    'filter_record_id' => $newRecord->id,
                    'pid' => $record->pid,
                    'legacy_record_id' => $record->id,
                    'import_status_reason' => $record->import_status_reason,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Observer: Error moving record to filter table', [
                'legacy_record_id' => $record->id,
                'pid' => $record->pid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw the exception to prevent breaking the main operation
            // Just log the error and continue
        }
    }

    /**
     * Log successful record to legacy_api_raw_data_histories_log table
     * Always inserts new record (append-only logging)
     */
    private function logToHistoriesLog(LegacyApiRawDataHistory $record): void
    {
        try {
            // Prepare the log data with all fields from the original record
            $logData = [
                'original_id' => $record->id,
                'pid' => $record->pid,
                'action_type' => 'success_import',
                'legacy_id' => $record->id,
                'closer1_id' => $record->closer1_id,
                'setter1_id' => $record->setter1_id,
                'closer2_id' => $record->closer2_id,
                'setter2_id' => $record->setter2_id,
                'customer_name' => $record->customer_name,
                'customer_address' => $record->customer_address,
                'customer_address_2' => $record->customer_address_2,
                'customer_state' => $record->customer_state,
                'customer_zip' => $record->customer_zip,
                'customer_city' => $record->customer_city,
                'location_code' => $record->location_code,
                'customer_email' => $record->customer_email,
                'customer_phone' => $record->customer_phone,
                'homeowner_id' => $record->homeowner_id,
                'proposal_id' => $record->proposal_id,
                'sales_rep_name' => $record->sales_rep_name,
                'employee_id' => $record->employee_id,
                'sales_rep_email' => $record->sales_rep_email,
                'kw' => $record->kw,
                'balance_age' => $record->balance_age,
                'date_cancelled' => $record->date_cancelled,
                'customer_signoff' => $record->customer_signoff,
                'm1_date' => $record->m1_date,
                'm2_date' => $record->m2_date,
                'product' => $record->product,
                'product_id' => $record->product_id,
                'product_code' => $record->product_code,
                'sale_product_name' => $record->sale_product_name,
                'gross_account_value' => $record->gross_account_value,
                'epc' => $record->epc,
                'net_epc' => $record->net_epc,
                'adders' => $record->adders,
                'adders_description' => $record->adders_description,
                'scheduled_install' => $record->scheduled_install,
                'install_complete_date' => $record->install_complete_date,
                'cash_amount' => $record->cash_amount,
                'loan_amount' => $record->loan_amount,
                'length_of_agreement' => $record->length_of_agreement,
                'service_schedule' => $record->service_schedule,
                'initial_service_cost' => $record->initial_service_cost,
                'auto_pay' => $record->auto_pay,
                'card_on_file' => $record->card_on_file,
                'subscription_payment' => $record->subscription_payment,
                'service_completed' => $record->service_completed,
                'last_service_date' => $record->last_service_date,
                'initial_service_date' => $record->initial_service_date,
                'bill_status' => $record->bill_status,
                'job_status' => $record->job_status,
                'trigger_date' => $record->trigger_date,
                'data_source_type' => $record->data_source_type,
                'import_to_sales' => $record->import_to_sales,
                'import_status_reason' => $record->import_status_reason,
                'import_status_description' => $record->import_status_description,
                'pay_period_from' => $record->pay_period_from,
                'pay_period_to' => $record->pay_period_to,
                'weekly_sheet_id' => $record->weekly_sheet_id,
                'excel_import_id' => $record->excel_import_id,
                'source_created_at' => $record->created_at,
                'source_updated_at' => $record->updated_at,
                'changed_at' => now(),
                'changed_by' => auth()->user()->id ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert to log table (always append, never update)
            DB::table('legacy_api_raw_data_histories_log')->insert($logData);

            Log::info('Observer: Successfully logged record to histories_log', [
                'original_record_id' => $record->id,
                'pid' => $record->pid,
                'action_type' => 'success_import',
            ]);

        } catch (\Exception $e) {
            Log::error('Observer: Error logging record to histories_log', [
                'legacy_record_id' => $record->id,
                'pid' => $record->pid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw the exception to prevent breaking the main operation
            // Just log the error and continue
        }
    }

    /**
     * Clean up sale_masters_excluded table by removing record with matching PID
     */
    private function cleanupFilterTable(string $pid): void
    {
        try {
            $deletedCount = SaleMasterExcluded::where('pid', $pid)->delete();

            if ($deletedCount > 0) {
                Log::info('Observer: Cleaned up filter table', [
                    'pid' => $pid,
                    'deleted_count' => $deletedCount,
                ]);
            } else {
                Log::debug('Observer: No records to cleanup in filter table', [
                    'pid' => $pid,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Observer: Error cleaning up filter table', [
                'pid' => $pid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw the exception to prevent breaking the main operation
            // Just log the error and continue
        }
    }
}
