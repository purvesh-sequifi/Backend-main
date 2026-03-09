<?php

namespace App\Observers;

use App\Jobs\SaleMasterJob;
use App\Models\CompanyProfile;
use App\Models\LegacyApiRowData;
use App\Models\User;

class HubSpotCurrentEnergyObserver
{
    /**
     * Handle the LegacyApiRowData "created" event.
     *
     * @param  \App\Models\LegacyApiRowData  $legacyApiRowData
     */
    public function created(): void
    {
        $user = User::find(1);
        $dataForPusher = [];
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            dispatch(new SaleMasterJob($user, true, $dataForPusher));
        } else {
            dispatch(new SaleMasterJob($user, false, $dataForPusher));
        }
    }

    /**
     * Handle the LegacyApiRowData "updated" event.
     *
     * @param  \App\Models\LegacyApiRowData  $legacyApiRowData
     */
    public function updated(): void
    {
        $user = User::find(1);
        $dataForPusher = [];
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            dispatch(new SaleMasterJob($user, true, $dataForPusher));
        } else {
            dispatch(new SaleMasterJob($user, false, $dataForPusher));
        }
    }

    /**
     * Handle the LegacyApiRowData "deleted" event.
     */
    public function deleted(LegacyApiRowData $legacyApiRowData): void
    {
        //
    }

    /**
     * Handle the LegacyApiRowData "restored" event.
     */
    public function restored(LegacyApiRowData $legacyApiRowData): void
    {
        //
    }

    /**
     * Handle the LegacyApiRowData "force deleted" event.
     */
    public function forceDeleted(LegacyApiRowData $legacyApiRowData): void
    {
        //
    }
}
