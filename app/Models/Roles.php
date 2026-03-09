<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roles extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function grouppolicy(): HasMany
    {
        return $this->hasMany(\App\Models\GroupPolicies::class, 'role_id', 'id')->with('policytab');
    }

    // public function policiesTab() {
    //     return $this->hasOne('App\Models\GroupPolicies', 'role_id', 'id');
    // }
}
