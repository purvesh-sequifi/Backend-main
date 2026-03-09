<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class TiersSchema extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'tiers_schema';

    const PROGRESSIVE = 'Progressive';

    const RETROACTIVE = 'Retroactive';

    protected $fillable = [
        'id',
        'schema_prefix',
        'schema_name',
        'schema_description',
        'tier_system_id',
        'tier_metrics_id',
        'tier_metrics_type',
        'tier_type',
        'tier_duration_id',
        'levels',
        'start_day',
        'end_day',
        'start_end_day',
        'next_reset_date',
        'deleted_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tiersLevels(): HasMany
    {
        return $this->hasMany(TiersLevel::class, 'tiers_schema_id', 'id')
            ->where('effective_date', function ($query) {
                $query->select('effective_date')
                    ->from('tiers_levels as tl')
                    ->whereColumn('tl.tiers_schema_id', 'tiers_levels.tiers_schema_id')
                    ->orderBy('effective_date', 'desc')
                    ->limit(1);
            });
    }

    public function positionProduct(): HasMany
    {
        return $this->hasMany(PositionProduct::class, 'product_id', 'id');
    }

    public function tier_system(): HasOne
    {
        return $this->hasOne(TierSystem::class, 'id', 'tier_system_id');
    }

    public function tier_metrics(): HasOne
    {
        return $this->hasOne(TierMetrics::class, 'id', 'tier_metrics_id');
    }

    public function tier_duration(): HasOne
    {
        return $this->hasOne(TierDuration::class, 'id', 'tier_duration_id');
    }

    public function tiers_levels_by_id(): HasMany
    {
        return $this->hasMany(TiersLevel::class, 'tiers_schema_id', 'id');
    }

    public function tiers_levelsbyid()
    {
        $currentDate = Carbon::today()->format('Y-m-d');
        // Check if the current instance has an ID
        if ($this->id) {
            // Step 1: Get the closest effective date for the current tiers_schema
            $closestDate = TiersLevel::where('tiers_schema_id', $this->id)
                ->where(function ($query) use ($currentDate) {
                    $query->whereDate('effective_date', '<=', $currentDate)
                        ->orWhereDate('effective_date', '>', $currentDate);
                })
                ->orderByRaw('CASE WHEN effective_date <= ? THEN 0 ELSE 1 END, effective_date DESC', [$currentDate])
                ->limit(1)
                ->value('effective_date');

            // Step 2: Return the related tiers_levels based on the closest effective_date
            return TiersLevel::where('tiers_schema_id', $this->id)
                ->whereDate('effective_date', $closestDate)
                ->orderBy('id', 'asc')->get();
        } else {
            // If $this->id is null, return an empty relation or handle the error
            return collect();
        }
    }

    public function tiersByPositionCommission(): HasMany
    {
        return $this->hasMany(PositionCommission::class, 'tiers_id', 'id');
    }

    public function tiersByPositionUpfront(): HasMany
    {
        return $this->hasMany(PositionCommissionUpfronts::class, 'tiers_id', 'id');
    }

    public function tiersByPositionOverride(): HasMany
    {
        return $this->hasMany(PositionOverride::class, 'tiers_id', 'id');
    }

    public static function tierspositionexist()
    {
        // Use the query builder to merge results from multiple tables and count distinct position IDs
        $uniqueUsers = PositionCommission::select('position_id')
            ->where('tiers_id', '>', 0)
            ->union(
                PositionCommissionUpfronts::select('position_id')->where('tiers_id', '>', 0)
            )
            ->union(
                PositionOverride::select('position_id')->where('tiers_id', '>', 0)
            )
            ->distinct()
            ->count('position_id'); // Count distinct position_id

        return $uniqueUsers;
    }

    public function positionTiers(): HasMany
    {
        return $this->hasMany(PositionTier::class, 'tiers_schema_id', 'id');
    }
}
