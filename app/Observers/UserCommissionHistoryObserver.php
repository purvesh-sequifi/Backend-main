<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserCommissionHistory;
use App\Models\UserCommissionAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserCommissionHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Fields to track for commission history.
     * Only changes to these fields will be logged.
     */
    protected function getTrackableFields(): array
    {
        return [
            'product_id',
            'commission',
            'commission_type',
            'commission_effective_date',
            'effective_end_date',
            'position_id',
            'core_position_id',
            'sub_position_id',
        ];
    }

    /**
     * Handle the UserCommissionHistory "created" event.
     * Track all CREATE events to make audit table self-contained.
     */
    public function created(UserCommissionHistory $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserCommissionHistory "updated" event.
     */
    public function updated(UserCommissionHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserCommissionHistory "deleted" event.
     */
    public function deleted(UserCommissionHistory $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserCommissionHistory $model, string $changeType): void
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

        if ($changeType === 'create') {
            // For CREATE: Look up the PREVIOUS commission value ourselves
            // (old_commission isn't reliably set at creation time)
            $newCommission = $model->commission;
            $newType = $model->commission_type;

            // Find the PREVIOUS record for this user/product to get old value
            // Include soft-deleted records (they were just deleted in same transaction!)
            $prevRecord = UserCommissionHistory::withTrashed()
                ->where('user_id', $userId)
                ->where('product_id', $model->product_id)
                ->where('id', '<', $model->id)
                ->where('core_position_id', $model->core_position_id)
                ->orderBy('id', 'desc')
                ->first(['commission', 'commission_type']);

            $oldCommission = $prevRecord?->commission;
            $oldType = $prevRecord?->commission_type;

            // Convert to float for comparison
            $oldVal = (float) ($oldCommission ?? 0);
            $newVal = (float) ($newCommission ?? 0);

            // Skip if both are 0
            if ($oldVal === 0.0 && $newVal === 0.0) {
                return;
            }

            // Skip if same value and same type (NO ACTUAL CHANGE)
            if ($oldVal === $newVal && $oldType === $newType) {
                return;
            }

            $oldValues = [
                'commission' => $oldCommission,
                'commission_type' => $oldType,
            ];

            $newValues = [
                'commission' => $newCommission,
                'commission_type' => $newType,
                'commission_effective_date' => $model->commission_effective_date,
            ];

            // Get position name for display
            $positionName = null;
            if ($model->position) {
                $positionName = $model->position->position_name;
            }

            UserCommissionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->commission_effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['commission', 'commission_type'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual commission values, not old_* backup fields
            $trackableFields = ['commission', 'commission_type'];
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

            // Get position name for display
            $positionName = null;
            if ($model->position) {
                $positionName = $model->position->position_name;
            }

            UserCommissionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->commission_effective_date,
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

            // Get position name for display
            $positionName = null;
            if ($model->position) {
                $positionName = $model->position->position_name;
            }

            UserCommissionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->commission_effective_date,
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

