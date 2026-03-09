<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserDeductionHistory;
use App\Models\UserDeductionAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserDeductionHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserDeductionHistory "created" event.
     */
    public function created(UserDeductionHistory $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserDeductionHistory "updated" event.
     */
    public function updated(UserDeductionHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserDeductionHistory "deleted" event.
     */
    public function deleted(UserDeductionHistory $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserDeductionHistory $model, string $changeType): void
    {
        // Skip audit for bulk position updates to avoid timeout
        if ($this->shouldSkipForBulkOperation()) {
            return;
        }

        $userId = $this->getUserIdFromModel($model);

        if (!$userId) {
            return;
        }

        $changedBy = $this->getChangedBy();
        $changeSource = $this->detectChangeSource();
        $ipAddress = $this->getIpAddress();
        $userAgent = $this->getUserAgent();
        $reason = $this->getChangeReason();

        // Get position name for display
        $positionName = null;
        if ($model->subPosition) {
            $positionName = $model->subPosition->position_name ?? null;
        }

        if ($changeType === 'create') {
            // Look up PREVIOUS record to get old values (include soft-deleted!)
            $prevRecord = UserDeductionHistory::withTrashed()
                ->where('user_id', $userId)
                ->where('cost_center_id', $model->cost_center_id)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['amount_par_paycheque', 'limit_value']);

            $oldAmount = $prevRecord?->amount_par_paycheque;
            $oldLimit = $prevRecord?->limit_value;
            $newAmount = $model->amount_par_paycheque;
            $newLimit = $model->limit_value;

            // Skip if no actual change
            $oldVal = (float) ($oldAmount ?? 0);
            $newVal = (float) ($newAmount ?? 0);

            if ($oldVal === $newVal && $oldLimit === $newLimit) {
                return;
            }

            // Skip if both are 0
            if ($oldVal === 0.0 && $newVal === 0.0) {
                return;
            }

            $oldValues = [
                'amount_par_paycheque' => $oldAmount,
                'limit_value' => $oldLimit,
            ];

            $newValues = [
                'amount_par_paycheque' => $newAmount,
                'limit_value' => $newLimit,
            ];

            UserDeductionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null, // Deductions don't have product_id
                'position_name' => $positionName,
                'effective_date' => $model->effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['amount_par_paycheque', 'limit_value'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual deduction values
            $trackableFields = ['amount_par_paycheque', 'limit_value'];
            $dirty = $model->getDirty();
            $original = $model->getOriginal();

            $oldValues = [];
            $newValues = [];
            $changedFields = [];

            foreach ($trackableFields as $field) {
                if (array_key_exists($field, $dirty)) {
                    $oldVal = $original[$field] ?? null;
                    $newVal = $dirty[$field] ?? null;
                    if ($oldVal !== $newVal) {
                        $oldValues[$field] = $oldVal;
                        $newValues[$field] = $newVal;
                        $changedFields[] = $field;
                    }
                }
            }

            // Skip if no meaningful changes
            if (empty($changedFields)) {
                return;
            }

            UserDeductionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null,
                'position_name' => $positionName,
                'effective_date' => $model->effective_date,
                'change_type' => 'update',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'delete') {
            $oldValues = $this->getAllAttributes($model);

            UserDeductionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null,
                'position_name' => $positionName,
                'effective_date' => $model->effective_date,
                'change_type' => 'delete',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => null,
                'changed_fields' => array_keys($oldValues),
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }
    }
}
