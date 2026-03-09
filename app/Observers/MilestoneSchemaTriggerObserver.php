<?php

namespace App\Observers;

use App\Models\CompanyProfile;
use App\Models\MilestoneSchemaTrigger;
use App\Models\Products;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MilestoneSchemaTriggerObserver
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
     * Handle the MilestoneSchemaTrigger "created" event.
     */
    public function created(MilestoneSchemaTrigger $milestoneSchemaTrigger): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        $data[] = [
            'name' => $milestoneSchemaTrigger->name,
            'on_trigger' => $milestoneSchemaTrigger->on_trigger,
            'created_at' => Carbon::now()->setTimezone($timezone)->toDateTimeString(),
        ];

        $this->auditLog->addChange([
            'type' => get_class($milestoneSchemaTrigger),
            'reference_id' => $milestoneSchemaTrigger->milestone_schema_id,
            'event' => 'created',
            'description' => json_encode($data),
            'user_id' => (Auth::user()?->id) ? Auth::user()?->id : 1,
        ]);
    }

    /**
     * Handle the MilestoneSchemaTrigger "updated" event.
     */
    public function updated(MilestoneSchemaTrigger $milestoneSchemaTrigger): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        $changes = $milestoneSchemaTrigger->getChanges();

        $logData = [];
        foreach ($changes as $key => $newValue) {
            $oldValue = $milestoneSchemaTrigger->getOriginal($key);
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
                'type' => get_class($milestoneSchemaTrigger),
                'reference_id' => $milestoneSchemaTrigger->milestone_schema_id,
                'event' => 'updated',
                'description' => json_encode($logData),
                'user_id' => Auth::user()?->id,
            ]);
        }
    }

    /**
     * Handle the MilestoneSchemaTrigger "deleted" event.
     */
    public function deleted(MilestoneSchemaTrigger $milestoneSchemaTrigger): void
    {
        //
    }

    /**
     * Handle the MilestoneSchemaTrigger "restored" event.
     */
    public function restored(MilestoneSchemaTrigger $milestoneSchemaTrigger): void
    {
        //
    }

    /**
     * Handle the MilestoneSchemaTrigger "force deleted" event.
     */
    public function forceDeleted(MilestoneSchemaTrigger $milestoneSchemaTrigger): void
    {
        //
    }
}
