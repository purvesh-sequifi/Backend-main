<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SequiDocsSendAgreementWithTemplate extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sequi_docs_send_agreement_with_templates';

    protected $fillable = [
        'template_id',
        'categery_id',
        'position_id',
        'aggrement_template_id',
    ];

    protected $hidden = [
        'created_at',
    ];

    // relations
}
