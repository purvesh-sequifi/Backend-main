<?php

namespace App\Observers;

use App\Models\CompanyProfile;
use App\Models\Products;
use App\Models\TiersSchema;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TiersSchemaObserver
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
     * Handle the TiersSchema "created" event.
     *
     * @param  \App\Models\TiersSchema  $TiersSchema
     */
    public function created(TiersSchema $tiersSchema): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        $data[] = [
            'schema_name' => $tiersSchema->schema_name,
            'schema_description' => $tiersSchema->schema_description,
            'tier_system_id' => $tiersSchema->tier_system_id,
            'tier_metrics_id' => $tiersSchema->tier_metrics_id,
            'tier_type' => $tiersSchema->tier_type,
            'tier_duration_id' => $tiersSchema->tier_duration_id,
            'start_day' => $tiersSchema->start_day,
            'end_day' => $tiersSchema->end_day,
            'levels' => $tiersSchema->levels,
            'created_at' => Carbon::now()->setTimezone($timezone)->toDateTimeString(),
        ];

        $this->auditLog->addChange([
            'type' => get_class($tiersSchema),
            'reference_id' => $tiersSchema->id,
            'event' => 'created',
            'description' => json_encode($data),
            'user_id' => Auth::user()?->id,
        ]);
    }

    public function updated(TiersSchema $tiersSchema): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        if (! $tiersSchema->getOriginal('deleted_at')) {
            $changes = $tiersSchema->getChanges();

            $logData = [];
            foreach ($changes as $key => $newValue) {
                if (! in_array($key, ['next_reset_date'])) {
                    $oldValue = $tiersSchema->getOriginal($key);
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
            }

            if (! empty($logData)) {
                $this->auditLog->addChange([
                    'type' => get_class($tiersSchema),
                    'reference_id' => $tiersSchema->id,
                    'event' => 'updated',
                    'description' => json_encode($logData),
                    'user_id' => Auth::user()?->id,
                ]);
            }
        } else {
            $changes = $tiersSchema->getChanges();

            $logData = [];
            foreach ($changes as $key => $newValue) {
                if (! in_array($key, ['next_reset_date'])) {
                    $oldValue = $tiersSchema->getOriginal($key);
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
            }

            if (! empty($logData)) {
                $this->auditLog->addChange([
                    'type' => get_class($tiersSchema),
                    'reference_id' => $tiersSchema->id,
                    'event' => 'restored',
                    'description' => json_encode($logData),
                    'user_id' => Auth::user()?->id,
                ]);
            }
        }
    }

    /**
     * Handle the TiersSchema "deleted" event.
     */
    public function deleted(TiersSchema $TiersSchema): void
    {
        // Retrieve the company's applicable timezone
        $timezone = $this->getCompanyTimezone();

        $data[] = [
            'deleted_at' => Carbon::now()->setTimezone($timezone)->toDateTimeString(),
        ];

        $this->auditLog->addChange([
            'type' => get_class($TiersSchema),
            'reference_id' => $TiersSchema->id,
            'event' => 'deleted',
            'description' => json_encode($data),
            'user_id' => Auth::user()?->id,
        ]);
    }

    /**
     * Handle the TiersSchema "restored" event.
     */
    public function restored(TiersSchema $TiersSchema): void
    {
        //
    }

    /**
     * Handle the TiersSchema "force deleted" event.
     */
    public function forceDeleted(TiersSchema $TiersSchema): void
    {
        //
    }
}
