<?php

namespace App\Core\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait SpatieLogsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $logOptions = new LogOptions;
        $logOptions->logAll();
        $logOptions->dontSubmitEmptyLogs();
        $logOptions->logOnlyDirty();

        return $logOptions;
    }
}
