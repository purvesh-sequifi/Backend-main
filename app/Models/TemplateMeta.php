<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateMeta extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'template_metas';

    protected $fillable = [
        // 'template_id',
        'meta_key',
        'meta_value',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function Template(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsTemplate::class, 'id', 'template_id');
    }
}
