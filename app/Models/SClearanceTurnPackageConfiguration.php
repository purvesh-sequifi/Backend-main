<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SClearanceTurnPackageConfiguration extends Model
{
    use HasFactory;

    protected $table = 's_clearance_turn_package_configurations';

    protected $fillable = [
        'name',
        'code',
        'description',
    ];
}
