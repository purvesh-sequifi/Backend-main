<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GroupPermissions extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'group_permissions';

    protected $fillable = [
        'group_id',
        'role_id',
        'group_policies_id',
        'policies_tabs_id',
        'permissions_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function policydata(): BelongsTo
    {
        return $this->belongsTo(GroupPolicies::class, 'group_policies_id', 'id');
        // return $this->hasMany(GroupPolicies::class, 'group_policies_id', 'id');
    }

    public function groupmaster(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GroupMaster::class, 'group_id', 'id');
    }

    public function grouppolicydata(): HasOne
    {
        return $this->hasOne(GroupPolicies::class, 'id', 'group_policies_id')->select('id', 'policies');
    }

    public function policytabb(): BelongsTo
    {
        return $this->belongsTo(PoliciesTabs::class, 'policies_tabs_id', 'id');
    }

    public function grouprole(): HasOne
    {
        return $this->hasOne(Roles::class, 'id', 'role_id');
    }

    public function permissions(): HasOne
    {
        return $this->hasOne(Permissions::class, 'id', 'permissions_id');
    }
}
