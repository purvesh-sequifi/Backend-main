<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SequiDocsTemplateCategories extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'template_categories';

    protected $fillable = [
        'categories',
        'id',
        'category_type', // 'system_fixed' , user_editable default user_editable
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // system_fixed_category_array
    public static function system_fixed_category_array()
    {
        return $system_fixed_category_array = [1, 2, 3, 101];
    }

    public function templateCategories(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsTemplate::class, 'categery_id', 'id');
    }

    public function SequiDocsTemplate(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsTemplate::class, 'categery_id', 'id');
    }

    // SequiDocsEmail templates for category 3
    public function SequiDocsEmailTemplates(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsEmailSettings::class, 'category_id', 'id');
    }

    // NewSequiDocsTemplate
    public function NewSequiDocsTemplate(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsTemplate::class, 'category_id', 'id')->where('is_deleted', '=', 0);
    }
}
