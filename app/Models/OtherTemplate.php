<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherTemplate extends Model
{
    use HasFactory;

    protected $table = 'other_templates';

    protected $fillable = [
        'created_by',
        'categery_id',
        'template_name',
        'template_link',
        'template_description',
        'is_sign_required_for_hire', //  DEFAULT '1' COMMENT '0 for not required, 1 for required'
    ];

    protected $hidden = [
        'created_at',
    ];
}
