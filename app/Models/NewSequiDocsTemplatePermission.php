<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewSequiDocsTemplatePermission extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_template_permissions';

    protected $fillable = [
        'template_id',
        'category_id',
        'position_id',
        'position_type', // 'permission', 'receipient'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // positionDetail relation
    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    // NewSequiDocsTemplate
    public function NewSequiDocsTemplate(): HasOne
    {
        return $this->hasOne(\App\Models\NewSequiDocsTemplate::class, 'id', 'template_id')->where('is_deleted', '<>', 1);
    }
}
