<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SequiDocsTemplatePermissions extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sequi_docs_template_permissions';

    protected $fillable = [
        'template_id',
        'category_id',
        'position_id',
        'position_type',
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

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id')->select('id', 'position_name');
    }

    /**
     * Get the user that owns the SequiDocsTemplatePermissions
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SequiDocsTemplate::class, 'template_id', 'id');
    }
}
