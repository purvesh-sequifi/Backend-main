<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportCategory extends Model
{
    use HasFactory;

    protected $table = 'import_categories';

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function categoryDetails(): HasMany
    {
        return $this->hasMany(ImportCategoryDetails::class, 'category_id', 'id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ImportTemplate::class, 'category_id', 'id');
    }
}
