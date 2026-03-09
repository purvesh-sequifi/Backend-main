<?php

declare(strict_types=1);

use App\Models\UserCommission;
use App\Models\SaleProductMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ExternalSaleProductMaster;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Combines two separate triggers into one unified trigger that handles both internal
     * and external sales based on worker_type.
     * 
     * DROPS:
     * - `update_sale_product_master_after_user_commission_update` (internal sales)
     * - `update_external_sale_product_master_after_user_commission_update` (external sales)
     * 
     * CREATES:
     * - `update_sale_product_master_after_user_commission_update` (combined trigger)
     * 
     * LOGIC:
     * - If NEW.worker_type = "external" → Updates external_sale_product_master
     *   (matches: type, pid, worker_id)
     * - If NEW.worker_type != "external" → Updates sale_product_master
     *   (matches: type, pid, setter1_id/setter2_id/closer1_id/closer2_id)
     * 
     * CRITICAL FIX:
     * - Added missing `pid` condition to internal sales update
     * - Ensures updates only affect the correct sale records
     * - Prevents incorrect updates when multiple sales have same type and user_id
     */
    public function up(): void
    {
        try {
            // Drop both existing triggers
            DB::unprepared('DROP TRIGGER IF EXISTS `update_sale_product_master_after_user_commission_update`');
            DB::unprepared('DROP TRIGGER IF EXISTS `update_external_sale_product_master_after_user_commission_update`');

            // Create the combined trigger
            DB::unprepared("
                CREATE TRIGGER `update_sale_product_master_after_user_commission_update`
                AFTER UPDATE ON `user_commission`
                FOR EACH ROW
                BEGIN
                    IF OLD.status = 1 AND NEW.status = 3 THEN
                        -- Handle external sales
                        IF NEW.worker_type = 'external' THEN
                            UPDATE `external_sale_product_master`
                            SET is_paid = 1
                            WHERE external_sale_product_master.type = NEW.schema_type
                              AND external_sale_product_master.pid = NEW.pid
                              AND external_sale_product_master.worker_id = NEW.user_id;
                        
                        -- Handle internal sales (worker_type != 'external')
                        ELSE
                            UPDATE `sale_product_master`
                            SET is_paid = 1
                            WHERE sale_product_master.type = NEW.schema_type
                              AND sale_product_master.pid = NEW.pid
                              AND (
                                sale_product_master.setter1_id = NEW.user_id
                                OR sale_product_master.setter2_id = NEW.user_id
                                OR sale_product_master.closer1_id = NEW.user_id
                                OR sale_product_master.closer2_id = NEW.user_id
                              );
                        END IF;
                    END IF;
                END
            ");

            Log::info('Successfully created combined trigger: update_sale_product_master_after_user_commission_update');
        } catch (\Exception $e) {
            Log::error('Failed to create combined trigger: update_sale_product_master_after_user_commission_update', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }

        try {
            // Get total counts for progress tracking
            $totalStatus1 = UserCommission::where('status', 1)->count();
            $totalStatus3 = UserCommission::where('status', 3)->count();

            Log::info('Starting data fix for sale_product_master and external_sale_product_master tables', [
                'total_status_1_records' => $totalStatus1,
                'total_status_3_records' => $totalStatus3,
            ]);

            $processedStatus1 = 0;
            $processedStatus3 = 0;
            $skippedStatus1 = 0;
            $skippedStatus3 = 0;

            // Fix incorrect data: Reset is_paid for status = 1 (unpaid commissions)
            // This corrects records that were incorrectly marked as paid by the old trigger
            UserCommission::where('status', 1)
                ->whereNotNull('pid')
                ->whereNotNull('schema_type')
                ->whereNotNull('user_id')
                ->chunkById(100, function ($userCommissions) use (&$processedStatus1, &$skippedStatus1) {
                    foreach ($userCommissions as $userCommission) {
                        // Skip records with missing required fields
                        if (empty($userCommission->pid) || empty($userCommission->schema_type) || empty($userCommission->user_id)) {
                            $skippedStatus1++;
                            continue;
                        }

                        try {
                            // Handle external sales
                            if ($userCommission->worker_type === 'external') {
                                ExternalSaleProductMaster::where('pid', $userCommission->pid)
                                    ->where('type', $userCommission->schema_type)
                                    ->where('worker_id', $userCommission->user_id)
                                    ->update(['is_paid' => 0]);
                            } else {
                                // Handle internal sales - use closure to properly group OR conditions
                                SaleProductMaster::where('pid', $userCommission->pid)
                                    ->where('type', $userCommission->schema_type)
                                    ->where(function ($query) use ($userCommission) {
                                        $query->where('setter1_id', $userCommission->user_id)
                                            ->orWhere('setter2_id', $userCommission->user_id)
                                            ->orWhere('closer1_id', $userCommission->user_id)
                                            ->orWhere('closer2_id', $userCommission->user_id);
                                    })
                                    ->update(['is_paid' => 0]);
                            }
                            $processedStatus1++;
                        } catch (\Exception $e) {
                            Log::warning('Failed to update record in data fix (status=1)', [
                                'user_commission_id' => $userCommission->id,
                                'pid' => $userCommission->pid,
                                'schema_type' => $userCommission->schema_type,
                                'user_id' => $userCommission->user_id,
                                'worker_type' => $userCommission->worker_type,
                                'error' => $e->getMessage(),
                            ]);
                            $skippedStatus1++;
                        }
                    }
                });

            // Fix incorrect data: Set is_paid for status = 3 (paid commissions)
            // This corrects records that should be marked as paid but weren't due to missing PID check
            UserCommission::where('status', 3)
                ->whereNotNull('pid')
                ->whereNotNull('schema_type')
                ->whereNotNull('user_id')
                ->chunkById(100, function ($userCommissions) use (&$processedStatus3, &$skippedStatus3) {
                    foreach ($userCommissions as $userCommission) {
                        // Skip records with missing required fields
                        if (empty($userCommission->pid) || empty($userCommission->schema_type) || empty($userCommission->user_id)) {
                            $skippedStatus3++;
                            continue;
                        }

                        try {
                            // Handle external sales
                            if ($userCommission->worker_type === 'external') {
                                ExternalSaleProductMaster::where('pid', $userCommission->pid)
                                    ->where('type', $userCommission->schema_type)
                                    ->where('worker_id', $userCommission->user_id)
                                    ->update(['is_paid' => 1]);
                            } else {
                                // Handle internal sales - use closure to properly group OR conditions
                                SaleProductMaster::where('pid', $userCommission->pid)
                                    ->where('type', $userCommission->schema_type)
                                    ->where(function ($query) use ($userCommission) {
                                        $query->where('setter1_id', $userCommission->user_id)
                                            ->orWhere('setter2_id', $userCommission->user_id)
                                            ->orWhere('closer1_id', $userCommission->user_id)
                                            ->orWhere('closer2_id', $userCommission->user_id);
                                    })
                                    ->update(['is_paid' => 1]);
                            }
                            $processedStatus3++;
                        } catch (\Exception $e) {
                            Log::warning('Failed to update record in data fix (status=3)', [
                                'user_commission_id' => $userCommission->id,
                                'pid' => $userCommission->pid,
                                'schema_type' => $userCommission->schema_type,
                                'user_id' => $userCommission->user_id,
                                'worker_type' => $userCommission->worker_type,
                                'error' => $e->getMessage(),
                            ]);
                            $skippedStatus3++;
                        }
                    }
                });

            Log::info('Successfully fixed incorrect data in sale_product_master and external_sale_product_master tables', [
                'status_1_processed' => $processedStatus1,
                'status_1_skipped' => $skippedStatus1,
                'status_3_processed' => $processedStatus3,
                'status_3_skipped' => $skippedStatus3,
                'total_status_1_records' => $totalStatus1,
                'total_status_3_records' => $totalStatus3,
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to fix incorrect data in sale_product_master and external_sale_product_master tables', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Restores the two separate triggers (for rollback purposes).
     * Note: This restores the old behavior with separate triggers.
     */
    public function down(): void
    {
        // No rollback needed because of the critical fix and can not be reverted
    }
};
