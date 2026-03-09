<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TierSystem extends Model
{
    use HasFactory, SpatieLogsActivity;

    const TIERED_BASED_ON_INDIVIDUAL_PERFORMANCE = 'Tiered based on Individual performance'; //

    const TIERED_BASED_ON_OFFICE_PERFORMANCE = 'Tiered based on Office Performance'; //

    const TIERED_BASED_ON_HIRING_PERFORMANCE = 'Tiered based on hiring/ recruitment performance';

    const TIERED_BASED_ON_JOB_METRICS_PERFORMANCE = 'Tiered based on job metrics'; //

    const TIERED_BASED_ON_DOWN_LINE_PERFORMANCE = 'Tiered based on Downline Performance'; //

    const TIERED_BASED_ON_JOB_METRICS_EXACT_MATCH_PERFORMANCE = 'Tiered based on job metrics (Exact Match)'; //

    protected $table = 'tier_systems';

    protected $fillable = [
        'value',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function metrics(): HasMany
    {
        return $this->hasMany(TierMetrics::class, 'tier_system_id');
    }
}
