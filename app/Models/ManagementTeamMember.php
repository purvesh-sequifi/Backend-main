<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManagementTeamMember extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'management_team_members';

    protected $fillable = [
        'team_id',
        'team_lead_id',
        'team_member_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function member(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'id', 'team_member_id')->select('id', 'first_name', 'last_name', 'image', 'position_id', 'is_manager', 'sub_position_id');
    }

    public function teamLeaderName(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'team_lead_id')->select('id', 'first_name', 'last_name', 'image');
    }

    // public function

}
