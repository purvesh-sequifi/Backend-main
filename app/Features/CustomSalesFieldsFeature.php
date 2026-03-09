<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\CompanyProfile;

class CustomSalesFieldsFeature
{
    /**
     * The feature name constant used across the application.
     * Use this constant instead of hardcoding the string 'custom-sales-fields'.
     */
    public const NAME = 'custom-sales-fields';

    /**
     * The feature name used for identification (Laravel Pennant)
     */
    public string $name = self::NAME;

    /**
     * Resolve the feature's initial value for the given scope.
     * 
     * @param CompanyProfile $company The company to check
     * @return bool Default to disabled
     */
    public function resolve(CompanyProfile $company): bool
    {
        // Default: Feature is disabled for all companies
        // Super admins enable it per company via Blade dashboard
        return false;
    }
}
