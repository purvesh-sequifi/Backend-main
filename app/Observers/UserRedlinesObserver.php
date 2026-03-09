<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserRedlines;
use App\Models\UserRedlinesAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;

class UserRedlinesObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserRedlines "created" event.
     */
    public function created(UserRedlines $model): void
    {
        $this->logHistory($model, 'create');
    }

    /**
     * Handle the UserRedlines "updated" event.
     */
    public function updated(UserRedlines $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
    }

    /**
     * Handle the UserRedlines "deleted" event.
     */
    public function deleted(UserRedlines $model): void
    {
        $this->logHistory($model, 'delete');
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserRedlines $model, string $changeType): void
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
            $prevRecord = UserRedlines::withTrashed()
                ->where('user_id', $userId)
                ->where('product_id', $model->product_id)
                ->where('core_position_id', $model->core_position_id)
                ->where('self_gen_user', $model->self_gen_user)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['redline', 'redline_type', 'redline_amount_type']);

            $oldRedline = $prevRecord?->redline;
            $oldType = $prevRecord?->redline_type;
            $newRedline = $model->redline;
            $newType = $model->redline_type;

            // Skip if no actual change
            $oldVal = (float) ($oldRedline ?? 0);
            $newVal = (float) ($newRedline ?? 0);

            if ($oldVal === $newVal && $oldType === $newType) {
                return;
            }

            // Skip if both are 0
            if ($oldVal === 0.0 && $newVal === 0.0) {
                return;
            }

            $oldValues = [
                'redline' => $oldRedline,
                'redline_type' => $oldType,
            ];

            $newValues = [
                'redline' => $newRedline,
                'redline_type' => $newType,
            ];

            UserRedlinesAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id ?? null,
                'position_name' => $positionName,
                'effective_date' => $model->start_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['redline', 'redline_type'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual redline values, not old_* backup fields
            $trackableFields = ['redline', 'redline_type', 'redline_amount_type'];
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

            UserRedlinesAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id ?? null,
                'position_name' => $positionName,
                'effective_date' => $model->start_date,
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

            UserRedlinesAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id ?? null,
                'position_name' => $positionName,
                'effective_date' => $model->start_date,
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
