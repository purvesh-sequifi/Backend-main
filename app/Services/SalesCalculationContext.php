<?php

declare(strict_types=1);

namespace App\Services;

use App\Features\CustomSalesFieldsFeature;
use App\Models\CompanyProfile;
use App\Models\SalesMaster;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

/**
 * SalesCalculationContext
 *
 * A request-scoped context that holds the current sale and company profile
 * during commission calculations. This enables the "Trick Subroutine" approach
 * where PositionCommission models can auto-convert 'custom field' types to
 * 'per sale' without modifying SubroutineTrait.
 *
 * IMPORTANT: Uses request-scoped array cache to prevent race conditions
 * in concurrent requests. Each request has its own isolated context.
 *
 * CONTEXT STACKING: The context now supports nesting. When set() is called
 * while a context already exists, the previous context is pushed onto a stack.
 * When clear() is called, the previous context is restored. This prevents
 * context loss when subroutines call other subroutines.
 *
 * Usage:
 *   1. Before calling subroutines: SalesCalculationContext::set($sale, $companyProfile)
 *   2. Subroutines fetch commissions normally (auto-converted via model event)
 *   3. After subroutines complete: SalesCalculationContext::clear()
 */
class SalesCalculationContext
{
    /**
     * Cache key for the context (request-scoped via array driver)
     */
    private const CACHE_KEY = 'sales_calculation_context';

    /**
     * Cache key for context stack (for nested context support)
     */
    private const STACK_KEY = 'sales_calculation_context_stack';

    /**
     * Cache key for company profile (request-scoped)
     */
    private const COMPANY_CACHE_KEY = 'cached_company_profile';

    /**
     * Set the current sale context for commission calculations
     *
     * If a context already exists, it will be pushed onto a stack and restored
     * when clear() is called. This prevents context loss in nested subroutine calls.
     *
     * @param SalesMaster $sale The sale being processed
     * @param CompanyProfile $companyProfile The company profile for feature checks
     */
    public static function set(SalesMaster $sale, CompanyProfile $companyProfile): void
    {
        // If context already exists, push it onto the stack
        $existingContext = Cache::store('array')->get(self::CACHE_KEY);
        if ($existingContext !== null) {
            $stack = Cache::store('array')->get(self::STACK_KEY, []);
            $stack[] = $existingContext;
            Cache::store('array')->put(self::STACK_KEY, $stack);
        }

        Cache::store('array')->put(self::CACHE_KEY, [
            'sale' => $sale,
            'company_profile' => $companyProfile,
        ]);
    }

    /**
     * Get current sale
     *
     * @return SalesMaster|null
     */
    public static function getSale(): ?SalesMaster
    {
        $context = Cache::store('array')->get(self::CACHE_KEY);
        return $context['sale'] ?? null;
    }

    /**
     * Get current company profile from context
     *
     * @return CompanyProfile|null
     */
    public static function getCompanyProfile(): ?CompanyProfile
    {
        $context = Cache::store('array')->get(self::CACHE_KEY);
        return $context['company_profile'] ?? null;
    }

    /**
     * Get cached company profile (request-scoped, avoids repeated DB queries)
     * Use this instead of CompanyProfile::first() throughout the codebase
     *
     * @return CompanyProfile|null
     */
    public static function getCachedCompanyProfile(): ?CompanyProfile
    {
        return Cache::store('array')->remember(
            self::COMPANY_CACHE_KEY,
            3600, // TTL doesn't matter for array driver - cleared per request
            fn () => CompanyProfile::first()
        );
    }

    /**
     * Check if custom sales fields feature is enabled for current company
     *
     * @return bool
     */
    public static function isCustomFieldsEnabled(): bool
    {
        $companyProfile = self::getCompanyProfile() ?? self::getCachedCompanyProfile();

        if (!$companyProfile) {
            return false;
        }

        return Feature::for($companyProfile)->active(CustomSalesFieldsFeature::NAME);
    }

