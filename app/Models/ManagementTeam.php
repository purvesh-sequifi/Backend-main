<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManagementTeam extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'management_teams';

    protected $fillable = [
        'id',
        'team_lead_id',
        'location_id',
        'team_name',
        'type',
        'office_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function State(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'location_id');
    }

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'location_id');
    }

    public function Team(): HasMany
    {
        return $this->hasMany(\App\Models\ManagementTeamMember::class, 'team_id', 'id')->with('member');
    }

    public function user(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'id', 'team_lead_id')->select('id', 'first_name', 'last_name', 'image', 'team_id', 'position_id', 'sub_position_id', 'dismiss')->where('dismiss', 0);
    }
}
