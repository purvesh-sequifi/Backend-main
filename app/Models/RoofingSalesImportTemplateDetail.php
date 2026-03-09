<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoofingSalesImportTemplateDetail extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'roofing_sales_import_template_details';

    protected $fillable = [
        'template_id',
        'field_id',
        'excel_field',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(RoofingSalesImportField::class, 'field_id');
    }
}
