<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'departments';

    protected $fillable = [
        'name',
        'parent_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function subdepartmant(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->with('subdepartmant');
    }

    public function position(): HasMany
    {
        return $this->hasMany(\App\Models\Positions::class, 'department_id', 'id')->where('setup_status', '=', 1)->where('position_name', '!=', 'Super Admin');
    }
}
