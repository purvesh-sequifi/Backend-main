<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TemplateGenerate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'template_id',
        'company_name',
        'company_address',
        'company_date',
        'employee_name',
        'employee_position',
        'manager_name',
        'due',
        'due_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function Template(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsTemplate::class, 'id', 'template_id');
    }

    public function categories(): HasOne
    {
        return $this->hasOne(\App\Models\SequiDocsTemplateCategories::class, 'id', 'template_id');
    }
    //  public function data3()
    // {
    //     return $this->hasOne('App\Models\TemplateMeta', 'id' , 'template_id');
    // }
}
