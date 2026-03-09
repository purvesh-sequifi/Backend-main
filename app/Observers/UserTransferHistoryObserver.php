<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserTransferHistory;
use App\Models\UserTransferAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserTransferHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserTransferHistory "created" event.
     */
    public function created(UserTransferHistory $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserTransferHistory "updated" event.
     */
    public function updated(UserTransferHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserTransferHistory "deleted" event.
     */
    public function deleted(UserTransferHistory $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserTransferHistory $model, string $changeType): void
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
            $prevRecord = UserTransferHistory::withTrashed()
                ->where('user_id', $userId)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['state_id', 'office_id', 'department_id', 'position_id', 'sub_position_id']);

            // Skip if no actual change in key fields
            $stateChanged = ($prevRecord?->state_id !== $model->state_id);
            $officeChanged = ($prevRecord?->office_id !== $model->office_id);
            $deptChanged = ($prevRecord?->department_id !== $model->department_id);
            $posChanged = ($prevRecord?->position_id !== $model->position_id);
            $subPosChanged = ($prevRecord?->sub_position_id !== $model->sub_position_id);

            if (!$stateChanged && !$officeChanged && !$deptChanged && !$posChanged && !$subPosChanged) {
                return;
            }

            $oldValues = [
                'state_id' => $prevRecord?->state_id,
                'office_id' => $prevRecord?->office_id,
                'department_id' => $prevRecord?->department_id,
                'position_id' => $prevRecord?->position_id,
                'sub_position_id' => $prevRecord?->sub_position_id,
            ];

            $newValues = [
                'state_id' => $model->state_id,
                'office_id' => $model->office_id,
                'department_id' => $model->department_id,
                'position_id' => $model->position_id,
                'sub_position_id' => $model->sub_position_id,
            ];

            UserTransferAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null, // Transfer doesn't have product_id
                'position_name' => $positionName,
                'effective_date' => $model->transfer_effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['transfer'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual transfer values, not old_* backup fields
            $trackableFields = ['state_id', 'office_id', 'department_id', 'position_id', 'sub_position_id'];
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

            UserTransferAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null,
                'position_name' => $positionName,
                'effective_date' => $model->transfer_effective_date,
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

            UserTransferAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null,
                'position_name' => $positionName,
                'effective_date' => $model->transfer_effective_date,
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