    /**
     * Check if context is currently set
     *
     * @return bool
     */
    public static function hasContext(): bool
    {
        $context = Cache::store('array')->get(self::CACHE_KEY);
        return isset($context['sale']) && isset($context['company_profile']);
    }

    /**
     * Get the sale ID (pid) if context is set
     *
     * @return int|string|null
     */
    public static function getSaleId(): int|string|null
    {
        return self::getSale()?->pid;
    }

    /**
     * Clear the context (call after subroutine processing completes)
     *
     * If a previous context was pushed onto the stack, it will be restored.
     * This supports nested subroutine calls without losing the outer context.
     */
    public static function clear(): void
    {
        // Check if there's a previous context on the stack to restore
        $stack = Cache::store('array')->get(self::STACK_KEY, []);

        if (!empty($stack)) {
            // Pop the previous context from the stack and restore it
            $previousContext = array_pop($stack);
            Cache::store('array')->put(self::STACK_KEY, $stack);
            Cache::store('array')->put(self::CACHE_KEY, $previousContext);
        } else {
            // No previous context, just clear
            Cache::store('array')->forget(self::CACHE_KEY);
        }
    }

    /**
     * Force clear all context including the stack
     *
     * Use this only when you need to completely reset the context state,
     * such as at the end of a request or in error recovery.
     */
    public static function forceReset(): void
    {
        Cache::store('array')->forget(self::CACHE_KEY);
        Cache::store('array')->forget(self::STACK_KEY);
        Cache::store('array')->forget(self::CACHE_KEY . '_display_pid');
    }

    /**
     * Get the current stack depth (for debugging/monitoring)
     *
     * @return int The number of contexts currently on the stack
     */
    public static function getStackDepth(): int
    {
        $stack = Cache::store('array')->get(self::STACK_KEY, []);
        return count($stack);
    }

    /**
     * Set a display context from just a PID
     * 
     * This is used when we know the PID but don't have a SalesMaster instance.
     * Useful for display/report contexts where full subroutine context isn't needed.
     * 
     * @param string|int $pid The sale PID
     * @return bool True if context was set successfully
     */
    public static function setFromPid(string|int $pid): bool
    {
        $sale = SalesMaster::where('pid', $pid)->first();
        if (!$sale) {
            return false;
        }

        $companyProfile = self::getCachedCompanyProfile();
        if (!$companyProfile) {
            return false;
        }

        self::set($sale, $companyProfile);
        return true;
    }

    /**
     * Set a display-only PID context (lightweight, no sale object loaded)
     * 
     * This sets just the PID for custom field value lookups without loading
     * the full SalesMaster object. Use when you only need to calculate
     * custom field values for display.
     * 
     * @param string|int $pid The sale PID
     */
    public static function setDisplayPid(string|int $pid): void
    {
        Cache::store('array')->put(self::CACHE_KEY . '_display_pid', $pid);
    }

    /**
     * Set display PID from the current request if available
     * 
     * This automatically detects PID from common request parameters.
     * Call this at the start of display/report endpoints.
     * 
     * @return bool True if a PID was found and set
     */
    public static function setDisplayPidFromRequest(): bool
    {
        $request = request();
        
        // Check common parameter names for PID
        $pid = $request->input('pid') 
            ?? $request->input('sale_pid')
            ?? $request->route('pid')
            ?? null;
        
        if ($pid) {
            self::setDisplayPid($pid);
            return true;
        }
        
        return false;
    }

    /**
     * Get the display PID if set
     * 
     * @return string|int|null
     */
    public static function getDisplayPid(): string|int|null
    {
        return Cache::store('array')->get(self::CACHE_KEY . '_display_pid');
    }

    /**
     * Clear the display PID
     */
    public static function clearDisplayPid(): void
    {
        Cache::store('array')->forget(self::CACHE_KEY . '_display_pid');
    }
}
