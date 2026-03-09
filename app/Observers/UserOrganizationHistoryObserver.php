<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserOrganizationHistory;
use App\Models\UserOrganizationAuditHistory;
use App\Traits\EmploymentCompensationAuditTrait;
use Illuminate\Support\Facades\Cache;

class UserOrganizationHistoryObserver
{
    use EmploymentCompensationAuditTrait;

    /**
     * Handle the UserOrganizationHistory "created" event.
     */
    public function created(UserOrganizationHistory $model): void
    {
        $this->logHistory($model, 'create');
        $this->clearUserCache($model->user_id);
    }

    /**
     * Handle the UserOrganizationHistory "updated" event.
     */
    public function updated(UserOrganizationHistory $model): void
    {
        if ($this->shouldSkipDuplicate($model)) {
            return;
        }

        $this->logHistory($model, 'update');
        $this->clearUserCache($model->user_id);
    }

    /**
     * Handle the UserOrganizationHistory "deleted" event.
     */
    public function deleted(UserOrganizationHistory $model): void
    {
        $this->logHistory($model, 'delete');
        $this->clearUserCache($model->user_id);
    }

    /**
     * Clear the cached user data (preserves original functionality).
     */
    protected function clearUserCache(int $userId): void
    {
        Cache::forget("user:data:{$userId}");
    }

    /**
     * Log history for changes.
     */
    protected function logHistory(UserOrganizationHistory $model, string $changeType): void
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
            $prevRecord = UserOrganizationHistory::withTrashed()
                ->where('user_id', $userId)
                ->where('id', '<', $model->id)
                ->orderBy('id', 'desc')
                ->first(['manager_id', 'team_id', 'position_id', 'sub_position_id', 'is_manager']);

            // Skip if no actual change in key fields
            $managerChanged = ($prevRecord?->manager_id !== $model->manager_id);
            $teamChanged = ($prevRecord?->team_id !== $model->team_id);
            $posChanged = ($prevRecord?->position_id !== $model->position_id);
            $subPosChanged = ($prevRecord?->sub_position_id !== $model->sub_position_id);
            $isManagerChanged = ($prevRecord?->is_manager !== $model->is_manager);

            if (!$managerChanged && !$teamChanged && !$posChanged && !$subPosChanged && !$isManagerChanged) {
                return;
            }

            $oldValues = [
                'manager_id' => $prevRecord?->manager_id,
                'team_id' => $prevRecord?->team_id,
                'position_id' => $prevRecord?->position_id,
                'sub_position_id' => $prevRecord?->sub_position_id,
                'is_manager' => $prevRecord?->is_manager,
            ];

            $newValues = [
                'manager_id' => $model->manager_id,
                'team_id' => $model->team_id,
                'position_id' => $model->position_id,
                'sub_position_id' => $model->sub_position_id,
                'is_manager' => $model->is_manager,
            ];

            UserOrganizationAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id ?? null,
                'position_name' => $positionName,
                'effective_date' => $model->effective_date,
                'change_type' => 'create',
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => ['organization'],
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        } elseif ($changeType === 'update') {
            // Only track changes to actual organization values, not old_* backup fields
            $trackableFields = ['manager_id', 'team_id', 'position_id', 'sub_position_id', 'is_manager'];
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

            UserOrganizationAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id ?? null,
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

            UserOrganizationAuditHistory::create([
                'source_id' => $model->id,
                'user_id' => $userId,
                'product_id' => $model->product_id ?? null,
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
