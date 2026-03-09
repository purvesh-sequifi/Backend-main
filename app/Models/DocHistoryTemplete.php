<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocHistoryTemplete extends Model
{
    use HasFactory;

    protected $table = 'doc_history_for_templete';

    protected $fillable = [
        'user_id',
        'template_id',
        'employee_name',
        'employee_position',
        'Company_Name',
        'manager_name',
        'currentdate',
        'building_no',
        'type',
        'pdf',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function assign(): HasMany
    {
        return $this->hasMany(\App\Models\TemplateAssign::class, 'template_id', 'template_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name');
    }
}
