<?php

namespace App\Core\Traits;

use App\Models\BackendSetting;
use App\Models\OverrideSettings;

trait CheckCompanySettingTrait
{
    public function checkSetting()
    {
        $data = [];
        $backendSetting = BackendSetting::first();
        $OverrideSettings = OverrideSettings::first();

        $data['reconcilication'] = $backendSetting['status'];

        return $data;
    }
}
