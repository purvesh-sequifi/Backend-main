<?php

declare(strict_types=1);

namespace App\Services\FeatureUsage;

/**
 * FeatureUsageCheckerInterface
 * 
 * Interface for feature usage checkers. Each feature that needs
 * usage tracking should implement this interface.
 */
interface FeatureUsageCheckerInterface
{
    /**
     * Check if the feature is currently in use
     * 
     * @return bool True if the feature has any active usage
     */
    public function isInUse(): bool;

    /**
     * Get detailed usage information
     * 
     * Returns an array with:
     * - modules: Array of module information (name, description, path)
     * - records: Array of record counts by type
     * - total_count: Total number of records using this feature
     * - can_disable: Whether the feature can be safely disabled
     * - message: Human-readable message about the usage status
     * 
     * @return array<string, mixed>
     */
    public function getUsageDetails(): array;
}
