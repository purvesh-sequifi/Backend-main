<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TierDuration extends Model
{
    use HasFactory, SpatieLogsActivity;

    const PER_PAY_PERIOD = 'Per Pay Period';

    const WEEKLY = 'Weekly';

    const MONTHLY = 'Monthly';

    const QUARTERLY = 'Quarterly';

    const SEMI_ANNUALLY = 'Semi-Annually';

    const ANNUALLY = 'Annually';

    const PER_RECON_PERIOD = 'Per Recon Period';

    const ON_DEMAND = 'On Demand';

    const CONTINUOUS = 'Continuous';

    const WEEK_DAYS = [
        'Sunday' => Carbon::SUNDAY,
        'Monday' => Carbon::MONDAY,
        'Tuesday' => Carbon::TUESDAY,
        'Wednesday' => Carbon::WEDNESDAY,
        'Thursday' => Carbon::THURSDAY,
        'Friday' => Carbon::FRIDAY,
        'Saturday' => Carbon::SATURDAY,
    ];

    protected $table = 'tier_durations';

    protected $fillable = [
        'value',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
