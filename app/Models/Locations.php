<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Locations extends Model
{
    use HasFactory, SpatieLogsActivity;

    // use SoftDeletes;
    protected $table = 'locations';

    protected $fillable = [
        'state_id',
        'work_site_id',
        'city_id',
        'installation_partner',
        'redline_min',
        'redline_standard',
        'date_effective',
        // 'marketing_deal_person_id',
        'type',
        'office_name',
        'business_address',
        'business_city',
        'business_state',
        'business_zip',
        'mailing_address',
        'mailing_state',
        'mailing_city',
        'mailing_zip',
        'redline_max',
        'created_by',
        'lat',
        'long',
        'office_status',
        'general_code',
        'time_zone',
        'everee_location_id',
        'everee_json_response',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Boot method for cache invalidation
     */
    protected static function boot()
    {
        parent::boot();

        // Clear states cache when location is created/updated/deleted
        static::created(function ($location) {
            if ($location->type === 'Office') {
                Cache::flush();
            }
        });

        static::updated(function ($location) {
            if ($location->type === 'Office' || $location->isDirty(['state_id', 'office_name', 'archived_at'])) {
                Cache::flush();
            }
        });

        static::deleted(function ($location) {
            if ($location->type === 'Office') {
                Cache::flush();
            }
        });
    }

    // public function getEvents(){
    //     return $this->hasOne('State','id');
    //   }
    // public function State()
    // {
    //     return $this->belongsTo(State::class,'state_id');
    // }
    // public function Citis()
    // {
    //     return $this->belongsTo(Citis::class,'city_id');
    // }
    public function State(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id');
    }

    public function Cities(): HasOne
    {
        return $this->hasOne(\App\Models\Cities::class, 'id', 'city_id');
    }

    public function createdBy(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'created_by')->select('id', 'first_name', 'last_name', 'image');
    }

    public function marketing(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'marketing_deal_person_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'office_id', 'id');
    }

    public function additionalRedline(): HasMany
    {
        return $this->hasMany(\App\Models\LocationRedlineHistory::class, 'location_id', 'id')->orderBy('id', 'desc');
    }

    public function effectiveRedlineForexport(): HasMany
    {
        return $this->hasMany(\App\Models\LocationRedlineHistory::class, 'location_id', 'id')->orderBy('id', 'desc')->select('location_id', 'redline_standard', 'effective_date');
    }

    public function redline_data(): HasMany
    {
        return $this->hasMany(\App\Models\LocationRedlineHistory::class, 'location_id', 'id')->orderBy('id', 'desc');
    }

    public function currentStateRedLine(): HasOne
    {
        return $this->hasOne(LocationRedlineHistory::class, 'location_id', 'id')->orderBy('effective_date', 'DESC');
    }
}
