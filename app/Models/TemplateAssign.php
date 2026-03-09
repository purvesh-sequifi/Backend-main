<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateAssign extends Model
{
    use HasFactory;

    protected $table = 'template_assigns';

    protected $fillable = [
        'user_id',
        'template_id',
        'assign_id',
    ];
}
