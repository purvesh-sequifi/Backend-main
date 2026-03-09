<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportTemplate extends Model
{
    use HasFactory;

    protected $table = 'import_templates';

    protected $fillable = [
        'template_name',
        'category_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function templateDetails(): HasMany
    {
        return $this->hasMany(ImportTemplateDetail::class, 'template_id', 'id');
    }
}
