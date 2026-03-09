<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserSelfGenCommissionAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserSelfGenCommissionHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserSelfGenCommmissionHistory "created" event.
     */
    public function created(UserSelfGenCommmissionHistory $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserSelfGenCommmissionHistory "updated" event.
     */
    public function updated(UserSelfGenCommmissionHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserSelfGenCommmissionHistory "deleted" event.
     */
    public function deleted(UserSelfGenCommmissionHistory $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserSelfGenCommmissionHistory $model, string $changeType): void
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
            // Look up PREVIOUS record to get old values (no SoftDeletes on this model)
            // Note: SelfGen doesn't have product_id, uses position_id and self_gen_user
            $prevRecord = UserSelfGenCommmissionHistory::where('user_id', $userId)
                ->where('position_id', $model->position_id)
                ->where('self_gen_user', $model->self_gen_user)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['commission', 'commission_type']);

            $oldCommission = $prevRecord?->commission;
            $oldType = $prevRecord?->commission_type;
            $newCommission = $model->commission;
            $newType = $model->commission_type;

            // Skip if no actual change
            $oldVal = (float) ($oldCommission ?? 0);
            $newVal = (float) ($newCommission ?? 0);

            if ($oldVal === $newVal && $oldType === $newType) {
                return;
            }

            // Skip if both are 0
            if ($oldVal === 0.0 && $newVal === 0.0) {
                return;
            }

            $oldValues = [
                'commission' => $oldCommission,
                'commission_type' => $oldType,
            ];

            $newValues = [
                'commission' => $newCommission,
                'commission_type' => $newType,
            ];

            UserSelfGenCommissionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null, // SelfGen doesn't have product_id
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

            UserSelfGenCommissionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null, // SelfGen doesn't have product_id
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

            UserSelfGenCommissionAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null, // SelfGen doesn't have product_id
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
