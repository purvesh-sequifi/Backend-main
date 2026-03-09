<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TurfSalesImportTemplate extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'turf_sales_import_templates';

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function templateDetails(): HasMany
    {
        return $this->hasMany(TurfSalesImportTemplateDetail::class, 'template_id', 'id');
    }
}
