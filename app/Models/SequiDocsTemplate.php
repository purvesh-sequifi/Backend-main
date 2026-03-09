<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// use Illuminate\Database\Eloquent\SoftDeletes;

class SequiDocsTemplate extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'templates';

    // SELECT `id`, `created_by`, `categery_id`, `template_name`, `template_description`, `is_sign_required_for_hire`, `template_content`, `template_agreements`, `dynamic_value`, `recipient_sign_req`, `self_sign_req`, `add_sign`, `template_comment`, `manager_sign_req`, `completed_step`, `recruiter_sign_req`, `add_recruiter_sign_req`, `created_at`, `updated_at` FROM `templates` WHERE 1

    protected $fillable = [
        'id',
        'categery_id',
        'created_by',
        'template_name',
        'template_description',
        'is_sign_required_for_hire', //  DEFAULT '1' COMMENT '0 for not required, 1 for required'
        'template_content',
        'template_agreements',
        'dynamic_value',
        'recipient_sign_req',
        'self_sign_req',
        'add_sign',
        'template_comment',
        'manager_sign_req',
        'completed_step',
        'recruiter_sign_req',
        'add_recruiter_sign_req',
    ];

    protected $hidden = [
        'created_at',
        // ,'updated_at'
    ];

    public function categories(): HasOne
    {
        return $this->hasOne(\App\Models\SequiDocsTemplateCategories::class, 'id', 'categery_id');
    }

    public function Template(): HasMany
    {
        return $this->hasMany(\App\Models\TemplateMeta::class, 'id', 'template_id');
    }

    public function created_by(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'created_by')->select('id', 'first_name', 'last_name', 'is_super_admin', 'is_manager', 'position_id', 'sub_position_id');
    }

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    // Define a relationship with SequiDocsTemplatePermissions
    public function permissions(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsTemplatePermissions::class, 'template_id', 'id')->where('position_type', 'permission')->where('category_id', '>', 0)->whereHas('positionDetail', function ($query) {
            $query->whereNotNull('position_name');
        })->with('positionDetail');
    }

    // Define a relationship with SequiDocsTemplatePermissions
    public function receipient(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsTemplatePermissions::class, 'template_id', 'id')->where('position_type', 'receipient')->where('category_id', '>', 0)->whereHas('positionDetail', function ($query) {
            $query->whereNotNull('position_name');
        })->with('positionDetail');
    }

    // Define a relationship with SequiDocsSignature
    public function SequiDocsAdditionalSignature(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsSignature::class, 'template_id', 'id')->with('additional_signature_Positions');
    }

    // Define a relationship with SequiDocsEmailSettings
    public function SequiDocsEmailSettings(): HasOne
    {
        return $this->hasOne(\App\Models\SequiDocsEmailSettings::class, 'tempate_id', 'id');
    }

    // Define a relationship with SequiDocsSendAgreementWithTemplate
    public function TemplateAgreements(): HasMany
    {
        return $this->hasMany(\App\Models\SequiDocsSendAgreementWithTemplate::class, 'template_id', 'id');
    }
}
