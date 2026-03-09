<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PestSalesImportTemplate extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'pest_sales_import_templates';

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function templateDetails(): HasMany
    {
        return $this->hasMany(PestSalesImportTemplateDetail::class, 'template_id', 'id');
    }
}
