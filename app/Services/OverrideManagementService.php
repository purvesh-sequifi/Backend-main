<?php

namespace App\Services;

use App\Models\UserOverrides;
use App\Models\ProjectionUserOverrides;
use App\Models\OverrideArchive;
use App\Models\User;
use App\Models\SalesMaster;
use App\Models\Payroll;
use App\Models\LegacyApiRawDataHistory;
use App\Core\Traits\PayFrequencyTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OverrideManagementService
{
    use PayFrequencyTrait;

    /**
     * Hard delete an override and move to unified archive
     */
    public function deleteOverride($overrideId, $pid, $isProjection = false, $deletionReason = null)
    {
        $model = $isProjection ? ProjectionUserOverrides::class : UserOverrides::class;
        
        $override = $model::find($overrideId);
        
        if (!$override || $override->pid !== $pid) {
            throw new \Exception('Override not found or does not belong to the specified PID');
        }

        // Validate deletion conditions
        $this->validateDeletionConditions($override, $isProjection);

        DB::beginTransaction();
        
        try {
            // Prepare archive data
            $archiveData = $override->toArray();
            $archiveData['original_id'] = $override->id;
            $archiveData['override_type'] = $isProjection ? 'projection' : 'normal';
            $archiveData['deleted_at'] = now();
            $archiveData['deleted_by'] = auth()->id();
            $archiveData['deletion_reason'] = $deletionReason;
            $archiveData['original_pay_period_from'] = $override->pay_period_from;
            $archiveData['original_pay_period_to'] = $override->pay_period_to;
            $archiveData['can_restore'] = $this->canRestore($override, $isProjection);
            
            // For projection overrides, map total_override to amount field for consistency
            if ($isProjection && isset($archiveData['total_override'])) {
                $archiveData['amount'] = $archiveData['total_override'];
            }
            
            // Remove fields that shouldn't be in archive
            unset($archiveData['id'], $archiveData['created_at'], $archiveData['updated_at']);
            
            // Create unified archive record
            OverrideArchive::create($archiveData);
            
            // Delete original record
            $override->delete();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Override deleted successfully',
                'can_restore' => $archiveData['can_restore']
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Restore an override from unified archive
     */
    public function restoreOverride($archiveId, $pid, $isProjection = false)
    {
        $archivedOverride = OverrideArchive::find($archiveId);
        
        if (!$archivedOverride || $archivedOverride->pid !== $pid) {
            throw new \Exception('Archived override not found or does not belong to the specified PID');
        }

        if (!$archivedOverride->can_restore) {
            throw new \Exception('This override cannot be restored');
        }

        // Validate override type matches request
        $expectedType = $isProjection ? 'projection' : 'normal';
        if ($archivedOverride->override_type !== $expectedType) {
            throw new \Exception('Override type mismatch');
        }

        // Validate restoration conditions
        $this->validateRestorationConditions($archivedOverride);

        DB::beginTransaction();
        
        try {
            // Get pay period using the original milestone date (before deletion) to match the same pay period
            // This ensures the restored override has the same pay period it had when originally created
            $milestoneDate = $archivedOverride->original_pay_period_from ?? $archivedOverride->pay_period_from;
            $currentPayPeriod = $this->getCurrentOpenPayPeriod($archivedOverride->user_id, $milestoneDate);
            
            // Prepare restoration data
            $restoreData = $archivedOverride->toArray();
            
            // Remove archive-specific fields
            unset(
                $restoreData['id'], 
                $restoreData['original_id'], 
                $restoreData['override_type'],
                $restoreData['deleted_at'], 
                $restoreData['deleted_by'], 
                $restoreData['deletion_reason'],
                $restoreData['original_pay_period_from'], 
                $restoreData['original_pay_period_to'],
                $restoreData['can_restore'], 
                $restoreData['restoration_pay_period_from'],
                $restoreData['restoration_pay_period_to'], 
                $restoreData['created_at'], 
                $restoreData['updated_at'],
                $restoreData['customer_signoff']  // Only exists in archive
            );
            
            // Remove fields that don't exist in the target table based on override type
            if ($isProjection) {
                // For projection overrides, remove normal override fields and fields that don't exist in projection_user_overrides
                unset(
                    $restoreData['amount'],        // Normal overrides use 'amount', projection uses 'total_override'
                    $restoreData['is_displayed'],  // Only in user_overrides
                    $restoreData['recon_status'],  // Only in user_overrides
                    $restoreData['payroll_id'],    // Only in user_overrides
                    $restoreData['product_id'],    // Only in user_overrides
                    $restoreData['during'],        // Only in user_overrides
                    $restoreData['product_code'],  // Only in user_overrides
                    $restoreData['net_epc'],       // Only in user_overrides
                    $restoreData['comment'],       // Only in user_overrides
                    $restoreData['adjustment_amount'], // Only in user_overrides
                    $restoreData['is_mark_paid'],     // Only in user_overrides
                    $restoreData['is_next_payroll'],  // Only in user_overrides
                    $restoreData['ref_id'],           // Only in user_overrides
                    $restoreData['is_move_to_recon'], // Only in user_overrides
                    $restoreData['is_onetime_payment'], // Only in user_overrides
                    $restoreData['one_time_payment_id'], // Only in user_overrides
                    $restoreData['worker_type'],       // Only in user_overrides
                    $restoreData['override_over']      // Exists in archive but not in projection_user_overrides table
                );
            } else {
                // For normal overrides, remove projection override fields
                unset(
                    $restoreData['customer_name'],    // Only in projection_user_overrides
                    $restoreData['override_over'],    // Exists in archive but not in user_overrides or projection_user_overrides tables
                    $restoreData['total_override'],   // Only in projection_user_overrides
                    $restoreData['is_stop_payroll'],  // Only in projection_user_overrides
                    $restoreData['date']               // Only in projection_user_overrides
                );
            }
            
            // Update pay period to current open period
            $restoreData['pay_period_from'] = $currentPayPeriod['pay_period_from'];
            $restoreData['pay_period_to'] = $currentPayPeriod['pay_period_to'];
            
            // Reset status to unpaid (status = 1)
            $restoreData['status'] = 1;
            
            // Force is_displayed to 1 for restored normal overrides only
            // Note: is_displayed only exists in user_overrides table, not in projection_user_overrides
            // Note: is_displayed is an ENUM('0','1') so we must use string '1'
            if (!$isProjection) {
                $restoreData['is_displayed'] = '1';
            }
            
            // Also set recon_status if it exists (only for normal overrides)
            if (!$isProjection && isset($restoreData['recon_status'])) {
                $restoreData['recon_status'] = 1;
            }
            
            // Create new override record in appropriate table
            $model = $isProjection ? ProjectionUserOverrides::class : UserOverrides::class;
            
            // Debug: Log the restore data
            Log::info("Restoring override", [
                'is_projection' => $isProjection,
                'status' => $restoreData['status'],
                'user_id' => $archivedOverride->user_id,
                'pid' => $pid,
                'has_is_displayed' => isset($restoreData['is_displayed']),
                'restoreData_keys' => array_keys($restoreData)
            ]);
            
            $newOverride = $model::create($restoreData);
            
            // Force update is_displayed after creation to ensure it's set (only for normal overrides)
            if (!$isProjection) {
                $newOverride->is_displayed = '1';
                $newOverride->save();
            }
            
            Log::info("Override created", [
                'new_override_id' => $newOverride->id,
                'is_projection' => $isProjection,
                'has_is_displayed' => isset($newOverride->is_displayed)
            ]);
            
            // *** PAYROLL INTEGRATION ***
            // Only create payroll record for normal overrides with during_m2 settlement
            $payrollIntegrated = false;
            if (!$isProjection && 
                isset($restoreData['overrides_settlement_type']) && 
                $restoreData['overrides_settlement_type'] == 'during_m2') {
                
                // Get user's position ID for payroll integration
                // $user = User::find($archivedOverride->user_id);
                // $positionId = $user->sub_position_id;
                
                // Create payroll record using existing function
                // subroutineCreatePayrollRecord($archivedOverride->user_id, $positionId, (object)$currentPayPeriod);
                $payrollIntegrated = true;
            }
            
            // Delete the archive entry since override is now restored
            // This prevents multiple archive entries if the same override is deleted again
            $archivedOverride->delete();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Override restored successfully',
                'new_override_id' => $newOverride->id,
                'original_pay_period' => [
                    'from' => $archivedOverride->original_pay_period_from,
                    'to' => $archivedOverride->original_pay_period_to
                ],
                'restoration_pay_period' => $currentPayPeriod,
                'payroll_integrated' => $payrollIntegrated
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Get all overrides for a sale including restorable ones
     */
    public function getOverridesForSale($pid, $includeRestorable = true)
    {
        $activeOverrides = UserOverrides::where('pid', $pid)->get();
        $projectionOverrides = ProjectionUserOverrides::where('pid', $pid)->get();
        
        $restorableOverrides = collect();
        
        if ($includeRestorable) {
            $restorableOverrides = OverrideArchive::forPid($pid)
                ->restorable()
                ->get();
        }
        
        return [
            'active_overrides' => $activeOverrides,
            'projection_overrides' => $projectionOverrides,
            'restorable_overrides' => $restorableOverrides
        ];
    }

    /**
     * Get restorable overrides for a specific PID and type
     */
    public function getRestorableOverrides($pid, $overrideType = null)
    {
        $query = OverrideArchive::forPid($pid)->restorable();
        
        if ($overrideType) {
            $query->where('override_type', $overrideType);
        }
        
        return $query->with(['deletedBy', 'user', 'saleUser'])
            ->get()
            ->map(function ($archivedOverride) {
                // Get current open pay period for display
                $currentPayPeriod = $this->getCurrentOpenPayPeriod($archivedOverride->user_id);
                
                return [
                    'archive_id' => $archivedOverride->id,
                    'original_id' => $archivedOverride->original_id,
                    'override_type' => $archivedOverride->override_type,
                    'user_id' => $archivedOverride->user_id,
                    'sale_user_id' => $archivedOverride->sale_user_id,
                    'pid' => $archivedOverride->pid,
                    'type' => $archivedOverride->type,
                    'amount' => $archivedOverride->amount,
                    'overrides_amount' => $archivedOverride->overrides_amount,
                    'overrides_type' => $archivedOverride->overrides_type,
                    
                    // User information
                    'user_name' => $archivedOverride->user ? 
                        ($archivedOverride->user->first_name . ' ' . $archivedOverride->user->last_name) : null,
                    'sale_user_name' => $archivedOverride->saleUser ? 
                        ($archivedOverride->saleUser->first_name . ' ' . $archivedOverride->saleUser->last_name) : null,
                    
                    // Deletion information
                    'deleted_at' => $archivedOverride->deleted_at,
                    'deleted_by' => $archivedOverride->deletedBy ? 
                        ($archivedOverride->deletedBy->first_name . ' ' . $archivedOverride->deletedBy->last_name) : null,
                    'deletion_reason' => $archivedOverride->deletion_reason,
                    
                    // Pay period information
                    'original_pay_period' => [
                        'from' => $archivedOverride->original_pay_period_from,
                        'to' => $archivedOverride->original_pay_period_to
                    ],
                    'restoration_pay_period' => $currentPayPeriod,
                    
                    // Status
                    'can_restore' => $archivedOverride->can_restore,
                    'status' => $archivedOverride->status
                ];
            });
    }

    /**
     * Get current open pay period for a user using existing functions
     * Optionally uses a milestone date (e.g., original pay period date) to match the pay period from before deletion
     * 
     * @param int $userId The user ID
     * @param string|null $milestoneDate Optional date to use instead of current date (e.g., original pay_period_from)
     * @return array Pay period dates
     */
    private function getCurrentOpenPayPeriod($userId, $milestoneDate = null)
    {
        $user = User::with('positionDetail.payFrequency')->find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $positionId = $user->sub_position_id;
        
        // Use milestone date (original pay period date) if provided, otherwise use current date
        // This ensures we match the same pay period the override had before deletion
        $dateToUse = $milestoneDate ? $milestoneDate : now()->format('Y-m-d');
        
        // Use the SAME function that's used in override creation
        $payPeriod = $this->payFrequencyNew($dateToUse, $positionId, $userId);
        
        return [
            'pay_period_from' => isset($payPeriod->pay_period_from) ? $payPeriod->pay_period_from : NULL,
            'pay_period_to' => isset($payPeriod->pay_period_to) ? $payPeriod->pay_period_to : NULL
        ];
    }

    /**
     * Validate if override can be deleted
     */
    private function validateDeletionConditions($override, $isProjection)
    {
        // Check if override is already paid
        if ($override->status == 3 && $override->overrides_settlement_type == 'during_m2') {
            throw new \Exception('Cannot delete override that has been paid out');
        }else if ($override->status == 3 && $override->overrides_settlement_type == 'reconciliation' && ($override->recon_status == '3' || $override->recon_status == '2')) {
            throw new \Exception('Cannot delete override that has been paid out');
        }
        // Check if payroll is being finalized
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            throw new \Exception('Cannot delete override during payroll finalization');
        }
        
        // Check if Excel import is in progress
        $excelImport = LegacyApiRawDataHistory::where([
            'pid' => $override->pid,
            'import_to_sales' => '0',
            'data_source_type' => 'excel'
        ])->whereNotNull('excel_import_id')->first();
        
        if ($excelImport) {
            throw new \Exception('Cannot delete override during Excel import');
        }
    }

    /**
     * Validate conditions for restoring an override
     */
    private function validateRestorationConditions($archivedOverride)
    {
        // Check if override is already paid
        if ($archivedOverride->status == 3 && $archivedOverride->overrides_settlement_type == 'during_m2') {
            throw new \Exception('Cannot restore override that has been paid out');
        }else if ($archivedOverride->status == 3 && $archivedOverride->overrides_settlement_type == 'reconciliation' && ($archivedOverride->recon_status == '3' || $archivedOverride->recon_status == '2')) {
            throw new \Exception('Cannot restore override that has been paid out');
        }
        
        // Check if payroll is being finalized
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            throw new \Exception('Cannot restore override during payroll finalization');
        }
        
        // Check if Excel import is in progress
        $excelImport = LegacyApiRawDataHistory::where([
            'pid' => $archivedOverride->pid,
            'import_to_sales' => '0',
            'data_source_type' => 'excel'
        ])->whereNotNull('excel_import_id')->first();
        
        if ($excelImport) {
            throw new \Exception('Cannot restore override during Excel import');
        }
        
        // Check if user still exists and is active
        $user = User::find($archivedOverride->user_id);
        if (!$user) {
            throw new \Exception('User no longer exists');
        }
        
        if ($user->dismiss == 1) {
            throw new \Exception('Cannot restore override for dismissed user');
        }
        
        if ($user->terminate == 1) {
            throw new \Exception('Cannot restore override for terminated user');
        }
    }

    /**
     * Check if override can be restored
     */
    private function canRestore($override, $isProjection)
    {
        // Only unpaid overrides can be restored
        if($override->status == 1){
            return true;
        }else if($override->status == 3 && $override->overrides_settlement_type == 'reconciliation' && $override->recon_status == 1){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Check if override was previously deleted (archived) and should not be recreated
     */
    public function isOverrideArchived($userId, $pid, $type, $saleUserId = null)
    {
        $query = OverrideArchive::where([
            'user_id' => $userId,
            'pid' => $pid,
            'type' => $type
        ]);

        // For overrides with sale_user_id, include it in the check
        if ($saleUserId !== null) {
            $query->where('sale_user_id', $saleUserId);
        } else {
            $query->whereNull('sale_user_id');
        }

        // Check if there's an archived override
        // For V2 hard delete system: If an override exists in archive, 
        // it means it was intentionally deleted and should not be recreated
        // The user can restore it manually via restore endpoint if needed
        $archivedOverride = $query->first();

        if ($archivedOverride) {
            // Override exists in archive, prevent recreation
            Log::info("Normal override found in archive - blocking recreation", [
                'user_id' => $userId,
                'pid' => $pid,
                'type' => $type,
                'sale_user_id' => $saleUserId,
                'archive_id' => $archivedOverride->id
            ]);
            return true; // Override is archived and cannot be recreated
        }

        // IMPORTANT: No archive entry found = creation is ALLOWED
        // This ensures first-time milestone date addition works correctly
        return false; // No archived override found, creation is allowed
    }

    /**
     * Check if projection override was previously deleted (archived) and should not be recreated
     * 
     * MAIN LOGIC:
     * - If override found in archive → return true (block creation)
     * - If override NOT found in archive → return false (allow creation)
     * 
     * This ensures deleted overrides are NOT recreated during recalculation
     * When new date is added and no archive exists → override will be created
     */
    public function isProjectionOverrideArchived($userId, $pid, $type, $saleUserId = null)
    {
        // Build query to check if this specific override exists in archive
        $query = OverrideArchive::where([
            'user_id' => $userId,
            'pid' => $pid,
            'type' => $type,
            'override_type' => 'projection' // CRITICAL: Only check projection overrides
        ]);

        // For overrides with sale_user_id (Stack, Indirect), include it in the check
        // For overrides without sale_user_id (Office, Direct, Manual), check for null
        if ($saleUserId !== null) {
            $query->where('sale_user_id', $saleUserId);
        } else {
            $query->whereNull('sale_user_id');
        }

        // Check if there's an archived override
        // If found → override was deleted → block recreation
        $archivedOverride = $query->first();

        if ($archivedOverride) {
            // Override exists in archive, prevent recreation
            Log::info("Projection override found in archive - blocking recreation", [
                'user_id' => $userId,
                'pid' => $pid,
                'type' => $type,
                'sale_user_id' => $saleUserId,
                'archive_id' => $archivedOverride->id
            ]);
            return true; // Override is archived and cannot be recreated
        }

        // IMPORTANT: No archive entry found = creation is ALLOWED
        // This ensures first-time milestone date addition works correctly
        return false; // No archived override found, creation is allowed
    }

    /**
     * Get override statistics for a sale
     */
    public function getOverrideStatistics($pid)
    {
        $activeCount = UserOverrides::where('pid', $pid)->count();
        $projectionCount = ProjectionUserOverrides::where('pid', $pid)->count();
        $restorableCount = OverrideArchive::forPid($pid)->restorable()->count();
        $totalArchivedCount = OverrideArchive::forPid($pid)->count();
        
        return [
            'active_overrides' => $activeCount,
            'projection_overrides' => $projectionCount,
            'restorable_overrides' => $restorableCount,
            'total_archived' => $totalArchivedCount,
            'total_active' => $activeCount + $projectionCount
        ];
    }
}
