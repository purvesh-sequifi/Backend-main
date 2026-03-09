<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class GroupPolicies extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'group_policies';

    protected $fillable = [
        'role_id',
        'policies',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function policytab(): HasMany
    {
        return $this->hasMany(\App\Models\PoliciesTabs::class, 'policies_id', 'id')->with('permission');
    }

    public function policytabdata(): HasMany
    {
        return $this->hasMany(\App\Models\PoliciesTabs::class, 'policies_id', 'id');
    }

    public function scopeFilterPolicies($query)
    {
        // Get statuses of S-Clearance, SequiCRM and Arena
        $crmStatuses = DB::table('crms')
            ->whereIn('name', ['S-Clearance', 'SequiCRM', 'SequiArena'])
            ->pluck('status', 'name');

        $sClearanceStatus = $crmStatuses->get('S-Clearance', 0); // Default to 0
        $sequiCrmStatus = $crmStatuses->get('SequiCRM', 0); // Default to 0
        $sequiArenaStatus = $crmStatuses->get('SequiArena', 0); // Default to 0

        // Apply filters based on the statuses
        if ($sClearanceStatus != 1) {
            $query->where('policies', '!=', 'S-Clearance');
        }

        if ($sequiCrmStatus != 1) {
            $query->where('policies', '!=', 'Sequi-CRM');
        }

        if ($sequiArenaStatus != 1) {
            $query->where('policies', '!=', 'Arena');
        }

        return $query;
    }
}
