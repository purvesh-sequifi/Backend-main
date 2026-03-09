<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserWages;
use App\Models\UserWagesAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserWagesObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserWages "created" event.
     */
    public function created(UserWages $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserWages "updated" event.
     */
    public function updated(UserWages $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserWages "deleted" event.
     */
    public function deleted(UserWages $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserWages $model, string $changeType): void
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

        // Wages don't have position, but may have department
        $positionName = null;

        if ($changeType === 'create') {
            // Look up PREVIOUS record to get old values (no SoftDeletes on this model)
            $prevRecord = UserWages::where('user_id', $userId)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['pay_type', 'pay_rate', 'pay_rate_type', 'pto_hours', 'expected_weekly_hours']);

            // Skip if no actual change in key fields
            $payRateChanged = ((float)($prevRecord?->pay_rate ?? 0) !== (float)($model->pay_rate ?? 0));
            $payTypeChanged = ($prevRecord?->pay_type !== $model->pay_type);
            $ptoChanged = ((float)($prevRecord?->pto_hours ?? 0) !== (float)($model->pto_hours ?? 0));

            if (!$payRateChanged && !$payTypeChanged && !$ptoChanged) {
                return;
            }

            $oldValues = [
                'pay_type' => $prevRecord?->pay_type,
                'pay_rate' => $prevRecord?->pay_rate,
                'pay_rate_type' => $prevRecord?->pay_rate_type,
                'pto_hours' => $prevRecord?->pto_hours,
                'expected_weekly_hours' => $prevRecord?->expected_weekly_hours,
            ];

            $newValues = [
                'pay_type' => $model->pay_type,
                'pay_rate' => $model->pay_rate,
                'pay_rate_type' => $model->pay_rate_type,
                'pto_hours' => $model->pto_hours,
                'expected_weekly_hours' => $model->expected_weekly_hours,
            ];

            UserWagesAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => null, // Wages don't have product_id
                'position_name' => $positionName,
                'effective_date' => $model->effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['wages'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual wage values
            $trackableFields = ['pay_type', 'pay_rate', 'pay_rate_type', 'pto_hours', 'expected_weekly_hours'];
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

            UserWagesAuditHistory::create([
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

            UserWagesAuditHistory::create([
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
