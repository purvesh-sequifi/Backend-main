<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Teams extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function teamLead(): HasOne
    {
        return $this->hasOne(ManagementTeamMember::class, 'team_id', 'id')->select('team_id', 'team_lead_id')->with('teamLeaderName');
    }
}
