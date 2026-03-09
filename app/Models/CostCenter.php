<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'cost_centers';

    protected $fillable = [
        'name',
        'parent_id',
        'code',
        'description',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function chields(): HasMany
    {
        // echo"DSAD";die;
        return $this->hasMany(self::class, 'parent_id')->with('chields');
    }
    // public function subCostCenterCount()
    // {
    //     return $this->hasMany(self::class, 'parent_id')->count();
    // }

}
