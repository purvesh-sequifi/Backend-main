<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserWithheldHistory;
use App\Models\UserWithheldAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserWithheldHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserWithheldHistory "created" event.
     */
    public function created(UserWithheldHistory $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserWithheldHistory "updated" event.
     */
    public function updated(UserWithheldHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserWithheldHistory "deleted" event.
     */
    public function deleted(UserWithheldHistory $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserWithheldHistory $model, string $changeType): void
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
        if ($model->position) {
            $positionName = $model->position->position_name ?? null;
        }

        if ($changeType === 'create') {
            // Look up PREVIOUS record to get old values (include soft-deleted!)
            $prevRecord = UserWithheldHistory::withTrashed()
                ->where('user_id', $userId)
                ->where('product_id', $model->product_id)
                ->where('self_gen_user', $model->self_gen_user)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['withheld_amount', 'withheld_type']);

            $oldAmount = $prevRecord?->withheld_amount;
            $oldType = $prevRecord?->withheld_type;
            $newAmount = $model->withheld_amount;
            $newType = $model->withheld_type;

            // Skip if no actual change
            $oldVal = (float) ($oldAmount ?? 0);
            $newVal = (float) ($newAmount ?? 0);

            if ($oldVal === $newVal && $oldType === $newType) {
                return;
            }

            // Skip if both are 0
            if ($oldVal === 0.0 && $newVal === 0.0) {
                return;
            }

            $oldValues = [
                'withheld_amount' => $oldAmount,
                'withheld_type' => $oldType,
            ];

            $newValues = [
                'withheld_amount' => $newAmount,
                'withheld_type' => $newType,
            ];

            UserWithheldAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->withheld_effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['withheld_amount', 'withheld_type'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual withheld values, not old_* backup fields
            $trackableFields = ['withheld_amount', 'withheld_type'];
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

            UserWithheldAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->withheld_effective_date,
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

            UserWithheldAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->withheld_effective_date,
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
