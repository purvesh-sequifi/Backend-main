<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MilestoneSchemaTrigger extends Model
{
    use HasFactory;

    protected $table = 'milestone_schema_trigger';

    protected $fillable = [
        'id',
        'milestone_schema_id',
        'name',
        'on_trigger',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
