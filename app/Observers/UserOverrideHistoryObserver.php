<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserOverrideHistory;
use App\Models\UserOverrideAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserOverrideHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserOverrideHistory "created" event.
     */
    public function created(UserOverrideHistory $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserOverrideHistory "updated" event.
     */
    public function updated(UserOverrideHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserOverrideHistory "deleted" event.
     */
    public function deleted(UserOverrideHistory $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserOverrideHistory $model, string $changeType): void
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
            $prevRecord = UserOverrideHistory::withTrashed()
                ->where('user_id', $userId)
                ->where('product_id', $model->product_id)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first([
                    'direct_overrides_amount', 'direct_overrides_type',
                    'indirect_overrides_amount', 'indirect_overrides_type',
                    'office_overrides_amount', 'office_overrides_type',
                    'direct_custom_sales_field_id', 'indirect_custom_sales_field_id', 'office_custom_sales_field_id',
                ]);

            $oldDirect = $prevRecord?->direct_overrides_amount;
            $oldIndirect = $prevRecord?->indirect_overrides_amount;
            $oldOffice = $prevRecord?->office_overrides_amount;
            $newDirect = $model->direct_overrides_amount;
            $newIndirect = $model->indirect_overrides_amount;
            $newOffice = $model->office_overrides_amount;

            // Skip if no actual change in any override
            $directChanged = ((float)($oldDirect ?? 0) !== (float)($newDirect ?? 0));
            $indirectChanged = ((float)($oldIndirect ?? 0) !== (float)($newIndirect ?? 0));
            $officeChanged = ((float)($oldOffice ?? 0) !== (float)($newOffice ?? 0));

            if (!$directChanged && !$indirectChanged && !$officeChanged) {
                return;
            }

            $oldValues = [
                'direct_overrides_amount' => $oldDirect,
                'direct_overrides_type' => $prevRecord?->direct_overrides_type,
                'direct_custom_sales_field_id' => $prevRecord?->direct_custom_sales_field_id,
                'indirect_overrides_amount' => $oldIndirect,
                'indirect_overrides_type' => $prevRecord?->indirect_overrides_type,
                'indirect_custom_sales_field_id' => $prevRecord?->indirect_custom_sales_field_id,
                'office_overrides_amount' => $oldOffice,
                'office_overrides_type' => $prevRecord?->office_overrides_type,
                'office_custom_sales_field_id' => $prevRecord?->office_custom_sales_field_id,
            ];

            $newValues = [
                'direct_overrides_amount' => $newDirect,
                'direct_overrides_type' => $model->direct_overrides_type,
                'direct_custom_sales_field_id' => $model->direct_custom_sales_field_id,
                'indirect_overrides_amount' => $newIndirect,
                'indirect_overrides_type' => $model->indirect_overrides_type,
                'indirect_custom_sales_field_id' => $model->indirect_custom_sales_field_id,
                'office_overrides_amount' => $newOffice,
                'office_overrides_type' => $model->office_overrides_type,
                'office_custom_sales_field_id' => $model->office_custom_sales_field_id,
            ];

            UserOverrideAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->override_effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['direct_overrides', 'indirect_overrides', 'office_overrides'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual override values, not old_* backup fields
            $trackableFields = [
                'direct_overrides_amount', 'direct_overrides_type', 'direct_custom_sales_field_id',
                'indirect_overrides_amount', 'indirect_overrides_type', 'indirect_custom_sales_field_id',
                'office_overrides_amount', 'office_overrides_type', 'office_custom_sales_field_id',
            ];
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

            UserOverrideAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->override_effective_date,
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

            UserOverrideAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id,
                'position_name' => $positionName,
                'effective_date' => $model->override_effective_date,
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
