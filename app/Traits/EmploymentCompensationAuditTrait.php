<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait for Employment Compensation Audit History tracking.
 * Shared logic for all compensation-related observers.
 */
trait EmploymentCompensationAuditTrait
{
    /**
     * Handle events immediately (not after commit).
     * This ensures audit logging works with manual DB transactions (e.g., profile updates).
     * Audit records will roll back with the transaction if it fails, maintaining data integrity.
     */
    public bool $afterCommit = false;

    /**
     * Position update API routes that indicate bulk updates.
     * These routes modify compensation for multiple users at once.
     */
    protected array $positionUpdateRoutes = [
        // Main position routes (routes/sequifi/v2/position/auth.php)
        'position/add-position-commission',
        'position/add-position-wages',
        'position/add-position-upfront',
        'position/add-position-deduction',
        'position/add-position-override',
        'position/add-position-settlement',
        'update-position',
        // Position-specific module routes
        'positioncommission/',
        'positionupfront/',
        'positionoverride/',
        'positiondeduction/',
        'positionsettlement/',
        'position_wages/',
    ];

    /**
     * Check if we should skip audit logging for bulk operations.
     * Position updates affect many users and can cause timeout issues.
     */
    protected function shouldSkipForBulkOperation(): bool
    {
        $uri = request()->path() ?? '';

        foreach ($this->positionUpdateRoutes as $route) {
            if (str_contains($uri, $route)) {
                return true; // Skip audit for bulk position updates
            }
        }

        // Skip for imports
        if (str_contains($uri, 'import')) {
            return true;
        }

        return false;
    }

    /**
     * Detect the source of the change (user_profile or position_update).
     */
    protected function detectChangeSource(): string
    {
        $uri = request()->path() ?? '';

        foreach ($this->positionUpdateRoutes as $route) {
            if (str_contains($uri, $route)) {
                return 'position_update';
            }
        }

        // Check for import routes
        if (str_contains($uri, 'import')) {
            return 'import';
        }

        // Check for console commands
        if (app()->runningInConsole()) {
            return 'console';
        }

        // Default to user profile
        return 'user_profile';
    }

    /**
     * Get the user ID from the model.
     */
    protected function getUserIdFromModel(Model $model): ?int
    {
        return $model->user_id ?? null;
    }

    /**
     * Get current user making the change.
     */
    protected function getChangedBy(): ?int
    {
        return auth()->id();
    }

    /**
     * Get IP address of the request.
     */
    protected function getIpAddress(): ?string
    {
        return request()?->ip();
    }

    /**
     * Get user agent of the request.
     */
    protected function getUserAgent(): ?string
    {
        return request()?->userAgent();
    }

    /**
     * Get reason for change from request if provided.
     * Admin can pass 'audit_reason' in the request to explain the change.
     */
    protected function getChangeReason(): ?string
    {
        return request()?->input('audit_reason');
    }

    /**
     * Get trackable fields for this observer.
     * Override in each observer to specify which fields to track.
     * Return empty array to track ALL fields (except excluded).
     */
    protected function getTrackableFields(): array
    {
        return []; // Empty = track all fields
    }

    /**
     * Check if a field should be tracked.
     */
    protected function shouldTrackField(string $field): bool
    {
        $trackable = $this->getTrackableFields();
        $excluded = $this->getExcludedFields();

        // Always exclude these fields
        if (in_array($field, $excluded)) {
            return false;
        }

        // If trackable is empty, track all non-excluded fields
        if (empty($trackable)) {
            return true;
        }

        // Only track if in trackable list
        return in_array($field, $trackable);
    }

    /**
     * Get all non-null attributes from model for create/delete events.
     * Only includes trackable fields.
     */
    protected function getAllAttributes(Model $model): array
    {
        $data = [];
        $attributes = $model->getAttributes();

        foreach ($attributes as $key => $value) {
            if (!$this->shouldTrackField($key)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Get only changed fields with old and new values.
     * Only includes trackable fields that actually changed.
     */
    protected function getChangedFields(Model $model): array
    {
        $changedFields = [];
        $oldValues = [];
        $newValues = [];

        $dirty = $model->getDirty();

        foreach ($dirty as $field => $newValue) {
            // Skip if not a trackable field
            if (!$this->shouldTrackField($field)) {
                continue;
            }

            $oldValue = $model->getOriginal($field);

            // Only log if values actually different (type-safe comparison)
            // Also handle null vs empty string comparisons
            if ($this->hasValueChanged($oldValue, $newValue)) {
                $changedFields[] = $field;
                $oldValues[$field] = $oldValue;
                $newValues[$field] = $newValue;
            }
        }

        if (empty($changedFields)) {
            return [];
        }

        return [
            'fields' => $changedFields,
            'old' => $oldValues,
            'new' => $newValues,
        ];
    }

    /**
     * Check if a value has actually changed.
     * Handles null, empty string, and numeric comparisons properly.
     */
    protected function hasValueChanged($oldValue, $newValue): bool
    {
        // Treat null and empty string as equivalent for tracking purposes
        $oldNormalized = $this->normalizeValue($oldValue);
        $newNormalized = $this->normalizeValue($newValue);

        // If both normalized values are "empty", no change
        if ($oldNormalized === '' && $newNormalized === '') {
            return false;
        }

        // Compare as strings to handle numeric vs string comparisons
        return (string) $oldNormalized !== (string) $newNormalized;
    }

    /**
     * Normalize a value for comparison.
     */
    protected function normalizeValue($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $value;
    }

    /**
     * Fields to exclude from history tracking.
     */
    protected function getExcludedFields(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Prevent duplicate logging using static array.
     */
    protected function shouldSkipDuplicate(Model $model): bool
    {
        static $processingRecords = [];

        $key = get_class($model) . '_' . $model->id . '_' . time();

        if (isset($processingRecords[$key])) {
            return true;
        }

        $processingRecords[$key] = true;

        // Clean up old entries
        if (count($processingRecords) > 100) {
            $processingRecords = array_slice($processingRecords, -50, 50, true);
        }

        return false;
    }
}

