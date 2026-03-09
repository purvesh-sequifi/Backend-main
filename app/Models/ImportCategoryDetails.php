<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportCategoryDetails extends Model
{
    use HasFactory;

    protected $table = 'import_category_details';

    protected $fillable = [
        'category_id',
        'name',
        'label',
        'sequence',
        'is_mandatory',
        'is_custom',
        'section_name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
