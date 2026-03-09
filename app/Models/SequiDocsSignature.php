<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SequiDocsSignature extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sequi_docs_template_signature';

    protected $fillable = [
        'template_id',
        'category_id',
        'additional_signature',
        'required_check',
        'created_at',
        'updated_at',

    ];

    // user position list
    public function additional_signature_Positions(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'additional_signature');
    }
}
