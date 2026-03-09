<?php

namespace App\Observers;

use App\Models\CompanyProfile;
use App\Models\Products;
use App\Models\TiersLevel;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TiersLevelsObserver
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    /**
     * Determine the appropriate timezone based on company profile.
     **/
    protected function getCompanyTimezone(): string
    {
        // Fetch the company profile (can be customized to fetch dynamically if needed)
        $company = CompanyProfile::first(); // or fetch based on context

        // Use mailing address timezone if it's set
        if (! empty($company?->mailing_address_time_zone)) {
            return $company->mailing_address_time_zone;
        }

        // If general time_zone is set, map it to a valid timezone using helper
        if (! empty($company?->time_zone)) {
            return Products::mapTimeZone($company->time_zone);
        }

        // Use application default timezone as fallback
        return config('app.timezone'); // fallback
    }

    /**
     * Handle the TiersLevel "created" event.
     */
    public function created(TiersLevel $tiersLevel): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        $data[] = [
            'tiers_schema_id' => $tiersLevel->tiers_schema_id,
            'level' => $tiersLevel->level,
            'to_value' => $tiersLevel->to_value,
            'from_value' => $tiersLevel->from_value,
            'effective_date' => $tiersLevel->effective_date,
            'created_at' => Carbon::now()->setTimezone($timezone)->toDateTimeString(),
        ];

        $this->auditLog->addChange([
            'type' => get_class($tiersLevel),
            'effective_on_date' => $tiersLevel->effective_date,
            'reference_id' => $tiersLevel->tiers_schema_id,
            'event' => 'created',
            'description' => json_encode($data),
            'user_id' => Auth::user()?->id,
        ]);
    }

    /**
     * Handle the TiersLevel "updated" event.
     */
    public function updated(TiersLevel $tiersLevel): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        $changes = $tiersLevel->getChanges();

        $logData = [];
        $logData['level'] = [
            'old' => $tiersLevel->level,
            'new' => $tiersLevel->level,
        ];
        foreach ($changes as $key => $newValue) {
            $oldValue = $tiersLevel->getOriginal($key);
            if ($oldValue != $newValue) {

                // If key is updated_at, convert new to company's timezone
                if ($key === 'updated_at') {
                    $newValue = Carbon::parse($newValue)->setTimezone($timezone)->toDateTimeString();
                }

                $logData[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        if (! empty($logData)) {
            $this->auditLog->addChange([
                'type' => get_class($tiersLevel),
                'effective_on_date' => $tiersLevel->effective_date,
                'reference_id' => $tiersLevel->tiers_schema_id,
                'event' => 'updated',
                'description' => json_encode($logData),
                'user_id' => Auth::user()?->id,
            ]);
        }
    }

    /**
     * Handle the TiersLevel "deleted" event.
     */
    public function deleted(TiersLevel $tiersLevel): void
    {
        //
    }

    /**
     * Handle the TiersLevel "restored" event.
     */
    public function restored(TiersLevel $tiersLevel): void
    {
        //
    }

    /**
     * Handle the TiersLevel "force deleted" event.
     */
    public function forceDeleted(TiersLevel $tiersLevel): void
    {
        //
    }
}
