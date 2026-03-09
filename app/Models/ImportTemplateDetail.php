<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImportTemplateDetail extends Model
{
    use HasFactory;

    protected $table = 'import_template_details';

    protected $fillable = [
        'template_id',
        'category_detail_id',
        'excel_field',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function categoryDetails(): HasOne
    {
        return $this->hasOne(ImportCategoryDetails::class, 'id', 'category_detail_id');
    }
}
