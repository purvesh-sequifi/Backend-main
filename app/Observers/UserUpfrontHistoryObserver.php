<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserUpfrontHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserUpfrontHistory "created" event.
     */
    public function created(UserUpfrontHistory $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserUpfrontHistory "updated" event.
     */
    public function updated(UserUpfrontHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserUpfrontHistory "deleted" event.
     */
    public function deleted(UserUpfrontHistory $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserUpfrontHistory $model, string $changeType): void
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
            $prevRecord = UserUpfrontHistory::withTrashed()
                ->where('user_id', $userId)
                ->where('product_id', $model->product_id)
                ->where('core_position_id', $model->core_position_id)
                ->where('milestone_schema_trigger_id', $model->milestone_schema_trigger_id)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['upfront_pay_amount', 'upfront_sale_type']);

            $oldAmount = $prevRecord?->upfront_pay_amount;
            $oldType = $prevRecord?->upfront_sale_type;
            $newAmount = $model->upfront_pay_amount;
            $newType = $model->upfront_sale_type;

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
                'upfront_pay_amount' => $oldAmount,
                'upfront_sale_type' => $oldType,
            ];

            $newValues = [
                'upfront_pay_amount' => $newAmount,
                'upfront_sale_type' => $newType,
            ];

            UserUpfrontAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->upfront_effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['upfront_pay_amount', 'upfront_sale_type'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual upfront values, not old_* backup fields
            $trackableFields = ['upfront_pay_amount', 'upfront_sale_type'];
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

            UserUpfrontAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->upfront_effective_date,
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

            UserUpfrontAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->upfront_effective_date,
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
