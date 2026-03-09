<?php

namespace App\Services;

/**
 * Request-scoped service for collecting audit log changes
 * Replaces static properties in Observers to prevent data bleeding in Octane
 *
 * CRITICAL: This service is registered as a singleton in AppServiceProvider,
 * which means it gets a fresh instance per request in Octane's worker model.
 */
class AuditLogService
{
    /**
     * Changes collected during current request
     */
    protected array $changes = [];

    /**
     * Add a change to the audit log
     */
    public function addChange(array $change): void
    {
        $this->changes[] = $change;
    }

    /**
     * Get all changes collected in this request
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Clear all changes (typically not needed as service is request-scoped)
     */
    public function clearChanges(): void
    {
        $this->changes = [];
    }

    /**
     * Check if there are any changes
     */
    public function hasChanges(): bool
    {
        return !empty($this->changes);
    }

    /**
     * Get count of changes
     */
    public function count(): int
    {
        return count($this->changes);
    }
}

