<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class State extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $fillable = [
        'id',
        'name',
        'state_code',
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

        // Clear states cache when state is updated/deleted
        static::updated(function ($state) {
            Cache::flush();
        });

        static::deleted(function ($state) {
            Cache::flush();
        });
    }

    public function cities(): HasMany
    {
        return $this->hasMany(Cities::class, 'state_id')->select('id', 'state_id', 'name')->orderBy('name', 'ASC');
    }

    public function stateSalesDetail(): HasMany
    {
        return $this->hasMany(SalesMaster::class, 'customer_state', 'state_code')->select('pid', 'customer_state', 'date_cancelled', 'id')->where('date_cancelled', '!=', null);
    }

    public function statePendingSalesDetail(): HasMany
    {
        return $this->hasMany(SalesMaster::class, 'customer_state', 'state_code')->select('pid', 'customer_state', 'date_cancelled', 'id')->where('install_complete_date', null);
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class, 'state_id', 'id')->select('id', 'state_id');
    }

    public function office(): HasMany
    {
        return $this->hasMany(\App\Models\Locations::class, 'state_id', 'id')->where('type', 'Office');
    }

    public function location(): HasMany
    {
        return $this->hasMany(\App\Models\Locations::class, 'state_id', 'id');
    }
}
